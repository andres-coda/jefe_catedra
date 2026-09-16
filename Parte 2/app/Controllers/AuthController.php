<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Usuario;

/**
 * Autenticación: login, registro y logout (PR2, specs AUTH-01/03).
 */
final class AuthController
{
    public function showLogin(Request $request): Response
    {
        if (Session::has('user_id')) {
            return Response::redirect(Http::url(''));
        }

        return $this->page('login', ['title' => 'Iniciar sesión']);
    }

    public function login(Request $request): Response
    {
        $email = trim((string) $request->post('email', ''));
        $password = (string) $request->post('pass', '');

        $usuario = Usuario::findByEmail($email);
        if ($usuario === null || !Usuario::verifyPassword($password, $usuario['pass'])) {
            Session::flash('error', 'Email o contraseña incorrectos.');
            return Response::redirect(Http::url('login'));
        }

        self::iniciarSesion($usuario);

        return Response::redirect(Http::url(''));
    }

    public function showRegistro(Request $request): Response
    {
        return $this->page('registro', ['title' => 'Registrarse']);
    }

    public function registro(Request $request): Response
    {
        $nombre = trim((string) $request->post('nombre', ''));
        $email = trim((string) $request->post('email', ''));
        $password = (string) $request->post('pass', '');

        $error = self::validarRegistro($nombre, $email, $password);
        if ($error !== null) {
            Session::flash('error', $error);
            return Response::redirect(Http::url('registro'));
        }

        try {
            $usuario = Usuario::create($nombre, $email, $password);
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'email_existente') {
                Session::flash('error', 'Ya existe una cuenta con ese email.');
                return Response::redirect(Http::url('registro'));
            }
            throw $exception;
        }

        self::iniciarSesion($usuario);

        return Response::redirect(Http::url(''));
    }

    public function logout(Request $request): Response
    {
        Session::destroy();

        return Response::redirect(Http::url('login'));
    }

    /**
     * @param array<string, mixed> $usuario
     */
    private static function iniciarSesion(array $usuario): void
    {
        Session::set('user_id', $usuario['id']);
        Session::set('user_nombre', $usuario['nombre']);
        Session::regenerate();
    }

    private static function validarRegistro(string $nombre, string $email, string $password): ?string
    {
        if ($nombre === '') {
            return 'El nombre es obligatorio.';
        }
        if (!Usuario::validarEmail($email)) {
            return 'El email no es válido.';
        }
        if (strlen($password) < 6) {
            return 'La contraseña debe tener al menos 6 caracteres.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function page(string $template, array $vars = []): Response
    {
        $html = View::render('layout', [
            'title' => $vars['title'] ?? '',
            'content' => View::render($template, $vars),
        ]);

        return (new Response())
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->body($html);
    }
}