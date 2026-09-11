<?php
class View {
  protected function maquetaFormulario($formulario){
    require_once("app/template/cabecera.phtml");
    require_once("app/template/heder.phtml");
    require_once("app/template/formulario.phtml");
    require_once("app/template/footer.phtml");
  }

  protected function mostrarElementos($datos, $card, $nuevoElemento, $titulo = null, $select = null){
    require_once("app/template/cabecera.phtml");
    require_once("app/template/heder.phtml");
    require_once('app/template/contenedorDeCards.phtml');
    require_once("app/template/footer.phtml");
  }

  public function mostrarElementosVacio($nuevoElemento = null){
    require_once("app/template/cabecera.phtml");
    require_once("app/template/heder.phtml");
    require_once('app/template/contenedorVacio.phtml');
    require_once("app/template/footer.phtml");
  }
}