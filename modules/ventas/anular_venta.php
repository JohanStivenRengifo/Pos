<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Verificar método POST y existencia del ID
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$venta_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$venta_id) {
    echo json_encode(['success' => false, 'message' => 'ID de venta inválido']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Verificar si la venta existe y pertenece al usuario
    $stmt = $pdo->prepare("SELECT id, anulada FROM ventas WHERE id = ? AND user_id = ?");
    $stmt->execute([$venta_id, $user_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Venta no encontrada o no autorizada');
    }

    if ($venta['anulada'] == 1) {
        throw new Exception('La venta ya está anulada');
    }

    // Obtener y devolver productos al inventario
    $stmt = $pdo->prepare("SELECT producto_id, cantidad FROM venta_detalles WHERE venta_id = ?");
    $stmt->execute([$venta_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($detalles as $detalle) {
        $stmt = $pdo->prepare("UPDATE inventario SET stock = stock + ? WHERE id = ?");
        $stmt->execute([$detalle['cantidad'], $detalle['producto_id']]);
    }

    // Marcar la venta como anulada
    $stmt = $pdo->prepare("UPDATE ventas SET anulada = 1 WHERE id = ?");
    $stmt->execute([$venta_id]);

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Venta anulada correctamente',
        'venta_id' => $venta_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error' => true
    ]);
}
?>
