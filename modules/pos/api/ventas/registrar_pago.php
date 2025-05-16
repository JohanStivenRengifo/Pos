<?php
session_start();
header('Content-Type: application/json');

require_once '../../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si existe el turno actual para pagos en efectivo
if (!isset($_SESSION['turno_actual'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay un turno abierto para recibir pagos en efectivo']);
    exit;
}

try {
    // Verificar si hay datos POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['venta_id']) || !isset($data['monto']) || !isset($data['metodo_pago'])) {
        throw new Exception('Datos incompletos para procesar el pago');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // Verificar que la venta existe y obtener su información
    $stmt = $pdo->prepare("
        SELECT total, saldo_pendiente, estado_pago
        FROM ventas
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$data['venta_id'], $_SESSION['user_id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Venta no encontrada');
    }

    // Validar el monto del pago
    $monto = floatval($data['monto']);
    if ($monto <= 0 || $monto > $venta['saldo_pendiente']) {
        throw new Exception('Monto de pago inválido');
    }

    // Registrar el pago
    $stmt = $pdo->prepare("
        INSERT INTO venta_pagos (
            venta_id,
            turno_id,
            monto,
            metodo_pago,
            referencia,
            notas,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $data['venta_id'],
        $_SESSION['turno_actual'],
        $monto,
        $data['metodo_pago'],
        $data['referencia'] ?? null,
        $data['notas'] ?? null
    ]);

    // Si el pago es en efectivo, registrar el movimiento en la caja
    if (strtolower($data['metodo_pago']) === 'efectivo') {
        $stmt = $pdo->prepare("
            INSERT INTO movimientos_caja (
                turno_id,
                tipo,
                monto,
                descripcion,
                created_at
            ) VALUES (?, 'ingreso', ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $_SESSION['turno_actual'],
            $monto,
            "Pago de venta #" . $data['venta_id']
        ]);
    }

    // Actualizar el saldo pendiente y estado de la venta
    $nuevo_saldo = $venta['saldo_pendiente'] - $monto;
    $nuevo_estado = $nuevo_saldo > 0 ? 'parcial' : 'pagada';

    $stmt = $pdo->prepare("
        UPDATE ventas
        SET saldo_pendiente = ?,
            estado_pago = ?
        WHERE id = ?
    ");

    $stmt->execute([$nuevo_saldo, $nuevo_estado, $data['venta_id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pago registrado correctamente',
        'detalles' => [
            'monto_pagado' => $monto,
            'saldo_restante' => $nuevo_saldo,
            'estado_pago' => $nuevo_estado
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}