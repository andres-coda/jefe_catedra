<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\DriveLink;

/**
 * Enlaces de Drive por curso (PR3): Jefe/Directivo agrega, edita y elimina
 * enlaces de un curso dictado. La lista es pública (visible al ver el curso).
 */
final class DriveLinkController
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

        $url = trim((string) $request->post('url', ''));
        $descripcion = trim((string) $request->post('descripcion', ''));

        $error = self::validar($url, $descripcion);
        if ($error !== null) {
            Session::flash('error', $error);

            return $this->volver($schoolId, $courseId);
        }

        try {
            DriveLink::create($courseId, $url, $descripcion !== '' ? $descripcion : null);
        } catch (\PDOException $exception) {
            Session::flash('error', 'No se pudo guardar el enlace: verificá tus permisos sobre el curso.');

            return $this->volver($schoolId, $courseId);
        }

        Session::flash('ok', 'Enlace agregado.');

        return $this->volver($schoolId, $courseId);
    }

    public function actualizar(Request $request): Response
    {
        $contexto = $this->resolverLink($request);
        if ($contexto === null) {
            return $this->noEncontrado();
        }
        [$schoolId, $courseId, $linkId] = $contexto;

        $url = trim((string) $request->post('url', ''));
        $descripcion = trim((string) $request->post('descripcion', ''));

        $error = self::validar($url, $descripcion);
        if ($error !== null) {
            Session::flash('error', $error);

            return $this->volver($schoolId, $courseId);
        }

        try {
            DriveLink::update($linkId, $url, $descripcion !== '' ? $descripcion : null);
        } catch (\PDOException $exception) {
            Session::flash('error', 'No se pudo actualizar el enlace: verificá tus permisos sobre el curso.');

            return $this->volver($schoolId, $courseId);
        }

        Session::flash('ok', 'Enlace actualizado.');

        return $this->volver($schoolId, $courseId);
    }

    public function eliminar(Request $request): Response
    {
        $contexto = $this->resolverLink($request);
        if ($contexto === null) {
            return $this->noEncontrado();
        }
        [$schoolId, $courseId, $linkId] = $contexto;

        try {
            DriveLink::delete($linkId);
        } catch (\PDOException $exception) {
            Session::flash('error', 'No se pudo eliminar el enlace: verificá tus permisos sobre el curso.');

            return $this->volver($schoolId, $courseId);
        }

        Session::flash('ok', 'Enlace eliminado.');

        return $this->volver($schoolId, $courseId);
    }

    /**
     * Alias POST de actualizar/eliminar (los formularios HTML no envían
     * PUT/DELETE; el campo oculto _accion decide la operación).
     */
    public function desdeFormulario(Request $request): Response
    {
        return (string) $request->post('_accion', 'actualizar') === 'eliminar'
            ? $this->eliminar($request)
            : $this->actualizar($request);
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null [schoolId, courseId, linkId]
     */
    private function resolverLink(Request $request): ?array
    {
        $resuelto = CursoController::resolverCurso($request);
        if ($resuelto === null) {
            return null;
        }
        [$escuela] = $resuelto;

        $courseId = (string) $request->param('courseId', '');
        $linkId = (string) $request->param('linkId', '');
        $link = DriveLink::findById($linkId);

        if ($link === null || (string) $link['id_curso_escuela'] !== $courseId) {
            return null;
        }

        return [(string) $escuela['id'], $courseId, $linkId];
    }

    private static function validar(string $url, string $descripcion): ?string
    {
        if ($url === '') {
            return 'La URL del enlace es obligatoria.';
        }
        if (!DriveLink::esUrlValida($url)) {
            return 'La URL debe ser un enlace válido que empiece con http:// o https://.';
        }
        if (strlen($descripcion) > 255) {
            return 'La descripción no puede superar los 255 caracteres.';
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