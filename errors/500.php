<?php
http_response_code(500);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error del servidor | VendEasy</title>
    <meta name="description" content="Lo sentimos, ha ocurrido un error interno del servidor.">
    <link rel="icon" type="image/png" href="../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/error.css">
</head>
<body>
    <div class="error-container error-500">
        <i class="fas fa-exclamation-triangle error-icon"></i>
        <h1 class="error-code">500</h1>
        <h2 class="error-title">Error interno del servidor</h2>
        <p class="error-message">
            Lo sentimos, ha ocurrido un error inesperado. 
            Nuestro equipo técnico ha sido notificado y está trabajando en solucionarlo.
        </p>
        <div class="error-actions">
            <button onclick="location.reload()" class="btn btn-outline">
                <i class="fas fa-redo"></i>
                Intentar nuevamente
            </button>
            <button onclick="history.back()" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>
                Regresar
            </button>
        </div>
        <div class="error-help">
            <p>Si el problema persiste, por favor</p>
            <a href="../contacto.php">contacta con soporte técnico</a>
        </div>
    </div>
</body>
</html> 