<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Controllers\BuscadorController;
use App\Controllers\CursoController;
use App\Controllers\EscuelaController;
use App\Core\AuthMiddleware;
use App\Core\Database;
use App\Core\Http;
use App\Core\PermissionDeniedException;
use App\Core\PermissionMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\SchoolContextMiddleware;
use App\Models\Area;
use App\Models\Escuela;
use App\Models\Materia;
use App\Models\Turno;
use PDO;
use Tests\Support\DbCase;

/**
 * Componentes front del autocompletar (formularios-autocomplete, WU3 — D5) y
 * su integración con el endpoint público GET /buscador/{recurso} (REQ-31..33).
 *
 * Sin navegador, el contrato observable es: (a) el endpoint devuelve el JSON
 * que el JS consume (cap 10 REQ-32/S3, público REQ-33/S4, scoping por área
 * REQ-22/S2); (b) las páginas renderizan el markup que el JS enlaza
 * (data-autocompletar, data-multiple, data-area-input, pares ocultos por chip,
 * filas de horario, script con defer); (c) el submit tras quitar un chip
 * viaja solo con los pares restantes (REQ-04/S4). El nombre de cada método es
 * el escenario en español, como en el resto de la suite.
 */
final class AutocompletarE2ETest extends DbCase
{
    public function testBuscadorPublicoDevuelveJsonOrdenadoYLimitadoA10(): void
    {
        $this->anonimo();

        $respuesta = $this->despacharBuscador('/buscador/escuela', ['q' => 'P4T Pag']);
        self::assertSame(200, $respuesta->statusCode(), 'Endpoint público sin sesión (REQ-33/S4).');

        $json = self::jsonDe($respuesta);
        self::assertCount(10, $json, '13 coincidencias → máx. 10 (REQ-32/S3).');
        self::assertSame('P4T Pag 01', $json[0]['nombre'], 'Ordenado por nombre (REQ-32).');
        self::assertSame('P4T Pag 10', $json[9]['nombre'], 'Las 10 primeras por orden alfabético.');

        foreach ($json as $fila) {
            self::assertSame(['id', 'nombre'], array_keys($fila), 'Solo se expone id y nombre (REQ-33).');
        }
    }

    public function testBuscadorDevuelveVacioSinCoincidencias(): void
    {
        $this->anonimo();

        $json = self::jsonDe($this->despacharBuscador('/buscador/curso', ['q' => 'zzz-no-existe-xyz']));
        self::assertSame([], $json, 'Sin coincidencias → [] (REQ-32).');
    }

    public function testBuscadorConRecursoDesconocidoDevuelve404(): void
    {
        $this->anonimo();

        $respuesta = $this->despacharBuscador('/buscador/desconocido', ['q' => 'x']);
        self::assertSame(404, $respuesta->statusCode(), 'Fuera del mapa → 404 (REQ-31/S2).');
        self::assertStringContainsString('Recurso desconocido', self::cuerpo($respuesta));
    }

    public function testBuscadorMateriaSeAcotaPorAreaElegida(): void
    {
        // Siembra elevada (patrón fixture): una segunda área con su materia.
        $pdo = self::conexionElevadaPropia();
        $pdo->prepare("INSERT INTO area (nombre) VALUES (?) ON CONFLICT (nombre) DO NOTHING")->execute(['P4T Area Scope']);
        $areaScopeId = (string) $pdo->query("SELECT id FROM area WHERE nombre = 'P4T Area Scope'")->fetchColumn();
        $pdo->prepare('INSERT INTO materia (nombre, id_area) VALUES (?, ?) ON CONFLICT (nombre) DO NOTHING')
            ->execute(['P4T Mat Scope', $areaScopeId]);
        $matScopeId = (string) $pdo->query("SELECT id FROM materia WHERE nombre = 'P4T Mat Scope'")->fetchColumn();

        $this->anonimo();

        // Con el área nueva elegida: solo la materia de ESA área (REQ-22/S2).
        $json = self::jsonDe($this->despacharBuscador('/buscador/materia', ['q' => 'P4T Mat Scope', 'area' => $areaScopeId]));
        self::assertSame([['id' => $matScopeId, 'nombre' => 'P4T Mat Scope']], $json, 'Scope por área elegida (REQ-22/S2).');

        // La misma materia NO aparece cuando el área elegida es otra.
        $json = self::jsonDe($this->despacharBuscador('/buscador/materia', ['q' => 'P4T Mat Scope', 'area' => self::$areaId]));
        self::assertSame([], $json, 'Una materia de otra área queda fuera del scope.');

        // Y la materia del fixture sigue en su área de origen.
        $json = self::jsonDe($this->despacharBuscador('/buscador/materia', ['q' => 'P4T Mat', 'area' => self::$areaId]));
        self::assertSame([['id' => self::$materiaId, 'nombre' => 'P4T Mat']], $json);
    }

    public function testPaginaNuevoCursoExponeContratoDeAutocompletarYAgregarFila(): void
    {
        $this->usarComo(self::$directivoId);

        $respuesta = $this->despacharCursos('GET', '/escuelas/' . self::$escuelaId . '/cursos/nuevo');
        self::assertSame(200, $respuesta->statusCode());
        $html = self::cuerpo($respuesta);

        // El script se carga con defer en cabecera.phtml (D5) y declara la
        // URL base del buscador para el fetch.
        self::assertStringContainsString('estilo/autocompletar.js', $html);
        self::assertStringContainsString('defer', $html);
        self::assertStringContainsString('data-buscador-url="/buscador"', $html);

        // Contrato single (REQ-22): binding + hidden UUID + scoping por área.
        self::assertStringContainsString('data-autocompletar="materia"', $html);
        self::assertStringContainsString('data-area-input="#id_area"', $html);
        self::assertStringContainsString('id="id_materia"', $html, 'Hidden id_{recurso} (D7).');

        // Filas de horario + botón para agregar filas (REQ-23, WU3).
        self::assertStringContainsString('class="fila-horario"', $html);
        self::assertStringContainsString('data-agregar-fila', $html);
    }

    public function testPaginaNuevaEscuelaExponeContratoDeChipsYElReRenderLosConserva(): void
    {
        $this->usarComo(self::$adminId);

        // "Mañana" es válido (chip) y "Temprano" no (vuelve al input editable):
        // el re-render debe conservar el contrato de pares ocultos (REQ-04/REQ-12).
        $post = $this->postEscuela(['nombre' => 'P4T Escuela Chips Contrato']);
        $post['turno_nombres'] = ['Mañana', 'Temprano'];

        $respuesta = $this->despacharEscuelas('POST', '/escuelas', $post);
        self::assertSame(200, $respuesta->statusCode());
        $html = self::cuerpo($respuesta);

        self::assertStringContainsString('data-autocompletar="turno"', $html);
        self::assertStringContainsString('data-multiple', $html, 'Modo chips (D5).');
        self::assertStringContainsString('data-chips="turno"', $html, 'Contenedor de chips del JS.');
        self::assertStringContainsString('<span class="chip">Mañana', $html, 'El turno válido queda como chip.');
        self::assertStringContainsString('name="turno_ids[]"', $html, 'Par oculto id (D5).');
        self::assertStringContainsString('name="turno_nombres[]"', $html, 'Par oculto nombre (D5).');
        self::assertStringContainsString('value="Temprano"', $html, 'El turno inválido vuelve al input editable (REQ-12).');
    }

    public function testQuitarUnChipEnviaSoloLosParesRestantes(): void
    {
        $this->usarComo(self::$adminId);

        // Estado post-remoción (REQ-04/S4): el chip "Mañana" ya no está en el
        // DOM; solo viaja el par oculto del chip restante, con su UUID.
        $tarde = Turno::obtenerOCrear('Tarde');
        $post = $this->postEscuela(['nombre' => 'P4T Escuela Chip Unico']);
        $post['turno_ids'] = [(string) $tarde['id']];
        $post['turno_nombres'] = ['Tarde'];

        $respuesta = $this->despacharEscuelas('POST', '/escuelas', $post);
        self::assertSame(302, $respuesta->statusCode(), 'Alta válida con el chip restante (REQ-04/S4).');

        $escuela = Escuela::searchByNombre('P4T Escuela Chip Unico');
        self::assertNotSame([], $escuela, 'La escuela se creó.');
        self::assertSame(['Tarde'], self::turnosDeEscuela((string) $escuela[0]['id']), 'Solo "Tarde" se envió y persistió.');
        self::assertSame(1, self::conteoTurnos((string) $escuela[0]['id']), 'Una sola fila pivote (dedup del par restante).');
    }

    /**
     * Despacha GET /buscador/{recurso} como index.php lo registra: público,
     * sin middlewares (REQ-33). El recurso no termina en "id" → [^/]+.
     *
     * @param array<string, string> $query
     */
    private function despacharBuscador(string $path, array $query = []): Response
    {
        $router = (new Router())
            ->get('/buscador/{recurso}', fn (Request $request): Response => (new BuscadorController())->buscar($request));

        return $router->dispatch(new Request('GET', $path, $query));
    }

    /**
     * Despacha las rutas del formulario de curso (WU3 usa el GET nuevo) con la
     * cadena real de middlewares y el manejo de PermissionDenied del front.
     */
    private function despacharCursos(string $method, string $path): Response
    {
        $router = new Router([
            new AuthMiddleware(),
            new SchoolContextMiddleware('id'),
        ]);
        $cursos = new CursoController();

        $router->get('/escuelas/{id}/cursos/nuevo', function (Request $request) use ($cursos): Response {
            PermissionMiddleware::assert('directivo', 'curso_escuela.nuevo', $request);
            return $cursos->nuevo($request);
        });

        try {
            return $router->dispatch(new Request($method, $path));
        } catch (PermissionDeniedException $exception) {
            if ($exception->shouldRedirectToLogin()) {
                return Response::redirect(Http::url('login'));
            }

            return (new Response())->status(403)->body('Access denied.');
        }
    }

    /**
     * Despacha POST /escuelas (chips) con la cadena real de middlewares.
     *
     * @param array<string, mixed> $post
     */
    private function despacharEscuelas(string $method, string $path, array $post = []): Response
    {
        $router = new Router([
            new AuthMiddleware(),
            new SchoolContextMiddleware('id'),
        ]);
        $escuelas = new EscuelaController();

        $router->post('/escuelas', function (Request $request) use ($escuelas): Response {
            PermissionMiddleware::assertAuthenticated('escuela.create', $request);
            return $escuelas->crear($request);
        });

        try {
            return $router->dispatch(new Request($method, $path, [], $post));
        } catch (PermissionDeniedException $exception) {
            if ($exception->shouldRedirectToLogin()) {
                return Response::redirect(Http::url('login'));
            }

            return (new Response())->status(403)->body('Access denied.');
        }
    }

    /**
     * POST /escuelas válido (base); $cambios pisa campos a medida.
     *
     * @param array<string, mixed> $cambios
     *
     * @return array<string, mixed>
     */
    private function postEscuela(array $cambios = []): array
    {
        $base = [
            'nombre' => 'P4T Escuela Base WU3',
            'numero' => '1',
            'region' => '1',
            'sigla' => '',
            'anexo' => '',
            'sector' => '',
            'distrito' => 'P4T Distrito',
            'localidad' => 'La Plata',
            'direccion' => 'P4T Direccion 123',
            'codigo_postal' => '1900',
            'telefono' => '',
            'email' => '',
            'turno_ids' => [],
            'turno_nombres' => [],
            'orientacion_ids' => [],
            'orientacion_nombres' => [],
        ];

        return array_merge($base, $cambios);
    }

    /**
     * Conexión elevada propia (el fixture la mantiene private en DbCase):
     * siembra de catálogo con tag P4T; la limpieza la hace tearDownAfterClass.
     */
    private static function conexionElevadaPropia(): PDO
    {
        global $configuracion;

        $config = $configuracion;
        $pdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'],
                $config['puerto'],
                $config['basenombre']
            ),
            $config['usuario'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdo->exec("SELECT set_config('app.contexto_cargado', 'true', false)");
        $pdo->exec("SELECT set_config('app.rol', 'admin', false)");

        return $pdo;
    }

    /** @return array<int, string> Nombres de turno enlazados, ordenados. */
    private static function turnosDeEscuela(string $escuelaId): array
    {
        $statement = Database::getConnection()->prepare(
            'SELECT t.nombre FROM escuela_turno et'
            . ' JOIN turno t ON t.id = et.id_turno'
            . ' WHERE et.id_escuela = ? ORDER BY t.nombre'
        );
        $statement->execute([$escuelaId]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function conteoTurnos(string $escuelaId): int
    {
        $statement = Database::getConnection()->prepare(
            'SELECT count(*) FROM escuela_turno WHERE id_escuela = ?'
        );
        $statement->execute([$escuelaId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<int, mixed> */
    private static function jsonDe(Response $response): array
    {
        return (array) json_decode(self::cuerpo($response), true);
    }

    private static function cuerpo(Response $response): string
    {
        ob_start();
        $response->send();

        return (string) ob_get_clean();
    }
}