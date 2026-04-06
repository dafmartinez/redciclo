<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

/**
 * Singleton PDO.
 *
 * Lee credenciales en este orden de prioridad:
 *   1. Variables de entorno  (hPanel → PHP → SetEnv o php.ini del hosting)
 *   2. Archivo .ini fuera del web root  (~/.redciclo/db.ini)
 *   3. Constantes de fallback definidas en este mismo archivo
 *      (SOLO para desarrollo local — vacíalas en producción)
 *
 * En Hostinger la ruta segura está UNA carpeta ARRIBA de public_html:
 *   /home/u130642646/.redciclo/db.ini
 *
 * Contenido del archivo .ini:
 *   [database]
 *   host    = localhost
 *   dbname  = u130642646_redciclo_api
 *   user    = u130642646_redciclo_usr
 *   pass    = TuPasswordAqui
 */
final class Database
{
    private static ?PDO $connection = null;

    // ── Ruta al archivo .ini (fuera del web root) ─────────────────────────────
    // En Hostinger: /home/USUARIO/.redciclo/db.ini
    // En desarrollo local: ajusta esta ruta a tu entorno.
    private const INI_PATH = '/home/u130642646/.redciclo/db.ini';

    private function __construct() {}

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        ['host' => $host, 'dbname' => $dbname, 'user' => $user, 'pass' => $pass]
            = self::loadCredentials();

        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

        try {
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // No exponer credenciales ni stack trace al cliente
            error_log('[Redciclo/DB] ' . $e->getMessage());
            throw new PDOException('No se pudo conectar a la base de datos.', (int)$e->getCode());
        }

        return self::$connection;
    }

    /** @return array{host:string, dbname:string, user:string, pass:string} */
    private static function loadCredentials(): array
    {
        // 1. Variables de entorno (más seguras en hosting con soporte)
        $fromEnv = self::fromEnv();
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        // 2. Archivo .ini fuera del web root
        $fromIni = self::fromIni();
        if ($fromIni !== null) {
            return $fromIni;
        }

        // 3. Fallback — activo hasta que el archivo .ini esté en el servidor.
        // Una vez creado /home/u130642646/.redciclo/db.ini, vaciar estos valores.
        return [
            'host'   => 'localhost',
            'dbname' => 'u130642646_redciclo_api',
            'user'   => 'u130642646_redciclo_usr',
            'pass'   => 'redcicl0_USR',
        ];
    }

    /** @return array{host:string,dbname:string,user:string,pass:string}|null */
    private static function fromEnv(): ?array
    {
        $host   = getenv('DB_HOST')   ?: ($_ENV['DB_HOST']   ?? '');
        $dbname = getenv('DB_NAME')   ?: ($_ENV['DB_NAME']   ?? '');
        $user   = getenv('DB_USER')   ?: ($_ENV['DB_USER']   ?? '');
        $pass   = getenv('DB_PASS')   ?: ($_ENV['DB_PASS']   ?? '');

        if ($host === '' || $dbname === '' || $user === '') {
            return null;
        }

        return compact('host', 'dbname', 'user', 'pass');
    }

    /** @return array{host:string,dbname:string,user:string,pass:string}|null */
    private static function fromIni(): ?array
    {
        if (!is_readable(self::INI_PATH)) {
            return null;
        }

        $cfg = parse_ini_file(self::INI_PATH, true);
        $db  = $cfg['database'] ?? [];

        $host   = (string)($db['host']   ?? '');
        $dbname = (string)($db['dbname'] ?? '');
        $user   = (string)($db['user']   ?? '');
        $pass   = (string)($db['pass']   ?? '');

        if ($host === '' || $dbname === '' || $user === '') {
            return null;
        }

        return compact('host', 'dbname', 'user', 'pass');
    }
}
