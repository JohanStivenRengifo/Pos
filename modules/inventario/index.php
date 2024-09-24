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

// Función para agregar un nuevo producto al inventario
function addProducto($user_id, $nombre, $descripcion, $cantidad, $precio)
{
    global $pdo;
    $query = "INSERT INTO inventario (user_id, nombre, descripcion, cantidad, precio) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $nombre, $descripcion, $cantidad, $precio]);
}

// Función para actualizar un producto
function updateProducto($id, $nombre, $descripcion, $cantidad, $precio)
{
    global $pdo;
    $query = "UPDATE inventario SET nombre = ?, descripcion = ?, cantidad = ?, precio = ? WHERE id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$nombre, $descripcion, $cantidad, $precio, $id]);
}

// Función para eliminar un producto
function deleteProducto($id)
{
    global $pdo;
    $query = "DELETE FROM inventario WHERE id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$id]);
}

// Guardar nuevo producto si se envía el formulario
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_producto'])) {
        // Agregar producto
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $cantidad = (int)trim($_POST['cantidad']);
        $precio = (float)trim($_POST['precio']);

        if (empty($nombre) || empty($descripcion) || $cantidad <= 0 || $precio <= 0) {
            $message = "Por favor, complete todos los campos correctamente.";
        } else {
            if (addProducto($user_id, $nombre, $descripcion, $cantidad, $precio)) {
                $message = "Producto agregado exitosamente.";
            } else {
                $message = "Error al agregar el producto.";
            }
        }
    } elseif (isset($_POST['update_producto'])) {
        // Actualizar producto
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $cantidad = (int)trim($_POST['cantidad']);
        $precio = (float)trim($_POST['precio']);

        if (updateProducto($id, $nombre, $descripcion, $cantidad, $precio)) {
            $message = "Producto actualizado exitosamente.";
        } else {
            $message = "Error al actualizar el producto.";
        }
    } elseif (isset($_POST['delete_producto'])) {
        // Eliminar producto
        $id = (int)$_POST['id'];
        if (deleteProducto($id)) {
            $message = "Producto eliminado exitosamente.";
        } else {
            $message = "Error al eliminar el producto.";
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
    <title>Inventario</title>
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
        <h2>Gestionar Inventario</h2>

        <a href="../stock/index.php">Agregar Stock</a>
        <?php if (!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h3>Agregar Nuevo Producto</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="nombre">Nombre del Producto:</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                <div class="form-group">
                    <label for="descripcion">Descripción:</label>
                    <textarea id="descripcion" name="descripcion" required></textarea>
                </div>
                <div class="form-group">
                    <label for="cantidad">Cantidad:</label>
                    <input type="number" id="cantidad" name="cantidad" min="1" required>
                </div>
                <div class="form-group">
                    <label for="precio">Precio:</label>
                    <input type="number" step="0.01" id="precio" name="precio" required>
                </div>
                <button type="submit" name="add_producto" class="btn btn-primary">Agregar Producto</button>
            </form>
        </div>

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
                                    <form method="POST" action="" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($producto['id']); ?>">
                                        <button type="submit" name="delete_producto" class="btn btn-danger">Eliminar</button>
                                    </form>
                                    <button class="btn btn-edit" onclick="editProducto(<?= htmlspecialchars($producto['id']); ?>, '<?= htmlspecialchars($producto['nombre']); ?>', '<?= htmlspecialchars($producto['descripcion']); ?>', <?= htmlspecialchars($producto['cantidad']); ?>, <?= htmlspecialchars($producto['precio']); ?>)">Editar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay productos registrados en el inventario.</p>
            <?php endif; ?>
        </div>

        <!-- Modal para editar producto -->
        <div id="editModal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h3>Editar Producto</h3>
                <form method="POST" action="">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label for="edit_nombre">Nombre del Producto:</label>
                        <input type="text" id="edit_nombre" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_descripcion">Descripción:</label>
                        <textarea id="edit_descripcion" name="descripcion" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_cantidad">Cantidad:</label>
                        <input type="number" id="edit_cantidad" name="cantidad" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_precio">Precio:</label>
                        <input type="number" step="0.01" id="edit_precio" name="precio" required>
                    </div>
                    <button type="submit" name="update_producto" class="btn btn-primary">Actualizar Producto</button>
                </form>
            </div>
        </div>

    </div>

    <script>
        function editProducto(id, nombre, descripcion, cantidad, precio) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_descripcion').value = descripcion;
            document.getElementById('edit_cantidad').value = cantidad;
            document.getElementById('edit_precio').value = precio;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>

</body>

</html>