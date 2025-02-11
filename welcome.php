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

// Verificar suscripción activa
$stmt = $pdo->prepare("
    SELECT p.*, e.plan_suscripcion 
    FROM pagos p
    JOIN empresas e ON e.id = p.empresa_id
    WHERE p.empresa_id = ? 
    AND p.estado = 'completado'
    AND p.fecha_fin_plan >= NOW()
    ORDER BY p.fecha_pago DESC
    LIMIT 1
");

$stmt->execute([$_SESSION['empresa_id']]);
$suscripcion = $stmt->fetch();

if (!$suscripcion) {
    $_SESSION['error_message'] = "Tu suscripción ha expirado. Por favor, renueva tu plan para continuar.";
    header('Location: /modules/empresa/planes.php');
    exit();
}

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
$ventasHoy = executeQuery(
    "
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
$promedioVentasDiarias = executeQuery(
    "
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
$estadisticasMensuales = executeQuery(
    "
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
$estadisticasEgresos = executeQuery(
    "
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
$promedioTransacciones = executeQuery(
    "
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
$ventasUltimos7Dias = executeQuery(
    "
    SELECT DATE(fecha) as fecha, COALESCE(SUM(total), 0) as total
    FROM ventas
    WHERE user_id = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha)
    ORDER BY fecha",
    [$user_id]
);

// Obtener totales por método de pago
$ventasPorMetodoPago = executeQuery(
    "
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
$ultimasVentas = executeQuery(
    "
    SELECT v.id, v.fecha, v.total, COALESCE(c.nombre, 'Cliente General') as cliente
    FROM ventas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    WHERE v.user_id = ?
    ORDER BY v.fecha DESC
    LIMIT 5",
    [$user_id]
);

// Obtener la información de la empresa
$empresa_info = executeQuery(
    "
    SELECT * FROM empresas 
    WHERE id = (SELECT empresa_id FROM users WHERE id = ?) 
    LIMIT 1",
    [$user_id]
);
$empresa_info = $empresa_info[0] ?? [];

// Obtener total de ingresos del mes actual
$totalIngresosMes = getTotal(
    "
    SELECT COALESCE(SUM(total), 0) as total
    FROM ventas 
    WHERE user_id = ? 
    AND MONTH(fecha) = MONTH(CURRENT_DATE())
    AND YEAR(fecha) = YEAR(CURRENT_DATE())",
    [$user_id]
);

// Obtener total de egresos del mes actual
$totalEgresosMes = getTotal(
    "
    SELECT COALESCE(SUM(monto), 0) as total
    FROM egresos 
    WHERE user_id = ? 
    AND MONTH(fecha) = MONTH(CURRENT_DATE())
    AND YEAR(fecha) = YEAR(CURRENT_DATE())
    AND estado = 'pagado'",
    [$user_id]
);

// Obtener balance mensual de los últimos 6 meses
$balanceMensual = executeQuery(
    "
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
$comparativaMensual = executeQuery(
    "
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
$productosPopulares = executeQuery(
    "
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
$promedioVentasDiarias = getTotal(
    "
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
$promedioTransaccionesDiarias = getTotal(
    "
    SELECT COALESCE(COUNT(*) / DATEDIFF(CURDATE(), MIN(fecha)), 0) as promedio
    FROM ventas 
    WHERE user_id = ? 
    AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
    [$user_id]
);

// Obtener ventas por día de la semana
$ventasPorDiaSemana = executeQuery(
    "
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

// Obtener estadísticas de créditos
$creditosActivos = executeQuery(
    "
    SELECT 
        COUNT(*) as total_creditos,
        COALESCE(SUM(monto_total), 0) as monto_total,
        COALESCE(SUM(monto_pagado), 0) as monto_pagado,
        COALESCE(SUM(saldo_pendiente), 0) as total_pendiente,
        COUNT(CASE WHEN estado = 'Atrasado' THEN 1 END) as creditos_atrasados,
        COUNT(CASE WHEN estado = 'Vencido' THEN 1 END) as creditos_vencidos,
        COUNT(CASE WHEN estado = 'Al día' THEN 1 END) as creditos_al_dia,
        COALESCE(SUM(CASE 
            WHEN estado IN ('Vencido', 'Atrasado') THEN saldo_pendiente 
            ELSE 0 
        END), 0) as monto_vencido
    FROM creditos c
    INNER JOIN ventas v ON c.venta_id = v.id 
    WHERE v.user_id = ? 
    AND c.estado != 'Pagado'",
    [$user_id]
);

$creditosActivos = $creditosActivos[0] ?? [
    'total_creditos' => 0,
    'monto_total' => 0,
    'monto_pagado' => 0,
    'total_pendiente' => 0,
    'creditos_atrasados' => 0,
    'creditos_vencidos' => 0,
    'creditos_al_dia' => 0,
    'monto_vencido' => 0
];

// Obtener últimos 5 créditos
$ultimosCreditos = executeQuery(
    "
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
    <title>VendEasy - Panel de Control</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .gradient-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include 'includes/header.php'; ?>

    <?php if (isset($_GET['payment_success']) && isset($_SESSION['success_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: '¡Pago Exitoso!',
                text: '<?= htmlspecialchars($_SESSION['success_message']) ?>',
                showConfirmButton: true,
                confirmButtonText: 'Continuar',
                confirmButtonColor: '#0284c7',
                timer: 5000,
                timerProgressBar: true
            });
        });
    </script>
    <?php 
        unset($_SESSION['success_message']);
    endif; 
    ?>

    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>

        <main class="flex-1 p-6">
            <!-- Encabezado Principal -->
            <div class="mb-6 p-4 rounded-xl gradient-primary text-white shadow-sm" data-aos="fade-up">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-semibold mb-1">
                            Panel de Control
                        </h1>
                        <p class="text-sm text-gray-100">
                            <?= htmlspecialchars($empresa_info['nombre_empresa'] ?? '') ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm opacity-90"><?= date('d M Y') ?></p>
                        <p class="text-xs opacity-75">v3.1.0</p>
                    </div>
                </div>
            </div>

            <!-- Banner Portal de Clientes -->
            <div class="mb-8 p-6 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl shadow-lg text-white" data-aos="fade-up" data-aos-delay="100">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 p-3 bg-white/20 rounded-full">
                            <i class="fas fa-bullhorn text-2xl text-white"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xl font-bold">¡Nuevo Portal de Clientes!</h3>
                            <p class="text-sm text-gray-100 mt-1">
                                Acceso a facturas, historial de compras y estado de cuenta en línea
                            </p>
                        </div>
                    </div>
                    <a href="https://portal.johanrengifo.cloud" target="_blank" 
                       class="inline-flex items-center px-4 py-2 bg-white text-indigo-600 text-sm font-medium rounded-xl 
                              transition-all hover:bg-indigo-50 hover:scale-105">
                        Visitar Portal
                        <i class="fas fa-external-link-alt ml-2"></i>
                    </a>
                </div>
            </div>

            <!-- Resumen Principal -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Ventas del Día -->
                <div class="bg-white rounded-xl p-6 shadow-sm card-hover" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
                            Hoy
                        </span>
                    </div>
                    <h3 class="text-gray-600 text-sm font-medium">Ventas Totales</h3>
                    <div class="mt-2">
                        <h2 class="text-2xl font-bold text-gray-900">
                            $<?= number_format($totalVentasDia, 0) ?>
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">
                            <?= $numVentasHoy ?> transacciones
                        </p>
                    </div>
                </div>

                <!-- Balance del Mes -->
                <div class="bg-white rounded-xl p-6 shadow-sm card-hover" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium text-green-600 bg-green-50 px-3 py-1 rounded-full">
                            Este Mes
                        </span>
                    </div>
                    <h3 class="text-gray-600 text-sm font-medium">Balance</h3>
                    <div class="mt-2">
                        <h2 class="text-2xl font-bold text-gray-900">
                            $<?= number_format($totalIngresosMes - $totalEgresosMes, 0) ?>
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">
                            vs mes anterior: <?= round($porcentajeCambioIngresos, 1) ?>%
                        </p>
                    </div>
                </div>

                <!-- Promedio Diario -->
                <div class="bg-white rounded-xl p-6 shadow-sm card-hover" data-aos="fade-up" data-aos-delay="400">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-amber-100 rounded-full">
                            <i class="fas fa-calculator text-amber-600 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium text-amber-600 bg-amber-50 px-3 py-1 rounded-full">
                            Promedio
                        </span>
                    </div>
                    <h3 class="text-gray-600 text-sm font-medium">Ventas Diarias</h3>
                    <div class="mt-2">
                        <h2 class="text-2xl font-bold text-gray-900">
                            $<?= number_format($promedioVentas, 0) ?>
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">
                            últimos <?= $diasConVentas ?> días
                        </p>
                    </div>
                </div>
            </div>

            <!-- Gráficos Principales -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Ventas Mensuales -->
                <div class="bg-white rounded-xl p-6 shadow-sm" data-aos="fade-up">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Ventas Mensuales</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500"><?= date('F Y') ?></span>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <!-- Ingresos -->
                        <div class="p-4 bg-green-50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Ingresos</span>
                                <span class="text-sm text-green-600 bg-green-100 px-2 py-1 rounded-full">
                                    Este mes
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <h4 class="text-2xl font-bold text-gray-900">
                                    $<?= number_format($totalIngresosMes, 0) ?>
                                </h4>
                                <?php if ($porcentajeCambioIngresos != 0): ?>
                                <span class="flex items-center text-sm <?= $porcentajeCambioIngresos >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <i class="fas fa-arrow-<?= $porcentajeCambioIngresos >= 0 ? 'up' : 'down' ?> mr-1"></i>
                                    <?= abs(round($porcentajeCambioIngresos, 1)) ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Egresos -->
                        <div class="p-4 bg-red-50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Egresos</span>
                                <span class="text-sm text-red-600 bg-red-100 px-2 py-1 rounded-full">
                                    Este mes
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <h4 class="text-2xl font-bold text-gray-900">
                                    $<?= number_format($totalEgresosMes, 0) ?>
                                </h4>
                                <?php if ($porcentajeCambioEgresos != 0): ?>
                                <span class="flex items-center text-sm <?= $porcentajeCambioEgresos <= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <i class="fas fa-arrow-<?= $porcentajeCambioEgresos <= 0 ? 'down' : 'up' ?> mr-1"></i>
                                    <?= abs(round($porcentajeCambioEgresos, 1)) ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Balance -->
                        <div class="p-4 bg-blue-50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Balance Total</span>
                                <span class="text-sm text-blue-600 bg-blue-100 px-2 py-1 rounded-full">
                                    Neto
                                </span>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-900">
                                $<?= number_format($totalIngresosMes - $totalEgresosMes, 0) ?>
                            </h4>
                        </div>
                    </div>
                </div>

                <!-- Ventas Diarias -->
                <div class="bg-white rounded-xl p-6 shadow-sm" data-aos="fade-up">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-800">Ventas Diarias</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">Últimos <?= $diasConVentas ?> días</span>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <!-- Promedio -->
                        <div class="p-4 bg-indigo-50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Promedio Diario</span>
                                <span class="text-sm text-indigo-600 bg-indigo-100 px-2 py-1 rounded-full">
                                    Promedio
                                </span>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-900">
                                $<?= number_format($promedioVentas, 0) ?>
                            </h4>
                        </div>
                        <!-- Transacciones -->
                        <div class="p-4 bg-purple-50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Transacciones Promedio</span>
                                <span class="text-sm text-purple-600 bg-purple-100 px-2 py-1 rounded-full">
                                    Por día
                                </span>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-900">
                                <?= number_format($promedioTransaccionesDiarias, 1) ?>
                            </h4>
                        </div>
                        <!-- Hoy -->
                        <div class="p-4 bg-amber-50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-600">Ventas de Hoy</span>
                                <span class="text-sm text-amber-600 bg-amber-100 px-2 py-1 rounded-full">
                                    <?= $numVentasHoy ?> transacciones
                                </span>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-900">
                                $<?= number_format($totalVentasDia, 0) ?>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Últimas Transacciones -->
            <div class="bg-white rounded-xl p-6 shadow-sm mb-8" data-aos="fade-up">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Últimas Transacciones</h3>
                    <a href="/modules/ventas/index.php" class="text-blue-600 hover:text-blue-800 text-sm">
                        Ver todas <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500">
                                <th class="pb-3 pl-4">Cliente</th>
                                <th class="pb-3">Fecha</th>
                                <th class="pb-3">Monto</th>
                                <th class="pb-3 text-right pr-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimasVentas as $venta): ?>
                                <tr class="border-t border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 pl-4">
                                        <?= htmlspecialchars($venta['cliente']) ?>
                                    </td>
                                    <td class="py-3">
                                        <?= date('d M Y, H:i', strtotime($venta['fecha'])) ?>
                                    </td>
                                    <td class="py-3 font-medium">
                                        $<?= number_format($venta['total'], 0) ?>
                                    </td>
                                    <td class="py-3 pr-4 text-right">
                                        <a href="./modules/ventas/ver.php?id=<?= $venta['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            Ver detalles
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

        // Configuración de Chart.js
        Chart.defaults.font.family = '"Inter var", system-ui, -apple-system, sans-serif';
        Chart.defaults.color = '#64748b';

        // Gráfico de ventas semanales
        const ctxVentas = document.getElementById('ventasChart').getContext('2d');
        new Chart(ctxVentas, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function ($fecha) {
                    return date('d M', strtotime($fecha));
                }, array_column($ventasUltimos7Dias, 'fecha'))) ?>,
                datasets: [{
                    label: 'Ventas',
                    data: <?= json_encode(array_column($ventasUltimos7Dias, 'total')) ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => '$' + new Intl.NumberFormat().format(value)
                        }
                    }
                }
            }
        });

        // Gráfico de métodos de pago
        const ctxMetodoPago = document.getElementById('metodoPagoChart').getContext('2d');
        new Chart(ctxMetodoPago, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($ventasPorMetodoPago, 'metodo_pago')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($ventasPorMetodoPago, 'total')) ?>,
                    backgroundColor: [
                        '#4f46e5',
                        '#06b6d4',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>