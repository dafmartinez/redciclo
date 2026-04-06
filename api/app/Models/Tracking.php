<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

final class Tracking
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerRutaPorOrden(int $ordenId): array
    {
        $stmt = $this->db->prepare(
            'SELECT latitud, longitud, ftrack
             FROM tracking
             WHERE orden = :orden
             ORDER BY ftrack ASC'
        );
        $stmt->execute([':orden' => $ordenId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar(int $ordenId, int $transportistaId, float $latitud, float $longitud): bool
    {
        $sql = 'INSERT INTO tracking (orden, usuario, latitud, longitud, ftrack)
                 VALUES (:orden, :usuario, :lat, :lng, NOW())';

        try {
            error_log(sprintf(
                '[Tracking v2026-04-06] archivo=%s sql=%s params=%s',
                __FILE__,
                preg_replace('/\s+/', ' ', $sql),
                json_encode([
                    'orden' => $ordenId,
                    'usuario' => $transportistaId,
                    'lat' => $latitud,
                    'lng' => $longitud,
                ], JSON_UNESCAPED_UNICODE)
            ));

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':orden'   => $ordenId,
                ':usuario' => $transportistaId,
                ':lat'     => $latitud,
                ':lng'     => $longitud,
            ]);

            return true;
        } catch (PDOException $e) {
            throw new \RuntimeException(
                "[Tracking v2026-04-06] Fallo el INSERT - archivo=" . __FILE__ .
                " sql=" . preg_replace('/\s+/', ' ', $sql) .
                " orden={$ordenId} transportista={$transportistaId}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
