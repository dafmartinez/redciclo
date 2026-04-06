<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Controller;
use App\Models\Tracking;

final class TrackingController extends Controller
{
    public function guardar(): never
    {
        // Solo transportistas pueden enviar ubicación
        $user   = $this->requireSession();
        $perfil = $this->normalizePerfil($user['perfil'] ?? '');

        if ($perfil !== 'transportista') {
            $this->fail('Solo el transportista puede enviar ubicacion.', 403);
        }

        // Validar campos obligatorios
        $data = $this->requireField('orden', 'lat', 'lng');

        $ordenId        = (int)$data['orden'];
        $latitud        = (float)$data['lat'];
        $longitud       = (float)$data['lng'];
        $transportistaId = (int)$user['id'];

        if ($ordenId <= 0) {
            $this->fail('ID de orden no valido.');
        }

        // Rango básico de coordenadas
        if ($latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
            $this->fail('Coordenadas fuera de rango.');
        }

        try {
            $model = new Tracking(Database::getConnection());
            $model->guardar($ordenId, $transportistaId, $latitud, $longitud);
        } catch (\RuntimeException $e) {
            error_log($e->getMessage());
            $this->fail('No se pudo guardar la ubicacion: ' . $e->getMessage(), 500);
        }

        $this->ok(null, 'Ubicacion registrada');
    }

    public function obtenerRuta(): never
    {
        $this->requireSession();
        $data    = $this->requireField('orden');
        $ordenId = (int)$data['orden'];

        if ($ordenId <= 0) {
            $this->fail('ID de orden no valido.');
        }

        error_log('[Redciclo/obtenerRuta] Buscando tracking para orden: ' . $ordenId);

        $model  = new Tracking(Database::getConnection());
        $puntos = $model->obtenerRutaPorOrden($ordenId);

        error_log('[Redciclo/obtenerRuta] Puntos encontrados: ' . count($puntos));

        $this->ok($puntos);
    }
}
