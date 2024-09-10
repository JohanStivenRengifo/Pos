<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente = $_POST['cliente'];
    $producto = $_POST['producto'];
    $cantidad = $_POST['cantidad'];
    $total = $_POST['total'];

    try {
        $stmt = $pdo->prepare("INSERT INTO ventas (cliente, producto, cantidad, total) VALUES (?, ?, ?, ?)");
        $stmt->execute([$cliente, $producto, $cantidad, $total]);
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        die("Error al crear la venta: " . $e->getMessage());
    }
}
?>

<div class="content">
    <h2>Crear Nueva Venta</h2>
    <form method="post">
        <label>Cliente:</label>
        <input type="text" name="cliente" required>
        <label>Producto:</label>
        <input type="text" name="producto" required>
        <label>Cantidad:</label>
        <input type="number" name="cantidad" required>
        <label>Total:</label>
        <input type="number" step="0.01" name="total" required>
        <button type="submit" class="btn btn-success">Crear Venta</button>
    </form>
</div>