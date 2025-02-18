<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Agregar esta función al inicio del archivo, después de la clase ApiResponse
function getMetodoPagoIcon($metodo_pago) {
    switch (strtolower($metodo_pago)) {
        case 'efectivo':
            return 'money-bill-wave';
        case 'transferencia':
            return 'exchange-alt';
        case 'tarjeta':
            return 'credit-card';
        case 'otro':
        default:
            return 'circle';
    }
}

// Funciones para manejar los turnos
function getTurnos($user_id, $limit = null, $offset = 0, $fecha = null) {
    global $pdo;
    try {
        $params = [':user_id' => $user_id];
        
        $query = "SELECT t.*, 
                  DATE_FORMAT(t.fecha_apertura, '%d/%m/%Y %H:%i') as fecha_apertura_formateada,
                  DATE_FORMAT(t.fecha_cierre, '%d/%m/%Y %H:%i') as fecha_cierre_formateada
                  FROM turnos t 
                  WHERE t.user_id = :user_id";
        
        if ($fecha) {
            $query .= " AND DATE(t.fecha_apertura) = :fecha";
            $params[':fecha'] = $fecha;
        }
        
        $query .= " ORDER BY t.fecha_apertura DESC";
        
        // Solo agregar LIMIT si se especifica
        if ($limit !== null) {
            $query .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }
        
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getTurnos: " . $e->getMessage());
        return [];
    }
}

function iniciarTurno($user_id, $data) {
    global $pdo;
    try {
        // Verificar si hay un turno activo
        $stmt = $pdo->prepare("SELECT id FROM turnos WHERE user_id = ? AND fecha_cierre IS NULL");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return ['status' => false, 'message' => 'Ya tienes un turno activo'];
        }

        $query = "INSERT INTO turnos (user_id, monto_inicial, fecha_apertura) 
                  VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$user_id, $data['monto_inicial']]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Turno iniciado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al iniciar el turno'];
    } catch (PDOException $e) {
        error_log("Error en iniciarTurno: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function cerrarTurno($user_id, $data) {
    global $pdo;
    try {
        // Obtener el turno activo
        $stmt = $pdo->prepare("SELECT id, monto_inicial FROM turnos WHERE user_id = ? AND fecha_cierre IS NULL");
        $stmt->execute([$user_id]);
        $turno = $stmt->fetch();
        
        if (!$turno) {
            return ['status' => false, 'message' => 'No hay turno activo para cerrar'];
        }

        // Calcular total de ventas durante el turno
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as total 
                              FROM ventas 
                              WHERE turno_id = ? AND anulada = 0");
        $stmt->execute([$turno['id']]);
        $total_ventas = $stmt->fetchColumn();

        // Calcular diferencia
        $diferencia = $data['monto_final'] - ($turno['monto_inicial'] + $total_ventas);

        // Actualizar turno
        $query = "UPDATE turnos SET 
                  monto_final = :monto_final,
                  diferencia = :diferencia,
                  fecha_cierre = NOW(),
                  observaciones = :observaciones
                  WHERE id = :turno_id";
                  
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':monto_final' => $data['monto_final'],
            ':diferencia' => $diferencia,
            ':observaciones' => $data['notas'],
            ':turno_id' => $turno['id']
        ]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Turno cerrado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al cerrar el turno'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

// Obtener el turno activo si existe
function getTurnoActivo($user_id) {
    global $pdo;
    try {
        $query = "SELECT t.*, 
                  DATE_FORMAT(t.fecha_apertura, '%d/%m/%Y %H:%i') as fecha_apertura_formateada
                  FROM turnos t 
                  WHERE t.user_id = ? AND t.fecha_cierre IS NULL";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getTurnoActivo: " . $e->getMessage());
        return null;
    }
}

// Modificar la función getDetallesTurno()
function getDetallesTurno($turno_id) {
    global $pdo;
    try {
        error_log("=== Iniciando getDetallesTurno para ID: " . $turno_id . " ===");

        // 1. Obtener información del turno
        $query = "SELECT t.*, 
                  DATE_FORMAT(t.fecha_apertura, '%d/%m/%Y %H:%i') as fecha_apertura_formateada,
                  DATE_FORMAT(t.fecha_cierre, '%d/%m/%Y %H:%i') as fecha_cierre_formateada,
                  u.nombre as nombre_usuario
                  FROM turnos t 
                  LEFT JOIN users u ON t.user_id = u.id
                  WHERE t.id = ?";
        
        error_log("Ejecutando query de turno: " . $query);
        $stmt = $pdo->prepare($query);
        $stmt->execute([$turno_id]);
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$turno) {
            error_log("No se encontró el turno con ID: " . $turno_id);
            return null;
        }

        // 2. Obtener ventas del turno
        $query = "SELECT v.*, 
                  DATE_FORMAT(v.fecha, '%d/%m/%Y %H:%i') as fecha_formateada,
                  COALESCE(c.nombre, 'Cliente General') as nombre_cliente
                  FROM ventas v
                  LEFT JOIN clientes c ON v.cliente_id = c.id
                  WHERE v.turno_id = ? AND v.anulada = 0
                  ORDER BY v.fecha DESC";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([$turno_id]);
        $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Obtener detalles de cada venta
        foreach ($ventas as &$venta) {
            $query = "SELECT 
                         vd.cantidad, 
                         vd.precio_unitario,
                         i.nombre as nombre_producto
                     FROM venta_detalles vd
                     LEFT JOIN inventario i ON vd.producto_id = i.id
                     WHERE vd.venta_id = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$venta['id']]);
            $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear campos monetarios de los detalles
            foreach ($detalles as &$detalle) {
                $detalle['precio_unitario'] = number_format(floatval($detalle['precio_unitario']), 2, '.', '');
            }
            
            $venta['detalles'] = $detalles;
        }

        // 4. Calcular totales
        $totales = [
            'total_ventas' => 0,
            'total_efectivo' => 0,
            'total_tarjeta' => 0,
            'total_transferencia' => 0,
            'cantidad_ventas' => count($ventas)
        ];

        foreach ($ventas as $venta) {
            $total = (float)str_replace(',', '', $venta['total']);
            $totales['total_ventas'] += $total;
            
            switch (strtolower($venta['metodo_pago'])) {
                case 'efectivo':
                    $totales['total_efectivo'] += $total;
                    break;
                case 'tarjeta':
                    $totales['total_tarjeta'] += $total;
                    break;
                case 'transferencia':
                    $totales['total_transferencia'] += $total;
                    break;
            }
        }

        // Formatear campos monetarios del turno
        $turno['monto_inicial'] = number_format((float)$turno['monto_inicial'], 2, '.', '');
        $turno['monto_final'] = $turno['monto_final'] ? number_format((float)$turno['monto_final'], 2, '.', '') : null;
        $turno['diferencia'] = $turno['diferencia'] ? number_format((float)$turno['diferencia'], 2, '.', '') : null;

        // Formatear totales
        foreach ($totales as $key => $value) {
            if ($key !== 'cantidad_ventas') {
                $totales[$key] = number_format($value, 2, '.', '');
            }
        }

        return [
            'turno' => $turno,
            'ventas' => $ventas,
            'totales' => $totales
        ];

    } catch (PDOException $e) {
        error_log("Error PDO en getDetallesTurno: " . $e->getMessage());
        error_log("Query que falló: " . $stmt->queryString);
        return null;
    }
}

// Agregar esta función de verificación
function verificarTurno($turno_id) {
    global $pdo;
    try {
        error_log("=== Iniciando verificarTurno para ID: " . $turno_id . " ===");
        
        // Verificar si el turno existe
        $stmt = $pdo->prepare("SELECT id, user_id FROM turnos WHERE id = ?");
        $stmt->execute([$turno_id]);
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$turno) {
            error_log("El turno " . $turno_id . " no existe en la base de datos");
            return false;
        }

        error_log("Turno encontrado: " . json_encode($turno));
        return true;

    } catch (PDOException $e) {
        error_log("Error en verificarTurno: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'iniciar_turno':
            $data = [
                'monto_inicial' => (float)trim($_POST['monto_inicial'])
            ];

            if ($data['monto_inicial'] <= 0) {
                ApiResponse::send(false, 'El monto inicial debe ser mayor que cero');
            }

            $result = iniciarTurno($user_id, $data);
            ApiResponse::send($result['status'], $result['message']);
            break;
            
        case 'cerrar_turno':
            $data = [
                'monto_final' => (float)trim($_POST['monto_final']),
                'notas' => trim($_POST['notas'] ?? '')
            ];

            if ($data['monto_final'] <= 0) {
                ApiResponse::send(false, 'El monto final debe ser mayor que cero');
            }

            $result = cerrarTurno($user_id, $data);
            ApiResponse::send($result['status'], $result['message']);
            break;
            
        case 'get_detalles_turno':
            $turno_id = (int)$_POST['turno_id'];
            error_log("=== Iniciando solicitud de detalles para el turno ID: " . $turno_id . " ===");
            
            try {
                // Primero verificamos si el turno existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM turnos WHERE id = ?");
                $stmt->execute([$turno_id]);
                $existe = $stmt->fetchColumn();
                
                if (!$existe) {
                    error_log("El turno ID: " . $turno_id . " no existe");
                    ApiResponse::send(false, 'El turno no existe');
                    exit;
                }

                error_log("Turno encontrado, obteniendo detalles...");
                
                // Obtener detalles básicos del turno
                $stmt = $pdo->prepare("
                    SELECT t.*, 
                           DATE_FORMAT(t.fecha_apertura, '%d/%m/%Y %H:%i') as fecha_apertura_formateada,
                           DATE_FORMAT(t.fecha_cierre, '%d/%m/%Y %H:%i') as fecha_cierre_formateada,
                           u.nombre as nombre_usuario
                    FROM turnos t 
                    LEFT JOIN users u ON t.user_id = u.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$turno_id]);
                $turno = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$turno) {
                    error_log("No se pudieron obtener los detalles del turno");
                    ApiResponse::send(false, 'No se pudieron obtener los detalles del turno');
                    exit;
                }

                // Obtener ventas asociadas al turno
                $stmt = $pdo->prepare("
                    SELECT v.*, 
                           DATE_FORMAT(v.fecha, '%d/%m/%Y %H:%i') as fecha_formateada,
                           COALESCE(c.nombre, 'Cliente General') as nombre_cliente
                    FROM ventas v
                    LEFT JOIN clientes c ON v.cliente_id = c.id
                    WHERE v.turno_id = ? AND v.anulada = 0
                    ORDER BY v.fecha DESC
                ");
                $stmt->execute([$turno_id]);
                $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Ventas encontradas: " . count($ventas));

                // Obtener detalles de cada venta
                foreach ($ventas as &$venta) {
                    $stmt = $pdo->prepare("
                        SELECT vd.*, i.nombre as nombre_producto
                        FROM venta_detalles vd
                        LEFT JOIN inventario i ON vd.producto_id = i.id
                        WHERE vd.venta_id = ?
                    ");
                    $stmt->execute([$venta['id']]);
                    $venta['detalles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                // Calcular totales
                $totales = [
                    'total_ventas' => 0,
                    'total_efectivo' => 0,
                    'total_tarjeta' => 0,
                    'total_transferencia' => 0,
                    'cantidad_ventas' => count($ventas)
                ];

                foreach ($ventas as $venta) {
                    $total = floatval($venta['total']);
                    $totales['total_ventas'] += $total;
                    
                    switch (strtolower($venta['metodo_pago'])) {
                        case 'efectivo':
                            $totales['total_efectivo'] += $total;
                            break;
                        case 'tarjeta':
                            $totales['total_tarjeta'] += $total;
                            break;
                        case 'transferencia':
                            $totales['total_transferencia'] += $total;
                            break;
                    }
                }

                // Formatear valores monetarios
                $turno['monto_inicial'] = number_format(floatval($turno['monto_inicial']), 2, '.', '');
                $turno['monto_final'] = $turno['monto_final'] ? number_format(floatval($turno['monto_final']), 2, '.', '') : null;
                $turno['diferencia'] = $turno['diferencia'] ? number_format(floatval($turno['diferencia']), 2, '.', '') : null;

                foreach ($totales as $key => $value) {
                    if ($key !== 'cantidad_ventas') {
                        $totales[$key] = number_format($value, 2, '.', '');
                    }
                }

                $resultado = [
                    'turno' => $turno,
                    'ventas' => $ventas,
                    'totales' => $totales
                ];

                error_log("Detalles obtenidos exitosamente");
                ApiResponse::send(true, 'Detalles obtenidos correctamente', $resultado);
                
            } catch (PDOException $e) {
                error_log("Error PDO: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                ApiResponse::send(false, 'Error en la base de datos: ' . $e->getMessage());
            } catch (Exception $e) {
                error_log("Error general: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                ApiResponse::send(false, 'Error al procesar la solicitud: ' . $e->getMessage());
            }
            break;
            
        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

// Obtener datos para la vista
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : null;

// Si hay filtro de fecha, usar paginación, si no, mostrar todos
if ($fecha_filtro) {
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $turnos = getTurnos($user_id, $limit, $offset, $fecha_filtro);
} else {
    // Mostrar todos los turnos sin límite
    $turnos = getTurnos($user_id, null, 0, null);
}

$turno_activo = getTurnoActivo($user_id);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnos | Numercia</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Animaciones personalizadas */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
        .transition-all-custom {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</head>
<body class="bg-gray-50 font-[Poppins]">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content ml-64 animate-fade-in">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-cash-register text-emerald-500"></i>
                    Gestión de Turnos
                </h2>
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="/" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-home mr-1"></i> Inicio
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fas fa-chevron-right text-gray-400 mx-2 text-sm"></i>
                                <span class="text-gray-500">Turnos</span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>
            
            <?php if ($turno_activo): ?>
            <!-- Tarjeta de Turno Activo -->
            <div class="bg-gradient-to-r from-emerald-500 to-green-600 rounded-xl shadow-lg p-6 mb-8 transform hover:scale-[1.01] transition-all-custom">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-clock mr-2 animate-pulse"></i> Turno Activo
                    </h3>
                    <span class="bg-emerald-400/30 text-white px-4 py-1.5 rounded-full text-sm font-medium backdrop-blur-sm">
                        En curso
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 hover:bg-white/20 transition-colors duration-200">
                        <p class="text-white/80 text-sm mb-1 font-medium">Inicio del Turno</p>
                        <p class="text-white font-medium flex items-center gap-2">
                            <i class="fas fa-calendar-alt"></i>
                            <?= $turno_activo['fecha_apertura_formateada'] ?>
                        </p>
                    </div>
                    
                    <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 hover:bg-white/20 transition-colors duration-200">
                        <p class="text-white/80 text-sm mb-1 font-medium">Monto Inicial</p>
                        <p class="text-white font-medium flex items-center gap-2">
                            <i class="fas fa-dollar-sign"></i>
                            <?= number_format($turno_activo['monto_inicial'], 2) ?>
                        </p>
                    </div>
                    
                    <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 hover:bg-white/20 transition-colors duration-200">
                        <p class="text-white/80 text-sm mb-1 font-medium">Tiempo Transcurrido</p>
                        <p class="text-white font-medium flex items-center gap-2" id="tiempoTranscurrido">
                            <i class="fas fa-hourglass-half"></i>
                            Calculando...
                        </p>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button onclick="cerrarTurno()" 
                            class="bg-red-500 hover:bg-red-600 text-white px-6 py-2.5 rounded-lg 
                                   flex items-center transition-all duration-200 hover:shadow-lg
                                   focus:ring-4 focus:ring-red-500/30">
                        <i class="fas fa-stop-circle mr-2"></i> 
                        Cerrar Turno
                    </button>
                </div>
            </div>
            <?php else: ?>
            <!-- Botón para Iniciar Turno -->
            <div class="bg-white rounded-xl shadow-md p-8 mb-8 transform hover:shadow-lg transition-all-custom">
                <div class="text-center max-w-lg mx-auto">
                    <div class="bg-emerald-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-cash-register text-3xl text-emerald-500"></i>
                    </div>
                    <h3 class="text-xl font-medium text-gray-800 mb-3">No hay turno activo</h3>
                    <p class="text-gray-600 mb-6">
                        Inicia un nuevo turno para comenzar a registrar ventas y mantener un control preciso de tus operaciones
                    </p>
                    <button onclick="iniciarTurno()" 
                            class="bg-emerald-500 hover:bg-emerald-600 text-white px-8 py-3 rounded-lg 
                                   flex items-center justify-center mx-auto transition-all duration-200
                                   hover:shadow-lg focus:ring-4 focus:ring-emerald-500/30 group">
                        <i class="fas fa-play-circle mr-2 group-hover:rotate-[360deg] transition-transform duration-500"></i>
                        Iniciar Turno
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Historial de Turnos -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                    <h4 class="text-lg font-medium text-gray-800 flex items-center gap-2">
                        <i class="fas fa-history text-emerald-500"></i>
                        Historial de Turnos
                    </h4>
                    <form id="filtroFechas" class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600 font-medium">Fecha:</label>
                            <div class="relative">
                                <input type="date" 
                                       id="fecha" 
                                       name="fecha"
                                       value="<?= $fecha_filtro ?? '' ?>"
                                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 
                                              focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-200">
                                <i class="fas fa-calendar-alt absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>
                        <button type="submit"
                                class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg 
                                       flex items-center gap-2 transition-all duration-200 hover:shadow-md
                                       focus:ring-4 focus:ring-emerald-500/30">
                            <i class="fas fa-filter"></i>
                            Filtrar
                        </button>
                        <?php if ($fecha_filtro): ?>
                        <button type="button"
                                onclick="limpiarFiltro()"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-lg 
                                       flex items-center gap-2 transition-all duration-200
                                       focus:ring-4 focus:ring-gray-200">
                            <i class="fas fa-times"></i>
                            Mostrar Todo
                        </button>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-100">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha Apertura
                                </th>
                                <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha Cierre
                                </th>
                                <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Monto Inicial
                                </th>
                                <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Monto Final
                                </th>
                                <th class="px-6 py-3.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th class="px-6 py-3.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($turnos)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500">
                                        <i class="fas fa-receipt text-4xl mb-3"></i>
                                        <p class="text-gray-500 mb-1">No hay turnos registrados</p>
                                        <p class="text-sm text-gray-400">Los turnos aparecerán aquí una vez iniciados</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($turnos as $turno): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-calendar-day text-emerald-500"></i>
                                            <?= htmlspecialchars($turno['fecha_apertura_formateada']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-calendar-check text-emerald-500"></i>
                                            <?= $turno['fecha_cierre_formateada'] ?? '-' ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-dollar-sign text-emerald-500"></i>
                                            <?= number_format($turno['monto_inicial'], 2) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-dollar-sign text-emerald-500"></i>
                                            <?= $turno['monto_final'] ? number_format($turno['monto_final'], 2) : '-' ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($turno['fecha_cierre']): ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                <i class="fas fa-lock mr-1"></i> Cerrado
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-800">
                                                <i class="fas fa-clock mr-1"></i> Activo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick="verDetallesTurno(<?= $turno['id'] ?>)" 
                                                class="text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100
                                                       p-2 rounded-lg transition-colors duration-200">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Actualizar el tiempo transcurrido cada minuto
        function actualizarTiempoTranscurrido() {
            const fechaInicio = new Date('<?= $turno_activo['fecha_apertura'] ?? '' ?>');
            const ahora = new Date();
            const diff = ahora - fechaInicio;
            
            const horas = Math.floor(diff / (1000 * 60 * 60));
            const minutos = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            document.getElementById('tiempoTranscurrido').textContent = 
                `${horas}h ${minutos}m`;
        }

        if (document.getElementById('tiempoTranscurrido')) {
            actualizarTiempoTranscurrido();
            setInterval(actualizarTiempoTranscurrido, 60000);
        }

        // Función para iniciar turno con nuevo diseño
        function iniciarTurno() {
            Swal.fire({
                title: 'Iniciar Nuevo Turno',
                html: `
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Monto Inicial en Caja
                        </label>
                        <input type="number" 
                               id="monto_inicial" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 
                                      focus:border-emerald-500" 
                               placeholder="0.00" 
                               step="0.01" 
                               min="0">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Iniciar Turno',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10B981',
                customClass: {
                    container: 'font-sans'
                },
                preConfirm: () => {
                    const montoInicial = document.getElementById('monto_inicial').value;
                    if (!montoInicial || montoInicial <= 0) {
                        Swal.showValidationMessage('Por favor ingrese un monto inicial válido');
                        return false;
                    }
                    return { monto_inicial: montoInicial };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'iniciar_turno');
                    formData.append('monto_inicial', result.value.monto_inicial);

                    fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            Swal.fire('¡Éxito!', data.message, 'success')
                            .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Ocurrió un error al procesar la solicitud', 'error');
                    });
                }
            });
        }

        // Función para cerrar turno con nuevo diseño
        function cerrarTurno() {
            Swal.fire({
                title: 'Cerrar Turno',
                html: `
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Monto Final en Caja
                            </label>
                            <input type="number" 
                                   id="monto_final" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 
                                          focus:border-emerald-500" 
                                   placeholder="0.00" 
                                   step="0.01" 
                                   min="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Observaciones
                            </label>
                            <textarea id="notas" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-emerald-500 
                                             focus:border-emerald-500" 
                                      rows="3" 
                                      placeholder="Observaciones del turno..."></textarea>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Cerrar Turno',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#EF4444',
                customClass: {
                    container: 'font-sans'
                },
                preConfirm: () => {
                    const montoFinal = document.getElementById('monto_final').value;
                    const notas = document.getElementById('notas').value;
                    
                    if (!montoFinal || montoFinal <= 0) {
                        Swal.showValidationMessage('Por favor ingrese un monto final válido');
                        return false;
                    }
                    
                    return { 
                        monto_final: montoFinal,
                        notas: notas
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'cerrar_turno');
                    formData.append('monto_final', result.value.monto_final);
                    formData.append('notas', result.value.notas);

                    fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            Swal.fire('¡Éxito!', data.message, 'success')
                            .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Ocurrió un error al procesar la solicitud', 'error');
                    });
                }
            });
        }

        // Agregar esta función al inicio del script
        function getMetodoPagoIcon(metodo_pago) {
            switch (String(metodo_pago).toLowerCase()) {
                case 'efectivo':
                    return 'money-bill-wave';
                case 'transferencia':
                    return 'exchange-alt';
                case 'tarjeta':
                    return 'credit-card';
                case 'otro':
                default:
                    return 'circle';
            }
        }

        // Actualizar la función verDetallesTurno para mostrar mejor los detalles
        function verDetallesTurno(turno_id) {
            const formData = new FormData();
            formData.append('action', 'get_detalles_turno');
            formData.append('turno_id', turno_id);

            fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (!data.status) {
                    throw new Error(data.message);
                }

                const turno = data.data.turno;
                const ventas = data.data.ventas;
                const totales = data.data.totales;

                Swal.fire({
                    title: 'Detalles del Turno',
                    html: `
                        <div class="turno-detalles">
                            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-600">Apertura</p>
                                        <p class="font-medium">${turno.fecha_apertura_formateada}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Cierre</p>
                                        <p class="font-medium">${turno.fecha_cierre_formateada || 'En curso'}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Responsable</p>
                                        <p class="font-medium">${turno.nombre_usuario || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <div class="bg-white p-4 rounded-lg shadow">
                                    <p class="text-sm text-gray-600">Monto Inicial</p>
                                    <p class="text-lg font-semibold text-gray-900">$${turno.monto_inicial}</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg shadow">
                                    <p class="text-sm text-gray-600">Monto Final</p>
                                    <p class="text-lg font-semibold text-gray-900">$${turno.monto_final || '-'}</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg shadow ${parseFloat(turno.diferencia || 0) < 0 ? 'bg-red-50' : ''}">
                                    <p class="text-sm text-gray-600">Diferencia</p>
                                    <p class="text-lg font-semibold ${parseFloat(turno.diferencia || 0) < 0 ? 'text-red-600' : 'text-gray-900'}">
                                        $${turno.diferencia || '0.00'}
                                    </p>
                                </div>
                            </div>

                            <div class="bg-white p-4 rounded-lg shadow mb-6">
                                <h4 class="text-lg font-medium mb-4">Resumen de Ventas</h4>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="text-center">
                                        <i class="fas fa-cash-register text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-600">Total Ventas</p>
                                        <p class="text-lg font-semibold text-emerald-600">$${totales.total_ventas}</p>
                                    </div>
                                    <div class="text-center">
                                        <i class="fas fa-money-bill-wave text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-600">Efectivo</p>
                                        <p class="text-lg font-semibold text-emerald-600">$${totales.total_efectivo}</p>
                                    </div>
                                    <div class="text-center">
                                        <i class="fas fa-credit-card text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-600">Tarjeta</p>
                                        <p class="text-lg font-semibold text-emerald-600">$${totales.total_tarjeta}</p>
                                    </div>
                                    <div class="text-center">
                                        <i class="fas fa-exchange-alt text-2xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-600">Transferencia</p>
                                        <p class="text-lg font-semibold text-emerald-600">$${totales.total_transferencia}</p>
                                    </div>
                                </div>
                            </div>

                            ${ventas && ventas.length > 0 ? `
                                <div class="space-y-4">
                                    <h4 class="text-lg font-medium">Detalle de Ventas (${totales.cantidad_ventas})</h4>
                                    ${ventas.map(venta => `
                                        <div class="bg-white p-4 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                                            <div class="flex flex-wrap justify-between items-center mb-3 pb-2 border-b border-gray-100">
                                                <div class="flex items-center space-x-2">
                                                    <span class="bg-emerald-100 text-emerald-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                                        Factura #${venta.numero_factura || 'N/A'}
                                                    </span>
                                                    <span class="text-sm text-gray-500">${venta.fecha_formateada}</span>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <i class="fas fa-${getMetodoPagoIcon(venta.metodo_pago)} text-gray-400"></i>
                                                    <span class="text-sm font-medium text-gray-600">${venta.metodo_pago}</span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <span class="text-sm text-gray-600">Cliente:</span>
                                                <span class="text-sm font-medium text-gray-800 ml-1">${venta.nombre_cliente}</span>
                                            </div>

                                            <div class="overflow-x-auto rounded-lg border border-gray-100">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                Producto
                                                            </th>
                                                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                Cantidad
                                                            </th>
                                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                                Precio Unit.
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        ${venta.detalles.map(detalle => `
                                                            <tr class="hover:bg-gray-50">
                                                                <td class="px-4 py-2.5 text-sm text-gray-900">
                                                                    ${detalle.nombre_producto || 'N/A'}
                                                                </td>
                                                                <td class="px-4 py-2.5 text-sm text-gray-900 text-center">
                                                                    <span class="inline-flex items-center justify-center bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                                                        ${detalle.cantidad}
                                                                    </span>
                                                                </td>
                                                                <td class="px-4 py-2.5 text-sm text-gray-900 text-right font-medium">
                                                                    $${detalle.precio_unitario}
                                                                </td>
                                                            </tr>
                                                        `).join('')}
                                                    </tbody>
                                                    <tfoot class="bg-gray-50">
                                                        <tr>
                                                            <td colspan="2" class="px-4 py-3 text-right text-sm font-medium text-gray-900">
                                                                Total:
                                                            </td>
                                                            <td class="px-4 py-3 text-right text-sm font-bold text-emerald-600">
                                                                $${venta.total}
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : '<div class="text-center py-8 bg-gray-50 rounded-lg"><i class="fas fa-receipt text-gray-400 text-4xl mb-3"></i><p class="text-gray-500">No hay ventas registradas en este turno</p></div>'}

                            ${turno.observaciones ? `
                                <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                    <h4 class="text-lg font-medium mb-2">Observaciones</h4>
                                    <p class="text-gray-600">${turno.observaciones}</p>
                                </div>
                            ` : ''}
                        </div>
                    `,
                    width: '900px',
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        container: 'turno-detalles-modal',
                        popup: 'swal2-popup-large'
                    }
                });
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'No se pudieron cargar los detalles del turno'
                });
            });
        }

        // Agregar estas nuevas funciones al script existente
        
        // Función para limpiar los filtros
        function limpiarFiltro() {
            window.location.href = window.location.pathname;
        }

        // Actualizar la validación del formulario de filtro
        document.getElementById('filtroFechas').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fecha = document.getElementById('fecha').value;
            if (!fecha) {
                // Si no hay fecha seleccionada, mostrar todos los turnos
                window.location.href = window.location.pathname;
                return;
            }

            this.submit();
        });

        // Actualizar la función de limpiar filtro
        function limpiarFiltro() {
            window.location.href = window.location.pathname;
        }

        // Establecer fecha máxima como hoy
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('fecha').max = today;
    </script>
</body>
</html>