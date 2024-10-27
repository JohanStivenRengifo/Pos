<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

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
    <title>Actualizar Inventario - VendEasy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
</head>
<body>
    <header class="header">
        <div class="logo">
            <a href="../../welcome.php">VendEasy</a>
        </div>
        <div class="header-icons">
            <i class="fas fa-bell"></i>
            <div class="account">
                <h4><?= htmlspecialchars($email) ?></h4>
            </div>
        </div>
    </header>
    <div class="container">
        <nav>
        <div class="side_navbar">
                <span>Menú Principal</span>
                <a href="/welcome.php">Dashboard</a>
                <a href="/modules/pos/index.php">Punto de Venta</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php" class="active">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="#">Ayuda</a>
                    <a href="#">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Actualizar Stock de Productos</h2>
            <div class="promo_card">
                <h1>Gestión de Inventario</h1>
                <span>Aquí puedes actualizar el stock de tus productos.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?= strpos($message, 'exitosamente') !== false ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Listado de Productos</h4>
                    </div>
                    <table>
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
                                        <button class="btn-edit" onclick="editStock(<?= htmlspecialchars(json_encode($producto)); ?>)">
                                            <i class="fas fa-edit"></i> Agregar Stock
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para agregar stock -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Agregar Stock</h3>
            <form id="stockForm" method="POST" action="">
                <input type="hidden" id="stock_id" name="id">
                <div class="form-group">
                    <label for="stock_cantidad_actual">Cantidad Actual:</label>
                    <input type="number" id="stock_cantidad_actual" readonly>
                </div>
                <div class="form-group">
                    <label for="stock_cantidad">Cantidad a Agregar:</label>
                    <input type="number" id="stock_cantidad" name="cantidad" min="1" required>
                </div>
                <div class="form-group">
                    <label for="stock_precio_actual">Precio Actual:</label>
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

    <script>
    function editStock(producto) {
        document.getElementById('stock_id').value = producto.id;
        document.getElementById('stock_cantidad_actual').value = producto.cantidad;
        document.getElementById('stock_precio_actual').value = producto.precio;
        document.getElementById('stock_cantidad').value = 1;
        document.getElementById('stock_precio').value = producto.precio;
        document.getElementById('stockModal').style.display = 'block';
    }

    // Cerrar el modal
    document.querySelector('.close').onclick = function() {
        document.getElementById('stockModal').style.display = 'none';
    }

    // Cerrar el modal si se hace clic fuera de él
    window.onclick = function(event) {
        if (event.target == document.getElementById('stockModal')) {
            document.getElementById('stockModal').style.display = 'none';
        }
    }

    // Manejar el envío del formulario
    document.getElementById('stockForm').onsubmit = function(e) {
        e.preventDefault();
        // Aquí puedes agregar validación adicional si lo deseas
        this.submit();
    }
    </script>
</body>
</html>

</body>
</html>