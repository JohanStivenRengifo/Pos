<?php
// Deshabilitar cualquier salida previa
error_reporting(0);
ini_set('display_errors', 0);
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

// Limpiar cualquier salida en el buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Iniciar un nuevo buffer limpio
ob_start();

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
        function __construct() {
            parent::__construct();
            $this->SetFont('Arial', '', 10);
            $this->SetAutoPageBreak(true, 50);
            $this->SetMargins(15, 15, 15);
            $this->AliasNbPages();
            // Definir colores corporativos
            $this->SetDrawColor(220, 220, 220); // Gris claro para bordes
            $this->SetFillColor(245, 245, 245); // Gris más claro para fondos
        }

        function Header() {
            global $empresa, $cotizacion;
            
            // Crear un rectángulo de fondo para el encabezado
            $this->SetFillColor(250, 250, 250);
            $this->Rect(0, 0, 210, 45, 'F');
            
            // Logo y encabezado principal
            if (!empty($empresa['logo']) && file_exists('../../' . $empresa['logo'])) {
                $this->Image('../../' . $empresa['logo'], 15, 10, 40);
            }
            
            // Información de la empresa (centrada)
            $this->SetY(10);
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 8, $this->normalize('Numercia'), 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, $this->normalize('NIT: ' . $empresa['nit']), 0, 1, 'C');
            $this->Cell(0, 5, $this->normalize($empresa['direccion']), 0, 1, 'C');
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, $this->normalize('Tel: ' . $empresa['telefono']), 0, 1, 'C');
            $this->Cell(0, 5, $this->normalize($empresa['correo_contacto']), 0, 1, 'C');

            // Número de cotización y tipo (lado derecho)
            $this->SetXY(130, 10);
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(50, 50, 50);
            $this->Cell(70, 8, $this->normalize('COTIZACIÓN'), 0, 1, 'R');
            
            $this->SetXY(130, 18);
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(70, 8, $this->normalize('No. ' . $cotizacion['numero']), 0, 1, 'R');
            
            // Fechas
            $this->SetXY(130, 26);
            $this->SetFont('Arial', '', 9);
            $this->Cell(70, 5, $this->normalize('Fecha de emisión: ' . date('d/m/Y', strtotime($cotizacion['fecha']))), 0, 1, 'R');
            $this->SetXY(130, 31);
            $this->Cell(70, 5, $this->normalize('Válida hasta: ' . date('d/m/Y', strtotime($cotizacion['fecha'] . ' + 30 days'))), 0, 1, 'R');
            
            $this->Ln(15);
        }

        function normalize($string) {
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $string);
        }

        function InfoCliente() {
            global $cotizacion;
            
            // Rectángulo gris claro para la sección del cliente
            $this->SetFillColor(248, 248, 248);
            $this->RoundedRect(10, $this->GetY(), 190, 40, 2, 'F');
            
            // Título de la sección
            $this->SetFont('Arial', 'B', 11);
            $this->SetTextColor(50, 50, 50);
            $this->Cell(190, 8, $this->normalize('INFORMACIÓN DEL CLIENTE'), 'B', 1, 'L');
            
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', '', 9);
            $y = $this->GetY() + 2;
            
            // Diseño en dos columnas
            $col_width = 90;
            $this->SetFont('Arial', 'B', 9);
            
            // Primera columna
            $this->SetXY(15, $y);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Cliente:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($cotizacion['cliente_nombre']), 0, 0, 'L');
            
            // Segunda columna
            $this->SetX(110);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('NIT/CC:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($cotizacion['cliente_identificacion']), 0, 1, 'L');
            
            // Segunda fila
            $this->SetXY(15, $y + 8);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Dirección:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($cotizacion['cliente_direccion']), 0, 0, 'L');
            
            $this->SetX(110);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Teléfono:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($cotizacion['cliente_telefono']), 0, 1, 'L');
            
            // Tercera fila
            $this->SetXY(15, $y + 16);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Email:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell(155, 6, $this->normalize($cotizacion['cliente_email']), 0, 1, 'L');
            
            $this->Ln(12);
        }

        function TableHeader() {
            // Encabezados de la tabla con mejor diseño
            $this->SetFillColor(240, 240, 240);
            $this->SetFont('Arial', 'B', 9);
            $this->SetTextColor(50, 50, 50);
            
            // Encabezados con bordes más sutiles y mejor espaciado
            $this->SetDrawColor(200, 200, 200);
            $this->Cell(25, 8, $this->normalize('Código'), 'TB', 0, 'C', true);
            $this->Cell(85, 8, $this->normalize('Descripción'), 'TB', 0, 'L', true);
            $this->Cell(20, 8, $this->normalize('Cant.'), 'TB', 0, 'C', true);
            $this->Cell(30, 8, $this->normalize('Precio'), 'TB', 0, 'R', true);
            $this->Cell(30, 8, $this->normalize('Total'), 'TB', 1, 'R', true);
            
            $this->SetTextColor(0, 0, 0);
            $this->SetDrawColor(220, 220, 220);
        }

        function RoundedRect($x, $y, $w, $h, $r, $style = '') {
            $k = $this->k;
            $hp = $this->h;
            if($style=='F')
                $op='f';
            elseif($style=='FD' || $style=='DF')
                $op='B';
            else
                $op='S';
            $MyArc = 4/3 * (sqrt(2) - 1);
            $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
            $xc = $x+$w-$r ;
            $yc = $y+$r;
            $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));

            $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
            $xc = $x+$w-$r ;
            $yc = $y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
            $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
            $xc = $x+$r ;
            $yc = $y+$h-$r;
            $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
            $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
            $xc = $x+$r ;
            $yc = $y+$r;
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
            $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
            $this->_out($op);
        }

        function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
            $h = $this->h;
            $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
                $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
        }

        function Footer() {
            global $cotizacion;
            $this->SetY(-60);
            
            // Términos y condiciones en un cuadro
            $this->SetFillColor(250, 250, 250);
            $this->RoundedRect(10, $this->GetY(), 190, 25, 2, 'F');
            $this->SetFont('Arial', '', 8);
            $this->SetXY(15, $this->GetY() + 2);
            $this->MultiCell(180, 4, $this->normalize(
                "• Esta cotización es válida hasta el " . date('d/m/Y', strtotime($cotizacion['fecha'] . ' + 30 days')) . ".\n" .
                "• Los precios pueden variar sin previo aviso después de la fecha de vencimiento.\n" .
                "• Los tiempos de entrega son estimados y comienzan a partir de la aprobación.\n" .
                "• Los precios incluyen IVA cuando aplica."), 0, 'L');
            
            // Línea para firmas
            $this->Ln(8);
            $this->SetDrawColor(200, 200, 200);
            $this->Cell(95, 0, '', 'T', 0, 'C');
            $this->Cell(10, 0, '', 0, 0);
            $this->Cell(85, 0, '', 'T', 1, 'C');
            
            $this->SetFont('Arial', '', 8);
            $this->Cell(95, 4, $this->normalize('ELABORADO POR'), 0, 0, 'C');
            $this->Cell(10, 4, '', 0, 0);
            $this->Cell(85, 4, $this->normalize('ACEPTADA, FIRMA Y/O SELLO'), 0, 1, 'C');
            
            // Pie de página
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 5, $this->normalize('Página ' . $this->PageNo() . '/{nb}'), 0, 1, 'C');
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 5, $this->normalize('Generado por www.numercia.com'), 0, 1, 'C');
        }
    }

    // Antes de generar el PDF
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Enviar headers
    header('Content-Type: application/pdf');
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Crear y generar el PDF
    $pdf = new CotizacionPDF();
    $pdf->AddPage();
    
    // Generar contenido
    $pdf->InfoCliente();

    // Detalle de productos
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 9);
    
    foreach ($detalles as $detalle) {
        $pdf->Cell(25, 6, substr($detalle['codigo_barras'], -6), 1, 0, 'C');
        $pdf->Cell(85, 6, $pdf->normalize($detalle['producto_nombre']), 1, 0, 'L');
        $pdf->Cell(20, 6, number_format($detalle['cantidad'], 0), 1, 0, 'C');
        $pdf->Cell(30, 6, '$ ' . number_format($detalle['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, '$ ' . number_format($detalle['subtotal'], 0, ',', '.'), 1, 1, 'R');
    }

    // Totales
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 10);
    
    // Crear una tabla para los totales con mejor diseño
    $width_label = 40;
    $width_value = 40;
    $x_position = $pdf->GetPageWidth() - $width_label - $width_value - 15;

    // Total
    $pdf->SetX($x_position);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell($width_label, 9, $pdf->normalize('TOTAL:'), 'T', 0, 'R');
    $pdf->Cell($width_value, 9, '$ ' . number_format($cotizacion['total'], 0, ',', '.'), 'T', 1, 'R');

    // Estado de la cotización
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, $pdf->normalize('Estado de la Cotización:'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, $pdf->normalize($cotizacion['estado']), 0, 1, 'L');

    try {
        // Asegurarse de que no haya salida previa
        if (headers_sent($filename, $linenum)) {
            throw new Exception("Headers already sent in $filename on line $linenum");
        }

        // Limpiar cualquier salida en el buffer una vez más antes de generar el PDF
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Enviar headers
        header('Content-Type: application/pdf');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Enviar el PDF
        $pdf->Output('I', 'cotizacion_' . $cotizacion['numero'] . '.pdf', true);
    } catch (Exception $e) {
        throw new Exception('Error al generar el PDF: ' . $e->getMessage());
    }
    exit;

} catch (Exception $e) {
    // Limpiar buffer en caso de error
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log('Error generando cotización: ' . $e->getMessage());
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Error</title></head><body>";
    echo "<h1>Error al generar la cotización</h1>";
    echo "<p>Lo sentimos, ha ocurrido un error al generar la cotización. Por favor, inténtelo de nuevo más tarde.</p>";
    if (isset($_SESSION['user_id'])) {
        echo "<p>Error técnico: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</body></html>";
    exit;
} 