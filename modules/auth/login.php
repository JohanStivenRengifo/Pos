<?php
// Establecer zona horaria
date_default_timezone_set('America/Bogota');

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Clase para manejar respuestas JSON
class ApiResponse
{
    public static function send($status, $message, $data = null)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Verificar si el usuario ya está autenticado
if (isUserLoggedIn($pdo)) {
    header("Location: ../../welcome.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Limpiar cualquier salida previa
        if (ob_get_length()) {
            ob_end_clean();
        }

        // Verificar si la IP está bloqueada
        if (checkIPBlocked($pdo, $_SERVER['REMOTE_ADDR'])) {
            throw new Exception('Demasiados intentos fallidos. Por favor, intente más tarde.');
        }

        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Token de seguridad inválido. Por favor, recargue la página e intente nuevamente.');
        }

        $email = filter_var(sanitizeInput($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = sanitizeInput($_POST['password']);

        if (empty($email) || empty($password)) {
            throw new Exception('Por favor ingrese su correo electrónico y contraseña.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El formato del correo electrónico no es válido.');
        }

        try {
            $user = getUserByEmail($pdo, $email);
        } catch (PDOException $e) {
            error_log("Error en la base de datos: " . $e->getMessage());
            throw new Exception('Error del sistema. Por favor, intente más tarde.');
        }

        if (!$user || !password_verify($password, $user['password'])) {
            logLoginAttempt($pdo, $user ? $user['id'] : null, false, 'Credenciales inválidas');
            throw new Exception('El correo electrónico o la contraseña son incorrectos.');
        }

        if (isset($user['estado']) && $user['estado'] !== 'activo') {
            logLoginAttempt($pdo, $user['id'], false, 'Usuario inactivo');
            throw new Exception('Esta cuenta está desactivada. Por favor, contacte al administrador.');
        }

        if (checkBruteForce($pdo, $user['id'])) {
            logLoginAttempt($pdo, $user['id'], false, 'Cuenta bloqueada');
            throw new Exception("Su cuenta está temporalmente bloqueada por múltiples intentos fallidos. Por favor, intente más tarde.");
        }

        logLoginAttempt($pdo, $user['id'], true, 'Login exitoso');

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['empresa_id'] = $user['empresa_id'] ?? null; 
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_nombre'] = $user['nombre'] ?? '';

        if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
            setRememberMeCookie($pdo, $user);
        }

        if (isAjaxRequest()) {
            ApiResponse::send(true, '¡Bienvenido ' . ($user['nombre'] ?? '') . '!', [
                'redirect' => '../../welcome.php',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'nombre' => $user['nombre'] ?? '',
                    'empresa_id' => $user['empresa_id'] ?? null
                ]
            ]);
        } else {
            $_SESSION['success_message'] = '¡Bienvenido ' . ($user['nombre'] ?? '') . '!';
            header("Location: ../../welcome.php");
            exit();
        }
    } catch (Exception $e) {
        if (isAjaxRequest()) {
            http_response_code(400);
            ApiResponse::send(false, $e->getMessage());
        } else {
            $error = $e->getMessage();
        }
    } catch (Error $e) {
        error_log("Error crítico en login: " . $e->getMessage());
        if (isAjaxRequest()) {
            http_response_code(500);
            ApiResponse::send(false, 'Ha ocurrido un error inesperado. Por favor, intente más tarde.');
        } else {
            $error = 'Ha ocurrido un error inesperado. Por favor, intente más tarde.';
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inicio de sesión seguro para VendEasy">
    <title>Login | VendEasy</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center bg-gray-100 font-poppins">
    <div class="bg-white shadow-md rounded-lg p-8 w-full max-w-md">
        <h2 class="text-2xl font-semibold text-center text-gray-700">Bienvenido de nuevo</h2>
        <p class="text-center text-gray-500 mb-6">Ingresa tus credenciales para continuar</p>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-4">
                <label for="email" class="block text-gray-700">Correo Electrónico</label>
                <input type="email" id="email" name="email" required autocomplete="email" spellcheck="false" placeholder="ejemplo@correo.com" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <div class="mb-4">
                <label for="password" class="block text-gray-700">Contraseña</label>
                <div class="relative">
                    <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <button type="button" class="absolute inset-y-0 right-3 flex items-center text-gray-500" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-4 flex items-center">
                <input type="checkbox" name="remember_me" id="remember_me" class="w-4 h-4">
                <label for="remember_me" class="ml-2 text-gray-700">Mantener sesión iniciada</label>
            </div>

            <div class="mb-4 text-right">
                <a href="recuperar-password.php" class="text-blue-500 hover:underline text-sm"><i class="fas fa-key"></i> ¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-md hover:bg-blue-600 transition duration-300">
                <span>Iniciar Sesión</span>
                <div class="spinner hidden"></div>
            </button>
        </form>

        <div class="mt-6 text-center">
            <p>¿No tienes una cuenta?</p>
            <a href="register.php" class="text-blue-500 hover:underline">Crear cuenta</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const submitButton = form.querySelector('button[type="submit"]');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                submitButton.disabled = true;
                submitButton.querySelector('.spinner').classList.remove('hidden');

                try {
                    const formData = new FormData(this);
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    let data;
                    try {
                        data = await response.json();
                    } catch (error) {
                        if (response.ok) {
                            window.location.href = '../../welcome.php';
                            return;
                        }
                        throw new Error('Error en la respuesta del servidor');
                    }

                    if (response.ok && data.status) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Bienvenido!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = data.data?.redirect || '../../welcome.php';
                        });
                    } else {
                        throw new Error(data.message || 'Error al iniciar sesión');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de inicio de sesión',
                        text: error.message || 'Ocurrió un error al procesar la solicitud',
                        confirmButtonColor: '#3085d6'
                    });
                } finally {
                    submitButton.disabled = false;
                    submitButton.querySelector('.spinner').classList.add('hidden');
                }
            });

            const togglePassword = document.querySelector('.toggle-password');
            const passwordInput = document.getElementById('password');

            togglePassword?.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>

</html>