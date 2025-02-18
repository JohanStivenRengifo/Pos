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

if (!isset($data['facturaId']) || !isset($data['email']) || !isset($data['asunto'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
    exit;
}

try {
    // Obtener información de la venta
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            CONCAT(c.primer_nombre, ' ', COALESCE(c.segundo_nombre, ''), ' ', COALESCE(c.apellidos, '')) as cliente_nombre,
            c.email as cliente_email,
            e.nombre as empresa_nombre,
            e.direccion as empresa_direccion,
            e.telefono as empresa_telefono,
            e.email as empresa_email
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON v.user_id = e.user_id
        WHERE v.id = ? AND v.user_id = ?
    ");
    
    $stmt->execute([$data['facturaId'], $_SESSION['user_id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Factura no encontrada');
    }

    // Obtener detalles de la venta
    $stmt = $pdo->prepare("
        SELECT 
            vd.*,
            i.nombre as producto_nombre,
            i.codigo_barras
        FROM venta_detalles vd
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
    ");
    
    $stmt->execute([$data['facturaId']]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear el contenido del correo
    $content = '
        <h2>Factura de Venta ' . htmlspecialchars($venta['numero_factura']) . '</h2>
        <div style="margin-bottom: 20px;">
            <p><strong>' . htmlspecialchars($venta['empresa_nombre']) . '</strong></p>
            <p>' . htmlspecialchars($venta['empresa_direccion']) . '</p>
            <p>Tel: ' . htmlspecialchars($venta['empresa_telefono']) . '</p>
            <p>Email: ' . htmlspecialchars($venta['empresa_email']) . '</p>
        </div>
        <div style="margin-bottom: 20px;">
            <p><strong>Cliente:</strong> ' . htmlspecialchars($venta['cliente_nombre']) . '</p>
            <p><strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime($venta['fecha'])) . '</p>
        </div>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f3f4f6;">
                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Producto</th>
                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">Cantidad</th>
                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">Precio</th>
                    <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($detalles as $detalle) {
        $content .= '
            <tr>
                <td style="padding: 8px; border: 1px solid #e5e7eb;">' . htmlspecialchars($detalle['producto_nombre']) . '</td>
                <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">' . $detalle['cantidad'] . '</td>
                <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">$' . number_format($detalle['precio_unitario'], 0, ',', '.') . '</td>
                <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">$' . number_format($detalle['subtotal'], 0, ',', '.') . '</td>
            </tr>';
    }

    $content .= '
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;"><strong>Subtotal:</strong></td>
                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">$' . number_format($venta['subtotal'], 0, ',', '.') . '</td>
                </tr>';
    
    if ($venta['descuento'] > 0) {
        $content .= '
            <tr>
                <td colspan="3" style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;"><strong>Descuento (' . $venta['descuento'] . '%):</strong></td>
                <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">-$' . number_format($venta['subtotal'] * ($venta['descuento'] / 100), 0, ',', '.') . '</td>
            </tr>';
    }

    $content .= '
                <tr>
                    <td colspan="3" style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;"><strong>Total:</strong></td>
                    <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;"><strong>$' . number_format($venta['total'], 0, ',', '.') . '</strong></td>
                </tr>
            </tfoot>
        </table>';

    // Crear instancia del controlador de correo
    $mailer = new MailController();
    
    // Enviar el correo
    $mailer->sendEmail(
        $data['email'],
        $data['asunto'],
        $content
    );

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Correo enviado correctamente'
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar el correo: ' . $e->getMessage()
    ]);
} 