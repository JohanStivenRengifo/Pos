<?php
session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

try {
    if (!isset($_POST['venta_id']) || !isset($_POST['email'])) {
        throw new Exception('Faltan datos requeridos');
    }

    $venta_id = intval($_POST['venta_id']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El correo electrónico no es válido');
    }

    // Obtener datos de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, c.nombre as cliente_nombre, c.email as cliente_email
        FROM ventas v 
        LEFT JOIN clientes c ON v.cliente_id = c.id 
        WHERE v.id = ?
    ");
    
    $stmt->execute([$venta_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Venta no encontrada');
    }

    // Generar PDF
    require_once 'generar_pdf.php';
    $pdf_path = generarPDFFactura($venta_id);
    
    if (!$pdf_path || !file_exists($pdf_path)) {
        throw new Exception('Error al generar el PDF de la factura');
    }

    // Configurar PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor SMTP de Hostinger
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'noreply@johanrengifo.cloud';
        $mail->Password = '6=H*]Sl>f/O';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 30;

        $mail->setFrom('noreply@johanrengifo.cloud', 'VendEasy');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Factura de Compra #' . $venta['numero_factura'];
        
        // Crear el contenido del correo
        $emailContent = "
            <h2>Factura de Compra #{$venta['numero_factura']}</h2>
            <p>Estimado(a) " . htmlspecialchars($venta['cliente_nombre']) . ",</p>
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <h3>Detalles de su compra:</h3>
                <ul style='list-style: none; padding: 0;'>
                    <li>Número de factura: {$venta['numero_factura']}</li>
                    <li>Fecha: " . date('d/m/Y', strtotime($venta['fecha'])) . "</li>
                    <li>Total: $" . number_format($venta['total'], 2, ',', '.') . "</li>
                </ul>
            </div>
            <p>Adjunto encontrará el documento PDF de su factura.</p>
            <p>Gracias por su preferencia.</p>
        ";

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
                                Este es un correo automático, por favor no responda a este mensaje.
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

        // Adjuntar PDF
        $mail->addAttachment($pdf_path, 'factura_' . $venta['numero_factura'] . '.pdf');

        // Enviar el email
        if (!$mail->send()) {
            throw new Exception('Error SMTP: ' . $mail->ErrorInfo);
        }

        // Limpiar archivo temporal
        @unlink($pdf_path);

        echo json_encode([
            'status' => true,
            'message' => 'Factura enviada correctamente al correo ' . $email
        ]);

    } catch (Exception $e) {
        if (isset($pdf_path) && file_exists($pdf_path)) {
            @unlink($pdf_path);
        }
        throw new Exception('Error al enviar el correo: ' . $e->getMessage());
    }

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Error al enviar la factura: ' . $e->getMessage()
    ]);
}