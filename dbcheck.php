<?php
// ARCHIVO TEMPORAL DE DIAGNÓSTICO — ELIMINAR DESPUÉS DE USAR
// Accede a: https://redciclo.top/dbcheck.php

$iniPath = '/home/u130642646/.redciclo/db.ini';

echo '<pre>';
echo "=== RUTA DEL ARCHIVO INI ===\n";
echo "Ruta: {$iniPath}\n";
echo "¿Existe?:   " . (file_exists($iniPath) ? 'SÍ' : 'NO') . "\n";
echo "¿Legible?:  " . (is_readable($iniPath) ? 'SÍ' : 'NO') . "\n\n";

echo "=== DIRECTORIO HOME DE PHP ===\n";
echo "getcwd():        " . getcwd() . "\n";
echo "__DIR__:         " . __DIR__ . "\n";
echo "HOME env:        " . (getenv('HOME') ?: '(vacío)') . "\n\n";

echo "=== CONTENIDO DEL INI (si es legible) ===\n";
if (is_readable($iniPath)) {
    $cfg = parse_ini_file($iniPath, true);
    $db  = $cfg['database'] ?? [];
    echo "host:   " . ($db['host']   ?? '(no encontrado)') . "\n";
    echo "dbname: " . ($db['dbname'] ?? '(no encontrado)') . "\n";
    echo "user:   " . ($db['user']   ?? '(no encontrado)') . "\n";
    echo "pass:   " . (isset($db['pass']) ? '***' : '(no encontrado)') . "\n\n";
} else {
    echo "(no se puede leer)\n\n";
}

echo "=== PRUEBA DE CONEXIÓN PDO ===\n";
$host   = 'localhost';
$dbname = 'u130642646_redciclo_api';
$user   = 'u130642646_redciclo_usr';
$pass   = 'redcicl0_USR';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "CONEXIÓN EXITOSA con host={$host}\n";
} catch (PDOException $e) {
    echo "FALLÓ con host=localhost: " . $e->getMessage() . "\n";

    // Intentar con 127.0.0.1
    try {
        $pdo2 = new PDO(
            "mysql:host=127.0.0.1;dbname={$dbname};charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "CONEXIÓN EXITOSA con host=127.0.0.1\n";
    } catch (PDOException $e2) {
        echo "FALLÓ con host=127.0.0.1: " . $e2->getMessage() . "\n";
    }
}

echo '</pre>';
