<?php
session_start();
header('Content-Type: application/json');

try {
    // Obtener los datos enviados
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error al decodificar los datos JSON');
    }

    // Validar que todos los datos necesarios estén presentes
    $requiredFields = ['cliente_id', 'tipo_documento', 'productos', 'total', 'turno_id'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Campo requerido faltante: {$field}");
        }
    }

    // Incluir la conexión a la base de datos
    require_once '../../../config/db.php';

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Insertar la venta
        $stmt = $pdo->prepare("
            INSERT INTO ventas (
                cliente_id, 
                turno_id,
                tipo_documento, 
                numeracion,
                subtotal,
                descuento,
                total,
                metodo_pago,
                fecha,
                estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'completada')
        ");

        $stmt->execute([
            $data['cliente_id'],
            $data['turno_id'],
            $data['tipo_documento'],
            $data['numeracion'],
            $data['subtotal'],
            $data['descuento'],
            $data['total'],
            $data['metodo_pago']
        ]);

        $ventaId = $pdo->lastInsertId();

        // Insertar los productos de la venta
        $stmt = $pdo->prepare("
            INSERT INTO venta_detalles (
                venta_id,
                producto_id,
                cantidad,
                precio_unitario
            ) VALUES (?, ?, ?, ?)
        ");

        foreach ($data['productos'] as $producto) {
            $stmt->execute([
                $ventaId,
                $producto['id'],
                $producto['cantidad'],
                $producto['precio']
            ]);

            // Actualizar el stock del producto en la tabla inventario
            $stmtStock = $pdo->prepare("
                UPDATE inventario 
                SET stock = stock - ? 
                WHERE id = ?
            ");
            $stmtStock->execute([$producto['cantidad'], $producto['id']]);
        }

        // Confirmar la transacción
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Venta procesada correctamente',
            'venta_id' => $ventaId
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