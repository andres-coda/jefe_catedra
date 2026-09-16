<?php

$configuracion = [];

$configuracion['host'] = 'localhost';
$configuracion['usuario'] = 'app_role';
$configuracion['password'] = 'root';
$configuracion['basenombre'] = 'jefe_catedra';
$configuracion['puerto'] = '5432';

// URL base de la app (usada en redirects generados por el front controller).
// Dejarla vacía usa URLs relativas; setearla absoluta (ej. https://dominio/) si
// la app vive en un subdirectorio o con dominio propio.
$configuracion['base_url'] = '';

/* 

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'tpe_web_2');
define('DB_USER', 'root');
define('DB_PASS', 'root');

define('BASE_URL', '');

define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');
define('UPLOAD_URL', BASE_URL . '/public/uploads/'); 

*/