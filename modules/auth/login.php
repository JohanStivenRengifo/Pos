<?php
// Establecer zona horaria
date_default_timezone_set('America/Bogota');

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Clase para manejar respuestas JSON
class ApiResponse {
    public static function send($status, $message, $data = null) {
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

        // Registrar login exitoso antes de completar el proceso
        logLoginAttempt($pdo, $user['id'], true, 'Login exitoso');
        
        // Completar el proceso de login
        if (loginUser($user)) {
            // Si el usuario marcó "recordarme"
            if (isset($_POST['remember_me']) && $_POST['remember_me'] === 'on') {
                setRememberMeCookie($pdo, $user);
            }

            // Registrar login exitoso
            logLoginAttempt($pdo, $user['id'], true, 'Login exitoso');

            // Enviar respuesta exitosa
            if (isAjaxRequest()) {
                ApiResponse::send(true, '¡Bienvenido ' . ($user['nombre'] ?? '') . '!', [
                    'redirect' => '../../welcome.php',
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'nombre' => $user['nombre'] ?? ''
                    ]
                ]);
            } else {
                $_SESSION['success_message'] = '¡Bienvenido ' . ($user['nombre'] ?? '') . '!';
                header("Location: ../../welcome.php");
                exit();
            }
        } else {
            throw new Exception('Error al iniciar sesión. Por favor, intente nuevamente.');
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>Bienvenido de nuevo</h2>
            <p>Ingresa tus credenciales para continuar</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required 
                       autocomplete="email" spellcheck="false"
                       placeholder="ejemplo@correo.com">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required
                           placeholder="••••••••">
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group checkbox-container">
                <input type="checkbox" name="remember_me" id="remember_me">
                <label for="remember_me">Mantener sesión iniciada</label>
            </div>

            <div class="form-group text-right">
                <a href="recuperar-password.php" class="btn-link">
                    <i class="fas fa-key"></i>
                    ¿Olvidaste tu contraseña?
                </a>
            </div>

            <button type="submit" class="btn-auth">
                <span>Iniciar Sesión</span>
                <div class="spinner"></div>
            </button>
        </form>

        <div class="auth-footer">
            <p>¿No tienes una cuenta?</p>
            <a href="register.php" class="btn-link">Crear cuenta</a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const submitButton = form.querySelector('button[type="submit"]');

        // Manejar envío del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Deshabilitar el botón y mostrar loading
            submitButton.disabled = true;
            submitButton.classList.add('loading');

            try {
                const formData = new FormData(this);
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                // Intentar obtener los datos JSON de la respuesta
                let data;
                try {
                    data = await response.json();
                } catch (error) {
                    // Si la respuesta no es JSON y es exitosa, redirigir
                    if (response.ok) {
                        window.location.href = '../../welcome.php';
                        return;
                    }
                    throw new Error('Error en la respuesta del servidor');
                }

                // Procesar la respuesta JSON
                if (response.ok && data.status) {
                    // Login exitoso
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
                    // Error con mensaje del servidor
                    throw new Error(data.message || 'Error al iniciar sesión');
                }

            } catch (error) {
                console.error('Error:', error);
                
                // Mostrar mensaje de error
                Swal.fire({
                    icon: 'error',
                    title: 'Error de inicio de sesión',
                    text: error.message || 'Ocurrió un error al procesar la solicitud',
                    confirmButtonColor: '#3085d6'
                });
                
            } finally {
                // Restaurar el botón
                submitButton.disabled = false;
                submitButton.classList.remove('loading');
            }
        });

        // Toggle password visibility
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