<?php
http_response_code(400);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error 400 - Solicitud incorrecta</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/error.css">
</head>
<body>
    <div class="error-container">
        <h1>400</h1>
        <h2>Solicitud incorrecta</h2>
        <p>Lo sentimos, la solicitud no pudo ser procesada debido a un error en la sintaxis.</p>
        <a href="<?= APP_URL ?>" class="btn-home">Volver al inicio</a>
    </div>
</body>
</html> 