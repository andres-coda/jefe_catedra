<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\CursoEscuela;
use App\Models\CursoEscuelaDia;
use App\Models\CursoProfesor;
use App\Models\Dia;
use App\Models\DriveLink;
use App\Models\Escuela;
use App\Models\Profesor;
use App\Models\Tarea;

/**
 * Cursos de una escuela (PR3): listado filtrable (día/hora/profesor) y ficha
 * de curso con datos, horarios y (para staff) gestión de profesores, tareas
 * y enlaces de Drive.
 */
final class CursoController
{
    public function index(Request $request): Response
    {
        $schoolId = (string) $request->param('id', '');
        $escuela = $schoolId !== '' ? Escuela::findById($schoolId) : null;

        if ($escuela === null) {
            return $this->noEncontrado();
        }

        $filtros = CursoEscuela::filtrosDe($request);

        return $this->page('cursos', [
            'title' => 'Cursos',
            'escuela' => $escuela,
            'cursos' => CursoEscuela::listByEscuela($schoolId, $filtros),
            'dias' => Dia::list(),
            'filtro' => $filtros,
            'profesores' => $this->esStaff($request) ? Profesor::listByEscuela($schoolId) : [],
            'esStaff' => $this->esStaff($request),
            'filtroAction' => 'escuelas/' . $schoolId . '/cursos',
        ]);
    }

    public function show(Request $request): Response
    {
        $resuelto = $this->resolverCurso($request);
        if ($resuelto === null) {
            return $this->noEncontrado();
        }
        [$escuela, $curso] = $resuelto;

        $courseId = (string) $request->param('courseId', '');
        $esStaff = $this->esStaff($request);

        return $this->page('curso', [
            'title' => (string) $curso['curso'] . ' — ' . (string) $curso['materia'],
            'escuela' => $escuela,
            'curso' => $curso,
            'schoolId' => (string) $escuela['id'],
            'horarios' => CursoEscuelaDia::listByCursoEscuela($courseId),
            'esStaff' => $esStaff,
            'profesores' => $esStaff ? CursoProfesor::listByCurso($courseId) : [],
            'tareas' => $esStaff ? Tarea::listByCurso($courseId) : [],
            'links' => DriveLink::listByCurso($courseId),
        ]);
    }

    /**
     * Resuelve escuela + curso de la ruta, verificando que el curso pertenezca
     * a la escuela del path.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}|null
     */
    public static function resolverCurso(Request $request): ?array
    {
        $schoolId = (string) $request->param('id', '');
        $courseId = (string) $request->param('courseId', '');

        if ($schoolId === '' || $courseId === '') {
            return null;
        }

        $escuela = Escuela::findById($schoolId);
        $curso = CursoEscuela::detalleById($courseId);

        if ($escuela === null || $curso === null || (string) $curso['id_escuela'] !== $schoolId) {
            return null;
        }

        return [$escuela, $curso];
    }

    private function esStaff(Request $request): bool
    {
        return in_array(
            (string) $request->getAttribute('school_role', ''),
            ['jefe', 'directivo', 'admin'],
            true
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