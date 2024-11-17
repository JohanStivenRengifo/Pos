<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

if (!isset($_POST['imagen_id']) || !isset($_POST['producto_id'])) {
    die(json_encode(['success' => false, 'message' => 'Datos incompletos']));
}

try {
    $pdo->beginTransaction();

    // Primero, quitar la marca de principal de todas las imágenes del producto
    $stmt = $pdo->prepare("UPDATE imagenes_producto SET es_principal = 0 WHERE producto_id = ?");
    $stmt->execute([$_POST['producto_id']]);

    // Luego, establecer la nueva imagen principal
    $stmt = $pdo->prepare("UPDATE imagenes_producto SET es_principal = 1 WHERE id = ? AND producto_id = ?");
    $stmt->execute([$_POST['imagen_id'], $_POST['producto_id']]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 