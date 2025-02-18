<?php
session_start();
require_once '../../../../config/db.php';
require_once '../../../../includes/functions.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si se proporcionó el ID de la factura
if (!isset($_GET['factura_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de factura no proporcionado']);
    exit;
}

try {
    // Obtener información del cliente de la factura
    $stmt = $pdo->prepare("
        SELECT 
            c.email as cliente_email,
            CONCAT(c.primer_nombre, ' ', COALESCE(c.segundo_nombre, ''), ' ', COALESCE(c.apellidos, '')) as cliente_nombre
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        WHERE v.id = ? AND v.user_id = ?
    ");
    
    $stmt->execute([$_GET['factura_id'], $_SESSION['user_id']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'cliente_email' => $cliente['cliente_email'],
        'cliente_nombre' => $cliente['cliente_nombre']
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener información del cliente: ' . $e->getMessage()
    ]);
} 