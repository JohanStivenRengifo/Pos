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
function getTurnos($user_id, $limit = 10, $offset = 0) {
    global $pdo;
    try {
        $query = "SELECT t.*, 
                  DATE_FORMAT(t.fecha_apertura, '%d/%m/%Y %H:%i') as fecha_apertura_formateada,
                  DATE_FORMAT(t.fecha_cierre, '%d/%m/%Y %H:%i') as fecha_cierre_formateada
                  FROM turnos t 
                  WHERE t.user_id = :user_id 
                  ORDER BY t.fecha_apertura DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
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
            $query = "SELECT vd.cantidad, vd.precio_unitario, 
                     i.nombre as nombre_producto,
                     (vd.cantidad * vd.precio_unitario) as subtotal  -- Calculamos el subtotal directamente en la consulta
                     FROM venta_detalles vd
                     LEFT JOIN inventario i ON vd.producto_id = i.id
                     WHERE vd.venta_id = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$venta['id']]);
            $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formatear campos monetarios de los detalles
            foreach ($detalles as &$detalle) {
                // Asegurarnos de que los valores sean numéricos antes de formatearlos
                $precio = floatval($detalle['precio_unitario']);
                $cantidad = floatval($detalle['cantidad']);
                $subtotal = floatval($detalle['subtotal']);

                $detalle['precio_unitario'] = number_format($precio, 2, '.', '');
                $detalle['subtotal'] = number_format($subtotal, 2, '.', '');
            }
            
            $venta['detalles'] = $detalles;
            $venta['total'] = number_format((float)$venta['total'], 2, '.', '');
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
$limit = 10;
$offset = ($page - 1) * $limit;

$turnos = getTurnos($user_id, $limit, $offset);
$turno_activo = getTurnoActivo($user_id);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnos | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        .turno-activo-card {
            background: linear-gradient(145deg, #2ecc71, #27ae60);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .turno-activo-card h3 {
            margin: 0 0 15px 0;
            font-size: 1.5rem;
        }

        .turno-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .turno-info-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
        }

        .turno-info-item strong {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .turno-info-item span {
            font-size: 1.2rem;
            font-weight: 500;
        }

        .turno-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-turno {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-iniciar {
            background: #2ecc71;
            color: white;
        }

        .btn-cerrar {
            background: #e74c3c;
            color: white;
        }

        .turnos-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
        }

        .turnos-table th {
            background: #f8f9fa;
            padding: 15px;
            font-weight: 600;
            color: #344767;
            text-align: left;
        }

        .turnos-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .estado-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .estado-activo {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .estado-cerrado {
            background: #ffebee;
            color: #c62828;
        }

        .monto {
            font-family: 'Poppins', monospace;
            font-weight: 500;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .turno-detalles {
            text-align: left;
            max-height: 80vh;
            overflow-y: auto;
            padding: 20px;
        }

        .turno-header {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .info-grupo {
            display: flex;
            flex-direction: column;
        }

        .turno-montos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .monto-item {
            text-align: center;
        }

        .monto-item strong {
            display: block;
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .monto-item span {
            font-size: 1.2rem;
            font-weight: 500;
            color: #2ecc71;
        }

        .diferencia-negativa span {
            color: #e74c3c;
        }

        .resumen-ventas {
            margin-bottom: 25px;
        }

        .totales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }

        .total-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .total-item i {
            font-size: 1.5rem;
            color: #3498db;
            margin-bottom: 10px;
        }

        .total-item strong {
            display: block;
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .total-item span {
            font-size: 1.1rem;
            font-weight: 500;
            color: #2ecc71;
        }

        .ventas-lista {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .venta-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .venta-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .detalles-table {
            width: 100%;
            margin: 10px 0;
            border-collapse: collapse;
        }

        .detalles-table th,
        .detalles-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .venta-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e9ecef;
        }

        .metodo-pago {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #6c757d;
        }

        .total {
            color: #2ecc71;
        }

        .observaciones {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .no-ventas {
            text-align: center;
            color: #6c757d;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-body">
            <h2>Gestión de Turnos</h2>
            
            <?php if ($turno_activo): ?>
            <!-- Tarjeta de Turno Activo -->
            <div class="turno-activo-card">
                <h3><i class="fas fa-clock"></i> Turno Activo</h3>
                <div class="turno-info">
                    <div class="turno-info-item">
                        <strong>Inicio del Turno</strong>
                        <span><?= $turno_activo['fecha_apertura_formateada'] ?></span>
                    </div>
                    <div class="turno-info-item">
                        <strong>Monto Inicial</strong>
                        <span class="monto">$<?= $turno_activo['monto_inicial_formateado'] ?></span>
                    </div>
                </div>
                <div class="turno-actions">
                    <button onclick="cerrarTurno()" class="btn-turno btn-cerrar">
                        <i class="fas fa-stop-circle"></i> Cerrar Turno
                    </button>
                </div>
            </div>
            <?php else: ?>
            <!-- Botón para Iniciar Turno -->
            <div class="promo_card">
                <h1>No hay turno activo</h1>
                <span>Inicia un nuevo turno para comenzar a registrar ventas.</span>
                <button onclick="iniciarTurno()" class="btn-turno btn-iniciar">
                    <i class="fas fa-play-circle"></i> Iniciar Turno
                </button>
            </div>
            <?php endif; ?>

            <!-- Historial de Turnos -->
            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Historial de Turnos</h4>
                        <div class="search-box">
                            <input type="text" id="searchTurnos" placeholder="Buscar turnos...">
                        </div>
                    </div>
                    <table class="turnos-table">
                        <thead>
                            <tr>
                                <th>Fecha Apertura</th>
                                <th>Fecha Cierre</th>
                                <th>Monto Inicial</th>
                                <th>Monto Final</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($turnos)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No hay turnos registrados</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($turnos as $turno): ?>
                                <tr>
                                    <td><?= htmlspecialchars($turno['fecha_apertura_formateada']) ?></td>
                                    <td><?= $turno['fecha_cierre_formateada'] ?? '-' ?></td>
                                    <td class="monto">$<?= number_format($turno['monto_inicial'], 2) ?></td>
                                    <td class="monto">$<?= $turno['monto_final'] ? number_format($turno['monto_final'], 2) : '-' ?></td>
                                    <td>
                                        <button onclick="verDetallesTurno(<?= $turno['id'] ?>)" class="btn-icon view">
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
        // Función para iniciar turno
        function iniciarTurno() {
            Swal.fire({
                title: 'Iniciar Nuevo Turno',
                html: `
                    <div class="form-group">
                        <label>Monto Inicial en Caja</label>
                        <input type="number" id="monto_inicial" class="swal2-input" placeholder="0.00" step="0.01" min="0">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Iniciar Turno',
                cancelButtonText: 'Cancelar',
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

        // Función para cerrar turno
        function cerrarTurno() {
            Swal.fire({
                title: 'Cerrar Turno',
                html: `
                    <div class="form-group">
                        <label>Monto Final en Caja</label>
                        <input type="number" id="monto_final" class="swal2-input" placeholder="0.00" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Notas</label>
                        <textarea id="notas" class="swal2-textarea" placeholder="Observaciones del turno..."></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Cerrar Turno',
                cancelButtonText: 'Cancelar',
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

        // Función para ver detalles del turno
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

                let ventasHtml = '';
                if (ventas && ventas.length > 0) {
                    ventasHtml = `
                        <div class="ventas-lista">
                            ${ventas.map(venta => `
                                <div class="venta-item">
                                    <div class="venta-header">
                                        <strong>Factura #${venta.numero_factura || 'N/A'}</strong>
                                        <span>${venta.fecha_formateada}</span>
                                    </div>
                                    <div class="venta-cliente">Cliente: ${venta.nombre_cliente || 'Cliente General'}</div>
                                    ${venta.detalles && venta.detalles.length > 0 ? `
                                        <div class="venta-detalles">
                                            <table class="detalles-table">
                                                <thead>
                                                    <tr>
                                                        <th>Producto</th>
                                                        <th>Cantidad</th>
                                                        <th>Precio</th>
                                                        <th>Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${venta.detalles.map(detalle => `
                                                        <tr>
                                                            <td>${detalle.nombre_producto || 'N/A'}</td>
                                                            <td>${detalle.cantidad}</td>
                                                            <td>$${detalle.precio_unitario}</td>
                                                            <td>$${detalle.subtotal}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    ` : '<p>No hay detalles disponibles</p>'}
                                    <div class="venta-footer">
                                        <span class="metodo-pago">
                                            <i class="fas fa-${getMetodoPagoIcon(venta.metodo_pago)}"></i>
                                            ${venta.metodo_pago || 'N/A'}
                                        </span>
                                        <strong class="total">Total: $${venta.total}</strong>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                } else {
                    ventasHtml = '<p class="no-ventas">No se registraron ventas durante este turno</p>';
                }

                Swal.fire({
                    title: 'Detalles del Turno',
                    html: `
                        <div class="turno-detalles">
                            <div class="turno-header">
                                <div class="info-grupo">
                                    <strong>Apertura:</strong>
                                    <span>${turno.fecha_apertura_formateada}</span>
                                </div>
                                <div class="info-grupo">
                                    <strong>Cierre:</strong>
                                    <span>${turno.fecha_cierre_formateada || 'En curso'}</span>
                                </div>
                                <div class="info-grupo">
                                    <strong>Responsable:</strong>
                                    <span>${turno.nombre_usuario || 'N/A'}</span>
                                </div>
                            </div>
                            
                            <div class="turno-montos">
                                <div class="monto-item">
                                    <strong>Monto Inicial:</strong>
                                    <span>$${turno.monto_inicial}</span>
                                </div>
                                <div class="monto-item">
                                    <strong>Monto Final:</strong>
                                    <span>$${turno.monto_final || '-'}</span>
                                </div>
                                <div class="monto-item ${parseFloat(turno.diferencia || 0) < 0 ? 'diferencia-negativa' : ''}">
                                    <strong>Diferencia:</strong>
                                    <span>$${turno.diferencia || '0.00'}</span>
                                </div>
                            </div>

                            <div class="resumen-ventas">
                                <h4>Resumen de Ventas</h4>
                                <div class="totales-grid">
                                    <div class="total-item">
                                        <i class="fas fa-cash-register"></i>
                                        <strong>Total Ventas</strong>
                                        <span>$${totales.total_ventas}</span>
                                    </div>
                                    <div class="total-item">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <strong>Efectivo</strong>
                                        <span>$${totales.total_efectivo}</span>
                                    </div>
                                    <div class="total-item">
                                        <i class="fas fa-credit-card"></i>
                                        <strong>Tarjeta</strong>
                                        <span>$${totales.total_tarjeta}</span>
                                    </div>
                                    <div class="total-item">
                                        <i class="fas fa-exchange-alt"></i>
                                        <strong>Transferencia</strong>
                                        <span>$${totales.total_transferencia}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="ventas-seccion">
                                <h4>Detalle de Ventas (${totales.cantidad_ventas})</h4>
                                ${ventasHtml}
                            </div>

                            ${turno.observaciones ? `
                                <div class="observaciones">
                                    <h4>Observaciones</h4>
                                    <p>${turno.observaciones}</p>
                                </div>
                            ` : ''}
                        </div>
                    `,
                    width: '800px',
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        container: 'turno-detalles-modal'
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

        // Función auxiliar para obtener el icono del método de pago
        function getMetodoPagoIcon(metodo_pago) {
            switch (String(metodo_pago).toLowerCase()) {
                case 'efectivo':
                    return 'money-bill-wave';
                case 'transferencia':
                    return 'exchange-alt';
                case 'tarjeta':
                    return 'credit-card';
                default:
                    return 'circle';
            }
        }

        // Búsqueda en la tabla
        document.getElementById('searchTurnos').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.querySelector('.turnos-table tbody');
            const rows = table.getElementsByTagName('tr');

            Array.from(rows).forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html>