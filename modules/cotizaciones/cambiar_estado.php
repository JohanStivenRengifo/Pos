<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Obtener datos del POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !isset($data['estado'])) {
        throw new Exception('Datos incompletos');
    }

    // Validar que el estado sea uno de los permitidos
    $estados_permitidos = ['Pendiente', 'Aprobada', 'Facturado', 'Cancelada', 'Vencida'];
    if (!in_array($data['estado'], $estados_permitidos)) {
        throw new Exception('Estado no válido');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Verificar que la cotización exista y obtener sus detalles
        $query = "SELECT c.*, cl.user_id, 
                        cl.id as cliente_id,
                        CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre
                 FROM cotizaciones c
                 LEFT JOIN clientes cl ON c.cliente_id = cl.id
                 WHERE c.id = ? AND cl.user_id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$data['id'], $_SESSION['user_id']]);
        $cotizacion = $stmt->fetch();

        if (!$cotizacion) {
            throw new Exception('Cotización no encontrada o no tiene permisos para modificarla');
        }

        // Validar la transición de estado
        if ($cotizacion['estado'] === 'Facturado' && $data['estado'] !== 'Facturado') {
            throw new Exception('No se puede cambiar el estado de una cotización ya facturada');
        }

        // Si el nuevo estado es 'Facturado', crear la venta
        if ($data['estado'] === 'Facturado') {
            // Obtener los detalles de la cotización
            $query = "SELECT cd.*, i.codigo_barras, i.nombre as producto_nombre
                     FROM cotizacion_detalles cd
                     LEFT JOIN inventario i ON cd.producto_id = i.id
                     WHERE cd.cotizacion_id = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$data['id']]);
            $detalles = $stmt->fetchAll();

            // Generar número de factura
            $fecha = date('Ymd');
            $query = "SELECT MAX(CAST(SUBSTRING_INDEX(numero_factura, '-', -1) AS UNSIGNED)) as ultimo
                     FROM ventas WHERE numero_factura LIKE ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute(["FAC-$fecha-%"]);
            $resultado = $stmt->fetch();
            $siguiente = ($resultado['ultimo'] ?? 0) + 1;
            $numero_factura = sprintf("FAC-%s-%03d", $fecha, $siguiente);

            // Crear la venta
            $query = "INSERT INTO ventas (
                user_id,
                cliente_id,
                total,
                subtotal,
                descuento,
                metodo_pago,
                fecha,
                numero_factura,
                tipo_documento,
                estado_factura
            ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE(), ?, 'FACTURA', 'EMITIDA')";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'],
                $cotizacion['cliente_id'],
                $cotizacion['total'],
                $cotizacion['total'], // subtotal igual al total si no hay descuento
                0, // descuento en 0 por defecto
                'EFECTIVO', // método de pago por defecto
                $numero_factura
            ]);
            
            $venta_id = $pdo->lastInsertId();

            // Insertar los detalles de la venta
            $query = "INSERT INTO venta_detalles (
                venta_id,
                producto_id,
                cantidad,
                precio_unitario
            ) VALUES (?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($query);
            
            foreach ($detalles as $detalle) {
                $stmt->execute([
                    $venta_id,
                    $detalle['producto_id'],
                    $detalle['cantidad'],
                    $detalle['precio_unitario']
                ]);

                // Actualizar el inventario
                $query = "UPDATE inventario 
                         SET stock = stock - ? 
                         WHERE id = ?";
                $stmt2 = $pdo->prepare($query);
                $stmt2->execute([
                    $detalle['cantidad'],
                    $detalle['producto_id']
                ]);
            }
        }

        // Actualizar el estado de la cotización
        $query = "UPDATE cotizaciones SET estado = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$data['estado'], $data['id']]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('No se pudo actualizar el estado');
        }

        // Confirmar transacción
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado y venta creada correctamente',
            'venta_id' => $venta_id ?? null
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 