<?php
session_start();
require_once '../../config/db.php';
require_once './functions.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Verificar si la solicitud es POST y contiene los datos necesarios
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['datos'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit();
}

try {
    $pdo->beginTransaction();

    $datos = json_decode($_POST['datos'], true);
    
    // Validar que todos los campos necesarios estén presentes
    if (!isset($datos['tipo_documento'], $datos['cliente_id'], $datos['total'], $datos['productos'], $datos['metodo_pago'])) {
        throw new Exception("Faltan datos necesarios para procesar el documento: " . print_r($datos, true));
    }

    $tipo_documento = $datos['tipo_documento'];
    $cliente_id = $datos['cliente_id'];
    $productos = $datos['productos'];
    $total = floatval($datos['total']);
    $descuento = isset($datos['descuento']) ? floatval($datos['descuento']) : 0;
    $metodo_pago = $datos['metodo_pago'];

    // Manejar información de crédito si es necesario
    $credito_info = null;
    if ($metodo_pago === 'credito') {
        if (!isset($datos['credito']['plazo'], $datos['credito']['interes'])) {
            throw new Exception("Faltan datos necesarios para el crédito");
        }
        $credito_info = $datos['credito'];
    }

    // Validar que el total sea un número positivo
    if ($total <= 0) {
        throw new Exception("El total del documento debe ser mayor que cero");
    }

    // Generar el número de documento
    $numero_documento = generarNumeroDocumento($pdo, $tipo_documento);

    if ($tipo_documento === 'factura') {
        // Insertar la venta en la base de datos
        $stmt = $pdo->prepare("INSERT INTO ventas (user_id, cliente_id, total, descuento, metodo_pago, fecha, numero_factura) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([$user_id, $cliente_id, $total, $descuento, $metodo_pago, $numero_documento]);
        $documento_id = $pdo->lastInsertId();

        // Si es crédito, insertar información adicional
        if ($metodo_pago === 'credito') {
            $stmt_credito = $pdo->prepare("INSERT INTO creditos (venta_id, plazo, interes) VALUES (?, ?, ?)");
            $stmt_credito->execute([$documento_id, $credito_info['plazo'], $credito_info['interes']]);
        }

        // Insertar los detalles de la venta y actualizar el inventario
        $stmt_detalle = $pdo->prepare("INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
        $stmt_inventario = $pdo->prepare("UPDATE inventario SET stock = stock - ? WHERE id = ? AND user_id = ?");
    } else {
        // Insertar la cotización en la base de datos
        $stmt = $pdo->prepare("INSERT INTO cotizaciones (user_id, cliente_id, total, descuento, fecha, numero_cotizacion) VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([$user_id, $cliente_id, $total, $descuento, $numero_documento]);
        $documento_id = $pdo->lastInsertId();

        // Insertar los detalles de la cotización
        $stmt_detalle = $pdo->prepare("INSERT INTO cotizacion_detalles (cotizacion_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
    }

    $productos_actualizados = [];
    foreach ($productos as $producto) {
        if (!isset($producto['id'], $producto['cantidad'], $producto['precio'])) {
            throw new Exception("Datos de producto incompletos: " . print_r($producto, true));
        }

        $stmt_detalle->execute([$documento_id, $producto['id'], $producto['cantidad'], $producto['precio']]);

        if ($tipo_documento === 'factura') {
            $stmt_inventario->execute([$producto['cantidad'], $producto['id'], $user_id]);

            if ($stmt_inventario->rowCount() === 0) {
                throw new Exception("Error al actualizar el inventario para el producto ID: " . $producto['id']);
            }

            // Obtener el stock actualizado
            $stmt_stock = $pdo->prepare("SELECT stock FROM inventario WHERE id = ? AND user_id = ?");
            $stmt_stock->execute([$producto['id'], $user_id]);
            $nuevo_stock = $stmt_stock->fetchColumn();

            $productos_actualizados[] = [
                'id' => $producto['id'],
                'nuevo_stock' => $nuevo_stock
            ];
        }
    }

    $pdo->commit();

    // Obtener información del cliente
    $stmt_cliente = $pdo->prepare("SELECT nombre FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$cliente_id]);
    $cliente_nombre = $stmt_cliente->fetchColumn();

    // Preparar datos para la impresión
    $datos_impresion = [
        'tipo_documento' => $tipo_documento,
        'numero_documento' => $numero_documento,
        'fecha' => date('Y-m-d H:i:s'),
        'cliente' => $cliente_nombre,
        'productos' => $productos,
        'subtotal' => $total + $descuento,
        'descuento' => $descuento,
        'total' => $total,
        'metodo_pago' => $metodo_pago
    ];

    echo json_encode([
        'success' => true,
        'message' => $tipo_documento === 'factura' ? 'Venta procesada correctamente' : 'Cotización generada correctamente',
        'documento_id' => $documento_id,
        'numero_documento' => $numero_documento,
        'datos_impresion' => $datos_impresion,
        'productos_actualizados' => $productos_actualizados
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error al procesar el documento: ' . $e->getMessage()]);
}

function generarNumeroDocumento($pdo, $tipo_documento) {
    $año_actual = date('Y');
    $prefijo = $tipo_documento === 'factura' ? 'F' : 'C';
    $tabla = $tipo_documento === 'factura' ? 'ventas' : 'cotizaciones';
    $campo = $tipo_documento === 'factura' ? 'numero_factura' : 'numero_cotizacion';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $tabla WHERE YEAR(fecha) = ?");
    $stmt->execute([$año_actual]);
    $numero_secuencial = $stmt->fetchColumn() + 1;
    
    return sprintf('%s%s-%06d', $prefijo, $año_actual, $numero_secuencial);
}
