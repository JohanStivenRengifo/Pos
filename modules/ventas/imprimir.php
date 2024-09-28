<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: ../../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $venta_id = (int)$_GET['id'];

    // Obtener los detalles de la venta
    $stmt = $pdo->prepare("SELECT v.*, c.nombre AS cliente_nombre 
                            FROM ventas v 
                            LEFT JOIN clientes c ON v.cliente_id = c.id 
                            WHERE v.id = ? AND v.user_id = ?");
    $stmt->execute([$venta_id, $user_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($venta) {
        // Mostrar la información de la venta
        echo "<h1>Detalles de la Venta</h1>";
        echo "<p>ID Venta: " . htmlspecialchars($venta['id']) . "</p>";
        echo "<p>Fecha: " . htmlspecialchars($venta['fecha_venta']) . "</p>";
        echo "<p>Cliente: " . htmlspecialchars($venta['cliente_nombre']) . "</p>";
        echo "<p>Total: " . htmlspecialchars(number_format($venta['total'], 2)) . "</p>";
        // Agregar más detalles si es necesario
    } else {
        echo "<p>No se encontró la venta.</p>";
    }
} else {
    echo "<p>ID de venta no especificado.</p>";
}
?>
