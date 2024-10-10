<?php

require '../../config/db.php';
require '../../fpdf/fpdf.php';
require 'helpers/NumeroALetras.php';

define('MONEDA', '$');
define('MONEDA_LETRA', 'pesos');
define('MONEDA_DECIMAL', 'centavos');

$idVenta = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idVenta <= 0) {
    die('ID de venta inválido');
}

$sqlVenta = "SELECT v.id, v.numero_factura, v.total, v.descuento, v.metodo_pago, 
             DATE_FORMAT(v.fecha, '%d/%m/%Y') AS fecha_venta, 
             DATE_FORMAT(v.fecha, '%H:%i') AS hora_venta,
             c.primer_nombre, c.segundo_nombre, c.apellidos, c.tipo_identificacion, c.identificacion
             FROM ventas v
             JOIN clientes c ON v.cliente_id = c.id
             WHERE v.id = ?";

$stmt = $pdo->prepare($sqlVenta);
$stmt->execute([$idVenta]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    die('Venta no encontrada');
}

$sqlDetalle = "SELECT vd.cantidad, vd.precio_unitario, i.nombre AS producto_nombre
               FROM venta_detalles vd
               JOIN inventario i ON vd.producto_id = i.id
               WHERE vd.venta_id = ?";

$stmtDetalle = $pdo->prepare($sqlDetalle);
$stmtDetalle->execute([$idVenta]);
$detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

$pdf = new FPDF('P', 'mm', array(80, 0));
$pdf->AddPage();
$pdf->SetMargins(5, 5, 5);
$pdf->SetFont('Arial', 'B', 9);

$pdf->Image('../../images/Logo.png', 25, 2, 30);

$pdf->Ln(12);

$pdf->MultiCell(70, 5, 'Ferreteria Obra Blanca', 0, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(70, 4, 'NIT: 11205733-1', 0, 1, 'C');
$pdf->Cell(70, 4, 'Dirección: Cra 3A-18', 0, 1, 'C');
$pdf->Cell(70, 4, 'Tel: (+57) 3112384067', 0, 1, 'C');

$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 5, 'Factura No:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(45, 5, $venta['numero_factura'], 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 5, 'Fecha:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(45, 5, $venta['fecha_venta'] . ' ' . $venta['hora_venta'], 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 5, 'Cliente:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(45, 5, $venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos'], 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 5, $venta['tipo_identificacion'] . ':', 0, 0, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(45, 5, $venta['identificacion'], 0, 1, 'L');

$pdf->Cell(70, 2, str_repeat('-', 47), 0, 1, 'L');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(8, 4, 'Cant', 0, 0, 'L');
$pdf->Cell(32, 4, 'Descripción', 0, 0, 'L');
$pdf->Cell(15, 4, 'Precio', 0, 0, 'R');
$pdf->Cell(15, 4, 'Total', 0, 1, 'R');

$pdf->Cell(70, 2, str_repeat('-', 47), 0, 1, 'L');

$totalProductos = 0;
$pdf->SetFont('Arial', '', 7);

foreach ($detalles as $detalle) {
    $importe = $detalle['cantidad'] * $detalle['precio_unitario'];
    $totalProductos += $detalle['cantidad'];

    $pdf->Cell(8, 4, $detalle['cantidad'], 0, 0, 'L');
    
    $yInicio = $pdf->GetY();
    $pdf->MultiCell(32, 4, mb_substr($detalle['producto_nombre'], 0, 20), 0, 'L');
    $yFin = $pdf->GetY();
    
    $pdf->SetXY(45, $yInicio);
    $pdf->Cell(15, 4, number_format($detalle['precio_unitario'], 2, ',', '.'), 0, 0, 'R');
    
    $pdf->SetXY(60, $yInicio);
    $pdf->Cell(15, 4, number_format($importe, 2, ',', '.'), 0, 1, 'R');
    $pdf->SetY($yFin);
}

$pdf->Cell(70, 2, str_repeat('-', 47), 0, 1, 'L');

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(55, 4, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(15, 4, number_format($venta['total'] + $venta['descuento'], 2, ',', '.'), 0, 1, 'R');

$pdf->Cell(55, 4, 'Descuento:', 0, 0, 'R');
$pdf->Cell(15, 4, number_format($venta['descuento'], 2, ',', '.'), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(55, 5, 'TOTAL:', 0, 0, 'R');
$pdf->Cell(15, 5, number_format($venta['total'], 2, ',', '.'), 0, 1, 'R');

$pdf->Ln(2);

$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(70, 4, 'Son ' . strtolower(NumeroALetras::convertir($venta['total'], MONEDA_LETRA, MONEDA_DECIMAL)), 0, 'L', 0);
$pdf->Cell(70, 4, 'Método de pago: ' . ucfirst($venta['metodo_pago']), 0, 1, 'L');
$pdf->Cell(70, 4, 'Artículos vendidos: ' . $totalProductos, 0, 1, 'L');

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 7);
$pdf->MultiCell(70, 3, 'Esta factura contribuye al desarrollo del país. Es un derecho del comprador exigirla y una obligación del vendedor emitirla.', 0, 'C');

$pdf->Ln(2);
$pdf->MultiCell(70, 5, 'AGRADECEMOS SU PREFERENCIA VUELVA PRONTO!!!', 0, 'C');

$pdf->Output('I', 'Ticket_' . $venta['numero_factura'] . '.pdf');