<?php
global $pdo;

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : null;
$category = isset($_GET['category']) ? trim($_GET['category']) : null;

$repository = new AppRepository($pdo);
if ($searchQuery || $category) {
    $appsList = $repository->getApps(['search' => $searchQuery, 'category' => $category], 30);
    $pageTitle = $searchQuery ? "Поиск: " . e($searchQuery) : "Категория: " . e($category);
    $isFiltered = true;
} else {
    $pageTitle = "АпАп — Каталог прогрессивных веб-приложений (PWA)";
    $recentApps = $repository->getApps([], 5);
    $mainApps = $repository->getApps(['category' => 'Инструменты'], 10);
    $weeklyTop = $repository->getApps([], 5);
    $isFiltered = false;
}

$categories = ['Все', 'Игры', 'Инструменты', 'Соцсети', 'Новости', 'Покупки'];

include __DIR__ . '/../templates/home_view.php';