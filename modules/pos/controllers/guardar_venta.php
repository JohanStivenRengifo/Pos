<?php
require_once '../../../config/db.php';
require_once 'alegra_integration.php';

try {
    // Validar datos recibidos
    if (!isset($_POST['datos_venta'])) {
        throw new Exception('No se recibieron los datos de la venta');
    }

    $datos = json_decode($_POST['datos_venta'], true);
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Insertar la venta
    // Asegurar que existe la columna alegra_id
    $stmt = $pdo->prepare("ALTER TABLE ventas ADD COLUMN IF NOT EXISTS alegra_id VARCHAR(50) NULL;");
    $stmt->execute();

    $stmt = $pdo->prepare("
        INSERT INTO ventas (
            cliente_id, 
            fecha, 
            total, 
            descuento, 
            metodo_pago,
            numeracion,
            numero_factura,
            user_id,
            alegra_id
        ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, NULL)
    ");

    $numeracion = $datos['tipo_factura'] ?? 'principal'; // Asegurarnos de que tipo_factura esté definido
    $numero_factura = $datos['numero_factura'];
    
    $stmt->execute([
        $datos['cliente_id'],
        $datos['total'],
        $datos['descuento'] ?? 0,
        $datos['metodo_pago'],
        $numeracion,
        $numero_factura,
        $datos['user_id']
    ]);
    
    $venta_id = $pdo->lastInsertId();
    
    // ... código para guardar detalles de la venta ...

    // Si es factura electrónica, crear en Alegra
    if ($numeracion === 'electronica') {
        $alegra = new AlegraIntegration();
        $resultadoAlegra = $alegra->createInvoice([
            'cliente_id' => $datos['cliente_id'],
            'items' => $datos['items'],
            'metodo_pago' => $datos['metodo_pago']
        ]);

        if (!$resultadoAlegra['success']) {
            throw new Exception('Error al crear factura en Alegra: ' . $resultadoAlegra['error']);
        }

        // Actualizar el ID de Alegra en la venta
        $stmt = $pdo->prepare("UPDATE ventas SET alegra_id = ? WHERE id = ?");
        $stmt->execute([$resultadoAlegra['data']['id'], $venta_id]);
    }

    $pdo->commit();
    
    // Determinar la URL de impresión según el tipo de factura
    $print_url = $numeracion === 'electronica' 
        ? 'controllers/imprimir_factura.php?id=' . $venta_id 
        : 'controllers/imprimir_factura.php?id=' . $venta_id;

    echo json_encode([
        'success' => true,
        'message' => 'Venta guardada exitosamente',
        'venta_id' => $venta_id,
        'print_url' => $print_url,
        'numeracion' => $numeracion // Agregar el tipo de numeración para debug
    ]);

} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}