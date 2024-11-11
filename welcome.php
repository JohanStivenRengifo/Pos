<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Incluir la configuración de la base de datos
require_once 'config/db.php';

// Funciones para obtener totales
function getTotal($query, $params = [])
{
    global $pdo;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchColumn() ?: 0;
}

// Obtener datos del usuario
$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

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

// Obtener las últimas 5 ventas
$stmt = $pdo->prepare("
    SELECT v.id, v.fecha, v.total, c.nombre as cliente
    FROM ventas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    WHERE v.user_id = ?
    ORDER BY v.fecha DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$ultimasVentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener la información de la empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = (SELECT empresa_id FROM users WHERE id = ?)");
$stmt->execute([$user_id]);
$empresa_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Cerrar sesión
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
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
    <link rel="icon" type="image/png" href="favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="css/welcome.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <header class="header">
        <div class="logo">
            <a href="#">VendEasy</a>
        </div>

        <div class="header-icons">
            <i class="fas fa-bell"></i>
            <div class="account">
                <h4><?= htmlspecialchars($email) ?></h4>
            </div>
        </div>
    </header>
    <div class="container">
        <nav>
            <div class="side_navbar">
                <span>Menú Principal</span>
                <a href="#" class="active">Dashboard</a>
                <a href="/modules/pos/index.php">Punto de Venta</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="/ayuda.php">Ayuda</a>
                    <a href="/contacto.php">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Dashboard</h2>
            <div class="promo_card">
                <h1>Bienvenido a VendEasy</h1>
                <span>Gestiona tus ventas y finanzas de manera eficiente.</span><br>
                <span>Nombre de la Empresa:<b> <?= htmlspecialchars($empresa_info['nombre_empresa'] ?? 'No configurado'); ?></b></span>
                <button onclick="location.href='modules/pos/index.php'">Realizar Venta</button>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Últimas Ventas</h4>
                        <a href="modules/ventas/index.php">Ver todas</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimasVentas as $venta): ?>
                                <tr>
                                    <td><?= $venta['id'] ?></td>
                                    <td><?= $venta['fecha'] ?></td>
                                    <td><?= htmlspecialchars($venta['cliente']) ?></td>
                                    <td>$<?= number_format($venta['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="list2">
                    <div class="row">
                        <h4>Ventas por Método de Pago</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventasPorMetodoPago as $metodo): ?>
                                <tr>
                                    <td><?= htmlspecialchars($metodo['metodo_pago']) ?></td>
                                    <td>$<?= number_format($metodo['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="sidebar">
            <h4>Resumen de Ventas</h4>

            <div class="balance">
                <i class="fas fa-dollar-sign icon"></i>
                <div class="info">
                    <h5>Ventas del Día</h5>
                    <span>$<?= number_format($totalVentasDia, 2) ?></span>
                </div>
            </div>

            <div class="balance">
                <i class="fas fa-chart-line icon"></i>
                <div class="info">
                    <h5>Ventas de la Semana</h5>
                    <span>$<?= number_format(array_sum(array_column($ventasUltimos7Dias, 'total')), 2) ?></span>
                </div>
            </div>

            <h4>Gráfico de Ventas</h4>
            <canvas id="ventasChart"></canvas>

            <form method="POST" action="">
                <button type="submit" name="logout" class="logout-btn">Cerrar Sesión</button>
            </form>
        </div>
    </div>

    <script>
        // Gráfico de ventas de los últimos 7 días
        const ctx = document.getElementById('ventasChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($ventasUltimos7Dias, 'fecha')) ?>,
                datasets: [{
                    label: 'Ventas ($)',
                    data: <?= json_encode(array_column($ventasUltimos7Dias, 'total')) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
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
    </script>
</body>

</html>
