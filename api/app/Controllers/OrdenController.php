<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Controller;
use App\Models\Nota;
use App\Models\Orden;

final class OrdenController extends Controller
{
    private Orden $ordenes;
    private Nota $notas;

    public function __construct()
    {
        $db = Database::getConnection();
        $this->ordenes = new Orden($db);
        $this->notas = new Nota($db);
    }

    public function listar(): never
    {
        $user = $this->requireSession();
        $perfil = $this->normalizePerfil((string)$this->field('perfil', $user['perfil']));

        $permitidos = ['cliente', 'transportista', 'aprovechador', 'operador'];
        if (!in_array($perfil, $permitidos, true)) {
            $this->fail('Perfil no valido');
        }

        $ordenes = $this->ordenes->getOrdenes((int)$user['id'], $perfil);
        $this->ok($ordenes);
    }

    public function crear(): never
    {
        $user = $this->requireSession();
        $perfil = $this->normalizePerfil($user['perfil'] ?? '');

        if ($perfil !== 'cliente') {
            $this->fail('Solo los usuarios con perfil cliente pueden crear solicitudes.', 403);
        }

        $data = $this->requireField('fprogramada', 'categoria', 'material', 'cantidad', 'medida');

        $ok = $this->ordenes->create(
            (int)$user['id'],
            (string)$data['fprogramada'],
            (int)$data['categoria'],
            (string)$data['material'],
            (float)$data['cantidad'],
            (int)$data['medida']
        );

        $ok ? $this->ok(null, 'Orden creada') : $this->fail('No se pudo crear la orden');
    }

    public function cambiarEstado(): never
    {
        $user = $this->requireSession();
        $perfil = $this->normalizePerfil($user['perfil'] ?? '');
        $data = $this->requireField('orden', 'estado');
        $estado = (int)$data['estado'];
        $idAsignado = (int)$this->field('id_asignado', 0);
        $transportistaId = $estado === 4 ? $idAsignado : (int)$this->field('transportista', 0);
        $aprovechadorId = $estado === 2 ? $idAsignado : (int)$this->field('aprovechador', 0);

        $ordenId = (int)$data['orden'];

        $ok = $this->ordenes->cambiarEstado(
            (int)$user['id'],
            $perfil,
            $ordenId,
            $estado,
            $transportistaId,
            $aprovechadorId
        );

        if (!$ok) {
            $estadoActual = $this->ordenes->getEstadoActual($ordenId);
            $this->fail(
                "Transicion rechazada. " .
                "Orden #{$ordenId} esta en estado_id={$estadoActual['id']} " .
                "({$estadoActual['nombre']}). " .
                "Se intentaba mover a estado_id={$estado}.",
                400
            );
        }

        $this->ok(null, 'Estado actualizado');
    }

    public function crearNota(): never
    {
        $user = $this->requireSession();
        $perfil = $this->normalizePerfil($user['perfil'] ?? '');
        $data = $this->requireField('orden', 'nota');

        $orden = $this->ordenes->findAccessibleByPerfil((int)$data['orden'], (int)$user['id'], $perfil);
        if (!$orden) {
            $this->fail('No tienes acceso a esta orden.', 403);
        }

        if ($perfil !== 'operador' && (int)$orden['id_estado'] === 9) {
            $this->fail('No puedes crear notas en el estado actual.', 403);
        }

        $ok = $this->notas->create((int)$data['orden'], (int)$user['id'], trim((string)$data['nota']));
        $ok ? $this->ok(null, 'Nota guardada') : $this->fail('No se pudo guardar la nota');
    }

    public function listarNotas(): never
    {
        $this->requireSession();
        $data = $this->requireField('orden');
        $this->ok($this->notas->allByOrden((int)$data['orden']));
    }

    public function cancelar(): never
    {
        $user = $this->requireSession();
        $perfil = $this->normalizePerfil($user['perfil'] ?? '');

        if ($perfil !== 'operador') {
            $this->fail('No tienes permiso para cancelar ordenes.', 403);
        }

        $data = $this->requireField('orden');
        $canceladoId = $this->ordenes->getEstadoCanceladoId();
        if ($canceladoId <= 0) {
            $this->fail('Estado cancelado no configurado.', 500);
        }

        $ok = $this->ordenes->cancelar((int)$data['orden'], $canceladoId);
        $ok ? $this->ok(null, 'Orden cancelada') : $this->fail('No se pudo cancelar la orden');
    }

    public function subirEvidencia(): never
    {
        $user   = $this->requireSession();
        $perfil = $this->normalizePerfil($user['perfil'] ?? '');

        if ($perfil !== 'transportista') {
            $this->fail('Solo el transportista puede subir evidencias.', 403);
        }

        // FormData → leer de $_POST y $_FILES, no de php://input
        $ordenId = (int)($_POST['orden'] ?? 0);
        $estado  = (int)($_POST['estado'] ?? 0);

        if ($ordenId <= 0) {
            $this->fail('ID de orden no valido.');
        }

        if (!in_array($estado, [7, 8], true)) {
            $this->fail('Estado de evidencia no valido. Se esperaba 7 (recoger) u 8 (entregar).');
        }

        // Validar que el archivo llegó sin errores
        $uploadError = $_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            $msgs = [
                UPLOAD_ERR_INI_SIZE   => 'La imagen supera el tamaño máximo permitido por el servidor.',
                UPLOAD_ERR_FORM_SIZE  => 'La imagen supera el tamaño máximo del formulario.',
                UPLOAD_ERR_PARTIAL    => 'La imagen se subio parcialmente. Intenta de nuevo.',
                UPLOAD_ERR_NO_FILE    => 'No se recibio ninguna imagen.',
                UPLOAD_ERR_NO_TMP_DIR => 'Error interno del servidor (sin directorio temporal).',
                UPLOAD_ERR_CANT_WRITE => 'Error interno del servidor (sin permisos de escritura).',
            ];
            $this->fail($msgs[$uploadError] ?? 'Error desconocido al recibir la imagen.', 400);
        }

        $tmpPath = $_FILES['foto']['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpPath)) {
            $this->fail('Archivo no valido.', 400);
        }

        // Validar tipo MIME real (no confiar en extensión ni en Content-Type del cliente)
        $mime = mime_content_type($tmpPath);
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extMap[$mime])) {
            $this->fail('El archivo debe ser una imagen JPG, PNG o WebP.');
        }

        // Limitar tamaño: 5 MB
        if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
            $this->fail('La imagen no puede superar 5 MB.');
        }

        // Directorio de destino: public/uploads/evidencias/ (accesible por URL)
        $uploadDir = dirname(__DIR__, 3) . '/public/uploads/evidencias/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            error_log('[Redciclo/subirEvidencia] No se pudo crear el directorio: ' . $uploadDir);
            $this->fail('Error interno al preparar el almacenamiento.', 500);
        }

        // Nombre único e impredecible: no expone IDs en filesystem
        $filename = sprintf('%d_%d_%s.%s', $ordenId, $estado, bin2hex(random_bytes(8)), $extMap[$mime]);
        $destino  = $uploadDir . $filename;

        if (!move_uploaded_file($tmpPath, $destino)) {
            $this->fail('No se pudo guardar la imagen en el servidor.', 500);
        }

        $rutaRelativa = 'uploads/evidencias/' . $filename;

        $ok = $this->ordenes->subirEvidencia(
            (int)$user['id'],
            $ordenId,
            $estado,
            $rutaRelativa
        );

        if (!$ok) {
            @unlink($destino); // limpiar archivo huérfano si el UPDATE falla
            $this->fail('Estado no permitido o la orden no te pertenece.');
        }

        $this->ok(['ruta' => $rutaRelativa], 'Evidencia subida correctamente');
    }
}
