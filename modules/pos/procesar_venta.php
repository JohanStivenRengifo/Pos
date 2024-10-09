<?php
session_start();
require_once '../../config/db.php'; // Conexión a la base de datos
require_once './functions.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Verificar si la solicitud es POST y contiene los datos necesarios
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['venta'])) {
    echo json_encode(['success' => false, 'message' => 'Datos de venta inválidos']);
    exit();
}

try {
    $pdo->beginTransaction();

    $venta = json_decode($_POST['venta'], true);
    
    // Validar que todos los campos necesarios estén presentes
    if (!isset($venta['cliente_id'], $venta['total'], $venta['productos'], $venta['metodo_pago'])) {
        throw new Exception("Faltan datos necesarios para procesar la venta: " . print_r($venta, true));
    }

    $cliente_id = $venta['cliente_id'];
    $productos = $venta['productos'];
    $total = floatval($venta['total']);
    $descuento = isset($venta['descuento']) ? floatval($venta['descuento']) : 0;
    $metodo_pago = $venta['metodo_pago'];

    // Validar que el total sea un número positivo
    if ($total <= 0) {
        throw new Exception("El total de la venta debe ser mayor que cero");
    }

    // Generar el número de factura
    $numero_factura = generarNumeroFactura($pdo);

    // Insertar la venta en la base de datos
    $stmt = $pdo->prepare("INSERT INTO ventas (user_id, cliente_id, total, descuento, metodo_pago, fecha, numero_factura) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->execute([$user_id, $cliente_id, $total, $descuento, $metodo_pago, $numero_factura]);
    $venta_id = $pdo->lastInsertId();

    // Preparar las consultas para los detalles de la venta y actualización de inventario
    $stmt_detalle = $pdo->prepare("INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
    $stmt_inventario = $pdo->prepare("UPDATE inventario SET stock = stock - ? WHERE id = ? AND user_id = ?");
    $stmt_stock = $pdo->prepare("SELECT stock FROM inventario WHERE id = ? AND user_id = ?");

    $productos_actualizados = [];
    foreach ($productos as $producto) {
        if (!isset($producto['id'], $producto['cantidad'], $producto['precio'])) {
            throw new Exception("Datos de producto incompletos: " . print_r($producto, true));
        }

        $stmt_detalle->execute([$venta_id, $producto['id'], $producto['cantidad'], $producto['precio']]);
        $stmt_inventario->execute([$producto['cantidad'], $producto['id'], $user_id]);

        if ($stmt_inventario->rowCount() === 0) {
            throw new Exception("Error al actualizar el inventario para el producto ID: " . $producto['id']);
        }

        $stmt_stock->execute([$producto['id'], $user_id]);
        $nuevo_stock = $stmt_stock->fetchColumn();

        $productos_actualizados[] = [
            'id' => $producto['id'],
            'nuevo_stock' => $nuevo_stock
        ];
    }

    $pdo->commit();

    // Obtener información del cliente
    $stmt_cliente = $pdo->prepare("SELECT nombre FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$cliente_id]);
    $cliente_nombre = $stmt_cliente->fetchColumn();

    // Preparar datos para la impresión
    $datos_impresion = [
        'numero_factura' => $numero_factura,
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
        'message' => 'Venta procesada correctamente',
        'venta_id' => $venta_id,
        'numero_factura' => $numero_factura,
        'datos_impresion' => $datos_impresion,
        'productos_actualizados' => $productos_actualizados
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error al procesar la venta: ' . $e->getMessage()]);
}

function generarNumeroFactura($pdo) {
    $año_actual = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE YEAR(fecha) = ?");
    $stmt->execute([$año_actual]);
    $numero_secuencial = $stmt->fetchColumn() + 1;
    return sprintf('%s-%06d', $año_actual, $numero_secuencial);
}