<?php
session_start();

// Mostrar mensaje de error si está disponible
if (isset($_SESSION['error_message'])) {
    echo '<div class="error-message">' . htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') . '</div>';
    unset($_SESSION['error_message']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="../../src/css/login.css">
    <script defer src="../../src/js/theme-toggle.js"></script>
</head>
<body>
    <div class="video-background">
        <video autoplay muted loop id="background-video">
            <source src="/src/video/VID-20240714-WA0008.mp4" type="video/mp4">
            Tu navegador no soporta el elemento de video.
        </video>
    </div>
    <div class="container">
        <header>
            <h1>Iniciar Sesión</h1>
            <button id="theme-toggle" class="theme-toggle" aria-label="Cambiar tema">🌙</button>
        </header>
        <div class="form-container">
            <form action="../../config/auth/process_login.php" method="post">
                <div class="form-group">
                    <label for="username">Nombre de Usuario:</label>
                    <input type="text" id="username" name="username" required aria-required="true">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required aria-required="true">
                </div>
                <button type="submit" class="submit-btn">Iniciar Sesión</button>
                <div class="additional-actions">
                    <a href="/public/auth/register.php" class="register-link">Registrar Usuario</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>