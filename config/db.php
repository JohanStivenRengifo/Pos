<?php
// Configuración de la base de datos principal
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('MAIN_DB', 'main_db');

// Función para conectar a la base de datos principal
function connectMainDB() {
    $connection = new mysqli(DB_HOST, DB_USER, MAIN_DB);
    
    if ($connection->connect_error) {
        die("Error de conexión a la base de datos principal: " . $connection->connect_error);
    }
    
    return $connection;
}

// Función para conectar a la base de datos de un usuario específico
function connectUserDB($dbname) {
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, $dbname);
    
    if ($connection->connect_error) {
        die("Error de conexión a la base de datos del usuario: " . $connection->connect_error);
    }
    
    return $connection;
}
?>