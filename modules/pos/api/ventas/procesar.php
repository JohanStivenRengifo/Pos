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

    // Si es factura electrónica, procesar con Alegra
    if ($data['tipo_documento'] === 'factura' && $data['numeracion'] === 'electronica') {
        $alegra = new AlegraIntegration();
        $alegraResponse = $alegra->createInvoice($data);

        if (!$alegraResponse['success']) {
            throw new Exception('Error al crear factura electrónica: ' . $alegraResponse['error']);
        }

        // Guardar referencia de Alegra y datos de facturación electrónica
        $data['alegra_id'] = $alegraResponse['data']['id'];
        $data['cufe'] = $alegraResponse['data']['cufe'];
        $data['qr_code'] = $alegraResponse['data']['qr_code'];
        $data['pdf_url'] = $alegraResponse['data']['pdf_url'];
        $data['xml_url'] = $alegraResponse['data']['xml_url'];
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
    
    $descuento = ($subtotal * floatval($data['descuento'])) / 100;
    $total = $subtotal - $descuento;

    // Insertar venta
    $stmt = $pdo->prepare("
        INSERT INTO ventas (
            user_id, 
            cliente_id,
            tipo_documento,
            numeracion,
            total,
            descuento,
            metodo_pago,
            numero_factura,
            alegra_id,
            turno_id,
            cufe,
            qr_code,
            pdf_url,
            xml_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['user_id'],
        $data['cliente_id'],
        $data['tipo_documento'],
        $data['numeracion'],
        $total,
        $descuento,
        $data['metodo_pago'],
        $numero_factura,
        $data['alegra_id'] ?? null,
        $_SESSION['turno_actual'],
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
        'message' => 'Venta procesada correctamente',
        'venta_id' => $venta_id,
        'alegra_id' => $data['alegra_id'] ?? null
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 