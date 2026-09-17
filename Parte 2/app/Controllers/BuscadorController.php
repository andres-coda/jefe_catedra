<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Models\Area;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\Materia;
use App\Models\Orientacion;
use App\Models\Turno;

/**
 * Buscador (D3): endpoint JSON público GET /buscador/{recurso}?q=...
 * que alimenta el autocompletar. Mapa estático recurso → modelo; expone
 * solo {id, nombre} y acota a 10 resultados. Los SELECTs usados son
 * públicos (pc_*_select with using(true)), RLS-safe sin contexto.
 */
final class BuscadorController
{
    /** @var array<string, class-string> Mapa recurso de la URL → modelo. */
    private const RECURSOS = [
        'escuela' => Escuela::class,
        'curso' => Curso::class,
        'materia' => Materia::class,
        'area' => Area::class,
        'turno' => Turno::class,
        'orientacion' => Orientacion::class,
    ];

    public function buscar(Request $request): Response
    {
        $recurso = strtolower(trim((string) $request->param('recurso', '')));
        $q = trim((string) $request->query('q', ''));

        if (!isset(self::RECURSOS[$recurso])) {
            return (new Response())->json(['error' => 'Recurso desconocido.'], 404);
        }

        // q vacío o sin q → lista vacía (200), sin tocar la base (REQ-32).
        if ($q === '') {
            return (new Response())->json([]);
        }

        $modelo = self::RECURSOS[$recurso];

        // Materia soporta el scope por área (?area=uuid) para acotar
        // resultados cuando el usuario eligió un área (REQ-22/S2, D3).
        if ($modelo === Materia::class) {
            $area = trim((string) $request->query('area', ''));
            if ($area !== '' && Router::isUuid($area)) {
                return (new Response())->json($modelo::searchByNombre($q, $area));
            }
            return (new Response())->json($modelo::searchByNombre($q));
        }

        return (new Response())->json($modelo::searchByNombre($q));
    }
}