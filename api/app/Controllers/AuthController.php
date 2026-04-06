<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Core\Controller;
use App\Models\Usuario;

final class AuthController extends Controller
{
    private ?Usuario $usuarios = null;

    private function usuariosModel(): Usuario
    {
        if (!$this->usuarios instanceof Usuario) {
            $this->usuarios = new Usuario(Database::getConnection());
        }

        return $this->usuarios;
    }

    public function registro(): never
    {
        $data = $this->requireField('login', 'password');
        $login = trim(htmlspecialchars((string)$data['login'], ENT_QUOTES, 'UTF-8'));
        $password = (string)$data['password'];
        $perfil = $this->normalizePerfil((string)$this->field('perfil', 'cliente'));

        if ($perfil === 'operador') {
            $this->fail('No esta permitido registrar operadores desde este formulario.', 403);
        }

        $perfilesValidos = ['cliente', 'transportista', 'aprovechador'];
        if (!in_array($perfil, $perfilesValidos, true)) {
            $this->fail('Perfil no valido.');
        }
        if (strlen($login) < 3) {
            $this->fail('El usuario debe tener al menos 3 caracteres.');
        }
        if (strlen($password) < 6) {
            $this->fail('La contrasena debe tener al menos 6 caracteres.');
        }
        if ($this->usuariosModel()->existsByLogin($login)) {
            $this->fail('Ese nombre de usuario ya existe.');
        }

        $id = $this->usuariosModel()->create($login, password_hash($password, PASSWORD_DEFAULT), $perfil);
        $this->ok([
            'id' => $id,
            'login' => $login,
            'perfil' => $perfil,
        ], 'Usuario registrado');
    }

    public function login(): never
    {
        $data = $this->requireField('login', 'password');
        $login = trim(htmlspecialchars((string)$data['login'], ENT_QUOTES, 'UTF-8'));
        $password = (string)$data['password'];

        $user = $this->usuariosModel()->findActiveByLogin($login);
        if (!$user) {
            $this->fail('Credenciales incorrectas', 401);
        }

        $storedPassword = (string)$user['password'];
        $isValid = password_verify($password, $storedPassword);

        if (!$isValid && hash_equals($storedPassword, $password)) {
            $isValid = true;
            $this->usuariosModel()->updatePassword((int)$user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        if (!$isValid) {
            $this->fail('Credenciales incorrectas', 401);
        }

        unset($user['password']);
        $_SESSION['user'] = $user;
        $this->ok($user, 'Sesion iniciada');
    }

    public function autorizar(): never
    {
        $user = $this->requireSession();
        $user['perfil'] = $this->normalizePerfil($user['perfil'] ?? '');
        $_SESSION['user'] = $user;
        $this->ok($user, "Autorizado ({$user['perfil']})");
    }

    public function logout(): never
    {
        session_destroy();
        $this->ok(null, 'Sesion cerrada');
    }

    public function usuarios(): never
    {
        $this->requireSession();
        $this->ok($this->usuariosModel()->allActive());
    }
}
