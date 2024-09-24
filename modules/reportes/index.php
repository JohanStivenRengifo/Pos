<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Funciones para obtener datos de reportes
require_once '../../config/db.php';

function getReporteVentas($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT fecha_venta, SUM(total) AS total FROM ventas WHERE user_id = ? GROUP BY fecha_venta ORDER BY fecha_venta");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalIngresos($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(monto) AS total FROM ingresos WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}

function getTotalEgresos($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT SUM(monto) AS total FROM egresos WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}

function getDeudas($user_id) {
    global $pdo;
    // Se obtiene la suma de todos los egresos del usuario
    $stmt = $pdo->prepare("SELECT SUM(monto) AS total FROM egresos WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 0;
}

// Obtener datos para gráficas y balances
$user_id = $_SESSION['user_id'];
$reporteVentas = getReporteVentas($user_id);
$totalIngresos = getTotalIngresos($user_id);
$totalEgresos = getTotalEgresos($user_id);
$totalDeudas = getDeudas($user_id);

$fechas = [];
$totales = [];

foreach ($reporteVentas as $venta) {
    $fechas[] = $venta['fecha_venta'];
    $totales[] = (float)$venta['total'];
}

// Manejar la descarga de CSV
if (isset($_POST['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reporte_ventas.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Fecha', 'Total']);
    foreach ($reporteVentas as $venta) {
        fputcsv($output, [$venta['fecha_venta'], $venta['total']]);
    }
    fclose($output);
    exit();
}

// Calcular balance
$balance = $totalIngresos - $totalEgresos;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes</title>
    <link rel="stylesheet" href="../../css/reportes.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="navbar">
    <h1>Reportes</h1>
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
</div>

<div class="content">
    <h2>Reporte de Ventas</h2>

    <canvas id="ventasChart"></canvas>

    <h3>Totales</h3>
    <p>Total Ingresos: <strong>$<?= number_format($totalIngresos, 2); ?></strong></p>
    <p>Total Egresos: <strong>$<?= number_format($totalEgresos, 2); ?></strong></p>
    <p>Total Deudas: <strong>$<?= number_format($totalDeudas, 2); ?></strong></p>
    <p>Balance Actual: <strong>$<?= number_format($balance, 2); ?></strong></p>

    <form method="POST" action="">
        <button type="submit" name="export_csv" class="export-button">Exportar a CSV</button>
    </form>
</div>

<script>
const ctx = document.getElementById('ventasChart').getContext('2d');
const ventasChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($fechas); ?>,
        datasets: [{
            label: 'Total Ventas',
            data: <?= json_encode($totales); ?>,
            borderColor: 'rgba(75, 192, 192, 1)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
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
</script>

</body>
</html>