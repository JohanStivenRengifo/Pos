<?php
session_start();
require_once __DIR__ . '/../../helpers/security.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/database.php';

// Generar un token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

// Parámetros de intentos fallidos
$max_attempts = 5;
$lockout_time = 15 * 60; // 15 minutos de bloqueo
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si el token CSRF está presente y válido
    if (!isset($_POST['csrf_token']) || !validarTokenCSRF($_POST['csrf_token'])) {
        $error = "Token CSRF no válido.";
    } else {
        // Control de fuerza bruta: comprobar si el usuario está bloqueado
        if (preventBruteForce($_POST['email'], $max_attempts, $lockout_time)) {
            $error = "Demasiados intentos fallidos. Por favor, intenta de nuevo en 15 minutos.";
        } else {
            // Sanitizar y validar los datos de entrada
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password'];

            if (!validateEmail($email)) {
                $error = "Email inválido.";
            } else {
                try {
                    $pdo = getPDOConnection();

                    // Verificar si el usuario existe
                    $stmt = $pdo->prepare('SELECT user_id, password_hash, first_name, last_name FROM Usuarios WHERE email = ?');
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    // Verificar contraseña
                    if ($user && verifyPassword($password, $user['password_hash'])) {
                        // Iniciar sesión y regenerar el ID de la sesión para evitar fijación de sesión
                        session_regenerate_id(true);
                        $_SESSION['user'] = [
                            'user_id' => $user['user_id'],
                            'email' => $email,
                            'first_name' => $user['first_name'],
                            'last_name' => $user['last_name']
                        ];

                        // Guardar el user_id en una cookie
                        setcookie('user_id', $user['user_id'], time() + (86400 * 30), "/"); // 30 días de expiración

                        // Redirigir al usuario a la página de inicio
                        header('Location: /app/views/home/index.php');
                        exit;
                    } else {
                        // Incrementar el contador de intentos fallidos
                        incrementFailedAttempts($email);
                        $error = "Credenciales inválidas.";
                    }
                } catch (Exception $e) {
                    // Registrar el error y mostrar un mensaje genérico
                    error_log("Error en el inicio de sesión: " . $e->getMessage());
                    $error = "Ocurrió un problema al procesar tu solicitud. Intenta nuevamente más tarde.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistema Contable</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Iniciar Sesión</h1>
        </header>

        <main>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form method="POST" action="index.php?action=login">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Contraseña" required>
    <button type="submit">Iniciar Sesión</button>
</form>

        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> Sistema Contable. Todos los derechos reservados.</p>
        </footer>
    </div>
</body>
</html>