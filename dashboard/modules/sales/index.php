<?php
// Llamado a Base de datos
require_once 'db_connection.php';
$pdo = getPDOConnection();

try {
    $stmt = $pdo->prepare("SELECT * FROM sales ORDER BY sale_date DESC");
    $stmt->execute();
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al realizar la consulta: " . $e->getMessage());
}
?>

<div class="content">
    <h2>Gestión de Ventas</h2>
    <form id="searchSalesForm">
        <input type="text" id="searchQuery" placeholder="Buscar por cliente, producto, etc.">
        <button type="submit">Buscar</button>
    </form>
    <table id="salesTable">
        <thead>
            <tr>
                <th>ID Venta</th>
                <th>Total</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($ventas)): ?>
                <?php foreach ($ventas as $venta): ?>
                    <tr>
                        <td><?= htmlspecialchars($venta['id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= number_format(htmlspecialchars($venta['total'], ENT_QUOTES, 'UTF-8'), 2) ?></td>
                        <td><?= htmlspecialchars($venta['sale_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="./sales/edit.php?id=<?= $venta['id'] ?>" class="btn btn-primary">Editar</a>
                            <a href="./sales/delete.php?id=<?= $venta['id'] ?>" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar esta venta?')">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">No hay ventas registradas.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>