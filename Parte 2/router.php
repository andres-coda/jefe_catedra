<?php
require_once 'app/controlador/auth.controller.php';
require_once 'app/controlador/usuario.controller.php';
require_once 'app/controlador/escuela.controller.php';
require_once 'app/controlador/curso.controller.php';
require_once 'app/controlador/materia.controller.php';
require_once 'app/controlador/area.controller.php';

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
      case '':
        $controllerEscuela->mostrarEscuelas();
        break;
      default:

        if (
          preg_match(
            '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/',
            $accionEscuela
          )
        ) {
          $accionEscuelaSelec = $params[2] ?? '';
          switch ($accionEscuelaSelec) {

            // ----- Curso ---- 
            case 'curso':
              $controllerCurso = new CursoEscuelaController();
              $accionCurso = $params[3] ?? '';
              switch ($accionCurso) {
                case 'nuevo':
                  $controllerCurso->crearCurso($accionEscuela);
                  break;
                case 'crearCurso':
                  $controllerCurso->enviarCrearCurso($accionEscuela);
                  break;
                case '':
                  $controllerCurso->mostrarCursos($accionEscuela);
                  break;

                default:
                  $controllerCurso->mostrarCursos($accionEscuela);
                  break;
              }
              break;


            // ----- Materia ---- 


            case 'materia':
              $controllerMateria = new MateriaController();
              $accionMateria = $params[3] ?? '';
              switch ($accionMateria) {
                case 'nueva':
                  $controllerMateria->crearMateria();
                  break;
                case 'crearMateria':
                  $controllerMateria->enviarCrearMateria();
                  break;
                default:
                  $controllerMateria->mostrarMaterias($accionMateria);
                  break;
              }
              break;

            // ----- Area ---- 


            case 'area':
              $controllerArea = new AreaController();
              $accionArea = $params[3] ?? '';

              switch ($accionArea) {
                case 'nueva':
                  $controllerArea->crearArea();
                  break;
                case 'crearArea':
                  $controllerArea->enviarCrearArea();
                  break;
                case '':
                  $controllerArea->mostrarAreas($accionEscuela);
                  break;
                default:
                  $accionAreaSelect = $params[4] ?? '';
                  switch ($accionAreaSelect) {
                    case '':
                      $controllerArea->mostrarArea($accionArea);
                      break;
                    case 'nueva':
                      $controllerCurso = new CursoEscuelaController();
                      $controllerCurso->crearCurso($accionEscuela, $accionArea);
                      break;
                    case 'crearCurso':
                      $controllerCurso = new CursoEscuelaController();
                      $controllerCurso->enviarCrearCurso($accionEscuela);
                      break;
                  }
                  break;
              }
              break;
          }
        }
        break;


    }
    break;

  default:
    $controllerLogin = new AuthController();
    $controllerLogin->inicioSesion();
    break;
}