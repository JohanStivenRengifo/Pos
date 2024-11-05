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

// Verificar si hay una sesión temporal de usuario
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_email'])) {
    header("Location: index.php");
    exit();
}

// Procesar verificación OTP
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Token de seguridad inválido');
        }

        // Si es solicitud de reenvío de OTP
        if (isset($_POST['resend_otp'])) {
            if (!generateAndSendOTP($_SESSION['temp_user_id'], $_SESSION['temp_email'])) {
                throw new Exception('Error al reenviar el código');
            }
            ApiResponse::send(true, 'Código reenviado correctamente');
        }

        // Verificación normal de OTP
        $otp = trim($_POST['otp']);
        
        // Validar formato del OTP
        if (!preg_match('/^\d{6}$/', $otp)) {
            throw new Exception('El código debe contener 6 dígitos');
        }

        if (verifyOTP($_SESSION['temp_user_id'], $otp)) {
            try {
                // Registrar intento exitoso
                $stmt = $pdo->prepare("
                    INSERT INTO login_attempts (
                        user_id,
                        ip_address,
                        time,
                        success
                    ) VALUES (?, ?, NOW(), ?)
                ");
                
                $stmt->execute([
                    $_SESSION['temp_user_id'],
                    $_SERVER['REMOTE_ADDR'],
                    1
                ]);

                // Registrar el login exitoso
                $stmt = $pdo->prepare("
                    INSERT INTO login_history (
                        user_id,
                        ip_address,
                        status,
                        login_time
                    ) VALUES (?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $_SESSION['temp_user_id'],
                    $_SERVER['REMOTE_ADDR'],
                    'success'
                ]);

                // Completar login
                $user = getUserById($pdo, $_SESSION['temp_user_id']);
                if (!$user) {
                    throw new Exception('No se pudo verificar la información del usuario.');
                }

                if (!loginUser($user)) {
                    throw new Exception('Error al iniciar sesión.');
                }
                
                // Limpiar variables de sesión temporales
                unset($_SESSION['temp_user_id'], $_SESSION['temp_email']);
                
                if (isAjaxRequest()) {
                    ApiResponse::send(true, 'Login exitoso', ['redirect' => 'welcome.php']);
                } else {
                    header("Location: welcome.php");
                    exit();
                }
            } catch (Exception $e) {
                // Registrar intento fallido
                $stmt = $pdo->prepare("
                    INSERT INTO login_attempts (
                        user_id,
                        ip_address,
                        time,
                        success
                    ) VALUES (?, ?, NOW(), ?)
                ");
                
                $stmt->execute([
                    $_SESSION['temp_user_id'],
                    $_SERVER['REMOTE_ADDR'],
                    0
                ]);

                throw $e;
            }
        } else {
            // Registrar intento fallido de OTP
            $stmt = $pdo->prepare("
                INSERT INTO login_attempts (
                    user_id,
                    ip_address,
                    time,
                    success
                ) VALUES (?, ?, NOW(), ?)
            ");
            
            $stmt->execute([
                $_SESSION['temp_user_id'],
                $_SERVER['REMOTE_ADDR'],
                0
            ]);

            throw new Exception('Código inválido o expirado');
        }

    } catch (Exception $e) {
        if (isAjaxRequest()) {
            ApiResponse::send(false, $e->getMessage());
        } else {
            $error = $e->getMessage();
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
    <meta name="description" content="Verificación de seguridad en dos pasos">
    <title>Verificación OTP | VendEasy</title>
    <link rel="icon" type="image/png" href="favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="login-container">
        <h2>Verificación en dos pasos</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form id="otpForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <div class="otp-info">
                    <i class="fas fa-envelope"></i>
                    <small class="otp-message">
                        Hemos enviado un código de verificación a:<br>
                        <strong><?= htmlspecialchars($_SESSION['temp_email']) ?></strong>
                    </small>
                </div>
                
                <div class="otp-input-container">
                    <input type="text" 
                           id="otp" 
                           name="otp" 
                           required 
                           maxlength="6" 
                           pattern="\d{6}"
                           class="otp-input"
                           placeholder="______"
                           autocomplete="one-time-code"
                           autofocus>
                    <div class="otp-boxes">
                        <span></span><span></span><span></span>
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <span>Verificar código</span>
                <i class="fas fa-arrow-right"></i>
                <div class="spinner"></div>
            </button>

            <div class="resend-container">
                <button type="button" id="resendBtn" class="btn-link">
                    <i class="fas fa-redo"></i>
                    Reenviar código
                </button>
                <span id="countdown" class="countdown"></span>
            </div>
        </form>

        <div class="back-to-login">
            <a href="index.php" class="btn-link">
                <i class="fas fa-arrow-left"></i>
                Volver al inicio de sesión
            </a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('otpForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const resendBtn = document.getElementById('resendBtn');
        const countdown = document.getElementById('countdown');
        const otpInput = document.getElementById('otp');
        const otpBoxes = document.querySelectorAll('.otp-boxes span');
        let countdownTime = 60;
        let countdownInterval;

        // Función para actualizar el contador
        function updateCountdown() {
            if (countdownTime > 0) {
                countdown.textContent = `(${countdownTime}s)`;
                countdownTime--;
            } else {
                clearInterval(countdownInterval);
                countdown.textContent = '';
                resendBtn.disabled = false;
                resendBtn.classList.remove('disabled');
            }
        }

        // Función para iniciar el contador
        function startCountdown() {
            countdownTime = 60;
            resendBtn.disabled = true;
            resendBtn.classList.add('disabled');
            clearInterval(countdownInterval);
            countdownInterval = setInterval(updateCountdown, 1000);
            updateCountdown();
        }

        // Iniciar contador
        startCountdown();

        // Actualizar cajas OTP visuales
        otpInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').substr(0, 6);
            const digits = this.value.split('');
            
            otpBoxes.forEach((box, index) => {
                box.textContent = digits[index] || '';
                box.classList.toggle('filled', !!digits[index]);
            });

            // Auto-submit cuando se completan los 6 dígitos
            if (this.value.length === 6) {
                form.dispatchEvent(new Event('submit'));
            }
        });

        // Manejar reenvío de código
        resendBtn.addEventListener('click', async function() {
            if (this.disabled) return;

            try {
                const formData = new FormData();
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                formData.append('resend_otp', '1');

                this.disabled = true;
                
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
                        title: 'Código reenviado',
                        text: 'Se ha enviado un nuevo código a tu correo',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    startCountdown();
                    otpInput.value = '';
                    otpBoxes.forEach(box => {
                        box.textContent = '';
                        box.classList.remove('filled');
                    });
                    otpInput.focus();
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al reenviar el código'
                });
                this.disabled = false;
            }
        });

        // Manejar envío del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (otpInput.value.length !== 6) return;
            
            submitButton.classList.add('loading');
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
                    Swal.fire({
                        icon: 'success',
                        title: '¡Verificación exitosa!',
                        text: 'Redirigiendo...',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = data.data.redirect;
                    });
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al verificar el código'
                });
                otpInput.value = '';
                otpBoxes.forEach(box => {
                    box.textContent = '';
                    box.classList.remove('filled');
                });
                otpInput.focus();
            } finally {
                submitButton.classList.remove('loading');
                submitButton.disabled = false;
            }
        });

        // Mejorar la experiencia de entrada del código
        otpInput.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value) {
                e.preventDefault();
                this.value = '';
                otpBoxes.forEach(box => {
                    box.textContent = '';
                    box.classList.remove('filled');
                });
            }
        });
    });
    </script>
</body>
</html> 