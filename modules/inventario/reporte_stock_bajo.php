<?php
session_start();
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
            
            // Configurar márgenes
            $this->SetMargins(15, 15, 15);
            
            // Encabezado con tabla
            $this->SetFillColor(240, 240, 240);
            $this->SetFont('Arial', 'B', 14);
            
            // Crear tabla de encabezado
            $this->Cell(0, 10, '', 'LTR', 1, 'C', true);
            
            // Logo y título en la misma línea
            if (!empty($empresa['logo']) && file_exists('../../' . $empresa['logo'])) {
                $this->Image('../../' . $empresa['logo'], $this->GetX() + 2, $this->GetY() - 8, 20);
                $this->SetX($this->GetX() + 25);
            }
            
            $this->Cell(0, 8, mb_convert_encoding('REPORTE DE STOCK BAJO Y AGOTADO', 'ISO-8859-1', 'UTF-8'), 'LR', 1, 'C', true);
            
            // Información de la empresa
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 6, mb_convert_encoding($empresa['nombre_empresa'], 'ISO-8859-1', 'UTF-8'), 'LR', 1, 'C', true);
            $this->Cell(0, 6, mb_convert_encoding('NIT: ' . $empresa['nit'], 'ISO-8859-1', 'UTF-8'), 'LR', 1, 'C', true);
            $this->Cell(0, 6, mb_convert_encoding($empresa['direccion'], 'ISO-8859-1', 'UTF-8'), 'LR', 1, 'C', true);
            $this->Cell(0, 6, 'Fecha: ' . date('d/m/Y H:i'), 'LRB', 1, 'C', true);
            
            $this->Ln(5);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, mb_convert_encoding('Página ' . $this->PageNo() . '/{nb}', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        }

        function ChapterTitle($title) {
            $this->SetFont('Arial', 'B', 11);
            $this->SetFillColor(51, 122, 183); // Azul corporativo
            $this->SetTextColor(255);
            $this->Cell(0, 7, mb_convert_encoding($title, 'ISO-8859-1', 'UTF-8'), 1, 1, 'L', true);
            $this->SetTextColor(0);
            $this->Ln(2);
        }

        function TableHeader() {
            $this->SetFont('Arial', 'B', 8);
            $this->SetFillColor(245, 245, 245);
            
            // Ajustar anchos de columnas para que quepan en la página
            $this->Cell(22, 6, 'Código', 1, 0, 'C', true);
            $this->Cell(55, 6, 'Producto', 1, 0, 'L', true);
            $this->Cell(15, 6, 'Stock', 1, 0, 'C', true);
            $this->Cell(15, 6, 'Mín', 1, 0, 'C', true);
            $this->Cell(20, 6, 'Faltante', 1, 0, 'C', true);
            $this->Cell(25, 6, 'Costo U.', 1, 0, 'R', true);
            $this->Cell(28, 6, 'Inversión', 1, 1, 'R', true);
        }

        function ResumenEjecutivo($data) {
            $this->SetFillColor(245, 245, 245);
            $this->SetFont('Arial', 'B', 9);
            
            // Crear tabla de resumen
            $this->Cell(45, 6, 'Stock Crítico:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 9);
            $this->Cell(35, 6, $data['total'], 1, 0, 'C');
            
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(45, 6, 'Agotados:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 9);
            $this->Cell(35, 6, $data['agotados'], 1, 1, 'C');
            
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(45, 6, 'Bajo Mínimo:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 9);
            $this->Cell(35, 6, $data['bajo_stock'], 1, 0, 'C');
            
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(45, 6, 'Inversión Total:', 1, 0, 'L', true);
            $this->SetFont('Arial', '', 9);
            $this->Cell(35, 6, '$' . number_format($data['inversion'], 2, ',', '.'), 1, 1, 'C');
            
            $this->Ln(5);
        }
    }

    // Crear nuevo PDF
    $pdf = new StockReportPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Resumen ejecutivo en formato de tabla
    $resumen_data = [
        'total' => $total_productos,
        'agotados' => $productos_agotados,
        'bajo_stock' => $productos_bajo_stock,
        'inversion' => $total_costo_reposicion
    ];

    $pdf->ChapterTitle('RESUMEN EJECUTIVO');
    $pdf->ResumenEjecutivo($resumen_data);

    // Productos Agotados
    if ($productos_agotados > 0) {
        $pdf->ChapterTitle('PRODUCTOS AGOTADOS');
        $pdf->TableHeader();
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($productos as $producto) {
            if ($producto['stock'] == 0) {
                $pdf->Cell(22, 6, $producto['codigo_barras'], 1, 0, 'C');
                $pdf->Cell(55, 6, mb_convert_encoding($producto['nombre'], 'ISO-8859-1', 'UTF-8'), 1);
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Cell(15, 6, $producto['stock'], 1, 0, 'C');
                $pdf->SetTextColor(0);
                $pdf->Cell(15, 6, $producto['stock_minimo'], 1, 0, 'C');
                $pdf->Cell(20, 6, $producto['cantidad_faltante'], 1, 0, 'C');
                $pdf->Cell(25, 6, '$' . number_format($producto['precio_costo'], 2, ',', '.'), 1, 0, 'R');
                $pdf->Cell(28, 6, '$' . number_format($producto['costo_reposicion'], 2, ',', '.'), 1, 1, 'R');
            }
        }
        $pdf->Ln(3);
    }

    // Productos Bajo Stock
    if ($productos_bajo_stock > 0) {
        $pdf->ChapterTitle('PRODUCTOS BAJO STOCK MÍNIMO');
        $pdf->TableHeader();
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($productos as $producto) {
            if ($producto['stock'] > 0 && $producto['stock'] <= $producto['stock_minimo']) {
                $pdf->Cell(22, 6, $producto['codigo_barras'], 1, 0, 'C');
                $pdf->Cell(55, 6, mb_convert_encoding($producto['nombre'], 'ISO-8859-1', 'UTF-8'), 1);
                $pdf->SetTextColor(255, 128, 0);
                $pdf->Cell(15, 6, $producto['stock'], 1, 0, 'C');
                $pdf->SetTextColor(0);
                $pdf->Cell(15, 6, $producto['stock_minimo'], 1, 0, 'C');
                $pdf->Cell(20, 6, $producto['cantidad_faltante'], 1, 0, 'C');
                $pdf->Cell(25, 6, '$' . number_format($producto['precio_costo'], 2, ',', '.'), 1, 0, 'R');
                $pdf->Cell(28, 6, '$' . number_format($producto['costo_reposicion'], 2, ',', '.'), 1, 1, 'R');
            }
        }
    }

    // Recomendaciones
    $pdf->AddPage();
    $pdf->ChapterTitle('RECOMENDACIONES DE ACCIÓN');
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, mb_convert_encoding(
        "1. Priorizar la reposición de productos agotados para evitar pérdidas de ventas.\n\n" .
        "2. Revisar los productos con stock bajo y programar su reabastecimiento.\n\n" .
        "3. Considerar ajustar los niveles de stock mínimo según la demanda actual.\n\n" .
        "4. Contactar a los proveedores para coordinar las órdenes de compra necesarias.\n\n" .
        "5. Evaluar la posibilidad de realizar compras por volumen para obtener mejores precios.",
        'ISO-8859-1',
        'UTF-8'
    ));

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