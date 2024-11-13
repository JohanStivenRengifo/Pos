<?php
require_once '../../config/db.php';
require_once '../../config/api_config.php';
require_once './functions.php';

header('Content-Type: application/json');

try {
    $factura_id = $_GET['factura_id'] ?? null;
    
    if (!$factura_id) {
        throw new Exception('ID de factura no proporcionado');
    }

    $estado = verificarEstadoFacturaElectronica($factura_id);
    
    if (!$estado) {
        throw new Exception('No se pudo obtener el estado de la factura');
    }

    echo json_encode([
        'success' => true,
        'estado' => $estado
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 