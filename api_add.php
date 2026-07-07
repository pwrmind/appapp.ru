<?php
// api_add.php
header('Content-Type: application/json; charset=utf-8');

// На время отладки можно включить, в production закомментировать
ini_set('display_errors', 0);
error_reporting(0);

require_once 'config.php';
require_once 'parser.php';

// Только метод POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Метод не поддерживается. Используйте POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Извлечение заголовка Authorization
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
if (empty($auth_header) || !preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Токен авторизации отсутствует.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$client_token = $matches[1];
if ($client_token !== API_SECRET_TOKEN) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Неверный токен доступа.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Получение параметров из тела запроса
$input_data = json_decode(file_get_contents('php://input'), true);
$url = $input_data['url'] ?? $_POST['url'] ?? '';
if (empty($url)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Параметр "url" обязателен.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Дополнительный параметр manifest_url (может отсутствовать)
$manifest_url = $input_data['manifest_url'] ?? $_POST['manifest_url'] ?? null;

// Запуск парсинга
$result = add_pwa_to_catalog($url, $manifest_url);

if ($result === 'success') {
    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Приложение успешно обработано. Иконки и скриншоты сохранены локально.'
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => $result
    ], JSON_UNESCAPED_UNICODE);
}