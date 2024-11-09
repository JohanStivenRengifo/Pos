<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID de cliente no proporcionado');
    }

    $stmt = $pdo->prepare("SELECT email FROM clientes WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $email = $stmt->fetchColumn();

    echo json_encode([
        'status' => true,
        'email' => $email
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
} 