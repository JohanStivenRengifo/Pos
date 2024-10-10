<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $venta_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $total = filter_input(INPUT_POST, 'total', FILTER_VALIDATE_FLOAT);
    $productos = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    $precios = $_POST['precios'] ?? [];

    if ($venta_id === false || $venta_id === null || $total === false || empty($productos)) {
        echo json_encode(['success' => false, 'message' => 'Datos de venta inválidos']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Actualizar la venta
        $stmt = $pdo->prepare("UPDATE ventas SET total = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$total, $venta_id, $user_id]);

        // Eliminar los detalles antiguos
        $stmt = $pdo->prepare("DELETE FROM venta_detalles WHERE venta_id = ?");
        $stmt->execute([$venta_id]);

        // Insertar los nuevos detalles
        $stmt = $pdo->prepare("INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
        foreach ($productos as $index => $producto_id) {
            $cantidad = $cantidades[$index];
            $precio = $precios[$index];
            $stmt->execute([$venta_id, $producto_id, $cantidad, $precio]);

            // Actualizar el stock del producto
            $stmt_stock = $pdo->prepare("UPDATE inventario SET stock = stock - ? WHERE id = ?");
            $stmt_stock->execute([$cantidad, $producto_id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Venta actualizada con éxito']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la venta: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
