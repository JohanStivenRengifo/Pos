<?php
http_response_code(401);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No autorizado | VendEasy</title>
    <meta name="description" content="Necesitas iniciar sesión para acceder a este recurso.">
    <link rel="icon" type="image/png" href="../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/error.css">
</head>
<body>
    <div class="error-container error-401">
        <i class="fas fa-user-lock error-icon"></i>
        <h1 class="error-code">401</h1>
        <h2 class="error-title">No autorizado</h2>
        <p class="error-message">
            Necesitas iniciar sesión para acceder a este recurso.
            Por favor, inicia sesión o regístrate si aún no tienes una cuenta.
        </p>
        <div class="error-actions">
            <a href="../modules/auth/login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar sesión
            </a>
            <a href="../modules/auth/register.php" class="btn btn-outline">
                <i class="fas fa-user-plus"></i>
                Crear cuenta
            </a>
        </div>
        <div class="error-help">
            <p>¿Olvidaste tu contraseña?</p>
            <a href="../modules/auth/recuperar-password.php">Recuperar acceso</a>
        </div>
    </div>
</body>
</html> 