<?php
require_once __DIR__ . '/env_loader.php'; 
loadEnv(__DIR__ . '/.env');

/**
 * Obtiene una conexión PDO a la base de datos global o a una específica.
 *
 * @param string|null $dbName Nombre de la base de datos
 * @return PDO
 * @throws Exception Si la configuración de la base de datos es incompleta o si hay un error en la conexión.
 */
function getPDOConnection($dbName = null) {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $charset = 'utf8mb4';
    $db = $dbName ?: getenv('DB_NAME');

    if (!$host || !$user || !$db) {
        throw new Exception("Configuración de base de datos incompleta.");
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
        throw new Exception("Error en la conexión. Inténtelo más tarde.");
    }
}
?>