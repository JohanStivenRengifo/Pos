<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';

try {
    if (!isset($_GET['turno_id'])) {
        throw new Exception('ID de turno no proporcionado');
    }

    $turnoId = $_GET['turno_id'];

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total_vendido
        FROM ventas
        WHERE turno_id = ?
    ");
    
    $stmt->execute([$turnoId]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total_vendido' => floatval($resultado['total_vendido'])
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 