<?php

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Token CSRF no válido.";
    } else {
        require '../../config/conn.php';
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        if ($password !== $confirm_password) {
            $error_message = "Las contraseñas no coinciden.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $error_message = "El correo electrónico ya está registrado.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (:email, :password)");
                $stmt->execute(['email' => $email, 'password' => $hashed_password]);
                header("Location: ../index.php");
                exit;
            }
        }
    }
}
?>