<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

try {
    $id = (int)$_GET['id'];
    $user_id = $_SESSION['user_id'];
    
    $query = "SELECT *, 
              FORMAT(monto, 2) as monto_formateado,
              DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as fecha_formateada 
              FROM ingresos 
              WHERE id = :id AND user_id = :user_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':id' => $id, ':user_id' => $user_id]);
    $ingreso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ingreso) {
        echo json_encode(['status' => true, 'data' => $ingreso]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Ingreso no encontrado']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => false, 'message' => 'Error en la base de datos']);
} 