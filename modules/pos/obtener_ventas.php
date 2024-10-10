<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Usuario no autenticado']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT id, numero_factura, DATE_FORMAT(fecha, '%d/%m/%Y %H:%i') AS fecha 
                           FROM ventas 
                           WHERE user_id = ? 
                           ORDER BY fecha DESC 
                           LIMIT 50");
    $stmt->execute([$user_id]);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($ventas);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error al obtener las ventas: ' . $e->getMessage()]);
}