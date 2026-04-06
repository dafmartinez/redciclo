<?php
// TEMPORAL — ELIMINAR DESPUÉS DE DIAGNOSTICAR
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo '<pre>';

// 1. Verificar sintaxis de los controladores
$archivos = [
    dirname(__DIR__) . '/app/Controllers/OrdenController.php',
    dirname(__DIR__) . '/app/Models/Orden.php',
    dirname(__DIR__) . '/app/Models/Nota.php',
    dirname(__DIR__) . '/app/Models/Tracking.php',
];

echo "=== VERIFICACIÓN DE SINTAXIS PHP ===\n";
foreach ($archivos as $ruta) {
    $nombre = basename($ruta);
    $salida = shell_exec("php -l " . escapeshellarg($ruta) . " 2>&1");
    echo "{$nombre}: " . trim($salida) . "\n";
}

echo "\n=== CARGA DE CLASES (autoload) ===\n";
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
});

require_once dirname(__DIR__) . '/config/database.php';

try {
    $pdo = \App\Config\Database::getConnection();
    echo "BD OK\n";
} catch (\Throwable $e) {
    echo "BD FALLÓ: " . $e->getMessage() . "\n";
    exit;
}

// 2. Instanciar el controlador directamente para atrapar errores de carga
try {
    $ctrl = new \App\Controllers\OrdenController();
    echo "OrdenController instanciado OK\n";
} catch (\Throwable $e) {
    echo "OrdenController FALLÓ: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n";
    exit;
}

// 3. Simular cancelar() sin sesión para ver hasta dónde llega
echo "\n=== SIMULACIÓN cancelar() ===\n";
try {
    $orden = new \App\Models\Orden($pdo);
    $canceladoId = $orden->getEstadoCanceladoId();
    echo "getEstadoCanceladoId(): {$canceladoId}\n";

    // Intentar cancelar la orden 49 (usa un ID que sepas que existe)
    $ok = $orden->cancelar(49, $canceladoId);
    echo "cancelar(49, {$canceladoId}): " . ($ok ? "OK (filas afectadas)" : "rowCount=0 (ya cancelada o no existe)") . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n";
}

echo '</pre>';
