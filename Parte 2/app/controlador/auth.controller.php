<?php
require_once("app/controlador/controller.php");
require_once("app/modelo/auth.modelo.php");
require_once("app/vista/auth.view.php");
class AuthController extends Controller
{
  public function __construct()
  {
    $this->model = new AuthModelo();
    $this->view = new AuthView();
  }

  public function inicioSesion()
  {
    $this->view->mostrarLogin();
  }

  public function registrarse()
  {
    $this->view->mostrarRegistro();
  }

  public function enviarLogin()
  {
    $datos = (object) [
      'email' => trim($_POST['email'] ?? ''),
      'password' => trim($_POST['password'] ?? '')
    ];

    $errores = (object) [];

    if(empty($datos->email)){
      $errores->email = "Requiere un email valido";      
    }

    if (empty($datos->pass)) {
      $errores->password = "Requiere una contraseña para iniciar sesión";
    }

    if (!empty((array) $errores)) {
      $this->view->mostrarLogin($datos, $errores);
      return;
    }

    $user = $this->model->login($datos->email);

    if ($user && password_verify($datos->password, $user->password)) {
      $_SESSION['IS_LOGGED'] = true;
      $_SESSION['ID_USER'] = $user->id;
      $_SESSION['USER'] = $user->nombre;

      header("Location: " . BASE_URL . "escuela");
    } else {
      $this->view->mostrarLogin("Usuario o contraseña incorrecta");
    }
  }

  public function enviarRegistro()
  {
    $datos = (object) [

      'nombre' =>  trim($_POST['nombre'] ?? ''),
      'email' =>  trim($_POST['email'] ?? ''),
      'pass' =>  trim($_POST['password'] ?? ''),
      'cargo' =>  trim($_POST['cargo'] ?? '')
    ];

    $errores = (object) [];

    if (empty($datos->nombre)) {
      $errores->nombre = "Requiere nombre de usuario";
    }

    if (empty($datos->email)) {
      $errores->email = "Requiere un email";
    }

    if (empty($datos->pass) || strlen($datos->pass) < 8) {
      $errores->password = "Requiere una contraseña válida de 8 caracteres como mínimo";
    }

    if (!empty((array) $errores)) {
      $this->view->mostrarRegistro($datos, $errores);
      return;
    }

    $user = $this->model->registrarse($datos->nombre, $datos->email, $datos->pass, $datos->cargo);

    if (!empty($user)) {
      $this->enviarLogin();
    }
  }

}