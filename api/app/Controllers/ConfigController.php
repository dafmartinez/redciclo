<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Controller;
use App\Models\Orden;

final class ConfigController extends Controller
{
    private Orden $ordenes;

    public function __construct()
    {
        $this->ordenes = new Orden(Database::getConnection());
    }

    public function configuracion(): never
    {
        $this->requireSession();
        $grupo = (string)$this->field('grupo', '');

        $data = match ($grupo) {
            'categorias' => $this->ordenes->getCategorias(),
            'materiales' => $this->ordenes->getMateriales(),
            'medidas' => $this->ordenes->getMedidas(),
            'estados' => $this->ordenes->getEstados(),
            default => $this->fail("Grupo no valido: {$grupo}"),
        };

        $this->ok($data);
    }
}
