<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Incluir la configuración de la base de datos
require_once 'config/db.php';

// Funciones para obtener totales de manera segura
function getTotal($query, $params = [])
{
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        error_log("Error en getTotal: " . $e->getMessage());
        return 0;
    }
}

// Función para ejecutar consultas de manera segura
function executeQuery($query, $params = [])
{
    global $pdo;
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en executeQuery: " . $e->getMessage());
        return [];
    }
}

// Obtener datos del usuario
$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Obtener el total de ventas del día actual con porcentaje de cambio
$ventasHoy = executeQuery("
    SELECT 
        (SELECT COALESCE(SUM(total), 0)
         FROM ventas 
         WHERE user_id = ? 
         AND DATE(fecha) = CURDATE()) as total_hoy,
        (SELECT COALESCE(SUM(total), 0)
         FROM ventas 
         WHERE user_id = ? 
         AND DATE(fecha) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)) as total_ayer,
        (SELECT COUNT(*)
         FROM ventas 
         WHERE user_id = ? 
         AND DATE(fecha) = CURDATE()) as num_ventas_hoy
    FROM dual",
    [$user_id, $user_id, $user_id]
);

// Agregar log para debug
error_log("Ventas de hoy: " . print_r($ventasHoy, true));

$totalVentasDia = $ventasHoy[0]['total_hoy'] ?? 0;
$totalVentasAyer = $ventasHoy[0]['total_ayer'] ?? 0;
$numVentasHoy = $ventasHoy[0]['num_ventas_hoy'] ?? 0;

// Calcular el porcentaje de cambio
$porcentajeCambioVentas = $totalVentasAyer > 0 ? 
    (($totalVentasDia - $totalVentasAyer) / $totalVentasAyer) * 100 : 0;

// Obtener el promedio de ventas diarias del último mes
$promedioVentasDiarias = executeQuery("
    SELECT 
        COALESCE(AVG(total_diario), 0) as promedio,
        COUNT(DISTINCT fecha) as dias_con_ventas
    FROM (
        SELECT 
            DATE(fecha) as fecha,
            SUM(total) as total_diario
        FROM ventas 
        WHERE user_id = ? 
        AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(fecha)
    ) as ventas_diarias",
    [$user_id]
);

$promedioVentas = $promedioVentasDiarias[0]['promedio'] ?? 0;
$diasConVentas = $promedioVentasDiarias[0]['dias_con_ventas'] ?? 0;

// Agregar más logs para debug
error_log("User ID: " . $user_id);
error_log("Fecha actual: " . date('Y-m-d'));
error_log("Total ventas día: " . $totalVentasDia);
error_log("Promedio ventas: " . $promedioVentas);

// Obtener estadísticas mensuales
$estadisticasMensuales = executeQuery("
    SELECT 
        COALESCE(SUM(CASE WHEN MONTH(fecha) = MONTH(CURRENT_DATE()) 
                         AND YEAR(fecha) = YEAR(CURRENT_DATE()) 
                    THEN total ELSE 0 END), 0) as mes_actual,
        COALESCE(SUM(CASE WHEN MONTH(fecha) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                         AND YEAR(fecha) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                    THEN total ELSE 0 END), 0) as mes_anterior
    FROM ventas 
    WHERE user_id = ? 
    AND fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH)",
    [$user_id]
);

$totalIngresosMes = $estadisticasMensuales[0]['mes_actual'] ?? 0;
$totalIngresosMesAnterior = $estadisticasMensuales[0]['mes_anterior'] ?? 0;
$porcentajeCambioIngresos = $totalIngresosMesAnterior > 0 ? 
    (($totalIngresosMes - $totalIngresosMesAnterior) / $totalIngresosMesAnterior) * 100 : 0;

// Obtener estadísticas de egresos
$estadisticasEgresos = executeQuery("
    SELECT 
        COALESCE(SUM(CASE WHEN MONTH(fecha) = MONTH(CURRENT_DATE()) 
                         AND YEAR(fecha) = YEAR(CURRENT_DATE()) 
                    THEN monto ELSE 0 END), 0) as mes_actual,
        COALESCE(SUM(CASE WHEN MONTH(fecha) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                         AND YEAR(fecha) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                    THEN monto ELSE 0 END), 0) as mes_anterior
    FROM egresos 
    WHERE user_id = ? 
    AND estado = 'pagado'
    AND fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH)",
    [$user_id]
);

$totalEgresosMes = $estadisticasEgresos[0]['mes_actual'] ?? 0;
$totalEgresosMesAnterior = $estadisticasEgresos[0]['mes_anterior'] ?? 0;
$porcentajeCambioEgresos = $totalEgresosMesAnterior > 0 ? 
    (($totalEgresosMes - $totalEgresosMesAnterior) / $totalEgresosMesAnterior) * 100 : 0;

// Calcular promedio real de transacciones diarias
$promedioTransacciones = executeQuery("
    SELECT 
        COUNT(*) as total_transacciones,
        COUNT(DISTINCT DATE(fecha)) as total_dias
    FROM ventas 
    WHERE user_id = ? 
    AND fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)",
    [$user_id]
);

$totalTransacciones = $promedioTransacciones[0]['total_transacciones'] ?? 0;
$totalDias = $promedioTransacciones[0]['total_dias'] ?? 1; // Evitar división por cero
$promedioTransaccionesDiarias = $totalDias > 0 ? $totalTransacciones / $totalDias : 0;

// Obtener ventas de los últimos 7 días
$ventasUltimos7Dias = executeQuery("
    SELECT DATE(fecha) as fecha, COALESCE(SUM(total), 0) as total
    FROM ventas
    WHERE user_id = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha)
    ORDER BY fecha",
    [$user_id]
);

// Obtener totales por método de pago
$ventasPorMetodoPago = executeQuery("
    SELECT 
        CASE 
            WHEN metodo_pago IS NULL OR metodo_pago = '' THEN 'No especificado'
            ELSE metodo_pago 
        END as metodo_pago,
        COALESCE(SUM(total), 0) as total
    FROM ventas
    WHERE user_id = ? AND DATE(fecha) = CURDATE()
    GROUP BY 
        CASE 
            WHEN metodo_pago IS NULL OR metodo_pago = '' THEN 'No especificado'
            ELSE metodo_pago 
        END",
    [$user_id]
);

// Si no hay datos, agregar un valor por defecto
if (empty($ventasPorMetodoPago)) {
    $ventasPorMetodoPago = [
        [
            'metodo_pago' => 'Sin ventas',
            'total' => 0
        ]
    ];
}

// Obtener las últimas 5 ventas
$ultimasVentas = executeQuery("
    SELECT v.id, v.fecha, v.total, COALESCE(c.nombre, 'Cliente General') as cliente
    FROM ventas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    WHERE v.user_id = ?
    ORDER BY v.fecha DESC
    LIMIT 5",
    [$user_id]
);

// Obtener la información de la empresa
$empresa_info = executeQuery("
    SELECT * FROM empresas 
    WHERE id = (SELECT empresa_id FROM users WHERE id = ?) 
    LIMIT 1",
    [$user_id]
);
$empresa_info = $empresa_info[0] ?? [];

// Obtener total de ingresos del mes actual
$totalIngresosMes = getTotal("
    SELECT COALESCE(SUM(total), 0) as total
    FROM ventas 
    WHERE user_id = ? 
    AND MONTH(fecha) = MONTH(CURRENT_DATE())
    AND YEAR(fecha) = YEAR(CURRENT_DATE())",
    [$user_id]
);

// Obtener total de egresos del mes actual
$totalEgresosMes = getTotal("
    SELECT COALESCE(SUM(monto), 0) as total
    FROM egresos 
    WHERE user_id = ? 
    AND MONTH(fecha) = MONTH(CURRENT_DATE())
    AND YEAR(fecha) = YEAR(CURRENT_DATE())
    AND estado = 'pagado'",
    [$user_id]
);

// Obtener balance mensual de los últimos 6 meses
$balanceMensual = executeQuery("
    SELECT 
        DATE_FORMAT(fecha_mov, '%Y-%m') as mes,
        SUM(CASE 
            WHEN tipo = 'ingreso' THEN monto 
            WHEN tipo = 'egreso' THEN -monto
            ELSE 0 
        END) as balance
    FROM (
        SELECT created_at as fecha_mov, monto, 'ingreso' as tipo
        FROM ingresos
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        UNION ALL
        SELECT created_at as fecha_mov, monto, 'egreso' as tipo
        FROM egresos
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        AND estado = 'pagado'
    ) as movimientos
    GROUP BY DATE_FORMAT(fecha_mov, '%Y-%m')
    ORDER BY mes DESC",
    [$user_id, $user_id]
);

// Obtener comparativa de ingresos vs egresos mensual
$comparativaMensual = executeQuery("
    SELECT 
        mes,
        SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as total_ingresos,
        SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as total_egresos,
        COUNT(CASE WHEN tipo = 'ingreso' THEN 1 END) as num_ingresos,
        COUNT(CASE WHEN tipo = 'egreso' THEN 1 END) as num_egresos
    FROM (
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mes,
            monto,
            'ingreso' as tipo
        FROM ingresos
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH)
        UNION ALL
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mes,
            monto,
            'egreso' as tipo
        FROM egresos
        WHERE user_id = ? 
        AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH)
        AND estado = 'pagado'
    ) as movimientos
    GROUP BY mes
    ORDER BY mes DESC
    LIMIT 2",
    [$user_id, $user_id]
);

// Obtener productos más vendidos
$productosPopulares = executeQuery("
    SELECT p.nombre, COALESCE(SUM(dv.cantidad), 0) as total_vendido
    FROM detalle_ventas dv
    JOIN productos p ON dv.producto_id = p.id
    JOIN ventas v ON dv.venta_id = v.id
    WHERE v.user_id = ? AND MONTH(v.fecha) = MONTH(CURRENT_DATE())
    GROUP BY p.id, p.nombre
    ORDER BY total_vendido DESC
    LIMIT 5",
    [$user_id]
);

// Obtener promedio de ventas diarias
$promedioVentasDiarias = getTotal("
    SELECT COALESCE(AVG(total_diario), 0) as promedio
    FROM (
        SELECT DATE(fecha) as dia, SUM(total) as total_diario
        FROM ventas 
        WHERE user_id = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(fecha)
    ) as ventas_diarias",
    [$user_id]
);

// Obtener número promedio de transacciones por día
$promedioTransaccionesDiarias = getTotal("
    SELECT COALESCE(COUNT(*) / DATEDIFF(CURDATE(), MIN(fecha)), 0) as promedio
    FROM ventas 
    WHERE user_id = ? 
    AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
    [$user_id]
);

// Obtener ventas por día de la semana
$ventasPorDiaSemana = executeQuery("
    SELECT 
        DAYOFWEEK(fecha) as dia_semana,
        COALESCE(AVG(total_diario), 0) as promedio_ventas
    FROM (
        SELECT 
            fecha,
            SUM(total) as total_diario
        FROM ventas
        WHERE user_id = ? 
        AND fecha >= DATE_SUB(CURRENT_DATE(), INTERVAL 3 MONTH)
        GROUP BY fecha
    ) as ventas_diarias
    GROUP BY dia_semana
    ORDER BY dia_semana",
    [$user_id]
);

// Obtener resumen de créditos activos
$creditosActivos = executeQuery("
    SELECT 
        COUNT(*) as total_creditos,
        COALESCE(SUM(monto_total), 0) as monto_total,
        COALESCE(SUM(CASE 
            WHEN estado IN ('Vencido', 'Atrasado') THEN saldo_pendiente 
            ELSE 0 
        END), 0) as monto_vencido,
        COUNT(CASE 
            WHEN estado IN ('Vencido', 'Atrasado') THEN 1 
        END) as creditos_vencidos,
        COALESCE(SUM(saldo_pendiente), 0) as total_pendiente
    FROM creditos c
    INNER JOIN ventas v ON c.venta_id = v.id 
    WHERE v.user_id = ? 
    AND c.estado != 'Pagado'",
    [$user_id]
);

$creditosActivos = $creditosActivos[0] ?? [
    'total_creditos' => 0,
    'monto_total' => 0,
    'monto_vencido' => 0,
    'creditos_vencidos' => 0,
    'total_pendiente' => 0
];

// Obtener últimos 5 créditos
$ultimosCreditos = executeQuery("
    SELECT 
        c.id,
        COALESCE(cl.nombre, 'Cliente General') as cliente,
        c.monto_total,
        c.saldo_pendiente as monto_pendiente,
        c.fecha_vencimiento,
        DATEDIFF(c.fecha_vencimiento, CURDATE()) as dias_restantes,
        c.estado,
        c.plazo,
        c.valor_cuota,
        v.user_id
    FROM creditos c
    INNER JOIN ventas v ON c.venta_id = v.id
    LEFT JOIN clientes cl ON v.cliente_id = cl.id
    WHERE v.user_id = ? 
    AND c.estado != 'Pagado'
    ORDER BY 
        CASE c.estado
            WHEN 'Vencido' THEN 1
            WHEN 'Atrasado' THEN 2
            WHEN 'Pendiente' THEN 3
            WHEN 'Al día' THEN 4
            ELSE 5
        END,
        c.fecha_vencimiento ASC
    LIMIT 5",
    [$user_id]
);

// Agregar debug para verificar
error_log("User ID: " . $user_id);
error_log("Créditos encontrados: " . print_r($creditosActivos, true));
error_log("Últimos créditos: " . print_r($ultimosCreditos, true));

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
    <link rel="icon" type="image/png" href="/favicon/favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Agregar AOS para animaciones -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <style>
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hover-scale {
            transition: transform 0.2s;
        }
        
        .hover-scale:hover {
            transform: scale(1.02);
        }

        .gradient-bg {
            background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include 'includes/header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-8">
            <!-- Banner de Bienvenida Mejorado -->
            <div class="mb-8 p-6 rounded-xl gradient-bg text-white" data-aos="fade-up">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">
                            ¡Bienvenido a VendEasy!
                        </h1>
                        <p class="text-gray-100">
                            Panel de control de <?= htmlspecialchars($empresa_info['nombre_empresa'] ?? 'su empresa') ?>
                        </p>
                        <div class="mt-4 flex items-center space-x-4">
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                Versión v3.1.0Alpha
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-clock mr-2"></i>
                                <?= date('d M Y, H:i') ?>
                            </span>
                        </div>
                    </div>
                    <div class="hidden md:block">
                        <i class="fas fa-chart-line text-6xl opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Nueva Alerta Portal de Clientes -->
            <div class="mb-8 p-4 bg-blue-50 border border-blue-200 rounded-xl shadow-sm" data-aos="fade-up" data-aos-delay="200">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-bullhorn text-2xl text-blue-600"></i>
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-blue-900">
                                    ¡Nuevo Portal de Clientes Disponible!
                                </h3>
                                <p class="mt-1 text-sm text-blue-700">
                                    Ahora tus clientes pueden acceder a sus facturas, historial de compras y estado de cuenta en línea.
                                </p>
                            </div>
                            <a href="https://portal.johanrengifo.cloud" 
                               target="_blank"
                               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                Visitar Portal
                                <i class="fas fa-external-link-alt ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjetas de Resumen Mejoradas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Ventas del Día -->
                <div class="bg-white rounded-xl shadow-lg p-6 hover-scale" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-indigo-100 rounded-full">
                            <i class="fas fa-dollar-sign text-indigo-600 text-xl"></i>
                        </div>
                        <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-1 rounded-full">
                            <?= $numVentasHoy ?> ventas hoy
                        </span>
                    </div>
                    <h3 class="text-gray-600 text-sm font-medium">Ventas del Día</h3>
                    <div class="flex items-center mt-2">
                        <h2 class="text-3xl font-bold text-gray-900">
                            $<?= number_format($totalVentasDia, 2) ?>
                        </h2>
                        <?php if ($porcentajeCambioVentas != 0): ?>
                            <span class="text-<?= $porcentajeCambioVentas >= 0 ? 'green' : 'red' ?>-500 text-sm ml-2">
                                <i class="fas fa-arrow-<?= $porcentajeCambioVentas >= 0 ? 'up' : 'down' ?>"></i>
                                <?= abs(round($porcentajeCambioVentas, 1)) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-gray-500">
                        <i class="fas fa-chart-line mr-2"></i>
                        <span>Promedio: $<?= number_format($promedioVentas, 2) ?></span>
                        <?php if ($diasConVentas > 0): ?>
                            <span class="ml-2 text-xs text-gray-400">(últimos <?= $diasConVentas ?> días)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Transacciones -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Transacciones Promedio</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?= number_format($promedioTransaccionesDiarias, 1) ?>/día
                            </p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-receipt text-blue-600"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center">
                            <span class="text-sm text-gray-500">Total mensual: </span>
                            <span class="text-sm font-semibold text-gray-900 ml-2">
                                <?= number_format($totalTransacciones) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Ingresos del Mes -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Ingresos del Mes</p>
                            <p class="text-2xl font-semibold text-gray-900">$<?= number_format($totalIngresosMes, 2) ?></p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-chart-line text-blue-600"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center">
                            <span class="<?= $porcentajeCambioIngresos >= 0 ? 'text-green-500' : 'text-red-500' ?> text-sm font-medium">
                                <i class="fas fa-arrow-<?= $porcentajeCambioIngresos >= 0 ? 'up' : 'down' ?> mr-1"></i>
                                <?= abs(round($porcentajeCambioIngresos, 1)) ?>%
                            </span>
                            <span class="text-gray-500 text-sm ml-2">vs mes anterior</span>
                        </div>
                    </div>
                </div>

                <!-- Egresos del Mes -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Egresos del Mes</p>
                            <p class="text-2xl font-semibold text-gray-900">$<?= number_format($totalEgresosMes, 2) ?></p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full">
                            <i class="fas fa-chart-pie text-red-600"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center">
                            <span class="<?= $porcentajeCambioEgresos <= 0 ? 'text-green-500' : 'text-red-500' ?> text-sm font-medium">
                                <i class="fas fa-arrow-<?= $porcentajeCambioEgresos <= 0 ? 'down' : 'up' ?> mr-1"></i>
                                <?= abs(round($porcentajeCambioEgresos, 1)) ?>%
                            </span>
                            <span class="text-gray-500 text-sm ml-2">vs mes anterior</span>
                        </div>
                    </div>
                </div>

                <!-- Balance -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Balance</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                $<?= number_format($totalIngresosMes - $totalEgresosMes, 2) ?>
                            </p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-balance-scale text-purple-600"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center">
                            <span class="text-purple-500 text-sm font-medium">Balance Total</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos Mejorados -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
                <!-- Gráfico de Ventas -->
                <div class="xl:col-span-2 bg-white rounded-xl shadow-lg p-6 hover-scale" data-aos="fade-up">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">
                                Ventas de los Últimos 7 Días
                            </h3>
                            <p class="text-sm text-gray-500">Análisis detallado de ventas</p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <select class="text-sm border-gray-300 rounded-lg focus:ring-indigo-500">
                                <option>Últimos 7 días</option>
                                <option>Último mes</option>
                            </select>
                            <button class="p-2 hover:bg-gray-100 rounded-lg">
                                <i class="fas fa-download text-gray-500"></i>
                            </button>
                        </div>
                    </div>
                    <div class="h-[300px]">
                        <canvas id="ventasChart"></canvas>
                    </div>
                </div>

                <!-- Distribución de Ventas por Método de Pago -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            Métodos de Pago
                        </h3>
                        <div class="h-[300px]">
                            <canvas id="metodoPagoChart"></canvas>
                        </div>
                        <div class="mt-4 space-y-2">
                            <?php foreach ($ventasPorMetodoPago as $metodo): ?>
                                <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                                    <span class="text-sm font-medium text-gray-600">
                                        <?= htmlspecialchars($metodo['metodo_pago']) ?>
                                    </span>
                                    <span class="text-sm font-semibold text-gray-900">
                                        $<?= number_format($metodo['total'], 2) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Comparativa Mensual -->
                <div class="xl:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            Comparativa Mensual de Ventas
                        </h3>
                        <div class="h-[300px]">
                            <canvas id="comparativaChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Distribución por Día de la Semana -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            Ventas por Día de Semana
                        </h3>
                        <div class="h-[300px]">
                            <canvas id="diasSemanaChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección de Créditos -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
                <!-- Resumen de Créditos -->
                <div class="xl:col-span-2 bg-white rounded-xl shadow-lg p-6 hover-scale" data-aos="fade-up">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Estado de Créditos</h3>
                            <p class="text-sm text-gray-500">Resumen de créditos activos</p>
                        </div>
                        <a href="/modules/creditos/index.php" class="text-indigo-600 hover:text-indigo-800">
                            Ver todos <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <!-- Total Créditos -->
                        <div class="bg-indigo-50 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-indigo-600 font-medium">Total Créditos</p>
                                    <h4 class="text-xl font-bold text-indigo-900">
                                        <?= number_format($creditosActivos['total_creditos']) ?>
                                    </h4>
                                </div>
                                <div class="p-2 bg-indigo-100 rounded-full">
                                    <i class="fas fa-file-invoice-dollar text-indigo-600"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Monto Total -->
                        <div class="bg-emerald-50 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-emerald-600 font-medium">Monto Total</p>
                                    <h4 class="text-xl font-bold text-emerald-900">
                                        $<?= number_format($creditosActivos['monto_total'], 2) ?>
                                    </h4>
                                </div>
                                <div class="p-2 bg-emerald-100 rounded-full">
                                    <i class="fas fa-dollar-sign text-emerald-600"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Créditos Vencidos -->
                        <div class="bg-red-50 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-red-600 font-medium">Créditos Vencidos</p>
                                    <h4 class="text-xl font-bold text-red-900">
                                        <?= number_format($creditosActivos['creditos_vencidos']) ?>
                                    </h4>
                                </div>
                                <div class="p-2 bg-red-100 rounded-full">
                                    <i class="fas fa-exclamation-circle text-red-600"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de Últimos Créditos -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-sm text-gray-500">
                                    <th class="pb-3">Cliente</th>
                                    <th class="pb-3">Monto Total</th>
                                    <th class="pb-3">Pendiente</th>
                                    <th class="pb-3">Vencimiento</th>
                                    <th class="pb-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm">
                                <?php foreach ($ultimosCreditos as $credito): ?>
                                    <tr class="border-t border-gray-100">
                                        <td class="py-3">
                                            <?= htmlspecialchars($credito['cliente']) ?>
                                        </td>
                                        <td class="py-3">
                                            $<?= number_format($credito['monto_total'], 2) ?>
                                        </td>
                                        <td class="py-3">
                                            $<?= number_format($credito['monto_pendiente'], 2) ?>
                                        </td>
                                        <td class="py-3">
                                            <?= date('d/m/Y', strtotime($credito['fecha_vencimiento'])) ?>
                                        </td>
                                        <td class="py-3">
                                            <?php 
                                                $estadoClases = [
                                                    'Vencido' => 'bg-red-100 text-red-800',
                                                    'Atrasado' => 'bg-yellow-100 text-yellow-800',
                                                    'Pendiente' => 'bg-blue-100 text-blue-800',
                                                    'Al día' => 'bg-green-100 text-green-800'
                                                ];
                                                $clase = $estadoClases[$credito['estado']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs rounded-full <?= $clase ?>">
                                                <?= htmlspecialchars($credito['estado']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Widget de Alertas de Créditos -->
                <div class="bg-white rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Alertas de Créditos</h3>
                    
                    <?php if ($creditosActivos['creditos_vencidos'] > 0): ?>
                        <div class="mb-4 p-4 bg-red-50 rounded-lg border border-red-100">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-semibold text-red-800">Créditos Vencidos</h4>
                                    <p class="text-sm text-red-600">
                                        Hay <?= $creditosActivos['creditos_vencidos'] ?> créditos vencidos por un total de 
                                        $<?= number_format($creditosActivos['monto_vencido'], 2) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Botón de Acción -->
                    <a href="/modules/creditos/crear.php" 
                       class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        Nuevo Crédito
                    </a>
                </div>
            </div>

            <!-- Estadísticas Detalladas -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
                <!-- Balance Mensual -->
                <div class="xl:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Balance Mensual</h3>
                            <select id="balanceYearSelect" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="2024">2024</option>
                                <option value="2023">2023</option>
                            </select>
                        </div>
                        <div class="h-[300px]">
                            <canvas id="balanceChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Últimas Ventas y Actividad -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Últimas Ventas</h3>
                        <div class="space-y-4">
                            <?php if (!empty($ultimasVentas)): ?>
                                <?php foreach ($ultimasVentas as $venta): ?>
                                    <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors duration-150">
                                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                            <i class="fas fa-shopping-cart text-indigo-600"></i>
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <div class="flex items-center justify-between">
                                                <p class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($venta['cliente']) ?>
                                                </p>
                                                <span class="text-sm font-semibold text-green-600">
                                                    $<?= number_format($venta['total'], 2) ?>
                                                </span>
                                            </div>
                                            <div class="flex items-center mt-1">
                                                <i class="fas fa-clock text-gray-400 text-xs mr-1"></i>
                                                <p class="text-xs text-gray-500">
                                                    <?= date('d M Y H:i', strtotime($venta['fecha'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="./modules/ventas/ver.php?id=<?= $venta['id'] ?>" 
                                           class="ml-4 text-indigo-600 hover:text-indigo-800">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                                <!-- Botón Ver todas las ventas -->
                                <div class="mt-4 text-center">
                                    <a href="/modules/ventas/index.php" 
                                       class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                        Ver todas las ventas
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                        <i class="fas fa-receipt text-gray-400 text-2xl"></i>
                                    </div>
                                    <p class="text-gray-500">No hay ventas registradas</p>
                                    <a href="/pos/index.php" 
                                       class="mt-2 inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800">
                                        Realizar una venta
                                        <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Inicializar AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Configuración global de Chart.js
        
        Chart.defaults.font.family = '"Inter var", system-ui, -apple-system, sans-serif';
        Chart.defaults.color = '#64748b';

        // Función para formatear moneda
        function formatCurrency(value) {
            return new Intl.NumberFormat('es-CO', {
                style: 'currency',
                currency: 'COP',
                minimumFractionDigits: 0
            }).format(value);
        }

        // Gráfico de ventas de los últimos 7 días
        const ctxVentas = document.getElementById('ventasChart').getContext('2d');
        new Chart(ctxVentas, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function($fecha) {
                    return date('d M', strtotime($fecha));
                }, array_column($ventasUltimos7Dias, 'fecha'))) ?>,
                datasets: [{
                    label: 'Ventas',
                    data: <?= json_encode(array_column($ventasUltimos7Dias, 'total')) ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4f46e5',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#e2e8f0',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return formatCurrency(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Gráfico de métodos de pago
        const ctxMetodoPago = document.getElementById('metodoPagoChart').getContext('2d');
        const metodoPagoChart = new Chart(ctxMetodoPago, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($ventasPorMetodoPago, 'metodo_pago')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($ventasPorMetodoPago, 'total')) ?>,
                    backgroundColor: [
                        '#4f46e5', // Indigo
                        '#06b6d4', // Cyan
                        '#10b981', // Emerald
                        '#f59e0b', // Amber
                        '#ef4444', // Red
                        '#8b5cf6'  // Violet
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#e2e8f0',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${formatCurrency(context.parsed)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de balance mensual
        const ctxBalance = document.getElementById('balanceChart').getContext('2d');
        new Chart(ctxBalance, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(function($item) {
                    return date('M Y', strtotime($item['mes'] . '-01'));
                }, $balanceMensual)) ?>,
                datasets: [{
                    label: 'Balance',
                    data: <?= json_encode(array_column($balanceMensual, 'balance')) ?>,
                    backgroundColor: function(context) {
                        const value = context.raw;
                        return value >= 0 ? 'rgba(34, 197, 94, 0.9)' : 'rgba(239, 68, 68, 0.9)';
                    },
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#e2e8f0',
                        bodyColor: '#e2e8f0',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const sign = value >= 0 ? '+' : '';
                                return 'Balance: ' + sign + formatCurrency(value);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de comparativa mensual
        const ctxComparativa = document.getElementById('comparativaChart').getContext('2d');
        new Chart(ctxComparativa, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(function($item) {
                    return date('F Y', strtotime($item['mes'] . '-01'));
                }, $comparativaMensual)) ?>,
                datasets: [{
                    label: 'Ingresos',
                    data: <?= json_encode(array_column($comparativaMensual, 'total_ingresos')) ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.9)',
                    borderRadius: 6
                }, {
                    label: 'Egresos',
                    data: <?= json_encode(array_column($comparativaMensual, 'total_egresos')) ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.9)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                return label + ': ' + formatCurrency(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de ventas por día de la semana
        const ctxDiasSemana = document.getElementById('diasSemanaChart').getContext('2d');
        const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        new Chart(ctxDiasSemana, {
            type: 'radar',
            data: {
                labels: diasSemana,
                datasets: [{
                    label: 'Promedio de Ventas',
                    data: <?= json_encode(array_column($ventasPorDiaSemana, 'promedio_ventas')) ?>,
                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                    borderColor: 'rgba(79, 70, 229, 1)',
                    pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(79, 70, 229, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Promedio: ' + formatCurrency(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>