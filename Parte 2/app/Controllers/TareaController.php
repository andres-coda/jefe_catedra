<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Tarea;

/**
 * Tareas por curso (PR3): Jefe/Directivo crea tareas de un curso dictado,
 * edita descripción/observaciones y marca el flag "realizado".
 */
final class TareaController
{
    public function crear(Request $request): Response
    {
        $resuelto = CursoController::resolverCurso($request);
        if ($resuelto === null) {
            return $this->noEncontrado();
        }
        [$escuela] = $resuelto;

        $schoolId = (string) $escuela['id'];
        $courseId = (string) $request->param('courseId', '');

        $descripcion = trim((string) $request->post('descripcion', ''));
        $observaciones = trim((string) $request->post('observaciones', ''));

        $error = self::validar($descripcion, $observaciones);
        if ($error !== null) {
            Session::flash('error', $error);

            return $this->volver($schoolId, $courseId);
        }

        try {
            Tarea::create($courseId, $descripcion, $observaciones !== '' ? $observaciones : null);
        } catch (\PDOException $exception) {
            Session::flash('error', 'No se pudo guardar la tarea: verificá tus permisos sobre el curso.');

            return $this->volver($schoolId, $courseId);
        }

        Session::flash('ok', 'Tarea creada.');

        return $this->volver($schoolId, $courseId);
    }

    public function actualizar(Request $request): Response
    {
        $contexto = $this->resolverTarea($request);
        if ($contexto === null) {
            return $this->noEncontrado();
        }
        [$schoolId, $courseId, $tareaId] = $contexto;

        $descripcion = trim((string) $request->post('descripcion', ''));
        $observaciones = trim((string) $request->post('observaciones', ''));
        $realizado = self::esRealizado($request);

        $error = self::validar($descripcion, $observaciones);
        if ($error !== null) {
            Session::flash('error', $error);

            return $this->volver($schoolId, $courseId);
        }

        try {
            Tarea::actualizar($tareaId, $descripcion, $observaciones !== '' ? $observaciones : null, $realizado);
        } catch (\PDOException $exception) {
            Session::flash('error', 'No se pudo actualizar la tarea: verificá tus permisos sobre el curso.');

            return $this->volver($schoolId, $courseId);
        }

        Session::flash('ok', 'Tarea actualizada.');

        return $this->volver($schoolId, $courseId);
    }

    public function marcar(Request $request): Response
    {
        $contexto = $this->resolverTarea($request);
        if ($contexto === null) {
            return $this->noEncontrado();
        }
        [$schoolId, $courseId, $tareaId] = $contexto;

        $realizado = self::esRealizado($request);

        try {
            Tarea::actualizarRealizado($tareaId, $realizado);
        } catch (\PDOException $exception) {
            Session::flash('error', 'No se pudo actualizar la tarea: verificá tus permisos sobre el curso.');

            return $this->volver($schoolId, $courseId);
        }

        Session::flash('ok', $realizado ? 'Tarea marcada como realizada.' : 'Tarea marcada como pendiente.');

        return $this->volver($schoolId, $courseId);
    }

    /**
     * Alias POST de actualizar/marcar (los formularios HTML no envían PUT).
     */
    public function desdeFormulario(Request $request): Response
    {
        return (string) $request->post('_accion', 'actualizar') === 'marcar'
            ? $this->marcar($request)
            : $this->actualizar($request);
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null [schoolId, courseId, tareaId]
     */
    private function resolverTarea(Request $request): ?array
    {
        $resuelto = CursoController::resolverCurso($request);
        if ($resuelto === null) {
            return null;
        }
        [$escuela] = $resuelto;

        $courseId = (string) $request->param('courseId', '');
        $tareaId = (string) $request->param('tareaId', '');
        $tarea = Tarea::findById($tareaId);

        if ($tarea === null || (string) $tarea['id_curso_escuela'] !== $courseId) {
            return null;
        }

        return [(string) $escuela['id'], $courseId, $tareaId];
    }

    private static function esRealizado(Request $request): bool
    {
        $valor = (string) $request->post('realizado', '1');

        return $valor !== '0' && $valor !== '';
    }

    private static function validar(string $descripcion, string $observaciones): ?string
    {
        if ($descripcion === '') {
            return 'La descripción de la tarea es obligatoria.';
        }
        if (strlen($descripcion) > 50) {
            return 'La descripción no puede superar los 50 caracteres.';
        }
        if (strlen($observaciones) > 255) {
            return 'Las observaciones no pueden superar los 255 caracteres.';
        }

        return null;
    }

    private function volver(string $schoolId, string $courseId): Response
    {
        return Response::redirect(Http::url('escuelas/' . $schoolId . '/cursos/' . $courseId));
    }

    private function noEncontrado(): Response
    {
        return (new Response())->status(404)->body('No se encontró la página solicitada.');
    }
}