<?php
session_start();
require_once '../../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Funciones OTP
function generateOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function saveOTP($user_id, $otp) {
    global $pdo;
    try {
        // Verificar si existe la tabla otp_codes
        $stmt = $pdo->query("SHOW TABLES LIKE 'otp_codes'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("CREATE TABLE otp_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                code VARCHAR(6) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                used TINYINT(1) DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )");
        }

        // Eliminar OTPs anteriores del usuario
        $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Guardar nuevo OTP
        $stmt = $pdo->prepare("INSERT INTO otp_codes (user_id, code, created_at, expires_at) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        return $stmt->execute([$user_id, $otp]);
    } catch (Exception $e) {
        error_log("Error guardando OTP: " . $e->getMessage());
        return false;
    }
}

function verifyOTP($user_id, $otp) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM otp_codes WHERE user_id = ? AND code = ? AND expires_at > NOW() AND used = 0");
        $stmt->execute([$user_id, $otp]);
        if ($stmt->rowCount() > 0) {
            // Marcar OTP como usado
            $stmt = $pdo->prepare("UPDATE otp_codes SET used = 1 WHERE user_id = ? AND code = ?");
            $stmt->execute([$user_id, $otp]);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error verificando OTP: " . $e->getMessage());
        return false;
    }
}

function sendOTPEmail($email, $otp, $nombre) {
    $subject = "Código de verificación - VendEasy";
    
    // Plantilla HTML del correo
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Código de Verificación</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background-color: #f8fafc; padding: 30px; border-radius: 0 0 8px 8px; }
            .code { background-color: #ffffff; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 8px; border: 2px dashed #4F46E5; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
            .button { display: inline-block; background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>VendEasy</h1>
            </div>
            <div class="content">
                <h2>Hola ' . htmlspecialchars($nombre) . ',</h2>
                <p>Has solicitado cambiar tu dirección de correo electrónico. Para confirmar este cambio, por favor utiliza el siguiente código de verificación:</p>
                <div class="code">' . $otp . '</div>
                <p>Este código expirará en 10 minutos por razones de seguridad.</p>
                <p><strong>¿No solicitaste este cambio?</strong><br>Si no solicitaste cambiar tu correo electrónico, por favor ignora este mensaje o contacta con nuestro soporte técnico.</p>
            </div>
            <div class="footer">
                <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                <p>&copy; ' . date('Y') . ' VendEasy. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>';

    // Headers para enviar HTML
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: VendEasy <noreply@vendeasy.com>\r\n";
    $headers .= "Reply-To: noreply@vendeasy.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($email, $subject, $message, $headers);
}

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT id, nombre, email, rol, estado, empresa_id, fecha_creacion FROM users WHERE id = ? AND empresa_id = ?");
$stmt->execute([$_SESSION['user_id'], $_SESSION['empresa_id']]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'request_email_change':
                if (isset($_POST['new_email'])) {
                    // Verificar que el nuevo email no esté en uso
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$_POST['new_email'], $_SESSION['user_id']]);
                    if ($stmt->fetchColumn() > 0) {
                        $_SESSION['error_message'] = "El correo electrónico ya está en uso";
                        break;
                    }

                    // Generar y guardar OTP
                    $otp = generateOTP();
                    if (saveOTP($_SESSION['user_id'], $otp)) {
                        // Guardar nuevo email en sesión temporalmente
                        $_SESSION['temp_new_email'] = $_POST['new_email'];
                        
                        if (sendOTPEmail($_POST['new_email'], $otp, $usuario['nombre'])) {
                            $_SESSION['success_message'] = "Se ha enviado un código de verificación a tu nuevo correo electrónico";
                            $_SESSION['show_otp_form'] = true;
                        } else {
                            $_SESSION['error_message'] = "Error al enviar el código de verificación";
                        }
                    } else {
                        $_SESSION['error_message'] = "Error al generar el código de verificación";
                    }
                }
                break;

            case 'verify_email_change':
                if (isset($_POST['otp']) && isset($_SESSION['temp_new_email'])) {
                    if (verifyOTP($_SESSION['user_id'], $_POST['otp'])) {
                        // Actualizar email
                        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ? AND empresa_id = ?");
                        if ($stmt->execute([$_SESSION['temp_new_email'], $_SESSION['user_id'], $_SESSION['empresa_id']])) {
                            $_SESSION['success_message'] = "Correo electrónico actualizado correctamente";
                            unset($_SESSION['temp_new_email']);
                            unset($_SESSION['show_otp_form']);
                        } else {
                            $_SESSION['error_message'] = "Error al actualizar el correo electrónico";
                        }
                    } else {
                        $_SESSION['error_message'] = "Código de verificación inválido o expirado";
                    }
                }
                break;

            case 'update_profile':
                $stmt = $pdo->prepare("UPDATE users SET nombre = ? WHERE id = ? AND empresa_id = ?");
                if ($stmt->execute([$_POST['nombre'], $_SESSION['user_id'], $_SESSION['empresa_id']])) {
                    $_SESSION['success_message'] = "Perfil actualizado correctamente";
                } else {
                    $_SESSION['error_message'] = "Error al actualizar el perfil";
                }
                break;
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | VendEasy</title>
    <link rel="icon" href="../../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../../includes/sidebar.php'; ?>

            <!-- Contenido Principal -->
            <div class="w-full lg:w-3/4 px-4">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-user-circle mr-2"></i>Mi Perfil
                    </h1>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                            <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                            <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Información del Usuario -->
                    <div class="bg-gray-50 p-6 rounded-lg mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">ID de Usuario</label>
                                <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($usuario['id']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fecha de Creación</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    <?php echo $usuario['fecha_creacion'] ? date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])) : 'No disponible'; ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Estado</label>
                                <span class="inline-flex mt-1 px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $usuario['estado'] === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($usuario['estado']); ?>
                                </span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Rol</label>
                                <p class="mt-1 text-sm text-gray-900"><?php echo ucfirst($usuario['rol']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de Perfil -->
                    <div class="bg-gray-50 p-6 rounded-lg mb-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Información Personal</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_profile">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nombre</label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
                                    Actualizar Información
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Cambio de Correo Electrónico -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Cambiar Correo Electrónico</h2>
                        <?php if (!isset($_SESSION['show_otp_form'])): ?>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="request_email_change">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Correo Electrónico Actual</label>
                                    <p class="mt-1 text-sm text-gray-600"><?php echo htmlspecialchars($usuario['email']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nuevo Correo Electrónico</label>
                                    <input type="email" name="new_email" required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
                                        Solicitar Cambio
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="verify_email_change">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Código de Verificación</label>
                                    <input type="text" name="otp" required maxlength="6" pattern="\d{6}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Ingresa el código de 6 dígitos">
                                    <p class="mt-2 text-sm text-gray-500">
                                        Se ha enviado un código de verificación a <?php echo htmlspecialchars($_SESSION['temp_new_email']); ?>
                                    </p>
                                </div>
                                <div class="flex space-x-4">
                                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
                                        Verificar Código
                                    </button>
                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" 
                                       class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        Cancelar
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 