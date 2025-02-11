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
$credito = null;
$pagos = [];

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de crédito no especificado");
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

    // Obtener información del crédito
    $query = "SELECT c.*, 
                     v.numero_factura,
                     v.total as venta_total,
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre,
                     cl.identificacion as cliente_identificacion,
                     cl.tipo_identificacion as cliente_tipo_identificacion,
                     cl.email as cliente_email,
                     cl.telefono as cliente_telefono,
                     cl.direccion as cliente_direccion
              FROM creditos c
              LEFT JOIN ventas v ON c.venta_id = v.id
              LEFT JOIN clientes cl ON v.cliente_id = cl.id
              WHERE c.id = ? AND cl.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $credito = $stmt->fetch();

    if (!$credito) {
        throw new Exception("Crédito no encontrado");
    }

    // Obtener pagos del crédito
    $query = "SELECT * FROM creditos_pagos 
              WHERE credito_id = ? 
              ORDER BY numero_cuota ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $pagos = $stmt->fetchAll();

    // Calcular estadísticas
    $pagos_realizados = array_filter($pagos, fn($p) => $p['estado'] === 'Pagado');
    $total_pagado = array_sum(array_column($pagos_realizados, 'monto'));

    // Crear clase personalizada de PDF
    class CreditoPDF extends FPDF {
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
            global $empresa, $credito;
            
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
            $this->Cell(0, 8, $this->normalize('VendEasy'), 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, $this->normalize('NIT: ' . $empresa['nit']), 0, 1, 'C');
            $this->Cell(0, 5, $this->normalize($empresa['direccion']), 0, 1, 'C');
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, $this->normalize('Tel: ' . $empresa['telefono']), 0, 1, 'C');
            $this->Cell(0, 5, $this->normalize($empresa['correo_contacto']), 0, 1, 'C');

            // Título del documento (lado derecho)
            $this->SetXY(130, 10);
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(50, 50, 50);
            $this->Cell(70, 8, $this->normalize('ESTADO DE CUENTA'), 0, 1, 'R');
            
            $this->SetXY(130, 18);
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(70, 8, $this->normalize('Factura No. ' . $credito['numero_factura']), 0, 1, 'R');
            
            // Fecha
            $this->SetXY(130, 26);
            $this->SetFont('Arial', '', 9);
            $this->Cell(70, 5, $this->normalize('Fecha: ' . date('d/m/Y')), 0, 1, 'R');
            
            $this->Ln(15);
        }

        function normalize($string) {
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $string);
        }

        function InfoCliente() {
            global $credito;
            
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
            $this->Cell($col_width - 25, 6, $this->normalize($credito['cliente_nombre']), 0, 0, 'L');
            
            // Segunda columna
            $this->SetX(110);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('NIT/CC:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($credito['cliente_identificacion']), 0, 1, 'L');
            
            // Segunda fila
            $this->SetXY(15, $y + 8);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Dirección:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($credito['cliente_direccion']), 0, 0, 'L');
            
            $this->SetX(110);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Teléfono:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($credito['cliente_telefono']), 0, 1, 'L');
            
            // Tercera fila
            $this->SetXY(15, $y + 16);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Email:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell(155, 6, $this->normalize($credito['cliente_email']), 0, 1, 'L');
            
            $this->Ln(12);
        }

        function ResumenCredito() {
            global $credito, $total_pagado;
            
            // Rectángulo gris claro para el resumen
            $this->SetFillColor(248, 248, 248);
            $this->RoundedRect(10, $this->GetY(), 190, 35, 2, 'F');
            
            // Título de la sección
            $this->SetFont('Arial', 'B', 11);
            $this->SetTextColor(50, 50, 50);
            $this->Cell(190, 8, $this->normalize('RESUMEN DEL CRÉDITO'), 'B', 1, 'L');
            
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', '', 9);
            
            // Primera fila
            $this->Cell(95, 6, $this->normalize('Monto Total: $ ' . number_format($credito['monto_total'], 0, ',', '.')), 0, 0);
            $this->Cell(95, 6, $this->normalize('Total Pagado: $ ' . number_format($total_pagado, 0, ',', '.')), 0, 1);
            
            // Segunda fila
            $this->Cell(95, 6, $this->normalize('Interés: ' . number_format($credito['interes'], 2) . '%'), 0, 0);
            $this->Cell(95, 6, $this->normalize('Saldo Pendiente: $ ' . number_format($credito['saldo_pendiente'], 0, ',', '.')), 0, 1);
            
            // Tercera fila
            $this->Cell(95, 6, $this->normalize('Plazo: ' . $credito['plazo'] . ' días'), 0, 0);
            $this->Cell(95, 6, $this->normalize('Valor Cuota: $ ' . number_format($credito['valor_cuota'], 0, ',', '.')), 0, 1);
            
            $this->Ln(10);
        }

        function TableHeader() {
            // Encabezados de la tabla con mejor diseño
            $this->SetFillColor(240, 240, 240);
            $this->SetFont('Arial', 'B', 9);
            $this->SetTextColor(50, 50, 50);
            
            // Encabezados con bordes más sutiles y mejor espaciado
            $this->SetDrawColor(200, 200, 200);
            $this->Cell(15, 8, $this->normalize('Cuota'), 'TB', 0, 'C', true);
            $this->Cell(25, 8, $this->normalize('Vencimiento'), 'TB', 0, 'C', true);
            $this->Cell(30, 8, $this->normalize('Capital'), 'TB', 0, 'R', true);
            $this->Cell(30, 8, $this->normalize('Interés'), 'TB', 0, 'R', true);
            $this->Cell(30, 8, $this->normalize('Total'), 'TB', 0, 'R', true);
            $this->Cell(30, 8, $this->normalize('Estado'), 'TB', 0, 'C', true);
            $this->Cell(30, 8, $this->normalize('Fecha Pago'), 'TB', 1, 'C', true);
            
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
            $this->SetY(-60);
            
            // Términos y condiciones en un cuadro
            $this->SetFillColor(250, 250, 250);
            $this->RoundedRect(10, $this->GetY(), 190, 25, 2, 'F');
            $this->SetFont('Arial', '', 8);
            $this->SetXY(15, $this->GetY() + 2);
            $this->MultiCell(180, 4, $this->normalize(
                "• Este documento es un estado de cuenta y no representa un comprobante de pago.\n" .
                "• Los pagos deben realizarse en las fechas establecidas para evitar intereses moratorios.\n" .
                "• Conserve sus comprobantes de pago.\n" .
                "• Para cualquier aclaración, presente este documento."), 0, 'L');
            
            // Línea para firmas
            $this->Ln(8);
            $this->SetDrawColor(200, 200, 200);
            $this->Cell(95, 0, '', 'T', 0, 'C');
            $this->Cell(10, 0, '', 0, 0);
            $this->Cell(85, 0, '', 'T', 1, 'C');
            
            $this->SetFont('Arial', '', 8);
            $this->Cell(95, 4, $this->normalize('FIRMA DEL CLIENTE'), 0, 0, 'C');
            $this->Cell(10, 4, '', 0, 0);
            $this->Cell(85, 4, $this->normalize('POR LA EMPRESA'), 0, 1, 'C');
            
            // Pie de página
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 5, $this->normalize('Página ' . $this->PageNo() . '/{nb}'), 0, 1, 'C');
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 5, $this->normalize('Generado por www.johanrengifo.cloud'), 0, 1, 'C');
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
    $pdf = new CreditoPDF();
    $pdf->AddPage();
    
    // Generar contenido
    $pdf->InfoCliente();
    $pdf->ResumenCredito();

    // Plan de pagos
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, $pdf->normalize('PLAN DE PAGOS'), 0, 1, 'L');
    $pdf->Ln(2);

    // Detalle de pagos
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 9);
    
    foreach ($pagos as $pago) {
        $pdf->Cell(15, 6, $pago['numero_cuota'] . '/' . $credito['cuotas'], 1, 0, 'C');
        $pdf->Cell(25, 6, date('d/m/Y', strtotime($pago['fecha_vencimiento_cuota'])), 1, 0, 'C');
        $pdf->Cell(30, 6, '$ ' . number_format($pago['capital_pagado'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, '$ ' . number_format($pago['interes_pagado'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, '$ ' . number_format($pago['monto'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, $pdf->normalize($pago['estado']), 1, 0, 'C');
        $pdf->Cell(30, 6, $pago['fecha_pago'] ? date('d/m/Y', strtotime($pago['fecha_pago'])) : '-', 1, 1, 'C');
    }

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
        $pdf->Output('I', 'estado_cuenta_' . $credito['numero_factura'] . '.pdf', true);
    } catch (Exception $e) {
        throw new Exception('Error al generar el PDF: ' . $e->getMessage());
    }
    exit;

} catch (Exception $e) {
    // Limpiar buffer en caso de error
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log('Error generando estado de cuenta: ' . $e->getMessage());
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Error</title></head><body>";
    echo "<h1>Error al generar el estado de cuenta</h1>";
    echo "<p>Lo sentimos, ha ocurrido un error al generar el estado de cuenta. Por favor, inténtelo de nuevo más tarde.</p>";
    if (isset($_SESSION['user_id'])) {
        echo "<p>Error técnico: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</body></html>";
    exit;
} 