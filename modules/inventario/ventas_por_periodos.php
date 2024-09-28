<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$ventas = [];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];

    $query = "SELECT * FROM ventas WHERE fecha_venta BETWEEN ? AND ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$fecha_inicio, $fecha_fin, $_SESSION['user_id']]);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$ventas) {
        $mensaje = "No se encontraron ventas en este periodo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ventas por Periodos</title>
    <link rel="stylesheet" href="../../css/modulos.css">
</head>
<body>

<div class="main-content">
    <h2>Ventas por Periodos</h2>

    <form method="post" action="">
        <label for="fecha_inicio">Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" required>
        <label for="fecha_fin">Fecha de Fin:</label>
        <input type="date" name="fecha_fin" required>
        <button type="submit">Consultar Ventas</button>
    </form>

    <?php if ($mensaje): ?>
        <p><?= htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>

    <?php if ($ventas): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ventas as $venta): ?>
                    <tr>
                        <td><?= htmlspecialchars($venta['id']); ?></td>
                        <td><?= htmlspecialchars($venta['cliente_id']); ?></td>
                        <td><?= htmlspecialchars($venta['fecha_venta']); ?></td>
                        <td><?= htmlspecialchars($venta['total']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
