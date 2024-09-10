<?php
require_once 'env_loader.php';

function getPDOConnection() {
    // Cargar las variables de entorno
    loadEnv(__DIR__ . '/.env');

    $host = getenv('DB_HOST');
    $db = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error al conectar a la base de datos: " . $e->getMessage());
        if (getenv('APP_ENV') === 'development') {
            die("Error al conectar a la base de datos.");
        } else {
            die("Error en la conexión. Por favor, inténtelo más tarde.");
        }
    }
}
?>