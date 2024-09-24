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

// Función para obtener todos los ingresos del usuario actual
function getUserIngresos($user_id)
{
    global $pdo;
    $query = "SELECT * FROM ingresos WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para agregar un nuevo ingreso
function addIngreso($user_id, $descripcion, $monto)
{
    global $pdo;
    $query = "INSERT INTO ingresos (user_id, descripcion, monto) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $descripcion, $monto]);
}

// Guardar nuevo ingreso si se envía el formulario
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_ingreso'])) {
    $descripcion = trim($_POST['descripcion']);
    $monto = (float)trim($_POST['monto']);

    // Validar los campos
    if (empty($descripcion) || $monto <= 0) {
        $message = "Por favor, complete todos los campos correctamente.";
    } else {
        // Agregar ingreso
        if (addIngreso($user_id, $descripcion, $monto)) {
            $message = "Ingreso agregado exitosamente.";
        } else {
            $message = "Error al agregar el ingreso.";
        }
    }
}

// Obtener todos los ingresos del usuario
$ingresos = getUserIngresos($user_id);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresos</title>
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
        <h2>Gestionar Ingresos</h2>

        <!-- Mostrar mensaje de éxito o error -->
        <?php if (!empty($message)): ?>
            <div class="message">
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para agregar nuevo ingreso -->
        <div class="form-container">
            <h3>Agregar Nuevo Ingreso</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="descripcion">Descripción:</label>
                    <input type="text" id="descripcion" name="descripcion" required>
                </div>
                <div class="form-group">
                    <label for="monto">Monto:</label>
                    <input type="number" step="0.01" id="monto" name="monto" required>
                </div>
                <button type="submit" name="add_ingreso" class="btn btn-primary">Agregar Ingreso</button>
            </form>
        </div>

        <!-- Mostrar tabla con la lista de ingresos -->
        <div class="table-container">
            <h3>Listado de Ingresos</h3>
            <?php if (count($ingresos) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ingresos as $ingreso): ?>
                            <tr>
                                <td><?= htmlspecialchars($ingreso['descripcion']); ?></td>
                                <td><?= htmlspecialchars($ingreso['monto']); ?></td>
                                <td><?= htmlspecialchars($ingreso['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No hay ingresos registrados.</p>
            <?php endif; ?>
        </div>

    </div>

</body>

</html>