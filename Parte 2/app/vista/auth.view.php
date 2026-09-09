<?php


require_once('app/vista/view.php');
class AuthView extends View
{
  public function mostrarLogin($datos = null, $err = null)
  {
    $formulario = (object) [
      'titulo' => 'Iniciar sesión',
      'accion' => 'enviarLogin',
      'borrar' => 'Limpiar',
      'aceptar' => 'Ingresar',

      'datos' => [
        (object) [
          'id' => 'email',
          'label' => 'Email',
          'tipo' => 'email',
          'requerido' => true,
          'valor' => $datos->email ?? '',
          'error' => $err->email ?? null
        ],

        (object) [
          'id' => 'password',
          'label' => 'Contraseña',
          'tipo' => 'password',
          'requerido' => true,
          'valor' => $datos->password ?? '',
          'error' => $err->password ?? null
        ]
      ]
    ];

    $this->maquetaFormulario($formulario);
  }

  public function mostrarRegistro($datos = null, $err = null)
  {
    $formulario = (object) [
      'titulo' => 'Registrarse',
      'accion' => 'enviarRegistro',
      'borrar' => 'Limpiar',
      'aceptar' => 'Registrarse',

      'datos' => [
        (object) [
          'id' => 'nombre',
          'label' => 'Nombre de usuario',
          'tipo' => 'text',
          'requerido' => true,
          'valor' => $datos->nombre ?? '',
          'error' => $err->nombre ?? null
        ],

        (object) [
          'id' => 'email',
          'label' => 'Email',
          'tipo' => 'email',
          'requerido' => true,
          'valor' => $datos->email ??  '',
          'error' => $err->email ?? null
        ],

        (object) [
          'id' => 'password',
          'label' => 'Contraseña',
          'tipo' => 'password',
          'requerido' => true,
          'valor' => $datos->password ??  '',
          'error' => $err->password ?? null
        ],

        (object) [
          'id' => 'cargo',
          'label' => 'Cargo',
          'tipo' => 'text',
          'requerido' => false,
          'valor' => $datos->cargo ??  '',
          'error' => $err->cargo ?? null
        ],
      ]
    ];

    $this->maquetaFormulario($formulario);
  }
}