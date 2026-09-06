<?php

require_once('config/claves.php');

class Modelo
{

  private $pDO = null;

  public function __construct()
  {
    $this->pDO = $this->crearConexion();
  }

  public function getPdo()
  {
    return $this->pDO;
  }

  public function crearConexion()
  {
    global $configuracion;

    $user = $configuracion['usuario'];
    $password = $configuracion['password'];
    $database = $configuracion['basenombre'];
    $host = $configuracion['host'];
    $port = $configuracion['puerto'];

    try {
      $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database", $user, $password);
    } catch (\Throwable $th) {
      die($th);
    }

    return $pdo;
  }

}