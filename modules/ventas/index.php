<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null;
$email = $_SESSION['email'] ?? $_COOKIE['email'] ?? null;

if (!$user_id || !$email) {
    header("Location: ../../index.php");
    exit();
}

// Función para obtener las ventas del usuario
function getUserVentas($user_id) {
    global $pdo;
    $query = "SELECT v.*, c.nombre AS cliente_nombre 
              FROM ventas v 
              LEFT JOIN clientes c ON v.cliente_id = c.id 
              WHERE v.user_id = ? 
              ORDER BY v.fecha_venta DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener las ventas
$ventas = getUserVentas($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<div class="sidebar">
        <h2>Menú Principal</h2>
        <ul>
            <li><a href="../../welcome.php">Inicio</a></li>
            <li><a href="../../modules/ventas/index.php">Ventas</a></li>
            <li><a href="../../modules/reportes/index.php">Reportes</a></li>
            <li><a href="../../modules/ingresos/index.php">Ingresos</a></li>
            <li><a href="../../modules/egresos/index.php">Egresos</a></li>
            <li><a href="../../modules/inventario/index.php">Productos</a></li>
            <li><a href="../../modules/clientes/index.php">Clientes</a></li>
            <li><a href="../../modules/proveedores/index.php">Proveedores</a></li>
            <li><a href="../../modules/config/index.php">Configuración</a></li>
            <form method="POST" action="">
                <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
            </form>
        </ul>
    </div>

<div class="main-content">
    <h2>Listado de Ventas</h2>
    <div class="table-container">
        <?php if (count($ventas) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID Venta</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td><?= htmlspecialchars($venta['id']); ?></td>
                            <td><?= htmlspecialchars($venta['fecha_venta']); ?></td>
                            <td><?= htmlspecialchars($venta['cliente_nombre']); ?></td>
                            <td><?= htmlspecialchars(number_format($venta['total'], 2)); ?></td>
                            <td>
                                <button class="btn-imprimir" data-id="<?= $venta['id']; ?>">Imprimir</button>
                                <button class="btn-modificar" data-id="<?= $venta['id']; ?>">Modificar</button>
                                <button class="btn-anular" data-id="<?= $venta['id']; ?>">Anular</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay ventas registradas.</p>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-imprimir').on('click', function() {
        var venta_id = $(this).data('id');
        window.location.href = "imprimir.php?id=" + venta_id;
    });

    $('.btn-modificar').on('click', function() {
        var venta_id = $(this).data('id');
        window.location.href = "editar.php?id=" + venta_id;
    });

    $('.btn-anular').on('click', function() {
        var venta_id = $(this).data('id');
        if (confirm("¿Estás seguro de que deseas anular esta venta?")) {
            $.ajax({
                url: 'anular_venta.php',
                type: 'POST',
                data: { id: venta_id },
                success: function(response) {
                    alert(response);
                    location.reload();
                },
                error: function() {
                    alert("Error al anular la venta.");
                }
            });
        }
    });
});
</script>

</body>
</html>