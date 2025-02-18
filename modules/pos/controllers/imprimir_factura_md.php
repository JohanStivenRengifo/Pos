<?php
// Deshabilitar cualquier salida previa
error_reporting(0);
ini_set('display_errors', 0);

// Limpiar cualquier salida en el buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Iniciar un nuevo buffer limpio
ob_start();

require_once '../../../config/db.php';
require_once '../../../vendor/autoload.php';

// Verificar y cargar FPDF al inicio
if (!class_exists('FPDF')) {
    require_once '../../../vendor/setasign/fpdf/fpdf.php';
}

// Clase para convertir números a letras
class NumeroALetras {
    private static $UNIDADES = [
        '',
        'UN ',
        'DOS ',
        'TRES ',
        'CUATRO ',
        'CINCO ',
        'SEIS ',
        'SIETE ',
        'OCHO ',
        'NUEVE ',
        'DIEZ ',
        'ONCE ',
        'DOCE ',
        'TRECE ',
        'CATORCE ',
        'QUINCE ',
        'DIECISEIS ',
        'DIECISIETE ',
        'DIECIOCHO ',
        'DIECINUEVE ',
        'VEINTE '
    ];

    private static $DECENAS = [
        'VEINTI',
        'TREINTA ',
        'CUARENTA ',
        'CINCUENTA ',
        'SESENTA ',
        'SETENTA ',
        'OCHENTA ',
        'NOVENTA ',
        'CIEN '
    ];

    private static $CENTENAS = [
        'CIENTO ',
        'DOSCIENTOS ',
        'TRESCIENTOS ',
        'CUATROCIENTOS ',
        'QUINIENTOS ',
        'SEISCIENTOS ',
        'SETECIENTOS ',
        'OCHOCIENTOS ',
        'NOVECIENTOS '
    ];

    public static function convertir($number, $moneda = '') {
        $converted = '';
        $decimales = '';

        if (($number < 0) || ($number > 999999999)) {
            return 'No es posible convertir el número a letras';
        }

        $div_decimales = explode('.',$number);

        if(count($div_decimales) > 1){
            $number = $div_decimales[0];
            $decNumberStr = (string) $div_decimales[1];
            if(strlen($decNumberStr) == 2){
                $decimales = $decNumberStr;
            } else if(strlen($decNumberStr) == 1){
                $decimales = $decNumberStr . '0';
            }
        }

        $numberStr = (string) $number;
        $numberStrFill = str_pad($numberStr, 9, '0', STR_PAD_LEFT);
        $millones = substr($numberStrFill, 0, 3);
        $miles = substr($numberStrFill, 3, 3);
        $cientos = substr($numberStrFill, 6);

        if (intval($millones) > 0) {
            if ($millones == '001') {
                $converted .= 'UN MILLON ';
            } else if (intval($millones) > 0) {
                $converted .= sprintf('%sMILLONES ', self::convertGroup($millones));
            }
        }

        if (intval($miles) > 0) {
            if ($miles == '001') {
                $converted .= 'MIL ';
            } else if (intval($miles) > 0) {
                $converted .= sprintf('%sMIL ', self::convertGroup($miles));
            }
        }

        if (intval($cientos) > 0) {
            if ($cientos == '001') {
                $converted .= 'UN ';
            } else if (intval($cientos) > 0) {
                $converted .= sprintf('%s ', self::convertGroup($cientos));
            }
        }

        if(empty($decimales)){
            $valor_convertido = $converted . strtoupper($moneda);
        } else {
            $valor_convertido = $converted . strtoupper($moneda) . ' CON ' . $decimales . '/100';
        }

        return trim($valor_convertido);
    }

    private static function convertGroup($n) {
        $output = '';

        if ($n == '100') {
            $output = "CIEN ";
        } else if ($n[0] !== '0') {
            $output = self::$CENTENAS[$n[0] - 1];
        }

        $k = intval(substr($n,1));

        if ($k <= 20) {
            $output .= self::$UNIDADES[$k];
        } else {
            if(($k > 30) && ($n[2] !== '0')) {
                $output .= sprintf('%sY %s', self::$DECENAS[intval($n[1]) - 2], self::$UNIDADES[intval($n[2])]);
            } else {
                $output .= sprintf('%s%s', self::$DECENAS[intval($n[1]) - 2], self::$UNIDADES[intval($n[2])]);
            }
        }

        return $output;
    }
}

try {
    session_start();
    if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
        throw new Exception('No se ha especificado una venta válida.');
    }

    // Obtener datos de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, 
               c.primer_nombre, c.segundo_nombre, c.apellidos, 
               c.identificacion, c.direccion, c.telefono, c.email,
               e.nombre_empresa, e.nit, e.direccion as empresa_direccion,
               e.telefono as empresa_telefono, e.logo, e.correo_contacto as empresa_email,
               e.regimen_fiscal, e.prefijo_factura, e.numero_inicial, e.numero_final
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON e.estado = 1 AND e.es_principal = 1
        WHERE v.id = ?
    ");
    
    if (!$stmt->execute([$_GET['id']])) {
        throw new Exception('Error al obtener los datos de la venta');
    }
    
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$venta) {
        throw new Exception('No se encontró la venta especificada');
    }

    // Obtener detalles de la venta
    $stmt = $pdo->prepare("
        SELECT 
            i.nombre, 
            i.codigo_barras,
            i.impuesto,
            i.precio_venta,
            SUM(vd.cantidad) as cantidad,
            vd.precio_unitario,
            SUM(vd.cantidad * vd.precio_unitario) as total_item
        FROM venta_detalles vd
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
        GROUP BY i.id, i.nombre, i.codigo_barras, i.impuesto, i.precio_venta, vd.precio_unitario
    ");
    
    if (!$stmt->execute([$_GET['id']])) {
        throw new Exception('Error al obtener los detalles de la venta');
    }
    
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear clase personalizada de PDF
    class FacturaPDF extends FPDF {
        protected $B = 0;
        protected $I = 0;
        protected $U = 0;
        protected $HREF = '';

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
            global $venta;
            
            // Crear un rectángulo de fondo para el encabezado
            $this->SetFillColor(250, 250, 250);
            $this->Rect(0, 0, 210, 45, 'F');
            
            // Logo y encabezado principal
            if (!empty($venta['logo']) && file_exists('../../../' . $venta['logo'])) {
                $this->Image('../../../' . $venta['logo'], 15, 10, 40);
            }
            
            // Información de la empresa (centrada)
            $this->SetY(10);
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 8, $this->normalize('Numercia'), 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, $this->normalize('NIT: 1048067754'), 0, 1, 'C');
            $this->Cell(0, 5, $this->normalize('Popayan'), 0, 1, 'C');
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, $this->normalize('Tel: 3116035791'), 0, 1, 'C');
            $this->Cell(0, 5, $this->normalize('johanstivenrengifo@outlook.com'), 0, 1, 'C');

            // Número de factura y tipo (lado derecho)
            $this->SetXY(130, 10);
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(50, 50, 50);
            $this->Cell(70, 8, $this->normalize('FACTURA DE VENTA'), 0, 1, 'R');
            
            $this->SetXY(130, 18);
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(70, 8, $this->normalize('No. ' . $venta['numero_factura']), 0, 1, 'R');
            
            // Fechas
            $this->SetXY(130, 26);
            $this->SetFont('Arial', '', 9);
            $this->Cell(70, 5, $this->normalize('Fecha de emisión: ' . date('d/m/Y', strtotime($venta['fecha']))), 0, 1, 'R');
            
            $this->Ln(15);
        }

        function normalize($string) {
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $string);
        }

        function InfoCliente() {
            global $venta;
            
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
            
            $nombre_cliente = trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos']);
            
            // Diseño en dos columnas
            $col_width = 90;
            $this->SetFont('Arial', 'B', 9);
            
            // Primera columna
            $this->SetXY(15, $y);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Cliente:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($nombre_cliente), 0, 0, 'L');
            
            // Segunda columna
            $this->SetX(110);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('NIT/CC:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($venta['identificacion']), 0, 1, 'L');
            
            // Segunda fila
            $this->SetXY(15, $y + 8);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Dirección:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($venta['direccion']), 0, 0, 'L');
            
            $this->SetX(110);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Teléfono:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($col_width - 25, 6, $this->normalize($venta['telefono']), 0, 1, 'L');
            
            // Tercera fila
            $this->SetXY(15, $y + 16);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(25, 6, $this->normalize('Email:'), 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell(155, 6, $this->normalize($venta['email']), 0, 1, 'L');
            
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
            $this->Cell(75, 8, $this->normalize('Descripción'), 'TB', 0, 'L', true);
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
            global $venta;
            $this->SetY(-60);
            
            // Notas y términos en un cuadro
            $this->SetFillColor(250, 250, 250);
            $this->RoundedRect(10, $this->GetY(), 190, 25, 2, 'F');
            $this->SetFont('Arial', '', 8);
            $this->SetXY(15, $this->GetY() + 2);
            $this->MultiCell(180, 4, $this->normalize(
                "Con esta factura de venta el comprador declara haber recibido de forma real y materialmente las mercancías y/o servicios descritos en este titulo valor.\n\n" .
                "Esta factura se asimila en todos sus efectos a una letra de cambio de conformidad con el Art. 774 del código de comercio."), 0, 'L');
            
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
    $pdf = new FacturaPDF();
    $pdf->AddPage();
    
    // Generar contenido
    $pdf->InfoCliente();

    // Detalle de productos
    $pdf->TableHeader();
    $pdf->SetFont('Arial', '', 9);
    
    $subtotal = 0;
    $total_iva = 0;
    foreach ($detalles as &$detalle) {
        $precio_sin_iva = round($detalle['precio_unitario'] / (1 + ($detalle['impuesto'] / 100)));
        $subtotal_item = $precio_sin_iva * $detalle['cantidad'];
        $subtotal += $subtotal_item;
        
        $iva_producto = round($subtotal_item * ($detalle['impuesto'] / 100));
        $total_iva += $iva_producto;
        
        $pdf->Cell(25, 6, $detalle['codigo_barras'], 1, 0, 'C');
        $pdf->Cell(75, 6, $pdf->normalize($detalle['nombre']), 1, 0, 'L');
        $pdf->Cell(20, 6, number_format($detalle['cantidad'], 0), 1, 0, 'C');
        $pdf->Cell(30, 6, '$ ' . number_format($detalle['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, '$ ' . number_format($detalle['total_item'], 0, ',', '.'), 1, 1, 'R');
    }

    // Totales
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 10);
    
    // Crear una tabla para los totales con mejor diseño
    $width_label = 40;
    $width_value = 40;
    $x_position = $pdf->GetPageWidth() - $width_label - $width_value - 15;

    // Subtotal
    $pdf->SetX($x_position);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($width_label, 7, $pdf->normalize('Subtotal:'), 'T', 0, 'R');
    $pdf->Cell($width_value, 7, '$ ' . number_format($subtotal, 0, ',', '.'), 'T', 1, 'R');

    // IVA
    $pdf->SetX($x_position);
    $pdf->Cell($width_label, 7, $pdf->normalize('IVA (19%):'), 0, 0, 'R');
    $pdf->Cell($width_value, 7, '$ ' . number_format($total_iva, 0, ',', '.'), 0, 1, 'R');

    // Calcular el total antes del descuento
    $total_antes_descuento = $subtotal + $total_iva;

    // Descuento si existe
    if ($venta['descuento'] > 0) {
        $pdf->SetX($x_position);
        $descuento_valor = round($total_antes_descuento * ($venta['descuento'] / 100));
        $pdf->Cell($width_label, 7, $pdf->normalize('Descuento (' . $venta['descuento'] . '%):'), 0, 0, 'R');
        $pdf->Cell($width_value, 7, '-$ ' . number_format($descuento_valor, 0, ',', '.'), 0, 1, 'R');
        $total_final = $total_antes_descuento - $descuento_valor;
    } else {
        $total_final = $total_antes_descuento;
    }

    // Total
    $pdf->SetX($x_position);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell($width_label, 9, $pdf->normalize('TOTAL:'), 'T', 0, 'R');
    $pdf->Cell($width_value, 9, '$ ' . number_format($total_final, 0, ',', '.'), 'T', 1, 'R');

    // Valor en letras
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, $pdf->normalize('Son: ' . NumeroALetras::convertir($total_final, 'PESOS')), 0, 1, 'L');

    try {
        // Enviar el PDF
        $pdf->Output('I', 'factura_' . $venta['numero_factura'] . '.pdf');
    } catch (Exception $e) {
        throw new Exception('Error al generar el PDF: ' . $e->getMessage());
    }
    exit;

} catch (Exception $e) {
    // Limpiar buffer en caso de error
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Error</title></head><body>";
    echo "<h1>Error al generar la factura</h1>";
    echo "<p>Lo sentimos, ha ocurrido un error al generar la factura. Por favor, inténtelo de nuevo más tarde.</p>";
    if (isset($_SESSION['user_id'])) {
        echo "<p>Error técnico: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</body></html>";
    exit;
}