<?php
// actions/sitemap.php
header("Content-Type: application/xml; charset=utf-8");

// Используем глобальное подключение к БД из index.php
global $pdo;

// Определяем домен с конвертацией Punycode в Unicode
$host = $_SERVER['HTTP_HOST'];
if (function_exists('idn_to_utf8')) {
    $store_domain = 'https://' . idn_to_utf8($host);
} else {
    $store_domain = 'https://' . $host;
}

// Очищаем буфер от случайных пробелов или выводов фреймворка перед генерацией XML
ob_clean();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Главная страница стора -->
    <url>
        <loc><?php echo $store_domain; ?>/</loc> <!-- ЧПУ: просто корень сайта -->
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <?php
    // Страницы категорий
    $categories = ['Игры', 'Инструменты', 'Соцсети', 'Новости', 'Покупки'];
    foreach ($categories as $cat):
        // ЧПУ: Обновлено с cat= на category=, как в вашем новом index.php
        $cat_url = $store_domain . '/index.php?category=' . urlencode($cat);
    ?>
    <url>
        <loc><?php echo $cat_url; ?></loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <?php endforeach; ?>

    <?php
    // Страницы приложений и разработчиков
    try {
        // ИСПРАВЛЕНИЕ: Выбираем приложения для генерации новых ссылок вида /app/{id}
        $stmtApps = $pdo->query("SELECT id, created_at FROM apps ORDER BY id DESC");
        $apps = $stmtApps->fetchAll(PDO::FETCH_ASSOC);

        foreach ($apps as $app):
            $date = !empty($app['created_at']) ? date('Y-m-d', strtotime($app['created_at'])) : date('Y-m-d');
            $app_url = $store_domain . '/app/' . $app['id']; // ЧПУ ссылка
    ?>
    <url>
        <loc><?php echo $app_url; ?></loc>
        <lastmod><?php echo $date; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php
        endforeach;

        // БОНУС: Поисковикам также нужно проиндексировать страницы разработчиков!
        $stmtDevs = $pdo->query("SELECT id, created_at FROM developers ORDER BY id DESC");
        $developers = $stmtDevs->fetchAll(PDO::FETCH_ASSOC);

        foreach ($developers as $dev):
            $date = !empty($dev['created_at']) ? date('Y-m-d', strtotime($dev['created_at'])) : date('Y-m-d');
            $dev_url = $store_domain . '/developer/' . $dev['id']; // ЧПУ ссылка
    ?>
    <url>
        <loc><?php echo $dev_url; ?></loc>
        <lastmod><?php echo $date; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php
        endforeach;

    } catch (PDOException $e) {
        // Если база недоступна – в sitemap уйдут только главная и категории
    }
    ?>
</urlset>
