<?php
// sitemap.php
header("Content-Type: application/xml; charset=utf-8");

require_once 'config.php';

// Определяем домен с конвертацией Punycode в Unicode (если доступно)
$host = $_SERVER['HTTP_HOST'];
if (function_exists('idn_to_utf8')) {
    $store_domain = 'https://' . idn_to_utf8($host);
} else {
    $store_domain = 'https://' . $host;
}

// Выводим XML-декларацию как строку, чтобы избежать short_open_tag
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Главная страница -->
    <url>
        <loc><?php echo $store_domain; ?>/index.php</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <?php
    // Страницы категорий
    $categories = ['Игры', 'Инструменты', 'Соцсети', 'Новости', 'Покупки'];
    foreach ($categories as $cat):
        $cat_url = $store_domain . '/index.php?cat=' . urlencode($cat);
    ?>
    <url>
        <loc><?php echo $cat_url; ?></loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <?php endforeach; ?>

    <?php
    // Страницы приложений
    try {
        $db = new PDO(DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $db->query("SELECT id, created_at FROM apps ORDER BY id DESC");
        $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($apps as $app):
            $date = !empty($app['created_at']) ? date('Y-m-d', strtotime($app['created_at'])) : date('Y-m-d');
            $app_url = $store_domain . '/app.php?id=' . $app['id'];
    ?>
    <url>
        <loc><?php echo $app_url; ?></loc>
        <lastmod><?php echo $date; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php
        endforeach;
    } catch (PDOException $e) {
        // Если база недоступна – выводятся только главная и категории
    }
    ?>
</urlset>