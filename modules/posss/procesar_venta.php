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

    // Agregar en la sección donde se procesa la venta
    $numeracion = $datos['numeracion'] ?? 'principal';

    // Calcular subtotal primero (total antes del descuento)
    $subtotal = $datos['total'] / (1 - ($datos['descuento'] ?? 0) / 100);

    // Dentro del bloque try donde se inserta la venta
    try {
        // Log de datos recibidos
        error_log("Datos recibidos para inserción: " . print_r([
            'user_id' => $user_id,
            'cliente_id' => $datos['cliente_id'],
            'total' => $datos['total'],
            'subtotal' => $subtotal,
            'descuento' => $datos['descuento'] ?? 0,
            'metodo_pago' => $datos['metodo_pago'],
            'tipo_documento' => $datos['tipo_documento'],
            'numero_factura' => $numero_factura,
            'numeracion_tipo' => $numeracion,
            'numeracion' => $datos['numeracion'] ?? 'principal'
        ], true));

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
            numeracion,
            turno_id,
            anulada,
            factura_electronica_id,
            estado_factura,
            fecha_envio_dian
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
            :numeracion,
            :turno_id,
            0,
            :factura_electronica_id,
            :estado_factura,
            :fecha_envio_dian
        )";
        
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
            ':numeracion_tipo' => $numeracion,
            ':numeracion' => $datos['numeracion'] ?? 'principal',
            ':turno_id' => $turno_id,
            ':factura_electronica_id' => null,
            ':estado_factura' => ($numeracion === 'electronica' ? 'PENDIENTE' : null),
            ':fecha_envio_dian' => null
        ]);

        if (!$result) {
            error_log("Error en la inserción: " . print_r($stmt->errorInfo(), true));
            throw new Exception('Error al insertar la venta: ' . implode(', ', $stmt->errorInfo()));
        }

        $venta_id = $pdo->lastInsertId();
        error_log("Venta creada exitosamente con ID: " . $venta_id);

    } catch (PDOException $e) {
        error_log("Error PDO: " . $e->getMessage());
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

    // Dentro del bloque try, después de crear la venta
    if ($numeracion === 'electronica') {
        try {
            error_log("Iniciando proceso de facturación electrónica...");
            require_once __DIR__ . '/services/factus_service.php';
            $factusService = new FactusService();

            // Obtener datos completos del cliente
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
            $stmt->execute([$datos['cliente_id']]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cliente) {
                throw new Exception("Cliente no encontrado para facturación electrónica");
            }

            error_log("Cliente encontrado: " . json_encode($cliente));

            // Preparar datos para Factus
            $datos_factura = [
                'prefijo' => $empresa['prefijo_factura'],
                'numero' => $siguiente_numero,
                'metodo_pago' => $datos['metodo_pago'],
                'observacion' => $datos['observacion'] ?? '',
                'cliente' => [
                    'documento' => $cliente['documento'],
                    'dv' => $cliente['digito_verificacion'],
                    'es_empresa' => $cliente['tipo_persona'] === 'juridica',
                    'nombre' => $cliente['nombre'],
                    'nombre_comercial' => $cliente['nombre_comercial'],
                    'direccion' => $cliente['direccion'],
                    'email' => $cliente['email'],
                    'telefono' => $cliente['telefono'],
                    'tipo_documento' => $cliente['tipo_documento'],
                    'municipio_id' => $cliente['municipio_id']
                ],
                'productos' => array_map(function($producto) use ($pdo) {
                    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
                    $stmt->execute([$producto['id']]);
                    $prod_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$prod_data) {
                        throw new Exception("Producto no encontrado: ID " . $producto['id']);
                    }
                    
                    return [
                        'codigo' => $prod_data['codigo_barras'],
                        'nombre' => $prod_data['nombre'],
                        'cantidad' => $producto['cantidad'],
                        'precio' => $producto['precio'],
                        'tasa_impuesto' => $prod_data['tasa_iva'] ?? "19.00",
                        'excluido_impuesto' => $prod_data['excluido_iva'] ?? 0,
                        'descuento' => $producto['descuento'] ?? 0,
                        'tasa_descuento' => $producto['tasa_descuento'] ?? 0
                    ];
                }, $datos['productos'])
            ];

            error_log("Datos preparados para envío: " . json_encode($datos_factura));
            $respuesta_factus = $factusService->enviarFactura($datos_factura);
            error_log("Respuesta recibida de Factus: " . json_encode($respuesta_factus));

            // Actualizar la venta con la información de Factus
            $stmt = $pdo->prepare("UPDATE ventas SET 
                factura_electronica_id = :factura_id,
                estado_factura = :estado,
                fecha_envio_dian = NOW(),
                respuesta_factus = :respuesta
                WHERE id = :venta_id");
                
            $stmt->execute([
                ':factura_id' => $respuesta_factus['data']['bill']['id'] ?? null,
                ':estado' => $respuesta_factus['data']['bill']['status'] ?? 'PENDIENTE',
                ':respuesta' => json_encode($respuesta_factus),
                ':venta_id' => $venta_id
            ]);

            // Agregar la respuesta de Factus a la respuesta final
            $response['factura_electronica'] = [
                'id' => $respuesta_factus['data']['bill']['id'] ?? null,
                'numero' => $respuesta_factus['data']['bill']['number'] ?? null,
                'estado' => $respuesta_factus['data']['bill']['status'] ?? 'PENDIENTE',
                'url_pdf' => $respuesta_factus['data']['bill']['pdf_url'] ?? null,
                'url_xml' => $respuesta_factus['data']['bill']['xml_url'] ?? null,
                'cufe' => $respuesta_factus['data']['bill']['cufe'] ?? null
            ];

        } catch (Exception $e) {
            error_log("Error al procesar factura electrónica: " . $e->getMessage());
            $stmt = $pdo->prepare("UPDATE ventas SET 
                estado_factura = 'ERROR',
                error_factura = :error
                WHERE id = :venta_id");
                
            $stmt->execute([
                ':error' => $e->getMessage(),
                ':venta_id' => $venta_id
            ]);

            $response['factura_electronica_error'] = $e->getMessage();
        }
    }

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