<?php
$pdo = getPDOConnection();

// Verifica si el usuario está autenticado 
if (!isset($_SESSION['user_id'])) {
    die("Acceso no autorizado.");
}

// Cambiar la contraseña del usuario
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    $userId = $_SESSION['user_id'];

    if ($newPassword !== $confirmPassword) {
        $error = "Las contraseñas no coinciden.";
    } else {
        // Verifica la contraseña actual
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($currentPassword, $user['password'])) {
            // Actualiza la contraseña
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);

            $success = "Contraseña cambiada exitosamente.";
        } else {
            $error = "La contraseña actual es incorrecta.";
        }
    }
}

// Cerrar sesión del usuario
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: /index.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguridad</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f4f4f4;
        }
        h1 {
            color: #333;
        }
        form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="submit"], button {
            padding: 10px 15px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 5px;
        }
        input[type="submit"]:hover, button:hover {
            background-color: #0056b3;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            color: green;
            border: 1px solid #d4edda;
            background-color: #d4edda;
        }
        .error {
            color: red;
            border: 1px solid #f8d7da;
            background-color: #f8d7da;
        }
    </style>
</head>
<body>
    <h1>Seguridad</h1>

    <!-- Mensajes de éxito o error -->
    <?php if (isset($success)): ?>
        <div class="message success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Cambiar Contraseña -->
    <form action="" method="post">
        <h2>Cambiar Contraseña</h2>
        <label for="current_password">Contraseña Actual:</label>
        <input type="password" id="current_password" name="current_password" required>

        <label for="new_password">Nueva Contraseña:</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Confirmar Nueva Contraseña:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <input type="submit" name="change_password" value="Cambiar Contraseña">
    </form>

    <!-- Cerrar Sesión -->
    <form action="" method="post">
        <input type="submit" name="logout" value="Cerrar Sesión">
    </form>
</body>
</html>
