<?php

class dbconnect{

public $bdclient;
public function database(){

 try {
   $host = getenv('DB_HOST') ?: 'localhost';
   $name = getenv('DB_NAME') ?: 'Otechnologie';
   $user = getenv('DB_USER') ?: 'root';
   $password = getenv('DB_PASSWORD') ?: '';

   $this->bdclient = new PDO(
     "mysql:host={$host};dbname={$name};charset=utf8mb4",
     $user,
     $password,
     array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
   );
  }
  catch (Exception $e) {
     die('Erreur : ' . $e->getMessage());
    }

  return $this->bdclient;
}




}



 ?>
