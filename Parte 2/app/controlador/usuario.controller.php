<?php
require_once('app/controlador/controller.php');
class UsuarioController extends Controller
{
  public function __construct()
  {
    $this->model = new UsuarioModelo();
    $this->view = new UsuarioView();
  }

  public function mostrarPerfil(){
    $this->view->mostrarLogin();
  }
}