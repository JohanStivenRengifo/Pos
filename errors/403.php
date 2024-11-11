<?php
http_response_code(403);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso denegado | VendEasy</title>
    <meta name="description" content="No tienes permiso para acceder a este recurso.">
    <link rel="icon" type="image/png" href="../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/error.css">
</head>
<body>
    <div class="error-container error-403">
        <i class="fas fa-lock error-icon"></i>
        <h1 class="error-code">403</h1>
        <h2 class="error-title">Acceso denegado</h2>
        <p class="error-message">
            Lo sentimos, no tienes permiso para acceder a este recurso.
            Si crees que esto es un error, por favor contacta con el administrador.
        </p>
        <div class="error-actions">
            <button onclick="history.back()" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>
                Regresar
            </button>
        </div>
        <div class="error-help">
            <p>¿Necesitas acceso?</p>
            <a href="../contacto.php">Solicitar permisos</a>
        </div>
    </div>
</body>
</html> 