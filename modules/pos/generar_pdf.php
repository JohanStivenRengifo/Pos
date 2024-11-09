<?php
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

function generarPDFFactura($venta_id) {
    global $pdo;
    
    // Obtener datos de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, c.nombre as cliente_nombre, c.identificacion, c.email, c.telefono,
               c.municipio_departamento
        FROM ventas v 
        LEFT JOIN clientes c ON v.cliente_id = c.id 
        WHERE v.id = ?
    ");
    $stmt->execute([$venta_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener detalles de la venta
    $stmt = $pdo->prepare("
        SELECT vd.*, p.nombre as producto_nombre, p.codigo_barras
        FROM venta_detalles vd
        JOIN inventario p ON vd.producto_id = p.id
        WHERE vd.venta_id = ?
    ");
    $stmt->execute([$venta_id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear nuevo documento PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Establecer información del documento
    $pdf->SetCreator('VendEasy');
    $pdf->SetAuthor('VendEasy');
    $pdf->SetTitle('Factura #' . $venta['numero_factura']);

    // Eliminar cabeceras y pies de página predeterminados
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Establecer márgenes
    $pdf->SetMargins(15, 15, 15);

    // Agregar una página
    $pdf->AddPage();

    // Establecer fuente
    $pdf->SetFont('helvetica', '', 10);

    // Logo y datos de la empresa
    $pdf->Image('../../assets/img/logo.png', 15, 15, 50);
    $pdf->Cell(0, 10, 'FACTURA DE VENTA', 0, 1, 'R');
    $pdf->Cell(0, 10, 'No. ' . $venta['numero_factura'], 0, 1, 'R');
    $pdf->Cell(0, 10, 'Fecha: ' . date('d/m/Y', strtotime($venta['fecha'])), 0, 1, 'R');

    // Información del cliente
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, 'DATOS DEL CLIENTE', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Nombre: ' . $venta['cliente_nombre'], 0, 1, 'L');
    if ($venta['identificacion']) {
        $pdf->Cell(0, 10, 'Identificación: ' . $venta['identificacion'], 0, 1, 'L');
    }
    if ($venta['telefono']) {
        $pdf->Cell(0, 10, 'Teléfono: ' . $venta['telefono'], 0, 1, 'L');
    }
    if ($venta['municipio_departamento']) {
        $pdf->Cell(0, 10, 'Dirección: ' . $venta['municipio_departamento'], 0, 1, 'L');
    }

    // Tabla de productos
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(20, 7, 'CANT.', 1, 0, 'C');
    $pdf->Cell(90, 7, 'DESCRIPCIÓN', 1, 0, 'C');
    $pdf->Cell(40, 7, 'PRECIO UNIT.', 1, 0, 'C');
    $pdf->Cell(40, 7, 'SUBTOTAL', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 9);
    foreach ($detalles as $detalle) {
        $pdf->Cell(20, 7, $detalle['cantidad'], 1, 0, 'C');
        $pdf->Cell(90, 7, $detalle['producto_nombre'], 1, 0, 'L');
        $pdf->Cell(40, 7, '$ ' . number_format($detalle['precio_unitario'], 2, ',', '.'), 1, 0, 'R');
        $subtotal = $detalle['cantidad'] * $detalle['precio_unitario'];
        $pdf->Cell(40, 7, '$ ' . number_format($subtotal, 2, ',', '.'), 1, 1, 'R');
    }

    // Totales
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(150, 7, 'SUBTOTAL:', 1, 0, 'R');
    $pdf->Cell(40, 7, '$ ' . number_format($venta['subtotal'], 2, ',', '.'), 1, 1, 'R');
    
    if ($venta['descuento'] > 0) {
        $pdf->Cell(150, 7, 'DESCUENTO:', 1, 0, 'R');
        $pdf->Cell(40, 7, '$ ' . number_format($venta['descuento'], 2, ',', '.'), 1, 1, 'R');
    }
    
    $pdf->Cell(150, 7, 'TOTAL:', 1, 0, 'R');
    $pdf->Cell(40, 7, '$ ' . number_format($venta['total'], 2, ',', '.'), 1, 1, 'R');

    // Información adicional
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 7, 'Método de pago: ' . ucfirst($venta['metodo_pago']), 0, 1, 'L');
    $pdf->Cell(0, 7, 'Factura generada por VendEasy - Sistema de Facturación', 0, 1, 'L');

    // Guardar PDF en una ubicación temporal
    $temp_path = sys_get_temp_dir() . '/factura_' . $venta_id . '.pdf';
    $pdf->Output($temp_path, 'F');

    return $temp_path;
} 