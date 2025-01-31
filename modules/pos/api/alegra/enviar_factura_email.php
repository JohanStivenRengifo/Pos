<?php
require_once '../../../config/db.php';
require_once '../../controllers/alegra_integration.php';

header('Content-Type: application/json');

try {
    // Verificar método de solicitud
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Obtener datos del request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['facturaId'])) {
        throw new Exception('ID de factura no proporcionado');
    }

    // Obtener el ID de Alegra de la factura
    $stmt = $pdo->prepare("
        SELECT alegra_id 
        FROM ventas 
        WHERE id = ?
    ");
    $stmt->execute([$data['facturaId']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta || empty($venta['alegra_id'])) {
        throw new Exception('Factura no encontrada o sin ID de Alegra');
    }

    // Enviar el correo
    $alegra = new AlegraIntegration();
    $result = $alegra->sendInvoiceEmail(
        $venta['alegra_id'],
        $data['email'] ?? null
    );

    if (!$result['success']) {
        throw new Exception($result['error']);
    }

    echo json_encode([
        'success' => true,
        'message' => $result['message']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 