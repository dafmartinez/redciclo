<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\ConfigController;
use App\Controllers\OrdenController;
use App\Controllers\TrackingController;
use App\Core\Router;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

require_once dirname(__DIR__) . '/config/database.php';

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

session_set_cookie_params([
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'data' => null, 'message' => 'Metodo no permitido']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$metodo = $payload['metodo'] ?? $_POST['metodo'] ?? '';

$router = new Router();
$router->post('test', static function (): void {
    echo json_encode([
        'status' => 'success',
        'data' => null,
        'message' => 'API Redciclo MVC activa',
    ], JSON_UNESCAPED_UNICODE);
});

$router->post('registro', static fn () => (new AuthController())->registro());
$router->post('login', static fn () => (new AuthController())->login());
$router->post('autorizar', static fn () => (new AuthController())->autorizar());
$router->post('logout', static fn () => (new AuthController())->logout());
$router->post('usuarios', static fn () => (new AuthController())->usuarios());
$router->post('solicitar', static fn () => (new OrdenController())->listar());
$router->post('crear', static fn () => (new OrdenController())->crear());
$router->post('cambiarestado', static fn () => (new OrdenController())->cambiarEstado());
$router->post('crearnota', static fn () => (new OrdenController())->crearNota());
$router->post('solicitarnotas', static fn () => (new OrdenController())->listarNotas());
$router->post('cancelar', static fn () => (new OrdenController())->cancelar());
$router->post('configuracion',   static fn () => (new ConfigController())->configuracion());
$router->post('subirEvidencia',  static fn () => (new OrdenController())->subirEvidencia());
$router->post('guardarTracking', static fn () => (new TrackingController())->guardar());

try {
    $router->dispatch((string)$metodo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'data' => null,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
