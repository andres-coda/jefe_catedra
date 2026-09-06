<?php

class Helpers {
    
    static public function estaLogueado() {
        if(session_status() != PHP_SESSION_ACTIVE){
            session_start();
        }
        if(isset($_SESSION['IS_LOGGED'])) {
            return true;
        }
        return false;
    }

    static public function checkLogueado() {      
        if (session_status() != PHP_SESSION_ACTIVE){
            session_start();
        }
        if (!isset($_SESSION['ID_USER'])) {
            header('Location: ' . BASE_URL . "login");
            die();            
        } else {
            return true;
        }
    }
}