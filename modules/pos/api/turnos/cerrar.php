<?php
// Evitar que se muestren errores en la salida
error_reporting(0);
ini_set('display_errors', 0);

// Iniciar sesión y establecer headers
session_start();
header('Content-Type: application/json');

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['turno_actual'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No hay un turno activo'
    ]);
    exit;
}

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

    // Verificar que la conexión a la base de datos existe
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Verificar que se recibieron los datos necesarios
    if (!isset($data['monto_final'])) {
        throw new Exception('El monto final es requerido');
    }

    $monto_final = floatval($data['monto_final']);
    $observaciones = isset($data['observaciones']) ? $data['observaciones'] : '';

    // Obtener el monto inicial y calcular la diferencia
    $stmt = $pdo->prepare("
        SELECT monto_inicial 
        FROM turnos 
        WHERE id = ? AND user_id = ? AND fecha_cierre IS NULL
    ");
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta');
    }

    $stmt->execute([$_SESSION['turno_actual'], $_SESSION['user_id']]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        throw new Exception('No se encontró el turno activo');
    }

    // Obtener el total de ventas
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total_ventas
        FROM ventas 
        WHERE turno_id = ?
    ");
    $stmt->execute([$_SESSION['turno_actual']]);
    $ventas = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_sistema = floatval($turno['monto_inicial']) + floatval($ventas['total_ventas']);
    $diferencia = $monto_final - $total_sistema;

    // Iniciar transacción
    $pdo->beginTransaction();

    // Actualizar el turno
    $stmt = $pdo->prepare("
        UPDATE turnos 
        SET 
            fecha_cierre = CURRENT_TIMESTAMP,
            monto_final = ?,
            diferencia = ?,
            observaciones = ?
        WHERE id = ? AND user_id = ? AND fecha_cierre IS NULL
    ");

    if (!$stmt->execute([$monto_final, $diferencia, $observaciones, $_SESSION['turno_actual'], $_SESSION['user_id']])) {
        throw new Exception('Error al cerrar el turno');
    }

    // Confirmar transacción
    $pdo->commit();

    // Eliminar el turno de la sesión
    unset($_SESSION['turno_actual']);

    echo json_encode([
        'success' => true,
        'message' => 'Turno cerrado correctamente',
        'detalles' => [
            'monto_inicial' => floatval($turno['monto_inicial']),
            'total_ventas' => floatval($ventas['total_ventas']),
            'total_sistema' => $total_sistema,
            'monto_final' => $monto_final,
            'diferencia' => $diferencia
        ]
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error PDO en cerrar.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error general en cerrar.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 