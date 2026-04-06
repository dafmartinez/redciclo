<?php
// TEMPORAL — ELIMINAR DESPUÉS DE DIAGNOSTICAR
// Accede a: https://redciclo.top/api/public/debug500.php
// Simula exactamente lo que hace index.php pero con errores visibles

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config/database.php';

spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
});

echo '<pre>';

// 1. Prueba de conexión
try {
    $pdo = \App\Config\Database::getConnection();
    echo "✅ Conexión BD OK\n\n";
} catch (\Throwable $e) {
    echo "❌ Conexión BD FALLÓ: " . $e->getMessage() . "\n\n";
    exit;
}

// 2. Prueba getEstadoCanceladoId
$orden = new \App\Models\Orden($pdo);
$id = $orden->getEstadoCanceladoId();
echo "Estado cancelado ID: {$id}\n\n";

// 3. Muestra todos los estados
$estados = $pdo->query('SELECT id, estado FROM estados ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
echo "Estados en BD:\n";
foreach ($estados as $e) {
    echo "  id={$e['id']} → {$e['estado']}\n";
}

// 4. Verifica que los archivos del controlador existen
$archivos = [
    'OrdenController'    => dirname(__DIR__) . '/app/Controllers/OrdenController.php',
    'TrackingController' => dirname(__DIR__) . '/app/Controllers/TrackingController.php',
    'Tracking model'     => dirname(__DIR__) . '/app/Models/Tracking.php',
    'Nota model'         => dirname(__DIR__) . '/app/Models/Nota.php',
];
echo "\nArchivos en servidor:\n";
foreach ($archivos as $nombre => $ruta) {
    echo "  {$nombre}: " . (file_exists($ruta) ? "✅ existe" : "❌ NO ENCONTRADO") . "\n";
}

echo '</pre>';
