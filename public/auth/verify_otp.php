<?php
session_start();

require_once '../../config/auth/index.php';

// Verificar el método de solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputOtp = $_POST['otp'] ?? '';

    // Validar si el OTP fue proporcionado
    if (empty($inputOtp)) {
        $_SESSION['error_message'] = "El OTP no puede estar vacío.";
        header('Location: verify_otp.php');
        exit();
    }

    // Verificar que el usuario está autenticado
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = "No se encontró la sesión del usuario.";
        header('Location: verify_otp.php');
        exit();
    }

    // Conectar a la base de datos principal
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, 'main_db');

    // Verificar la conexión a la base de datos
    if ($db->connect_error) {
        $_SESSION['error_message'] = "Error de conexión a la base de datos: " . $db->connect_error;
        header('Location: verify_otp.php');
        exit();
    }

    // Preparar y ejecutar la consulta para obtener el OTP almacenado y su expiración
    $stmt = $db->prepare("SELECT otp, otp_expires_at FROM users WHERE id = ?");
    if (!$stmt) {
        $_SESSION['error_message'] = "Error al preparar la consulta: " . $db->error;
        $db->close();
        header('Location: verify_otp.php');
        exit();
    }

    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($storedOtp, $otpExpiresAt);
    $stmt->fetch();
    $stmt->close();
    
    // Verificar OTP y su validez
    $currentDateTime = new DateTime();
    if ($storedOtp === $inputOtp && $currentDateTime <= new DateTime($otpExpiresAt)) {
        // OTP verificado exitosamente, limpiar OTP y expiración
        $stmt = $db->prepare("UPDATE users SET otp = NULL, otp_expires_at = NULL WHERE id = ?");
        if (!$stmt) {
            $_SESSION['error_message'] = "Error al preparar la actualización del OTP: " . $db->error;
            $db->close();
            header('Location: verify_otp.php');
            exit();
        }

        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();

        // Redirigir al usuario a la siguiente página o proceso
        header('Location: /home.php');
        exit();
    } else {
        $_SESSION['error_message'] = "El OTP es incorrecto o ha expirado.";
        header('Location: verify_otp.php');
        exit();
    }

    $db->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar OTP</title>
    <link rel="stylesheet" href="../../src/css/otp.css">
</head>
<body>
    <div class="container">
        <h1>Verificar OTP</h1>
        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<p class="error-message">' . htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') . '</p>';
            unset($_SESSION['error_message']);
        }
        ?>
        <form action="verify_otp.php" method="post">
            <div class="form-group">
                <label for="otp">Introduce tu OTP:</label>
                <input type="text" id="otp" name="otp" required>
            </div>
            <button type="submit" class="submit-btn">Verificar OTP</button>
        </form>
    </div>
</body>
</html>