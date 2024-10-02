<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['productos']) && isset($_POST['cliente_id'])) {
    // Decodificar los productos de JSON
    $productos = json_decode($_POST['productos'], true);
    $clienteId = filter_var($_POST['cliente_id'], FILTER_SANITIZE_NUMBER_INT); // Sanitizar el ID del cliente

    // Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        foreach ($productos as $producto) {
            $productoId = filter_var($producto['id'], FILTER_SANITIZE_NUMBER_INT); // Sanitizar el ID del producto
            $cantidad = filter_var($producto['cantidad'], FILTER_SANITIZE_NUMBER_INT); // Sanitizar cantidad
            $precio = filter_var($producto['precio'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); // Sanitizar precio
            $fechaVenta = date('Y-m-d H:i:s');
            $total = $cantidad * $precio;

            // Verificar cantidad en inventario
            $query = $pdo->prepare("SELECT stock FROM inventario WHERE id = ? AND user_id = ?");
            $query->execute([$productoId, $user_id]);
            $row = $query->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['stock'] >= $cantidad) {
                // Descontar cantidad
                $query = $pdo->prepare("UPDATE inventario SET stock = stock - ? WHERE id = ? AND user_id = ?"); 
                $query->execute([$cantidad, $productoId, $user_id]);

                // Insertar en ventas
                $query = $pdo->prepare("INSERT INTO ventas (user_id, producto_id, cliente_id, fecha_venta, cantidad, total, estado) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $estado = 'completada';
                $query->execute([$user_id, $productoId, $clienteId, $fechaVenta, $cantidad, $total, $estado]);
            } else {
                throw new Exception("Inventario insuficiente para el producto: " . htmlspecialchars($producto['nombre']));
            }
        }
        
        // Si todo sale bien, confirmar la transacción
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Venta procesada exitosamente."]);
    } catch (Exception $e) {
        // Si ocurre un error, revertir la transacción
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Error: Datos incompletos."]);
}
?>