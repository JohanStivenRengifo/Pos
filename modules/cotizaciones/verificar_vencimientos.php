<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

try {
    $hoy = date('Y-m-d');
    
    // Actualizar cotizaciones vencidas basado en la fecha + 30 días
    $query = "UPDATE cotizaciones c
             INNER JOIN clientes cl ON c.cliente_id = cl.id
             SET c.estado = 'Vencida'
             WHERE DATE_ADD(c.fecha, INTERVAL 30 DAY) < ?
             AND c.estado NOT IN ('Facturado', 'Cancelada', 'Vencida')
             AND cl.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$hoy, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'actualizadas' => $stmt->rowCount()
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 