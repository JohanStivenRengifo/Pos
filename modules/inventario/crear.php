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

function obtenerCategorias() {
    global $pdo;
    $query = "SELECT id, nombre FROM categorias"; // Sin WHERE user_id
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerDepartamentos() {
    global $pdo;
    $query = "SELECT id, nombre FROM departamentos"; // Sin WHERE user_id
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para agregar un nuevo producto al inventario
function agregarProducto($user_id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $otro_dato, $categoria_id, $departamento_id) {
    global $pdo;

    // Calcular el precio de venta basado en el impuesto y redondear
    $precio_venta = $precio_costo + ($precio_costo * ($impuesto / 100));
    $precio_venta = round($precio_venta);  // Redondear al entero más cercano

    $query = "INSERT INTO inventario (user_id, codigo_barras, nombre, descripcion, stock, precio_costo, impuesto, precio_venta, otro_dato, categoria_id, departamento_id, fecha_ingreso) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $precio_venta, $otro_dato, $categoria_id, $departamento_id]);
}

// Inicialización de variables
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $codigo_barras = trim($_POST['codigo_barras']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $stock = (int)trim($_POST['stock']);
    $precio_costo = (float)trim($_POST['precio_costo']);
    $impuesto = (float)trim($_POST['impuesto']);
    $otro_dato = trim($_POST['otro_dato']);
    $categoria_id = (int)$_POST['categoria'];
    $departamento_id = (int)$_POST['departamento'];

    // Validación de campos requeridos
    if (empty($codigo_barras) || empty($nombre) || $stock <= 0 || $precio_costo <= 0 || $impuesto < 0 || $categoria_id == 0 || $departamento_id == 0) {
        $message = "Por favor, complete todos los campos obligatorios correctamente.";
    } else {
        // Intentar agregar el producto al inventario
        if (agregarProducto($user_id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $otro_dato, $categoria_id, $departamento_id)) {
            $message = "Producto agregado exitosamente.";
        } else {
            $message = "Error al agregar el producto.";
        }
    }
}

// Obtener las categorías y departamentos para mostrarlas en el formulario
$categorias = obtenerCategorias($user_id);
$departamentos = obtenerDepartamentos($user_id);

// Formato de moneda
function formatoMoneda($monto) {
    return '$' . number_format($monto, 2, ',', '.'); // Cambia el formato según sea necesario
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Producto</title>
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
        <h2>Crear Nuevo Producto</h2>

        <!-- Mensaje de éxito o error -->
        <?php if (!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Formulario para agregar un nuevo producto -->
        <form method="POST" action="">
            <div class="form-group">
                <label for="codigo_barras">Código de Barras:</label>
                <input type="text" id="codigo_barras" name="codigo_barras" required>
            </div>

            <div class="form-group">
                <label for="nombre">Nombre del Producto:</label>
                <input type="text" id="nombre" name="nombre" required>
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción:</label>
                <textarea id="descripcion" name="descripcion" required></textarea>
            </div>

            <div class="form-group">
                <label for="stock">Stock:</label>
                <input type="number" id="stock" name="stock" min="1" required>
            </div>

            <div class="form-group">
                <label for="precio_costo">Precio Costo:</label>
                <input type="number" step="0.01" id="precio_costo" name="precio_costo" required>
            </div>

            <div class="form-group">
                <label for="impuesto">Impuesto (%):</label>
                <input type="number" step="0.01" id="impuesto" name="impuesto" required>
            </div>

            <!-- Listado de Categorías -->
            <div class="form-group">
                <label for="categoria">Categoría:</label>
                <select id="categoria" name="categoria" required>
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= htmlspecialchars($categoria['id']); ?>"><?= htmlspecialchars($categoria['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Listado de Departamentos -->
            <div class="form-group">
                <label for="departamento">Departamento:</label>
                <select id="departamento" name="departamento" required>
                    <option value="">Selecciona un departamento</option>
                    <?php foreach ($departamentos as $departamento): ?>
                        <option value="<?= htmlspecialchars($departamento['id']); ?>"><?= htmlspecialchars($departamento['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="otro_dato">Otro Dato:</label>
                <input type="text" id="otro_dato" name="otro_dato">
            </div>

            <button type="submit" class="btn btn-success">Agregar Producto</button>
        </form>
    </div>

</body>
</html>
