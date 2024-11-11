<?php
http_response_code(404);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página no encontrada | VendEasy</title>
    <meta name="description" content="Lo sentimos, la página que buscas no existe o ha sido movida.">
    <link rel="icon" type="image/png" href="../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/error.css">
</head>
<body>
    <div class="error-container error-404">
        <i class="fas fa-search error-icon"></i>
        <h1 class="error-code">404</h1>
        <h2 class="error-title">Página no encontrada</h2>
        <p class="error-message">
            Lo sentimos, la página que estás buscando no existe o ha sido movida a otra ubicación.
        </p>
        <div class="error-actions">
            <button onclick="history.back()" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>
                Regresar
            </button>
        </div>
        <div class="error-help">
            <p>¿Necesitas ayuda?</p>
            <a href="../contacto.php">Contacta con soporte</a>
        </div>
    </div>
</body>
</html> 