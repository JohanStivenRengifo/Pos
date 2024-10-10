<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: ../../index.php");
    exit();
}

// Función para obtener un producto por su código de barras
function obtenerProductoPorCodigo($codigo_barras)
{
    global $pdo;
    $query = "SELECT * FROM inventario WHERE codigo_barras = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$codigo_barras]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para actualizar un producto
function actualizarProducto($id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $precio_venta, $otro_dato)
{
    global $pdo;

    // Si no se proporciona el precio de venta, calcularlo automáticamente
    if (empty($precio_venta)) {
        $precio_venta = $precio_costo + ($precio_costo * ($impuesto / 100));
        $precio_venta = round($precio_venta, 2);  // Redondear a 2 decimales
    }

    $query = "UPDATE inventario SET codigo_barras = ?, nombre = ?, descripcion = ?, stock = ?, precio_costo = ?, impuesto = ?, precio_venta = ?, otro_dato = ? WHERE id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $precio_venta, $otro_dato, $id]);
}

// Inicialización de variables
$message = '';
$product = null;

// Verificar si se ha pasado un código de barras por la URL
if (isset($_GET['codigo_barras'])) {
    $codigo_barras = $_GET['codigo_barras'];

    // Buscar el producto por código de barras
    $product = obtenerProductoPorCodigo($codigo_barras);

    if (!$product) {
        $message = "Producto no encontrado con el código de barras proporcionado.";
    }

    // Si se ha enviado el formulario para actualizar
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
        $id = $product['id'];
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion']);
        $stock = (int)trim($_POST['stock']);
        $precio_costo = (float)trim($_POST['precio_costo']);
        $impuesto = (float)trim($_POST['impuesto']);
        $precio_venta = isset($_POST['precio_venta']) ? (float)trim($_POST['precio_venta']) : null;
        $otro_dato = trim($_POST['otro_dato']);

        // Validar que los campos estén completos y correctos
        if (empty($nombre) || $stock < 0 || $precio_costo < 0 || $impuesto < 0) {
            $message = "Por favor, complete todos los campos correctamente.";
        } else {
            if (actualizarProducto($id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $precio_venta, $otro_dato)) {
                // Redirigir al index con un mensaje de éxito
                header("Location: index.php?mensaje=producto_actualizado");
                exit();
            } else {
                $message = "Error al actualizar el producto.";
            }
        }
    }
} else {
    $message = "No se ha proporcionado un código de barras.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Producto</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="number"], textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>Menú Principal</h2>
        <ul>
            <li><a href="../../welcome.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="../../modules/ventas/index.php"><i class="fas fa-shopping-cart"></i> Ventas</a></li>
            <li><a href="../../modules/reportes/index.php"><i class="fas fa-chart-bar"></i> Reportes</a></li>
            <li><a href="../../modules/ingresos/index.php"><i class="fas fa-plus-circle"></i> Ingresos</a></li>
            <li><a href="../../modules/egresos/index.php"><i class="fas fa-minus-circle"></i> Egresos</a></li>
            <li><a href="../../modules/inventario/index.php"><i class="fas fa-box"></i> Productos</a></li>
            <li><a href="../../modules/clientes/index.php"><i class="fas fa-users"></i> Clientes</a></li>
            <li><a href="../../modules/proveedores/index.php"><i class="fas fa-truck"></i> Proveedores</a></li>
            <li><a href="../../modules/config/index.php"><i class="fas fa-cog"></i> Configuración</a></li>
            <form method="POST" action="">
                <button type="submit" name="logout" class="logout-button"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</button>
            </form>
        </ul>
    </div>

    <div class="main-content">
        <h2><i class="fas fa-edit"></i> Modificar Producto</h2>

        <?php if (!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($product): ?>
            <form method="POST" action="">
                <input type="hidden" name="codigo_barras" value="<?= htmlspecialchars($product['codigo_barras']); ?>">
                
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
                    <input type="number" id="stock" name="stock" min="0" value="<?= htmlspecialchars($product['stock']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="precio_costo">Precio Costo:</label>
                    <input type="number" step="0.01" id="precio_costo" name="precio_costo" value="<?= htmlspecialchars($product['precio_costo']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="impuesto">Impuesto (%):</label>
                    <input type="number" step="0.01" id="impuesto" name="impuesto" value="<?= htmlspecialchars($product['impuesto']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="precio_venta">Precio Venta:</label>
                    <input type="number" step="0.01" id="precio_venta" name="precio_venta" value="<?= htmlspecialchars($product['precio_venta']); ?>">
                </div>

                <div class="form-group">
                    <label for="otro_dato">Otro Dato:</label>
                    <input type="text" id="otro_dato" name="otro_dato" value="<?= htmlspecialchars($product['otro_dato']); ?>">
                </div>

                <button type="submit" name="update" class="btn btn-success"><i class="fas fa-save"></i> Actualizar Producto</button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>