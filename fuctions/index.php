<?php
require_once __DIR__ . '/../env_loader.php'; 
loadEnv(__DIR__ . '/../.env');

/**
 * Obtiene una conexión PDO a la base de datos global
 *
 * @return PDO
 */
function getPDOConnection($dbName = null) {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $charset = 'utf8mb4';
    $db = $dbName ? $dbName : getenv('DB_NAME');

    if (!$host || !$user || !$db) {
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

/**
 * Crea una nueva base de datos para un usuario
 *
 * @param string $companyName El nombre de la empresa
 * @return PDO La conexión PDO a la nueva base de datos
 */
function createUserDatabase($companyName) {
    $userDbName = strtolower(preg_replace('/\s+/', '_', $companyName));
    
    // Conectar a la base de datos global para crear la base de datos del usuario
    $pdo = getPDOConnection();

    try {
        $pdo->exec("CREATE DATABASE `$userDbName`");
        
        // Conectarse a la nueva base de datos
        $pdo = getPDOConnection($userDbName);
        return $pdo;
    } catch (PDOException $e) {
        die("Error al crear la base de datos del usuario: " . $e->getMessage());
    }
}

/**
 * Importa una plantilla SQL a una base de datos
 *
 * @param PDO $pdo La conexión PDO a la base de datos
 * @param string $templateFile La ruta al archivo de plantilla SQL
 */
function importSqlTemplate(PDO $pdo, $templateFile) {
    $sql = file_get_contents($templateFile);

    if ($sql === false) {
        die("No se pudo leer el archivo de plantilla SQL.");
    }

    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        die("Error al importar la plantilla SQL: " . $e->getMessage());
    }
}

/**
 * Genera un token CSRF
 *
 * @return string El token CSRF generado
 */
function generarTokenCSRF() {
    return bin2hex(random_bytes(32));
}

/**
 * Valida un token CSRF
 *
 * @param string $token El token CSRF a validar
 */
function validarTokenCSRF($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Token CSRF no válido.");
    }
}
?>