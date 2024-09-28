<?php
session_start();
require_once '../../config/db.php';

$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null;
$email = $_SESSION['email'] ?? $_COOKIE['email'] ?? null;

if (!$user_id || !$email) {
    header("Location: ../../index.php");
    exit();
}

function getUserProveedores($user_id)
{
    global $pdo;
    $query = "SELECT * FROM proveedores WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserEgresos($user_id)
{
    global $pdo;
    $query = "SELECT * FROM egresos WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addEgreso($user_id, $numero_factura, $proveedor, $descripcion, $monto, $fecha)
{
    global $pdo;
    $query = "INSERT INTO egresos (user_id, numero_factura, proveedor, descripcion, monto, fecha) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $numero_factura, $proveedor, $descripcion, $monto, $fecha]);
}

function deleteEgreso($id)
{
    global $pdo;
    $query = "DELETE FROM egresos WHERE id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$id]);
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_egreso'])) {
        $numero_factura = trim($_POST['numero_factura']);
        $proveedor = trim($_POST['proveedor']);
        $descripcion = trim($_POST['descripcion']);
        $monto = (float)trim($_POST['monto']);
        $fecha = $_POST['fecha'];

        if (empty($numero_factura) || empty($proveedor) || empty($descripcion) || $monto <= 0 || empty($fecha)) {
            $message = "Por favor, complete todos los campos correctamente.";
        } else {
            if (addEgreso($user_id, $numero_factura, $proveedor, $descripcion, $monto, $fecha)) {
                $message = "Egreso agregado exitosamente.";
            } else {
                $message = "Error al agregar el egreso.";
            }
        }
    } elseif (isset($_POST['delete_egreso'])) {
        $id = (int)$_POST['id'];
        if (deleteEgreso($id)) {
            $message = "Egreso eliminado exitosamente.";
        } else {
            $message = "Error al eliminar el egreso.";
        }
    }
}

$egresos = getUserEgresos($user_id);

function getConfig($user_id) {
    global $pdo;
    $query = "SELECT valor FROM configuracion WHERE user_id = ? AND tipo = 'numero_factura'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$configuracion = getConfig($user_id);
$numero_factura_default = $configuracion['valor'] ?? ''; 
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egresos</title>
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
        <h2>Gestionar Egresos</h2>

        <?php if (!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h3>Agregar Nuevo Egreso</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="numero_factura">Número de Factura:</label>
                    <input type="text" id="numero_factura" name="numero_factura" value="<?= htmlspecialchars($numero_factura_default); ?>" required>
                </div>
                <div class="form-group">
                    <label for="proveedor">Proveedor:</label>
                    <select id="proveedor" name="proveedor" required>
                        <option value="">Seleccione un proveedor</option>
                        <?php
                        // Obtener proveedores y llenarlos en el select
                        $proveedores = getUserProveedores($user_id);
                        foreach ($proveedores as $prov) {
                            echo '<option value="' . htmlspecialchars($prov['nombre']) . '">' . htmlspecialchars($prov['nombre']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="descripcion">Descripción:</label>
                    <textarea id="descripcion" name="descripcion" required></textarea>
                </div>
                <div class="form-group">
                    <label for="monto">Monto:</label>
                    <input type="number" step="0.01" id="monto" name="monto" required>
                </div>
                <div class="form-group">
                    <label for="fecha">Fecha:</label>
                    <input type="date" id="fecha" name="fecha" required>
                </div>
                <button type="submit" name="add_egreso" class="btn btn-primary">Agregar Egreso</button>
            </form>
        </div>

        <div class="table-container">
            <h3>Listado de Egresos</h3>
            <?php if (count($egresos) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Número de Factura</th>
                            <th>Proveedor</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($egresos as $egreso): ?>
                            <tr>
                                <td><?= htmlspecialchars($egreso['id']); ?></td>
                                <td><?= htmlspecialchars($egreso['numero_factura']); ?></td>
                                <td><?= htmlspecialchars($egreso['proveedor']); ?></td>
                                <td><?= htmlspecialchars($egreso['descripcion']); ?></td>
                                <td>$<?= htmlspecialchars(number_format($egreso['monto'], 2)); ?></td>
                                <td><?= htmlspecialchars($egreso['fecha']); ?></td>
                                <td>
                                    <form method="POST" action="" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($egreso['id']); ?>">
                                        <button type="submit" name="delete_egreso" class="btn btn-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay egresos registrados.</p>
            <?php endif; ?>
        </div>

    </div>

</body>

</html>
