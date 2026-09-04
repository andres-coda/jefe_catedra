<?php

class UsuarioView
{

  public function mostrarRegistro()
  {
    require './app/templates/usuario.phtml';
  }

  public function mostrarLogin()
  {
    $formulario = (object) [
    'accion' => 'login',
    'borrar' => 'Limpiar',
    'aceptar' => 'Ingresar',

    'datos' => [
        (object) [
            'id' => 'email',
            'label' => 'Email',
            'tipo' => 'email',
            'requerido' => true,
            'valor' => '',
            'error' => ''
        ],

        (object) [
            'id' => 'password',
            'label' => 'Contraseña',
            'tipo' => 'password',
            'requerido' => true,
            'valor' => '',
            'error' => ''
        ]
    ]
];
    require './app/templates/formulario.phtml';
  }
}
