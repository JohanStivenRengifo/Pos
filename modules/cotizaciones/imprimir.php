<?php
session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$cotizacion = null;
$detalles = [];

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de cotización no especificado");
    }

    // Obtener información de la empresa principal
    $query = "SELECT * FROM empresas 
              WHERE usuario_id = ? AND es_principal = 1 
              LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $empresa = $stmt->fetch();

    if (!$empresa) {
        throw new Exception("No se encontró información de la empresa");
    }

    // Obtener la cotización
    $query = "SELECT c.*, 
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion,
                     cl.tipo_identificacion as cliente_tipo_identificacion,
                     cl.email as cliente_email,
                     cl.telefono as cliente_telefono,
                     cl.direccion as cliente_direccion
              FROM cotizaciones c
              LEFT JOIN clientes cl ON c.cliente_id = cl.id
              WHERE c.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        throw new Exception("Cotización no encontrada");
    }

    // Obtener los detalles de la cotización
    $query = "SELECT cd.*, i.codigo_barras, i.nombre as producto_nombre
              FROM cotizacion_detalles cd
              LEFT JOIN inventario i ON cd.producto_id = i.id
              WHERE cd.cotizacion_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll();

    // Crear clase personalizada de PDF
    class CotizacionPDF extends FPDF {
        function Header() {
            global $empresa, $cotizacion;
            
            // Logo
            if (!empty($empresa['logo']) && file_exists('../../' . $empresa['logo'])) {
                $this->Image('../../' . $empresa['logo'], 10, 10, 30);
            }
            
            // Título del documento
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, mb_convert_encoding('COTIZACIÓN N° ' . $cotizacion['numero'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
            // Información de la empresa
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, mb_convert_encoding($empresa['nombre_empresa'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding('NIT: ' . $empresa['nit'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding($empresa['direccion'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
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
    $pdf = new CotizacionPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Información del Cliente
    $pdf->InfoSection('INFORMACIÓN DEL CLIENTE');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, mb_convert_encoding('Cliente: ' . $cotizacion['cliente_nombre'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding($cotizacion['cliente_tipo_identificacion'] . ': ' . $cotizacion['cliente_identificacion'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Dirección: ' . $cotizacion['cliente_direccion'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Teléfono: ' . $cotizacion['cliente_telefono'], 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Ln(5);

    // Información de la Cotización
    $pdf->InfoSection('DETALLES DE LA COTIZACIÓN');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Fecha de Emisión: ' . date('d/m/Y', strtotime($cotizacion['fecha'])), 0, 1);
    $pdf->Cell(0, 6, 'Válida hasta: ' . date('d/m/Y', strtotime($cotizacion['fecha'] . ' + 30 days')), 0, 1);
    $pdf->Cell(0, 6, 'Estado: ' . $cotizacion['estado'], 0, 1);
    $pdf->Ln(5);

    // Tabla de Productos
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 9);
    
    foreach ($detalles as $detalle) {
        $pdf->Cell(25, 6, $detalle['codigo_barras'], 1, 0, 'C');
        $pdf->Cell(80, 6, mb_convert_encoding($detalle['descripcion'], 'ISO-8859-1', 'UTF-8'), 1);
        $pdf->Cell(20, 6, $detalle['cantidad'], 1, 0, 'C');
        $pdf->Cell(30, 6, '$' . number_format($detalle['precio_unitario'], 2, ',', '.'), 1, 0, 'R');
        $pdf->Cell(35, 6, '$' . number_format($detalle['subtotal'], 2, ',', '.'), 1, 1, 'R');
    }

    // Total
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(155, 8, 'Total:', 1, 0, 'R');
    $pdf->Cell(35, 8, '$' . number_format($cotizacion['total'], 2, ',', '.'), 1, 1, 'R');

    // Términos y Condiciones
    $pdf->Ln(10);
    $pdf->InfoSection('TÉRMINOS Y CONDICIONES');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, mb_convert_encoding("• Esta cotización es válida hasta el " . date('d/m/Y', strtotime($cotizacion['fecha'] . ' + 30 days')) . ".\n• Los precios pueden variar sin previo aviso después de la fecha de vencimiento.\n• Los tiempos de entrega son estimados y comienzan a partir de la aprobación.\n• El pago debe realizarse según los términos acordados.\n• Los precios incluyen IVA cuando aplica.", 'ISO-8859-1', 'UTF-8'));

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
    $pdf->Output('I', 'cotizacion_' . $cotizacion['numero'] . '_' . date('Y-m-d') . '.pdf');
    exit;

} catch (Exception $e) {
    error_log('Error generando cotización: ' . $e->getMessage());
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Error al generar la cotización</h1>";
    echo "<p>Lo sentimos, ha ocurrido un error al generar la cotización. Por favor, inténtelo de nuevo más tarde.</p>";
} 