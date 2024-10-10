<?php
require_once '../../config/db.php';
require_once '../../fpdf/fpdf.php';
require_once './helpers/NumeroALetras.php';

define('MONEDA', '$');
define('MONEDA_LETRA', 'pesos');
define('MONEDA_DECIMAL', 'centavos');

class TicketPDF extends FPDF {
    function __construct() {
        parent::__construct('P', 'mm', array(80, 0));
    }

    function Header() {
        $logoPath = '../../images/Logo.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 25, 2, 30);
            $this->Ln(12);
        }
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(70, 5, 'Ferreteria Obra Blanca', 0, 1, 'C');
        $this->SetFont('Arial', '', 8);
        $this->Cell(70, 4, 'NIT: 11205733-1', 0, 1, 'C');
        $this->Cell(70, 4, 'Dirección: Cra 3A-18', 0, 1, 'C');
        $this->Cell(70, 4, 'Tel: (+57) 3112384067', 0, 1, 'C');
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Gracias por su compra', 0, 0, 'C');
    }

    function LineaSeparadora() {
        $this->Cell(70, 2, str_repeat('-', 47), 0, 1, 'L');
    }
}

$idVenta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idVenta <= 0) {
    die('ID de venta inválido');
}

try {
    $stmt = $pdo->prepare("SELECT v.*, c.nombre AS cliente_nombre, c.tipo_identificacion, c.identificacion,
                           CONCAT(c.primer_nombre, ' ', IFNULL(c.segundo_nombre, ''), ' ', c.apellidos) AS cliente_nombre_completo
                           FROM ventas v 
                           JOIN clientes c ON v.cliente_id = c.id 
                           WHERE v.id = ?");
    $stmt->execute([$idVenta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        die('Venta no encontrada');
    }

    $stmt = $pdo->prepare("SELECT vd.*, i.nombre AS producto_nombre 
                           FROM venta_detalles vd 
                           JOIN inventario i ON vd.producto_id = i.id 
                           WHERE vd.venta_id = ?");
    $stmt->execute([$idVenta]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new TicketPDF();
    $pdf->AddPage();
    $pdf->SetMargins(5, 5, 5);

    // Información de la venta
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(35, 5, 'Factura No:', 0, 0, 'R');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(35, 5, $venta['numero_factura'], 0, 1, 'L');
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(35, 5, 'Fecha:', 0, 0, 'R');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(35, 5, date('d/m/Y H:i', strtotime($venta['fecha'])), 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(35, 5, 'Cliente:', 0, 0, 'R');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(35, 5, $venta['cliente_nombre_completo'], 0, 1, 'L');

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(35, 5, $venta['tipo_identificacion'] . ':', 0, 0, 'R');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(35, 5, $venta['identificacion'], 0, 1, 'L');

    $pdf->Ln(2);
    $pdf->LineaSeparadora();

    // Encabezados de la tabla
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(8, 4, 'Cant', 0, 0, 'L');
    $pdf->Cell(32, 4, 'Producto', 0, 0, 'L');
    $pdf->Cell(15, 4, 'Precio', 0, 0, 'R');
    $pdf->Cell(15, 4, 'Total', 0, 1, 'R');
    $pdf->LineaSeparadora();

    // Detalles de la venta
    $pdf->SetFont('Arial', '', 7);
    $totalProductos = 0;
    foreach ($detalles as $detalle) {
        $importe = $detalle['cantidad'] * $detalle['precio_unitario'];
        $totalProductos += $detalle['cantidad'];

        $pdf->Cell(8, 4, $detalle['cantidad'], 0, 0, 'L');
        $pdf->Cell(32, 4, mb_substr($detalle['producto_nombre'], 0, 20), 0, 0, 'L');
        $pdf->Cell(15, 4, number_format($detalle['precio_unitario'], 2, ',', '.'), 0, 0, 'R');
        $pdf->Cell(15, 4, number_format($importe, 2, ',', '.'), 0, 1, 'R');
    }

    $pdf->LineaSeparadora();

    // Totales
    $pdf->SetFont('Arial', '', 8);
    $subtotal = $venta['total'] + $venta['descuento'];
    $pdf->Cell(55, 4, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(15, 4, number_format($subtotal, 2, ',', '.'), 0, 1, 'R');

    $pdf->Cell(55, 4, 'Descuento:', 0, 0, 'R');
    $pdf->Cell(15, 4, number_format($venta['descuento'], 2, ',', '.'), 0, 1, 'R');

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(55, 5, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(15, 5, number_format($venta['total'], 2, ',', '.'), 0, 1, 'R');

    $pdf->Ln(2);

    // Información adicional
    $pdf->SetFont('Arial', '', 8);
    $pdf->MultiCell(70, 4, 'Son ' . strtolower(NumeroALetras::convertir($venta['total'], MONEDA_LETRA, MONEDA_DECIMAL)), 0, 'L', 0);
    $pdf->Cell(70, 4, 'Método de pago: ' . ucfirst($venta['metodo_pago']), 0, 1, 'L');
    $pdf->Cell(70, 4, 'Artículos vendidos: ' . $totalProductos, 0, 1, 'L');

    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 7);
    $pdf->MultiCell(70, 3, 'Esta factura contribuye al desarrollo del país. Es un derecho del comprador exigirla y una obligación del vendedor emitirla.', 0, 'C');

    $pdf->Output('I', 'Ticket_' . $venta['numero_factura'] . '.pdf');

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}