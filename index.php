<?php
session_start();
require_once 'fuctions/index.php';

if (isset($_SESSION['user'])) {
    // El usuario está autenticado
    $user = $_SESSION['user'];
    $companyName = strtolower(preg_replace('/\s+/', '_', $user['company_name']));
    
    try {
        $userDbPdo = new PDO("mysql:host=localhost;dbname=$companyName", DB_USER, DB_PASS);
        // Puedes hacer consultas a la base de datos del usuario aquí
        echo "<h1>Bienvenido, " . htmlspecialchars($user['full_name']) . "</h1>";
        echo "<p>Has iniciado sesión con éxito. <a href='dashboard.php'>Ir al panel de usuario</a></p>";
        echo "<p><a href='logout.php'>Cerrar sesión</a></p>";
    } catch (Exception $e) {
        echo "<p>No se pudo conectar a tu base de datos: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    // El usuario no está autenticado
    echo "<h1>Bienvenido al Sistema</h1>";
    echo "<p><a href='register.php'>Registrar</a></p>";
    echo "<p><a href='login.php'>Iniciar sesión</a></p>";
}
?>

<head>
    <meta charset="UTF-8">
    <title>Inicio - Sistema Contable</title>
    <link rel="stylesheet" href="/css/login.css">
</head>