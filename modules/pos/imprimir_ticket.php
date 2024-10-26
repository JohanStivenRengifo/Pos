<?php
session_start();
require_once '../../config/db.php';
require_once './functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$venta_id = $_GET['id'];

// Obtener información de la venta
$stmt = $pdo->prepare("SELECT v.*, c.nombre AS cliente_nombre, c.identificacion AS cliente_identificacion, u.nombre AS vendedor_nombre
                       FROM ventas v
                       LEFT JOIN clientes c ON v.cliente_id = c.id
                       LEFT JOIN users u ON v.user_id = u.id
                       WHERE v.id = ? AND v.user_id = ?");
$stmt->execute([$venta_id, $user_id]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    echo "Venta no encontrada";
    exit();
}

// Obtener información de la empresa
$stmt = $pdo->prepare("SELECT e.nombre_empresa, e.direccion, e.telefono, e.correo_contacto, e.prefijo_factura
                       FROM users u
                       LEFT JOIN empresas e ON u.empresa_id = e.id
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener detalles de la venta
$stmt = $pdo->prepare("SELECT vd.*, p.nombre AS producto_nombre
                       FROM venta_detalles vd
                       LEFT JOIN inventario p ON vd.producto_id = p.id
                       WHERE vd.venta_id = ?");
$stmt->execute([$venta_id]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configuración para impresora térmica de 80mm
$ancho_papel = 80; // mm
$caracteres_por_linea = 48; // Ajusta según la densidad de impresión de tu impresora

// Funciones de formato
function centrar_texto($texto, $ancho) {
    $espacios = $ancho - mb_strlen($texto);
    $espacios_izquierda = floor($espacios / 2);
    return str_repeat(' ', $espacios_izquierda) . $texto;
}

function alinear_derecha($texto, $ancho) {
    $espacios = $ancho - mb_strlen($texto);
    return str_repeat(' ', $espacios) . $texto;
}

function formatear_numero($numero) {
    return number_format($numero, 2, ',', '.');
}

// Generar contenido del ticket
$ticket = "";

// Encabezado
$ticket .= str_repeat('=', $caracteres_por_linea) . "\n";
$ticket .= centrar_texto(mb_strtoupper($empresa['nombre_empresa']), $caracteres_por_linea) . "\n";
$ticket .= centrar_texto($empresa['direccion'], $caracteres_por_linea) . "\n";
$ticket .= centrar_texto("Tel: " . $empresa['telefono'], $caracteres_por_linea) . "\n";
$ticket .= centrar_texto($empresa['correo_contacto'], $caracteres_por_linea) . "\n";
$ticket .= str_repeat('=', $caracteres_por_linea) . "\n\n";

// Detalles de la venta
$ticket .= "FACTURA DE VENTA N°: " . $empresa['prefijo_factura'] . "-" . $venta['numero_factura'] . "\n";
$ticket .= "Fecha: " . date('d/m/Y H:i', strtotime($venta['fecha'])) . "\n";
$ticket .= str_repeat('-', $caracteres_por_linea) . "\n";
$ticket .= "Cliente: " . $venta['cliente_nombre'] . "\n";
$ticket .= "NIT/CC: " . $venta['cliente_identificacion'] . "\n";
$ticket .= "Vendedor: " . $venta['vendedor_nombre'] . "\n";
$ticket .= str_repeat('-', $caracteres_por_linea) . "\n\n";

// Encabezados de la tabla de productos
$ticket .= sprintf("%-20s %8s %8s %10s\n", "PRODUCTO", "CANT", "PRECIO", "TOTAL");
$ticket .= str_repeat('-', $caracteres_por_linea) . "\n";

// Detalles de los productos
foreach ($detalles as $detalle) {
    $nombre_producto = mb_substr($detalle['producto_nombre'], 0, 20);
    $cantidad = formatear_numero($detalle['cantidad']);
    $precio = formatear_numero($detalle['precio_unitario']);
    $total = formatear_numero($detalle['cantidad'] * $detalle['precio_unitario']);
    
    $ticket .= sprintf("%-20s %8s %8s %10s\n", $nombre_producto, $cantidad, $precio, $total);
}

$ticket .= str_repeat('-', $caracteres_por_linea) . "\n";

// Totales
$subtotal = formatear_numero($venta['total'] + $venta['descuento']);
$descuento = formatear_numero($venta['descuento']);
$total = formatear_numero($venta['total']);

$ticket .= alinear_derecha("SUBTOTAL: $" . $subtotal, $caracteres_por_linea) . "\n";
$ticket .= alinear_derecha("DESCUENTO: $" . $descuento, $caracteres_por_linea) . "\n";
$ticket .= str_repeat('-', $caracteres_por_linea) . "\n";
$ticket .= alinear_derecha("TOTAL A PAGAR: $" . $total, $caracteres_por_linea) . "\n\n";

// Método de pago
$ticket .= "Método de pago: " . mb_strtoupper($venta['metodo_pago']) . "\n\n";

// Pie de página
$ticket .= str_repeat('=', $caracteres_por_linea) . "\n";
$ticket .= centrar_texto("¡GRACIAS POR SU COMPRA!", $caracteres_por_linea) . "\n";
$ticket .= centrar_texto("Lo esperamos pronto", $caracteres_por_linea) . "\n";
$ticket .= str_repeat('=', $caracteres_por_linea) . "\n";

// Imprimir el ticket
header("Content-Type: text/plain; charset=UTF-8");
header("Content-Disposition: inline; filename=factura_" . $venta['numero_factura'] . ".txt");
echo $ticket;
?>
