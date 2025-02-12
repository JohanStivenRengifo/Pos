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
        $mail->SMTPDebug = 0;  // 0 = off, 1 = client messages, 2 = client and server messages
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@johanrengifo.cloud';
        $mail->Password = '6=H*]Sl>f/O';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 30;
        
        // Habilitar debug en modo producción
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

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

        $result = $mail->send();
        if (!$result) {
            error_log("Error PHPMailer: " . $mail->ErrorInfo);
            throw new Exception("Error al enviar el correo: " . $mail->ErrorInfo);
        }
        return true;

    } catch (Exception $e) {
        error_log("Error detallado al enviar correo de recuperación: " . $e->getMessage());
        throw new Exception('Error al enviar el correo de recuperación. Por favor, inténtalo más tarde.');
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
    <meta name="description" content="Sistema de gestión empresarial VendEasy - Recuperación de contraseña">
    <title>VendEasy | Recuperar Contraseña</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
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
            <h2 class="text-3xl font-bold">¿Olvidaste tu contraseña?</h2>
            <p class="text-xl text-primary-100">No te preocupes, te ayudamos a recuperar el acceso a tu cuenta</p>
            <div class="space-y-4">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-primary-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-envelope text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium">Ingresa tu correo</h3>
                        <p class="text-sm text-primary-100">Proporciona el correo asociado a tu cuenta</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-primary-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-link text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium">Recibe el enlace</h3>
                        <p class="text-sm text-primary-100">Te enviaremos un enlace seguro a tu correo</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-primary-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-key text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-medium">Crea una nueva contraseña</h3>
                        <p class="text-sm text-primary-100">Establece una nueva contraseña segura</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-sm text-primary-100">
            © <?= date('Y') ?> VendEasy. Todos los derechos reservados.
        </div>
    </div>

    <!-- Formulario de recuperación -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-key text-3xl text-primary-600"></i>
                </div>
                <h2 class="mt-2 text-3xl font-bold text-gray-900">Recuperar contraseña</h2>
                <p class="mt-2 text-sm text-gray-600">Te enviaremos instrucciones para restablecer tu contraseña</p>
            </div>

            <?php if (isset($error)): ?>
            <div class="p-4 bg-red-100 border border-red-200 text-red-700 rounded-lg flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
            <div class="p-4 bg-green-100 border border-green-200 text-green-700 rounded-lg flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <form id="recoveryForm" method="POST" action="" class="mt-8 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" required 
                               autocomplete="email" spellcheck="false"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               placeholder="ejemplo@empresa.com">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Ingresa el correo con el que te registraste
                    </p>
                </div>

                <button type="submit" 
                    class="group relative w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150 ease-in-out">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-paper-plane"></i>
                    </span>
                    <span class="mx-auto">Enviar instrucciones</span>
                    <div class="spinner hidden absolute right-4 top-1/2 transform -translate-y-1/2"></div>
                </button>

                <div class="bg-primary-50 p-4 rounded-lg border border-primary-100">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shield-alt text-primary-600"></i>
                        </div>
                        <div class="text-sm text-primary-700">
                            Te enviaremos un enlace seguro para restablecer tu contraseña. 
                            Este enlace expirará en 1 hora por seguridad.
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <a href="login.php" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-500">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver al inicio de sesión
                    </a>
                </div>
            </form>
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
            this.classList.toggle('border-green-500', isValid);
            this.classList.toggle('border-red-500', !isValid && this.value);
        });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!form.checkValidity()) {
                return;
            }

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
                submitButton.disabled = false;
                submitButton.querySelector('.spinner').classList.remove('hidden');
            }
        });
    });
    </script>
</body>
</html> 