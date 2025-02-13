<?php

// Configuración del servidor SMTP
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'soporte@johanrengifo.cloud');
define('SMTP_PASS', 'N#w>:]f]8');
define('SMTP_FROM', 'soporte@johanrengifo.cloud');
define('SMTP_FROM_NAME', 'VendEasy');

class MailController {
    private $from_email = 'soporte@johanrengifo.cloud';
    private $from_name = 'VendEasy | Sistema Contable';
    private $reply_to = 'soporte@johanrengifo.cloud';

    // Plantilla base para todos los correos
    private function getBaseTemplate($content) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>VendEasy</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f3f4f6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background-color: #ffffff; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .code { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 8px; border: 2px dashed #4F46E5; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; padding: 20px; }
                .button { display: inline-block; background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-top: 20px; }
                .alert { padding: 15px; border-radius: 6px; margin: 20px 0; }
                .alert-warning { background-color: #fff7ed; border-left: 4px solid #f97316; color: #9a3412; }
                .alert-info { background-color: #eff6ff; border-left: 4px solid #3b82f6; color: #1e40af; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>VendEasy</h1>
                </div>
                <div class="content">
                    ' . $content . '
                </div>
                <div class="footer">
                    <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                    <p>&copy; ' . date('Y') . ' VendEasy. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    // Configuración de headers para el correo
    private function getHeaders() {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->reply_to}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        return $headers;
    }

    // Enviar correo OTP para cambio de email
    public function sendEmailChangeOTP($email, $nombre, $otp) {
        $subject = "Código de verificación - VendEasy";
        
        $content = '
            <h2>Hola ' . htmlspecialchars($nombre) . ',</h2>
            <p>Has solicitado cambiar tu dirección de correo electrónico. Para confirmar este cambio, por favor utiliza el siguiente código de verificación:</p>
            <div class="code">' . $otp . '</div>
            <p>Este código expirará en 10 minutos por razones de seguridad.</p>
            <div class="alert alert-warning">
                <strong>¿No solicitaste este cambio?</strong><br>
                Si no solicitaste cambiar tu correo electrónico, por favor ignora este mensaje o contacta con nuestro soporte técnico.
            </div>';

        return $this->sendEmail($email, $subject, $content);
    }

    // Enviar correo de bienvenida
    public function sendWelcomeEmail($email, $nombre) {
        $subject = "¡Bienvenido a VendEasy!";
        
        $content = '
            <h2>¡Bienvenido a VendEasy, ' . htmlspecialchars($nombre) . '!</h2>
            <p>Nos alegra tenerte como parte de nuestra comunidad. Con VendEasy podrás gestionar tu negocio de manera eficiente y profesional.</p>
            <div class="alert alert-info">
                <strong>Próximos pasos:</strong>
                <ul>
                    <li>Completa la información de tu empresa</li>
                    <li>Configura tus productos y servicios</li>
                    <li>Personaliza tus documentos</li>
                </ul>
            </div>
            <p>Si necesitas ayuda, nuestro equipo de soporte está disponible para asistirte.</p>';

        return $this->sendEmail($email, $subject, $content);
    }

    // Enviar notificación de inicio de sesión sospechoso
    public function sendSecurityAlert($email, $nombre, $device_info, $location) {
        $subject = "Alerta de Seguridad - Nuevo Inicio de Sesión";
        
        $content = '
            <h2>Hola ' . htmlspecialchars($nombre) . ',</h2>
            <p>Hemos detectado un nuevo inicio de sesión en tu cuenta desde un dispositivo no reconocido:</p>
            <div class="alert alert-warning">
                <p><strong>Detalles del inicio de sesión:</strong></p>
                <p>Dispositivo: ' . htmlspecialchars($device_info) . '</p>
                <p>Ubicación: ' . htmlspecialchars($location) . '</p>
                <p>Fecha y hora: ' . date('d/m/Y H:i:s') . '</p>
            </div>
            <p>Si no reconoces esta actividad, por favor:</p>
            <ol>
                <li>Cambia tu contraseña inmediatamente</li>
                <li>Revisa la configuración de seguridad de tu cuenta</li>
                <li>Contacta con nuestro soporte técnico</li>
            </ol>';

        return $this->sendEmail($email, $subject, $content);
    }

    // Enviar notificación de factura
    public function sendInvoiceNotification($email, $nombre, $invoice_number, $amount, $due_date) {
        $subject = "Nueva Factura Generada - VendEasy";
        
        $content = '
            <h2>Hola ' . htmlspecialchars($nombre) . ',</h2>
            <p>Se ha generado una nueva factura para tu cuenta:</p>
            <div style="background-color: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <p><strong>Número de Factura:</strong> ' . htmlspecialchars($invoice_number) . '</p>
                <p><strong>Monto:</strong> $' . number_format($amount, 2) . '</p>
                <p><strong>Fecha de Vencimiento:</strong> ' . date('d/m/Y', strtotime($due_date)) . '</p>
            </div>
            <p>Puedes ver los detalles completos de la factura en tu panel de control.</p>';

        return $this->sendEmail($email, $subject, $content);
    }

    // Enviar recordatorio de suscripción
    public function sendSubscriptionReminder($email, $nombre, $expiry_date, $plan) {
        $subject = "Recordatorio de Suscripción - VendEasy";
        
        $content = '
            <h2>Hola ' . htmlspecialchars($nombre) . ',</h2>
            <p>Tu suscripción al plan ' . htmlspecialchars($plan) . ' vencerá pronto:</p>
            <div class="alert alert-info">
                <p><strong>Fecha de vencimiento:</strong> ' . date('d/m/Y', strtotime($expiry_date)) . '</p>
                <p>Para mantener el acceso a todas las funciones, asegúrate de renovar tu suscripción antes de la fecha de vencimiento.</p>
            </div>
            <p>Si tienes habilitada la renovación automática, no necesitas realizar ninguna acción.</p>';

        return $this->sendEmail($email, $subject, $content);
    }

    // Método base para enviar correos
    public function sendEmail($to, $subject, $content) {
        $message = $this->getBaseTemplate($content);
        return sendEmail($to, $subject, $message);
    }
}

// Ejemplo de uso:
/*
$mailer = new MailController();

// Enviar OTP
$mailer->sendEmailChangeOTP('usuario@ejemplo.com', 'Juan Pérez', '123456');

// Enviar bienvenida
$mailer->sendWelcomeEmail('usuario@ejemplo.com', 'Juan Pérez');

// Enviar alerta de seguridad
$mailer->sendSecurityAlert('usuario@ejemplo.com', 'Juan Pérez', 'Chrome en Windows', 'Bogotá, Colombia');

// Enviar notificación de factura
$mailer->sendInvoiceNotification('usuario@ejemplo.com', 'Juan Pérez', 'INV-2024-001', 99.99, '2024-03-01');

// Enviar recordatorio de suscripción
$mailer->sendSubscriptionReminder('usuario@ejemplo.com', 'Juan Pérez', '2024-03-01', 'Premium');
*/

// Función para enviar correos
function sendEmail($to, $subject, $message, $from = SMTP_FROM, $fromName = SMTP_FROM_NAME) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Error enviando correo: " . $e->getMessage());
        return false;
    }
}
?> 