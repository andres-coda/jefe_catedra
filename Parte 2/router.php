<?php
require_once 'app/controlador/auth.controller.php';
require_once 'app/controlador/usuario.controller.php';
require_once 'app/controlador/escuela.controller.php';
require_once 'app/controlador/curso.controller.php';
require_once 'app/controlador/materia.controller.php';

define('BASE_URL', '//' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . dirname($_SERVER['PHP_SELF']) . '/');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$action = $_REQUEST['action'] ?? 'escuela';

$params = explode('/', $action);

switch ($params[0]) {

  //public
  case 'login':
    $controllerLogin = new AuthController();
    $controllerLogin->inicioSesion();
    break;
  case 'enviarLogin':
    $controllerLogin = new AuthController();
    $controllerLogin->enviarLogin();
    break;
  case 'registro':
    $controllerLogin = new AuthController();
    $controllerLogin->registrarse();
    break;
  case 'enviarRegistro':
    $controllerLogin = new AuthController();
    $controllerLogin->enviarRegistro();
    break;

  case 'escuela':
    $controllerEscuela = new EscuelaController();
    $accionEscuela = $params[1] ?? '';
    switch ($accionEscuela) {
      case 'nueva':
        $controllerEscuela->crearEscuela();
        break;
      case 'crearEscuela':
        $controllerEscuela->enviarCrearEscuela();
        break;
      case 'curso':
        $controllerCurso = new CursoEscuelaController();
        $accionCurso = $params[2] ?? '';
        $accionIdEscuela = $params[3] ?? '';
        switch ($accionCurso) {
          case 'nuevo':
            $controllerCurso->crearCurso();
            break;
          case 'crearCurso':
            $controllerCurso->enviarCrearCurso($accionIdEscuela);
            break;
          default:
            $controllerCurso->mostrarCursos($accionCurso);
            break;
        }
        break;
        case 'materia':
        $controllerCurso = new MateriaController();
        $accionCurso = $params[2] ?? '';
        $accionIdEscuela = $params[3] ?? '';
        switch ($accionCurso) {
          case 'nueva':
            $controllerCurso->crearMateria();
            break;
          case 'crearMateria':
            $controllerCurso->enviarCrearMateria();
            break;
          default:
            $controllerCurso->mostrarMaterias($accionCurso);
            break;
        }
        break;
      default:
        $controllerEscuela->mostrarEscuelas();
        break;      
    }
    break;

  default:
    $controllerLogin = new AuthController();
    $controllerLogin->inicioSesion();
    break;
}