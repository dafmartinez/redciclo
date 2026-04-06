<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Nota
{
    public function __construct(private PDO $db)
    {
    }

    public function allByOrden(int $ordenId): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.nota, n.fnota, u.perfil, u.login
             FROM notas n
             JOIN usuarios u ON u.id = n.usuario
             WHERE n.orden = :orden
             ORDER BY n.fnota DESC'
        );
        $stmt->execute([':orden' => $ordenId]);

        return $stmt->fetchAll();
    }

    public function create(int $ordenId, int $usuarioId, string $nota): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notas (nota, orden, usuario, fnota)
             VALUES (:nota, :orden, :usuario, NOW())'
        );

        return $stmt->execute([
            ':nota'    => $nota,
            ':orden'   => $ordenId,
            ':usuario' => $usuarioId,
        ]);
    }
}
