<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Obtener el ID del producto si se pasa por la URL
$producto_id = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : null;

// Función para obtener el Kardex del producto
function obtenerKardex($producto_id)
{
    global $pdo;
    $query = "
        SELECT k.*, p.nombre AS producto_nombre
        FROM kardex k
        JOIN inventario p ON k.producto_id = p.id
        WHERE k.producto_id = ?
        ORDER BY k.fecha DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$producto_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener la lista de productos
function obtenerProductos($user_id)
{
    global $pdo;
    $query = "SELECT id, nombre FROM inventario WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener el Kardex si se selecciona un producto
$kardex = [];
if ($producto_id) {
    $kardex = obtenerKardex($producto_id);
}

// Obtener la lista de productos para seleccionar
$productos = obtenerProductos($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kardex</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha384-k6RqeWeci5ZR/Lv4MR0sA0FfDOM5ch3kFccf/dD4Hp/v5/a48Kt7E3/qErQAwz2" crossorigin="anonymous">
</head>

<body>
    <div class="sidebar">
        <h2>Menú Principal</h2>
        <ul>
            <li><a href="../../welcome.php">Inicio</a></li>
            <li><a href="../../modules/ventas/index.php">Ventas</a></li>
            <li><a href="../../modules/reportes/index.php">Reportes</a></li>
            <li><a href="../../modules/inventario/index.php">Productos</a></li>
            <li><a href="../../modules/clientes/index.php">Clientes</a></li>
            <li><a href="../../modules/proveedores/index.php">Proveedores</a></li>
            <li><a href="../../modules/config/index.php">Configuración</a></li>
            <li>
                <form method="POST" action="">
                    <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
                </form>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <h2>Kardex de Productos</h2>
        <form method="GET" action="">
            <label for="producto_id">Seleccionar Producto:</label>
            <select name="producto_id" id="producto_id" onchange="this.form.submit()">
                <option value="">-- Seleccionar Producto --</option>
                <?php foreach ($productos as $producto): ?>
                    <option value="<?= $producto['id']; ?>" <?= ($producto['id'] == $producto_id) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($producto['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($producto_id): ?>
            <h3>Kardex del Producto: <?= htmlspecialchars($kardex[0]['producto_nombre']); ?></h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Descripción del Movimiento</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Nuevo Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($kardex) > 0): ?>
                            <?php foreach ($kardex as $movimiento): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($movimiento['fecha'])); ?></td>
                                    <td><?= htmlspecialchars($movimiento['descripcion']); ?></td>
                                    <td><?= htmlspecialchars($movimiento['cantidad']); ?></td>
                                    <td><?= '$ ' . number_format($movimiento['precio_unitario'], 2, ',', '.'); ?></td>
                                    <td><?= htmlspecialchars($movimiento['nuevo_stock']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay movimientos registrados para este producto.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
