<?php
session_start();
header('Content-Type: application/json');

// Verificar si hay errores de PHP antes de cualquier salida
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar si existe el turno actual
if (!isset($_SESSION['turno_actual'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay un turno abierto']);
    exit;
}

try {
    // Verificar que el body sea JSON válido
    $jsonInput = file_get_contents('php://input');
    if (!$jsonInput) {
        throw new Exception('No se recibieron datos');
    }
    
    $data = json_decode($jsonInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido: ' . json_last_error_msg());
    }
    
    if (empty($data['items'])) {
        throw new Exception('No hay items en el carrito');
    }

    $pdo->beginTransaction();

    // Obtener el último número de factura
    $stmt = $pdo->prepare("
        SELECT prefijo_factura, ultimo_numero, numero_final 
        FROM empresas 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $empresa = $stmt->fetch();

    if (!$empresa) {
        throw new Exception('Error al obtener información de la empresa');
    }

    $nuevo_numero = ($empresa['ultimo_numero'] ?? 0) + 1;
    if ($nuevo_numero > $empresa['numero_final']) {
        throw new Exception('Se ha alcanzado el número máximo de facturas permitido');
    }

    $numero_factura = $empresa['prefijo_factura'] . str_pad($nuevo_numero, 8, '0', STR_PAD_LEFT);

    // Calcular totales
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        $subtotal += $item['precio'] * $item['cantidad'];
    }
    
    $descuento = ($subtotal * $data['descuento']) / 100;
    $total = $subtotal - $descuento;

    // Insertar venta
    $stmt = $pdo->prepare("
        INSERT INTO ventas (
            user_id, cliente_id, total, subtotal, descuento,
            metodo_pago, numero_factura, tipo_documento,
            numeracion_tipo, numeracion, turno_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['cliente_id'],
        $total,
        $subtotal,
        $data['descuento'],
        $data['metodo_pago'],
        $numero_factura,
        $data['tipo_documento'],
        $data['numeracion'],
        'principal',
        $_SESSION['turno_actual']
    ]);

    $venta_id = $pdo->lastInsertId();

    // Insertar detalles y actualizar inventario
    foreach ($data['items'] as $item) {
        // Insertar detalle
        $stmt = $pdo->prepare("
            INSERT INTO venta_detalles (
                venta_id, producto_id, cantidad, precio_unitario
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $venta_id,
            $item['id'],
            $item['cantidad'],
            $item['precio']
        ]);

        // Actualizar inventario
        $stmt = $pdo->prepare("
            UPDATE inventario 
            SET stock = stock - ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['cantidad'], $item['id']]);
    }

    // Actualizar último número de factura
    $stmt = $pdo->prepare("
        UPDATE empresas 
        SET ultimo_numero = ? 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$nuevo_numero, $_SESSION['user_id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'venta_id' => $venta_id,
        'numero_factura' => $numero_factura
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 