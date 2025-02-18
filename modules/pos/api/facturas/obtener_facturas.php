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

try {
    // Obtener las ventas del usuario actual
    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.numero_factura,
            v.total,
            v.fecha,
            v.estado,
            v.numeracion,
            v.tipo_documento,
            CONCAT(c.primer_nombre, ' ', COALESCE(c.segundo_nombre, ''), ' ', COALESCE(c.apellidos, '')) as cliente_nombre
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        WHERE v.user_id = ?
        AND v.estado = 'completada'
        AND v.anulada = 0
        ORDER BY v.fecha DESC
        LIMIT 100
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'facturas' => $facturas
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener las facturas: ' . $e->getMessage()
    ]);
} 