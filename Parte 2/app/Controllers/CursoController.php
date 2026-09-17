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
use App\Models\Area;
use App\Models\Curso;
use App\Models\CursoEscuela;
use App\Models\CursoEscuelaDia;
use App\Models\CursoProfesor;
use App\Models\Dia;
use App\Models\DriveLink;
use App\Models\Escuela;
use App\Models\Materia;
use App\Models\Profesor;
use App\Models\Tarea;

/**
 * Cursos de una escuela (PR3): listado filtrable (día/hora/profesor), ficha
 * de curso con datos, horarios y (para staff) gestión de profesores, tareas
 * y enlaces de Drive; y alta de curso dictado (formularios-autocomplete WU2b)
 * vía GET /escuelas/{id}/cursos/nuevo + POST /escuelas/{id}/cursos.
 */
final class CursoController
{
    /** @var array<string, string> Claves de máquina/SQLSTATE → mensajes amigables. */
    private const ERRORES_AMIGABLES = [
        'curso_duplicado' => 'ya existe un curso con ese nombre',
        'materia_duplicada' => 'ya existe una materia con ese nombre',
        'area_duplicada' => 'ya existe un área con ese nombre',
        'curso_escuela_duplicada' => 'ese curso ya está dictado en esa escuela para ese año',
        'dia_duplicado' => 'un día solo admite un horario',
        '42501' => 'No tienes permisos para crear cursos en esta escuela.',
    ];

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
            'puedeCrearCurso' => $this->puedeCrearCurso($request),
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
     * GET /escuelas/{id}/cursos/nuevo (REQ-21): muestra el formulario de alta
     * de un curso dictado. Solo admin global o directivo de la escuela (el
     * guard de la ruta ya filtra; esta comprobación es defensa en profundidad
     * y mantiene el controlador testeable de forma aislada). Escuela
     * inexistente → 404.
     */
    public function nuevo(Request $request): Response
    {
        if (!$this->puedeCrearCurso($request)) {
            return $this->prohibido();
        }

        $escuela = $this->resolverEscuela($request);
        if ($escuela === null) {
            return $this->noEncontrado();
        }

        return $this->renderNuevo($escuela, [], [], []);
    }

    /**
     * POST /escuelas/{id}/cursos (REQ-21..REQ-27): valida el catálogo
     * (REQ-22) re-validando los UUID ocultos server-side (D7, REQ-26/S7) y las
     * filas de horario (D6, REQ-23/REQ-24) ANTES de tocar la base; si todo es
     * válido persiste en UNA transacción cooperativa (D1, REQ-25): área (si
     * falta) → materia con área (si falta) → curso (si falta) → curso_escuela
     * → curso_escuela_dia por fila. Ante un error re-renderiza con los valores
     * enviados (REQ-27); al éxito hace PRG con flash.
     */
    public function crear(Request $request): Response
    {
        if (!$this->puedeCrearCurso($request)) {
            return $this->prohibido();
        }

        $escuela = $this->resolverEscuela($request);
        if ($escuela === null) {
            return $this->noEncontrado();
        }

        $schoolId = (string) $escuela['id'];
        $datos = $this->leerDatos($request);
        $filas = $this->leerFilas($request);

        $errores = $this->validarDatos($datos);
        $errores = array_merge($errores, $this->validarFilas($filas, $this->diasValidos()));

        if ($errores !== []) {
            return $this->renderNuevo($escuela, $datos, $errores, $filas);
        }

        try {
            $cursoEscuela = Database::transaction(function () use ($schoolId, $datos, $filas): array {
                $materiaId = $datos['id_materia'];

                if ($materiaId === '') {
                    // Materia nueva: exige área (Materia::create con id_area).
                    $areaId = $datos['id_area'] !== ''
                        ? $datos['id_area']
                        : (string) Area::create($datos['area_nombre'])['id'];
                    $materiaId = (string) Materia::create($datos['materia_nombre'], $areaId)['id'];
                }

                $cursoId = $datos['id_curso'];
                if ($cursoId === '') {
                    $cursoId = (string) Curso::create($datos['curso_nombre'])['id'];
                }

                $fila = CursoEscuela::create(
                    $schoolId,
                    $cursoId,
                    $materiaId,
                    self::cargaHorariaDe($datos),
                    self::anioDe($datos)
                );

                foreach ($filas as $horario) {
                    CursoEscuelaDia::create(
                        (string) $fila['id'],
                        $horario['dia'],
                        $horario['entrada'],
                        $horario['salida']
                    );
                }

                return $fila;
            });
        } catch (\RuntimeException $exception) {
            $mensaje = self::mensajeAmigable($exception->getMessage());
            if ($mensaje === null) {
                throw $exception;
            }

            return $this->renderNuevo($escuela, $datos, ['general' => $mensaje], $filas);
        } catch (\PDOException $exception) {
            $mensaje = self::mensajeAmigable((string) $exception->getCode());
            if ($mensaje === null) {
                throw $exception;
            }

            return $this->renderNuevo($escuela, $datos, ['general' => $mensaje], $filas);
        }

        Session::flash('ok', 'Curso creado correctamente.');

        return Response::redirect(Http::url('escuelas/' . $schoolId . '/cursos/' . $cursoEscuela['id']));
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
     * Lee y recorta los campos del formulario (REQ-22). Los ids ocultos se
     * normalizan: un valor que no sea UUID v4 se trata como ausente (D7) y el
     * texto libre decide.
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
            'id_area' => $this->leerUuid($request, 'id_area'),
            'area_nombre' => $leer('area_nombre'),
            'id_materia' => $this->leerUuid($request, 'id_materia'),
            'materia_nombre' => $leer('materia_nombre'),
            'id_curso' => $this->leerUuid($request, 'id_curso'),
            'curso_nombre' => $leer('curso_nombre'),
            'carga_horaria' => $leer('carga_horaria'),
            'anio' => $leer('anio'),
        ];
    }

    private function leerUuid(Request $request, string $campo): string
    {
        $valor = $request->post($campo, '');
        $valor = is_string($valor) ? trim($valor) : '';

        return $valor !== '' && Router::isUuid($valor) ? $valor : '';
    }

    /**
     * Lee las filas de horario (D6): arrays paralelos dia[]/hora_entrada[]/
     * hora_salida[]. Las filas totalmente vacías se descartan (WU3 puede dejar
     * una fila de más al agregar/quitar filas con JS).
     *
     * @return array<int, array{dia: string, entrada: string, salida: string}>
     */
    private function leerFilas(Request $request): array
    {
        $dias = $request->post('dia', []);
        $entradas = $request->post('hora_entrada', []);
        $salidas = $request->post('hora_salida', []);
        $dias = is_array($dias) ? $dias : [];
        $entradas = is_array($entradas) ? $entradas : [];
        $salidas = is_array($salidas) ? $salidas : [];

        $filas = [];
        foreach ($dias as $indice => $dia) {
            $fila = [
                'dia' => trim((string) $dia),
                'entrada' => trim((string) ($entradas[$indice] ?? '')),
                'salida' => trim((string) ($salidas[$indice] ?? '')),
            ];

            if ($fila['dia'] === '' && $fila['entrada'] === '' && $fila['salida'] === '') {
                continue;
            }

            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * Mapa id_dia => nombre para validar el select de días (REQ-23) contra el
     * catálogo existente Dia::list() (diseño D6).
     *
     * @return array<string, string>
     */
    private function diasValidos(): array
    {
        $mapa = [];
        foreach (Dia::list() as $dia) {
            $mapa[(string) $dia['id']] = (string) $dia['nombre'];
        }

        return $mapa;
    }

    /**
     * Validación del catálogo (REQ-22/REQ-23) y re-validación server-side de
     * los UUID ocultos (D7, REQ-26/S7): formato + existencia + alcance
     * materia↔área. Corre ANTES de la transacción; los límites replican el
     * schema.sql (curso/materia varchar(20), area varchar(80)).
     *
     * @param array<string, string> $datos
     *
     * @return array<string, string>
     */
    private function validarDatos(array $datos): array
    {
        $errores = [];

        // Área: opcional (REQ-22). Solo decide si viene id o nombre.
        if ($datos['id_area'] !== '') {
            if (Area::findById($datos['id_area']) === null) {
                $errores['area'] = 'El área seleccionada no existe.';
            }
        } elseif (mb_strlen($datos['area_nombre']) > 80) {
            $errores['area'] = 'El área no puede superar los 80 caracteres.';
        }

        // Materia: obligatoria; con área elegida, debe pertenecer a ella (D7).
        if ($datos['id_materia'] !== '') {
            $materia = Materia::findById($datos['id_materia']);
            if ($materia === null) {
                $errores['materia'] = 'La materia seleccionada no existe.';
            } elseif (!self::materiaPerteneceArea($materia, $datos['id_area'])) {
                $errores['materia'] = 'La materia seleccionada no pertenece al área elegida.';
            }
        } elseif ($datos['materia_nombre'] === '') {
            $errores['materia'] = 'La materia es obligatoria.';
        } elseif (mb_strlen($datos['materia_nombre']) > 20) {
            $errores['materia'] = 'La materia no puede superar los 20 caracteres.';
        } elseif ($datos['id_area'] === '' && $datos['area_nombre'] === '') {
            // Materia nueva y materia.id_area es NOT NULL (REQ-25).
            $errores['materia'] = 'El área es obligatoria para crear una materia.';
        }

        // Curso: obligatorio.
        if ($datos['id_curso'] !== '') {
            if (Curso::findById($datos['id_curso']) === null) {
                $errores['curso'] = 'El curso seleccionado no existe.';
            }
        } elseif ($datos['curso_nombre'] === '') {
            $errores['curso'] = 'El curso es obligatorio.';
        } elseif (mb_strlen($datos['curso_nombre']) > 20) {
            $errores['curso'] = 'El curso no puede superar los 20 caracteres.';
        }

        if (!self::esCargaHorariaValida($datos['carga_horaria'])) {
            $errores['carga_horaria'] = 'La carga horaria debe ser un número entre 0,01 y 99,99.';
        }

        if ($datos['anio'] !== '' && preg_match('/^\d{4}$/', $datos['anio']) !== 1) {
            $errores['anio'] = 'El año debe tener 4 dígitos.';
        }

        return $errores;
    }

    /**
     * Valida las filas de horario (REQ-23/REQ-24): al menos una fila, día del
     * catálogo, HH:MM en ambas horas, entrada ≤ salida (comparación de strings
     * zero-padded, D6) y sin días repetidos (PK (id_curso_escuela, id_dia)).
     *
     * @param array<int, array{dia: string, entrada: string, salida: string}> $filas
     * @param array<string, string> $diasValidos
     *
     * @return array<string, string>
     */
    private function validarFilas(array $filas, array $diasValidos): array
    {
        if ($filas === []) {
            return ['horarios' => 'Debe cargar al menos un día y horario.'];
        }

        if (self::diaDuplicado($filas)) {
            return ['horarios' => 'un día solo admite un horario'];
        }

        foreach ($filas as $fila) {
            if (!isset($diasValidos[$fila['dia']])) {
                return ['horarios' => 'El día seleccionado no es válido.'];
            }

            if (!self::esHoraValida($fila['entrada']) || !self::esHoraValida($fila['salida'])) {
                return ['horarios' => 'Las horas deben tener el formato HH:MM.'];
            }

            if ($fila['entrada'] > $fila['salida']) {
                return ['horarios' => 'La hora de entrada no puede ser posterior a la de salida.'];
            }
        }

        return [];
    }

    /**
     * Hora válida en formato HH:MM de 24 horas (validador puro, testeable
     * sin base; cero-padded para poder comparar strings, D6).
     */
    public static function esHoraValida(string $hora): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) === 1;
    }

    /**
     * carga_horaria nullable (REQ-23): vacío es válido (null); si viene, el
     * numeric(4,2) del schema exige 0,01–99,99 (validador puro).
     */
    public static function esCargaHorariaValida(string $valor): bool
    {
        if ($valor === '') {
            return true;
        }

        if (preg_match('/^\d{1,2}(\.\d{1,2})?$/', $valor) !== 1) {
            return false;
        }

        $numero = (float) $valor;

        return $numero >= 0.01 && $numero <= 99.99;
    }

    /**
     * true si algún día se repite entre las filas (REQ-24; validador puro).
     *
     * @param array<int, array{dia: string, entrada: string, salida: string}> $filas
     */
    public static function diaDuplicado(array $filas): bool
    {
        $vistos = [];
        foreach ($filas as $fila) {
            $dia = $fila['dia'] ?? '';
            if ($dia === '') {
                continue;
            }
            if (isset($vistos[$dia])) {
                return true;
            }
            $vistos[$dia] = true;
        }

        return false;
    }

    /**
     * Alcance materia↔área (D7): sin área elegida no hay restricción; con
     * área, la materia debe pertenecer a ella (validador puro, testeable).
     *
     * @param array<string, mixed>|null $materia
     */
    public static function materiaPerteneceArea(?array $materia, string $areaId): bool
    {
        if ($areaId === '') {
            return true;
        }

        return $materia !== null && (string) ($materia['id_area'] ?? '') === $areaId;
    }

    /**
     * @param array<string, mixed> $escuela
     * @param array<string, string> $datos
     * @param array<string, string> $errores
     * @param array<int, array{dia: string, entrada: string, salida: string}> $filas
     */
    private function renderNuevo(array $escuela, array $datos, array $errores, array $filas): Response
    {
        return $this->page('curso.nuevo', [
            'title' => 'Nuevo curso',
            'logueado' => true,
            'escuela' => $escuela,
            'datos' => $datos,
            'errores' => $errores,
            'filas' => $filas,
            'dias' => Dia::list(),
        ]);
    }

    /**
     * Escuela del path, o null si no existe (→ 404).
     *
     * @return array<string, mixed>|null
     */
    private function resolverEscuela(Request $request): ?array
    {
        $schoolId = (string) $request->param('id', '');

        return $schoolId !== '' ? Escuela::findById($schoolId) : null;
    }

    /**
     * true solo para admin global o directivo de la escuela (REQ-21): un jefe
     * no ve la entrada del formulario ni puede crear el curso dictado.
     */
    private function puedeCrearCurso(Request $request): bool
    {
        return in_array(
            (string) $request->getAttribute('school_role', ''),
            ['admin', 'directivo'],
            true
        );
    }

    private function prohibido(): Response
    {
        return (new Response())->status(403)->body('Acceso denegado.');
    }

    /**
     * Mapea claves de máquina (RuntimeException) y SQLSTATE (PDOException) a
     * mensajes amigables (REQ-26); null si no hay traducción.
     */
    private static function mensajeAmigable(string $clave): ?string
    {
        return self::ERRORES_AMIGABLES[$clave] ?? null;
    }

    private static function cargaHorariaDe(array $datos): ?float
    {
        return $datos['carga_horaria'] !== '' ? (float) $datos['carga_horaria'] : null;
    }

    private static function anioDe(array $datos): int
    {
        return $datos['anio'] !== '' ? (int) $datos['anio'] : (int) date('Y');
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