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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Librería para gráficos -->
    <link rel="stylesheet" href="css/welcome.css">
</head>
<body>

    <!-- Barra de navegación -->
    <div class="navbar">
        <h1>Sistema Contable</h1>
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
        </form>
    </div>

    <!-- Barra lateral -->
    <div class="sidebar">
        <h3>Menú</h3>
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

    <!-- Contenido principal -->
    <div class="content">
        <h2>Bienvenido al Sistema Contable</h2>
        <p>Hola, <strong><?= htmlspecialchars($email); ?></strong></p>
        <p>Tu balance actual es: <strong>$<?= number_format($gananciasNetas, 2); ?></strong></p>

        <div class="charts">
            <!-- Gráfico de ingresos vs egresos -->
            <div class="chart-container">
                <canvas id="balanceChart"></canvas>
            </div>

            <!-- Gráfico de ventas completadas vs anuladas -->
            <div class="chart-container">
                <canvas id="ventasChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Pie de página -->
    <div class="footer">
        <p>&copy; <?= date("Y"); ?> Sistema POS. Todos los derechos reservados.</p>
    </div>

    <script>
        // Gráfico de balance (Ingresos vs Egresos)
        const balanceChart = new Chart(document.getElementById('balanceChart'), {
            type: 'bar',
            data: {
                labels: ['Ingresos', 'Egresos'],
                datasets: [{
                    label: 'Monto ($)',
                    data: [<?= $totalIngresos; ?>, <?= $totalEgresos; ?>],
                    backgroundColor: ['#4CAF50', '#FF6B6B'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Gráfico de ventas (Completadas vs Anuladas)
        const ventasChart = new Chart(document.getElementById('ventasChart'), {
            type: 'pie',
            data: {
                labels: ['Ventas Completadas', 'Ventas Anuladas'],
                datasets: [{
                    label: 'Ventas ($)',
                    data: [<?= $totalVentasCompletadas; ?>, <?= $totalVentasAnuladas; ?>],
                    backgroundColor: ['#007bff', '#FF6B6B'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
            }
        });
    </script>
</body>
</html>