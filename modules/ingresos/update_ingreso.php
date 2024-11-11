<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $id = (int)$_POST['id'];
    $user_id = $_SESSION['user_id'];
    
    $query = "UPDATE ingresos 
              SET descripcion = :descripcion,
                  monto = :monto,
                  categoria = :categoria,
                  metodo_pago = :metodo_pago,
                  notas = :notas
              WHERE id = :id AND user_id = :user_id";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        ':id' => $id,
        ':user_id' => $user_id,
        ':descripcion' => trim($_POST['descripcion']),
        ':monto' => (float)trim($_POST['monto']),
        ':categoria' => trim($_POST['categoria']),
        ':metodo_pago' => trim($_POST['metodo_pago']),
        ':notas' => trim($_POST['notas'])
    ]);
    
    if ($result) {
        echo json_encode(['status' => true, 'message' => 'Ingreso actualizado correctamente']);
    } else {
        echo json_encode(['status' => false, 'message' => 'Error al actualizar el ingreso']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => false, 'message' => 'Error en la base de datos']);
} 