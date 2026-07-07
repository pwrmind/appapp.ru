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
    curl_setopt($ch, CURLOPT_USERAGENT,
        'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1');

    $success = curl_exec($ch);
    // curl_close убран – с PHP 8.0 ресурс освобождается автоматически
    fclose($fp);

    if ($success && filesize($local_path) > 0) {
        return $local_path;
    } else {
        @unlink($local_path);
        return null;
    }
}

/**
 * Главная функция: принимает URL сайта, извлекает PWA‑манифест,
 * загружает иконки/скриншоты, определяет категорию, проверяет
 * белые списки и сохраняет всё в базу данных.
 */
function add_pwa_to_catalog($site_url) {
    $site_url = rtrim($site_url, '/');
    if (!filter_var($site_url, FILTER_VALIDATE_URL)) return "Неверный формат ссылки.";

    // 1. Получаем HTML главной страницы
    $ch = curl_init($site_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT,
        'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1');
    $html = curl_exec($ch);
    // curl_close убран
    if (!$html) return "Не удалось открыть сайт.";

    // 2. Ищем ссылку на манифест в HTML
    if (!preg_match('/<link[^>]+rel=["\']manifest["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
        return "На сайте не найден PWA-манифест.";
    }

    $manifest_path = $matches[1];
    if (parse_url($manifest_path, PHP_URL_SCHEME) === null) {
        $manifest_url = $site_url . '/' . ltrim($manifest_path, '/');
    } else {
        $manifest_url = $manifest_path;
    }

    // 3. Загружаем содержимое манифеста
    $ch = curl_init($manifest_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $manifest_json = curl_exec($ch);
    // curl_close убран
    if (!$manifest_json) return "Не удалось скачать файл манифеста по адресу: " . $manifest_url;

    $manifest = json_decode($manifest_json, true);
    if (!$manifest) return "Ошибка чтения JSON структуры в манифесте.";

    // 4. Извлекаем текстовые данные
    $title = $manifest['name'] ?? $manifest['short_name'] ?? parse_url($site_url, PHP_URL_HOST);
    $description = $manifest['description'] ?? 'Прогрессивное веб-приложение (PWA) для мобильных устройств.';

    // 5. Умное определение категории
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
    if ($final_category === 'Инструменты') {
        $desc_lower = mb_strtolower($description);
        if (mb_strpos($desc_lower, 'игра') !== false || mb_strpos($desc_lower, 'game') !== false) {
            $final_category = 'Игры';
        } elseif (mb_strpos($desc_lower, 'магазин') !== false || mb_strpos($desc_lower, 'купить') !== false) {
            $final_category = 'Покупки';
        }
    }

    // 6. Скачивание иконки
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

    // 7. Скачивание скриншотов
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

    // 8. Автоматическая проверка белого списка РФ
    $app_host = parse_url($site_url, PHP_URL_HOST);
    $is_whitelisted = 0;

    $ip_address = gethostbyname($app_host);
    if ($ip_address !== $app_host) { // домен успешно резолвится
        // Пробуем обратиться к публичному API проверки блокировок (эмуляция)
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
            // Резервный вариант: если HTTPS и домен .ru / .рф — считаем безопасным
            if (preg_match('/\.(ru|рф)$/i', $app_host) && strpos($site_url, 'https://') === 0) {
                $is_whitelisted = 1;
            }
        }
    }

    // 9. Сохраняем в базу данных
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