<?php
// parser.php
require_once 'config.php';

/**
 * Очистка строки для безопасного использования в имени файла
 */
function clean_extension($string) {
    return preg_replace('/[^a-zA-Z0-9]/', '', $string);
}

/**
 * Скачивание удалённого файла (иконки, скриншота) в локальное хранилище.
 * Возвращает относительный путь к файлу при успехе или null.
 */
function download_remote_file($file_url, $save_dir) {
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
    curl_setopt($ch, CURLOPT_ENCODING, ''); // автоматически обрабатывать gzip
    curl_setopt($ch, CURLOPT_USERAGENT,
        'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36');

    $success = curl_exec($ch);
    fclose($fp);

    if ($success && filesize($local_path) > 0) {
        return $local_path;
    } else {
        @unlink($local_path);
        return null;
    }
}

/**
 * Загрузка манифеста: 1) явный URL, 2) стандартный /manifest.json, 3) поиск в HTML.
 * Возвращает строку с JSON или false.
 */
function fetch_manifest($site_url, $explicit_manifest_url = null) {
    // 1. Если передан явный URL манифеста – пробуем его
    if (!empty($explicit_manifest_url)) {
        if (parse_url($explicit_manifest_url, PHP_URL_SCHEME) === null) {
            $explicit_manifest_url = rtrim($site_url, '/') . '/' . ltrim($explicit_manifest_url, '/');
        }
        $ch = curl_init($explicit_manifest_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json, text/plain, */*',
            'User-Agent: Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36'
        ]);
        $response = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && $response) {
            return $response;
        }
    }

    // 2. Стандартный /manifest.json
    $url = rtrim($site_url, '/') . '/manifest.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json, text/plain, */*',
        'User-Agent: Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36'
    ]);
    $response = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && $response) {
        return $response;
    }

    // 3. Поиск в HTML
    $ch = curl_init($site_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_USERAGENT,
        'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36');
    $html = curl_exec($ch);
    if (!$html) return false;

    if (preg_match('/<link[^>]+rel=["\']manifest["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
        $manifestPath = $matches[1];
        if (parse_url($manifestPath, PHP_URL_SCHEME) === null) {
            $manifestUrl = rtrim($site_url, '/') . '/' . ltrim($manifestPath, '/');
        } else {
            $manifestUrl = $manifestPath;
        }
        $ch = curl_init($manifestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json, text/plain, */*',
            'User-Agent: Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36'
        ]);
        $response = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
            return $response;
        }
    }
    return false;
}

/**
 * Главная функция: принимает URL сайта и опциональный URL манифеста.
 * Извлекает PWA‑манифест, загружает иконки/скриншоты, определяет категорию,
 * проверяет белые списки и сохраняет всё в базу данных.
 */
function add_pwa_to_catalog($site_url, $manifest_url = null) {
    $site_url = rtrim($site_url, '/');
    if (!filter_var($site_url, FILTER_VALIDATE_URL)) return "Неверный формат ссылки.";

    // 1. Загружаем содержимое манифеста
    $manifest_response = fetch_manifest($site_url, $manifest_url);
    if ($manifest_response === false || !is_string($manifest_response)) {
        return "Не удалось найти или загрузить манифест. Убедитесь, что сайт является PWA и манифест доступен.";
    }

    // 2. Минимальная очистка: BOM и замена управляющих символов
    $json = str_replace("\xEF\xBB\xBF", '', $manifest_response);   // удалить BOM
    $json = str_replace(["\t", "\r", "\n"], ' ', $json);          // табуляции и переносы внутри строк -> пробел

    $manifest = json_decode($json, true);
    if (!$manifest) {
        $err = json_last_error_msg();
        $preview = substr($json, 0, 200);
        return "Ошибка чтения JSON: $err. Содержимое: $preview";
    }

    // 3. Извлекаем текстовые данные
    $title = $manifest['name'] ?? $manifest['short_name'] ?? parse_url($site_url, PHP_URL_HOST);
    $description = $manifest['description'] ?? 'Прогрессивное веб-приложение (PWA) для мобильных устройств.';

    // 4. Умное определение категории
    $final_category = 'Инструменты';
    $manifest_categories = !empty($manifest['categories']) ? array_map('strtolower', (array)$manifest['categories']) : [];
    foreach ($manifest_categories as $cat_tag) {
        if (strpos($cat_tag, 'game') !== false || strpos($cat_tag, 'arcade') !== false) {
            $final_category = 'Игры'; break;
        }
        if (strpos($cat_tag, 'social') !== false || strpos($cat_tag, 'chat') !== false || strpos($cat_tag, 'communication') !== false) {
            $final_category = 'Соцсети'; break;
        }
        if (strpos($cat_tag, 'shop') !== false || strpos($cat_tag, 'commerce') !== false) {
            $final_category = 'Покупки'; break;
        }
        if (strpos($cat_tag, 'news') !== false || strpos($cat_tag, 'book') !== false || strpos($cat_tag, 'productivity') !== false) {
            $final_category = 'Новости'; break;
        }
    }

    // Дополнительная проверка по описанию (только если есть mbstring)
    if ($final_category === 'Инструменты' && extension_loaded('mbstring')) {
        $desc_lower = mb_strtolower($description, 'UTF-8');
        if (mb_strpos($desc_lower, 'игра') !== false || mb_strpos($desc_lower, 'game') !== false) {
            $final_category = 'Игры';
        } elseif (mb_strpos($desc_lower, 'магазин') !== false || mb_strpos($desc_lower, 'купить') !== false) {
            $final_category = 'Покупки';
        }
    }

    // 5. Скачивание иконки
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
            $local_icon_url = download_remote_file($remote_icon_url, 'uploads/icons/');
        }
    }

    // 6. Скачивание скриншотов
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
                $saved_path = download_remote_file($remote_screen_url, 'uploads/screenshots/');
                if ($saved_path) {
                    $local_screenshots_paths[] = $saved_path;
                }
            }
        }
    }
    $screenshots_string = implode(',', $local_screenshots_paths);

    // 7. Автоматическая проверка белого списка РФ
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

    // 8. Сохраняем в базу данных
    try {
        $db = new PDO(DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $db->prepare("INSERT INTO apps 
            (title, url, icon, description, category, screenshots, downloads, is_verified, is_whitelisted)
            VALUES (:title, :url, :icon, :description, :category, :screenshots, :downloads, :is_verified, :is_whitelisted)");
        $stmt->execute([
            ':title' => $title,
            ':url' => $site_url,
            ':icon' => $local_icon_url,
            ':description' => $description,
            ':category' => $final_category,
            ':screenshots' => $screenshots_string,
            ':downloads' => rand(250, 1800),
            ':is_verified' => 0,
            ':is_whitelisted' => $is_whitelisted
        ]);
        return "success";
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') return "Это PWA приложение уже добавлено в каталог.";
        return "Ошибка базы данных: " . $e->getMessage();
    }
}