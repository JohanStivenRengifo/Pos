<?php
session_start();
require_once '../../config/db.php';
require_once './functions.php';

// Activar el reporte de errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configurar el header para JSON
header('Content-Type: application/json');

try {
    // Verificar que el usuario esté autenticado y tenga un turno activo
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['turno_id'])) {
        throw new Exception('Usuario no autenticado o turno no iniciado');
    }

    $user_id = $_SESSION['user_id'];
    $turno_id = $_SESSION['turno_id'];

    // Verificar que el turno esté activo
    $stmt = $pdo->prepare("SELECT id FROM turnos WHERE id = ? AND user_id = ? AND fecha_cierre IS NULL");
    $stmt->execute([$turno_id, $user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('No hay un turno activo');
    }

    // Obtener la configuración de la empresa del usuario
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE usuario_id = ? AND estado = 1 LIMIT 1");
    $stmt->execute([$user_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        throw new Exception('No se encontró la configuración de la empresa');
    }

    // Log de datos recibidos
    error_log("Datos recibidos: " . file_get_contents('php://input'));
    
    // Obtener y decodificar los datos JSON
    $jsonData = file_get_contents('php://input');
    $datos = json_decode($jsonData, true);

    // Verificar errores en el JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
    }

    // Validar datos requeridos
    if (empty($datos['cliente_id'])) {
        throw new Exception('ID de cliente no proporcionado');
    }
    if (empty($datos['productos'])) {
        throw new Exception('No hay productos en la venta');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // Generar número de factura según la configuración de la empresa
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $num_facturas = $stmt->fetchColumn();
    
    $siguiente_numero = $empresa['numero_inicial'] + $num_facturas;
    if ($siguiente_numero > $empresa['numero_final']) {
        throw new Exception('Se ha alcanzado el límite de numeración de facturas');
    }

    $numero_factura = $empresa['prefijo_factura'] . str_pad($siguiente_numero, 8, '0', STR_PAD_LEFT);

    // Insertar venta con manejo de errores mejorado
    try {
        $sql = "INSERT INTO ventas (
            user_id,
            cliente_id, 
            total, 
            subtotal,
            descuento, 
            metodo_pago, 
            tipo_documento, 
            fecha, 
            numero_factura,
            numeracion_tipo,
            turno_id,
            anulada
        ) VALUES (
            :user_id,
            :cliente_id, 
            :total, 
            :subtotal,
            :descuento, 
            :metodo_pago, 
            :tipo_documento, 
            NOW(), 
            :numero_factura,
            :numeracion_tipo,
            :turno_id,
            0
        )";
        
        // Calcular subtotal (total antes del descuento)
        $subtotal = $datos['total'] / (1 - ($datos['descuento'] ?? 0) / 100);
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':cliente_id' => $datos['cliente_id'],
            ':total' => $datos['total'],
            ':subtotal' => $subtotal,
            ':descuento' => $datos['descuento'] ?? 0,
            ':metodo_pago' => $datos['metodo_pago'],
            ':tipo_documento' => $datos['tipo_documento'],
            ':numero_factura' => $numero_factura,
            ':numeracion_tipo' => 'principal',
            ':turno_id' => $turno_id
        ]);

        if (!$result) {
            throw new Exception('Error al insertar la venta: ' . implode(', ', $stmt->errorInfo()));
        }

        $venta_id = $pdo->lastInsertId();
        
        // Log de venta creada
        error_log("Venta creada con ID: " . $venta_id . " para el turno: " . $turno_id);

    } catch (PDOException $e) {
        throw new Exception('Error en la base de datos al crear la venta: ' . $e->getMessage());
    }

    // Procesar productos
    $productos_actualizados = [];
    foreach ($datos['productos'] as $producto) {
        try {
            // Verificar stock
            $stmt = $pdo->prepare("SELECT stock FROM inventario WHERE id = ? FOR UPDATE");
            $stmt->execute([$producto['id']]);
            $stock_actual = $stmt->fetchColumn();

            if ($stock_actual === false) {
                throw new Exception("Producto no encontrado: ID {$producto['id']}");
            }

            if ($stock_actual < $producto['cantidad']) {
                throw new Exception("Stock insuficiente para el producto ID: {$producto['id']}. Disponible: {$stock_actual}");
            }

            // Insertar detalle de venta
            $stmt = $pdo->prepare("INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario) 
                                 VALUES (:venta_id, :producto_id, :cantidad, :precio)");
            
            $result = $stmt->execute([
                ':venta_id' => $venta_id,
                ':producto_id' => $producto['id'],
                ':cantidad' => $producto['cantidad'],
                ':precio' => $producto['precio']
            ]);

            if (!$result) {
                throw new Exception('Error al insertar detalle de venta: ' . implode(', ', $stmt->errorInfo()));
            }

            // Actualizar inventario
            $nuevo_stock = $stock_actual - $producto['cantidad'];
            $stmt = $pdo->prepare("UPDATE inventario SET stock = ? WHERE id = ?");
            $result = $stmt->execute([$nuevo_stock, $producto['id']]);

            if (!$result) {
                throw new Exception('Error al actualizar stock: ' . implode(', ', $stmt->errorInfo()));
            }

            $productos_actualizados[] = [
                'id' => $producto['id'],
                'nuevo_stock' => $nuevo_stock
            ];

        } catch (PDOException $e) {
            throw new Exception('Error en la base de datos al procesar producto: ' . $e->getMessage());
        }
    }

    // Procesar crédito si aplica
    if ($datos['metodo_pago'] === 'credito' && isset($datos['credito'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO creditos (venta_id, plazo, interes) VALUES (:venta_id, :plazo, :interes)");
            $result = $stmt->execute([
                ':venta_id' => $venta_id,
                ':plazo' => $datos['credito']['plazo'],
                ':interes' => $datos['credito']['interes']
            ]);

            if (!$result) {
                throw new Exception('Error al insertar crédito: ' . implode(', ', $stmt->errorInfo()));
            }
        } catch (PDOException $e) {
            throw new Exception('Error en la base de datos al procesar crédito: ' . $e->getMessage());
        }
    }

    // Confirmar transacción
    $pdo->commit();

    // Enviar respuesta exitosa
    $response = [
        'status' => true,
        'message' => 'Venta procesada correctamente',
        'venta_id' => $venta_id,
        'numero_factura' => $numero_factura,
        'turno_id' => $turno_id,
        'productos_actualizados' => $productos_actualizados
    ];

    error_log("Respuesta exitosa: " . print_r($response, true));
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error en procesar_venta.php: " . $e->getMessage());
    
    // Revertir transacción en caso de error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Enviar respuesta de error
    http_response_code(500);
    $error_response = [
        'status' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'error_type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ];
    
    error_log("Respuesta de error: " . print_r($error_response, true));
    echo json_encode($error_response);
}

exit();
?> 