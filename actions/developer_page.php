<?php
// actions/developer_page.php
global $pdo;

// ИСПРАВЛЕНИЕ №1: Читаем напрямую из $_GET, куда роутер записал id из ЧПУ-ссылки /developer/{id}
$developerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($developerId <= 0) {
    header("HTTP/1.1 404 Not Found");
    // ИСПРАВЛЕНИЕ №2: Очищаем буфер и выводим ошибку внутри темы layout.php без аварийного exit
    ob_clean();
    echo "<div class='error-page'><h2>Ошибка 404</h2><p>Некорректный идентификатор разработчика.</p></div>";
    return;
}

$repository = new AppRepository($pdo);
$developer = $repository->findDeveloperById($developerId);

if (!$developer) {
    header("HTTP/1.1 404 Not Found");
    ob_clean();
    echo "<div class='error-page'><h2>Ошибка 404</h2><p>Разработчик не найден в базе данных.</p></div>";
    return;
}

// Загружаем список приложений автора
$developerApps = $repository->getAppsByDeveloperId($developerId);

// Заголовок страницы (автоматически улетит в layout.php)
$pageTitle = e($developer['name']) . " — Приложения разработчика на АпАп";

// Подключаем верстку (она отрендерится внутри буфера и попадет в центр layout.php)
include __DIR__ . '/../templates/developer_view.php';
