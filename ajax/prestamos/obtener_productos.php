<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    error_log("Error: Usuario no autenticado en obtener_productos.php");
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    error_log("Buscando productos para user_id: " . $user_id);
    
    // Verificar productos disponibles
    $queryTotal = "SELECT COUNT(*) as total 
                   FROM inventario 
                   WHERE user_id = ? 
                   AND stock > 0 
                   AND estado = 'activo'";
    $stmt = $conn->prepare($queryTotal);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $totalProductos = $stmt->get_result()->fetch_assoc()['total'];
    error_log("Total de productos disponibles: " . $totalProductos);

    // Consultar productos con stock
    $query = "SELECT 
                id,
                nombre,
                codigo_barras,
                stock,
                precio_venta as precio,
                descripcion
              FROM inventario 
              WHERE user_id = ? 
              AND stock > 0 
              AND estado = 'activo'
              ORDER BY nombre ASC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
    
    error_log("Productos encontrados: " . count($productos));
    
    if (count($productos) > 0) {
        error_log("Primer producto encontrado: " . json_encode($productos[0]));
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $productos,
        'debug' => [
            'user_id' => $user_id,
            'total_disponibles' => $totalProductos,
            'total_encontrados' => count($productos)
        ]
    ]);

} catch(Exception $e) {
    error_log("Error en obtener_productos.php: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error en la base de datos',
        'message' => $e->getMessage(),
        'debug' => [
            'user_id' => $user_id ?? 'no definido',
            'sql_error' => $e->getMessage()
        ]
    ]);
} 