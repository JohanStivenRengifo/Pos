<?php
session_start();
header('Content-Type: application/json');

try {
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!isset($data['turno_id']) || !isset($data['dinero_caja'])) {
        throw new Exception('Datos incompletos');
    }

    require_once '../../../config/db.php';
    $pdo->beginTransaction();

    try {
        // Obtener el total vendido y monto inicial
        $stmt = $pdo->prepare("
            SELECT t.monto_inicial,
                   COALESCE(SUM(v.total), 0) as total_vendido,
                   COALESCE(SUM(CASE WHEN v.metodo_pago = 'efectivo' THEN v.total ELSE 0 END), 0) as total_efectivo
            FROM turnos t
            LEFT JOIN ventas v ON v.turno_id = t.id
            WHERE t.id = ?
            GROUP BY t.id, t.monto_inicial
        ");
        $stmt->execute([$data['turno_id']]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        $dineroEsperado = $resultado['monto_inicial'] + $resultado['total_efectivo'];
        $diferencia = $data['dinero_caja'] - $dineroEsperado;

        // Cerrar el turno
        $stmt = $pdo->prepare("
            UPDATE turnos 
            SET fecha_cierre = NOW(),
                monto_final = ?,
                diferencia = ?,
                observaciones = ?
            WHERE id = ? AND fecha_cierre IS NULL
        ");
        
        $observaciones = $data['observaciones'] . "\n" .
                        "Dinero esperado: $" . number_format($dineroEsperado, 0, ',', '.') . "\n" .
                        "Dinero en caja: $" . number_format($data['dinero_caja'], 0, ',', '.') . "\n" .
                        "Diferencia: $" . number_format($diferencia, 0, ',', '.');

        $stmt->execute([
            $data['dinero_caja'],
            $diferencia,
            $observaciones,
            $data['turno_id']
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('El turno ya está cerrado o no existe');
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Turno cerrado correctamente'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 