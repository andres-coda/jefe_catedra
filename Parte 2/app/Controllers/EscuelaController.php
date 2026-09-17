<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Http;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Models\CursoEscuela;
use App\Models\Dia;
use App\Models\Escuela;
use App\Models\EscuelaFavorita;
use App\Models\Orientacion;
use App\Models\Profesor;
use App\Models\Turno;
use App\Models\Usuario;

/**
 * Escuela (PR3): catálogo público paginado, ficha de escuela con vistas
 * cursos/áreas filtrables, toggle de favorita para usuarios registrados y
 * alta de escuela (admin global) vía GET /escuelas/nueva + POST /escuelas.
 */
final class EscuelaController
{
    private const PER_PAGE = 12;

    /** @var array<string, string> Claves de máquina/SQLSTATE → mensajes amigables. */
    private const ERRORES_AMIGABLES = [
        'escuela_duplicada' => 'ya existe una escuela con ese nombre',
        'turno_invalido' => 'el turno debe ser Mañana, Tarde o Noche',
        '23514' => 'el turno debe ser Mañana, Tarde o Noche',
        '42501' => 'No tienes permisos para crear escuelas.',
    ];

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
            'puedeCrear' => $this->esAdminGlobal($request),
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
            'puedeCrearCurso' => $this->puedeCrearCurso($request),
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

    public function nueva(Request $request): Response
    {
        if (!$this->esAdminGlobal($request)) {
            return $this->prohibido();
        }

        return $this->renderNueva([], [], [], []);
    }

    /**
     * Procesa POST /escuelas (REQ-11..REQ-17): valida los campos y los pares
     * de chips (turnos/orientaciones), y si todo es válido persiste en UNA
     * transacción cooperativa (D1): escuela → turno.obtenerOCrear →
     * escuela_turno → orientacion.obtenerOCrear → escuela_orientacion
     * (REQ-14). Ante cualquier fallo re-renderiza el formulario con los
     * valores enviados (REQ-12); al éxito hace PRG con flash (REQ-17).
     */
    public function crear(Request $request): Response
    {
        if (!$this->esAdminGlobal($request)) {
            return $this->prohibido();
        }

        $datos = $this->leerDatos($request);
        $turnos = $this->paresChips($request, 'turno');
        $orientaciones = $this->paresChips($request, 'orientacion');

        $errores = $this->validarDatos($datos);
        $errores = array_merge($errores, $this->validarTurnos($turnos));
        $errores = array_merge($errores, $this->validarOrientaciones($orientaciones));

        if ($errores !== []) {
            return $this->reRender($datos, $errores, $turnos, $orientaciones);
        }

        try {
            $escuela = Database::transaction(function () use ($datos, $turnos, $orientaciones): array {
                $fila = Escuela::create(self::datosParaCreate($datos));

                foreach ($turnos as $turno) {
                    $turnoId = $turno['id'] !== ''
                        ? $turno['id']
                        : (string) Turno::obtenerOCrear($turno['nombre'])['id'];
                    Escuela::agregarTurno((string) $fila['id'], $turnoId);
                }

                foreach ($orientaciones as $orientacion) {
                    $orientacionId = $orientacion['id'] !== ''
                        ? $orientacion['id']
                        : (string) Orientacion::obtenerOCrear($orientacion['nombre'])['id'];
                    Escuela::agregarOrientacion((string) $fila['id'], $orientacionId);
                }

                return $fila;
            });
        } catch (\RuntimeException $exception) {
            $mensaje = self::mensajeAmigable($exception->getMessage());
            if ($mensaje === null) {
                throw $exception;
            }

            return $this->reRender($datos, ['general' => $mensaje], $turnos, $orientaciones);
        } catch (\PDOException $exception) {
            $mensaje = self::mensajeAmigable((string) $exception->getCode());
            if ($mensaje === null) {
                throw $exception;
            }

            return $this->reRender($datos, ['general' => $mensaje], $turnos, $orientaciones);
        }

        Session::flash('ok', 'Escuela creada correctamente.');

        return Response::redirect(Http::url('escuelas/' . $escuela['id']));
    }

    /**
     * Re-render del formulario tras un error (REQ-12): conserva los valores
     * enviados. Los pares re-submitables quedan como chips; el primer par
     * inválido va al input editable (un solo input por recurso); los demás
     * pares inválidos no se redibujan.
     *
     * @param array<string, string> $datos
     * @param array<string, string> $errores
     * @param array<int, array{id: string, nombre: string}> $turnos
     * @param array<int, array{id: string, nombre: string}> $orientaciones
     */
    private function reRender(array $datos, array $errores, array $turnos, array $orientaciones): Response
    {
        $esTurnoValido = static fn (string $nombre): bool => in_array($nombre, Turno::NOMBRES_VALIDOS, true);
        $esOrientacionValida = static fn (string $nombre): bool => strlen($nombre) <= 50;

        [$turnosChips, $turnoNuevoValor] = $this->prepararChipsReRender($turnos, $errores['turnos'] ?? null, $esTurnoValido);
        [$orientacionesChips, $orientacionNuevoValor] = $this->prepararChipsReRender($orientaciones, $errores['orientaciones'] ?? null, $esOrientacionValida);

        return $this->renderNueva(
            $datos,
            $errores,
            $turnosChips,
            $orientacionesChips,
            $turnoNuevoValor,
            $orientacionNuevoValor
        );
    }

    /**
     * @param array<int, array{id: string, nombre: string}> $pares
     * @param callable(string): bool $esNombreValido
     *
     * @return array{0: array<int, array{id: string, nombre: string}>, 1: string}
     */
    private function prepararChipsReRender(array $pares, ?string $error, callable $esNombreValido): array
    {
        if ($error === null) {
            return [$pares, ''];
        }

        $chips = [];
        $nuevoValor = '';
        foreach ($pares as $par) {
            if ($par['id'] !== '' || $esNombreValido($par['nombre'])) {
                $chips[] = $par;
            } elseif ($nuevoValor === '') {
                $nuevoValor = $par['nombre'];
            }
        }

        return [$chips, $nuevoValor];
    }

    /**
     * Lee y recorta los campos del formulario; los opcionales quedan en ''.
     *
     * @return array<string, string>
     */
    private function leerDatos(Request $request): array
    {
        $leer = static function (string $campo) use ($request): string {
            $valor = $request->post($campo, '');
            if (!is_string($valor)) {
                return '';
            }

            return trim($valor);
        };

        return [
            'nombre' => $leer('nombre'),
            'numero' => $leer('numero'),
            'region' => $leer('region'),
            'sigla' => $leer('sigla'),
            'anexo' => $leer('anexo'),
            'sector' => $leer('sector'),
            'distrito' => $leer('distrito'),
            'localidad' => $leer('localidad'),
            'direccion' => $leer('direccion'),
            'codigo_postal' => $leer('codigo_postal'),
            'telefono' => $leer('telefono'),
            'email' => $leer('email'),
        ];
    }

    /**
     * Validación de los campos de la escuela (REQ-12). Los límites replican
     * el schema.sql; devuelve errores por campo para el re-render.
     *
     * @param array<string, string> $datos
     *
     * @return array<string, string>
     */
    private function validarDatos(array $datos): array
    {
        $errores = [];

        if ($datos['nombre'] === '') {
            $errores['nombre'] = 'El nombre es obligatorio.';
        } elseif (strlen($datos['nombre']) > 50) {
            $errores['nombre'] = 'El nombre no puede superar los 50 caracteres.';
        }

        if ($datos['numero'] === '' || preg_match('/^\d+$/', $datos['numero']) !== 1) {
            $errores['numero'] = 'El número debe ser un entero mayor o igual a 0.';
        }

        if ($datos['region'] === '' || preg_match('/^\d+$/', $datos['region']) !== 1) {
            $errores['region'] = 'La región debe ser un entero mayor o igual a 0.';
        }

        if (strlen($datos['sigla']) > 5) {
            $errores['sigla'] = 'La sigla no puede superar los 5 caracteres.';
        }

        if (strlen($datos['anexo']) > 9) {
            $errores['anexo'] = 'El anexo no puede superar los 9 caracteres.';
        }

        if ($datos['sector'] !== '' && !in_array($datos['sector'], ['0', '1', '2', '3'], true)) {
            $errores['sector'] = 'El sector debe ser 0, 1, 2 o 3.';
        }

        if ($datos['distrito'] === '') {
            $errores['distrito'] = 'El distrito es obligatorio.';
        } elseif (strlen($datos['distrito']) > 50) {
            $errores['distrito'] = 'El distrito no puede superar los 50 caracteres.';
        }

        if ($datos['localidad'] === '') {
            $errores['localidad'] = 'La localidad es obligatoria.';
        } elseif (strlen($datos['localidad']) > 50) {
            $errores['localidad'] = 'La localidad no puede superar los 50 caracteres.';
        }

        if ($datos['direccion'] === '') {
            $errores['direccion'] = 'La dirección es obligatoria.';
        } elseif (strlen($datos['direccion']) > 100) {
            $errores['direccion'] = 'La dirección no puede superar los 100 caracteres.';
        }

        if ($datos['codigo_postal'] === '' || preg_match('/^\d{4,5}$/', $datos['codigo_postal']) !== 1) {
            $errores['codigo_postal'] = 'El código postal debe tener entre 4 y 5 dígitos.';
        }

        if (strlen($datos['telefono']) > 15) {
            $errores['telefono'] = 'El teléfono no puede superar los 15 caracteres.';
        }

        if ($datos['email'] !== '' && (!Usuario::validarEmail($datos['email']) || strlen($datos['email']) > 50)) {
            $errores['email'] = 'El email no es válido.';
        }

        return $errores;
    }

    /**
     * Valida los turnos enviados (REQ-15, D7) ANTES de la transacción:
     * un par con id requiere un UUID existente; un par sin id (texto libre,
     * REQ-05/S5) debe pertenecer a {Mañana, Tarde, Noche}.
     *
     * @param array<int, array{id: string, nombre: string}> $turnos
     *
     * @return array<string, string>
     */
    private function validarTurnos(array $turnos): array
    {
        foreach ($turnos as $turno) {
            if ($turno['id'] !== '') {
                if (Turno::findById($turno['id']) === null) {
                    return ['turnos' => 'El turno seleccionado no existe.'];
                }

                continue;
            }

            if (!in_array($turno['nombre'], Turno::NOMBRES_VALIDOS, true)) {
                return ['turnos' => 'el turno debe ser Mañana, Tarde o Noche'];
            }
        }

        return [];
    }

    /**
     * Valida las orientaciones enviadas (D7) ANTES de la transacción:
     * id existente o nombre libre de hasta 50 caracteres (REQ-15; el límite
     * replica el schema.sql — las orientaciones son nombres libres).
     *
     * @param array<int, array{id: string, nombre: string}> $orientaciones
     *
     * @return array<string, string>
     */
    private function validarOrientaciones(array $orientaciones): array
    {
        foreach ($orientaciones as $orientacion) {
            if ($orientacion['id'] !== '') {
                if (Orientacion::findById($orientacion['id']) === null) {
                    return ['orientaciones' => 'La orientación seleccionada no existe.'];
                }

                continue;
            }

            if (strlen($orientacion['nombre']) > 50) {
                return ['orientaciones' => 'La orientación no puede superar los 50 caracteres.'];
            }
        }

        return [];
    }

    /**
     * Empareja los pares ocultos {recurso}_ids[]/{recurso}_nombres[] que
     * producen los chips (D5): descarta nombres vacíos, normaliza ids no-UUID
     * a '' (D7) y deduplica por id o por nombre (case-insensitive). El input
     * visible agrega al final un par sin id (texto libre sin JS, REQ-05/S5).
     *
     * @return array<int, array{id: string, nombre: string}>
     */
    private function paresChips(Request $request, string $recurso): array
    {
        $ids = $request->post($recurso . '_ids', []);
        $nombres = $request->post($recurso . '_nombres', []);
        $ids = is_array($ids) ? $ids : [];
        $nombres = is_array($nombres) ? $nombres : [];

        $pares = [];
        $vistos = [];
        foreach ($nombres as $indice => $nombre) {
            $nombre = trim((string) $nombre);
            if ($nombre === '') {
                continue;
            }

            $id = isset($ids[$indice]) ? trim((string) $ids[$indice]) : '';
            if ($id !== '' && !Router::isUuid($id)) {
                $id = '';
            }

            $clave = $id !== '' ? 'id:' . $id : 'nombre:' . mb_strtolower($nombre);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $pares[] = ['id' => $id, 'nombre' => $nombre];
        }

        return $pares;
    }

    /**
     * Normaliza los valores para Escuela::create (REQ-12): sigla/sector con
     * default y opcionales vacíos como null.
     *
     * @param array<string, string> $datos
     *
     * @return array<string, int|string|null>
     */
    private static function datosParaCreate(array $datos): array
    {
        return [
            'nombre' => $datos['nombre'],
            'numero' => (int) $datos['numero'],
            'sigla' => $datos['sigla'] !== '' ? $datos['sigla'] : 'EES',
            'anexo' => $datos['anexo'] !== '' ? $datos['anexo'] : null,
            'sector' => $datos['sector'] !== '' ? $datos['sector'] : '0',
            'region' => (int) $datos['region'],
            'distrito' => $datos['distrito'],
            'localidad' => $datos['localidad'],
            'direccion' => $datos['direccion'],
            'codigo_postal' => $datos['codigo_postal'],
            'telefono' => $datos['telefono'] !== '' ? $datos['telefono'] : null,
            'email' => $datos['email'] !== '' ? $datos['email'] : null,
        ];
    }

    /**
     * @param array<string, string> $datos
     * @param array<string, string> $errores
     * @param array<int, array{id: string, nombre: string}> $turnos
     * @param array<int, array{id: string, nombre: string}> $orientaciones
     */
    private function renderNueva(
        array $datos,
        array $errores,
        array $turnos = [],
        array $orientaciones = [],
        string $turnoNuevoValor = '',
        string $orientacionNuevoValor = ''
    ): Response {
        return $this->page('escuela.nueva', [
            'title' => 'Nueva escuela',
            'logueado' => true,
            'datos' => $datos,
            'errores' => $errores,
            'turnos' => $turnos,
            'orientaciones' => $orientaciones,
            'turnoNuevoValor' => $turnoNuevoValor,
            'orientacionNuevoValor' => $orientacionNuevoValor,
        ]);
    }

    private function esAdminGlobal(Request $request): bool
    {
        $user = $request->getAttribute('user');

        return $user !== null && ($user['rol'] ?? '') === 'admin';
    }

    private function prohibido(): Response
    {
        return (new Response())->status(403)->body('Acceso denegado.');
    }

    /**
     * Mapea claves de máquina (RuntimeException) y SQLSTATE (PDOException) a
     * mensajes amigables (REQ-15/REQ-16); null si no hay traducción.
     */
    private static function mensajeAmigable(string $clave): ?string
    {
        return self::ERRORES_AMIGABLES[$clave] ?? null;
    }

    private function esStaff(Request $request): bool
    {
        return in_array(
            (string) $request->getAttribute('school_role', ''),
            ['jefe', 'directivo', 'admin'],
            true
        );
    }

    /**
     * true solo para admin global o directivo de la escuela (REQ-21): decide si
     * se muestra la entrada "Nuevo curso" en el catálogo (WU2b).
     */
    private function puedeCrearCurso(Request $request): bool
    {
        return in_array(
            (string) $request->getAttribute('school_role', ''),
            ['admin', 'directivo'],
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