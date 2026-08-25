<?php

class   AreaModelo {
  private PDO $db;

  public function __construct(){
    $this->db = DataBase::getConection();
  }

  public function obtenerAreas(){
    
  }
}