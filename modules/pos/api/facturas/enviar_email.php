<?php
session_start();
require_once '../../../../config/db.php';
require_once '../../../../config/mail.php';
require_once '../../../../includes/functions.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['facturaId']) || !isset($data['email'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Obtener información de la factura
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            CONCAT(c.primer_nombre, ' ', COALESCE(c.segundo_nombre, ''), ' ', COALESCE(c.apellidos, '')) as cliente_nombre,
            c.email as cliente_email,
            c.identificacion as cliente_documento
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        WHERE v.id = ? AND v.user_id = ?
    ");
    
    $stmt->execute([$data['facturaId'], $_SESSION['user_id']]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        throw new Exception('Factura no encontrada');
    }

    // Obtener detalles de la factura
    $stmt = $pdo->prepare("
        SELECT 
            vd.*,
            i.nombre as producto_nombre,
            i.codigo_barras
        FROM venta_detalles vd
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
    ");
    
    $stmt->execute([$factura['id']]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear el contenido del correo
    $content = '
        <h2>Factura de Venta #' . htmlspecialchars($factura['numero_factura']) . '</h2>
        <p>Estimado/a cliente,</p>
        <p>Adjunto encontrará su factura con los siguientes detalles:</p>
        
        <div style="background-color: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px;"><strong>Número de Factura:</strong></td>
                    <td style="padding: 8px;">' . htmlspecialchars($factura['numero_factura']) . '</td>
                    <td style="padding: 8px;"><strong>Fecha:</strong></td>
                    <td style="padding: 8px;">' . date('d/m/Y', strtotime($factura['fecha'])) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px;"><strong>Cliente:</strong></td>
                    <td style="padding: 8px;">' . htmlspecialchars($factura['cliente_nombre']) . '</td>
                    <td style="padding: 8px;"><strong>Documento:</strong></td>
                    <td style="padding: 8px;">' . htmlspecialchars($factura['cliente_documento']) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px;"><strong>Tipo:</strong></td>
                    <td style="padding: 8px;">' . htmlspecialchars($factura['tipo_documento']) . '</td>
                    <td style="padding: 8px;"><strong>Método de Pago:</strong></td>
                    <td style="padding: 8px;">' . htmlspecialchars($factura['metodo_pago']) . '</td>
                </tr>
            </table>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background-color: #f3f4f6;">
                    <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e5e7eb;">Producto</th>
                    <th style="padding: 12px; text-align: center; border-bottom: 2px solid #e5e7eb;">Código</th>
                    <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">Cantidad</th>
                    <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">Precio Unit.</th>
                    <th style="padding: 12px; text-align: right; border-bottom: 2px solid #e5e7eb;">Subtotal</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($detalles as $detalle) {
        $content .= '
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">' . htmlspecialchars($detalle['producto_nombre']) . '</td>
                <td style="padding: 12px; text-align: center; border-bottom: 1px solid #e5e7eb;">' . htmlspecialchars($detalle['codigo_barras']) . '</td>
                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e5e7eb;">' . number_format($detalle['cantidad'], 0) . '</td>
                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e5e7eb;">$' . number_format($detalle['precio_unitario'], 2, ',', '.') . '</td>
                <td style="padding: 12px; text-align: right; border-bottom: 1px solid #e5e7eb;">$' . number_format($detalle['subtotal'], 2, ',', '.') . '</td>
            </tr>';
    }

    $content .= '
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="padding: 12px; text-align: right;"><strong>Subtotal:</strong></td>
                    <td style="padding: 12px; text-align: right;">$' . number_format($factura['subtotal'], 2, ',', '.') . '</td>
                </tr>';
    
    if ($factura['descuento'] > 0) {
        $content .= '
            <tr>
                <td colspan="4" style="padding: 12px; text-align: right;"><strong>Descuento:</strong></td>
                <td style="padding: 12px; text-align: right;">$' . number_format($factura['descuento'], 2, ',', '.') . '</td>
            </tr>';
    }
    
    $content .= '
                <tr>
                    <td colspan="4" style="padding: 12px; text-align: right;"><strong>Total:</strong></td>
                    <td style="padding: 12px; text-align: right;"><strong>$' . number_format($factura['total'], 2, ',', '.') . '</strong></td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top: 20px; padding: 20px; background-color: #f3f4f6; border-radius: 8px;">
            <p style="margin: 0; font-size: 12px; color: #4b5563;">Esta factura ha sido generada electrónicamente.</p>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #4b5563;">Para cualquier consulta, por favor contacte a nuestro equipo de soporte.</p>
        </div>';

    // Crear instancia del controlador de correo
    $mailer = new MailController();
    
    // Preparar el asunto del correo
    $subject = !empty($data['asunto']) 
        ? $data['asunto'] 
        : "Factura #{$factura['numero_factura']} | " . SMTP_FROM_NAME;

    // Ruta al PDF de la factura
    $pdfPath = "../../../../facturas/pdf/{$factura['id']}.pdf";
    $attachments = [];
    
    // Verificar si existe el PDF
    if (file_exists($pdfPath)) {
        $attachments[] = $pdfPath;
    }

    // Enviar el correo
    $result = $mailer->sendEmail(
        $data['email'],
        $subject,
        $content,
        $attachments
    );

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Factura enviada correctamente'
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar la factura: ' . $e->getMessage()
    ]);
} 