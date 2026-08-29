<?php
require_once('app/modelos/Modelo.php');

class ProfesorModelo extends Modelo
{

  public function obtenerProfesores()
  {
    try {

      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM profesor ORDER BY nombre
        ');
      $sentencia->execute();
      $profesores = $sentencia->fetchAll(PDO::FETCH_OBJ);

      return $profesores;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerProfesorPorId($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM vw_profesor_cursos 
          WHERE id_profe = ?
        ');
      $sentencia->execute([$id]);
      $profesor = $sentencia->fetch(PDO::FETCH_OBJ);
      return $profesor;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function insertarProfesor($nombreProfesor, $emailProfesor, $telefonoProfesor, $detalleProfesor)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO profesor (nombre, email, telefono, detalles) VALUES (?,?,?,?)
          RETURNING *
        ');
      $sentencia->execute([$nombreProfesor, $emailProfesor, $telefonoProfesor, $detalleProfesor]);

      $profesor = $sentencia->fetch(PDO::FETCH_OBJ);

      return $profesor;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function editarProfesor($id, $nombreProfesor, $emailProfesor, $telefonoProfesor, $detalleProfesor)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        UPDATE profesor 
        SET nombre = ?, email = ?, telefono = ?, detalles = ?
        WHERE id = ?
        RETURNING *
      ');
      $sentencia->execute([$nombreProfesor, $emailProfesor, $telefonoProfesor, $detalleProfesor, $id]);
      $profesor = $sentencia->fetch(PDO::FETCH_OBJ);
      return $profesor;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function delete($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        DELETE FROM profesor WHERE id = ?
      ');
      $sentencia->execute([$id]);

      return true;
    } catch (\Throwable $th) {
      return false;
    }
  }
}