<?php
require_once '../../config/database.php';

$id = $_GET['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("DELETE FROM ventas WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        die("Error al eliminar la venta: " . $e->getMessage());
    }
}
?>

<div class="content">
    <h2>Eliminar Venta</h2>
    <p>¿Estás seguro de que deseas eliminar esta venta?</p>
    <form method="post">
        <button type="submit" class="btn btn-danger">Eliminar</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>