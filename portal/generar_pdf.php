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

// Extender FPDF para agregar marca de agua
class PDF extends FPDF {
    function AddWatermark($text) {
        // Guardar estado
        $this->SetFont('Arial', 'B', 96);
        
        // Calcular dimensiones de la página
        $pageWidth = $this->GetPageWidth();
        $pageHeight = $this->GetPageHeight();
        
        // Calcular ancho del texto
        $textWidth = $this->GetStringWidth($text);
        
        // Calcular posición central
        $x = ($pageWidth - $textWidth) / 2;
        $y = $pageHeight / 2;
        
        // Color rojo claro semi-transparente
        $this->SetTextColor(255, 0, 0);
        
        // Guardar estado
        $this->_out('q');
        
        // Establecer opacidad
        $this->_out('0.3 g');
        
        // Rotar y posicionar
        $this->_out(sprintf('BT %.2F %.2F %.2F %.2F %.2F %.2F Tm (%s) Tj ET',
            cos(deg2rad(45)), sin(deg2rad(45)), -sin(deg2rad(45)), cos(deg2rad(45)),
            $x * $this->k, ($this->h - $y) * $this->k,
            $this->_escape($text)));
        
        // Restaurar estado
        $this->_out('Q');
        
        // Restaurar color
        $this->SetTextColor(0);
    }
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
        if (ob_get_length()) {
            ob_clean();
        }

        // Generar PDF con la clase extendida
        $pdf = new PDF();
        $pdf->SetTitle('Factura ' . $venta['numero_factura']);
        $pdf->AddPage();

        // Configuración inicial - Reducir márgenes para aprovechar más espacio
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(200, 200, 200);

        // Agregar marca de agua si está anulada
        if (isset($venta['anulada']) && $venta['anulada'] == 1) {
            $pdf->AddWatermark('ANULADA');
        }

        // Encabezado de la empresa - Aumentar tamaño
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell(0, 12, mb_convert_encoding($venta['empresa_nombre'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

        // Información de factura - Aumentar tamaño
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'FACTURA DE VENTA No. ' . $venta['numero_factura'], 0, 1, 'C');

        // Información de la empresa en dos columnas - Ajustar anchos
        $pdf->SetFont('Arial', '', 10);
        $leftColumn = 'NIT: ' . $venta['empresa_nit'] . "\n";
        $leftColumn .= 'Dir: ' . $venta['empresa_direccion'] . "\n";
        $leftColumn .= 'Tel: ' . $venta['empresa_telefono'] . "\n";
        $leftColumn .= 'Email: ' . $venta['empresa_email'];

        $rightColumn = 'Fecha: ' . date('d/m/Y', strtotime($venta['fecha'])) . "\n";
        $rightColumn .= 'Régimen: ' . $venta['regimen_fiscal'] . "\n";
        if (!empty($venta['numero_inicial']) && !empty($venta['numero_final'])) {
            $rightColumn .= 'Resolución DIAN No. ' . $venta['numero_inicial'] . ' al ' . $venta['numero_final'];
        }

        // Posicionar columnas - Ajustar posiciones y anchos
        $y = $pdf->GetY() + 5;
        $pdf->SetXY(10, $y);
        $pdf->MultiCell(120, 6, mb_convert_encoding($leftColumn, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        $pdf->SetXY(130, $y);
        $pdf->MultiCell(70, 6, mb_convert_encoding($rightColumn, 'ISO-8859-1', 'UTF-8'), 0, 'R');

        // Información del cliente - Aumentar espacio
        $pdf->Ln(5);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, 'INFORMACIÓN DEL CLIENTE', 1, 1, 'L', true);
        $pdf->SetFont('Arial', '', 10);

        $nombreCliente = trim($venta['primer_nombre'] . ' ' . $venta['segundo_nombre'] . ' ' . $venta['apellidos']);
        $pdf->Cell(120, 7, 'Nombre: ' . mb_convert_encoding($nombreCliente, 'ISO-8859-1', 'UTF-8'), 'LR', 0);
        $pdf->Cell(70, 7, 'ID: ' . $venta['identificacion'], 'LR', 1);
        $pdf->Cell(120, 7, 'Dir: ' . mb_convert_encoding($venta['direccion'], 'ISO-8859-1', 'UTF-8'), 'LR', 0);
        $pdf->Cell(70, 7, 'Tel: ' . $venta['telefono'], 'LR', 1);
        $pdf->Cell(190, 7, 'Email: ' . $venta['email'], 'LRB', 1);

        // Detalles de la venta - Ajustar anchos de columnas
        $pdf->Ln(5);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 10);
        
        // Encabezados de la tabla - Ajustar anchos
        $pdf->Cell(90, 8, ' PRODUCTO', 1, 0, 'L', true);
        $pdf->Cell(25, 8, ' CANT.', 1, 0, 'C', true);
        $pdf->Cell(35, 8, ' PRECIO', 1, 0, 'R', true);
        $pdf->Cell(40, 8, ' TOTAL', 1, 1, 'R', true);

        // Productos
        $pdf->SetFont('Arial', '', 10);
        foreach ($detalles as $detalle) {
            $pdf->Cell(90, 7, ' ' . mb_convert_encoding($detalle['nombre'], 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->Cell(25, 7, $detalle['cantidad'], 1, 0, 'C');
            $pdf->Cell(35, 7, '$' . number_format($detalle['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(40, 7, '$' . number_format($detalle['cantidad'] * $detalle['precio_unitario'], 0, ',', '.'), 1, 1, 'R');
        }

        // Totales - Ajustar anchos
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(150, 8, 'SUBTOTAL:', 1, 0, 'R', true);
        $pdf->Cell(40, 8, '$' . number_format($venta['total'] + $venta['descuento'], 0, ',', '.'), 1, 1, 'R');
        $pdf->Cell(150, 8, 'DESCUENTO:', 1, 0, 'R', true);
        $pdf->Cell(40, 8, '$' . number_format($venta['descuento'], 0, ',', '.'), 1, 1, 'R');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(150, 9, 'TOTAL A PAGAR:', 1, 0, 'R', true);
        $pdf->Cell(40, 9, '$' . number_format($venta['total'], 0, ',', '.'), 1, 1, 'R');

        // Pie de página - Ajustar posición
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, mb_convert_encoding(
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
?>