<?php
session_start();
require '../../config/db.php'; // Asegúrate de que la ruta sea correcta

// Variables para mensajes
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoger y sanitizar los datos del formulario
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validar que los campos no estén vacíos
    if (empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor complete todos los campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor ingrese un correo electrónico válido.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        // Verificar si el correo electrónico ya está registrado
        $sql = "SELECT * FROM users WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $error = 'Este correo electrónico ya está registrado.';
        } else {
            // Registrar nuevo usuario
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (email, password) VALUES (:email, :password)";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                'email' => $email,
                'password' => $hashed_password
            ]);

            if ($result) {
                // Registro exitoso
                $success = 'Registro exitoso. Ahora puedes iniciar sesión.';
                header("refresh:2;url=../../index.php"); // Redirigir al login después de 2 segundos
            } else {
                $error = 'Ocurrió un problema al registrar el usuario. Inténtalo de nuevo.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .register-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        label {
            display: block;
            margin: 1rem 0 0.5rem 0;
            color: #555;
        }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        input[type="submit"] {
            width: 100%;
            padding: 0.75rem;
            background-color: #007bff;
            border: none;
            border-radius: 5px;
            color: #fff;
            font-size: 1rem;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .message {
            text-align: center;
            margin-top: 1rem;
        }
        .error {
            color: #e74c3c;
        }
        .success {
            color: #2ecc71;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Registrar Cuenta</h2>

        <!-- Mostrar mensajes de error o éxito -->
        <?php if ($error): ?>
            <p class="message error"><?= $error ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="message success"><?= $success ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required>

            <label for="confirm_password">Confirmar Contraseña</label>
            <input type="password" id="confirm_password" name="confirm_password" required>

            <input type="submit" value="Registrar">
        </form>
    </div>
</body>
</html>
