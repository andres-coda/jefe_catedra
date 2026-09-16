<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Models\CursoProfesor;
use App\Models\Profesor;
use App\Models\Usuario;

/**
 * Profesores por curso (PR3): el Jefe/Directivo asigna profesores del registro
 * de la escuela a un curso dictado (curso_profesor), registra profesores
 * nuevos y edita cese/detalles de la asignación. Los profesores no acceden.
 */
final class ProfesorController
{
    public function mostrar(Request $request): Response
    {
        $resuelto = CursoController::resolverCurso($request);
        if ($resuelto === null) {
            return $this->noEncontrado();
        }
        [$escuela, $curso] = $resuelto;

        $courseId = (string) $request->param('courseId', '');

        return $this->page('profesores', [
            'title' => 'Profesores del curso',
            'escuela' => $escuela,
            'curso' => $curso,
            'schoolId' => (string) $escuela['id'],
            'courseId' => $courseId,
            'asignaciones' => CursoProfesor::listByCurso($courseId),
            'profesoresEscuela' => Profesor::listByEscuela((string) $escuela['id']),
        ]);
    }

    public function crear(Request $request): Response
    {
        $resuelto = CursoController::resolverCurso($request);
        if ($resuelto === null) {
            return $this->noEncontrado();
        }
        [$escuela] = $resuelto;

        $schoolId = (string) $escuela['id'];
        $courseId = (string) $request->param('courseId', '');

        $profesorId = trim((string) $request->post('profesor_id', ''));
        $nombre = trim((string) $request->post('nombre', ''));
        $email = trim((string) $request->post('email', ''));
        $telefono = trim((string) $request->post('telefono', ''));
        $detalles = trim((string) $request->post('detalles', ''));

        if ($profesorId !== '') {
            if (!Router::isUuid($profesorId) || !Profesor::perteneceAEscuela($profesorId, $schoolId)) {
                Session::flash('error', 'El profesor seleccionado no pertenece a esta escuela.');

                return $this->volver($schoolId, $courseId);
            }

            CursoProfesor::asignar($courseId, $profesorId);
            Session::flash('ok', 'Profesor asignado al curso.');

            return $this->volver($schoolId, $courseId);
        }

        $error = self::validarNuevo($nombre, $email, $telefono, $detalles);
        if ($error !== null) {
            Session::flash('error', $error);

            return $this->volver($schoolId, $courseId);
        }

        try {
            $profesor = Profesor::create(
                $schoolId,
                $nombre,
                $email !== '' ? $email : null,
                $telefono !== '' ? $telefono : null,
                $detalles !== '' ? $detalles : null
            );
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'email_existente') {
                Session::flash('error', 'Ya existe un profesor con ese email en esta escuela.');

                return $this->volver($schoolId, $courseId);
            }
            throw $exception;
        }

        CursoProfesor::asignar($courseId, (string) $profesor['id']);
        Session::flash('ok', 'Profesor creado y asignado al curso.');

        return $this->volver($schoolId, $courseId);
    }

    public function actualizar(Request $request): Response
    {
        $contexto = $this->resolverAsignacion($request);
        if ($contexto === null) {
            return $this->noEncontrado();
        }
        [$schoolId, $courseId, $profesorId] = $contexto;

        $fechaCese = trim((string) $request->post('fecha_cese', ''));
        $detalles = trim((string) $request->post('detalles', ''));

        if ($fechaCese !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $fechaCese) !== 1) {
            Session::flash('error', 'La fecha de cese no es válida.');

            return $this->volver($schoolId, $courseId);
        }
        if (strlen($detalles) > 50) {
            Session::flash('error', 'Los detalles no pueden superar los 50 caracteres.');

            return $this->volver($schoolId, $courseId);
        }

        CursoProfesor::actualizarAsignacion(
            $courseId,
            $profesorId,
            $fechaCese !== '' ? $fechaCese : null,
            $detalles !== '' ? $detalles : null
        );
        Session::flash('ok', 'Asignación actualizada.');

        return $this->volver($schoolId, $courseId);
    }

    public function quitar(Request $request): Response
    {
        $contexto = $this->resolverAsignacion($request);
        if ($contexto === null) {
            return $this->noEncontrado();
        }
        [$schoolId, $courseId, $profesorId] = $contexto;

        CursoProfesor::quitarAsignacion($courseId, $profesorId);
        Session::flash('ok', 'Profesor quitado del curso.');

        return $this->volver($schoolId, $courseId);
    }

    /**
     * Alias POST de actualizar/quitar: los formularios HTML no envían PUT/DELETE;
     * el campo oculto _accion decide la operación.
     */
    public function desdeFormulario(Request $request): Response
    {
        return (string) $request->post('_accion', 'actualizar') === 'quitar'
            ? $this->quitar($request)
            : $this->actualizar($request);
    }

    /**
     * Resuelve y valida la asignación curso+profesor del path.
     *
     * @return array{0: string, 1: string, 2: string}|null [schoolId, courseId, profesorId]
     */
    private function resolverAsignacion(Request $request): ?array
    {
        $resuelto = CursoController::resolverCurso($request);
        if ($resuelto === null) {
            return null;
        }
        [$escuela] = $resuelto;

        $schoolId = (string) $escuela['id'];
        $courseId = (string) $request->param('courseId', '');
        $profesorId = (string) $request->param('profesorId', '');

        if (!Profesor::perteneceAEscuela($profesorId, $schoolId)
            || !CursoProfesor::existe($courseId, $profesorId)) {
            return null;
        }

        return [$schoolId, $courseId, $profesorId];
    }

    private static function validarNuevo(string $nombre, string $email, string $telefono, string $detalles): ?string
    {
        if ($nombre === '') {
            return 'El nombre del profesor es obligatorio.';
        }
        if (strlen($nombre) > 50) {
            return 'El nombre no puede superar los 50 caracteres.';
        }
        if ($email !== '' && (!Usuario::validarEmail($email) || strlen($email) > 50)) {
            return 'El email no es válido.';
        }
        if (strlen($telefono) > 15) {
            return 'El teléfono no puede superar los 15 caracteres.';
        }
        if (strlen($detalles) > 50) {
            return 'Los detalles no pueden superar los 50 caracteres.';
        }

        return null;
    }

    private function volver(string $schoolId, string $courseId): Response
    {
        return Response::redirect(
            Http::url('escuelas/' . $schoolId . '/cursos/' . $courseId . '/profesores')
        );
    }

    private function noEncontrado(): Response
    {
        return (new Response())->status(404)->body('No se encontró la página solicitada.');
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