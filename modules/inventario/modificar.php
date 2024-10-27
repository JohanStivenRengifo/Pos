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
function obtenerProductoPorCodigo($codigo_barras, $user_id)
{
    global $pdo;
    $query = "SELECT * FROM inventario WHERE codigo_barras = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$codigo_barras, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para actualizar un producto
function actualizarProducto($id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $precio_venta, $otro_dato, $user_id)
{
    global $pdo;
    $query = "UPDATE inventario SET codigo_barras = ?, nombre = ?, descripcion = ?, stock = ?, precio_costo = ?, impuesto = ?, precio_venta = ?, otro_dato = ? WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $precio_venta, $otro_dato, $id, $user_id]);
}

$message = '';
$product = null;

// Verificar si se ha pasado un código de barras por la URL
if (isset($_GET['codigo_barras'])) {
    $codigo_barras = $_GET['codigo_barras'];
    $product = obtenerProductoPorCodigo($codigo_barras, $user_id);

    if (!$product) {
        $message = "Producto no encontrado con el código de barras proporcionado.";
    }
}

// Procesar la actualización del producto vía AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $response = ['success' => false, 'message' => ''];

    $id = $_POST['id'];
    $codigo_barras = trim($_POST['codigo_barras']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $stock = (int)trim($_POST['stock']);
    $precio_costo = (float)trim($_POST['precio_costo']);
    $impuesto = (float)trim($_POST['impuesto']);
    $precio_venta = (float)trim($_POST['precio_venta']);
    $otro_dato = trim($_POST['otro_dato']);

    if (empty($nombre) || $stock < 0 || $precio_costo < 0 || $impuesto < 0) {
        $response['message'] = "Por favor, complete todos los campos correctamente.";
    } else {
        if (actualizarProducto($id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $precio_venta, $otro_dato, $user_id)) {
            $response['success'] = true;
            $response['message'] = "Producto actualizado exitosamente.";
        } else {
            $response['message'] = "Error al actualizar el producto.";
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
    <title>Modificar Producto - VendEasy</title>
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
            <h2>Modificar Producto</h2>
            <div class="promo_card">
                <h1>Actualizar Información del Producto</h1>
                <span>Modifique los detalles del producto seleccionado.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-info">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($product): ?>
                <div class="history_lists">
                    <div class="list1">
                        <div class="row">
                            <h4>Formulario de Modificación</h4>
                        </div>
                        <form id="updateProductForm">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($product['id']); ?>">
                            <div class="form-group">
                                <label for="codigo_barras">Código de Barras:</label>
                                <input type="text" id="codigo_barras" name="codigo_barras" value="<?= htmlspecialchars($product['codigo_barras']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="nombre">Nombre del Producto:</label>
                                <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($product['nombre']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="descripcion">Descripción:</label>
                                <textarea id="descripcion" name="descripcion" required><?= htmlspecialchars($product['descripcion']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="stock">Stock:</label>
                                <input type="number" id="stock" name="stock" value="<?= htmlspecialchars($product['stock']); ?>" required min="0">
                            </div>
                            <div class="form-group">
                                <label for="precio_costo">Precio Costo:</label>
                                <input type="number" step="0.01" id="precio_costo" name="precio_costo" value="<?= htmlspecialchars($product['precio_costo']); ?>" required min="0">
                            </div>
                            <div class="form-group">
                                <label for="impuesto">Impuesto (%):</label>
                                <input type="number" step="0.01" id="impuesto" name="impuesto" value="<?= htmlspecialchars($product['impuesto']); ?>" required min="0">
                            </div>
                            <div class="form-group">
                                <label for="precio_venta">Precio Venta:</label>
                                <input type="number" step="0.01" id="precio_venta" name="precio_venta" value="<?= htmlspecialchars($product['precio_venta']); ?>" required min="0">
                            </div>
                            <div class="form-group">
                                <label for="otro_dato">Otro Dato:</label>
                                <input type="text" id="otro_dato" name="otro_dato" value="<?= htmlspecialchars($product['otro_dato']); ?>">
                            </div>
                            <button type="submit" name="update" class="btn btn-primary">Actualizar Producto</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#updateProductForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'modificar.php',
                    type: 'POST',
                    data: $(this).serialize() + '&update=1',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
                                text: response.message,
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'index.php';
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message,
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Hubo un problema al procesar la solicitud.',
                        });
                    }
                });
            });

            // Calcular precio de venta automáticamente
            $('#precio_costo, #impuesto').on('input', function() {
                var precio_costo = parseFloat($('#precio_costo').val()) || 0;
                var impuesto = parseFloat($('#impuesto').val()) || 0;
                var precio_venta = precio_costo + (precio_costo * (impuesto / 100));
                $('#precio_venta').val(precio_venta.toFixed(2));
            });
        });
    </script>
</body>

</html>