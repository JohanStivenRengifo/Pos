<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    // Obtener suscripciones que deben cobrarse hoy
    $stmt = $pdo->prepare("
        SELECT * FROM suscripciones_programadas
        WHERE fecha_proximo_cobro <= NOW()
        AND estado = 'pendiente'
    ");
    $stmt->execute();
    $suscripciones = $stmt->fetchAll();

    foreach ($suscripciones as $suscripcion) {
        try {
            $pdo->beginTransaction();
            
            // Generar nuevo order_id para Bold
            $order_id = 'SUB' . time() . rand(1000, 9999);
            
            // Aquí iría la lógica para crear el cobro automático con Bold
            // Bold tiene que proporcionar una API para cobros recurrentes
            
            // Registrar el intento de pago
            $stmt = $pdo->prepare("
                INSERT INTO pagos (
                    empresa_id,
                    usuario_id,
                    plan,
                    monto,
                    fecha_pago,
                    estado,
                    metodo_pago,
                    bold_order_id,
                    detalles_transaccion
                ) VALUES (?, ?, ?, ?, NOW(), 'pendiente', 'bold_recurring', ?, ?)
            ");
            
            $detalles = json_encode([
                'tipo' => 'cobro_recurrente',
                'suscripcion_id' => $suscripcion['id']
            ]);
            
            $stmt->execute([
                $suscripcion['empresa_id'],
                $suscripcion['usuario_id'],
                $suscripcion['plan'],
                $suscripcion['monto'],
                $order_id,
                $detalles
            ]);
            
            // Actualizar la fecha del próximo cobro
            $stmt = $pdo->prepare("
                UPDATE suscripciones_programadas
                SET fecha_proximo_cobro = DATE_ADD(NOW(), INTERVAL 1 MONTH),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$suscripcion['id']]);
            
            $pdo->commit();
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error procesando suscripción {$suscripcion['id']}: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("Error general procesando suscripciones: " . $e->getMessage());
} 