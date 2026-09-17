<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\CursoEscuela;
use App\Models\Dia;
use App\Models\Escuela;
use App\Models\Materia;
use App\Models\Profesor;

/**
 * Materias de una escuela (PR3): materias que se dictan, con sus cursos,
 * respetando los filtros (día/hora/profesor).
 */
final class MateriaController
{
    public function index(Request $request): Response
    {
        $schoolId = (string) $request->param('id', '');
        $escuela = $schoolId !== '' ? Escuela::findById($schoolId) : null;

        if ($escuela === null) {
            return $this->noEncontrado();
        }

        $filtros = CursoEscuela::filtrosDe($request);
        $cursos = CursoEscuela::listByEscuela($schoolId, $filtros);
        $esStaff = $this->esStaff($request);

        $porMateria = [];
        foreach ($cursos as $curso) {
            $porMateria[(string) $curso['id_materia']][] = $curso;
        }

        $materias = [];
        foreach (Materia::listByEscuela($schoolId) as $materia) {
            $materia['cursos'] = $porMateria[(string) $materia['id']] ?? [];
            if ($materia['cursos'] !== []) {
                $materias[] = $materia;
            }
        }

        return $this->page('materias', [
            'title' => 'Materias',
            'escuela' => $escuela,
            'materias' => $materias,
            'dias' => Dia::list(),
            'filtro' => $filtros,
            'profesores' => $esStaff ? Profesor::listByEscuela($schoolId) : [],
            'esStaff' => $esStaff,
            'filtroAction' => 'escuelas/' . $schoolId . '/materias',
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