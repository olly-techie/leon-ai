<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\Auth;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad(); // no-op if .env missing (Render injects env vars directly)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_ENV['CORS_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$public = [
    'GET'  => ['/health' => fn() => json_encode(['status' => 'ok', 'time' => gmdate('c')])],
    'POST' => [
        '/api/auth/register' => [App\Controllers\AuthController::class, 'register'],
        '/api/auth/login'    => [App\Controllers\AuthController::class, 'login'],
    ],
];

$protected = [
    'GET' => [
        '/api/session/report' => [App\Controllers\SessionController::class, 'report'],
    ],
    'POST' => [
        '/api/upload'          => [App\Controllers\UploadController::class, 'upload'],
        '/api/session/start'   => [App\Controllers\SessionController::class, 'start'],
        '/api/session/answer'  => [App\Controllers\SessionController::class, 'answer'],
    ],
];

$isPublic = isset($public[$method][$uri]);
$handler  = $public[$method][$uri] ?? $protected[$method][$uri] ?? null;

if ($handler === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

if (!$isPublic) {
    Auth::require();
}

try {
    if (is_array($handler)) {
        [$class, $action] = $handler;
        $controller = new $class();
        $controller->$action();
    } else {
        echo $handler();
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
