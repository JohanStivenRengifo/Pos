<?php
// Primero, asegurarnos de que no haya salida previa
ob_start();

require_once '../../../config/db.php';
require_once 'alegra_integration.php';

// Habilitar el reporte de errores pero guardarlos en el log en lugar de mostrarlos
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Verificar y cargar FPDF
$fpdfPaths = [
    'fpdf/fpdf.php',                    // Ruta relativa actual
    '../fpdf/fpdf.php',                 // Un nivel arriba
    '../../fpdf/fpdf.php',             // Dos niveles arriba
    '../../../fpdf/fpdf.php',          // Tres niveles arriba
    '../../../vendor/fpdf/fpdf.php',   // En vendor
    dirname(__FILE__) . '/fpdf/fpdf.php' // Ruta absoluta
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
    die('Error: La librería FPDF no está instalada. Por favor, instale FPDF en una de las siguientes ubicaciones: ' . implode(', ', $fpdfPaths));
}

if (!isset($_GET['id'])) {
    die('ID de venta no especificado');
}

// Antes de cualquier salida, agregar esto al inicio del archivo, justo después de los requires
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/pdf');

try {
    // Obtener datos de la venta
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
               e.numero_final,
               e.estado as empresa_estado
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN empresas e ON e.estado = 1 AND e.es_principal = 1
        WHERE v.id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Error preparando la consulta: ' . $pdo->errorInfo()[2]);
    }
    
    $stmt->execute([$_GET['id']]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception('Venta no encontrada');
    }

    // Verificar si tenemos los datos de la empresa
    if (empty($venta['empresa_nombre'])) {
        // Intentar obtener la empresa principal
        $stmtEmpresa = $pdo->prepare("
            SELECT 
                nombre_empresa,
                nit,
                direccion,
                telefono,
                correo_contacto as email,
                prefijo_factura,
                regimen_fiscal,
                numero_inicial,
                numero_final
            FROM empresas 
            WHERE estado = 1 AND es_principal = 1 
            LIMIT 1
        ");
        $stmtEmpresa->execute();
        $empresa = $stmtEmpresa->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            $venta['empresa_nombre'] = $empresa['nombre_empresa'];
            $venta['empresa_nit'] = $empresa['nit'];
            $venta['empresa_direccion'] = $empresa['direccion'];
            $venta['empresa_telefono'] = $empresa['telefono'];
            $venta['empresa_email'] = $empresa['email'];
            $venta['prefijo_factura'] = $empresa['prefijo_factura'];
            $venta['regimen_fiscal'] = $empresa['regimen_fiscal'];
            $venta['numero_inicial'] = $empresa['numero_inicial'];
            $venta['numero_final'] = $empresa['numero_final'];
        }
    }

    // Construir la resolución DIAN
    $venta['resolucion_facturacion'] = !empty($venta['numero_inicial']) && !empty($venta['numero_final']) 
        ? 'Resolución DIAN No. ' . $venta['numero_inicial'] . ' al ' . $venta['numero_final']
        : '';

    // Si es factura electrónica y tiene ID de Alegra
    if ($venta['numeracion'] === 'electronica' && !empty($venta['alegra_id'])) {
        try {
            $alegra = new AlegraIntegration();
            $alegraResponse = $alegra->getInvoiceDetails($venta['alegra_id']);

            if ($alegraResponse['success']) {
                // Actualizar datos de la factura electrónica si es necesario
                if (empty($venta['cufe']) || empty($venta['qr_code'])) {
                    $updateStmt = $pdo->prepare("
                        UPDATE ventas 
                        SET cufe = ?, qr_code = ?, pdf_url = ?, xml_url = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([
                        $alegraResponse['data']['cufe'],
                        $alegraResponse['data']['qr_code'],
                        $alegraResponse['data']['pdf_url'],
                        $alegraResponse['data']['xml_url'],
                        $venta['id']
                    ]);
                }

                // Redirigir al PDF de Alegra
                if (!empty($alegraResponse['data']['pdf_url'])) {
                    header('Location: ' . $alegraResponse['data']['pdf_url']);
                    exit;
                }
            }
            
            throw new Exception('No se pudo obtener el PDF de Alegra: ' . 
                              ($alegraResponse['error'] ?? 'Error desconocido'));
            
        } catch (Exception $e) {
            error_log('Error en Alegra Integration: ' . $e->getMessage());
            throw new Exception('Error procesando factura electrónica: ' . $e->getMessage());
        }
    }

    // Para facturas normales, obtener detalles
    $stmt = $pdo->prepare("
        SELECT vd.*, i.nombre, i.codigo_barras
        FROM venta_detalles vd
        LEFT JOIN inventario i ON vd.producto_id = i.id
        WHERE vd.venta_id = ?
    ");
    $stmt->execute([$venta['id']]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Antes de generar el PDF, limpiar cualquier salida previa
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Establecer headers para PDF
    header('Content-Type: application/pdf');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Generar PDF normal
    try {
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // Configuración de márgenes y colores
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(200, 200, 200);
        
        // Logo y Encabezado de la empresa
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 15, mb_convert_encoding($venta['empresa_nombre'] ?? '', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        
        // Información de la empresa en dos columnas
        $pdf->SetFont('Arial', '', 9);
        $leftColumn = 'NIT: ' . ($venta['empresa_nit'] ?? '') . "\n";
        $leftColumn .= 'Dir: ' . ($venta['empresa_direccion'] ?? '') . "\n";
        $leftColumn .= 'Tel: ' . ($venta['empresa_telefono'] ?? '');
        
        $rightColumn = ($venta['empresa_email'] ?? '') . "\n";
        if (!empty($venta['resolucion_facturacion'])) {
            $rightColumn .= $venta['resolucion_facturacion'] . "\n";
        }
        $rightColumn .= 'Régimen: ' . ($venta['regimen_fiscal'] ?? '');
        
        // Posición inicial
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y);
        
        // Columna izquierda
        $pdf->MultiCell(95, 5, mb_convert_encoding($leftColumn, 'ISO-8859-1', 'UTF-8'), 0, 'L');
        
        // Columna derecha
        $pdf->SetXY(110, $y);
        $pdf->MultiCell(85, 5, mb_convert_encoding($rightColumn, 'ISO-8859-1', 'UTF-8'), 0, 'R');
        
        // Línea separadora
        $pdf->Ln(5);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);
        
        // Información de la factura
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'FACTURA DE VENTA: ' . ($venta['prefijo_factura'] ?? '') . $venta['numero_factura'], 0, 1, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Fecha: ' . date('d/m/Y', strtotime($venta['fecha'])), 0, 1, 'R');
        
        // Información del cliente en un cuadro
        $pdf->Ln(5);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 7, ' INFORMACIÓN DEL CLIENTE', 1, 1, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        
        $nombreCliente = trim(
            ($venta['primer_nombre'] ?? '') . ' ' . 
            ($venta['segundo_nombre'] ?? '') . ' ' . 
            ($venta['apellidos'] ?? '')
        );
        $pdf->Cell(97, 6, ' Nombre: ' . mb_convert_encoding($nombreCliente, 'ISO-8859-1', 'UTF-8'), 'LR', 0);
        $pdf->Cell(83, 6, ' ID: ' . ($venta['identificacion'] ?? ''), 'LR', 1);
        $pdf->Cell(97, 6, ' Dir: ' . mb_convert_encoding($venta['direccion'] ?? '', 'ISO-8859-1', 'UTF-8'), 'LR', 0);
        $pdf->Cell(83, 6, ' Tel: ' . ($venta['telefono'] ?? ''), 'LR', 1);
        $pdf->Cell(180, 6, ' Email: ' . ($venta['email'] ?? ''), 'LRB', 1);
        
        // Detalles de la venta
        $pdf->Ln(5);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 9);
        // Encabezados de la tabla
        $pdf->Cell(80, 7, ' PRODUCTO', 1, 0, 'L', true);
        $pdf->Cell(25, 7, ' CANT.', 1, 0, 'C', true);
        $pdf->Cell(35, 7, ' PRECIO', 1, 0, 'R', true);
        $pdf->Cell(40, 7, ' TOTAL', 1, 1, 'R', true);
        
        // Contenido de la tabla
        $pdf->SetFont('Arial', '', 9);
        foreach ($detalles as $detalle) {
            $pdf->Cell(80, 6, ' ' . mb_convert_encoding($detalle['nombre'], 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->Cell(25, 6, $detalle['cantidad'], 1, 0, 'C');
            $pdf->Cell(35, 6, '$' . number_format($detalle['precio_unitario'], 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(40, 6, '$' . number_format($detalle['cantidad'] * $detalle['precio_unitario'], 0, ',', '.'), 1, 1, 'R');
        }
        
        // Totales con estilo
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
        $pdf->MultiCell(0, 4, mb_convert_encoding("GRACIAS POR SU COMPRA\nEsta factura es un título valor según el artículo 772 del Código de Comercio", 'ISO-8859-1', 'UTF-8'), 0, 'C');
        
        // Al final, antes de Output
        while (ob_get_level()) {
            ob_end_clean();
        }
        $pdf->Output('I', 'factura.pdf');
        exit;
    } catch (Exception $e) {
        error_log('Error generando PDF: ' . $e->getMessage());
        throw new Exception('Error generando el PDF: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log('Error en imprimir_factura.php: ' . $e->getMessage());
    // Limpiar cualquier salida parcial
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: " . $e->getMessage();
} 