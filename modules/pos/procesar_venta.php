<?php
// Desactivar la salida de errores de PHP
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Función para manejar errores y devolverlos como JSON
function handleError($errno, $errstr, $errfile, $errline) {
    $error = [
        'success' => false,
        'message' => "Error: [$errno] $errstr - $errfile:$errline"
    ];
    echo json_encode($error);
    exit;
}

// Establecer el manejador de errores
set_error_handler("handleError");

// Asegurarse de que la salida sea siempre JSON
header('Content-Type: application/json');

session_start();
require_once '../../config/db.php';
require_once './functions.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Verificar la información del usuario
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user_info) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit();
}

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
    $numero_factura = generarNumeroFactura($pdo, $user_id);

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
    $stmt_cliente = $pdo->prepare("SELECT id, nombre, email, telefono, tipo_identificacion, identificacion, primer_nombre, segundo_nombre, apellidos, municipio_departamento, codigo_postal FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$cliente_id]);
    $cliente_info = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    // Preparar datos para la impresión
    $datos_impresion = [
        'numero_factura' => $numero_factura,
        'fecha' => date('Y-m-d H:i:s'),
        'cliente' => [
            'id' => $cliente_info['id'],
            'nombre_completo' => trim($cliente_info['primer_nombre'] . ' ' . $cliente_info['segundo_nombre'] . ' ' . $cliente_info['apellidos']),
            'nombre' => $cliente_info['nombre'],
            'email' => $cliente_info['email'],
            'telefono' => $cliente_info['telefono'],
            'tipo_identificacion' => $cliente_info['tipo_identificacion'],
            'identificacion' => $cliente_info['identificacion'],
            'municipio_departamento' => $cliente_info['municipio_departamento'],
            'codigo_postal' => $cliente_info['codigo_postal']
        ],
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

function generarNumeroFactura($pdo, $user_id) {
    // Obtener la información de la empresa del usuario
    $stmt = $pdo->prepare("SELECT e.prefijo_factura, e.numero_inicial, e.numero_final, e.id as empresa_id, u.empresa_id as user_empresa_id 
                           FROM users u 
                           LEFT JOIN empresas e ON u.empresa_id = e.id 
                           WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        throw new Exception("No se encontró información del usuario ID: " . $user_id);
    }

    if (!$result['empresa_id']) {
        // El usuario existe pero no tiene una empresa asociada
        if ($result['user_empresa_id']) {
            throw new Exception("El usuario ID: " . $user_id . " tiene un empresa_id (" . $result['user_empresa_id'] . ") pero no se encontró la empresa correspondiente.");
        } else {
            throw new Exception("El usuario ID: " . $user_id . " no tiene una empresa asociada.");
        }
    }

    $empresa_info = $result;
    $prefijo = $empresa_info['prefijo_factura'];

    // Obtener el último número de factura usado para esta empresa
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(numero_factura, '-', -1) AS UNSIGNED)) as ultimo_numero 
                           FROM ventas v
                           INNER JOIN users u ON v.user_id = u.id
                           WHERE u.empresa_id = ? AND v.numero_factura LIKE ?");
    $stmt->execute([$empresa_info['empresa_id'], $prefijo . '-%']);
    $ultimo_numero = $stmt->fetchColumn();

    // Determinar el próximo número de factura
    $proximo_numero = $ultimo_numero ? $ultimo_numero + 1 : $empresa_info['numero_inicial'];

    // Verificar si se ha alcanzado el número final
    if ($proximo_numero > $empresa_info['numero_final']) {
        throw new Exception("Se ha alcanzado el número máximo de facturas permitido");
    }

    // Generar el nuevo número de factura
    $nuevo_numero_factura = sprintf('%s-%06d', $prefijo, $proximo_numero);

    return $nuevo_numero_factura;
}
