<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Clase para manejar respuestas JSON
class ApiResponse {
    public static function send($status, $message, $data = null) {
        header('Content-Type: application/json');
        ob_clean(); // Limpiar cualquier salida previa
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Función para generar token de recuperación
function generateRecoveryToken() {
    return bin2hex(random_bytes(32));
}

// Función para enviar email de recuperación
function sendRecoveryEmail($email, $token) {
    try {
        $mail = new PHPMailer(true);
        
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@johanrengifo.cloud';
        $mail->Password = '6=H*]Sl>f/O';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 30;

        // Configuración del remitente y destinatario
        $mail->setFrom('noreply@johanrengifo.cloud', 'VendEasy');
        $mail->addAddress($email);

        // Configuración del contenido
        $mail->isHTML(true);
        $mail->Subject = 'Recuperación de Contraseña - VendEasy';

        // URL de recuperación
        $recovery_url = "https://" . $_SERVER['HTTP_HOST'] . 
                       "/modules/auth/reset-password.php?token=" . $token;

        // Contenido del correo
        $emailContent = "
            <h2>Recuperación de Contraseña</h2>
            <p>Has solicitado restablecer tu contraseña en VendEasy.</p>
            <p>Para continuar con el proceso, haz clic en el siguiente enlace:</p>
            <p><a href='{$recovery_url}' style='padding: 10px 20px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>
                Restablecer Contraseña
            </a></p>
            <p>Este enlace expirará en 1 hora por seguridad.</p>
            <p>Si no solicitaste este cambio, puedes ignorar este correo.</p>
        ";

        // Plantilla del correo
        $template = <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
            <style>
                body {
                    font-size: 1.25rem;
                    font-family: 'Roboto', sans-serif;
                    padding: 20px;
                    background-color: #FAFAFA;
                    width: 75%;
                    max-width: 1280px;
                    min-width: 600px;
                    margin: auto;
                }
                .main-content {
                    padding: 50px;
                    background-color: #fff;
                    max-width: 660px;
                    margin: auto;
                }
                .footer-text {
                    color: #B6B6B6;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <table cellpadding="12" cellspacing="0" width="100%" bgcolor="#FAFAFA" style="border-collapse: collapse;">
                <thead>
                    <tr>
                        <td style="padding: 0;">
                            <img src="https://uploads-ssl.webflow.com/5e96c040bda7162df0a5646d/5f91d2a4d4d57838829dcef4_image-blue%20waves%402x.png" 
                                 style="width: 80%; max-width: 750px;" alt="Blue waves" />
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center; padding-bottom: 20px;">
                            <h1 style="font-family: 'Times New Roman', serif; color: black; font-size: 2.5em; font-style: italic;">
                                VendEasy
                            </h1>
                            <p style="font-family: 'Times New Roman', serif; color: gray; font-size: 1em;">
                                Tu sistema contable inteligente
                            </p>
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="main-content">
                            <table width="100%">
                                <tr>
                                    <td style="text-align: left;">
                                        $emailContent
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td style="text-align: center; padding-top: 30px;">
                            <p class="footer-text">
                                Este es un correo automático, por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </body>
        </html>
        HTML;

        $mail->Body = $template;
        $mail->AltBody = strip_tags($emailContent);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Error al enviar correo de recuperación: " . $e->getMessage());
        return false;
    }
}

// Procesar solicitud de recuperación
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Token de seguridad inválido');
        }

        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Por favor ingrese un correo electrónico válido');
        }

        // Verificar si el email existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Por seguridad, no revelamos si el email existe o no
            $success = "Si el correo existe en nuestro sistema, recibirás instrucciones para restablecer tu contraseña.";
            
            // Limpiar la sesión y redirigir
            session_destroy();
            if (isAjaxRequest()) {
                ApiResponse::send(true, $success, ['redirect' => 'login.php']);
            } else {
                $_SESSION['success_message'] = $success;
                header("Location: login.php");
                exit();
            }
        } else {
            // Generar y guardar token
            $token = generateRecoveryToken();
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expiry_date, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$user['id'], $token, $expiry])) {
                if (sendRecoveryEmail($email, $token)) {
                    $success = "Se han enviado las instrucciones de recuperación a tu correo electrónico.";
                    
                    // Limpiar la sesión y redirigir
                    session_destroy();
                    session_start();
                    $_SESSION['success_message'] = $success;
                    
                    if (isAjaxRequest()) {
                        ApiResponse::send(true, $success, ['redirect' => 'login.php']);
                    } else {
                        header("Location: login.php");
                        exit();
                    }
                } else {
                    throw new Exception('Error al enviar el correo de recuperación');
                }
            } else {
                throw new Exception('Error al procesar la solicitud');
            }
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
    <meta name="description" content="Recupera el acceso a tu cuenta de VendEasy">
    <title>Recuperar Contraseña | VendEasy</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/auth.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <i class="fas fa-key auth-icon"></i>
            <h2>Recuperar contraseña</h2>
            <p>Te enviaremos instrucciones para restablecer tu contraseña</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form id="recoveryForm" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <div class="input-container">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" required 
                           autocomplete="email" spellcheck="false"
                           placeholder="Ingresa tu correo electrónico">
                </div>
                <small class="form-help">
                    <i class="fas fa-info-circle"></i>
                    Ingresa el correo con el que te registraste
                </small>
            </div>

            <button type="submit" class="btn-auth">
                <span>Enviar instrucciones</span>
                <div class="spinner"></div>
            </button>

            <div class="form-info">
                <i class="fas fa-shield-alt"></i>
                <p>Te enviaremos un enlace seguro para restablecer tu contraseña</p>
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
        const form = document.getElementById('recoveryForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const emailInput = document.getElementById('email');

        // Validación en tiempo real del email
        emailInput.addEventListener('input', function() {
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
            this.classList.toggle('is-valid', isValid);
            this.classList.toggle('is-invalid', !isValid && this.value);
        });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }

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
                        title: '¡Correo enviado!',
                        text: data.message,
                        confirmButtonText: 'Entendido',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = 'login.php';
                    });
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al procesar la solicitud'
                });
            } finally {
                submitButton.classList.remove('loading');
                submitButton.disabled = false;
            }
        });
    });
    </script>
</body>
</html> 