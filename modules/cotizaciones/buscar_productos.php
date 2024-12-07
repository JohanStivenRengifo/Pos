<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

try {
    $busqueda = $_GET['q'] ?? '';
    
    $query = "SELECT id, 
                     codigo_barras,
                     nombre,
                     descripcion,
                     CAST(precio_venta AS DECIMAL(10,2)) as precio,
                     CAST(impuesto AS DECIMAL(10,2)) as impuesto,
                     stock
              FROM inventario 
              WHERE (nombre LIKE ? OR codigo_barras LIKE ?) 
              AND user_id = ? 
              AND estado = 'activo'
              LIMIT 10";
    
    $stmt = $pdo->prepare($query);
    $param = "%{$busqueda}%";
    $stmt->execute([$param, $param, $_SESSION['user_id']]);
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los números para asegurar que son válidos
    foreach ($productos as &$producto) {
        $producto['precio'] = number_format((float)$producto['precio'], 2, '.', '');
        $producto['impuesto'] = number_format((float)$producto['impuesto'], 2, '.', '');
    }

    header('Content-Type: application/json');
    echo json_encode($productos);

} catch (Exception $e) {
    error_log("Error en buscar_productos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 