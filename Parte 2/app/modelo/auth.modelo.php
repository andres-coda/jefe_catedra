<?php
require_once('app/modelo/modelo.php');
class AuthModelo extends Modelo
{
  public function login($email)
  {
    $sentencia = $this->getPdo()->prepare("select * from usuario where email = ?");
    $sentencia->execute([$email]);
    $usuario = $sentencia->fetch(PDO::FETCH_OBJ);
    return $usuario;
  }

  public function registrarse($nombreUsuario, $email, $pass, $cargo)
  {

    try {

      $passHash = password_hash($pass, PASSWORD_DEFAULT);

      $sentencia = $this->getPdo()->prepare('
          INSERT INTO usuario (nombre, email, pass, cargo) VALUES (?, ?,?,?)
          RETURNING nombre
        ');
      $sentencia->execute([$nombreUsuario, $email, $passHash, $cargo]);

      $usuario = $sentencia->fetch(PDO::FETCH_OBJ);

      return $usuario;
    } catch (\Throwable $th) {
      return false;
    }
  }
}