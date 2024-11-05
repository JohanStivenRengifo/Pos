<?php
http_response_code(401);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error 401 - No autorizado</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/error.css">
</head>
<body>
    <div class="error-container">
        <h1>401</h1>
        <h2>No autorizado</h2>
        <p>Lo sentimos, no tienes autorización para acceder a este recurso.</p>
        <a href="<?= APP_URL ?>" class="btn-home">Volver al inicio</a>
    </div>
</body>
</html> 