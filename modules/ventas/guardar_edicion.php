<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $venta_id = (int)$_POST['id'];
    $total = (float)$_POST['total'];

    // Actualizar la venta
    $stmt = $pdo->prepare("UPDATE ventas SET total = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$total, $venta_id, $user_id]);

    echo "Venta actualizada con éxito.";
} else {
    echo "Método no permitido.";
}
?>
