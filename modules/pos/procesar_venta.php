<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    header("Location: ../../index.php");
    exit();
}

if (isset($_POST['productos']) && isset($_POST['cliente_id'])) {
    // Decodificar los productos de JSON
    $productos = json_decode($_POST['productos'], true);
    $clienteId = (int)$_POST['cliente_id']; 
    $pdo->beginTransaction();
    
    try {
        foreach ($productos as $producto) {
            $productoId = (int)$producto['id'];
            $cantidad = (int)$producto['cantidad'];
            $precio = (float)$producto['precio'];
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
                throw new Exception("Inventario insuficiente para el producto: " . $producto['nombre']);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Venta procesada exitosamente."]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Error: Datos incompletos."]);
}
?>
