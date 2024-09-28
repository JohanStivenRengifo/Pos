<?php
// Cargar variables de entorno
require_once 'config.php';

// Parámetros de conexión
$host = getenv('DB_HOST') ?: '';
$db = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$port = getenv('DB_PORT') ?: '';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

// Construcción del DSN
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

// Opciones de PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
    PDO::ATTR_EMULATE_PREPARES   => false, 
];

try {
    // Crear una conexión PDO
    $pdo = new PDO($dsn, $user, $pass, $options);
    //echo "Conexión exitosa a la base de datos.";
} catch (PDOException $e) {
    // Registro de errores en un archivo de log (producción)
    error_log($e->getMessage(), 3, 'db_errors.log');
    // Mensaje de error genérico para el usuario (producción)
    die('Error en la conexión a la base de datos. Intente más tarde.');
}
?>