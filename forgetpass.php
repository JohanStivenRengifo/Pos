<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Página para recuperar la contraseña del usuario en POSPro.">
    <title>Recuperar Contraseña | POSPro</title>
    <link rel="icon" href="/favicon/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="./css/forgetpass.css">
</head>
<body>
    <main class="main-content">
        <div class="container">
            <h2>Recuperar Contraseña</h2>
            <form action="./auth/forms/forgetpass_process.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" required>
                <button type="submit">Enviar Instrucciones</button>
            </form>
        </div>
    </main>
</body>
</html>