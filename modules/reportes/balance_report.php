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

// Función para obtener el total de ingresos
function getTotalIngresos($user_id) {
    global $pdo;
    $query = "SELECT SUM(monto) AS total_ingresos FROM ingresos WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Función para obtener el total de egresos
function getTotalEgresos($user_id) {
    global $pdo;
    $query = "SELECT SUM(monto) AS total_egresos FROM egresos WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Función para obtener el total de ventas
function getTotalVentas($user_id) {
    global $pdo;
    $query = "SELECT SUM(total) AS total_ventas FROM ventas WHERE user_id = ? AND estado = 'completada'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Obtener totales
$total_ingresos = getTotalIngresos($user_id);
$total_egresos = getTotalEgresos($user_id);
$total_ventas = getTotalVentas($user_id);

// Calcular el balance
$balance = ($total_ingresos + $total_ventas) - $total_egresos;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance General</title>
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
    <h2>Balance General</h2>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Ingresos</td>
                    <td><?= htmlspecialchars(number_format($total_ingresos, 2)); ?></td>
                </tr>
                <tr>
                    <td>Total Egresos</td>
                    <td><?= htmlspecialchars(number_format($total_egresos, 2)); ?></td>
                </tr>
                <tr>
                    <td>Total Ventas</td>
                    <td><?= htmlspecialchars(number_format($total_ventas, 2)); ?></td>
                </tr>
                <tr>
                    <td><strong>Balance</strong></td>
                    <td><strong><?= htmlspecialchars(number_format($balance, 2)); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
