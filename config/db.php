<?php
$host = 'localhost';
$db = 'global_db';
$user = 'root';
$pass = 'J0han#R3ng1fo';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Cambié $conn a $pdo para usarlo como estándar
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Lanzar excepción si no se puede conectar
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>