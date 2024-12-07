<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Usuario no autenticado'
    ]);
    exit;
}

try {
    $busqueda = $_GET['q'] ?? '';
    $user_id = $_SESSION['user_id'];

    error_log("Buscando productos para user_id: $user_id, búsqueda: $busqueda");

    // Verificar si la tabla existe
    $check = $pdo->query("SHOW TABLES LIKE 'inventario'");
    if ($check->rowCount() == 0) {
        throw new Exception("La tabla inventario no existe");
    }

    $query = "SELECT 
                id, 
                nombre, 
                codigo_barras, 
                stock, 
                descripcion,
                precio_venta as precio
              FROM inventario 
              WHERE user_id = :user_id 
              AND stock > 0 
              AND estado = 'activo'
              AND (
                  nombre LIKE :busqueda 
                  OR codigo_barras LIKE :busqueda
                  OR descripcion LIKE :busqueda
              )
              ORDER BY nombre ASC 
              LIMIT 10";

    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . implode(" ", $pdo->errorInfo()));
    }

    $busquedaParam = "%{$busqueda}%";
    $params = [
        ':user_id' => $user_id,
        ':busqueda' => $busquedaParam
    ];

    $stmt->execute($params);
    $productos = $stmt->fetchAll();

    error_log("Productos encontrados: " . count($productos));

    echo json_encode([
        'success' => true,
        'data' => $productos,
        'debug' => [
            'user_id' => $user_id,
            'busqueda' => $busqueda,
            'total_encontrados' => count($productos)
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en buscar_productos.php: " . $e->getMessage());
    error_log("SQL State: " . $pdo->errorInfo()[0] ?? 'N/A');
    error_log("SQL Error: " . $pdo->errorInfo()[2] ?? 'N/A');

    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar productos',
        'message' => $e->getMessage(),
        'debug' => [
            'user_id' => $user_id ?? 'no definido',
            'busqueda' => $busqueda ?? 'no definida',
            'sql_error' => $pdo->errorInfo()[2] ?? 'N/A',
            'sql_state' => $pdo->errorInfo()[0] ?? 'N/A'
        ]
    ]);
} 