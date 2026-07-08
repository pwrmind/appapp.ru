<?php
// parser.php
require_once 'config.php';

function clean_extension($string) {
    return preg_replace('/[^a-zA-Z0-9]/', '', $string);
}

function download_remote_file($file_url, $save_dir, $cookies = null) {
    if (empty($file_url)) return null;
    $path_info = pathinfo(parse_url($file_url, PHP_URL_PATH));
    $extension = !empty($path_info['extension']) ? $path_info['extension'] : 'png';
    $extension = substr(clean_extension($extension), 0, 4);
    $local_name = uniqid('pwa_', true) . '.' . $extension;
    $local_path = $save_dir . $local_name;

    $ch = curl_init($file_url);
    $fp = @fopen($local_path, 'wb');
    if (!$fp) return null;

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_USERAGENT,
        'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36');
    if (!empty($cookies)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }

    $success = curl_exec($ch);
    fclose($fp);

    if ($success && filesize($local_path) > 0) {
        return $local_path;
    } else {
        @unlink($local_path);
        return null;
    }
}

function curl_get($url, $accept = 'application/json, text/plain, */*', $referer = null, $cookies = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    $headers = [
        'Accept: ' . $accept,
        'User-Agent: Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36'
    ];
    if ($referer) {
        $headers[] = 'Referer: ' . $referer;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if (!empty($cookies)) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$response, $httpCode];
}

function looks_like_json($str) {
    $trimmed = ltrim($str);
    return isset($trimmed[0]) && ($trimmed[0] === '{' || $trimmed[0] === '[');
}

function fetch_manifest($site_url, $explicit_manifest_url = null, $cookies = null) {
    // 1. Явный URL манифеста
    if (!empty($explicit_manifest_url)) {
        if (parse_url($explicit_manifest_url, PHP_URL_SCHEME) === null) {
            $explicit_manifest_url = rtrim($site_url, '/') . '/' . ltrim($explicit_manifest_url, '/');
        }
        list($response, $httpCode) = curl_get(
            $explicit_manifest_url,
            'application/manifest+json, application/json, text/plain, */*',
            $site_url . '/',
            $cookies
        );
        if ($httpCode === 200 && $response && looks_like_json($response)) {
            return $response;
        }
    }

    // 2. Стандартный /manifest.json
    $url = rtrim($site_url, '/') . '/manifest.json';
    list($response, $httpCode) = curl_get(
        $url,
        'application/manifest+json, application/json, text/plain, */*',
        $site_url . '/',
        $cookies
    );
    if ($httpCode === 200 && $response && looks_like_json($response)) {
        return $response;
    }

    // 3. Поиск ссылки в HTML
    list($html, $httpCode) = curl_get(
        $site_url,
        'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        null,
        $cookies
    );
    if ($httpCode !== 200 || !$html) return false;

    if (preg_match('/<link[^>]+rel=["\']manifest["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
        $manifestPath = $matches[1];
        if (parse_url($manifestPath, PHP_URL_SCHEME) === null) {
            $manifestUrl = rtrim($site_url, '/') . '/' . ltrim($manifestPath, '/');
        } else {
            $manifestUrl = $manifestPath;
        }
        list($response, $httpCode) = curl_get(
            $manifestUrl,
            'application/manifest+json, application/json, text/plain, */*',
            $site_url . '/',
            $cookies
        );
        if ($httpCode === 200 && $response && looks_like_json($response)) {
            return $response;
        }
    }

    return false;
}

/**
 * Определяет или создаёт разработчика по манифесту и URL.
 */
function get_or_create_developer($manifest, $site_url) {
    $domain = parse_url($site_url, PHP_URL_HOST);
    $developer_name = $manifest['author'] ?? $manifest['developer'] ?? null;
    if (!$developer_name) {
        $developer_name = ucfirst(preg_replace('/^www\./', '', $domain));
        $developer_name = preg_replace('/\.(com|ru|org|net|рф|su).*$/i', '', $developer_name);
        $developer_name = ucfirst($developer_name);
    }
    $db = new PDO(DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Ищем разработчика по имени
    $stmt = $db->prepare("SELECT id FROM developers WHERE name = :name");
    $stmt->execute([':name' => $developer_name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row['id'];
    }
    // Создаём
    $stmt = $db->prepare("INSERT INTO developers (name, website) VALUES (:name, :website)");
    $stmt->execute([':name' => $developer_name, ':website' => $site_url]);
    return $db->lastInsertId();
}

function add_pwa_to_catalog($site_url, $manifest_url = null, $cookies = null) {
    $site_url = rtrim($site_url, '/');
    if (!filter_var($site_url, FILTER_VALIDATE_URL)) return "Неверный формат ссылки.";

    $manifest_response = fetch_manifest($site_url, $manifest_url, $cookies);
    if ($manifest_response === false || !is_string($manifest_response)) {
        return "Не удалось найти или загрузить манифест.";
    }

    $json = str_replace("\xEF\xBB\xBF", '', $manifest_response);
    $json = str_replace(["\t", "\r", "\n"], ' ', $json);
    $manifest = json_decode($json, true);
    if (!$manifest) {
        $err = json_last_error_msg();
        $preview = substr($json, 0, 200);
        return "Ошибка чтения JSON: $err. Содержимое: $preview";
    }

    // Основная информация
    $title = $manifest['name'] ?? $manifest['short_name'] ?? parse_url($site_url, PHP_URL_HOST);
    $description = $manifest['description'] ?? 'Прогрессивное веб-приложение (PWA) для мобильных устройств.';
    $final_category = 'Инструменты';
    $manifest_categories = !empty($manifest['categories']) ? array_map('strtolower', (array)$manifest['categories']) : [];
    foreach ($manifest_categories as $cat_tag) {
        if (strpos($cat_tag, 'game') !== false || strpos($cat_tag, 'arcade') !== false) { $final_category = 'Игры'; break; }
        if (strpos($cat_tag, 'social') !== false || strpos($cat_tag, 'chat') !== false || strpos($cat_tag, 'communication') !== false) { $final_category = 'Соцсети'; break; }
        if (strpos($cat_tag, 'shop') !== false || strpos($cat_tag, 'commerce') !== false) { $final_category = 'Покупки'; break; }
        if (strpos($cat_tag, 'news') !== false || strpos($cat_tag, 'book') !== false || strpos($cat_tag, 'productivity') !== false) { $final_category = 'Новости'; break; }
    }
    if ($final_category === 'Инструменты' && extension_loaded('mbstring')) {
        $desc_lower = mb_strtolower($description, 'UTF-8');
        if (mb_strpos($desc_lower, 'игра') !== false || mb_strpos($desc_lower, 'game') !== false) { $final_category = 'Игры'; }
        elseif (mb_strpos($desc_lower, 'магазин') !== false || mb_strpos($desc_lower, 'купить') !== false) { $final_category = 'Покупки'; }
    }

    // Иконка
    $local_icon_url = '';
    if (!empty($manifest['icons']) && is_array($manifest['icons'])) {
        $last_icon = end($manifest['icons']);
        $remote_icon_src = $last_icon['src'] ?? '';
        if ($remote_icon_src) {
            if (parse_url($remote_icon_src, PHP_URL_SCHEME) === null) {
                $remote_icon_url = $site_url . '/' . ltrim($remote_icon_src, '/');
            } else {
                $remote_icon_url = $remote_icon_src;
            }
            $local_icon_url = download_remote_file($remote_icon_url, 'uploads/icons/', $cookies);
        }
    }

    // Скриншоты
    $local_screenshots_paths = [];
    if (!empty($manifest['screenshots']) && is_array($manifest['screenshots'])) {
        foreach ($manifest['screenshots'] as $screen) {
            $src = $screen['src'] ?? '';
            if ($src) {
                if (parse_url($src, PHP_URL_SCHEME) === null) {
                    $remote_screen_url = $site_url . '/' . ltrim($src, '/');
                } else {
                    $remote_screen_url = $src;
                }
                $saved_path = download_remote_file($remote_screen_url, 'uploads/screenshots/', $cookies);
                if ($saved_path) {
                    $local_screenshots_paths[] = $saved_path;
                }
            }
        }
    }
    $screenshots_string = implode(',', $local_screenshots_paths);

    // Белый список РФ
    $app_host = parse_url($site_url, PHP_URL_HOST);
    $is_whitelisted = 0;
    $ip_address = gethostbyname($app_host);
    if ($ip_address !== $app_host) {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $rkn_check = @file_get_contents("https://isblocked.ru/" . urlencode($app_host), false, $ctx);
        if ($rkn_check) {
            $rkn_data = json_decode($rkn_check, true);
            if (isset($rkn_data['blocked']) && $rkn_data['blocked'] === false) {
                if (preg_match('/\.(ru|рф|su)$/i', $app_host)) {
                    $is_whitelisted = 1;
                }
            }
        } else {
            if (preg_match('/\.(ru|рф)$/i', $app_host) && strpos($site_url, 'https://') === 0) {
                $is_whitelisted = 1;
            }
        }
    }

    // Определение разработчика
    $developer_id = get_or_create_developer($manifest, $site_url);

    // Сохранение в БД
    try {
        $db = new PDO(DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("CREATE TABLE IF NOT EXISTS developers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            website TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS apps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            url TEXT UNIQUE NOT NULL,
            icon TEXT,
            description TEXT,
            category TEXT DEFAULT 'Инструменты',
            screenshots TEXT,
            rating REAL DEFAULT 4.5,
            downloads INTEGER DEFAULT 0,
            is_verified INTEGER DEFAULT 0,
            is_whitelisted INTEGER DEFAULT 0,
            developer_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (developer_id) REFERENCES developers(id)
        )");

        $stmt = $db->prepare("INSERT INTO apps 
            (title, url, icon, description, category, screenshots, downloads, is_verified, is_whitelisted, developer_id)
            VALUES (:title, :url, :icon, :description, :category, :screenshots, :downloads, :is_verified, :is_whitelisted, :developer_id)");
        $stmt->execute([
            ':title' => $title,
            ':url' => $site_url,
            ':icon' => $local_icon_url,
            ':description' => $description,
            ':category' => $final_category,
            ':screenshots' => $screenshots_string,
            ':downloads' => rand(250, 1800),
            ':is_verified' => 0,
            ':is_whitelisted' => $is_whitelisted,
            ':developer_id' => $developer_id
        ]);
        return "success";
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') return "Это PWA приложение уже добавлено в каталог.";
        return "Ошибка базы данных: " . $e->getMessage();
    }
}