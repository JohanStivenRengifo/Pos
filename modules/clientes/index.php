<?php
session_start();
require_once '../../config/db.php'; 

// Verificar sesión
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

// Funciones para manejar clientes
function getClientes($user_id) {
    global $pdo;
    $query = "SELECT * FROM clientes WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCliente($user_id, $nombre, $email, $telefono, $tipo_identificacion, $identificacion, $primer_nombre, $segundo_nombre, $apellidos, $municipio_departamento, $codigo_postal) {
    global $pdo;
    $query = "INSERT INTO clientes (user_id, nombre, email, telefono, tipo_identificacion, identificacion, primer_nombre, segundo_nombre, apellidos, municipio_departamento, codigo_postal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $nombre, $email, $telefono, $tipo_identificacion, $identificacion, $primer_nombre, $segundo_nombre, $apellidos, $municipio_departamento, $codigo_postal]);
}

function deleteCliente($cliente_id, $user_id) {
    global $pdo;
    $query = "DELETE FROM clientes WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$cliente_id, $user_id]);
}

// Mensajes
$message = '';

// Procesar formulario para agregar cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_cliente'])) {
    $nombre = trim($_POST['nombre']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $telefono = trim($_POST['telefono']);
    $tipo_identificacion = trim($_POST['tipo_identificacion']);
    $identificacion = trim($_POST['identificacion']);
    $primer_nombre = trim($_POST['primer_nombre']);
    $segundo_nombre = trim($_POST['segundo_nombre']);
    $apellidos = trim($_POST['apellidos']);
    $municipio_departamento = trim($_POST['municipio_departamento']);
    $codigo_postal = trim($_POST['codigo_postal']);

    // Validación
    if (empty($nombre) || empty($email) || empty($telefono) || empty($tipo_identificacion) || empty($identificacion)) {
        $message = "Por favor complete todos los campos obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ingrese un correo electrónico válido.";
    } else {
        if (addCliente($user_id, $nombre, $email, $telefono, $tipo_identificacion, $identificacion, $primer_nombre, $segundo_nombre, $apellidos, $municipio_departamento, $codigo_postal)) {
            $message = "Cliente agregado exitosamente.";
        } else {
            $message = "Error al agregar cliente.";
        }
    }
}

// Procesar eliminación de cliente
if (isset($_POST['delete_cliente'])) {
    $cliente_id = (int)$_POST['cliente_id'];
    if (deleteCliente($cliente_id, $user_id)) {
        $message = "Cliente eliminado correctamente.";
    } else {
        $message = "Error al eliminar cliente.";
    }
}

$clientes = getClientes($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes</title>
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
    <h2>Clientes</h2>

    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="nombre">Nombre del Cliente:</label>
            <input type="text" id="nombre" name="nombre" required>
        </div>
        <div class="form-group">
            <label for="email">Correo Electrónico:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="telefono">Teléfono:</label>
            <input type="text" id="telefono" name="telefono" required>
        </div>
        <div class="form-group">
            <label for="tipo_identificacion">Tipo de Identificación:</label>
            <input type="text" id="tipo_identificacion" name="tipo_identificacion" required>
        </div>
        <div class="form-group">
            <label for="identificacion">Número de Identificación:</label>
            <input type="text" id="identificacion" name="identificacion" required>
        </div>
        <div class="form-group">
            <label for="primer_nombre">Primer Nombre:</label>
            <input type="text" id="primer_nombre" name="primer_nombre" required>
        </div>
        <div class="form-group">
            <label for="segundo_nombre">Segundo Nombre:</label>
            <input type="text" id="segundo_nombre" name="segundo_nombre">
        </div>
        <div class="form-group">
            <label for="apellidos">Apellidos:</label>
            <input type="text" id="apellidos" name="apellidos" required>
        </div>
        <div class="form-group">
            <label for="municipio_departamento">Municipio/Departamento:</label>
            <input type="text" id="municipio_departamento" name="municipio_departamento" required>
        </div>
        <div class="form-group">
            <label for="codigo_postal">Código Postal:</label>
            <input type="text" id="codigo_postal" name="codigo_postal" required>
        </div>
        <button type="submit" name="add_cliente" class="btn-primary">Agregar Cliente</button>
    </form>

    <h3>Lista de Clientes</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>IDentificación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($clientes): ?>
                <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td><?= htmlspecialchars($cliente['nombre']); ?></td>
                        <td><?= htmlspecialchars($cliente['email']); ?></td>
                        <td><?= htmlspecialchars($cliente['telefono']); ?></td>
                        <td><?= htmlspecialchars($cliente['identificacion']); ?></td>
                        <td>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="cliente_id" value="<?= $cliente['id']; ?>">
                                <button type="submit" name="delete_cliente" class="btn-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No tienes clientes registrados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h3>Proveedores</h3>
    <p><a href="../proveedores/index.php">Ir a la gestión de proveedores</a></p>
</div>

</body>
</html>