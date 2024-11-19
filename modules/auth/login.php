<?php
// Establecer zona horaria
date_default_timezone_set('America/Bogota');

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Clase para manejar respuestas JSON
class ApiResponse {
    public static function send($status, $message, $data = null) {
        header('Content-Type: application/json');
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

// Procesar solicitud de login
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Limpiar cualquier salida previa
        ob_clean();
        
        // Verificar si la IP está bloqueada
        if (checkIPBlocked($pdo, $_SERVER['REMOTE_ADDR'])) {
            throw new Exception('Demasiados intentos fallidos. Por favor, intente más tarde.');
        }

        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Token de seguridad inválido. Por favor, recargue la página e intente nuevamente.');
        }

        // Login normal
        $email = filter_var(sanitizeInput($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = sanitizeInput($_POST['password']);
        
        // Validaciones básicas
        if (empty($email) || empty($password)) {
            throw new Exception('Por favor ingrese su correo electrónico y contraseña.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El formato del correo electrónico no es válido.');
        }

        // Obtener usuario y manejar errores específicos
        try {
            $user = getUserByEmail($pdo, $email);
        } catch (PDOException $e) {
            error_log("Error en la base de datos: " . $e->getMessage());
            throw new Exception('Error del sistema. Por favor, intente más tarde.');
        }
        
        // Verificar usuario y contraseña
        if (!$user || !password_verify($password, $user['password'])) {
            // Registrar el intento fallido
            logLoginAttempt($pdo, $user ? $user['id'] : null, false, 'Credenciales inválidas');
            throw new Exception('El correo electrónico o la contraseña son incorrectos.');
        }

        // Verificar estado del usuario
        if (isset($user['estado']) && $user['estado'] !== 'activo') {
            logLoginAttempt($pdo, $user['id'], false, 'Usuario inactivo');
            throw new Exception('Esta cuenta está desactivada. Por favor, contacte al administrador.');
        }

        // Verificar si la cuenta está bloqueada
        if (checkBruteForce($pdo, $user['id'])) {
            logLoginAttempt($pdo, $user['id'], false, 'Cuenta bloqueada');
            throw new Exception("Su cuenta está temporalmente bloqueada por múltiples intentos fallidos. Por favor, intente más tarde.");
        }

        // Registrar intento exitoso antes de generar OTP
        logLoginAttempt($pdo, $user['id'], true, 'Login exitoso - Pre OTP');

        // Generar y enviar OTP
        if (!generateAndSendOTP($user['id'], $user['email'])) {
            throw new Exception('No se pudo enviar el código de verificación. Por favor, intente nuevamente.');
        }

        // Guardar datos temporales en sesión
        $_SESSION['temp_user_id'] = $user['id'];
        $_SESSION['temp_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

        // Redirigir a la página de verificación OTP
        if (isAjaxRequest()) {
            ApiResponse::send(true, 'Se ha enviado un código de verificación a su correo electrónico.', [
                'redirect' => 'verify_otp.php'
            ]);
        } else {
            header("Location: verify_otp.php");
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

// Generar CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inicio de sesión seguro para VendEasy">
    <title>Login | VendEasy</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Agregamos Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Bienvenido de nuevo</h2>
            <p class="text-gray-600">Ingresa tus credenciales para continuar</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-4 p-4 rounded-lg bg-green-50 text-green-700 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path>
                </svg>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="mb-4 p-4 rounded-lg bg-red-50 text-red-700 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"></path>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    Correo Electrónico
                </label>
                <input type="email" id="email" name="email" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                       autocomplete="email" spellcheck="false"
                       placeholder="ejemplo@correo.com">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    Contraseña
                </label>
                <div class="relative">
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                           placeholder="••••••••">
                    <button type="button" 
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors"
                            aria-label="Toggle password visibility">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" name="remember_me" id="remember_me" 
                           class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-600">Mantener sesión iniciada</span>
                </label>
                
                <a href="recuperar-password.php" 
                   class="text-sm text-blue-600 hover:text-blue-800 transition-colors">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>

            <button type="submit" 
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors relative">
                <span>Iniciar Sesión</span>
                <div class="absolute right-4 top-1/2 -translate-y-1/2 hidden spinner">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">¿No tienes una cuenta?
                <a href="register.php" class="text-blue-600 hover:text-blue-800 font-medium transition-colors">
                    Crear cuenta
                </a>
            </p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const spinner = submitButton.querySelector('.spinner');

        // Manejar envío del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            submitButton.disabled = true;
            spinner.classList.remove('hidden');

            try {
                const formData = new FormData(this);
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                
                if (data.status) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Bienvenido!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = data.data.redirect;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ocurrió un error al procesar la solicitud'
                });
            } finally {
                submitButton.disabled = false;
                spinner.classList.add('hidden');
            }
        });

        // Toggle password visibility
        const togglePassword = document.querySelector('button[aria-label="Toggle password visibility"]');
        const passwordInput = document.getElementById('password');
        
        togglePassword?.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('svg').classList.toggle('hidden');
        });
    });
    </script>
</body>
</html>