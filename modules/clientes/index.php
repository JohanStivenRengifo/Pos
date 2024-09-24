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
    // Redirigir al login si no está logueado
    header("Location: ../../index.php");
    exit();
}

// Función para obtener los clientes del usuario
function getClientes($user_id) {
    global $pdo;
    $query = "SELECT * FROM clientes WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para agregar un cliente
function addCliente($user_id, $nombre, $email, $telefono) {
    global $pdo;
    $query = "INSERT INTO clientes (user_id, nombre, email, telefono) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $nombre, $email, $telefono]);
}

// Función para eliminar un cliente
function deleteCliente($cliente_id, $user_id) {
    global $pdo;
    $query = "DELETE FROM clientes WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$cliente_id, $user_id]);
}

// Función para obtener proveedores del usuario
function getProveedores($user_id) {
    global $pdo;
    $query = "SELECT * FROM proveedores WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Guardar un nuevo cliente
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_cliente'])) {
    $nombre = trim($_POST['nombre']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $telefono = trim($_POST['telefono']);

    if (empty($nombre) || empty($email) || empty($telefono)) {
        $message = "Por favor complete todos los campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ingrese un correo electrónico válido.";
    } else {
        if (addCliente($user_id, $nombre, $email, $telefono)) {
            $message = "Cliente agregado exitosamente.";
        } else {
            $message = "Error al agregar cliente.";
        }
    }
}

// Eliminar un cliente
if (isset($_POST['delete_cliente'])) {
    $cliente_id = (int)$_POST['cliente_id'];
    if (deleteCliente($cliente_id, $user_id)) {
        $message = "Cliente eliminado correctamente.";
    } else {
        $message = "Error al eliminar cliente.";
    }
}

// Obtener la lista de clientes y proveedores del usuario
$clientes = getClientes($user_id);
$proveedores = getProveedores($user_id);
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

    <!-- Mostrar mensaje -->
    <?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Formulario para agregar nuevo cliente -->
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
        <button type="submit" name="add_cliente" class="btn-primary">Agregar Cliente</button>
    </form>

    <!-- Listado de clientes -->
    <h3>Lista de Clientes</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Teléfono</th>
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

    <!-- Enlace a la sección de proveedores -->
    <h3>Proveedores</h3>
    <p><a href="../proveedores/index.php">Ir a la gestión de proveedores</a></p>
</div>

</body>
</html>
