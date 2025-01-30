<?php
// Evitar que se muestren errores en la salida
error_reporting(0);
ini_set('display_errors', 0);

// Iniciar sesión y establecer headers
session_start();
header('Content-Type: application/json');

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
    ]);
    exit;
}

// Verificar que el archivo existe antes de incluirlo
if (!file_exists('../../../../config/db.php')) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de configuración: No se encuentra el archivo de conexión'
    ]);
    exit;
}

try {
    require_once '../../../../config/db.php';

    // Verificar que la conexión a la base de datos existe
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Verificar que se recibieron los datos necesarios
    if (!isset($data['monto_inicial'])) {
        throw new Exception('El monto inicial es requerido');
    }

    // Verificar que no haya un turno abierto
    $stmt = $pdo->prepare("
        SELECT id 
        FROM turnos 
        WHERE user_id = ? 
        AND fecha_cierre IS NULL
    ");
    $stmt->execute([$_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        throw new Exception('Ya tienes un turno abierto');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // Insertar nuevo turno
    $stmt = $pdo->prepare("
        INSERT INTO turnos (
            user_id,
            fecha_apertura,
            monto_inicial
        ) VALUES (
            ?,
            CURRENT_TIMESTAMP,
            ?
        )
    ");

    if (!$stmt->execute([
        $_SESSION['user_id'],
        floatval($data['monto_inicial'])
    ])) {
        throw new Exception('Error al crear el turno');
    }

    // Obtener el ID del turno creado
    $turno_id = $pdo->lastInsertId();

    // Guardar el turno en la sesión
    $_SESSION['turno_actual'] = $turno_id;

    // Confirmar transacción
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Turno abierto correctamente',
        'turno_id' => $turno_id
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error PDO en abrir.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error general en abrir.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 