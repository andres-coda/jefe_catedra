<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Escuela;
use App\Models\EscuelaFavorita;

/**
 * Home (PR3): usuario autenticado ve sus escuelas favoritas paginadas;
 * visitante ve el catálogo de escuelas paginado. La vista preferida
 * (cursos | áreas) se guarda en sesión y se cambia desde POST /vista-config.
 */
final class HomeController
{
    private const PER_PAGE = 12;

    /** @var array<int, string> */
    private const VISTAS = ['cursos', 'areas'];

    public function home(Request $request): Response
    {
        $user = $request->getAttribute('user');
        $logueado = $user !== null;

        $page = max(1, (int) $request->query('page', 1));

        if ($logueado) {
            $total = EscuelaFavorita::countByUsuario((string) $user['id']);
            $paginas = max(1, (int) ceil($total / self::PER_PAGE));
            $page = min($page, $paginas);
            $escuelas = $total > 0
                ? EscuelaFavorita::listByUsuario((string) $user['id'], ($page - 1) * self::PER_PAGE, self::PER_PAGE)
                : [];
        } else {
            $total = Escuela::count();
            $paginas = max(1, (int) ceil($total / self::PER_PAGE));
            $page = min($page, $paginas);
            $escuelas = $total > 0
                ? Escuela::paginar(($page - 1) * self::PER_PAGE, self::PER_PAGE)
                : [];
        }

        return $this->page('home', [
            'title' => 'Inicio',
            'escuelas' => $escuelas,
            'page' => $page,
            'paginas' => $paginas,
            'baseUrl' => '',
            'logueado' => $logueado,
            'vista' => self::vistaActual(),
        ]);
    }

    public function vistaConfig(Request $request): Response
    {
        if ($request->getAttribute('user') === null) {
            return Response::redirect(Http::url('login'));
        }

        $vista = (string) $request->post('vista', 'cursos');
        if (!in_array($vista, self::VISTAS, true)) {
            $vista = 'cursos';
        }

        Session::set('view_config', $vista);
        Session::flash('ok', $vista === 'areas' ? 'Vista configurada: áreas.' : 'Vista configurada: cursos.');

        return Response::redirect(Http::url(self::retornoDe($request)));
    }

    /**
     * Vista preferida del usuario (default 'cursos'; valores inválidos caen al default).
     */
    public static function vistaActual(): string
    {
        $vista = Session::get('view_config');

        return is_string($vista) && in_array($vista, self::VISTAS, true) ? $vista : 'cursos';
    }

    /**
     * Retorno seguro para redirects: solo paths internos ("/algo"); cualquier
     * otro valor cae a la raíz.
     */
    private static function retornoDe(Request $request): string
    {
        $retorno = trim((string) $request->post('retorno', ''));
        if ($retorno === '' || $retorno[0] !== '/') {
            return '';
        }

        return ltrim($retorno, '/');
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