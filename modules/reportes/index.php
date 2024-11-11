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

// Nueva función para obtener el balance general
function getBalanceGeneral($user_id) {
    global $pdo;
    try {
        // Obtener total de ventas
        $queryVentas = "SELECT COALESCE(SUM(total - descuento), 0) as total_ventas 
                        FROM ventas 
                        WHERE user_id = ?";
        $stmtVentas = $pdo->prepare($queryVentas);
        $stmtVentas->execute([$user_id]);
        $totalVentas = $stmtVentas->fetch(PDO::FETCH_ASSOC)['total_ventas'];

        // Obtener total de ingresos adicionales
        $queryIngresos = "SELECT COALESCE(SUM(monto), 0) as total_ingresos 
                         FROM ingresos 
                         WHERE user_id = ?";
        $stmtIngresos = $pdo->prepare($queryIngresos);
        $stmtIngresos->execute([$user_id]);
        $totalIngresos = $stmtIngresos->fetch(PDO::FETCH_ASSOC)['total_ingresos'];

        // Obtener total de egresos
        $queryEgresos = "SELECT COALESCE(SUM(monto), 0) as total_egresos 
                        FROM egresos 
                        WHERE user_id = ?";
        $stmtEgresos = $pdo->prepare($queryEgresos);
        $stmtEgresos->execute([$user_id]);
        $totalEgresos = $stmtEgresos->fetch(PDO::FETCH_ASSOC)['total_egresos'];

        $balance = ($totalVentas + $totalIngresos) - $totalEgresos;

        return [
            'status' => true,
            'data' => [
                'total_ventas' => $totalVentas,
                'total_ingresos' => $totalIngresos,
                'total_egresos' => $totalEgresos,
                'balance' => $balance
            ]
        ];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener balance: ' . $e->getMessage()];
    }
}

// Nueva función para obtener productos más vendidos
function getProductosMasVendidos($user_id, $limit = 5) {
    global $pdo;
    try {
        $query = "SELECT p.nombre, SUM(dv.cantidad) as total_vendido, 
                         SUM(dv.cantidad * dv.precio_unitario) as total_ingresos
                  FROM detalle_ventas dv
                  JOIN productos p ON dv.producto_id = p.id
                  JOIN ventas v ON dv.venta_id = v.id
                  WHERE v.user_id = ?
                  GROUP BY p.id, p.nombre
                  ORDER BY total_vendido DESC
                  LIMIT ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $limit]);
        return ['status' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener productos más vendidos: ' . $e->getMessage()];
    }
}

// Nueva función para obtener estadísticas de clientes
function getEstadisticasClientes($user_id) {
    global $pdo;
    try {
        $query = "SELECT 
                    COUNT(DISTINCT c.id) as total_clientes,
                    COUNT(DISTINCT v.cliente_id) as clientes_activos,
                    (SELECT c2.nombre 
                     FROM clientes c2 
                     JOIN ventas v2 ON v2.cliente_id = c2.id 
                     WHERE v2.user_id = ? 
                     GROUP BY c2.id 
                     ORDER BY SUM(v2.total) DESC 
                     LIMIT 1) as mejor_cliente,
                    (SELECT SUM(v3.total) 
                     FROM ventas v3 
                     WHERE v3.user_id = ? 
                     AND v3.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as ventas_mes_actual
                  FROM clientes c
                  LEFT JOIN ventas v ON v.cliente_id = c.id
                  WHERE c.user_id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $user_id, $user_id]);
        return ['status' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener estadísticas de clientes: ' . $e->getMessage()];
    }
}

// Nueva función para obtener tendencias de ventas
function getTendenciasVentas($user_id) {
    global $pdo;
    try {
        $query = "SELECT 
                    DATE_FORMAT(fecha, '%H:00') as hora,
                    COUNT(*) as total_ventas,
                    COALESCE(SUM(total - descuento), 0) as monto_total
                  FROM ventas 
                  WHERE user_id = ? 
                  AND fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
                  GROUP BY DATE_FORMAT(fecha, '%H:00')
                  ORDER BY hora ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        
        // Crear array con todas las horas del día
        $horasCompletas = array_fill(0, 24, [
            'hora' => '',
            'total_ventas' => 0,
            'monto_total' => 0
        ]);
        
        // Llenar con datos reales
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($resultados as $row) {
            $hora = intval(substr($row['hora'], 0, 2));
            $horasCompletas[$hora] = $row;
        }
        
        // Formatear horas faltantes
        for ($i = 0; $i < 24; $i++) {
            if (empty($horasCompletas[$i]['hora'])) {
                $horasCompletas[$i]['hora'] = sprintf("%02d:00", $i);
                $horasCompletas[$i]['total_ventas'] = 0;
                $horasCompletas[$i]['monto_total'] = 0;
            }
        }
        
        return ['status' => true, 'data' => array_values($horasCompletas)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener tendencias: ' . $e->getMessage()];
    }
}

// Nueva función para obtener KPIs
function getKPIs($user_id) {
    global $pdo;
    try {
        $query = "SELECT 
                    AVG(total) as ticket_promedio,
                    COUNT(*)/30 as ventas_diarias,
                    SUM(total)/30 as ingreso_diario,
                    COUNT(DISTINCT cliente_id) as clientes_unicos
                  FROM ventas 
                  WHERE user_id = ? 
                  AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return ['status' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener KPIs: ' . $e->getMessage()];
    }
}

// Nueva función para obtener flujo de caja
function getFlujoCaja($user_id) {
    global $pdo;
    try {
        $query = "SELECT 
                    DATE_FORMAT(fecha, '%Y-%m') as mes,
                    SUM(CASE 
                        WHEN tipo = 'ingreso' THEN monto 
                        ELSE -monto 
                    END) as flujo
                  FROM (
                    SELECT fecha, total as monto, 'ingreso' as tipo 
                    FROM ventas 
                    WHERE user_id = ?
                    UNION ALL
                    SELECT fecha, monto, 'ingreso' as tipo 
                    FROM ingresos 
                    WHERE user_id = ?
                    UNION ALL
                    SELECT fecha, monto, 'egreso' as tipo 
                    FROM egresos 
                    WHERE user_id = ?
                  ) as movimientos
                  GROUP BY DATE_FORMAT(fecha, '%Y-%m')
                  ORDER BY mes DESC
                  LIMIT 12";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id, $user_id, $user_id]);
        return ['status' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener flujo de caja: ' . $e->getMessage()];
    }
}

// Nueva función para obtener desglose de gastos por categoría
function getGastosPorCategoria($user_id) {
    global $pdo;
    try {
        $query = "SELECT 
                    categoria,
                    COUNT(*) as numero_transacciones,
                    SUM(monto) as total_gastos
                  FROM egresos 
                  WHERE user_id = ? 
                  AND fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                  GROUP BY categoria
                  ORDER BY total_gastos DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return ['status' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener gastos por categoría: ' . $e->getMessage()];
    }
}

// Nueva función para obtener comparativa mensual
function getComparativaMensual($user_id) {
    global $pdo;
    try {
        $query = "SELECT 
                    DATE_FORMAT(fecha, '%Y-%m') as mes,
                    SUM(total - descuento) as ventas,
                    COUNT(*) as num_ventas,
                    COUNT(DISTINCT cliente_id) as num_clientes
                  FROM ventas 
                  WHERE user_id = ?
                  AND fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(fecha, '%Y-%m')
                  ORDER BY mes DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return ['status' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error al obtener comparativa mensual: ' . $e->getMessage()];
    }
}

// Obtener datos para gráficos y tablas con manejo de errores
$ventasNetasResult = getVentasNetasPorMes($user_id);
$ventasPorMetodoPagoResult = getVentasPorMetodoPago($user_id);
$ultimasVentasResult = getUltimasVentas($user_id);
$balanceResult = getBalanceGeneral($user_id);
$productosMasVendidosResult = getProductosMasVendidos($user_id);

// Verificar si hubo errores en alguna consulta
$errors = [];
if (!$ventasNetasResult['status']) $errors[] = $ventasNetasResult['message'];
if (!$ventasPorMetodoPagoResult['status']) $errors[] = $ventasPorMetodoPagoResult['message'];
if (!$ultimasVentasResult['status']) $errors[] = $ultimasVentasResult['message'];
if (!$balanceResult['status']) $errors[] = $balanceResult['message'];
if (!$productosMasVendidosResult['status']) $errors[] = $productosMasVendidosResult['message'];

// Extraer datos si no hubo errores
$ventasNetasPorMes = $ventasNetasResult['status'] ? $ventasNetasResult['data'] : [];
$ventasPorMetodoPago = $ventasPorMetodoPagoResult['status'] ? $ventasPorMetodoPagoResult['data'] : [];
$ultimasVentas = $ultimasVentasResult['status'] ? $ultimasVentasResult['data'] : [];
$balanceGeneral = $balanceResult['status'] ? $balanceResult['data'] : [];
$productosMasVendidos = $productosMasVendidosResult['status'] ? $productosMasVendidosResult['data'] : [];

// Obtener los nuevos datos
$estadisticasClientesResult = getEstadisticasClientes($user_id);
$tendenciasVentasResult = getTendenciasVentas($user_id);
$kpisResult = getKPIs($user_id);

// Obtener datos adicionales
$flujoCajaResult = getFlujoCaja($user_id);
$gastosPorCategoriaResult = getGastosPorCategoria($user_id);
$comparativaMensualResult = getComparativaMensual($user_id);
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
                    <a href="/ayuda.php">Ayuda</a>
                    <a href="/contacto.php">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Panel de Reportes</h2>
            
            <!-- KPIs Principales -->
            <div class="kpi-container">
                <div class="kpi-card">
                    <i class="fas fa-receipt"></i>
                    <div class="kpi-content">
                        <h3>Ticket Promedio</h3>
                        <p>$<?= number_format($kpisResult['data']['ticket_promedio'], 2) ?></p>
                    </div>
                </div>
                <div class="kpi-card">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="kpi-content">
                        <h3>Ventas Diarias</h3>
                        <p><?= round($kpisResult['data']['ventas_diarias'], 1) ?></p>
                    </div>
                </div>
                <div class="kpi-card">
                    <i class="fas fa-users"></i>
                    <div class="kpi-content">
                        <h3>Clientes Únicos</h3>
                        <p><?= $kpisResult['data']['clientes_unicos'] ?></p>
                    </div>
                </div>
                <div class="kpi-card">
                    <i class="fas fa-dollar-sign"></i>
                    <div class="kpi-content">
                        <h3>Ingreso Diario</h3>
                        <p>$<?= number_format($kpisResult['data']['ingreso_diario'], 2) ?></p>
                    </div>
                </div>
            </div>

            <!-- Balance General Mejorado -->
            <div class="balance-section">
                <h3>Balance General</h3>
                <div class="balance-cards">
                    <div class="balance-card income">
                        <div class="balance-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="balance-details">
                            <h4>Ingresos Totales</h4>
                            <p>$<?= number_format($balanceGeneral['total_ventas'] + $balanceGeneral['total_ingresos'], 2) ?></p>
                            <div class="balance-breakdown">
                                <span>Ventas: $<?= number_format($balanceGeneral['total_ventas'], 2) ?></span>
                                <span>Otros: $<?= number_format($balanceGeneral['total_ingresos'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="balance-card expenses">
                        <div class="balance-icon">
                            <i class="fas fa-minus-circle"></i>
                        </div>
                        <div class="balance-details">
                            <h4>Egresos Totales</h4>
                            <p>$<?= number_format($balanceGeneral['total_egresos'], 2) ?></p>
                        </div>
                    </div>
                    <div class="balance-card total">
                        <div class="balance-icon">
                            <i class="fas fa-equals"></i>
                        </div>
                        <div class="balance-details">
                            <h4>Balance Final</h4>
                            <p class="<?= $balanceGeneral['balance'] >= 0 ? 'positive' : 'negative' ?>">
                                $<?= number_format($balanceGeneral['balance'], 2) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nueva sección de tendencias -->
            <div class="trends-section">
                <div class="chart-card">
                    <h4>Tendencias de Ventas por Hora</h4>
                    <div class="chart-wrapper">
                        <canvas id="tendenciasVentasChart"></canvas>
                    </div>
                    <div class="chart-legend">
                        <div class="legend-item">
                            <span class="legend-color" style="background: #4CAF50"></span>
                            <span>Número de Ventas</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background: #2196F3"></span>
                            <span>Monto Total</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Después de la sección de tendencias -->
            <!-- Flujo de Caja -->
            <div class="chart-section">
                <div class="chart-card">
                    <h4>Flujo de Caja (Últimos 12 meses)</h4>
                    <div class="chart-wrapper">
                        <canvas id="flujoCajaChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Desglose de Gastos y Comparativa Mensual -->
            <div class="charts-container">
                <div class="chart-card">
                    <h4>Desglose de Gastos por Categoría</h4>
                    <div class="chart-wrapper">
                        <canvas id="gastosCategoriasChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <h4>Comparativa Mensual</h4>
                    <div class="chart-wrapper">
                        <canvas id="comparativaMensualChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Resumen Contable -->
            <div class="accounting-summary">
                <h3>Resumen Contable</h3>
                <div class="accounting-grid">
                    <div class="accounting-card">
                        <h4>Ingresos</h4>
                        <div class="accounting-details">
                            <div class="detail-item">
                                <span>Ventas</span>
                                <span class="amount positive">$<?= number_format($balanceGeneral['total_ventas'], 2) ?></span>
                            </div>
                            <div class="detail-item">
                                <span>Otros Ingresos</span>
                                <span class="amount positive">$<?= number_format($balanceGeneral['total_ingresos'], 2) ?></span>
                            </div>
                            <div class="detail-item total">
                                <span>Total Ingresos</span>
                                <span class="amount positive">$<?= number_format($balanceGeneral['total_ventas'] + $balanceGeneral['total_ingresos'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="accounting-card">
                        <h4>Egresos</h4>
                        <div class="accounting-details">
                            <?php foreach ($gastosPorCategoriaResult['data'] as $gasto): ?>
                            <div class="detail-item">
                                <span><?= htmlspecialchars($gasto['categoria']) ?></span>
                                <span class="amount negative">$<?= number_format($gasto['total_gastos'], 2) ?></span>
                            </div>
                            <?php endforeach; ?>
                            <div class="detail-item total">
                                <span>Total Egresos</span>
                                <span class="amount negative">$<?= number_format($balanceGeneral['total_egresos'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="accounting-card">
                        <h4>Resultados</h4>
                        <div class="accounting-details">
                            <div class="detail-item">
                                <span>Margen Bruto</span>
                                <span class="amount <?= ($balanceGeneral['balance'] >= 0) ? 'positive' : 'negative' ?>">
                                    $<?= number_format($balanceGeneral['balance'], 2) ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span>Rentabilidad</span>
                                <?php 
                                $rentabilidad = ($balanceGeneral['total_ventas'] > 0) 
                                    ? ($balanceGeneral['balance'] / $balanceGeneral['total_ventas']) * 100 
                                    : 0;
                                ?>
                                <span class="amount <?= ($rentabilidad >= 0) ? 'positive' : 'negative' ?>">
                                    <?= number_format($rentabilidad, 1) ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .summary-card {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .summary-card i {
        font-size: 2rem;
        padding: 1rem;
        border-radius: 50%;
        background: #f8f9fa;
    }

    .summary-card.positive i {
        color: #28a745;
    }

    .summary-card.negative i {
        color: #dc3545;
    }

    .card-content h3 {
        font-size: 0.9rem;
        color: #6c757d;
        margin: 0;
    }

    .card-content p {
        font-size: 1.5rem;
        font-weight: bold;
        margin: 0.5rem 0 0;
    }

    .charts-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-card {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        min-height: 400px;
        display: flex;
        flex-direction: column;
    }

    .chart-card h4 {
        margin: 0 0 1rem 0;
    }

    .chart-wrapper {
        flex-grow: 1;
        position: relative;
        min-height: 300px;
    }

    @media (max-width: 768px) {
        .charts-container {
            grid-template-columns: 1fr;
        }
        
        .chart-card {
            min-height: 350px;
        }
        
        .chart-wrapper {
            min-height: 250px;
        }
    }

    .table-container {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .table-responsive {
        overflow-x: auto;
    }

    /* Estilos para KPIs */
    .kpi-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .kpi-card {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.3s ease;
    }

    .kpi-card:hover {
        transform: translateY(-5px);
    }

    .kpi-card i {
        font-size: 2rem;
        color: #4CAF50;
    }

    .kpi-content h3 {
        font-size: 0.9rem;
        color: #666;
        margin: 0;
    }

    .kpi-content p {
        font-size: 1.5rem;
        font-weight: bold;
        margin: 0.5rem 0 0;
        color: #2c3e50;
    }

    /* Estilos para Balance General */
    .balance-section {
        margin-bottom: 2rem;
    }

    .balance-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .balance-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .balance-icon i {
        font-size: 2.5rem;
        padding: 1rem;
        border-radius: 50%;
    }

    .balance-card.income .balance-icon i {
        color: #4CAF50;
        background: rgba(76, 175, 80, 0.1);
    }

    .balance-card.expenses .balance-icon i {
        color: #f44336;
        background: rgba(244, 67, 54, 0.1);
    }

    .balance-card.total .balance-icon i {
        color: #2196F3;
        background: rgba(33, 150, 243, 0.1);
    }

    .balance-details h4 {
        margin: 0;
        color: #666;
        font-size: 1rem;
    }

    .balance-details p {
        margin: 0.5rem 0;
        font-size: 1.8rem;
        font-weight: bold;
        color: #2c3e50;
    }

    .balance-breakdown {
        display: flex;
        flex-direction: column;
        font-size: 0.9rem;
        color: #666;
    }

    .positive { color: #4CAF50; }
    .negative { color: #f44336; }

    /* Mejoras responsivas */
    @media (max-width: 768px) {
        .balance-cards {
            grid-template-columns: 1fr;
        }
        
        .kpi-container {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 480px) {
        .kpi-container {
            grid-template-columns: 1fr;
        }
    }

    /* Agregar a la sección de estilos */
    .trends-section {
        margin-bottom: 2rem;
    }

    .chart-legend {
        display: flex;
        justify-content: space-between;
        margin-top: 1rem;
    }

    .legend-item {
        display: flex;
        align-items: center;
    }

    .legend-color {
        width: 1rem;
        height: 1rem;
        border-radius: 50%;
        margin-right: 0.5rem;
    }

    /* Estilos para el Resumen Contable */
    .accounting-summary {
        margin-top: 2rem;
    }

    .accounting-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 1rem;
    }

    .accounting-card {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    }

    .accounting-card h4 {
        margin: 0 0 1rem 0;
        color: #2c3e50;
        font-size: 1.2rem;
    }

    .accounting-details {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #eee;
    }

    .detail-item.total {
        border-top: 2px solid #ddd;
        border-bottom: none;
        margin-top: 0.5rem;
        padding-top: 1rem;
        font-weight: bold;
    }

    .amount {
        font-weight: 500;
    }

    .amount.positive {
        color: #4CAF50;
    }

    .amount.negative {
        color: #f44336;
    }

    /* Estilos para las nuevas gráficas */
    .chart-section {
        margin-bottom: 2rem;
    }

    .chart-section .chart-card {
        width: 100%;
    }
    </style>

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

    // Configuración global de Chart.js
    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.tooltip.callbacks.label = function(context) {
        return context.dataset.label + ': $' + new Intl.NumberFormat().format(context.raw);
    };

    // Función principal para inicializar todas las gráficas
    function initializeAllCharts() {

        // Gráfica de Tendencias de Ventas
        const tendenciasData = <?= json_encode($tendenciasVentasResult['data'] ?? []) ?>;
        const ctxTendencias = document.getElementById('tendenciasVentasChart');
        
        if (ctxTendencias && tendenciasData.length > 0) {
            new Chart(ctxTendencias, {
                type: 'line',
                data: {
                    labels: tendenciasData.map(item => item.hora),
                    datasets: [
                        {
                            label: 'Número de Ventas',
                            data: tendenciasData.map(item => parseInt(item.total_ventas)),
                            borderColor: '#4CAF50',
                            backgroundColor: 'rgba(76, 175, 80, 0.1)',
                            yAxisID: 'y',
                            fill: true
                        },
                        {
                            label: 'Monto Total',
                            data: tendenciasData.map(item => parseFloat(item.monto_total)),
                            borderColor: '#2196F3',
                            backgroundColor: 'rgba(33, 150, 243, 0.1)',
                            yAxisID: 'y1',
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Número de Ventas'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Monto Total ($)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }

        // Gráfica de Flujo de Caja
        const flujoCajaData = <?= json_encode($flujoCajaResult['data'] ?? []) ?>;
        const ctxFlujo = document.getElementById('flujoCajaChart');
        
        if (ctxFlujo && flujoCajaData.length > 0) {
            new Chart(ctxFlujo, {
                type: 'bar',
                data: {
                    labels: flujoCajaData.map(item => {
                        const fecha = new Date(item.mes + '-01');
                        return fecha.toLocaleDateString('es-ES', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Flujo de Caja',
                        data: flujoCajaData.map(item => parseFloat(item.flujo)),
                        backgroundColor: flujoCajaData.map(item => 
                            parseFloat(item.flujo) >= 0 ? 
                            'rgba(76, 175, 80, 0.6)' : 
                            'rgba(244, 67, 54, 0.6)'
                        )
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => '$' + value.toLocaleString('es-ES')
                            }
                        }
                    }
                }
            });
        }

        // Gráfica de Gastos por Categoría
        const gastosData = <?= json_encode($gastosPorCategoriaResult['data'] ?? []) ?>;
        const ctxGastos = document.getElementById('gastosCategoriasChart');
        
        if (ctxGastos && gastosData.length > 0) {
            new Chart(ctxGastos, {
                type: 'doughnut',
                data: {
                    labels: gastosData.map(item => item.categoria),
                    datasets: [{
                        data: gastosData.map(item => parseFloat(item.total_gastos)),
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }

        // Gráfica de Comparativa Mensual
        const comparativaData = <?= json_encode($comparativaMensualResult['data'] ?? []) ?>;
        const ctxComparativa = document.getElementById('comparativaMensualChart');
        
        if (ctxComparativa && comparativaData.length > 0) {
            new Chart(ctxComparativa, {
                type: 'line',
                data: {
                    labels: comparativaData.map(item => {
                        const fecha = new Date(item.mes + '-01');
                        return fecha.toLocaleDateString('es-ES', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [
                        {
                            label: 'Ventas ($)',
                            data: comparativaData.map(item => parseFloat(item.ventas)),
                            borderColor: '#4CAF50',
                            backgroundColor: 'rgba(76, 175, 80, 0.1)',
                            yAxisID: 'y',
                            fill: true
                        },
                        {
                            label: 'Número de Clientes',
                            data: comparativaData.map(item => parseInt(item.num_clientes)),
                            borderColor: '#2196F3',
                            backgroundColor: 'rgba(33, 150, 243, 0.1)',
                            yAxisID: 'y1',
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Ventas ($)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Número de Clientes'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }
    }

    // Inicialización cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        try {
            initializeAllCharts();
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Hubo un problema al cargar las gráficas'
            });
        }
    });
    </script>
</body>
</html>
