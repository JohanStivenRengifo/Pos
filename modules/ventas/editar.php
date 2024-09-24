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

// Obtener detalles de la venta
function getVenta($venta_id) {
    global $pdo;
    $query = "SELECT v.*, c.nombre AS cliente_nombre FROM ventas v 
              LEFT JOIN clientes c ON v.cliente_id = c.id 
              WHERE v.id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$venta_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener los productos de la venta
function getProductosVenta($venta_id) {
    global $pdo;
    $query = "
        SELECT i.id, i.nombre, dv.cantidad, dv.precio 
        FROM detalle_venta dv 
        JOIN inventario i ON dv.producto_id = i.id 
        WHERE dv.venta_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$venta_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener la venta y sus productos
$venta = getVenta($venta_id);
$productos = getProductosVenta($venta_id);

if (!$venta) {
    die("Venta no encontrada.");
}

// Obtener clientes
$clientes = $pdo->query("SELECT id, nombre FROM clientes")->fetchAll(PDO::FETCH_ASSOC);

// Manejar el envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $productos_seleccionados = $_POST['productos'];

    // Actualizar la venta
    $stmt = $pdo->prepare("UPDATE ventas SET cliente_id = ? WHERE id = ?");
    $stmt->execute([$cliente_id, $venta_id]);

    // Actualizar detalle de la venta
    foreach ($productos_seleccionados as $producto) {
        $producto_id = $producto['id'];
        $cantidad = $producto['cantidad'];
        $stmt = $pdo->prepare("UPDATE detalle_venta SET cantidad = ? WHERE venta_id = ? AND producto_id = ?");
        $stmt->execute([$cantidad, $venta_id, $producto_id]);
    }

    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Modificar Venta</title>
    <link rel="stylesheet" href="../../css/modulos.css">
</head>
<body>

<h2>Modificar Venta</h2>
<form method="POST">
    <label for="cliente">Cliente:</label>
    <select id="cliente" name="cliente_id" required>
        <?php foreach ($clientes as $cliente): ?>
            <option value="<?= htmlspecialchars($cliente['id']); ?>" <?= $venta['cliente_id'] == $cliente['id'] ? 'selected' : ''; ?>>
                <?= htmlspecialchars($cliente['nombre']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <h3>Productos:</h3>
    <?php foreach ($productos as $producto): ?>
        <div>
            <label><?= htmlspecialchars($producto['nombre']); ?> ($<?= number_format($producto['precio'], 2); ?>):</label>
            <input type="number" name="productos[<?= htmlspecialchars($producto['id']); ?>][cantidad]" min="1" value="<?= htmlspecialchars($producto['cantidad']); ?>" required>
            <input type="hidden" name="productos[<?= htmlspecialchars($producto['id']); ?>][id]" value="<?= htmlspecialchars($producto['id']); ?>">
        </div>
    <?php endforeach; ?>

    <button type="submit">Guardar Cambios</button>
</form>

<a href="index.php">Volver al Listado de Ventas</a>

</body>
</html>
