<?php
// Evitar que se muestren errores en la salida
error_reporting(0);
ini_set('display_errors', 0);

// Iniciar sesión y establecer headers
session_start();
header('Content-Type: application/json');

// Verificar que el archivo existe antes de incluirlo
if (!file_exists('../../config/database.php')) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de configuración: No se encuentra el archivo de conexión'
    ]);
    exit;
}

try {
    require_once '../../config/database.php';

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['turno_actual'])) {
        echo json_encode([
            'success' => false,
            'message' => 'No hay un turno activo'
        ]);
        exit;
    }

    // Verificar que la conexión a la base de datos existe
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Obtener información del turno actual
    $stmt = $pdo->prepare("
        SELECT t.monto_inicial, t.fecha_apertura
        FROM turnos t
        WHERE t.id = ? AND t.user_id = ? AND t.fecha_cierre IS NULL
    ");
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta del turno');
    }

    $stmt->execute([$_SESSION['turno_actual'], $_SESSION['user_id']]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el turno activo'
        ]);
        exit;
    }

    // Obtener total de ventas del turno
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE 
                WHEN metodo_pago = 'efectivo' THEN total 
                ELSE 0 
            END), 0) as total_efectivo,
            COALESCE(SUM(CASE 
                WHEN metodo_pago = 'tarjeta' THEN total 
                ELSE 0 
            END), 0) as total_tarjeta,
            COALESCE(SUM(CASE 
                WHEN metodo_pago = 'transferencia' THEN total 
                ELSE 0 
            END), 0) as total_transferencia,
            COUNT(*) as total_ventas,
            COALESCE(SUM(total), 0) as total_general
        FROM ventas 
        WHERE turno_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta de ventas');
    }

    $stmt->execute([$_SESSION['turno_actual']]);
    $totales = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($totales === false) {
        throw new Exception('Error al obtener los totales de ventas');
    }

    echo json_encode([
        'success' => true,
        'monto_inicial' => floatval($turno['monto_inicial']),
        'fecha_apertura' => $turno['fecha_apertura'],
        'total' => floatval($totales['total_general']),
        'detalles' => [
            'efectivo' => floatval($totales['total_efectivo']),
            'tarjeta' => floatval($totales['total_tarjeta']),
            'transferencia' => floatval($totales['total_transferencia']),
            'total_ventas' => intval($totales['total_ventas'])
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error PDO en obtener_total.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error general en obtener_total.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 