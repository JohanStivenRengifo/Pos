<?php
session_start();
require_once './config/db.php';
require_once './includes/functions.php';

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
    header("Location: welcome.php");
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
    <link rel="icon" type="image/png" href="favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <?php if (isset($_SESSION['show_otp'])): ?>
            <h2>Verificación en dos pasos</h2>
            <form id="otpForm" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label for="otp">Código de verificación</label>
                    <input type="text" id="otp" name="otp" required 
                           maxlength="6" pattern="\d{6}"
                           placeholder="Ingrese el código de 6 dígitos">
                    <small>Se ha enviado un código a su correo electrónico</small>
                </div>

                <button type="submit" name="verify_otp" class="btn-login">
                    Verificar
                </button>
            </form>
            <?php unset($_SESSION['show_otp']); // Limpiar después de mostrar ?>
        <?php else: ?>
            <h2>Iniciar Sesión</h2>
            <form id="loginForm" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" required 
                           autocomplete="email" spellcheck="false">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group checkbox-container">
                    <input type="checkbox" name="remember_me" id="remember_me">
                    <label for="remember_me">Recuérdame</label>
                </div>

                <button type="submit" class="btn-login">
                    <span>Iniciar Sesión</span>
                    <div class="spinner"></div>
                </button>
            </form>

            <div class="register-container">
                <p>¿No tienes una cuenta?</p>
                <a href="modules/auth/register.php" class="btn-register">Regístrate aquí</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const submitButton = form.querySelector('button[type="submit"]');

        // Manejar envío del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
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

    // Agregar manejo para el formulario OTP
    document.getElementById('otpForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        
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
                window.location.href = data.data.redirect;
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
                text: 'Error al procesar la solicitud'
            });
        } finally {
            submitButton.disabled = false;
        }
    });
    </script>
</body>
</html>
