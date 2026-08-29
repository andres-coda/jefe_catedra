<?php
require_once('app/modelos/Modelo.php');

class EscuelaModelo extends Modelo
{

  public function obtenerEscuela()
  {
    try {

      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM escuela ORDER BY nombre
        ');
      $sentencia->execute();
      $escuelas = $sentencia->fetchAll(PDO::FETCH_OBJ);

      return $escuelas;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerEscuelaPorId($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          SELECT ce.*, e.nombre AS escuela, c.nombre AS curso, m.nombre AS materia, a.nombre AS area 
          FROM curso_escuela ce
          JOIN escuela e ON e.id = ce.id_escuela
          JOIN curso c ON c.id = ce.id_curso
          JOIN materia m ON m.id = ce.id_materia
          JOIN area a ON a.id = m.id_area
          WHERE ce.id_escuela = ?
        ');
      $sentencia->execute([$id]);
      $escuela = $sentencia->fetch(PDO::FETCH_OBJ);
      return $escuela;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function insertarEscuela($nombreEscuela)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO escuela (nombre) VALUES (?)     
          RETURNING *
        ');
      $sentencia->execute([$nombreEscuela]);

      $escuela = $sentencia->fetch(PDO::FETCH_OBJ);

      return $escuela;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function editarEscuela($id, $nombreEscuela)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        UPDATE escuela SET nombre = ? 
        WHERE id = ?        
        RETURNING *
      ');
      $sentencia->execute([$nombreEscuela, $id]);
      $escuela = $sentencia->fetch(PDO::FETCH_OBJ);
      return $escuela;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function delete($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        DELETE FROM escuela WHERE id = ?
      ');
      $sentencia->execute([$id]);

      return true;
    } catch (\Throwable $th) {
      return false;
    }
  }
}