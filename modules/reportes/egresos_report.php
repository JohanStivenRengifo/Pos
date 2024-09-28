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

// Función para obtener los egresos por mes
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

// Obtener los egresos por mes
$egresosPorMes = getEgresosPorMes($user_id);
$meses = array_column($egresosPorMes, 'mes');
$totales = array_column($egresosPorMes, 'total_egresos');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Egresos</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="sidebar">
    <h2>Menú Principal</h2>
    <ul>
        <li><a href="../../welcome.php">Inicio</a></li>
        <li><a href="../../modules/reportes/index.php">Reportes</a></li>
        <li><a href="egresos_report.php">Reporte de Egresos</a></li>
        <li><a href="ingresos_report.php">Reporte de Ingresos</a></li>
        <li><a href="balance_report.php">Balance General</a></li>
        <li><a href="impuestos_report.php">Reporte de Impuestos</a></li>
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
        </form>
    </ul>
</div>

<div class="main-content">
    <h2>Reporte de Egresos</h2>
    <canvas id="graficaEgresos"></canvas>
</div>

<script>
var ctx = document.getElementById('graficaEgresos').getContext('2d');
var graficaEgresos = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($meses); ?>,
        datasets: [{
            label: 'Egresos por Mes',
            data: <?= json_encode($totales); ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
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
</script>

</body>
</html>
