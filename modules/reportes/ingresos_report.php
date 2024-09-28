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

// Función para obtener los ingresos por mes
function getIngresosPorMes($user_id) {
    global $pdo;
    $query = "SELECT MONTH(created_at) AS mes, SUM(monto) AS total_ingresos 
              FROM ingresos 
              WHERE user_id = ? 
              GROUP BY MONTH(created_at) 
              ORDER BY mes";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener ingresos
$ingresos = getIngresosPorMes($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ingresos</title>
    <link rel="stylesheet" href="../../css/modulos.css">
</head>
<body>

<div class="sidebar">
    <h2>Menú Principal</h2>
    <ul>
        <li><a href="../../welcome.php">Inicio</a></li>
        <li><a href="../ventas/index.php">Ventas</a></li>
        <li><a href="ingresos_report.php">Reporte de Ingresos</a></li>
        <li><a href="egresos_report.php">Reporte de Egresos</a></li>
        <li><a href="balance_report.php">Balance General</a></li>
        <li><a href="impuestos_report.php">Reporte de Impuestos</a></li>
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
        </form>
    </ul>
</div>

<div class="main-content">
    <h2>Reporte de Ingresos</h2>
    <div class="table-container">
        <?php if (count($ingresos) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Total Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ingresos as $ingreso): ?>
                        <tr>
                            <td><?= htmlspecialchars($ingreso['mes']); ?></td>
                            <td><?= htmlspecialchars(number_format($ingreso['total_ingresos'], 2)); ?></td>
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