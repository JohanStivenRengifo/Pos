<?php
session_start();
define('DEBUG', false); // Set to true for debugging
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Antes de cualquier salida, limpiar el buffer
if (ob_get_level()) ob_end_clean();

try {
    // Obtener información de la empresa
    $stmt = $pdo->prepare("
        SELECT * FROM empresas 
        WHERE estado = 1 AND es_principal = 1 
        LIMIT 1
    ");
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    // Consulta para obtener productos con stock bajo o agotado
    $stmt = $pdo->prepare("
        SELECT 
            i.*,
            c.nombre as categoria_nombre,
            d.nombre as departamento_nombre,
            COALESCE(b.nombre, 'Sin asignar') as bodega,
            (i.stock * i.precio_costo) as valor_costo_total,
            (i.stock_minimo - i.stock) as cantidad_faltante,
            ((i.stock_minimo - i.stock) * i.precio_costo) as costo_reposicion,
            CASE 
                WHEN i.stock = 0 THEN 'Agotado'
                WHEN i.stock <= i.stock_minimo THEN 'Bajo'
                ELSE 'Normal'
            END as estado_stock
        FROM inventario i
        LEFT JOIN categorias c ON i.categoria_id = c.id
        LEFT JOIN departamentos d ON i.departamento_id = d.id
        LEFT JOIN inventario_bodegas ib ON i.id = ib.producto_id
        LEFT JOIN bodegas b ON ib.bodega_id = b.id
        WHERE i.user_id = :user_id 
        AND (i.stock <= i.stock_minimo OR i.stock = 0)
        ORDER BY i.stock ASC, i.nombre ASC
    ");
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular totales
    $total_productos = count($productos);
    $total_costo_reposicion = 0;
    $productos_agotados = 0;
    $productos_bajo_stock = 0;

    foreach ($productos as $producto) {
        $total_costo_reposicion += $producto['costo_reposicion'];
        if ($producto['stock'] == 0) {
            $productos_agotados++;
        } else {
            $productos_bajo_stock++;
        }
    }

    // Verificar y cargar FPDF
    if (!class_exists('FPDF')) {
        require_once '../../vendor/fpdf/fpdf.php';
    }

    // Crear clase personalizada de PDF
    class StockReportPDF extends FPDF {
        function Header() {
            global $empresa;
            
            // Logo
            if (!empty($empresa['logo']) && file_exists('../../' . $empresa['logo'])) {
                $this->Image('../../' . $empresa['logo'], 10, 10, 30);
            }
            
            // Título del reporte
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, mb_convert_encoding('REPORTE DE STOCK BAJO Y AGOTADO', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            
            // Información de la empresa
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, mb_convert_encoding($empresa['nombre_empresa'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding('NIT: ' . $empresa['nit'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, mb_convert_encoding($empresa['direccion'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
            $this->Cell(0, 6, 'Fecha de generación: ' . date('d/m/Y H:i'), 0, 1, 'C');
            
            $this->Ln(5);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, mb_convert_encoding('Página ' . $this->PageNo() . '/{nb}', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        }

        function ChapterTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(230, 230, 230);
            $this->Cell(0, 8, mb_convert_encoding($title, 'ISO-8859-1', 'UTF-8'), 0, 1, 'L', true);
            $this->Ln(4);
        }

        function TableHeader() {
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor(240, 240, 240);
            $this->Cell(25, 7, 'Código', 1, 0, 'C', true);
            $this->Cell(60, 7, 'Producto', 1, 0, 'L', true);
            $this->Cell(20, 7, 'Stock', 1, 0, 'C', true);
            $this->Cell(20, 7, mb_convert_encoding('Mínimo', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
            $this->Cell(25, 7, 'Faltante', 1, 0, 'C', true);
            $this->Cell(25, 7, 'Costo U.', 1, 0, 'R', true);
            $this->Cell(25, 7, mb_convert_encoding('Inversión', 'ISO-8859-1', 'UTF-8'), 1, 1, 'R', true);
        }
    }

    // Crear nuevo PDF
    $pdf = new StockReportPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Resumen ejecutivo
    $pdf->ChapterTitle('RESUMEN EJECUTIVO');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, mb_convert_encoding('Total de productos con stock crítico: ' . $total_productos, 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Productos agotados: ' . $productos_agotados, 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Productos bajo stock mínimo: ' . $productos_bajo_stock, 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 6, mb_convert_encoding('Inversión necesaria para reposición: $' . number_format($total_costo_reposicion, 2, ',', '.'), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Ln(5);

    // Productos Agotados
    if ($productos_agotados > 0) {
        $pdf->ChapterTitle('PRODUCTOS AGOTADOS');
        $pdf->TableHeader();
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($productos as $producto) {
            if ($producto['stock'] == 0) {
                $pdf->Cell(25, 6, $producto['codigo_barras'], 1, 0, 'C');
                $pdf->Cell(60, 6, mb_convert_encoding($producto['nombre'], 'ISO-8859-1', 'UTF-8'), 1);
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Cell(20, 6, $producto['stock'], 1, 0, 'C');
                $pdf->SetTextColor(0);
                $pdf->Cell(20, 6, $producto['stock_minimo'], 1, 0, 'C');
                $pdf->Cell(25, 6, $producto['cantidad_faltante'], 1, 0, 'C');
                $pdf->Cell(25, 6, '$' . number_format($producto['precio_costo'], 2, ',', '.'), 1, 0, 'R');
                $pdf->Cell(25, 6, '$' . number_format($producto['costo_reposicion'], 2, ',', '.'), 1, 1, 'R');
            }
        }
        $pdf->Ln(5);
    }

    // Productos Bajo Stock
    if ($productos_bajo_stock > 0) {
        $pdf->ChapterTitle('PRODUCTOS BAJO STOCK MÍNIMO');
        $pdf->TableHeader();
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($productos as $producto) {
            if ($producto['stock'] > 0 && $producto['stock'] <= $producto['stock_minimo']) {
                $pdf->Cell(25, 6, $producto['codigo_barras'], 1, 0, 'C');
                $pdf->Cell(60, 6, mb_convert_encoding($producto['nombre'], 'ISO-8859-1', 'UTF-8'), 1);
                $pdf->SetTextColor(255, 128, 0);
                $pdf->Cell(20, 6, $producto['stock'], 1, 0, 'C');
                $pdf->SetTextColor(0);
                $pdf->Cell(20, 6, $producto['stock_minimo'], 1, 0, 'C');
                $pdf->Cell(25, 6, $producto['cantidad_faltante'], 1, 0, 'C');
                $pdf->Cell(25, 6, '$' . number_format($producto['precio_costo'], 2, ',', '.'), 1, 0, 'R');
                $pdf->Cell(25, 6, '$' . number_format($producto['costo_reposicion'], 2, ',', '.'), 1, 1, 'R');
            }
        }
    }

    // Generar el PDF
    $pdf->Output('I', 'reporte_stock_bajo_' . date('Y-m-d') . '.pdf');
    exit;

} catch (Exception $e) {
    error_log('Error generando reporte de stock bajo: ' . $e->getMessage());
    header('Content-Type: text/html; charset=UTF-8');
    echo "<h1>Error al generar el reporte</h1>";
    echo "<p>Lo sentimos, ha ocurrido un error al generar el reporte. Por favor, inténtelo de nuevo más tarde.</p>";
    if (defined('DEBUG') && DEBUG) {
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}