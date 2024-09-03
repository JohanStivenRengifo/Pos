<?php
session_start();
require_once '.././db.php';
require_once '../..//config/auth/index.php'; // Asegúrate de que `generateOTP`, `sendOtpEmail`, y `verifyOTP` estén definidos aquí

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validar datos
    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = "Por favor, complete todos los campos.";
        header('Location: ../../index.php');
        exit();
    }

    // Conectar a la base de datos principal
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, 'main_db');

    // Verificar la conexión
    if ($db->connect_error) {
        $_SESSION['error_message'] = "Error de conexión: " . $db->connect_error;
        header('Location: ../../index.php');
        exit();
    }

    // Preparar y ejecutar la consulta
    $stmt = $db->prepare("SELECT id, password, email FROM users WHERE username = ?");
    if (!$stmt) {
        $_SESSION['error_message'] = "Error en la consulta: " . $db->error;
        header('Location: ../../index.php');
        exit();
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password, $email);
    $stmt->fetch();

    // Verificar la contraseña
    if (password_verify($password, $hashed_password)) {
        // Generar y enviar OTP
        $otp = generateOTP();
        $_SESSION['otp'] = $otp;
        $_SESSION['user_id'] = $id;

        if (sendOtpEmail($email, $otp)) {
            header('Location: ../../public/auth/verify_otp.php');
            exit();
        } else {
            $_SESSION['error_message'] = "No se pudo enviar el OTP. Por favor, intenta de nuevo.";
            header('Location: ../../index.php');
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Usuario o contraseña incorrectos.";
        header('Location: ../../index.php');
        exit();
    }

    // Cerrar la declaración y la conexión
    $stmt->close();
    $db->close();
}
?>