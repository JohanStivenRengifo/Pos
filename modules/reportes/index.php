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
        // Primero obtenemos el total de días con ventas
        $queryDiasVenta = "SELECT COUNT(DISTINCT DATE(fecha)) as total_dias 
                          FROM ventas 
                          WHERE user_id = ? 
                          AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmtDias = $pdo->prepare($queryDiasVenta);
        $stmtDias->execute([$user_id]);
        $totalDias = $stmtDias->fetch(PDO::FETCH_ASSOC)['total_dias'];
        
        // Si no hay días con ventas, usamos 1 para evitar división por cero
        $totalDias = max(1, $totalDias);
        
        // Ahora obtenemos las estadísticas principales
        $query = "SELECT 
                    COALESCE(AVG(total), 0) as ticket_promedio,
                    COUNT(*) as total_ventas,
                    COALESCE(SUM(total), 0) as total_ingresos,
                    COUNT(DISTINCT cliente_id) as clientes_unicos,
                    COUNT(*) / ? as ventas_diarias,
                    COALESCE(SUM(total), 0) / ? as ingreso_diario
                  FROM ventas 
                  WHERE user_id = ? 
                  AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$totalDias, $totalDias, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log para debugging
        error_log("KPIs calculados: " . json_encode($result));
        error_log("Total días con ventas: " . $totalDias);
        
        return ['status' => true, 'data' => $result];
    } catch (PDOException $e) {
        error_log("Error en getKPIs: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error al obtener KPIs: ' . $e->getMessage()];
    }
}

// Función para obtener flujo de caja
function getFlujoCaja($user_id) {
    global $pdo;
    try {
        // Primero verificamos si hay datos
        $queryCheck = "SELECT COUNT(*) as total FROM turnos t 
                      WHERE t.user_id = ? AND t.estado = 'cerrado'";
        $stmtCheck = $pdo->prepare($queryCheck);
        $stmtCheck->execute([$user_id]);
        $totalTurnos = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
        
        error_log("Total de turnos encontrados: " . $totalTurnos);
        
        // Query principal modificada para asegurar que obtengamos datos
        $query = "SELECT 
                    DATE_FORMAT(t.fecha_apertura, '%Y-%m') as mes,
                    COALESCE(SUM(v.total), 0) as total_ventas,
                    COUNT(DISTINCT t.id) as total_turnos
                  FROM turnos t
                  LEFT JOIN ventas v ON v.turno_id = t.id AND v.estado = 'completada'
                  WHERE t.user_id = ?
                  AND t.estado = 'cerrado'
                  AND t.fecha_apertura >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(t.fecha_apertura, '%Y-%m')
                  ORDER BY mes DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log para debugging
        error_log("Query Flujo de caja: " . $query);
        error_log("User ID: " . $user_id);
        error_log("Resultados encontrados: " . count($result));
        error_log("Datos de flujo de caja: " . json_encode($result));
        
        // Si no hay resultados, intentamos obtener al menos los turnos
        if (empty($result)) {
            $queryBasic = "SELECT 
                            DATE_FORMAT(fecha_apertura, '%Y-%m') as mes,
                            0 as total_ventas,
                            COUNT(*) as total_turnos
                          FROM turnos 
                          WHERE user_id = ?
                          AND estado = 'cerrado'
                          AND fecha_apertura >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                          GROUP BY DATE_FORMAT(fecha_apertura, '%Y-%m')
                          ORDER BY mes DESC";
            
            $stmtBasic = $pdo->prepare($queryBasic);
            $stmtBasic->execute([$user_id]);
            $result = $stmtBasic->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Resultados básicos encontrados: " . count($result));
        }
        
        return ['status' => true, 'data' => $result];
    } catch (PDOException $e) {
        error_log("Error en getFlujoCaja: " . $e->getMessage());
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

// Nueva función para obtener ventas por turno
function getVentasPorTurno($user_id) {
    global $pdo;
    try {
        $query = "SELECT 
                    t.id as turno_id,
                    DATE_FORMAT(t.fecha_apertura, '%d/%m/%Y') as fecha,
                    t.fecha_apertura,
                    t.fecha_cierre,
                    t.monto_inicial,
                    t.monto_final,
                    COALESCE(SUM(v.total), 0) as total_ventas,
                    COUNT(DISTINCT v.id) as numero_ventas
                  FROM turnos t
                  LEFT JOIN ventas v ON v.turno_id = t.id 
                  WHERE t.user_id = ? 
                  AND t.fecha_cierre IS NOT NULL
                  GROUP BY t.id, t.fecha_apertura, t.fecha_cierre, t.monto_inicial, t.monto_final
                  ORDER BY t.fecha_apertura DESC
                  LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log para debugging
        error_log("Query Ventas por Turno: " . $query);
        error_log("User ID: " . $user_id);
        error_log("Resultados encontrados: " . count($result));
        error_log("Datos de ventas por turno: " . json_encode($result));
        
        // Si no hay resultados, intentamos obtener al menos los turnos básicos
        if (empty($result)) {
            $queryBasic = "SELECT 
                            id as turno_id,
                            DATE_FORMAT(fecha_apertura, '%d/%m/%Y') as fecha,
                            fecha_apertura,
                            fecha_cierre,
                            monto_inicial,
                            monto_final,
                            0 as total_ventas,
                            0 as numero_ventas
                          FROM turnos 
                          WHERE user_id = ?
                          AND fecha_cierre IS NOT NULL
                          ORDER BY fecha_apertura DESC
                          LIMIT 10";
            
            $stmtBasic = $pdo->prepare($queryBasic);
            $stmtBasic->execute([$user_id]);
            $result = $stmtBasic->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Resultados básicos encontrados: " . count($result));
        }
        
        return ['status' => true, 'data' => $result];
    } catch (PDOException $e) {
        error_log("Error en getVentasPorTurno: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error al obtener ventas por turno: ' . $e->getMessage()];
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

// Debug temporal - Mostrar en el HTML como comentario
echo "<!-- Debug Flujo Caja\n";
echo "Status: " . ($flujoCajaResult['status'] ? 'true' : 'false') . "\n";
echo "Cantidad de registros: " . (isset($flujoCajaResult['data']) ? count($flujoCajaResult['data']) : 0) . "\n";
echo "Datos:\n";
print_r($flujoCajaResult);
echo "\n-->";
$gastosPorCategoriaResult = getGastosPorCategoria($user_id);
$comparativaMensualResult = getComparativaMensual($user_id);

// Modificar la función getFlujoCajaMensual
function getFlujoCajaMensual($user_id) {
    global $pdo;
    try {
        $query = "SELECT 
                    DATE_FORMAT(t.fecha_apertura, '%Y-%m') as mes,
                    SUM(t.monto_final - t.monto_inicial) as ingresos,
                    SUM(CASE WHEN t.monto_final < t.monto_inicial THEN (t.monto_inicial - t.monto_final) ELSE 0 END) as egresos,
                    SUM(t.monto_final - t.monto_inicial) as flujo_neto
                  FROM turnos t
                  WHERE t.user_id = ? 
                  AND t.fecha_cierre IS NOT NULL
                  AND t.fecha_apertura >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                  GROUP BY DATE_FORMAT(t.fecha_apertura, '%Y-%m')
                  ORDER BY mes DESC
                  LIMIT 12";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log para debugging
        error_log("Query Flujo de Caja: " . $query);
        error_log("Resultados encontrados: " . count($result));
        
        // Si no hay resultados, crear datos de ejemplo para pruebas
        if (empty($result)) {
            $result = [];
            for ($i = 11; $i >= 0; $i--) {
                $date = date('Y-m', strtotime("-$i months"));
                $result[] = [
                    'mes' => $date,
                    'ingresos' => 0,
                    'egresos' => 0,
                    'flujo_neto' => 0
                ];
            }
        }

        return ['status' => true, 'data' => $result];
    } catch (PDOException $e) {
        error_log("Error en getFlujoCajaMensual: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error al obtener flujo de caja: ' . $e->getMessage()];
    }
}

// Modificar la parte donde se obtienen los datos
$flujoCajaResult = getFlujoCajaMensual($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-1 p-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-6">Panel de Reportes</h2>
            
            <!-- KPIs Principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Ticket Promedio -->
                <div class="bg-white rounded-xl shadow-md p-6 transition-transform hover:-translate-y-1">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-receipt text-green-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Ticket Promedio</p>
                            <p class="text-2xl font-bold text-gray-800">
                                $<?= number_format($kpisResult['data']['ticket_promedio'], 2) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Ventas Diarias -->
                <div class="bg-white rounded-xl shadow-md p-6 transition-transform hover:-translate-y-1">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-chart-line text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Ventas Diarias</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?= number_format($kpisResult['data']['ventas_diarias'], 1) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Clientes Únicos -->
                <div class="bg-white rounded-xl shadow-md p-6 transition-transform hover:-translate-y-1">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-users text-purple-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Clientes Únicos</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?= $kpisResult['data']['clientes_unicos'] ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Ingreso Diario -->
                <div class="bg-white rounded-xl shadow-md p-6 transition-transform hover:-translate-y-1">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-amber-100 rounded-full">
                            <i class="fas fa-dollar-sign text-amber-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Ingreso Diario</p>
                            <p class="text-2xl font-bold text-gray-800">
                                $<?= number_format($kpisResult['data']['ingreso_diario'], 2) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Balance General -->
            <div class="mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Balance General</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Ingresos Totales -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-green-100 rounded-full">
                                <i class="fas fa-plus-circle text-green-600 text-xl"></i>
                            </div>
                            <p class="text-sm text-gray-500">Ingresos Totales</p>
                        </div>
                        <p class="text-2xl font-bold text-gray-800">
                            $<?= number_format($balanceGeneral['total_ventas'] + $balanceGeneral['total_ingresos'], 2) ?>
                        </p>
                        <div class="mt-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Ventas</span>
                                <span class="text-green-600">$<?= number_format($balanceGeneral['total_ventas'], 2) ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Otros</span>
                                <span class="text-green-600">$<?= number_format($balanceGeneral['total_ingresos'], 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Egresos Totales -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-red-100 rounded-full">
                                <i class="fas fa-minus-circle text-red-600 text-xl"></i>
                            </div>
                            <p class="text-sm text-gray-500">Egresos Totales</p>
                        </div>
                        <p class="text-2xl font-bold text-gray-800">
                            $<?= number_format($balanceGeneral['total_egresos'], 2) ?>
                        </p>
                    </div>

                    <!-- Balance Final -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="p-3 bg-blue-100 rounded-full">
                                <i class="fas fa-equals text-blue-600 text-xl"></i>
                            </div>
                            <p class="text-sm text-gray-500">Balance Final</p>
                        </div>
                        <p class="text-2xl font-bold <?= $balanceGeneral['balance'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            $<?= number_format($balanceGeneral['balance'], 2) ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Gráficas Principales -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Tendencias de Ventas -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Tendencias de Ventas por Hora</h4>
                    <div class="h-80">
                        <canvas id="tendenciasVentasChart"></canvas>
                    </div>
                </div>

                <!-- Flujo de Caja -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Flujo de Caja Mensual</h4>
                    <div class="h-80">
                        <canvas id="flujoCajaChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Gráficas Secundarias -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Gastos por Categoría -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Desglose de Gastos por Categoría</h4>
                    <div class="h-80">
                        <canvas id="gastosCategoriaChart"></canvas>
                    </div>
                </div>

                <!-- Comparativa Mensual -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">Comparativa Mensual</h4>
                    <div class="h-80">
                        <canvas id="comparativaMensualChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Resumen Contable -->
            <div class="mb-8">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Resumen Contable</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Ingresos -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h4 class="font-semibold text-gray-800 mb-4">Ingresos</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Ventas</span>
                                <span class="font-medium text-green-600">
                                    $<?= number_format($balanceGeneral['total_ventas'], 2) ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Otros Ingresos</span>
                                <span class="font-medium text-green-600">
                                    $<?= number_format($balanceGeneral['total_ingresos'], 2) ?>
                                </span>
                            </div>
                            <div class="pt-3 border-t">
                                <div class="flex justify-between items-center font-semibold">
                                    <span>Total Ingresos</span>
                                    <span class="text-green-600">
                                        $<?= number_format($balanceGeneral['total_ventas'] + $balanceGeneral['total_ingresos'], 2) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Egresos -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h4 class="font-semibold text-gray-800 mb-4">Egresos</h4>
                        <div class="space-y-3">
                            <?php foreach ($gastosPorCategoriaResult['data'] as $gasto): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600"><?= htmlspecialchars($gasto['categoria']) ?></span>
                                <span class="font-medium text-red-600">
                                    $<?= number_format($gasto['total_gastos'], 2) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                            <div class="pt-3 border-t">
                                <div class="flex justify-between items-center font-semibold">
                                    <span>Total Egresos</span>
                                    <span class="text-red-600">
                                        $<?= number_format($balanceGeneral['total_egresos'], 2) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resultados -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h4 class="font-semibold text-gray-800 mb-4">Resultados</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Margen Bruto</span>
                                <span class="font-medium <?= $balanceGeneral['balance'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    $<?= number_format($balanceGeneral['balance'], 2) ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Rentabilidad</span>
                                <?php 
                                $rentabilidad = ($balanceGeneral['total_ventas'] > 0) 
                                    ? ($balanceGeneral['balance'] / $balanceGeneral['total_ventas']) * 100 
                                    : 0;
                                ?>
                                <span class="font-medium <?= $rentabilidad >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= number_format($rentabilidad, 1) ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts para las gráficas -->
    <script>
    // Configuración global de Chart.js para mejorar el aspecto
    Chart.defaults.font.family = "'Inter', 'system-ui', '-apple-system', sans-serif";
    Chart.defaults.color = '#4B5563';
    Chart.defaults.scale.grid.color = '#E5E7EB';

    // Paleta de colores personalizada
    const colors = {
        primary: {
            base: 'rgb(79, 70, 229)',
            light: 'rgba(79, 70, 229, 0.1)'
        },
        success: {
            base: 'rgb(16, 185, 129)',
            light: 'rgba(16, 185, 129, 0.1)'
        },
        warning: {
            base: 'rgb(245, 158, 11)',
            light: 'rgba(245, 158, 11, 0.1)'
        },
        danger: {
            base: 'rgb(239, 68, 68)',
            light: 'rgba(239, 68, 68, 0.1)'
        },
        info: {
            base: 'rgb(59, 130, 246)',
            light: 'rgba(59, 130, 246, 0.1)'
        }
    };

    // Opciones globales para las gráficas
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 20,
                    font: {
                        size: 12,
                        weight: '500'
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(17, 24, 39, 0.95)',
                titleFont: {
                    size: 13,
                    weight: '600'
                },
                bodyFont: {
                    size: 12
                },
                padding: 12,
                cornerRadius: 8,
                boxPadding: 6,
                usePointStyle: true
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        },
        scales: {
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: {
                        size: 11
                    }
                }
            },
            y: {
                grid: {
                    borderDash: [4, 4]
                },
                ticks: {
                    font: {
                        size: 11
                    }
                }
            }
        }
    };

    // Función para formatear números
    const formatNumber = (number) => {
        if (number >= 1000000) {
            return (number / 1000000).toFixed(1) + 'M';
        } else if (number >= 1000) {
            return (number / 1000).toFixed(1) + 'K';
        }
        return number.toFixed(0);
    };

    // Función para formatear moneda
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Tendencias de Ventas
        const ctxTendencias = document.getElementById('tendenciasVentasChart');
        const tendenciasData = <?= json_encode($tendenciasVentasResult['data'] ?? []) ?>;
        
        if (ctxTendencias && tendenciasData && tendenciasData.length > 0) {
            new Chart(ctxTendencias, {
                type: 'line',
                data: {
                    labels: tendenciasData.map(item => item.hora),
                    datasets: [{
                        label: 'Ventas',
                        data: tendenciasData.map(item => item.total_ventas),
                        borderColor: colors.primary.base,
                        backgroundColor: colors.primary.light,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }, {
                        label: 'Monto',
                        data: tendenciasData.map(item => item.monto_total),
                        borderColor: colors.success.base,
                        backgroundColor: colors.success.light,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    ...chartOptions,
                    scales: {
                        ...chartOptions.scales,
                        y: {
                            ...chartOptions.scales.y,
                            title: {
                                display: true,
                                text: 'Número de Ventas'
                            }
                        },
                        y1: {
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Monto Total'
                            },
                            ticks: {
                                callback: value => formatCurrency(value)
                            }
                        }
                    },
                    plugins: {
                        ...chartOptions.plugins,
                        tooltip: {
                            ...chartOptions.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.raw;
                                    if (label === 'Monto') {
                                        return `${label}: ${formatCurrency(value)}`;
                                    }
                                    return `${label}: ${value}`;
                                }
                            }
                        }
                    }
                }
            });
        } else if (ctxTendencias) {
            // Mostrar mensaje cuando no hay datos
            const ctx = ctxTendencias.getContext('2d');
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillStyle = '#666';
            ctx.fillText('No hay datos disponibles para mostrar', 
                ctxTendencias.width / 2, 
                ctxTendencias.height / 2
            );
        }

        // Flujo de Caja
        const ctxFlujoCaja = document.getElementById('flujoCajaChart');
        const flujoCajaData = <?= json_encode($flujoCajaResult['data'] ?? []) ?>;
        
        if (ctxFlujoCaja && flujoCajaData && flujoCajaData.length > 0) {
            new Chart(ctxFlujoCaja, {
                type: 'bar',
                data: {
                    labels: flujoCajaData.map(item => {
                        const fecha = new Date(item.mes + '-01');
                        return fecha.toLocaleDateString('es-ES', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Ingresos',
                        data: flujoCajaData.map(item => item.ingresos),
                        backgroundColor: colors.success.base,
                        borderRadius: 4,
                        stack: 'stack0'
                    }, {
                        label: 'Egresos',
                        data: flujoCajaData.map(item => -item.egresos),
                        backgroundColor: colors.danger.base,
                        borderRadius: 4,
                        stack: 'stack0'
                    }, {
                        label: 'Balance',
                        data: flujoCajaData.map(item => item.flujo_neto),
                        type: 'line',
                        borderColor: colors.info.base,
                        backgroundColor: colors.info.light,
                        fill: false,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    ...chartOptions,
                    scales: {
                        x: {
                            stacked: true
                        },
                        y: {
                            stacked: true,
                            ticks: {
                                callback: value => formatCurrency(value)
                            }
                        }
                    },
                    plugins: {
                        ...chartOptions.plugins,
                        tooltip: {
                            ...chartOptions.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = Math.abs(context.raw);
                                    return `${label}: ${formatCurrency(value)}`;
                                }
                            }
                        }
                    }
                }
            });
        } else if (ctxFlujoCaja) {
            const ctx = ctxFlujoCaja.getContext('2d');
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillStyle = '#666';
            ctx.fillText('No hay datos de flujo de caja disponibles', 
                ctxFlujoCaja.width / 2, 
                ctxFlujoCaja.height / 2
            );
        }

        // Gastos por Categoría
        const ctxGastos = document.getElementById('gastosCategoriaChart');
        const gastosData = <?= json_encode($gastosPorCategoriaResult['data'] ?? []) ?>;
        
        if (ctxGastos && gastosData && gastosData.length > 0) {
            new Chart(ctxGastos, {
                type: 'doughnut',
                data: {
                    labels: gastosData.map(item => item.categoria),
                    datasets: [{
                        data: gastosData.map(item => item.total_gastos),
                        backgroundColor: [
                            colors.primary.base,
                            colors.success.base,
                            colors.warning.base,
                            colors.danger.base,
                            colors.info.base
                        ]
                    }]
                },
                options: {
                    ...chartOptions,
                    cutout: '60%',
                    plugins: {
                        ...chartOptions.plugins,
                        tooltip: {
                            ...chartOptions.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        } else if (ctxGastos) {
            const ctx = ctxGastos.getContext('2d');
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillStyle = '#666';
            ctx.fillText('No hay datos de gastos disponibles', 
                ctxGastos.width / 2, 
                ctxGastos.height / 2
            );
        }

        // Comparativa Mensual
        const ctxComparativa = document.getElementById('comparativaMensualChart');
        const comparativaData = <?= json_encode($comparativaMensualResult['data'] ?? []) ?>;
        
        if (ctxComparativa && comparativaData && comparativaData.length > 0) {
            new Chart(ctxComparativa, {
                type: 'line',
                data: {
                    labels: comparativaData.map(item => {
                        const fecha = new Date(item.mes + '-01');
                        return fecha.toLocaleDateString('es-ES', { month: 'short', year: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Ventas',
                        data: comparativaData.map(item => item.ventas),
                        borderColor: colors.primary.base,
                        backgroundColor: colors.primary.light,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    }, {
                        label: 'Clientes',
                        data: comparativaData.map(item => item.num_clientes),
                        borderColor: colors.success.base,
                        backgroundColor: colors.success.light,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    ...chartOptions,
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            ticks: {
                                callback: value => formatCurrency(value)
                            }
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        } else if (ctxComparativa) {
            const ctx = ctxComparativa.getContext('2d');
            ctx.font = '14px Arial';
            ctx.textAlign = 'center';
            ctx.fillStyle = '#666';
            ctx.fillText('No hay datos comparativos disponibles', 
                ctxComparativa.width / 2, 
                ctxComparativa.height / 2
            );
        }
    });
    </script>
</body>
</html>
