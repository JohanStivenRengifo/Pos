<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

try {
    // Obtener datos de la cotización
    $stmt = $pdo->prepare("
        SELECT c.*,
               cl.primer_nombre, cl.segundo_nombre, cl.apellidos,
               cl.identificacion, cl.direccion, cl.telefono,
               cl.tipo_persona, cl.responsabilidad_tributaria,
               e.nombre_empresa, e.nit, e.direccion as empresa_direccion,
               e.telefono as empresa_telefono, e.correo_contacto,
               e.regimen_fiscal, e.logo
        FROM cotizaciones c
        LEFT JOIN clientes cl ON c.cliente_id = cl.id
        LEFT JOIN empresas e ON e.usuario_id = ?
        WHERE c.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $_GET['id']]);
    $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cotizacion) {
        throw new Exception('Cotización no encontrada');
    }

    // Obtener detalles de la cotización
    $stmt = $pdo->prepare("
        SELECT cd.*, i.nombre, i.codigo_barras
        FROM cotizacion_detalles cd
        LEFT JOIN inventario i ON cd.producto_id = i.id
        WHERE cd.cotizacion_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear clase personalizada de PDF
    class POSCotizacionPDF extends FPDF {
        function Header() {
            global $cotizacion;
            
            // Logo
            if (!empty($cotizacion['logo']) && file_exists('../../../' . $cotizacion['logo'])) {
                $this->Image('../../../' . $cotizacion['logo'], 10, 10, 30);
            }
            
            // Título del documento
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, mb_convert_encoding('COTIZACIÓN N° ' . $cotizacion['numero'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
            // Información de la empresa
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, mb_convert_encoding($cotizacion['nombre_empresa'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding('NIT: ' . $cotizacion['nit'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding($cotizacion['empresa_direccion'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding('Tel: ' . $cotizacion['empresa_telefono'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
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
            $this->Cell(80, 7, 'Descripción', 1, 0, 'L', true);
            $this->Cell(20, 7, 'Cant.', 1, 0, 'C', true);
            $this->Cell(30, 7, 'Precio Unit.', 1, 0, 'R', true);
            $this->Cell(35, 7, 'Subtotal', 1, 1, 'R', true);
        }
    }

    // Crear nuevo PDF
    $pdf = new POSCotizacionPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Información del Cliente
    $pdf->InfoSection('INFORMACIÓN DEL CLIENTE');
    $pdf->SetFont('Arial', '', 10);
    $nombre_cliente = trim($cotizacion['primer_nombre'] . ' ' . $cotizacion['segundo_nombre'] . ' ' . $cotizacion['apellidos']);
    $pdf->Cell(0, 6, mb_convert_encoding('Cliente: ' . $nombre_cliente, 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Identificación: ' . $cotizacion['identificacion'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Dirección: ' . $cotizacion['direccion'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Teléfono: ' . $cotizacion['telefono'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Ln(5);

    // Información de la Cotización
    $pdf->InfoSection('DETALLES DE LA COTIZACIÓN');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Fecha de Emisión: ' . date('d/m/Y', strtotime($cotizacion['fecha'])), 0, 1);
    $pdf->Cell(0, 6, 'Válida hasta: ' . date('d/m/Y', strtotime($cotizacion['fecha'] . ' + 15 days')), 0, 1);
    $pdf->Ln(5);

    // Tabla de Productos
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 9);
    
    foreach ($detalles as $detalle) {
        $pdf->Cell(25, 6, $detalle['codigo_barras'], 1, 0, 'C');
        $pdf->Cell(80, 6, mb_convert_encoding($detalle['descripcion'], 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->Cell(20, 6, $detalle['cantidad'], 1, 0, 'C');
        $pdf->Cell(30, 6, '$' . number_format($detalle['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(35, 6, '$' . number_format($detalle['subtotal'], 0, ',', '.'), 1, 1, 'R');
    }

    // Total
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(155, 8, 'Total:', 1, 0, 'R');
    $pdf->Cell(35, 8, '$' . number_format($cotizacion['total'], 0, ',', '.'), 1, 1, 'R');

    // Términos y Condiciones
    $pdf->Ln(10);
    $pdf->InfoSection('TÉRMINOS Y CONDICIONES');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, mb_convert_encoding(
        "• Esta cotización tiene una validez de 15 días a partir de la fecha de emisión.\n" .
        "• Los precios están sujetos a cambios sin previo aviso.\n" .
        "• Los tiempos de entrega se confirmarán al momento de la orden.\n" .
        "• Esta cotización no representa un compromiso de venta.", 
        'ISO-8859-1', 'UTF-8'));

    // Espacios para firmas
    $pdf->Ln(20);
    $pdf->Cell(95, 0, '', 'T', 0, 'C');
    $pdf->Cell(10, 0, '', 0, 0);
    $pdf->Cell(95, 0, '', 'T', 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 5, 'Firma del Vendedor', 0, 0, 'C');
    $pdf->Cell(10, 5, '', 0, 0);
    $pdf->Cell(95, 5, 'Firma del Cliente', 0, 1, 'C');

    // Generar el PDF
    $pdf->Output('I', 'cotizacion_pos_' . $cotizacion['numero'] . '_' . date('Y-m-d') . '.pdf');
    exit;

} catch (Exception $e) {
    error_log('Error generando cotización POS: ' . $e->getMessage());
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Error al generar la cotización</h1>";
    echo "<p>Lo sentimos, ha ocurrido un error al generar la cotización. Por favor, inténtelo de nuevo más tarde.</p>";
} 