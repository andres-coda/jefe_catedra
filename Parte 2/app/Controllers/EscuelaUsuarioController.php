<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\EscuelaUsuario;

/**
 * Roles por escuela: listar usuarios y asignar rol por email (PR2, RBAC).
 * Accesible para Directivo/Admin de la escuela (guarda en las rutas).
 */
final class EscuelaUsuarioController
{
    public function lista(Request $request): Response
    {
        $schoolId = $request->getAttribute('school_id');

        if ($schoolId === null) {
            return (new Response())->status(404)->body('No se encontró la página solicitada.');
        }

        $usuarios = EscuelaUsuario::listByEscuela($schoolId);

        return $this->page('usuarios', [
            'title' => 'Usuarios de la escuela',
            'schoolId' => $schoolId,
            'usuarios' => $usuarios,
        ]);
    }

    public function asignar(Request $request): Response
    {
        $schoolId = $request->getAttribute('school_id');

        if ($schoolId === null) {
            return (new Response())->status(404)->body('No se encontró la página solicitada.');
        }

        $email = trim((string) $request->post('email', ''));
        $rol = (string) $request->post('rol', '');

        if (!in_array($rol, EscuelaUsuario::ROLES, true)) {
            Session::flash('error', 'Rol inválido.');
        } else {
            $resultado = EscuelaUsuario::assignByEmail($schoolId, $email, $rol);

            if ($resultado === null) {
                Session::flash('error', sprintf('El email "%s" no está registrado en la plataforma.', $email));
            } else {
                Session::flash('ok', sprintf('Rol "%s" asignado a %s.', $rol, $resultado['usuario']['nombre']));
            }
        }

        return Response::redirect(Http::url('escuelas/' . $schoolId . '/usuarios'));
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