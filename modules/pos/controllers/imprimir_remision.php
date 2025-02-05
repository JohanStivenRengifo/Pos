<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

try {
    // Obtener datos de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, 
               c.primer_nombre, c.segundo_nombre, c.apellidos, 
               c.identificacion, c.direccion, c.telefono,
               e.nombre_empresa, e.nit, e.direccion as empresa_direccion,
               e.telefono as empresa_telefono, e.logo, e.correo_contacto as empresa_email,
               e.regimen_fiscal
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON e.estado = 1 AND e.es_principal = 1
        WHERE v.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener detalles
    $stmt = $pdo->prepare("
        SELECT vd.*, i.nombre, i.codigo_barras, i.ubicacion
        FROM venta_detalles vd
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear clase personalizada de PDF
    class RemisionPDF extends FPDF {
        function Header() {
            global $venta;
            
            // Logo
            if (!empty($venta['logo']) && file_exists('../../../' . $venta['logo'])) {
                $this->Image('../../../' . $venta['logo'], 10, 10, 30);
            }
            
            // Título del documento
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, mb_convert_encoding('REMISIÓN N° ' . $venta['numero_factura'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
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
            $this->Cell(25, 7, 'Código', 1, 0, 'C', true);
            $this->Cell(95, 7, 'Descripción', 1, 0, 'L', true);
            $this->Cell(25, 7, 'Cantidad', 1, 0, 'C', true);
            $this->Cell(35, 7, 'Valor Unit.', 1, 0, 'R', true);
            $this->Cell(40, 7, 'Valor Total', 1, 1, 'R', true);
        }

        // Agregar función para márgenes
        function SetDocumentMargins() {
            $this->SetMargins(15, 15, 15);
            $this->SetAutoPageBreak(true, 25);
        }
    }

    // Crear nuevo PDF
    $pdf = new RemisionPDF();
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
    $pdf->Ln(5);

    // Información de la Remisión
    $pdf->InfoSection('DETALLE DE PRODUCTOS');
    $pdf->Ln(2);

    // Tabla de Productos
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 9);
    
    $subtotal = 0;
    foreach ($detalles as $detalle) {
        $precio_final = $detalle['precio_unitario'] - $detalle['descuento'];
        $total_item = $precio_final * $detalle['cantidad'];
        $subtotal += $total_item;

        $pdf->Cell(25, 6, $detalle['codigo_barras'], 1, 0, 'C');
        $pdf->Cell(95, 6, mb_convert_encoding($detalle['nombre'], 'ISO-8859-1', 'UTF-8'), 1, 0, 'L');
        $pdf->Cell(25, 6, $detalle['cantidad'], 1, 0, 'C');
        $pdf->Cell(35, 6, '$' . number_format($precio_final, 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(40, 6, '$' . number_format($total_item, 0, ',', '.'), 1, 1, 'R');
    }

    // Totales
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 10);
    
    // Alinear totales a la derecha con ancho fijo
    $pdf->Cell(140, 6, '', 0, 0);
    $pdf->Cell(40, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(40, 6, '$' . number_format($subtotal, 0, ',', '.'), 0, 1, 'R');
    
    if ($venta['descuento'] > 0) {
        $pdf->Cell(140, 6, '', 0, 0);
        $pdf->Cell(40, 6, 'Descuento:', 0, 0, 'R');
        $pdf->Cell(40, 6, '$' . number_format($venta['descuento'], 0, ',', '.'), 0, 1, 'R');
    }
    
    $pdf->Cell(140, 6, '', 0, 0);
    $pdf->Cell(40, 6, 'IVA (19%):', 0, 0, 'R');
    $pdf->Cell(40, 6, '$' . number_format($venta['total'] * 0.19, 0, ',', '.'), 0, 1, 'R');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(140, 6, '', 0, 0);
    $pdf->Cell(40, 6, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(40, 6, '$' . number_format($venta['total'] * 1.19, 0, ',', '.'), 0, 1, 'R');

    // Observaciones
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'Observaciones:', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, mb_convert_encoding(
        "• Esta remisión no tiene validez como factura.\n" .
        "• Los precios incluyen IVA cuando aplica.\n" .
        "• Conserve este documento para cualquier reclamación.", 
        'ISO-8859-1', 'UTF-8'));

    // Espacios para firmas
    $pdf->Ln(15);
    $pdf->Cell(95, 0, '', 'T', 0, 'C');
    $pdf->Cell(20, 0, '', 0, 0);
    $pdf->Cell(95, 0, '', 'T', 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 5, 'ENTREGADO POR', 0, 0, 'C');
    $pdf->Cell(20, 5, '', 0, 0);
    $pdf->Cell(95, 5, 'RECIBIDO POR', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(95, 5, mb_convert_encoding($venta['nombre_empresa'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->Cell(20, 5, '', 0, 0);
    $pdf->Cell(95, 5, mb_convert_encoding($nombre_cliente, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

    // Pie de página personalizado
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'Generado en www.johanrengifo.cloud', 0, 1, 'C');

    // Generar el PDF
    $pdf->Output('I', 'remision_' . $venta['numero_factura'] . '_' . date('Y-m-d') . '.pdf');
    exit;

} catch (Exception $e) {
    error_log('Error generando remisión: ' . $e->getMessage());
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Error al generar la remisión</h1>";
    echo "<p>Lo sentimos, ha ocurrido un error al generar la remisión. Por favor, inténtelo de nuevo más tarde.</p>";
} 