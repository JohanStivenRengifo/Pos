<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../config/mail.php';

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

// Función para generar token de recuperación
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

// Función para guardar token de recuperación
function saveResetToken($pdo, $user_id, $token) {
    try {
        // Verificar si existe la tabla password_resets
        $stmt = $pdo->query("SHOW TABLES LIKE 'password_resets'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("CREATE TABLE password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                used TINYINT(1) DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )");
        }

        // Eliminar tokens anteriores del usuario
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Guardar nuevo token (expira en 1 hora)
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (user_id, token, expires_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ");
        return $stmt->execute([$user_id, $token]);
    } catch (Exception $e) {
        error_log("Error guardando token de recuperación: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'request_reset':
                    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Por favor ingrese un correo electrónico válido.');
                    }

                    // Verificar si el usuario existe
                    $stmt = $pdo->prepare("SELECT id, nombre FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        throw new Exception('Si el correo existe en nuestro sistema, recibirás las instrucciones para restablecer tu contraseña.');
                    }

                    // Generar y guardar token
                    $token = generateResetToken();
                    if (!saveResetToken($pdo, $user['id'], $token)) {
                        throw new Exception('Error al procesar la solicitud. Por favor, intenta más tarde.');
                    }

                    // Enviar correo con el enlace de recuperación
                    $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/modules/auth/reset-password.php?token=" . $token;
                    
                    $mailer = new MailController();
                    $content = '
                        <h2>Hola ' . htmlspecialchars($user['nombre']) . ',</h2>
                        <p>Has solicitado restablecer tu contraseña en VendEasy. Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="' . $resetLink . '" style="background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 500;">
                                Restablecer Contraseña
                            </a>
                        </div>
                        <p>Este enlace expirará en 1 hora por razones de seguridad.</p>
                        <div class="alert alert-warning">
                            <p><strong>¿No solicitaste este cambio?</strong></p>
                            <p>Si no solicitaste restablecer tu contraseña, ignora este mensaje o contacta a soporte técnico.</p>
                        </div>';

                    if (!$mailer->sendEmail($email, "Recuperación de Contraseña - VendEasy", $content)) {
                        throw new Exception('Error al enviar el correo. Por favor, intenta más tarde.');
                    }

                    if (isAjaxRequest()) {
                        ApiResponse::send(true, 'Si el correo existe en nuestro sistema, recibirás las instrucciones para restablecer tu contraseña.');
                    } else {
                        $_SESSION['success_message'] = 'Si el correo existe en nuestro sistema, recibirás las instrucciones para restablecer tu contraseña.';
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    }
                    break;
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="min-h-screen flex flex-col md:flex-row bg-gray-50">
    <!-- Sección lateral -->
    <div class="hidden lg:flex lg:w-1/2 bg-indigo-600 text-white p-12 flex-col justify-between">
        <div>
            <h1 class="text-4xl font-bold mb-4">VendEasy</h1>
            <p class="text-indigo-100">Sistema integral de gestión empresarial</p>
        </div>
        <div class="space-y-6">
            <h2 class="text-3xl font-bold">¿Olvidaste tu contraseña?</h2>
            <p class="text-xl text-indigo-100">No te preocupes, te ayudaremos a recuperar el acceso a tu cuenta.</p>
            <div class="space-y-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-envelope text-indigo-300"></i>
                    <span>Recibirás un correo con instrucciones</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-lock text-indigo-300"></i>
                    <span>Proceso seguro y rápido</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-shield-alt text-indigo-300"></i>
                    <span>Protección de datos garantizada</span>
                </div>
            </div>
        </div>
        <div class="text-sm text-indigo-100">
            © <?= date('Y') ?> VendEasy. Todos los derechos reservados.
        </div>
    </div>

    <!-- Formulario de recuperación -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-bold text-gray-900">Recuperar Contraseña</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Ingresa tu correo electrónico y te enviaremos las instrucciones para restablecer tu contraseña.
                </p>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="p-4 bg-green-100 border border-green-200 text-green-700 rounded-lg flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="p-4 bg-red-100 border border-red-200 text-red-700 rounded-lg flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form id="resetForm" method="POST" class="mt-8 space-y-6">
                <input type="hidden" name="action" value="request_reset">
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo Electrónico</label>
                    <div class="mt-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-gray-400"></i>
                        </div>
                        <input type="email" id="email" name="email" required
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="ejemplo@empresa.com">
                    </div>
                </div>

                <button type="submit" 
                    class="group relative w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-paper-plane"></i>
                    </span>
                    <span class="mx-auto">Enviar Instrucciones</span>
                    <div class="spinner hidden absolute right-4 top-1/2 transform -translate-y-1/2"></div>
                </button>

                <div class="text-center space-y-3">
                    <p class="text-sm text-gray-600">
                        <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                            <i class="fas fa-arrow-left mr-1"></i>
                            Volver al inicio de sesión
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>

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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('resetForm');
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

                const data = await response.json();

                if (data.status) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Correo enviado!',
                        text: data.message,
                        confirmButtonColor: '#4F46E5'
                    }).then(() => {
                        form.reset();
                    });
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al procesar la solicitud',
                    confirmButtonColor: '#4F46E5'
                });
            } finally {
                submitButton.disabled = false;
                submitButton.querySelector('.spinner').classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html> 