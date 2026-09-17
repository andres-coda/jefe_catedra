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
 * Perfil del usuario autenticado: ver y editar nombre/email/contraseña (PR2).
 */
final class PerfilController
{
    public function show(Request $request): Response
    {
        $usuario = Usuario::findById((string) Session::get('user_id'));

        if ($usuario === null) {
            Session::destroy();
            return Response::redirect(Http::url('login'));
        }

        return $this->page('perfil', ['title' => 'Mi perfil', 'usuario' => $usuario]);
    }

    public function update(Request $request): Response
    {
        $usuario = Usuario::findById((string) Session::get('user_id'));

        if ($usuario === null) {
            Session::destroy();
            return Response::redirect(Http::url('login'));
        }

        $nombre = trim((string) $request->post('nombre', ''));
        $email = trim((string) $request->post('email', ''));
        $passActual = (string) $request->post('pass_actual', '');
        $passNueva = (string) $request->post('pass', '');

        $error = self::validarPerfil($usuario, $nombre, $email, $passActual, $passNueva);
        if ($error !== null) {
            Session::flash('error', $error);
            return Response::redirect(Http::url('perfil'));
        }

        if (!Usuario::emailDisponible($email, (string) $usuario['id'])) {
            Session::flash('error', 'Ya existe otra cuenta con ese email.');
            return Response::redirect(Http::url('perfil'));
        }

        $nuevoHash = $passNueva !== '' ? Usuario::hashPassword($passNueva) : null;
        Usuario::updateProfile((string) $usuario['id'], $nombre, $email, $nuevoHash);

        Session::set('user_nombre', $nombre);
        Session::flash('ok', 'Perfil actualizado.');

        return Response::redirect(Http::url('perfil'));
    }

    /**
     * @param array<string, mixed> $usuario
     */
    private static function validarPerfil(array $usuario, string $nombre, string $email, string $passActual, string $passNueva): ?string
    {
        if ($nombre === '') {
            return 'El nombre es obligatorio.';
        }
        if (!Usuario::validarEmail($email)) {
            return 'El email no es válido.';
        }
        if ($passNueva !== '' && strlen($passNueva) < 6) {
            return 'La contraseña nueva debe tener al menos 6 caracteres.';
        }
        if ($passNueva !== '' && !Usuario::verifyPassword($passActual, (string) $usuario['pass'])) {
            return 'La contraseña actual no es correcta.';
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