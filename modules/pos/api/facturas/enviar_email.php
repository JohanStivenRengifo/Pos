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
            c.email as cliente_email
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        WHERE v.id = ? AND v.user_id = ?
    ");
    
    $stmt->execute([$data['facturaId'], $_SESSION['user_id']]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$factura) {
        throw new Exception('Factura no encontrada');
    }

    // Crear instancia del controlador de correo
    $mailer = new MailController();

    // Generar el PDF de la factura
    ob_start();
    include '../../controllers/imprimir_factura.php';
    $pdf_content = ob_get_clean();

    // Preparar el contenido del correo
    $subject = isset($data['asunto']) && !empty($data['asunto']) 
        ? $data['asunto'] 
        : "Factura #{$factura['numero_factura']} - " . SMTP_FROM_NAME;

    $content = '
        <h2>Estimado(a) cliente,</h2>
        <p>Adjunto encontrará la factura correspondiente a su compra:</p>
        <div style="background-color: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p><strong>Número de Factura:</strong> ' . htmlspecialchars($factura['numero_factura']) . '</p>
            <p><strong>Fecha:</strong> ' . date('d/m/Y', strtotime($factura['fecha'])) . '</p>
            <p><strong>Total:</strong> $' . number_format($factura['total'], 2, ',', '.') . '</p>
        </div>
        <p>Gracias por su preferencia.</p>';

    // Enviar el correo
    if ($mailer->sendEmail($data['email'], $subject, $content)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Factura enviada correctamente'
        ]);
    } else {
        throw new Exception('Error al enviar el correo');
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar la factura: ' . $e->getMessage()
    ]);
} 