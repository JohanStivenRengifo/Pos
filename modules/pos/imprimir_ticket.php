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

// Configuración para impresora térmica
$ancho_papel = 80; // mm
$caracteres_por_linea = 48;
$ticket = ""; // Inicializar la variable ticket

// Funciones de formato mejoradas
function centrar_texto($texto, $ancho) {
    if (!is_numeric($ancho)) return $texto;
    $texto = trim($texto);
    $espacios = $ancho - mb_strlen($texto);
    if ($espacios < 0) return $texto;
    $espacios_izquierda = floor($espacios / 2);
    return str_repeat(' ', $espacios_izquierda) . $texto;
}

function alinear_derecha($texto, $ancho) {
    if (!is_numeric($ancho)) return $texto;
    $texto = trim($texto);
    $espacios = $ancho - mb_strlen($texto);
    if ($espacios < 0) return $texto;
    return str_repeat(' ', $espacios) . $texto;
}

function formatear_numero($numero) {
    return number_format($numero, 2, ',', '.');
}

function truncar_texto($texto, $longitud) {
    if (mb_strlen($texto) > $longitud) {
        return mb_substr($texto, 0, $longitud - 3) . '...';
    }
    return $texto;
}

// Función para formatear datos faltantes
function formatearDatoFaltante($valor, $tipo) {
    if (strpos($valor, 'pendiente') !== false || strpos($valor, 'no configurada') !== false) {
        return "[" . strtoupper($tipo) . " NO CONFIGURADO]";
    }
    return $valor;
}

// Obtener información de la venta
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        COALESCE(c.nombre, 'Cliente no registrado') AS cliente_nombre,
        COALESCE(c.identificacion, 'Sin identificación') AS cliente_identificacion,
        COALESCE(u.nombre, 'Vendedor no registrado') AS vendedor_nombre
    FROM ventas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    LEFT JOIN users u ON v.user_id = u.id
    WHERE v.id = ? AND v.user_id = ?
");
$stmt->execute([$venta_id, $user_id]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    die("Venta no encontrada o sin acceso");
}

// Obtener información de la empresa
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(e.nombre_empresa, 'Empresa no configurada') as nombre_empresa,
        COALESCE(e.nit, 'NIT pendiente de configurar') as nit,
        COALESCE(e.direccion, 'Dirección pendiente de configurar') as direccion,
        COALESCE(e.telefono, 'Teléfono pendiente de configurar') as telefono,
        COALESCE(e.correo_contacto, 'Correo pendiente de configurar') as correo_contacto,
        COALESCE(e.prefijo_factura, 'SF') as prefijo_factura,
        COALESCE(e.regimen_fiscal, 'Régimen pendiente de configurar') as regimen_fiscal
    FROM users u
    LEFT JOIN empresas e ON u.empresa_id = e.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener detalles de la venta
$stmt = $pdo->prepare("
    SELECT 
        vd.*,
        COALESCE(p.nombre, 'Producto no encontrado') AS producto_nombre,
        COALESCE(p.codigo_barras, 'Sin código') AS codigo_barras
    FROM venta_detalles vd
    LEFT JOIN inventario p ON vd.producto_id = p.id
    WHERE vd.venta_id = ?
");
$stmt->execute([$venta_id]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar contenido del ticket
$ticket = "";

// Verificar si la empresa está configurada
if ($empresa['nombre_empresa'] === 'Empresa no configurada') {
    $ticket .= str_repeat('*', $caracteres_por_linea) . "\n";
    $ticket .= centrar_texto("IMPORTANTE: CONFIGURACIÓN PENDIENTE", $caracteres_por_linea) . "\n";
    $ticket .= "Por favor, configure los datos de su empresa en el módulo de Configuración\n";
    $ticket .= "para que aparezcan correctamente en sus facturas.\n";
    $ticket .= str_repeat('*', $caracteres_por_linea) . "\n\n";
}

// Encabezado con información de la empresa
$ticket .= str_repeat('=', $caracteres_por_linea) . "\n";
$ticket .= centrar_texto(mb_strtoupper($empresa['nombre_empresa']), $caracteres_por_linea) . "\n";
$ticket .= centrar_texto(formatearDatoFaltante($empresa['nit'], 'nit'), $caracteres_por_linea) . "\n";
$ticket .= centrar_texto(formatearDatoFaltante($empresa['direccion'], 'dirección'), $caracteres_por_linea) . "\n";
$ticket .= centrar_texto("Tel: " . formatearDatoFaltante($empresa['telefono'], 'teléfono'), $caracteres_por_linea) . "\n";
$ticket .= centrar_texto(formatearDatoFaltante($empresa['correo_contacto'], 'correo'), $caracteres_por_linea) . "\n";
$ticket .= centrar_texto(formatearDatoFaltante($empresa['regimen_fiscal'], 'régimen'), $caracteres_por_linea) . "\n";
$ticket .= str_repeat('=', $caracteres_por_linea) . "\n\n";

// Información de la venta
$ticket .= "FACTURA DE VENTA N°: " . $empresa['prefijo_factura'] . "-" . 
           str_pad($venta['numero_factura'], 8, '0', STR_PAD_LEFT) . "\n";
$ticket .= "Fecha: " . date('d/m/Y H:i', strtotime($venta['fecha'])) . "\n";
$ticket .= str_repeat('-', $caracteres_por_linea) . "\n";

// Información del cliente
$ticket .= "Cliente: " . truncar_texto($venta['cliente_nombre'], 35) . "\n";
$ticket .= "NIT/CC: " . $venta['cliente_identificacion'] . "\n";
$ticket .= "Vendedor: " . truncar_texto($venta['vendedor_nombre'], 35) . "\n";
$ticket .= str_repeat('-', $caracteres_por_linea) . "\n\n";

// Encabezados de productos
$ticket .= sprintf("%-20s %8s %8s %10s\n", 
    "PRODUCTO", "CANT", "PRECIO", "TOTAL");
$ticket .= str_repeat('-', $caracteres_por_linea) . "\n";

// Detalles de productos
$subtotal = 0;
foreach ($detalles as $detalle) {
    $nombre_producto = truncar_texto($detalle['producto_nombre'], 20);
    $cantidad = formatear_numero($detalle['cantidad']);
    $precio = formatear_numero($detalle['precio_unitario']);
    $total_linea = $detalle['cantidad'] * $detalle['precio_unitario'];
    $total = formatear_numero($total_linea);
    $subtotal += $total_linea;
    
    $ticket .= sprintf("%-20s %8s %8s %10s\n",
        $nombre_producto, $cantidad, $precio, $total);
    
    // Si el producto tiene código de barras, mostrarlo
    if ($detalle['codigo_barras'] !== 'Sin código') {
        $ticket .= "  Cod: " . $detalle['codigo_barras'] . "\n";
    }
}

// Totales
$ticket .= str_repeat('-', $caracteres_por_linea) . "\n";
$ticket .= alinear_derecha("SUBTOTAL: $" . formatear_numero($subtotal), $caracteres_por_linea) . "\n";

if ($venta['descuento'] > 0) {
    $ticket .= alinear_derecha("DESCUENTO: $" . formatear_numero($venta['descuento']), $caracteres_por_linea) . "\n";
}

$ticket .= str_repeat('-', $caracteres_por_linea) . "\n";
$ticket .= alinear_derecha("TOTAL A PAGAR: $" . formatear_numero($venta['total']), $caracteres_por_linea) . "\n\n";

// Método de pago
$ticket .= "Método de pago: " . mb_strtoupper($venta['metodo_pago']) . "\n";
if ($venta['metodo_pago'] === 'credito') {
    $ticket .= "Plazo: " . $venta['credito_plazo'] . " meses\n";
    $ticket .= "Interés: " . $venta['credito_interes'] . "%\n";
}

// Antes del pie de página
if (strpos($empresa['nombre_empresa'], 'no configurada') !== false || 
    strpos($empresa['nit'], 'pendiente') !== false) {
    $ticket .= "\n" . str_repeat('-', $caracteres_por_linea) . "\n";
    $ticket .= "NOTA: Algunos datos de la empresa están pendientes de configurar.\n";
    $ticket .= "Para una facturación completa, por favor configure los datos\n";
    $ticket .= "faltantes en el módulo de Configuración.\n";
}

// Pie de página
$ticket .= "\n" . str_repeat('=', $caracteres_por_linea) . "\n";
$ticket .= centrar_texto("¡GRACIAS POR SU COMPRA!", $caracteres_por_linea) . "\n";
$ticket .= centrar_texto("Lo esperamos pronto", $caracteres_por_linea) . "\n";
$ticket .= str_repeat('=', $caracteres_por_linea) . "\n\n";

// Información legal y adicional
$ticket .= "Fecha de impresión: " . date('d/m/Y H:i:s') . "\n";
$ticket .= "Esta factura se asimila en todos sus efectos \n a una letra de cambio de conformidad con el \n Art. 774 del código de comercio. Autorizo que \n en caso de incumplimiento de esta obligación \n sea reportado a las centrales de riesgo, se \n cobraran intereses por mora.\n";
$ticket .= "Con esta factura de venta el comprador declara\n haber recibido de forma real y materialmente \nlas mercancías y/o servicios descritos en \n este titulo valor.\n";
$ticket .= "Representación impresa de la factura electrónica\n";

// Imprimir el ticket
header("Content-Type: text/plain; charset=UTF-8");
header("Content-Disposition: inline; filename=factura_" . $venta['numero_factura'] . ".txt");
echo $ticket;
?>
