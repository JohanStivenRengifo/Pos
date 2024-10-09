<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null;
$email = $_SESSION['email'] ?? $_COOKIE['email'] ?? null;

if (!$user_id || !$email) {
    header("Location: ../../index.php");
    exit();
}

// Función para obtener datos de ventas netas por mes
function getVentasNetasPorMes($user_id) {
    global $pdo;
    $query = "SELECT MONTH(fecha) AS mes, SUM(total) AS total_ventas 
              FROM ventas 
              WHERE user_id = ? 
              GROUP BY MONTH(fecha) 
              ORDER BY mes";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Función para obtener datos de egresos por mes
function getEgresosPorMes($user_id) {
    global $pdo;
    $query = "SELECT MONTH(fecha) AS mes, SUM(monto) AS total_egresos 
              FROM egresos 
              WHERE user_id = ? 
              GROUP BY MONTH(fecha) 
              ORDER BY mes";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener datos de devoluciones (anuladas) por mes
function getDevolucionesPorMes($user_id) {
    global $pdo;
    $query = "SELECT MONTH(fecha) AS mes, SUM(total) AS total_devoluciones 
              FROM ventas 
              WHERE user_id = ? 
              GROUP BY MONTH(fecha) 
              ORDER BY mes";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



// Obtener datos para gráficos
$ventas_data = getVentasNetasPorMes($user_id);
$egresos_data = getEgresosPorMes($user_id);
$devoluciones_data = getDevolucionesPorMes($user_id);

$meses = [];
$ventas_totales = [];
$egresos_totales = [];
$devoluciones_totales = [];
$impuestos_totales = [];

for ($i = 1; $i <= 12; $i++) {
    $meses[] = $i;
    $ventas_totales[] = 0;
    $egresos_totales[] = 0;
    $devoluciones_totales[] = 0;
    $impuestos_totales[] = 0;
}

// Rellenar los datos obtenidos
foreach ($ventas_data as $data) {
    $ventas_totales[$data['mes'] - 1] = $data['total_ventas'];
}

foreach ($egresos_data as $data) {
    $egresos_totales[$data['mes'] - 1] = $data['total_egresos'];
}

foreach ($devoluciones_data as $data) {
    $devoluciones_totales[$data['mes'] - 1] = $data['total_devoluciones'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <h2>Módulo de Reportes</h2>
    <div class="report-options">
        <h3>Seleccione el tipo de reporte:</h3>
        <ul>
            <li><a href="ventas_report.php">Reporte de Ventas</a></li>
            <li><a href="egresos_report.php">Reporte de Egresos</a></li>
            <li><a href="ingresos_report.php">Reporte de Ingresos</a></li>
            <li><a href="balance_report.php">Balance General</a></li>
        </ul>
    </div>

    <div class="chart-container">
        <h3>Gráfico de Ventas Netas por Mes</h3>
        <canvas id="ventasChart" width="400" height="200"></canvas>
        <h3>Gráfico de Egresos por Mes</h3>
        <canvas id="egresosChart" width="400" height="200"></canvas>
        <h3>Gráfico de Devoluciones por Mes</h3>
        <canvas id="devolucionesChart" width="400" height="200"></canvas>
    </div>

</div>

<script>
$(document).ready(function() {
    var ctxVentas = document.getElementById('ventasChart').getContext('2d');
    var ventasChart = new Chart(ctxVentas, {
        type: 'bar',
        data: {
            labels: <?= json_encode($meses) ?>,
            datasets: [{
                label: 'Total Ventas Netas',
                data: <?= json_encode($ventas_totales) ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    var ctxEgresos = document.getElementById('egresosChart').getContext('2d');
    var egresosChart = new Chart(ctxEgresos, {
        type: 'bar',
        data: {
            labels: <?= json_encode($meses) ?>,
            datasets: [{
                label: 'Total Egresos',
                data: <?= json_encode($egresos_totales) ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    var ctxDevoluciones = document.getElementById('devolucionesChart').getContext('2d');
    var devolucionesChart = new Chart(ctxDevoluciones, {
        type: 'bar',
        data: {
            labels: <?= json_encode($meses) ?>,
            datasets: [{
                label: 'Total Devoluciones (Anuladas)',
                data: <?= json_encode($devoluciones_totales) ?>,
                backgroundColor: 'rgba(255, 206, 86, 0.6)',
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

</body>
</html>
