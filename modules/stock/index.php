<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado mediante sesión o cookies
if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
    $user_id = $_SESSION['user_id'];
    $email = $_SESSION['email'];
} elseif (isset($_COOKIE['user_id']) && isset($_COOKIE['email'])) {
    $user_id = $_COOKIE['user_id'];
    $email = $_COOKIE['email'];
} else {
    header("Location: ../../index.php");
    exit();
}

// Función para obtener todos los productos del inventario del usuario actual
function getUserInventario($user_id)
{
    global $pdo;
    $query = "SELECT * FROM inventario WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para agregar nuevo stock a un producto
function addStock($id, $cantidad, $precio)
{
    global $pdo;
    $query = "UPDATE inventario SET cantidad = cantidad + ?, precio = ? WHERE id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$cantidad, $precio, $id]);
}

// Guardar nuevo stock si se envía el formulario
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_stock'])) {
        // Agregar stock
        $id = (int)$_POST['id'];
        $cantidad = (int)trim($_POST['cantidad']);
        $precio = (float)trim($_POST['precio']);

        if ($cantidad <= 0 || $precio <= 0) {
            $message = "Por favor, ingrese una cantidad y precio válidos.";
        } else {
            if (addStock($id, $cantidad, $precio)) {
                $message = "Stock actualizado exitosamente.";
            } else {
                $message = "Error al actualizar el stock.";
            }
        }
    }
}

// Obtener todos los productos del inventario del usuario
$productos = getUserInventario($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar Inventario</title>
    <link rel="stylesheet" href="../../css/modulos.css">
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
    <h2>Actualizar Stock de Productos</h2>

    <?php if (!empty($message)): ?>
        <div class="message"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="table-container">
        <h3>Listado de Productos</h3>
        <?php if (count($productos) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $producto): ?>
                        <tr>
                            <td><?= htmlspecialchars($producto['id']); ?></td>
                            <td><?= htmlspecialchars($producto['nombre']); ?></td>
                            <td><?= htmlspecialchars($producto['descripcion']); ?></td>
                            <td><?= htmlspecialchars($producto['cantidad']); ?></td>
                            <td><?= htmlspecialchars($producto['precio']); ?></td>
                            <td>
                                <button class="btn btn-edit" onclick="editStock(<?= htmlspecialchars($producto['id']); ?>, <?= htmlspecialchars($producto['cantidad']); ?>, <?= htmlspecialchars($producto['precio']); ?>)">Agregar Stock</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay productos registrados en el inventario.</p>
        <?php endif; ?>
    </div>

    <!-- Modal para agregar stock -->
    <div id="stockModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeStockModal()">&times;</span>
            <h3>Agregar Stock</h3>
            <form method="POST" action="">
                <input type="hidden" id="stock_id" name="id">
                <div class="form-group">
                    <label for="stock_cantidad">Cantidad Actual:</label>
                    <input type="number" id="stock_cantidad_actual" readonly>
                </div>
                <div class="form-group">
                    <label for="stock_cantidad">Cantidad a Agregar:</label>
                    <input type="number" id="stock_cantidad" name="cantidad" min="1" required>
                </div>
                <div class="form-group">
                    <label for="stock_precio">Precio Actual:</label>
                    <input type="number" id="stock_precio_actual" readonly>
                </div>
                <div class="form-group">
                    <label for="stock_precio">Nuevo Precio:</label>
                    <input type="number" step="0.01" id="stock_precio" name="precio" required>
                </div>
                <button type="submit" name="add_stock" class="btn btn-primary">Actualizar Stock</button>
            </form>
        </div>
    </div>

</div>

<script>
    function editStock(id, cantidad, precio) {
        document.getElementById('stock_id').value = id;
        document.getElementById('stock_cantidad_actual').value = cantidad;
        document.getElementById('stock_precio_actual').value = precio;
        document.getElementById('stock_cantidad').value = 1; // valor predeterminado para cantidad a agregar
        document.getElementById('stock_precio').value = precio; // valor predeterminado para nuevo precio
        document.getElementById('stockModal').style.display = 'block';
    }

    function closeStockModal() {
        document.getElementById('stockModal').style.display = 'none';
    }
</script>

</body>
</html>