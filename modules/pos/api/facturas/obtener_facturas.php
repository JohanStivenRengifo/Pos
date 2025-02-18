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
    // Obtener las facturas del usuario actual
    $stmt = $pdo->prepare("
        SELECT 
            f.id,
            f.numero_factura,
            f.total,
            f.fecha,
            CONCAT(c.primer_nombre, ' ', COALESCE(c.segundo_nombre, ''), ' ', COALESCE(c.apellidos, '')) as cliente_nombre
        FROM facturas f
        LEFT JOIN clientes c ON f.cliente_id = c.id
        WHERE f.user_id = ?
        ORDER BY f.fecha DESC
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