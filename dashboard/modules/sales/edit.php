<?php
require_once '../../config/database.php';

$id = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente = $_POST['cliente'];
    $producto = $_POST['producto'];
    $cantidad = $_POST['cantidad'];
    $total = $_POST['total'];

    try {
        $stmt = $pdo->prepare("UPDATE ventas SET cliente = ?, producto = ?, cantidad = ?, total = ? WHERE id = ?");
        $stmt->execute([$cliente, $producto, $cantidad, $total, $id]);
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        die("Error al actualizar la venta: " . $e->getMessage());
    }
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id = ?");
        $stmt->execute([$id]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener la venta: " . $e->getMessage());
    }
}
?>

<div class="content">
    <h2>Editar Venta</h2>
    <form method="post">
        <label>Cliente:</label>
        <input type="text" name="cliente" value="<?= htmlspecialchars($venta['cliente'], ENT_QUOTES, 'UTF-8') ?>" required>
        <label>Producto:</label>
        <input type="text" name="producto" value="<?= htmlspecialchars($venta['producto'], ENT_QUOTES, 'UTF-8') ?>" required>
        <label>Cantidad:</label>
        <input type="number" name="cantidad" value="<?= htmlspecialchars($venta['cantidad'], ENT_QUOTES, 'UTF-8') ?>" required>
        <label>Total:</label>
        <input type="number" step="0.01" name="total" value="<?= htmlspecialchars($venta['total'], ENT_QUOTES, 'UTF-8') ?>" required>
        <button type="submit" class="btn btn-primary">Actualizar Venta</button>
    </form>
</div>
