<?php
session_start();

// Variables para el usuario
$user_id = '';
$email = '';

// Verificar si el usuario está logueado mediante sesión o cookies
if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
    $user_id = $_SESSION['user_id'];
    $email = $_SESSION['email'];
} elseif (isset($_COOKIE['user_id']) && isset($_COOKIE['email'])) {
    $user_id = $_COOKIE['user_id'];
    $email = $_COOKIE['email'];
} else {
    // Redirigir al login si no está logueado
    header("Location: index.php");
    exit();
}

// Funciones para obtener ingresos, egresos y ventas
require_once 'config/db.php';

function getTotalIngresos($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(monto) AS total FROM ingresos WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}

function getTotalEgresos($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(monto) AS total FROM egresos WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}

function getTotalVentas($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(total) AS total FROM ventas WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}

// Obtener totales
$totalIngresos = getTotalIngresos($user_id);
$totalEgresos = getTotalEgresos($user_id);
$totalVentas = getTotalVentas($user_id);

// Calcular el balance
$balance = $totalVentas + $totalIngresos - $totalEgresos;

// Cerrar sesión
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    setcookie('user_id', '', time() - 3600, '/');
    setcookie('email', '', time() - 3600, '/');
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido al Sistema POS</title>
    <link rel="stylesheet" href="css/welcome.css">
</head>
<body>

    <!-- Barra de navegación -->
    <div class="navbar">
        <h1>Sistema POS</h1>
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
        </form>
    </div>

    <!-- Contenedor de bienvenida -->
    <div class="welcome-container">
        <div class="content">
            <h2>Bienvenido al Sistema Contable</h2>
            <p class="user-info">Hola <strong><?= htmlspecialchars($email); ?></strong></p>
            <p>Tu balance actual es: <strong>$<?= number_format($balance, 2); ?></strong></p>

            <div class="actions">
                <a href="modules/pos/index.php" class="action-button">Realizar Venta</a>
                <a href="modules/ventas/index.php" class="action-button">Ventas</a>
                <a href="modules/reportes/index.php" class="action-button">Reportes</a>
                <a href="modules/ingresos/index.php" class="action-button">Ingresos</a>
                <a href="modules/egresos/index.php" class="action-button">Egresos</a>
                <a href="modules/inventario/index.php" class="action-button">Productos</a>
                <a href="modules/clientes/index.php" class="action-button">Clientes</a>
                <a href="modules/proveedores/index.php" class="action-button">Proveedores</a>
                <a href="modules/config/index.php" class="action-button">Configuración</a>
            </div>
        </div>
    </div>

    <!-- Pie de página -->
    <div class="footer">
        <p>&copy; <?= date("Y"); ?> Sistema POS. Todos los derechos reservados.</p>
    </div>

</body>
</html>