<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Obtener el ID de la venta
$venta_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Función para obtener los detalles de la venta
function getVenta($venta_id)
{
    global $pdo;
    $query = "
        SELECT v.*, c.nombre AS cliente_nombre 
        FROM ventas v 
        LEFT JOIN clientes c ON v.cliente_id = c.id 
        WHERE v.id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$venta_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para obtener los detalles de los productos vendidos
function getProductosVenta($venta_id)
{
    global $pdo;
    $query = "
        SELECT i.nombre, dv.cantidad, dv.precio
        FROM detalle_venta dv
        JOIN inventario i ON dv.producto_id = i.id
        WHERE dv.venta_id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$venta_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener los detalles de la venta
$venta = getVenta($venta_id);

if (!$venta) {
    die("Venta no encontrada.");
}

// Obtener los detalles de los productos vendidos
$productos = getProductosVenta($venta_id);
$totalVenta = array_reduce($productos, function($carry, $producto) {
    return $carry + ($producto['cantidad'] * $producto['precio']);
}, 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Venta</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            width: 300px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 10px;
        }
        .producto {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .total {
            font-weight: bold;
            margin-top: 20px;
            text-align: right;
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</head>
<body>

<h2>Tirilla de Venta</h2>
<p><strong>ID Venta:</strong> <?= htmlspecialchars($venta['id']); ?></p>
<p><strong>Fecha:</strong> <?= htmlspecialchars($venta['fecha_venta']); ?></p>
<p><strong>Cliente:</strong> <?= htmlspecialchars($venta['cliente_nombre'] ?: 'No asignado'); ?></p>

<h3>Productos:</h3>
<?php if (count($productos) > 0): ?>
    <?php foreach ($productos as $producto): ?>
        <div class="producto">
            <div><?= htmlspecialchars($producto['nombre']); ?> (x<?= htmlspecialchars($producto['cantidad']); ?>)</div>
            <div>$<?= number_format($producto['cantidad'] * $producto['precio'], 2); ?></div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>No hay productos en esta venta.</p>
<?php endif; ?>

<p class="total">Total: $<?= number_format($totalVenta, 2); ?></p>

</body>
</html>