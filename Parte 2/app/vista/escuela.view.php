<?php
class EscuelaView
{
  public function crearEscuela($err = null)
  {
    $formulario = (object) [
      'accion' => 'crearEscuela',
      'borrar' => 'Limpiar',
      'aceptar' => 'Crear Escuela',

      'datos' => [
        (object) [
          'id' => 'nombre',
          'label' => 'Nombre de la nueva escuela',
          'tipo' => '',
          'requerido' => true,
          'valor' => '',
          'error' => $err
        ]
      ]
    ];

    require_once("app/template/cabecera.phtml");
    require_once("app/template/heder.phtml");
    require_once("app/template/formulario.phtml");
    require_once("app/template/footer.phtml");
  }
}