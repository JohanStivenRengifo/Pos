<?php
require_once 'env_loader.php';
loadEnv(__DIR__ . '/.env');

function getPDOConnection() {
    $host = getenv('DB_HOST');
    $db = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $charset = 'utf8mb4';
    if (!$host || !$db || !$user) {
        die("Configuración de base de datos incompleta. Por favor, verifique el archivo .env.");
    }
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error al conectar a la base de datos: " . $e->getMessage());
        if (getenv('APP_ENV') === 'development') {
            die("Error al conectar a la base de datos: " . htmlspecialchars($e->getMessage()));
        } else {
            die("Error en la conexión. Por favor, inténtelo más tarde.");
        }
    }
}