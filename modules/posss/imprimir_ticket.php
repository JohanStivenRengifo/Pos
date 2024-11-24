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

// Ajustar configuración para impresora térmica de 80mm
$ancho_papel = 80; // mm
$caracteres_por_linea = 48; // Ajustado para 80mm con márgenes
$margen_izquierdo = 2; // Espacios para margen izquierdo

// Función mejorada para centrar texto
function centrar_texto($texto, $ancho) {
    $texto = trim($texto);
    $longitud = mb_strlen($texto, 'UTF-8');
    $espacios = $ancho - $longitud;
    if ($espacios < 0) return $texto;
    $espacios_izquierda = floor($espacios / 2);
    return str_repeat(' ', $espacios_izquierda) . $texto;
}

// Nueva función para aplicar margen izquierdo
function aplicar_margen($texto, $margen) {
    return str_repeat(' ', $margen) . $texto;
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

// Generar contenido del ticket con formato mejorado
$ticket = "\x1B\x40"; // Inicializar impresora
$ticket .= "\x1B\x61\x01"; // Alineación centrada

// Encabezado con formato mejorado
$ticket .= "\x1B\x21\x08"; // Texto en negrita
$ticket .= centrar_texto(mb_strtoupper($empresa['nombre_empresa']), $caracteres_por_linea) . "\n";
$ticket .= "\x1B\x21\x00"; // Texto normal
$ticket .= centrar_texto(formatearDatoFaltante($empresa['nit'], 'nit'), $caracteres_por_linea) . "\n";
$ticket .= centrar_texto(formatearDatoFaltante($empresa['direccion'], 'dirección'), $caracteres_por_linea) . "\n";
$ticket .= centrar_texto("Tel: " . formatearDatoFaltante($empresa['telefono'], 'teléfono'), $caracteres_por_linea) . "\n\n";

// Información de factura con alineación izquierda
$ticket .= "\x1B\x61\x00"; // Alineación izquierda
$ticket .= aplicar_margen("FACTURA: " . $empresa['prefijo_factura'] . "-" . 
           str_pad($venta['numero_factura'], 8, '0', STR_PAD_LEFT), $margen_izquierdo) . "\n";
$ticket .= aplicar_margen("FECHA: " . date('d/m/Y H:i', strtotime($venta['fecha'])), $margen_izquierdo) . "\n";
$ticket .= aplicar_margen("CLIENTE: " . truncar_texto($venta['cliente_nombre'], 35), $margen_izquierdo) . "\n";
$ticket .= aplicar_margen("ID: " . $venta['cliente_identificacion'], $margen_izquierdo) . "\n";
$ticket .= aplicar_margen("VENDEDOR: " . truncar_texto($venta['vendedor_nombre'], 35), $margen_izquierdo) . "\n\n";

// Encabezados de productos
$ticket .= aplicar_margen(str_repeat('-', $caracteres_por_linea - ($margen_izquierdo * 2)), $margen_izquierdo) . "\n";
$ticket .= aplicar_margen(sprintf("%-30s %6s %10s", "PRODUCTO", "CANT", "TOTAL"), $margen_izquierdo) . "\n";
$ticket .= aplicar_margen(str_repeat('-', $caracteres_por_linea - ($margen_izquierdo * 2)), $margen_izquierdo) . "\n";

// Detalles de productos
$subtotal = 0;
foreach ($detalles as $detalle) {
    $nombre_producto = truncar_texto($detalle['producto_nombre'], 30);
    $cantidad = number_format($detalle['cantidad'], 0);
    $total_linea = $detalle['cantidad'] * $detalle['precio_unitario'];
    $total = formatear_numero($total_linea);
    $subtotal += $total_linea;
    
    $ticket .= aplicar_margen(sprintf("%-30s %6s %10s",
        $nombre_producto, $cantidad, $total), $margen_izquierdo) . "\n";
    
    // Mostrar precio unitario en línea separada
    $ticket .= aplicar_margen(sprintf("  Precio unit: $%s", 
        formatear_numero($detalle['precio_unitario'])), $margen_izquierdo) . "\n";
}

// Totales con formato mejorado
$ticket .= "\n";
$ticket .= aplicar_margen(str_repeat('-', $caracteres_por_linea - ($margen_izquierdo * 2)), $margen_izquierdo) . "\n";
$ticket .= aplicar_margen(sprintf("%38s %8s", "SUBTOTAL:", "$" . formatear_numero($subtotal)), $margen_izquierdo) . "\n";

if ($venta['descuento'] > 0) {
    $ticket .= aplicar_margen(sprintf("%38s %8s", "DESCUENTO:", "$" . formatear_numero($venta['descuento'])), $margen_izquierdo) . "\n";
}

$ticket .= aplicar_margen(str_repeat('=', $caracteres_por_linea - ($margen_izquierdo * 2)), $margen_izquierdo) . "\n";
$ticket .= "\x1B\x21\x08"; // Texto en negrita
$ticket .= aplicar_margen(sprintf("%38s %8s", "TOTAL:", "$" . formatear_numero($venta['total'])), $margen_izquierdo) . "\n";
$ticket .= "\x1B\x21\x00"; // Texto normal

// Método de pago
$ticket .= "\n";
$ticket .= aplicar_margen("MÉTODO DE PAGO: " . mb_strtoupper($venta['metodo_pago']), $margen_izquierdo) . "\n";
if ($venta['metodo_pago'] === 'credito') {
    $ticket .= aplicar_margen(sprintf("Plazo: %d meses - Interés: %.2f%%", 
        $venta['credito_plazo'], $venta['credito_interes']), $margen_izquierdo) . "\n";
}

// Pie de página centrado
$ticket .= "\n";
$ticket .= "\x1B\x61\x01"; // Alineación centrada
$ticket .= centrar_texto("¡GRACIAS POR SU COMPRA!", $caracteres_por_linea) . "\n";
$ticket .= centrar_texto(date('d/m/Y H:i:s'), $caracteres_por_linea) . "\n\n";

// Información legal
$ticket .= "\x1B\x61\x00"; // Alineación izquierda
$ticket .= aplicar_margen("Esta factura se asimila a letra de cambio", $margen_izquierdo) . "\n";
$ticket .= aplicar_margen("según Art.774 código de comercio.", $margen_izquierdo) . "\n";
$ticket .= aplicar_margen("Representación impresa factura electrónica", $margen_izquierdo) . "\n";

// Espacios finales y corte
$ticket .= "\n\n\n\x1B\x69"; // Comando de corte

// Imprimir el ticket
header("Content-Type: text/plain; charset=UTF-8");
header("Content-Disposition: inline; filename=factura_" . $venta['numero_factura'] . ".txt");
echo $ticket;
?>