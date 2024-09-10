<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Token CSRF no válido.";
    } else {
        require './config/conn.php';
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Correo electrónico inválido.";
        } else {
            $stmt = $pdo->prepare("SELECT id, email, role, password FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $success_message = "Inicio de sesión exitoso.";
                $_SESSION['success_message'] = $success_message;
                header("Location: ../../users/home/index.php");
                exit;
            } else {
                $error_message = "Correo electrónico o contraseña incorrectos.";
            }
        }
    }
    if ($error_message) {
        $_SESSION['error_message'] = $error_message;
    }
}
?>