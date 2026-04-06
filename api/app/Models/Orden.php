<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class Orden
{
    private const COLUMNA_POR_PERFIL = [
        'cliente' => 'cliente',
        'aprovechador' => 'aprovechador',
        'transportista' => 'transportista',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function getOrdenes(int $userId, string $perfil): array
    {
        if ($perfil === 'operador') {
            $whereSql = '1 = 1';
            $params = [];
        } elseif (isset(self::COLUMNA_POR_PERFIL[$perfil])) {
            $whereSql = 'o.' . self::COLUMNA_POR_PERFIL[$perfil] . ' = :userId';
            $params = [':userId' => $userId];
        } else {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT
                o.id,
                o.forden,
                o.fprogramada,
                c.categoria,
                m.material,
                o.cantidad,
                med.medida,
                e.estado,
                e.id AS estado_id,
                o.cliente AS cliente,
                o.transportista AS transportista,
                o.aprovechador AS aprovechador
             FROM ordenes o
             LEFT JOIN categorias c ON c.id = o.categoria
             LEFT JOIN materiales m ON m.id = o.material
             LEFT JOIN medidas med ON med.id = o.medida
             LEFT JOIN estados e ON e.id = o.estado
             WHERE {$whereSql}
             ORDER BY o.forden DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function create(
        int $clienteId,
        string $fprogramada,
        int $categoriaId,
        int|string $materialId,
        float $cantidad,
        int $medidaId
    ): bool {
        $materialIdReal = $this->resolveMaterialId($materialId, $categoriaId, $medidaId);
        if ($materialIdReal <= 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO ordenes
                (cliente, categoria, material, cantidad, medida, fprogramada, transportista, aprovechador, estado, forden, faprovechador, ftransportista, fcamino, frecogida, fentrega)
             VALUES
                (:cliente, :categoria, :material, :cantidad, :medida, :fprogramada, 0, 0, 1, NOW(), "0000-00-00 00:00:00", "0000-00-00 00:00:00", "0000-00-00 00:00:00", "0000-00-00 00:00:00", "0000-00-00 00:00:00")'
        );

        return $stmt->execute([
            ':cliente' => $clienteId,
            ':categoria' => $categoriaId,
            ':material' => $materialIdReal,
            ':cantidad' => $cantidad,
            ':medida' => $medidaId,
            ':fprogramada' => $fprogramada,
        ]);
    }

    private function resolveMaterialId(int|string $materialValue, int $categoriaId, int $medidaId): int
    {
        if (is_numeric($materialValue) && (int)$materialValue > 0) {
            return (int)$materialValue;
        }

        $stmt = $this->db->prepare(
            'SELECT id
             FROM materiales
             WHERE activo = 1
               AND material = :material
               AND categoria = :categoria
               AND medida = :medida
             LIMIT 1'
        );
        $stmt->execute([
            ':material' => trim((string)$materialValue),
            ':categoria' => $categoriaId,
            ':medida' => $medidaId,
        ]);
        $row = $stmt->fetch();

        return (int)($row['id'] ?? 0);
    }

    public function cambiarEstado(
        int $userId,
        string $perfil,
        int $ordenId,
        int $estadoDestino,
        int $transportistaId = 0,
        int $aprovechadorId = 0
    ): bool {
        $sql = '';
        $params = [':orden' => $ordenId];

        switch ($perfil) {
            case 'operador':
                if ($estadoDestino === 2 && $aprovechadorId > 0) {
                    $sql = 'UPDATE ordenes
                            SET estado = 2,
                                aprovechador = :aprovechador,
                                faprovechador = NOW()
                            WHERE id = :orden
                              AND estado = 1';
                    $params[':aprovechador'] = $aprovechadorId;
                } elseif ($estadoDestino === 4 && $transportistaId > 0) {
                    $sql = 'UPDATE ordenes
                            SET estado = 4,
                                transportista = :transportista,
                                ftransportista = NOW()
                            WHERE id = :orden
                              AND estado = 3';
                    $params[':transportista'] = $transportistaId;
                }
                break;

            case 'aprovechador':
                if ($estadoDestino === 3) {
                    $sql = 'UPDATE ordenes
                            SET estado = 3
                            WHERE id = :orden
                              AND estado = 2
                              AND aprovechador = :usuario';
                    $params[':usuario'] = $userId;
                } elseif ($estadoDestino === 9) {
                    $sql = 'UPDATE ordenes
                            SET estado = 9
                            WHERE id = :orden
                              AND estado = 8
                              AND aprovechador = :usuario';
                    $params[':usuario'] = $userId;
                }
                break;

            case 'transportista':
                if ($estadoDestino === 5) {
                    $sql = 'UPDATE ordenes
                            SET estado = 5
                            WHERE id = :orden
                              AND estado = 4
                              AND transportista = :usuario';
                    $params[':usuario'] = $userId;
                } elseif ($estadoDestino === 6) {
                    $sql = 'UPDATE ordenes
                            SET estado = 6,
                                fcamino = NOW()
                            WHERE id = :orden
                              AND estado = 5
                              AND transportista = :usuario';
                    $params[':usuario'] = $userId;
                }
                break;
        }

        if ($sql === '') {
            return false;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function cancelar(int $ordenId, int $canceladoId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ordenes
             SET estado = :cancelado
             WHERE id = :orden
               AND estado <> :cancelado2'
        );
        $stmt->execute([
            ':cancelado'  => $canceladoId,
            ':cancelado2' => $canceladoId,
            ':orden'      => $ordenId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function findAccessibleByPerfil(int $ordenId, int $userId, string $perfil): array|false
    {
        $whereSql = match ($perfil) {
            'cliente' => 'o.cliente = :userId',
            'aprovechador' => 'o.aprovechador = :userId',
            'transportista' => 'o.transportista = :userId',
            'operador' => '1 = 1',
            default => '1 = 0',
        };

        $stmt = $this->db->prepare(
            "SELECT o.id, o.estado AS id_estado
             FROM ordenes o
             WHERE o.id = :orden
               AND {$whereSql}
             LIMIT 1"
        );

        $params = [':orden' => $ordenId];
        if ($perfil !== 'operador') {
            $params[':userId'] = $userId;
        }

        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Actualiza la orden con la foto de evidencia y avanza al estado destino.
     *
     * estado 7 (Recogido en cliente): transición desde 6, campo foto_recoleccion / frecogida
     * estado 8 (Entregado aprovechador): transición desde 7, campo foto_entrega / fentrega
     */
    public function subirEvidencia(int $userId, int $ordenId, int $estadoDestino, string $ruta): bool
    {
        $map = [
            7 => ['desde' => 6, 'foto' => 'foto_recoleccion', 'fecha' => 'frecogida'],
            8 => ['desde' => 7, 'foto' => 'foto_entrega',     'fecha' => 'fentrega'],
        ];

        if (!isset($map[$estadoDestino])) {
            return false;
        }

        ['desde' => $desde, 'foto' => $campoFoto, 'fecha' => $campoFecha] = $map[$estadoDestino];

        $stmt = $this->db->prepare(
            "UPDATE ordenes
             SET estado        = :estado,
                 {$campoFoto}  = :ruta,
                 {$campoFecha} = NOW()
             WHERE id            = :orden
               AND estado        = :desde
               AND transportista = :usuario"
        );

        $stmt->execute([
            ':estado'  => $estadoDestino,
            ':ruta'    => $ruta,
            ':orden'   => $ordenId,
            ':desde'   => $desde,
            ':usuario' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /** Devuelve el estado actual de una orden: ['id' => int, 'nombre' => string] */
    public function getEstadoActual(int $ordenId): array
    {
        $stmt = $this->db->prepare(
            'SELECT e.id, e.estado AS nombre
             FROM ordenes o
             LEFT JOIN estados e ON e.id = o.estado
             WHERE o.id = :orden
             LIMIT 1'
        );
        $stmt->execute([':orden' => $ordenId]);
        $row = $stmt->fetch();
        return [
            'id'     => (int)($row['id']     ?? 0),
            'nombre' => (string)($row['nombre'] ?? 'desconocido'),
        ];
    }

    /**
     * Devuelve el ID del estado "Cancelado".
     * Intenta varias grafías para tolerar "Cancelado", "Cancelada", "Orden cancelada", etc.
     * Si ninguna coincide, devuelve el ID más alto de la tabla (convención: el último estado es cancelado).
     * En último recurso retorna 0 para que el controlador informe el error correctamente.
     */
    public function getEstadoCanceladoId(): int
    {
        // Intento 1: coincidencia parcial con "cancel"
        $stmt = $this->db->query(
            "SELECT id FROM estados WHERE LOWER(TRIM(estado)) LIKE '%cancel%' ORDER BY id DESC LIMIT 1"
        );
        $row = $stmt->fetch();
        if ($row) {
            return (int)$row['id'];
        }

        // Intento 2: devolver todos los estados para loguear cuáles existen
        $todos = $this->db->query("SELECT id, estado FROM estados ORDER BY id")->fetchAll();
        error_log('[Redciclo/getEstadoCanceladoId] No se encontró estado cancelado. Estados en BD: '
            . json_encode($todos, JSON_UNESCAPED_UNICODE));

        return 0;
    }

    public function getCategorias(): array
    {
        return $this->db
            ->query('SELECT id, categoria FROM categorias WHERE activo = 1 ORDER BY categoria')
            ->fetchAll();
    }

    public function getMateriales(): array
    {
        $rows = $this->db
            ->query('SELECT material, categoria, medida FROM materiales WHERE activo = 1 ORDER BY material, categoria, medida')
            ->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $key = mb_strtolower(trim((string)$row['material'])) . '|' . (int)$row['categoria'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'nombre_material' => (string)$row['material'],
                    'id_categoria' => (int)$row['categoria'],
                    'medidas_permitidas' => [],
                ];
            }

            $medidaId = (int)$row['medida'];
            if (!in_array($medidaId, $grouped[$key]['medidas_permitidas'], true)) {
                $grouped[$key]['medidas_permitidas'][] = $medidaId;
            }
        }

        return array_values($grouped);
    }

    public function getMedidas(): array
    {
        return $this->db
            ->query('SELECT id, medida FROM medidas ORDER BY medida')
            ->fetchAll();
    }

    public function getEstados(): array
    {
        return $this->db
            ->query('SELECT id, estado FROM estados ORDER BY id')
            ->fetchAll();
    }
}
