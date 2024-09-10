<?php
session_start();

require_once '../../config/db_connection.php';
$pdo = getPDOConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = filter_var($_POST['csrf_token'], FILTER_SANITIZE_SPECIAL_CHARS);
    if ($csrf_token !== $_SESSION['csrf_token']) {
        die('Token CSRF inválido.');
    }

    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare('INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)');
            $stmt->execute([$email, $token, $expires]);

            $resetLink = "https://possystem.test/forgetpass.php?token=$token";
            $subject = 'Recuperación de Contraseña';
            $message = "Para restablecer tu contraseña, haz clic en el siguiente enlace: $resetLink";
            $headers = 'From: noreply@pospro.com' . "\r\n" .
                'Reply-To: noreply@pospro.com' . "\r\n" .
                'X-Mailer: PHP/' . phpversion();

            header('Location: ../../forgetpass_success.php');
            exit;
        } else {
            $error = 'No se encontró una cuenta con ese correo electrónico.';
        }
    } else {
        $error = 'Correo electrónico inválido.';
    }
} else {
    header('Location: forgetpass.php');
    exit;
}