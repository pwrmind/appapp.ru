<?php
// Получаем чистый путь из URL (без GET-параметров)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

// Если это реальный файл — встроенный сервер PHP отдаст его сам (CSS, JS, картинки)
if (file_exists($file) && !is_dir($file)) {
    return false;
}

// --- ИСПРАВЛЕНИЕ: Передаем текущий маршрут в $_GET['route'] ---
// Убираем первый слэш (например, "/app/1" превращаем в "app/1")
$route = ltrim($path, '/');
$_GET['route'] = $route;

// Корректируем глобальные переменные для правильной работы приложения
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';

// Перенаправляем весь остальной трафик на главный index.php
require_once __DIR__ . '/index.php';
