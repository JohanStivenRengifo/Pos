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

function buscarClientes($pdo, $user_id, $query) {
    $query = "%$query%";
    $stmt = $pdo->prepare("SELECT id, nombre, documento FROM clientes WHERE user_id = ? AND (nombre LIKE ? OR documento LIKE ?) LIMIT 10");
    $stmt->execute([$user_id, $query, $query]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function guardarCliente($pdo, $user_id, $nombre, $documento, $email) {
    $stmt = $pdo->prepare("INSERT INTO clientes (user_id, nombre, documento, email) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $nombre, $documento, $email])) {
        $id = $pdo->lastInsertId();
        return [
            'id' => $id,
            'nombre' => $nombre,
            'documento' => $documento,
            'email' => $email
        ];
    }
    return false;
}
