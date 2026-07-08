<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/src/Router.php';
require_once __DIR__ . '/src/AppRepository.php';
require_once __DIR__ . '/src/PwaParser.php';

$router = new Router();
$router->add('sitemap.xml', 'actions/sitemap.php');

$router->add('', 'actions/home_page.php');
$router->add('app/{id}', 'actions/app_page.php');
$router->add('developer/{id}', 'actions/developer_page.php');
$router->add('about', 'actions/about_page.php');
$router->add('privacy', 'actions/privacy_page.php');

// API
$router->add('api/apps', 'actions/add_api.php');

$currentRoute = $_GET['route'] ?? '';

ob_start();

// Запускаем роутер и смотрим на ответ
$routeFound = $router->dispatch($currentRoute);

// Если роутер вернул false (страница не найдена)
if (!$routeFound) {
    header("HTTP/1.1 404 Not Found");
    $pageTitle = "Страница не найдена — АпАп";
    // Очищаем всё, что успел выдать роутер до падения, и пишем красивую ошибку
    ob_clean(); 
    echo "<div class='error-page'><h2>Ошибка 404</h2><p>Упс! Страница не найдена. <a href='/'>Вернуться на главную</a></p></div>";
}

$content = ob_get_clean();

// Если запрос шел к API или Карте сайта — просто отдаем чистый контент (JSON/XML)
if (str_starts_with($currentRoute, 'api/') || $currentRoute === 'sitemap.xml') {
    echo $content;
    exit;
}

include __DIR__ . '/templates/layout.php';

// Вставьте этот код в index.php для теста:
// echo "Текущий роут из URL: '" . htmlspecialchars($_GET['route'] ?? '') . "'<br>";
// echo "Очищенный роут: '" . htmlspecialchars(trim($_GET['route'] ?? '', '/')) . "'<br>";