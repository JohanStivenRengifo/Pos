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

        // Iniciar sesión con datos completos
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_role'] = $user['rol'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['empresa_id'] = $user['empresa_id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['created_at'] = time();

        // Implementación mejorada de "Recordarme"
        if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
            $token = bin2hex(random_bytes(32));
            $selector = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Almacenar hash del token en la base de datos
            $hashedToken = password_hash($token, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO user_tokens (user_id, selector, token, expires) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    selector = VALUES(selector),
                    token = VALUES(token),
                    expires = VALUES(expires)
                ");
                $stmt->execute([$user['id'], $selector, $hashedToken, $expires]);
                
                // Establecer cookies seguras
                $cookieValue = $selector . ':' . $token;
                setcookie(
                    'remember_token',
                    $cookieValue,
                    [
                        'expires' => strtotime('+30 days'),
                        'path' => '/',
                        'domain' => '',
                        'secure' => true,
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]
                );
            } catch (PDOException $e) {
                error_log("Error al guardar el token: " . $e->getMessage());
                // Continuar sin el remember me si falla
            }
        }

        // Registrar el inicio de sesión exitoso
        logLoginAttempt($pdo, $user['id'], true, 'Login exitoso');

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

// Agregar esta función al inicio del archivo o en functions.php
function cleanExpiredTokens($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE expires < NOW()");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error limpiando tokens expirados: " . $e->getMessage());
    }
}

// Limpiar tokens expirados periódicamente (por ejemplo, 5% de las veces)
if (rand(1, 100) <= 5) {
    cleanExpiredTokens($pdo);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de gestión empresarial VendEasy - Inicio de sesión">
    <title>VendEasy | Iniciar Sesión</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 3px solid #fff;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="min-h-screen flex flex-col md:flex-row bg-gray-50">
    <!-- Sección lateral con imagen y mensaje de bienvenida -->
    <div class="hidden lg:flex lg:w-1/2 bg-primary-600 text-white p-12 flex-col justify-between">
        <div>
            <h1 class="text-4xl font-bold mb-4">VendEasy</h1>
            <p class="text-primary-100">Sistema integral de gestión empresarial</p>
        </div>
        <div class="space-y-6">
            <h2 class="text-3xl font-bold">Potencia tu negocio con tecnología inteligente</h2>
            <p class="text-xl text-primary-100">Gestiona ventas, inventario y más en una sola plataforma.</p>
            <div class="flex space-x-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-check-circle text-primary-300"></i>
                    <span>Interfaz intuitiva</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-shield-alt text-primary-300"></i>
                    <span>100% Seguro</span>
                </div>
            </div>
        </div>
        <div class="text-sm text-primary-100">
            © <?= date('Y') ?> VendEasy. Todos los derechos reservados.
        </div>
    </div>

    <!-- Formulario de inicio de sesión -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-bold text-gray-900">Bienvenido de nuevo</h2>
                <p class="mt-2 text-sm text-gray-600">Ingresa tus credenciales para continuar</p>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="p-4 bg-green-100 border border-green-200 text-green-700 rounded-lg flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); endif; ?>

            <?php if (isset($error)): ?>
            <div class="p-4 bg-red-100 border border-red-200 text-red-700 rounded-lg flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="" class="mt-8 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" id="email" name="email" required autocomplete="email" 
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                placeholder="ejemplo@empresa.com">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                class="block w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                placeholder="••••••••">
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" name="remember_me" id="remember_me" 
                                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                                Mantener sesión iniciada
                            </label>
                        </div>
                        <a href="recuperar-password.php" class="text-sm font-medium text-primary-600 hover:text-primary-500">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                </div>

                <button type="submit" 
                    class="group relative w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150 ease-in-out">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-sign-in-alt"></i>
                    </span>
                    <span class="mx-auto">Iniciar Sesión</span>
                    <div class="spinner hidden absolute right-4 top-1/2 transform -translate-y-1/2"></div>
                </button>

                <div class="text-center mt-4">
                    <p class="text-sm text-gray-600">
                        ¿No tienes una cuenta?
                        <a href="register.php" class="font-medium text-primary-600 hover:text-primary-500">
                            Crear cuenta
                        </a>
                    </p>
                </div>
            </form>
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