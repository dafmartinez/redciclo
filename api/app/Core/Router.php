<?php
declare(strict_types=1);

namespace App\Core;

use Closure;

final class Router
{
    /** @var array<string, Closure> */
    private array $routes = [];

    public function post(string $method, callable $handler): void
    {
        $this->routes[$method] = Closure::fromCallable($handler);
    }

    public function dispatch(string $method): mixed
    {
        if (!isset($this->routes[$method])) {
            throw new \RuntimeException("Metodo desconocido: {$method}");
        }

        return ($this->routes[$method])();
    }
}
