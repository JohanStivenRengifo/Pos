<?php
session_start();

// Verificar si el usuario está logueado
$user_id = '';
$email = '';

if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
    $user_id = $_SESSION['user_id'];
    $email = $_SESSION['email'];
} elseif (isset($_COOKIE['user_id']) && isset($_COOKIE['email'])) {
    $user_id = $_COOKIE['user_id'];
    $email = $_COOKIE['email'];
} else {
    header("Location: index.php");
    exit();
}

// Incluir la configuración de la base de datos
require_once 'config/db.php';

// Funciones para obtener totales
function getTotal($query, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}

$totalIngresos = getTotal("SELECT SUM(monto) FROM ingresos WHERE user_id = ?", $user_id);
$totalEgresos = getTotal("SELECT SUM(monto) FROM egresos WHERE user_id = ?", $user_id);
$totalVentasCompletadas = getTotal("SELECT SUM(total) FROM ventas WHERE user_id = ? AND estado = 'completada'", $user_id);
$totalVentasAnuladas = getTotal("SELECT SUM(total) FROM ventas WHERE user_id = ? AND estado = 'anulada'", $user_id);

// Calcular el balance neto
$gananciasNetas = $totalVentasCompletadas - $totalVentasAnuladas + $totalIngresos - $totalEgresos;

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
    <title>Dashboard - Sistema Contable</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
        }

        .navbar {
            background-color: #3A405A;
            color: white;
            padding: 1rem;
            text-align: center;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            margin: 0;
        }

        .logout-button {
            background-color: #FF6B6B;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        .logout-button:hover {
            background-color: #FF4B4B;
        }

        .welcome-container {
            max-width: 1200px;
            margin: 2rem auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .welcome-container h2 {
            color: #3A405A;
        }

        .user-info {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .card {
            background-color: #e2e2e2;
            padding: 2rem;
            text-align: center;
            border-radius: 10px;
            transition: transform 0.3s, background-color 0.3s;
        }

        .card a {
            text-decoration: none;
            color: #333;
            font-weight: bold;
        }

        .card:hover {
            transform: translateY(-10px);
            background-color: #d1d1d1;
        }

        .footer {
            text-align: center;
            padding: 1rem;
            background-color: #3A405A;
            color: white;
            margin-top: 2rem;
        }
    </style>
</head>
<body>

    <!-- Barra de navegación -->
    <div class="navbar">
        <h1>Sistema Contable</h1>
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
        </form>
    </div>

    <!-- Contenedor de bienvenida -->
    <div class="welcome-container">
        <h2>Bienvenido al Sistema Contable</h2>
        <p class="user-info">Hola, <strong><?= htmlspecialchars($email); ?></strong></p>
        <p>Tu balance actual es: <strong>$<?= number_format($gananciasNetas, 2); ?></strong></p>

        <div class="actions">
            <div class="card">
                <a href="modules/pos/index.php">Realizar Venta</a>
            </div>
            <div class="card">
                <a href="modules/ventas/index.php">Ventas</a>
            </div>
            <div class="card">
                <a href="modules/reportes/index.php">Reportes</a>
            </div>
            <div class="card">
                <a href="modules/ingresos/index.php">Ingresos</a>
            </div>
            <div class="card">
                <a href="modules/egresos/index.php">Egresos</a>
            </div>
            <div class="card">
                <a href="modules/inventario/index.php">Productos</a>
            </div>
            <div class="card">
                <a href="modules/clientes/index.php">Clientes</a>
            </div>
            <div class="card">
                <a href="modules/proveedores/index.php">Proveedores</a>
            </div>
            <div class="card">
                <a href="modules/config/index.php">Configuración</a>
            </div>
        </div>
    </div>

    <!-- Pie de página -->
    <div class="footer">
        <p>&copy; <?= date("Y"); ?> Sistema POS. Todos los derechos reservados.</p>
    </div>

</body>
</html>
