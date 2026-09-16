<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\CursoEscuela;
use App\Models\Dia;
use App\Models\Escuela;
use App\Models\EscuelaFavorita;
use App\Models\Profesor;

/**
 * Escuela (PR3): catálogo público paginado, ficha de escuela con vistas
 * cursos/áreas filtrables, y toggle de favorita para usuarios registrados.
 */
final class EscuelaController
{
    private const PER_PAGE = 12;

    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = Escuela::count();
        $paginas = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $paginas);
        $escuelas = $total > 0 ? Escuela::paginar(($page - 1) * self::PER_PAGE, self::PER_PAGE) : [];

        return $this->page('escuelas', [
            'title' => 'Escuelas',
            'escuelas' => $escuelas,
            'page' => $page,
            'paginas' => $paginas,
            'baseUrl' => 'escuelas',
            'logueado' => $request->getAttribute('user') !== null,
        ]);
    }

    public function show(Request $request): Response
    {
        $schoolId = (string) $request->param('id', '');
        $escuela = $schoolId !== '' ? Escuela::findById($schoolId) : null;

        if ($escuela === null) {
            return $this->noEncontrado();
        }

        $user = $request->getAttribute('user');
        $logueado = $user !== null;
        $filtros = CursoEscuela::filtrosDe($request);
        $esStaff = $this->esStaff($request);

        $shared = [
            'title' => (string) $escuela['nombre'],
            'escuela' => $escuela,
            'logueado' => $logueado,
            'esFavorita' => $logueado ? EscuelaFavorita::isFavorita($schoolId, (string) $user['id']) : false,
            'vista' => $logueado ? HomeController::vistaActual() : 'cursos',
            'dias' => Dia::list(),
            'filtro' => $filtros,
            'profesores' => $esStaff ? Profesor::listByEscuela($schoolId) : [],
            'esStaff' => $esStaff,
            'filtroAction' => 'escuelas/' . $schoolId,
        ];

        if ($shared['vista'] === 'areas') {
            return $this->page('escuela', $shared + [
                'agrupado' => CursoEscuela::agruparPorArea(CursoEscuela::listByEscuela($schoolId, $filtros)),
            ]);
        }

        return $this->page('escuela', $shared + [
            'cursos' => CursoEscuela::listByEscuela($schoolId, $filtros),
        ]);
    }

    public function favorito(Request $request): Response
    {
        $user = $request->getAttribute('user');
        if ($user === null) {
            return Response::redirect(Http::url('login'));
        }

        $schoolId = (string) $request->param('id', '');
        $escuela = $schoolId !== '' ? Escuela::findById($schoolId) : null;

        if ($escuela === null) {
            return $this->noEncontrado();
        }

        $esFavorita = EscuelaFavorita::toggle($schoolId, (string) $user['id']);
        Session::flash('ok', $esFavorita ? 'Escuela agregada a favoritas.' : 'Escuela quitada de favoritas.');

        $retorno = trim((string) $request->post('retorno', ''));
        if ($retorno === '' || $retorno[0] !== '/') {
            $retorno = 'escuelas/' . $schoolId;
        }

        return Response::redirect(Http::url(ltrim($retorno, '/')));
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