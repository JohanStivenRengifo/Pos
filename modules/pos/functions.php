<?php
// Funciones generales
function obtenerClientes($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, nombre FROM clientes WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerProductos($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, nombre, precio_venta AS precio, stock AS cantidad, codigo_barras FROM inventario WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
