<?php
session_start();
require_once '../../config/db.php';
require_once './functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $productos = obtenerProductos($pdo, $user_id);
    if (empty($productos)) {
        echo json_encode(['warning' => 'No se encontraron productos para este usuario.']);
    } else {
        echo json_encode($productos);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al obtener productos: ' . $e->getMessage()]);
}