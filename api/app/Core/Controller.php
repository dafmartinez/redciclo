<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function json(string $status, mixed $data = null, string $message = '', int $httpCode = 200): never
    {
        http_response_code($httpCode);
        echo json_encode([
            'status' => $status,
            'data' => $data,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function ok(mixed $data = null, string $message = ''): never
    {
        $this->json('success', $data, $message);
    }

    protected function fail(string $message, int $httpCode = 400, mixed $data = null): never
    {
        $this->json('error', $data, $message, $httpCode);
    }

    protected function input(): array
    {
        static $payload = null;
        if ($payload === null) {
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        }
        return $payload;
    }

    protected function field(string $key, mixed $default = null): mixed
    {
        $body = $this->input();
        return $body[$key] ?? $_POST[$key] ?? $default;
    }

    protected function requireField(string ...$keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $value = $this->field($key);
            if ($value === null || $value === '') {
                $this->fail("Campo requerido: {$key}");
            }
            $data[$key] = $value;
        }
        return $data;
    }

    protected function requireSession(): array
    {
        if (empty($_SESSION['user'])) {
            $this->fail('No autenticado', 401);
        }

        return $_SESSION['user'];
    }

    protected function normalizePerfil(?string $perfil): string
    {
        $perfil = strtolower(trim((string)$perfil));

        return match ($perfil) {
            'cliente', 'clientes' => 'cliente',
            'transportista', 'transportistas', 'conductor' => 'transportista',
            'aprovechador', 'aprovechadores', 'reciclador' => 'aprovechador',
            'operador', 'operadores', 'admin', 'administrador' => 'operador',
            default => $perfil,
        };
    }
}
