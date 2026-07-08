<?php
// actions/api_apps.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

// Переменная $pdo уже доступна глобально благодаря index.php
global $pdo; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean(); // Очищаем буфер перед выводом JSON
    echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается. Используйте POST.'], JSON_UNESCAPED_UNICODE);
    return; // Используем return вместо exit
}

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Токен авторизации отсутствует.'], JSON_UNESCAPED_UNICODE);
    return;
}

$clientToken = $matches[1];
if ($clientToken !== API_SECRET_TOKEN) {
    http_response_code(403);
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Неверный токен доступа.'], JSON_UNESCAPED_UNICODE);
    return;
}

$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? $_POST['url'] ?? '';
if (empty($url)) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Параметр "url" обязателен.'], JSON_UNESCAPED_UNICODE);
    return;
}

$manifestUrl = $input['manifest_url'] ?? $_POST['manifest_url'] ?? null;
$cookies = $input['cookies'] ?? $_POST['cookies'] ?? null;

// Слой Домена (Domain): Вызываем парсер, который инкапсулирует логику работы с БД внутри себя
$parser = new PwaParser($pdo);
$result = $parser->addPwaToCatalog($url, $manifestUrl, $cookies);

// Слой Ответчика (Responder): Формируем финальный JSON статус
ob_clean();
if ($result === 'success') {
    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => 'Приложение успешно обработано. Иконки и скриншоты сохранены локально.'], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $result], JSON_UNESCAPED_UNICODE);
}
return;
