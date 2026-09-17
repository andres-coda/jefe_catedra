<?php

declare(strict_types=1);

/**
 * Punto de entrada único (front controller).
 * Flujo: .htaccess → index.php (autoload + config + sesión) → Router::dispatch
 *   → middleware chain → Controller → Model → Vista .phtml → Response.
 */

// --- Autoloader PSR-4 ---------------------------------------------------------
// Mecanismo primario: vendor/autoload.php generado por composer (composer.json:
// "App\\" → "app/"). Si composer no está disponible en el entorno, se registra
// un autoloader PSR-4 mínimo equivalente para que la app siga arrancando.
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

// --- Configuración ------------------------------------------------------------
require __DIR__ . '/config/claves.php';

use App\Controllers\AreaController;
use App\Controllers\AuthController;
use App\Controllers\BuscadorController;
use App\Controllers\CursoController;
use App\Controllers\DriveLinkController;
use App\Controllers\EscuelaController;
use App\Controllers\EscuelaUsuarioController;
use App\Controllers\HomeController;
use App\Controllers\MateriaController;
use App\Controllers\PerfilController;
use App\Controllers\ProfesorController;
use App\Controllers\TareaController;
use App\Core\AuthMiddleware;
use App\Core\Http;
use App\Core\PermissionDeniedException;
use App\Core\PermissionMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\SchoolContextMiddleware;
use App\Core\Session;

global $configuracion;

Session::start();

$auth = new AuthController();
$perfil = new PerfilController();
$usuariosEscuela = new EscuelaUsuarioController();
$home = new HomeController();
$escuelas = new EscuelaController();
$cursos = new CursoController();
$areas = new AreaController();
$materias = new MateriaController();
$profesores = new ProfesorController();
$tareas = new TareaController();
$links = new DriveLinkController();
$buscador = new BuscadorController();

// Cadena de middlewares global (diseño D3): Session → Auth → SchoolContext.
$router = new Router([
    new AuthMiddleware(),
    new SchoolContextMiddleware('id'),
]);

// -----------------------------------------------------------------------------
// Registro de rutas (PR2 — Auth + RBAC):
//   Públicas:   GET/POST /login, /registro; POST /logout.
//   Protegidas: /perfil (requiere sesión iniciada de cualquier usuario
//               autenticado, con o sin rol de escuela), /escuelas/{id}/usuarios
//               (requiere Directivo/Admin de la escuela, rol >= directivo).
// Los guards por acción se aplican en el closure de cada ruta.
// -----------------------------------------------------------------------------

$router->get('/login', fn (Request $r): Response => $auth->showLogin($r));
$router->post('/login', fn (Request $r): Response => $auth->login($r));
$router->get('/registro', fn (Request $r): Response => $auth->showRegistro($r));
$router->post('/registro', fn (Request $r): Response => $auth->registro($r));
$router->post('/logout', fn (Request $r): Response => $auth->logout($r));

$router->get('/perfil', function (Request $r) use ($perfil): Response {
    PermissionMiddleware::assertAuthenticated('perfil.view', $r);
    return $perfil->show($r);
});
$router->post('/perfil', function (Request $r) use ($perfil): Response {
    PermissionMiddleware::assertAuthenticated('perfil.update', $r);
    return $perfil->update($r);
});

$router->get('/escuelas/{id}/usuarios', function (Request $r) use ($usuariosEscuela): Response {
    PermissionMiddleware::assert('directivo', 'escuela_usuarios.list', $r);
    return $usuariosEscuela->lista($r);
});
$router->post('/escuelas/{id}/usuarios', function (Request $r) use ($usuariosEscuela): Response {
    PermissionMiddleware::assert('directivo', 'escuela_usuarios.assign', $r);
    return $usuariosEscuela->asignar($r);
});

// -----------------------------------------------------------------------------
// Registro de rutas (PR3 — Navegación de escuelas/cursos/áreas/materias):
//   Públicas:  GET / (home), GET /escuelas, GET /escuelas/{id} y las vistas
//              cursos/áreas/materias + ficha de curso (catálogo abierto).
//   Con sesión: POST /vista-config (preferencia de vista) y POST
//              /escuelas/{id}/favorito (toggle de favorita).
// -----------------------------------------------------------------------------

$router->get('/', fn (Request $r): Response => $home->home($r));
$router->get('/escuelas', fn (Request $r): Response => $escuelas->index($r));
$router->get('/escuelas/{id}', fn (Request $r): Response => $escuelas->show($r));
$router->get('/escuelas/{id}/cursos', fn (Request $r): Response => $cursos->index($r));
$router->get('/escuelas/{id}/areas', fn (Request $r): Response => $areas->index($r));
$router->get('/escuelas/{id}/materias', fn (Request $r): Response => $materias->index($r));
$router->get('/escuelas/{id}/cursos/{courseId}', fn (Request $r): Response => $cursos->show($r));

$router->post('/vista-config', fn (Request $r): Response => $home->vistaConfig($r));
$router->post('/escuelas/{id}/favorito', fn (Request $r): Response => $escuelas->favorito($r));

// -----------------------------------------------------------------------------
// Alta de escuela (formularios-autocomplete, WU2): solo admin global
// (REQ-11). No hay {id} en el path → el guard de rol se resuelve en el
// controlador; "nueva" es una ruta literal y nunca colisiona con {id}
// (la plantilla {id} exige UUID v4). GET muestra el formulario; POST crea.
// -----------------------------------------------------------------------------

$router->get('/escuelas/nueva', function (Request $r) use ($escuelas): Response {
    PermissionMiddleware::assertAuthenticated('escuela.nueva', $r);
    return $escuelas->nueva($r);
});
$router->post('/escuelas', function (Request $r) use ($escuelas): Response {
    PermissionMiddleware::assertAuthenticated('escuela.create', $r);
    return $escuelas->crear($r);
});

// -----------------------------------------------------------------------------
// Alta de curso dictado (formularios-autocomplete, WU2b): admin o directivo de
// la escuela del path (REQ-21). "nuevo" es una ruta literal y nunca colisiona
// con {courseId} (la plantilla exige UUID v4). GET muestra el formulario;
// POST crea con validación + transacción única (D1, REQ-25..REQ-27).
// -----------------------------------------------------------------------------

$router->get('/escuelas/{id}/cursos/nuevo', function (Request $r) use ($cursos): Response {
    PermissionMiddleware::assert('directivo', 'curso_escuela.nuevo', $r);
    return $cursos->nuevo($r);
});
$router->post('/escuelas/{id}/cursos', function (Request $r) use ($cursos): Response {
    PermissionMiddleware::assert('directivo', 'curso_escuela.create', $r);
    return $cursos->crear($r);
});

// -----------------------------------------------------------------------------
// Autocompletar (formularios-autocomplete): endpoint JSON público que alimenta
// el componente de autocompletar. El parámetro {recurso} no termina en "id"
// (compila como [^/]+) y se valida contra el mapa estático del controlador;
// cualquier recurso desconocido → 404 (D3, REQ-31).
// -----------------------------------------------------------------------------

$router->get('/buscador/{recurso}', fn (Request $r): Response => $buscador->buscar($r));

// -----------------------------------------------------------------------------
// PR3 — Profesores por curso (Jefe/Directivo/Admin de la escuela):
//   GET/POST /escuelas/{id}/cursos/{courseId}/profesores
//   PUT/DELETE .../profesores/{profesorId}; POST .../profesores/{profesorId}
//   funciona como alias de PUT/DELETE (formularios HTML: campo _accion).
// -----------------------------------------------------------------------------

$router->get('/escuelas/{id}/cursos/{courseId}/profesores', function (Request $r) use ($profesores): Response {
    PermissionMiddleware::assert('jefe', 'profesor.list', $r);
    return $profesores->mostrar($r);
});
$router->post('/escuelas/{id}/cursos/{courseId}/profesores', function (Request $r) use ($profesores): Response {
    PermissionMiddleware::assert('jefe', 'profesor.create', $r);
    return $profesores->crear($r);
});
$router->put('/escuelas/{id}/cursos/{courseId}/profesores/{profesorId}', function (Request $r) use ($profesores): Response {
    PermissionMiddleware::assert('jefe', 'profesor.update', $r);
    return $profesores->actualizar($r);
});
$router->delete('/escuelas/{id}/cursos/{courseId}/profesores/{profesorId}', function (Request $r) use ($profesores): Response {
    PermissionMiddleware::assert('jefe', 'profesor.delete', $r);
    return $profesores->quitar($r);
});
$router->post('/escuelas/{id}/cursos/{courseId}/profesores/{profesorId}', function (Request $r) use ($profesores): Response {
    PermissionMiddleware::assert('jefe', 'profesor.update', $r);
    return $profesores->desdeFormulario($r);
});

// -----------------------------------------------------------------------------
// PR3 — Tareas por curso (Jefe/Directivo/Admin de la escuela):
//   POST /escuelas/{id}/cursos/{courseId}/tareas
//   PUT .../tareas/{tareaId}; POST .../tareas/{tareaId} (alias, _accion)
//   POST .../tareas/{tareaId}/realizado (marcar realizada/pendiente)
// -----------------------------------------------------------------------------

$router->post('/escuelas/{id}/cursos/{courseId}/tareas', function (Request $r) use ($tareas): Response {
    PermissionMiddleware::assert('jefe', 'tarea.create', $r);
    return $tareas->crear($r);
});
$router->put('/escuelas/{id}/cursos/{courseId}/tareas/{tareaId}', function (Request $r) use ($tareas): Response {
    PermissionMiddleware::assert('jefe', 'tarea.update', $r);
    return $tareas->actualizar($r);
});
$router->post('/escuelas/{id}/cursos/{courseId}/tareas/{tareaId}', function (Request $r) use ($tareas): Response {
    PermissionMiddleware::assert('jefe', 'tarea.update', $r);
    return $tareas->desdeFormulario($r);
});
$router->post('/escuelas/{id}/cursos/{courseId}/tareas/{tareaId}/realizado', function (Request $r) use ($tareas): Response {
    PermissionMiddleware::assert('jefe', 'tarea.realizado', $r);
    return $tareas->marcar($r);
});

// -----------------------------------------------------------------------------
// PR3 — Enlaces de Drive por curso (Jefe/Directivo/Admin de la escuela):
//   POST /escuelas/{id}/cursos/{courseId}/links
//   PUT/DELETE .../links/{linkId}; POST .../links/{linkId} (alias, _accion).
//   La lista se muestra públicamente al ver el curso.
// -----------------------------------------------------------------------------

$router->post('/escuelas/{id}/cursos/{courseId}/links', function (Request $r) use ($links): Response {
    PermissionMiddleware::assert('jefe', 'drive_link.create', $r);
    return $links->crear($r);
});
$router->put('/escuelas/{id}/cursos/{courseId}/links/{linkId}', function (Request $r) use ($links): Response {
    PermissionMiddleware::assert('jefe', 'drive_link.update', $r);
    return $links->actualizar($r);
});
$router->delete('/escuelas/{id}/cursos/{courseId}/links/{linkId}', function (Request $r) use ($links): Response {
    PermissionMiddleware::assert('jefe', 'drive_link.delete', $r);
    return $links->eliminar($r);
});
$router->post('/escuelas/{id}/cursos/{courseId}/links/{linkId}', function (Request $r) use ($links): Response {
    PermissionMiddleware::assert('jefe', 'drive_link.update', $r);
    return $links->desdeFormulario($r);
});

try {
    $response = $router->dispatch(Request::fromGlobals());
} catch (PermissionDeniedException $exception) {
    // Visitante intentando una acción protegida → pantalla de login.
    if ($exception->shouldRedirectToLogin()) {
        $response = Response::redirect(Http::url('login'));
    } else {
        $response = (new Response())->status(403)->body('Acceso denegado.');
    }
} catch (\Throwable $exception) {
    error_log('[multi-school-platform] ' . $exception->getMessage());
    $response = (new Response())->status(500)->body('Error interno del servidor.');
}

$response->send();