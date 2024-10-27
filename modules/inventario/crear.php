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

function obtenerCategorias()
{
    global $pdo;
    $query = "SELECT id, nombre FROM categorias";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerDepartamentos()
{
    global $pdo;
    $query = "SELECT id, nombre FROM departamentos";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function codigoBarrasExistente($codigo_barras)
{
    global $pdo;
    $query = "SELECT COUNT(*) FROM inventario WHERE codigo_barras = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$codigo_barras]);
    return $stmt->fetchColumn() > 0;
}

function agregarProducto($user_id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $otro_dato, $categoria_id, $departamento_id)
{
    global $pdo;
    $precio_venta = $precio_costo + ($precio_costo * ($impuesto / 100));
    $precio_venta = round($precio_venta);

    $query = "INSERT INTO inventario (user_id, codigo_barras, nombre, descripcion, stock, precio_costo, impuesto, precio_venta, otro_dato, categoria_id, departamento_id, fecha_ingreso) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $precio_venta, $otro_dato, $categoria_id, $departamento_id]);
}

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

    if (empty($codigo_barras) || empty($nombre) || $stock <= 0 || $precio_costo <= 0 || $impuesto < 0 || $categoria_id == 0 || $departamento_id == 0) {
        $message = "Por favor, complete todos los campos obligatorios correctamente.";
    } elseif (codigoBarrasExistente($codigo_barras)) {
        $message = "El código de barras ya está en uso. Por favor, ingrese uno diferente.";
    } else {
        if (agregarProducto($user_id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $otro_dato, $categoria_id, $departamento_id)) {
            $message = "Producto agregado exitosamente.";
        } else {
            $message = "Error al agregar el producto.";
        }
    }
}

$categorias = obtenerCategorias();
$departamentos = obtenerDepartamentos();

function formatoMoneda($monto)
{
    return '$' . number_format($monto, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Producto - VendEasy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
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
            <h2>Crear Nuevo Producto</h2>
            <div class="promo_card">
                <h1>Agregar Producto al Inventario</h1>
                <span>Ingrese los detalles del nuevo producto.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?= strpos($message, 'exitosamente') !== false ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Formulario de Nuevo Producto</h4>
                    </div>
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

            <div class="form-group">
                <label for="precio_venta">Precio Venta:</label>
                <input type="number" step="0.01" id="precio_venta" name="precio_venta" readonly>
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
            </div>
        </div>
    </div>

    <script>
        // Aquí iría el script para calcular el precio de venta
    </script>
</body>

</html>