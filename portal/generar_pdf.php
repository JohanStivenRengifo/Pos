<?php
require_once('../config/db.php');

// Habilitar el reporte de errores pero guardarlos en el log
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Verificar y cargar FPDF
$fpdfPaths = [
    '../fpdf/fpdf.php',
    '../../fpdf/fpdf.php',
    '../../../fpdf/fpdf.php',
    '../../../vendor/fpdf/fpdf.php'
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
    die('Error: La librería FPDF no está instalada correctamente.');
}

function generarFacturaPDF($tipo, $id) {
    try {
        global $pdo;
        
        // Obtener datos completos de la venta
        $stmt = $pdo->prepare("
            SELECT v.*, 
                   c.primer_nombre, c.segundo_nombre, c.apellidos, 
                   c.identificacion, c.email, c.telefono, c.direccion,
                   e.nombre_empresa as empresa_nombre,
                   e.nit as empresa_nit,
                   e.direccion as empresa_direccion,
                   e.telefono as empresa_telefono,
                   e.correo_contacto as empresa_email,
                   e.prefijo_factura,
                   e.regimen_fiscal,
                   e.numero_inicial,
                   e.numero_final
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN empresas e ON e.estado = 1 AND e.es_principal = 1
            WHERE v.id = ?
        ");
        $stmt->execute([$id]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venta) {
            throw new Exception('Venta no encontrada');
        }

        // Obtener detalles de la venta
        $stmt = $pdo->prepare("
            SELECT vd.*, i.nombre, i.codigo_barras
            FROM venta_detalles vd
            LEFT JOIN inventario i ON vd.producto_id = i.id
            WHERE vd.venta_id = ?
        ");
        $stmt->execute([$id]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Limpiar cualquier salida previa
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Generar PDF
        $pdf = new FPDF();
        $pdf->AddPage();

        // Configuración inicial
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(200, 200, 200);

        // Encabezado de la empresa
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, mb_convert_encoding($venta['empresa_nombre'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

        // Información de factura
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'FACTURA DE VENTA No. ' . $venta['numero_factura'], 0, 1, 'C');

        // Información de la empresa en dos columnas
        $pdf->SetFont('Arial', '', 9);
        $leftColumn = 'NIT: ' . $venta['empresa_nit'] . "\n";
        $leftColumn .= 'Dir: ' . $venta['empresa_direccion'] . "\n";
        $leftColumn .= 'Tel: ' . $venta['empresa_telefono'] . "\n";
        $leftColumn .= 'Email: ' . $venta['empresa_email'];

        $rightColumn = 'Fecha: ' . date('d/m/Y', strtotime($venta['fecha'])) . "\n";
        $rightColumn .= 'Régimen: ' . $venta['regimen_fiscal'] . "\n";
        if (!empty($venta['numero_inicial']) && !empty($venta['numero_final'])) {
            $rightColumn .= 'Resolución DIAN No. ' . $venta['numero_inicial'] . ' al ' . $venta['numero_final'];
        }

        // Posicionar columnas
        $y = $pdf->GetY() + 5;
        $pdf->SetXY(15, $y);
        $pdf->MultiCell(95, 5, mb_convert_encoding($leftColumn, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        $pdf->SetXY(110, $y);
        $pdf->MultiCell(85, 5, mb_convert_encoding($rightColumn, 'ISO-8859-1', 'UTF-8'), 0, 'R');

        // Información del cliente
        $pdf->Ln(5);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 7, 'INFORMACIÓN DEL CLIENTE', 1, 1, 'L', true);
        $pdf->SetFont('Arial', '', 9);

        $nombreCliente = trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos']);
        $pdf->Cell(97, 6, 'Nombre: ' . mb_convert_encoding($nombreCliente, 'ISO-8859-1', 'UTF-8'), 'LR', 0);
        $pdf->Cell(83, 6, 'ID: ' . $venta['identificacion'], 'LR', 1);
        $pdf->Cell(97, 6, 'Dir: ' . mb_convert_encoding($venta['direccion'], 'ISO-8859-1', 'UTF-8'), 'LR', 0);
        $pdf->Cell(83, 6, 'Tel: ' . $venta['telefono'], 'LR', 1);
        $pdf->Cell(180, 6, 'Email: ' . $venta['email'], 'LRB', 1);

        // Detalles de la venta
        $pdf->Ln(5);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 9);
        
        // Encabezados de la tabla
        $pdf->Cell(80, 7, ' PRODUCTO', 1, 0, 'L', true);
        $pdf->Cell(25, 7, ' CANT.', 1, 0, 'C', true);
        $pdf->Cell(35, 7, ' PRECIO', 1, 0, 'R', true);
        $pdf->Cell(40, 7, ' TOTAL', 1, 1, 'R', true);

        // Productos
        $pdf->SetFont('Arial', '', 9);
        foreach ($detalles as $detalle) {
            $pdf->Cell(80, 6, ' ' . mb_convert_encoding($detalle['nombre'], 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->Cell(25, 6, $detalle['cantidad'], 1, 0, 'C');
            $pdf->Cell(35, 6, '$' . number_format($detalle['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(40, 6, '$' . number_format($detalle['cantidad'] * $detalle['precio_unitario'], 0, ',', '.'), 1, 1, 'R');
        }

        // Totales
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(140, 7, 'SUBTOTAL:', 1, 0, 'R', true);
        $pdf->Cell(40, 7, '$' . number_format($venta['total'] + $venta['descuento'], 0, ',', '.'), 1, 1, 'R');
        $pdf->Cell(140, 7, 'DESCUENTO:', 1, 0, 'R', true);
        $pdf->Cell(40, 7, '$' . number_format($venta['descuento'], 0, ',', '.'), 1, 1, 'R');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(140, 8, 'TOTAL A PAGAR:', 1, 0, 'R', true);
        $pdf->Cell(40, 8, '$' . number_format($venta['total'], 0, ',', '.'), 1, 1, 'R');

        // Pie de página
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(0, 4, mb_convert_encoding(
            "GRACIAS POR SU COMPRA\n" .
            "Esta factura se asimila en todos sus efectos a una letra de cambio según el artículo 774 del Código de Comercio",
            'ISO-8859-1',
            'UTF-8'
        ), 0, 'C');

        // Generar el PDF
        $pdf->Output('I', 'factura_' . $venta['numero_factura'] . '.pdf');
        exit;

    } catch (Exception $e) {
        error_log('Error generando PDF: ' . $e->getMessage());
        header('Location: detalles.php?tipo=' . urlencode($tipo) . '&id=' . $id . '&error=pdf');
        exit;
    }
}

// Validar parámetros
$tipo = filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_STRING);
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if ($tipo === 'Venta' && !empty($id)) {
    generarFacturaPDF($tipo, $id);
} else {
    header('Location: index.php');
    exit;
} 