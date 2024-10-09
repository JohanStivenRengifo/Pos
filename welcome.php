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
function getTotal($query, $params) {
    global $pdo;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchColumn() ?: 0;
}

// Obtener el total de ventas del día actual
$totalVentasDia = getTotal("SELECT SUM(total) FROM ventas WHERE user_id = ? AND DATE(fecha) = CURDATE()", [$user_id]);

// Obtener ventas de los últimos 7 días
$stmt = $pdo->prepare("
    SELECT DATE(fecha) as fecha, SUM(total) as total
    FROM ventas
    WHERE user_id = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha)
    ORDER BY fecha
");
$stmt->execute([$user_id]);
$ventasUltimos7Dias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener totales por método de pago
$stmt = $pdo->prepare("
    SELECT metodo_pago, SUM(total) as total
    FROM ventas
    WHERE user_id = ? AND DATE(fecha) = CURDATE()
    GROUP BY metodo_pago
");
$stmt->execute([$user_id]);
$ventasPorMetodoPago = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <p>Ventas del día: <strong>$<?= number_format($totalVentasDia, 2); ?></strong></p>

        <div class="charts">
            <!-- Gráfico de ventas de los últimos 7 días -->
            <div class="chart-container">
                <canvas id="ventasChart"></canvas>
            </div>

            <!-- Gráfico de ventas por método de pago -->
            <div class="chart-container">
                <canvas id="metodoPagoChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Pie de página -->
    <div class="footer">
        <p>&copy; <?= date("Y"); ?> Sistema POS. Todos los derechos reservados.</p>
    </div>

    <script>
        // Gráfico de ventas de los últimos 7 días
        const ventasChart = new Chart(document.getElementById('ventasChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($ventasUltimos7Dias, 'fecha')); ?>,
                datasets: [{
                    label: 'Ventas ($)',
                    data: <?= json_encode(array_column($ventasUltimos7Dias, 'total')); ?>,
                    borderColor: '#007bff',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Ventas de los últimos 7 días'
                    }
                }
            }
        });

        // Gráfico de ventas por método de pago
        const metodoPagoChart = new Chart(document.getElementById('metodoPagoChart'), {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_column($ventasPorMetodoPago, 'metodo_pago')); ?>,
                datasets: [{
                    data: <?= json_encode(array_column($ventasPorMetodoPago, 'total')); ?>,
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Ventas por método de pago (Hoy)'
                    }
                }
            }
        });
    </script>
</body>
</html>