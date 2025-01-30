<?php
session_start();
header('Content-Type: application/json');

require_once '../../../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $jsonInput = file_get_contents('php://input');
    if (!$jsonInput) {
        throw new Exception('No se recibieron datos');
    }
    
    $data = json_decode($jsonInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    if (empty($data['items'])) {
        throw new Exception('No hay items en la cotización');
    }

    $pdo->beginTransaction();

    // Generar número de cotización
    $stmt = $pdo->prepare("SELECT MAX(CAST(numero as UNSIGNED)) as ultimo FROM cotizaciones");
    $stmt->execute();
    $resultado = $stmt->fetch();
    $nuevo_numero = ($resultado['ultimo'] ?? 0) + 1;
    $numero_cotizacion = str_pad($nuevo_numero, 6, '0', STR_PAD_LEFT);

    // Calcular totales
    $total = 0;
    foreach ($data['items'] as $item) {
        $total += $item['precio'] * $item['cantidad'];
    }
    
    // Aplicar descuento
    $total = $total - ($total * ($data['descuento'] / 100));

    // Insertar cotización
    $stmt = $pdo->prepare("
        INSERT INTO cotizaciones (
            numero, cliente_id, fecha, fecha_vencimiento, total, estado
        ) VALUES (?, ?, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY), ?, 'Pendiente')
    ");
    $stmt->execute([
        $numero_cotizacion,
        $data['cliente_id'],
        $total
    ]);

    $cotizacion_id = $pdo->lastInsertId();

    // Insertar detalles
    foreach ($data['items'] as $item) {
        $subtotal = $item['precio'] * $item['cantidad'];
        $stmt = $pdo->prepare("
            INSERT INTO cotizacion_detalles (
                cotizacion_id, producto_id, descripcion, cantidad,
                precio_unitario, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $cotizacion_id,
            $item['id'],
            $item['nombre'],
            $item['cantidad'],
            $item['precio'],
            $subtotal
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'cotizacion_id' => $cotizacion_id,
        'numero' => $numero_cotizacion
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 