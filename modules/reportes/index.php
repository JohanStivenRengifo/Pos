<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Clase para manejar respuestas JSON
class ApiResponse {
    public static function send($status, $message, $data = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Función para obtener datos de ventas netas por mes con manejo de errores
function getVentasNetasPorMes($user_id) {
    global $pdo;
    try {
        $query = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, SUM(total - descuento) AS total_ventas 
                  FROM ventas 
                  WHERE user_id = ? 
                  GROUP BY DATE_FORMAT(fecha, '%Y-%m') 
                  ORDER BY mes";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return ['status' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener datos de ventas: ' . $e->getMessage()];
    }
}

// Función para obtener datos de ventas por método de pago con manejo de errores
function getVentasPorMetodoPago($user_id) {
    global $pdo;
    try {
        $query = "SELECT metodo_pago, SUM(total - descuento) AS total_ventas 
                  FROM ventas 
                  WHERE user_id = ? 
                  GROUP BY metodo_pago";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return ['status' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener datos de métodos de pago: ' . $e->getMessage()];
    }
}

// Función para obtener las últimas ventas con manejo de errores
function getUltimasVentas($user_id, $limit = 5) {
    global $pdo;
    try {
        $query = "SELECT v.id, v.fecha, v.total, v.descuento, v.metodo_pago, v.numero_factura, c.nombre AS cliente_nombre
                  FROM ventas v
                  LEFT JOIN clientes c ON v.cliente_id = c.id
                  WHERE v.user_id = :user_id
                  ORDER BY v.fecha DESC
                  LIMIT :limit";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return ['status' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener últimas ventas: ' . $e->getMessage()];
    }
}

// Obtener datos para gráficos y tablas con manejo de errores
$ventasNetasResult = getVentasNetasPorMes($user_id);
$ventasPorMetodoPagoResult = getVentasPorMetodoPago($user_id);
$ultimasVentasResult = getUltimasVentas($user_id);

// Verificar si hubo errores en alguna consulta
$errors = [];
if (!$ventasNetasResult['status']) $errors[] = $ventasNetasResult['message'];
if (!$ventasPorMetodoPagoResult['status']) $errors[] = $ventasPorMetodoPagoResult['message'];
if (!$ultimasVentasResult['status']) $errors[] = $ultimasVentasResult['message'];

// Extraer datos si no hubo errores
$ventasNetasPorMes = $ventasNetasResult['status'] ? $ventasNetasResult['data'] : [];
$ventasPorMetodoPago = $ventasPorMetodoPagoResult['status'] ? $ventasPorMetodoPagoResult['data'] : [];
$ultimasVentas = $ultimasVentasResult['status'] ? $ultimasVentasResult['data'] : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <header class="header">
        <div class="logo">
            <a href="../../welcome.php">VendEasy</a>
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
                <a href="/welcome.php">Dashboard</a>
                <a href="/modules/pos/index.php">POS</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php" class="active">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="#">Ayuda</a>
                    <a href="#">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Módulo de Reportes</h2>
            <div class="promo_card">
                <h1>Análisis de Ventas</h1>
                <span>Visualiza y analiza tus datos de ventas.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Ventas Netas por Mes</h4>
                    </div>
                    <canvas id="ventasNetasChart"></canvas>
                </div>
                <div class="list2">
                    <div class="row">
                        <h4>Ventas por Método de Pago</h4>
                    </div>
                    <canvas id="metodoPagoChart"></canvas>
                </div>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Últimas Ventas</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Descuento</th>
                                <th>Método de Pago</th>
                                <th>Número de Factura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimasVentas as $venta): ?>
                                <tr>
                                    <td><?= htmlspecialchars($venta['id']) ?></td>
                                    <td><?= htmlspecialchars($venta['fecha']) ?></td>
                                    <td><?= htmlspecialchars($venta['cliente_nombre'] ?? 'N/A') ?></td>
                                    <td>$<?= number_format($venta['total'], 2) ?></td>
                                    <td>$<?= number_format($venta['descuento'], 2) ?></td>
                                    <td><?= htmlspecialchars($venta['metodo_pago']) ?></td>
                                    <td><?= htmlspecialchars($venta['numero_factura']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Configuración global de notificaciones
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // Función para mostrar notificaciones
    function showNotification(type, message) {
        Toast.fire({
            icon: type,
            title: message
        });
    }

    // Función para mostrar errores
    function showError(title, message) {
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonText: 'Entendido'
        });
    }

    // Mostrar errores si existen
    <?php if (!empty($errors)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Error al cargar datos',
                html: '<?= implode("<br>", $errors) ?>',
                confirmButtonText: 'Entendido'
            });
        });
    <?php endif; ?>

    // Gráfico de Ventas Netas por Mes con manejo de errores
    const ctxVentasNetas = document.getElementById('ventasNetasChart').getContext('2d');
    try {
        new Chart(ctxVentasNetas, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($ventasNetasPorMes, 'mes')) ?>,
                datasets: [{
                    label: 'Ventas Netas ($)',
                    data: <?= json_encode(array_column($ventasNetasPorMes, 'total_ventas')) ?>,
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
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Ventas: $' + new Intl.NumberFormat().format(context.raw);
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        showError('Error en el gráfico', 'No se pudo cargar el gráfico de ventas netas');
    }

    // Gráfico de Ventas por Método de Pago con manejo de errores
    const ctxMetodoPago = document.getElementById('metodoPagoChart').getContext('2d');
    try {
        new Chart(ctxMetodoPago, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_column($ventasPorMetodoPago, 'metodo_pago')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($ventasPorMetodoPago, 'total_ventas')) ?>,
                    backgroundColor: [
                        'rgb(255, 99, 132)',
                        'rgb(54, 162, 235)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': $' + new Intl.NumberFormat().format(context.raw);
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        showError('Error en el gráfico', 'No se pudo cargar el gráfico de métodos de pago');
    }

    // Estilo personalizado para SweetAlert2
    const style = document.createElement('style');
    style.textContent = `
        .swal2-popup {
            font-family: 'Poppins', sans-serif;
            border-radius: 12px;
        }
        .swal2-title {
            color: #344767;
        }
        .swal2-html-container {
            color: #495057;
        }
        .swal2-confirm {
            background: linear-gradient(145deg, #007bff, #0056b3) !important;
        }
        .swal2-cancel {
            background: linear-gradient(145deg, #6c757d, #495057) !important;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>
