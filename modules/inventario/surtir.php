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

// Función para obtener un producto por su código de barras
function obtenerProducto($codigo_barras, $user_id)
{
    global $pdo;
    $query = "SELECT * FROM inventario WHERE codigo_barras = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$codigo_barras, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para actualizar el stock de un producto
function actualizarStock($id, $nueva_cantidad, $nuevo_precio_costo, $nuevo_precio_venta, $nuevo_impuesto, $nueva_descripcion, $user_id)
{
    global $pdo;
    $query = "UPDATE inventario 
              SET stock = stock + ?, precio_costo = ?, precio_venta = ?, impuesto = ?, descripcion = ? 
              WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$nueva_cantidad, $nuevo_precio_costo, $nuevo_precio_venta, $nuevo_impuesto, $nueva_descripcion, $id, $user_id]);
}

// Procesar solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    if ($_POST['action'] === 'buscar_producto') {
        $codigo_barras = $_POST['codigo_barras'];
        $producto = obtenerProducto($codigo_barras, $user_id);
        if ($producto) {
            $response['success'] = true;
            $response['producto'] = $producto;
        } else {
            $response['message'] = "Producto no encontrado.";
        }
    } elseif ($_POST['action'] === 'actualizar_stock') {
        $id = $_POST['id'];
        $nueva_cantidad = (int)$_POST['nueva_cantidad'];
        $nuevo_precio_costo = (float)$_POST['nuevo_precio_costo'];
        $nuevo_precio_venta = (float)$_POST['nuevo_precio_venta'];
        $nuevo_impuesto = (float)$_POST['nuevo_impuesto'];
        $nueva_descripcion = $_POST['nueva_descripcion'];

        if (actualizarStock($id, $nueva_cantidad, $nuevo_precio_costo, $nuevo_precio_venta, $nuevo_impuesto, $nueva_descripcion, $user_id)) {
            $response['success'] = true;
            $response['message'] = "Stock actualizado correctamente.";
        } else {
            $response['message'] = "Error al actualizar el stock.";
        }
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surtir Inventario - VendEasy</title>
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
                <a href="#" class="active">Dashboard</a>
                <a href="/modules/pos/index.php">Punto de Venta</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
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
            <h2>Surtir Inventario</h2>
            <div class="promo_card">
                <h1>Actualizar Stock de Productos</h1>
                <span>Ingrese el código de barras y la cantidad a agregar.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Buscar Producto</h4>
                    </div>
                    <form id="buscarProductoForm">
                        <div class="form-group">
                            <label for="codigo_barras">Código de Barras:</label>
                            <input type="text" id="codigo_barras" name="codigo_barras" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Buscar Producto</button>
                    </form>
                </div>

                <div class="list1" id="actualizarStockForm" style="display: none;">
                    <div class="row">
                        <h4>Actualizar Stock</h4>
                    </div>
                    <form id="stockForm">
                        <input type="hidden" id="producto_id" name="id">
                        <div class="form-group">
                            <label for="nombre_producto">Nombre del Producto:</label>
                            <input type="text" id="nombre_producto" readonly>
                        </div>
                        <div class="form-group">
                            <label for="stock_actual">Stock Actual:</label>
                            <input type="number" id="stock_actual" readonly>
                        </div>
                        <div class="form-group">
                            <label for="nueva_cantidad">Cantidad a Agregar:</label>
                            <input type="number" id="nueva_cantidad" name="nueva_cantidad" required>
                        </div>
                        <div class="form-group">
                            <label for="nuevo_precio_costo">Nuevo Precio Costo:</label>
                            <input type="number" step="0.01" id="nuevo_precio_costo" name="nuevo_precio_costo" required>
                        </div>
                        <div class="form-group">
                            <label for="nuevo_precio_venta">Nuevo Precio Venta:</label>
                            <input type="number" step="0.01" id="nuevo_precio_venta" name="nuevo_precio_venta" required>
                        </div>
                        <div class="form-group">
                            <label for="nuevo_impuesto">Nuevo Impuesto (%):</label>
                            <input type="number" step="0.01" id="nuevo_impuesto" name="nuevo_impuesto" required>
                        </div>
                        <div class="form-group">
                            <label for="nueva_descripcion">Nueva Descripción:</label>
                            <textarea id="nueva_descripcion" name="nueva_descripcion"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Actualizar Stock</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#buscarProductoForm').on('submit', function(e) {
                e.preventDefault();
                const codigo_barras = $('#codigo_barras').val();

                $.ajax({
                    url: 'surtir.php',
                    method: 'POST',
                    data: {
                        action: 'buscar_producto',
                        codigo_barras: codigo_barras
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const producto = response.producto;
                            $('#producto_id').val(producto.id);
                            $('#nombre_producto').val(producto.nombre);
                            $('#stock_actual').val(producto.stock);
                            $('#nuevo_precio_costo').val(producto.precio_costo);
                            $('#nuevo_precio_venta').val(producto.precio_venta);
                            $('#nuevo_impuesto').val(producto.impuesto);
                            $('#nueva_descripcion').val(producto.descripcion);
                            $('#actualizarStockForm').show();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Hubo un problema al buscar el producto.', 'error');
                    }
                });
            });

            $('#stockForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();

                $.ajax({
                    url: 'surtir.php',
                    method: 'POST',
                    data: formData + '&action=actualizar_stock',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Éxito', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Hubo un problema al actualizar el stock.', 'error');
                    }
                });
            });
        });
    </script>
</body>

</html>