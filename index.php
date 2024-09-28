<?php
session_start();
require './config/db.php'; 

// Variables para mensajes
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recoger los datos del formulario y sanitizarlos
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);
    
    // Validar que los campos no estén vacíos
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingrese su correo electrónico y contraseña.';
    } else {
        // Preparar la consulta para evitar inyecciones SQL
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Validar usuario y contraseña
        if ($user && password_verify($password, $user['password'])) {
            // Guardar user_id y email en la sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];

            // Manejo de "Recuérdame"
            $cookie_duration = isset($_POST['remember_me']) ? (86400 * 30) : (86400 * 5);
            setcookie('user_id', $user['id'], time() + $cookie_duration, "/");
            setcookie('email', $user['email'], time() + $cookie_duration, "/");

            // Redirigir al usuario a la página de bienvenida o éxito
            $success = "Inicio de sesión exitoso. Redirigiendo...";
            header("refresh:2;url=welcome.php"); // Redirigir después de 2 segundos
            exit();
        } else {
            $error = "Correo electrónico o contraseña incorrectos.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0px 6px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 380px;
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: bold;
        }

        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            background-color: #f9f9f9;
        }

        input[type="checkbox"] {
            margin-right: 0.5rem;
        }

        input[type="submit"] {
            width: 100%;
            padding: 0.75rem;
            background-color: #007bff;
            border: none;
            border-radius: 6px;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }

        .message {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.9rem;
        }

        .error {
            color: #e74c3c;
        }

        .success {
            color: #2ecc71;
        }

        .register-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            margin-top: 10px;
            transition: background-color 0.3s ease;
        }

        .register-button:hover {
            background-color: #0056b3;
        }

        .register-container {
            text-align: center;
            margin-top: 1.5rem;
        }

        .login-container .checkbox-container {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Iniciar Sesión</h2>

        <!-- Mostrar mensajes de error o éxito -->
        <?php if ($error): ?>
            <p class="message error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="message success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>

            <div class="checkbox-container">
                <input type="checkbox" name="remember_me"> <label for="remember_me">Recuérdame</label>
            </div>

            <input type="submit" value="Login">
        </form>

        <!-- Agregar el botón para redirigir al registro -->
        <div class="register-container">
            <p>¿No tienes una cuenta?</p>
            <a href="modules/auth/register.php" class="register-button">Regístrate aquí</a>
        </div>
    </div>
</body>
</html>
