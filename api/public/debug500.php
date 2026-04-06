<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo '<pre>';

spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
});

require_once dirname(__DIR__) . '/config/database.php';

$pdo = \App\Config\Database::getConnection();
echo "BD OK\n\n";

// Cargar cada clase individualmente para aislar cuál falla
$clases = [
    'App\\Models\\Orden',
    'App\\Models\\Nota',
    'App\\Models\\Tracking',
    'App\\Core\\Controller',
    'App\\Controllers\\OrdenController',
];

foreach ($clases as $clase) {
    try {
        class_exists($clase);
        echo "OK  {$clase}\n";
    } catch (\Throwable $e) {
        echo "ERR {$clase}: " . $e->getMessage() . " (línea " . $e->getLine() . " en " . $e->getFile() . ")\n";
    }
}

echo "\n--- Instanciar OrdenController ---\n";
try {
    $ctrl = new \App\Controllers\OrdenController();
    echo "OK\n";
} catch (\Throwable $e) {
    echo "FALLÓ: " . $e->getMessage() . "\n";
    echo "En: " . $e->getFile() . " línea " . $e->getLine() . "\n";
}

echo "\n--- Simular cancelar() ---\n";
try {
    $orden      = new \App\Models\Orden($pdo);
    $canceladoId = $orden->getEstadoCanceladoId();
    echo "Estado cancelado ID: {$canceladoId}\n";
    $ok = $orden->cancelar(49, $canceladoId);
    echo "cancelar(49): " . ($ok ? "OK" : "rowCount=0") . "\n";
} catch (\Throwable $e) {
    echo "FALLÓ: " . $e->getMessage() . "\n";
    echo "En: " . $e->getFile() . " línea " . $e->getLine() . "\n";
}

echo '</pre>';
