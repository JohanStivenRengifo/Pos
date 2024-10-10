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

    if ($venta_id === false || $venta_id === null) {
        echo json_encode(['success' => false, 'message' => 'ID de venta inválido']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Verificar si la venta pertenece al usuario
        $stmt = $pdo->prepare("SELECT id, total, descuento FROM ventas WHERE id = ? AND user_id = ?");
        $stmt->execute([$venta_id, $user_id]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venta) {
            throw new Exception('Venta no encontrada o no pertenece al usuario');
        }

        // Obtener los detalles de la venta
        $stmt = $pdo->prepare("SELECT producto_id, cantidad, precio_unitario FROM venta_detalles WHERE venta_id = ?");
        $stmt->execute([$venta_id]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Actualizar el stock en la tabla inventario
        foreach ($detalles as $detalle) {
            $stmt = $pdo->prepare("UPDATE inventario SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$detalle['cantidad'], $detalle['producto_id']]);
        }

        // Eliminar los detalles de la venta
        $stmt = $pdo->prepare("DELETE FROM venta_detalles WHERE venta_id = ?");
        $stmt->execute([$venta_id]);

        // Eliminar la venta
        $stmt = $pdo->prepare("DELETE FROM ventas WHERE id = ?");
        $stmt->execute([$venta_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Venta eliminada con éxito']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la venta: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
