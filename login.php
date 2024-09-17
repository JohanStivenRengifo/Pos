<?php
require_once 'fuctions/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        echo "Please fill in all the fields.";
        return;
    }

    try {
        $pdo = getPDOConnection();

        // Fetch user data
        $stmt = $pdo->prepare("SELECT * FROM global_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user'] = $user;

            // Redirect to user's dashboard or home page
            header("Location: dashboard.php");
            exit;

        } else {
            echo "Invalid email or password.";
        }

    } catch (Exception $e) {
        echo "Login failed: " . $e->getMessage();
    }
}
?>

<head>
    <meta charset="UTF-8">
    <title>Login - Sistema Contable</title>
    <link rel="stylesheet" href="/css/login.css">
</head>

<form method="POST" action="login.php">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Login</button>
</form>
