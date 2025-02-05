<?php
// Primero, asegurarnos de que no haya salida previa
ob_start();

require_once '../../../config/db.php';
require_once '../../../vendor/autoload.php';

// Habilitar el reporte de errores pero guardarlos en el log en lugar de mostrarlos
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Verificar y cargar FPDF
$fpdfPaths = [
    'fpdf/fpdf.php',                    // Ruta relativa actual
    '../fpdf/fpdf.php',                 // Un nivel arriba
    '../../fpdf/fpdf.php',             // Dos niveles arriba
    '../../../fpdf/fpdf.php',          // Tres niveles arriba
    '../../../vendor/fpdf/fpdf.php',   // En vendor
    dirname(__FILE__) . '/fpdf/fpdf.php' // Ruta absoluta
];

$fpdfLoaded = false;
foreach ($fpdfPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $fpdfLoaded = true;
        break;
    }
}

if (!$fpdfLoaded) {
    die('Error: La librería FPDF no está instalada. Por favor, instale FPDF en una de las siguientes ubicaciones: ' . implode(', ', $fpdfPaths));
}

session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

// Antes de cualquier salida, agregar esto al inicio del archivo, justo después de los requires
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/pdf');

try {
    // Obtener datos de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, 
               c.primer_nombre, c.segundo_nombre, c.apellidos, 
               c.identificacion, c.direccion, c.telefono,
               e.nombre_empresa, e.nit, e.direccion as empresa_direccion,
               e.telefono as empresa_telefono, e.logo, e.correo_contacto as empresa_email,
               e.regimen_fiscal, e.prefijo_factura, e.numero_inicial, e.numero_final
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON e.estado = 1 AND e.es_principal = 1
        WHERE v.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener detalles
    $stmt = $pdo->prepare("
        SELECT vd.*, i.nombre, i.codigo_barras
        FROM venta_detalles vd
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear clase personalizada de PDF
    class FacturaPDF extends FPDF {
        function Header() {
            global $venta;
            
            // Logo
            if (!empty($venta['logo']) && file_exists('../../../' . $venta['logo'])) {
                $this->Image('../../../' . $venta['logo'], 10, 10, 30);
            }
            
            // Título del documento
            $this->SetFont('Arial', 'B', 16);
            if ($venta['numeracion'] === 'electronica') {
                $this->Cell(0, 10, mb_convert_encoding('FACTURA ELECTRÓNICA DE VENTA', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            } else {
                $this->Cell(0, 10, mb_convert_encoding('FACTURA DE VENTA', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            }
            
            // Número de factura
            $this->SetFont('Arial', '', 12);
            $this->Cell(0, 6, mb_convert_encoding('No. ' . ($venta['prefijo_factura'] ?? '') . $venta['numero_factura'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
            // Información de la empresa
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, mb_convert_encoding($venta['nombre_empresa'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding('NIT: ' . $venta['nit'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding($venta['empresa_direccion'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding('Tel: ' . $venta['empresa_telefono'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding('Email: ' . $venta['empresa_email'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
            $this->Ln(5);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, mb_convert_encoding('Página ' . $this->PageNo() . '/{nb}', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        }

        function InfoSection($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(230, 230, 230);
            $this->Cell(0, 8, mb_convert_encoding($title, 'ISO-8859-1', 'UTF-8'), 0, 1, 'L', true);
            $this->Ln(4);
        }

        function TableHeader() {
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor(240, 240, 240);
            $this->Cell(15, 7, 'Cód.', 1, 0, 'C', true);
            $this->Cell(85, 7, 'Descripción', 1, 0, 'L', true);
            $this->Cell(20, 7, 'Cant.', 1, 0, 'C', true);
            $this->Cell(25, 7, 'V.Unit', 1, 0, 'R', true);
            $this->Cell(25, 7, 'Total', 1, 1, 'R', true);
        }

        function SetDocumentMargins() {
            $this->SetMargins(15, 15, 15);
            $this->SetAutoPageBreak(true, 25);
        }
    }

    // Crear nuevo PDF
    $pdf = new FacturaPDF();
    $pdf->SetDocumentMargins();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Información del Cliente
    $pdf->InfoSection('INFORMACIÓN DEL CLIENTE');
    $pdf->SetFont('Arial', '', 10);
    $nombre_cliente = trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos']);
    
    // Organizar información del cliente en dos columnas
    $pdf->Cell(95, 6, mb_convert_encoding('Cliente: ' . $nombre_cliente, 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->Cell(95, 6, mb_convert_encoding('Identificación: ' . $venta['identificacion'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(95, 6, mb_convert_encoding('Teléfono: ' . $venta['telefono'], 'ISO-8859-1', 'UTF-8'), 0, 0);
    $pdf->Cell(95, 6, mb_convert_encoding('Fecha: ' . date('d/m/Y', strtotime($venta['fecha'])), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Dirección: ' . $venta['direccion'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Email: ' . $venta['email'], 'ISO-8859-1', 'UTF-8'), 0, 1);
        $pdf->Ln(5);

    // Detalle de productos
    $pdf->InfoSection('DETALLE DE PRODUCTOS');
    $pdf->Ln(2);

    // Tabla de Productos
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 9);
    
    $subtotal = 0;
    foreach ($detalles as $detalle) {
        $total_item = $detalle['cantidad'] * $detalle['precio_unitario'];
        $subtotal += $total_item;

        $pdf->Cell(15, 6, substr($detalle['codigo_barras'], -4), 1, 0, 'C');
        $pdf->Cell(85, 6, utf8_decode($detalle['nombre']), 1, 0, 'L');
        $pdf->Cell(20, 6, $detalle['cantidad'], 1, 0, 'C');
        $pdf->Cell(25, 6, '$' . number_format($detalle['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(25, 6, '$' . number_format($total_item, 0, ',', '.'), 1, 1, 'R');
    }

    // Totales
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 10);
    
    // Alinear totales a la derecha con ancho fijo
    $pdf->Cell(120, 6, '', 0, 0);
    $pdf->Cell(25, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(25, 6, '$' . number_format($subtotal, 0, ',', '.'), 0, 1, 'R');
    
    if ($venta['descuento'] > 0) {
        $pdf->Cell(120, 6, '', 0, 0);
        $pdf->Cell(25, 6, 'Descuento:', 0, 0, 'R');
        $pdf->Cell(25, 6, '$' . number_format($venta['descuento'], 0, ',', '.'), 0, 1, 'R');
    }
    
    $pdf->Cell(120, 6, '', 0, 0);
    $pdf->Cell(25, 6, 'IVA (19%):', 0, 0, 'R');
    $pdf->Cell(25, 6, '$' . number_format($venta['total'] * 0.19, 0, ',', '.'), 0, 1, 'R');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(120, 6, '', 0, 0);
    $pdf->Cell(25, 6, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(25, 6, '$' . number_format($venta['total'] * 1.19, 0, ',', '.'), 0, 1, 'R');

    // Información legal y resolución DIAN
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'Información Legal:', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, mb_convert_encoding(
        "• Esta factura se asimila en todos sus efectos a una letra de cambio según Art. 774 del Código de Comercio.\n" .
        "• Resolución DIAN: " . ($venta['numero_inicial'] ?? '') . " al " . ($venta['numero_final'] ?? '') . "\n" .
        "• Régimen Fiscal: " . ($venta['regimen_fiscal'] ?? 'No responsable de IVA'),
        'ISO-8859-1', 'UTF-8'));

    // Si es factura electrónica, agregar CUFE y QR
    if ($venta['numeracion'] === 'electronica' && !empty($venta['cufe'])) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(0, 6, 'CUFE:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(0, 4, $venta['cufe'], 0, 'L');

            if (!empty($venta['qr_code'])) {
                $qrImage = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $venta['qr_code']));
                $tmpfile = tempnam(sys_get_temp_dir(), 'qr_');
                file_put_contents($tmpfile, $qrImage);
                $pdf->Image($tmpfile, 15, $pdf->GetY() + 5, 30);
                unlink($tmpfile);
            }
        }

    // Espacios para firmas
    $pdf->Ln(20);
    $pdf->Cell(95, 0, '', 'T', 0, 'C');
    $pdf->Cell(20, 0, '', 0, 0);
    $pdf->Cell(95, 0, '', 'T', 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 5, 'VENDEDOR', 0, 0, 'C');
    $pdf->Cell(20, 5, '', 0, 0);
    $pdf->Cell(95, 5, 'CLIENTE', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(95, 5, mb_convert_encoding($venta['nombre_empresa'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->Cell(20, 5, '', 0, 0);
    $pdf->Cell(95, 5, mb_convert_encoding($nombre_cliente, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

    // Pie de página personalizado
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado en www.johanrengifo.cloud', 0, 1, 'C');

    // Generar el PDF
    $pdf->Output('I', 'factura_' . $venta['numero_factura'] . '_' . date('Y-m-d') . '.pdf');
    exit;

} catch (Exception $e) {
    error_log('Error generando factura: ' . $e->getMessage());
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Error al generar la factura</h1>";
    echo "<p>Lo sentimos, ha ocurrido un error al generar la factura. Por favor, inténtelo de nuevo más tarde.</p>";
}
