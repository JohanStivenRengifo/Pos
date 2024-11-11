<?php
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

// Verificar si hay una sesión temporal de usuario
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_email'])) {
    header("Location: login.php");
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
                    ApiResponse::send(true, 'Login exitoso', ['redirect' => '../../welcome.php']);
                } else {
                    header("Location: ../../welcome.php");
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
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
    .otp-input-container {
        position: relative;
        margin: 20px 0 40px;
        height: 60px;
    }

    .otp-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: text;
        z-index: 2;
    }

    .otp-boxes {
        display: flex;
        gap: 10px;
        justify-content: center;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1;
        pointer-events: none;
    }

    .otp-box {
        width: 50px;
        height: 50px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 24px;
        font-weight: 600;
        background: #ffffff;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .otp-box.filled {
        border-color: #1a73e8;
        background: #e8f0fe;
        transform: translateY(-2px);
    }

    .otp-box.active {
        border-color: #1a73e8;
        box-shadow: 0 0 0 3px rgba(26,115,232,0.2);
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(26,115,232,0.4); }
        70% { box-shadow: 0 0 0 6px rgba(26,115,232,0); }
        100% { box-shadow: 0 0 0 0 rgba(26,115,232,0); }
    }

    .resend-container {
        margin-top: 2rem;
        padding: 1rem;
        border-radius: 12px;
        background: #f8f9fa;
        position: relative;
        z-index: 2;
    }

    .countdown {
        color: #666;
        font-size: 14px;
    }

    .btn-link {
        color: #2196F3;
        transition: all 0.3s ease;
    }

    .btn-link:hover {
        color: #1976D2;
    }

    .btn-link.disabled {
        color: #999;
        cursor: not-allowed;
    }

    .otp-info {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .otp-info i {
        font-size: 24px;
        color: #2196F3;
    }

    /* Mejoras visuales adicionales */
    .auth-container {
        max-width: 480px;
        padding: 2rem;
        box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        border-radius: 16px;
        background: linear-gradient(to bottom, #ffffff, #f8f9fa);
        margin: 2rem auto;
    }

    .auth-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .auth-header h2 {
        color: #1a73e8;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }

    .auth-header p {
        color: #5f6368;
        font-size: 1rem;
    }

    .otp-info {
        background: linear-gradient(145deg, #e3f2fd, #bbdefb);
        border-left: 4px solid #1a73e8;
        padding: 1.25rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .otp-message strong {
        color: #1a73e8;
        font-size: 1.1rem;
        display: block;
        margin-top: 0.25rem;
    }

    .btn-auth {
        background: linear-gradient(145deg, #1a73e8, #1557b0);
        border: none;
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-size: 1rem;
        font-weight: 600;
        color: white;
        width: 100%;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        margin-top: 1rem;
        z-index: 2;
    }

    .btn-auth:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26,115,232,0.3);
    }

    .btn-auth:active {
        transform: translateY(0);
    }

    .resend-container {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 12px;
        margin-top: 1.5rem;
    }

    .btn-link {
        color: #1a73e8;
        font-weight: 500;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .btn-link:hover:not(.disabled) {
        background: #e8f0fe;
    }

    .countdown {
        background: #e8f0fe;
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        font-size: 0.875rem;
        color: #1a73e8;
    }

    /* Animación de carga */
    .loading .spinner {
        width: 20px;
        height: 20px;
        border: 3px solid #ffffff;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Mejoras para dispositivos móviles */
    @media (max-width: 480px) {
        .otp-input-container {
            margin: 20px 0 30px;
        }

        .otp-box {
            width: 40px;
            height: 40px;
            font-size: 20px;
        }

        .auth-container {
            margin: 1rem;
            padding: 1.5rem;
        }
    }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>Verificación en dos pasos</h2>
            <p>Por tu seguridad, necesitamos verificar tu identidad</p>
        </div>

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
                    <div class="otp-message">
                        <p>Hemos enviado un código de verificación a:</p>
                        <strong><?= htmlspecialchars($_SESSION['temp_email']) ?></strong>
                    </div>
                </div>
                
                <label for="otp">Código de verificación</label>
                <div class="otp-input-container">
                    <input type="text" 
                           id="otp" 
                           name="otp" 
                           required 
                           maxlength="6" 
                           pattern="\d{6}"
                           class="otp-input"
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           autofocus>
                    <div class="otp-boxes">
                        <div class="otp-box"></div>
                        <div class="otp-box"></div>
                        <div class="otp-box"></div>
                        <div class="otp-box"></div>
                        <div class="otp-box"></div>
                        <div class="otp-box"></div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-auth">
                <span>Verificar código</span>
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

        <div class="auth-footer">
            <a href="login.php" class="btn-link">
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
        const otpBoxes = document.querySelectorAll('.otp-box');
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

        // Función para animar la transición entre dígitos
        function animateDigitTransition(box, newDigit) {
            box.style.transform = 'translateY(-10px)';
            box.style.opacity = '0';
            
            setTimeout(() => {
                box.textContent = newDigit;
                box.style.transform = 'translateY(0)';
                box.style.opacity = '1';
            }, 150);
        }

        // Mejorar el feedback táctil
        function provideFeedback() {
            if (window.navigator.vibrate) {
                window.navigator.vibrate(50);
            }
        }

        // Actualizar el listener de input
        otpInput.addEventListener('input', function(e) {
            // Limpiar cualquier carácter no numérico y limitar a 6 dígitos
            let value = this.value.replace(/\D/g, '').substring(0, 6);
            this.value = value; // Actualizar el valor del input

            // Actualizar las cajas visuales
            const digits = value.split('');
            otpBoxes.forEach((box, index) => {
                // Animar solo si el dígito ha cambiado
                if (digits[index] !== box.textContent) {
                    animateDigitTransition(box, digits[index] || '');
                }
                box.classList.toggle('filled', !!digits[index]);
                box.classList.toggle('active', index === digits.length);
            });

            // Proporcionar feedback táctil
            if (value.length > 0 && window.navigator.vibrate) {
                window.navigator.vibrate(50);
            }

            // Auto-submit cuando se completan los 6 dígitos
            if (value.length === 6) {
                if (window.navigator.vibrate) {
                    window.navigator.vibrate([100, 50, 100]);
                }
                submitButton.classList.add('ready');
                // Opcional: auto-submit
                // form.dispatchEvent(new Event('submit'));
            } else {
                submitButton.classList.remove('ready');
            }
        });

        // Agregar listener para el foco
        document.addEventListener('click', function(e) {
            // Si se hace clic en cualquier parte del contenedor OTP, enfocar el input
            if (e.target.closest('.otp-input-container')) {
                otpInput.focus();
            }
        });

        // Mejorar el manejo del pegado
        otpInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedData = (e.clipboardData || window.clipboardData)
                .getData('text')
                .replace(/\D/g, '')
                .substring(0, 6);
            
            this.value = pastedData;
            this.dispatchEvent(new Event('input')); // Disparar evento input para actualizar UI
        });

        // Agregar soporte para borrado
        otpInput.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                const currentValue = this.value;
                if (currentValue.length > 0) {
                    this.value = currentValue.slice(0, -1);
                    this.dispatchEvent(new Event('input'));
                }
            }
        });

        // Mejorar la animación de éxito
        function showSuccessAnimation() {
            const container = document.querySelector('.auth-container');
            container.style.transform = 'scale(0.95)';
            setTimeout(() => {
                container.style.transform = 'scale(1)';
            }, 200);
        }

        // Actualizar el manejo de éxito en el submit
        if (data.status) {
            showSuccessAnimation();
            Swal.fire({
                icon: 'success',
                title: '¡Verificación exitosa!',
                text: 'Acceso confirmado',
                timer: 1500,
                showConfirmButton: false,
                background: '#f8f9fa',
                customClass: {
                    popup: 'animated-success-popup'
                },
                didOpen: () => {
                    if (window.navigator.vibrate) {
                        window.navigator.vibrate([100, 50, 100]);
                    }
                }
            }).then(() => {
                window.location.href = data.data.redirect;
            });
        }

        // Manejar navegación con teclado
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

        // Manejar reenvío de código
        resendBtn.addEventListener('click', async function() {
            if (this.disabled) return;

            try {
                const formData = new FormData();
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
                formData.append('resend_otp', '1');

                this.disabled = true;
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                
                if (data.status) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Código reenviado',
                        text: 'Se ha enviado un nuevo código a tu correo',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        position: 'top-end',
                        toast: true
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
                await Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al reenviar el código',
                    confirmButtonColor: '#1a73e8'
                });
            } finally {
                this.disabled = false;
            }
        });

        // Mejorar la animación de carga
        const showLoading = () => {
            submitButton.classList.add('loading');
            submitButton.disabled = true;
            otpInput.disabled = true;
            submitButton.querySelector('span').style.display = 'none';
            submitButton.querySelector('.spinner').style.display = 'block';
        };

        const hideLoading = () => {
            submitButton.classList.remove('loading');
            submitButton.disabled = false;
            otpInput.disabled = false;
            submitButton.querySelector('span').style.display = 'block';
            submitButton.querySelector('.spinner').style.display = 'none';
        };

        // Mejorar el manejo de errores
        const handleError = (message) => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message,
                showConfirmButton: true,
                confirmButtonText: 'Intentar de nuevo',
                confirmButtonColor: '#2196F3'
            });
            otpInput.value = '';
            otpBoxes.forEach(box => {
                box.textContent = '';
                box.classList.remove('filled', 'active');
            });
            otpInput.focus();
        };

        // Actualizar el manejo del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (otpInput.value.length !== 6) return;
            
            showLoading();

            try {
                const formData = new FormData(this);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                
                if (data.status) {
                    // Mostrar mensaje de éxito
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Verificación exitosa!',
                        text: 'Acceso confirmado',
                        timer: 1500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        didOpen: () => {
                            if (window.navigator.vibrate) {
                                window.navigator.vibrate([100, 50, 100]);
                            }
                        }
                    });
                    
                    // Redireccionar después del mensaje
                    window.location.href = data.data.redirect;
                } else {
                    throw new Error(data.message || 'Error al verificar el código');
                }
            } catch (error) {
                // Mostrar mensaje de error
                await Swal.fire({
                    icon: 'error',
                    title: 'Error de verificación',
                    text: error.message || 'Error al verificar el código',
                    confirmButtonColor: '#1a73e8',
                    confirmButtonText: 'Intentar de nuevo'
                });
                
                // Limpiar el input y las cajas
                otpInput.value = '';
                otpBoxes.forEach(box => {
                    box.textContent = '';
                    box.classList.remove('filled', 'active');
                });
                otpInput.focus();
            } finally {
                hideLoading();
            }
        });
    });
    </script>
</body>
</html> 