<?php
session_start();
require_once __DIR__ . '/../../helpers/security.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/database.php';

// Generar un token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

// Manejo del envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si el token CSRF está presente
    if (!isset($_POST['csrf_token']) || !validarTokenCSRF($_POST['csrf_token'])) {
        die("Token CSRF no válido.");
    }

    // Sanitizar y validar datos de entrada
    $email = sanitizeInput($_POST['email']);
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $password = $_POST['password'];

    // Verificar datos
    if (!validateEmail($email)) {
        $error = "Email inválido.";
    } elseif (!validatePassword($password)) {
        $error = "La contraseña debe tener al menos 8 caracteres, incluyendo una mayúscula, una minúscula, un número y un carácter especial.";
    } else {
        try {
            $pdo = getPDOConnection();
            
            // Verificar si el email ya está registrado
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM Usuarios WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "El email ya está registrado.";
            } else {
                // Insertar el nuevo usuario
                $hashedPassword = hashPassword($password);
                $stmt = $pdo->prepare('INSERT INTO Usuarios (email, first_name, last_name, password_hash, created_at) VALUES (?, ?, ?, ?, NOW())');
                $stmt->execute([$email, $first_name, $last_name, $hashedPassword]);

                $success = "Registro exitoso. Puedes iniciar sesión.";
            }
        } catch (Exception $e) {
            $error = "Error en el registro: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - Sistema Contable</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Registro de Usuario</h1>
        </header>

        <main>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php elseif (isset($success)): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="first_name" placeholder="Nombre" required>
                <input type="text" name="last_name" placeholder="Apellido" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit">Registrar</button>
            </form>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> Sistema Contable. Todos los derechos reservados.</p>
        </footer>
    </div>
</body>
</html>