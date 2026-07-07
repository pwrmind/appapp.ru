<?php
// sitemap.php
header("Content-Type: application/xml; charset=utf-8");

require_once 'config.php';

$store_domain = "https://" . $_SERVER['HTTP_HOST'];

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://sitemaps.org">';

// Главная страница
echo '<url>';
echo '<loc>' . $store_domain . '/index.php</loc>';
echo '<lastmod>' . date('Y-m-d') . '</lastmod>';
echo '<changefreq>daily</changefreq>';
echo '<priority>1.0</priority>';
echo '</url>';

try {
    $db = new PDO(DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("SELECT id, created_at FROM apps ORDER BY id DESC");
    $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($apps as $app) {
        $date = !empty($app['created_at']) ? date('Y-m-d', strtotime($app['created_at'])) : date('Y-m-d');
        echo '<url>';
        echo '<loc>' . $store_domain . '/app.php?id=' . $app['id'] . '</loc>';
        echo '<lastmod>' . $date . '</lastmod>';
        echo '<changefreq>weekly</changefreq>';
        echo '<priority>0.8</priority>';
        echo '</url>';
    }
} catch (PDOException $e) {
    // Если база недоступна, карта сайта будет содержать только главную страницу
}

echo '</urlset>';