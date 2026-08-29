<?php
require_once('app/modelos/Modelo.php');

class UsuarioModelo extends Modelo
{

  public function obtenerUsuarios()
  {
    try {

      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM usuario ORDER BY nombre
        ');
      $sentencia->execute();
      $usuarios = $sentencia->fetchAll(PDO::FETCH_OBJ);

      return $usuarios;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerUsuarioPorId($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM usuario 
          WHERE id = ?
        ');
      $sentencia->execute([$id]);
      $usuario = $sentencia->fetch(PDO::FETCH_OBJ);
      return $usuario;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function insertarUsuario($nombreUsuario, $email, $pass, $cargo)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO usuario (nombre, email, pass, cargo) VALUES (?, ?,?,?)
          RETURNING *
        ');
      $sentencia->execute([$nombreUsuario, $email, $pass, $cargo]);

      $usuario = $sentencia->fetch(PDO::FETCH_OBJ);

      return $usuario;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function editarUsuario($id, $nombreUsuario, $cargo)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        UPDATE usuario SET nombre = ? , cargo = ?
        WHERE id = ?
        RETURNING *
      ');
      $sentencia->execute([$nombreUsuario, $cargo, $id]);
      $usuario = $sentencia->fetch(PDO::FETCH_OBJ);
      return $usuario;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function delete($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        DELETE FROM usuario WHERE id = ?
      ');
      $sentencia->execute([$id]);

      return true;
    } catch (\Throwable $th) {
      return false;
    }
  }
}