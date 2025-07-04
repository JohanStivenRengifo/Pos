<?php
session_start();
header('Content-Type: application/json');

// Verificar si hay errores de PHP antes de cualquier salida
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../../../config/db.php';
require_once '../../controllers/alegra_integration.php';

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
    // Verificar si hay datos POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('No se recibieron datos de la venta');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // Determinar el estado de la factura
    $estado_factura = 'NO EMITIDA';
    
    if ($data['tipo_documento'] === 'factura') {
        if ($data['numeracion'] === 'electronica') {
            // Si es factura electrónica, procesar con Alegra
            $alegra = new AlegraIntegration();
            $alegraResponse = $alegra->createInvoice($data);

            if (!$alegraResponse['success']) {
                throw new Exception('Error al crear factura electrónica: ' . $alegraResponse['error']);
            }

            // Actualizar estados después de procesar con Alegra
            $estado_factura = 'EMITIDA';

            // Guardar referencia de Alegra y datos de facturación electrónica
            $data['alegra_id'] = $alegraResponse['data']['id'];
            $data['cufe'] = $alegraResponse['data']['cufe'];
            $data['qr_code'] = $alegraResponse['data']['qr_code'];
            $data['pdf_url'] = $alegraResponse['data']['pdf_url'];
            $data['xml_url'] = $alegraResponse['data']['xml_url'];
        }
    }

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
        $subtotal += floatval($item['precio']) * intval($item['cantidad']);
    }
    
    // Guardar el porcentaje de descuento directamente
    $descuento_porcentaje = isset($data['descuento']) ? floatval($data['descuento']) : 0;
    // Calcular el total con el descuento
    $descuento_monto = ($subtotal * $descuento_porcentaje) / 100;
    $total = $subtotal - $descuento_monto;

    // Insertar venta con todos los campos necesarios
    $stmt = $pdo->prepare("
        INSERT INTO ventas (
            user_id, 
            cliente_id,
            tipo_documento,
            prefijo,
            numeracion,
            total,
            subtotal,
            descuento_total,
            impuestos_total,
            saldo_pendiente,
            metodo_pago,
            estado_pago,
            numero_factura,
            alegra_id,
            turno_id,
            estado_factura,
            anulada,
            cufe,
            qr_code,
            pdf_url,
            xml_url,
            fecha_emision
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, CURRENT_TIMESTAMP
        )
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $data['cliente_id'],
        $data['tipo_documento'] ?? 'FACTURA',
        $empresa['prefijo_factura'],
        $nuevo_numero,
        $total,
        $subtotal,
        $descuento_monto,
        0, // impuestos_total - will be calculated from details
        $total, // saldo_pendiente starts as total amount
        $data['metodo_pago'],
        'pendiente', // estado_pago starts as pending
        $numero_factura,
        $data['alegra_id'] ?? null,
        $_SESSION['turno_actual'],
        $estado_factura,
        $data['cufe'] ?? null,
        $data['qr_code'] ?? null,
        $data['pdf_url'] ?? null,
        $data['xml_url'] ?? null
    ]);

    $venta_id = $pdo->lastInsertId();

    // Insertar detalles y actualizar inventario
    foreach ($data['items'] as $item) {
        // Insertar detalle
        $stmt = $pdo->prepare("
            INSERT INTO venta_detalles (
                venta_id, 
                producto_id, 
                cantidad, 
                precio_unitario,
                costo_unitario,
                descuento_porcentaje,
                descuento_valor,
                impuesto_porcentaje,
                impuesto_valor,
                subtotal,
                total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        // Calcular valores para el detalle
        $cantidad = floatval($item['cantidad']);
        $precio = floatval($item['precio']);
        $descuento_porc = floatval($item['descuento'] ?? 0);
        $descuento_val = ($precio * $cantidad * $descuento_porc) / 100;
        $subtotal_item = $precio * $cantidad - $descuento_val;
        $impuesto_porc = floatval($item['impuesto'] ?? 19);
        $impuesto_val = ($subtotal_item * $impuesto_porc) / 100;
        $total_item = $subtotal_item + $impuesto_val;

        $stmt->execute([
            $venta_id,
            $item['id'],
            $cantidad,
            $precio,
            $item['costo'] ?? 0,
            $descuento_porc,
            $descuento_val,
            $impuesto_porc,
            $impuesto_val,
            $subtotal_item,
            $total_item
        ]);

        // Actualizar totales de impuestos de la venta
        $stmt = $pdo->prepare("UPDATE ventas SET impuestos_total = impuestos_total + ? WHERE id = ?");
        $stmt->execute([$impuesto_val, $venta_id]);

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
        'message' => 'Venta procesada correctamente',
        'venta_id' => $venta_id,
        'alegra_id' => $data['alegra_id'] ?? null,
        'estado_factura' => $estado_factura
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}