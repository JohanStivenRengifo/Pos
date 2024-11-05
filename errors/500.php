<?php
http_response_code(500);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error 500 - Error interno del servidor</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/error.css">
</head>
<body>
    <div class="error-container">
        <h1>500</h1>
        <h2>Error interno del servidor</h2>
        <p>Lo sentimos, ha ocurrido un error interno. Por favor, inténtalo más tarde.</p>
        <a href="<?= APP_URL ?>" class="btn-home">Volver al inicio</a>
    </div>
</body>
</html> 