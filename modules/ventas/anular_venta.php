<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $venta_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    // Aquí puedes agregar la lógica para anular la venta, por ejemplo, marcarla como cancelada.
    $stmt = $pdo->prepare("UPDATE ventas SET estado = 'anulada' WHERE id = ? AND user_id = ?");
    $stmt->execute([$venta_id, $_SESSION['user_id']]);
    
    echo "Venta anulada con éxito.";
} else {
    echo "Método no permitido.";
}
?>