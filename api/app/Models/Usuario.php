<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Usuario
{
    public function __construct(private PDO $db)
    {
    }

    public function findActiveByLogin(string $login): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, login, password, perfil
             FROM usuarios
             WHERE login = :login
               AND activo = 1
             LIMIT 1'
        );
        $stmt->execute([':login' => $login]);

        return $stmt->fetch();
    }

    public function existsByLogin(string $login): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM usuarios WHERE login = :login LIMIT 1');
        $stmt->execute([':login' => $login]);

        return (bool)$stmt->fetch();
    }

    public function create(string $login, string $passwordHash, string $perfil): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (login, password, perfil, activo)
             VALUES (:login, :password, :perfil, 1)'
        );
        $stmt->execute([
            ':login' => $login,
            ':password' => $passwordHash,
            ':perfil' => $perfil,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET password = :password
             WHERE id = :id'
        );
        $stmt->execute([
            ':password' => $passwordHash,
            ':id' => $userId,
        ]);
    }

    public function allActive(): array
    {
        return $this->db
            ->query('SELECT id, login, perfil FROM usuarios WHERE activo = 1 ORDER BY login')
            ->fetchAll();
    }
}
