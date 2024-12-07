<?php
session_start();
require_once '../../config/db.php';

$response = ['success' => false, 'message' => ''];

try {
    $user_id = $_SESSION['user_id'];
    $cliente_id = $_POST['cliente_id'];
    $productos = $_POST['productos'];
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Insertar el préstamo
    $query = "INSERT INTO prestamos (user_id, cliente_id) VALUES (?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $cliente_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al crear el préstamo");
    }
    
    $prestamo_id = $conn->insert_id;
    
    // Insertar los productos del préstamo
    $query_productos = "INSERT INTO prestamos_productos (prestamo_id, producto_id, cantidad) VALUES (?, ?, 1)";
    $stmt_productos = $conn->prepare($query_productos);
    
    // Actualizar el stock de productos
    $query_update_stock = "UPDATE inventario 
                          SET stock = stock - 1 
                          WHERE id = ? 
                          AND user_id = ? 
                          AND stock > 0 
                          AND estado = 'activo'";
    $stmt_update_stock = $conn->prepare($query_update_stock);
    
    foreach ($productos as $producto_id) {
        // Insertar en prestamos_productos
        $stmt_productos->bind_param("ii", $prestamo_id, $producto_id);
        if (!$stmt_productos->execute()) {
            throw new Exception("Error al registrar producto en préstamo");
        }
        
        // Actualizar stock
        $stmt_update_stock->bind_param("ii", $producto_id, $user_id);
        if (!$stmt_update_stock->execute() || $stmt_update_stock->affected_rows == 0) {
            throw new Exception("Error al actualizar el stock del producto");
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    $response['success'] = true;
    $response['message'] = 'Préstamo guardado exitosamente';
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    $response['message'] = $e->getMessage();
    error_log("Error en guardar_prestamo.php: " . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($response); 