<?php
require_once 'app/controlador/auth.controller.php';
require_once 'app/controlador/usuario.controller.php';
require_once 'app/controlador/escuela.controller.php';

define('BASE_URL', '//' . $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . dirname($_SERVER['PHP_SELF']) . '/');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$action = $_REQUEST['action'] ?? 'login';

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
    $accion = $params[1] ?? 'nueva';
    switch ($accion) {
      case 'nueva':
        $controllerEscuela->crearEscuela();
        break;
      default:
        $controllerEscuela->crearEscuela();
        break;
    }
    break;
  default:
    $controllerLogin = new AuthController();
    $controllerLogin->inicioSesion();
    break;
}