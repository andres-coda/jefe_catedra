<?php
require_once('app/modelo/modelo.php');

class MateriaModelo extends Modelo
{

  public function obtenerMaterias()
  {
    try {

      $sentencia = $this->getPdo()->prepare('
          SELECT * FROM materia ORDER BY nombre
        ');
      $sentencia->execute();
      $materias = $sentencia->fetchAll(PDO::FETCH_OBJ);

      return $materias;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerMateriaPorNombre($nombre)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO materia (nombre) 
          VALUES (?)
          RETURNING *
        ');
      $sentencia->execute([$nombre]);

      $materia = $sentencia->fetch(PDO::FETCH_OBJ);

      return $materia;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerMateriaPorId($idMateria, $idEscuela)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
          SELECT ce.*, e.nombre AS escuela, c.nombre AS curso, m.nombre AS materia, a.nombre AS area 
          FROM curso_escuela ce
          JOIN escuela e ON e.id = ce.id_escuela
          JOIN curso c ON c.id = ce.id_curso
          JOIN materia m ON m.id = ce.id_materia
          JOIN area a ON a.id = m.id_area
          WHERE ce.id_materia = ? AND ce.id_escuela = ?
        ');
      $sentencia->execute([$idMateria, $idEscuela]);
      $materia = $sentencia->fetch(PDO::FETCH_OBJ);
      return $materia;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function obtenerMateriaPorIdEscuela($idEscuela)
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
      $sentencia->execute([$idEscuela]);
      $materias = $sentencia->fetchAll(PDO::FETCH_OBJ);
      return $materias;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function insertarMateria($nombreMateria, $idArea)
  {

    try {
      $sentencia = $this->getPdo()->prepare('
          INSERT INTO materia (nombre, id_area) VALUES (?, ?)
          RETURNING *
        ');
      $sentencia->execute([$nombreMateria, $idArea]);

      $materia = $sentencia->fetch(PDO::FETCH_OBJ);
      return $materia;

    } catch (\Throwable $th) {
      return false;
    }
  }

  public function editarMateria($id, $nombreMateria, $idArea)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        UPDATE materia SET nombre = ?, id_area = ?
        WHERE id = ?
        RETURNING *
      ');
      $sentencia->execute([$nombreMateria, $idArea, $id]);
      $materia = $sentencia->fetch(PDO::FETCH_OBJ);
      return $materia;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public function delete($id)
  {
    try {
      $sentencia = $this->getPdo()->prepare('
        DELETE FROM materia WHERE id = ?
      ');
      $sentencia->execute([$id]);

      return true;
    } catch (\Throwable $th) {
      return false;
    }
  }
}