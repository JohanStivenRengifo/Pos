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

    // Verificar que el ID de la venta es válido
    if ($venta_id > 0) {
        // Marcar la venta como anulada
        $stmt = $pdo->prepare("UPDATE ventas SET estado = 'anulada' WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$venta_id, $user_id])) {
            echo "Venta anulada con éxito.";
        } else {
            // Captura el error
            $errorInfo = $stmt->errorInfo();
            echo "Error al anular la venta: " . htmlspecialchars($errorInfo[2]);
        }
    } else {
        echo "ID de venta inválido.";
    }
} else {
    echo "Método no permitido.";
}
?>
