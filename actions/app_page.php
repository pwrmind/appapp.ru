<?php
global $pdo;

// ИСПРАВЛЕНИЕ: Читаем напрямую из $_GET, куда роутер записал id из ЧПУ-ссылки
$appId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($appId <= 0) {
    header("HTTP/1.1 404 Not Found");
    // Здесь мы просто очищаем буфер, чтобы вывести красивую ошибку внутри layout.php
    ob_clean(); 
    echo "<div class='error-page'><h2>Ошибка 404</h2><p>Неверный идентификатор приложения.</p></div>";
    return; // Используем return вместо exit, чтобы index.php завершил отрисовку layout
}

$repository = new AppRepository($pdo);
$app = $repository->findAppById($appId);

if (!$app) {
    header("HTTP/1.1 404 Not Found");
    ob_clean();
    echo "<div class='error-page'><h2>Ошибка 404</h2><p>Приложение не найдено в базе данных.</p></div>";
    return;
}

$repository->incrementDownloads($appId);
$relatedApps = $repository->getRelatedApps($app['category'], $appId, 4);
$screenshots = !empty($app['screenshots']) ? explode(',', $app['screenshots']) : [];
$appDomain = parse_url($app['url'], PHP_URL_HOST);
$pageTitle = e($app['title']) . " — Скачать PWA на АпАп";

include __DIR__ . '/../templates/app_view.php';
