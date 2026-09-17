<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\CursoEscuela;
use App\Models\Dia;
use App\Models\Escuela;
use App\Models\Profesor;

/**
 * Áreas de una escuela (PR3): cursos agrupados por área y materia, con los
 * mismos filtros (día/hora/profesor) que el listado de cursos.
 */
final class AreaController
{
    public function index(Request $request): Response
    {
        $schoolId = (string) $request->param('id', '');
        $escuela = $schoolId !== '' ? Escuela::findById($schoolId) : null;

        if ($escuela === null) {
            return $this->noEncontrado();
        }

        $filtros = CursoEscuela::filtrosDe($request);
        $esStaff = $this->esStaff($request);

        return $this->page('areas', [
            'title' => 'Áreas',
            'escuela' => $escuela,
            'agrupado' => CursoEscuela::agruparPorArea(CursoEscuela::listByEscuela($schoolId, $filtros)),
            'dias' => Dia::list(),
            'filtro' => $filtros,
            'profesores' => $esStaff ? Profesor::listByEscuela($schoolId) : [],
            'esStaff' => $esStaff,
            'filtroAction' => 'escuelas/' . $schoolId . '/areas',
        ]);
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