<?php
require_once '../../../config/db.php';
require_once 'alegra_integration.php';

if (!isset($_GET['id'])) {
    die(json_encode(['success' => false, 'error' => 'ID de venta no especificado']));
}

try {
    // Obtener datos de la venta
    $stmt = $pdo->prepare("SELECT v.alegra_id, v.numeracion, c.email 
                          FROM ventas v 
                          LEFT JOIN clientes c ON v.cliente_id = c.id 
                          WHERE v.id = ?");
    $stmt->execute([$_GET['id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Venta no encontrada');
    }

    if (empty($venta['alegra_id'])) {
        throw new Exception('Esta venta no tiene un ID de Alegra asociado');
    }

    // Usar el email proporcionado o el del cliente como respaldo
    $email = $_GET['email'] ?? $venta['email'];
    if (empty($email)) {
        throw new Exception('No se proporcionó un correo electrónico');
    }

    $alegra = new AlegraIntegration();
    $result = $alegra->sendInvoiceEmail($venta['alegra_id'], $email);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 