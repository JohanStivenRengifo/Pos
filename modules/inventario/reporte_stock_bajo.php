<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Antes de cualquier salida, limpiar el buffer
if (ob_get_level()) ob_end_clean();

try {
    // Verificar y cargar FPDF
    if (!class_exists('FPDF')) {
        require_once '../../vendor/fpdf/fpdf.php';
    }

    // Crear clase personalizada de PDF
    class StockReportPDF extends FPDF {
        protected $empresa;

        function __construct($empresa) {
            parent::__construct();
            $this->empresa = $empresa;
        }

        function Header() {
            $this->SetMargins(15, 15, 15);
            
            // Logo
            if (!empty($this->empresa['logo']) && file_exists('../../' . $this->empresa['logo'])) {
                $this->Image('../../' . $this->empresa['logo'], 15, 15, 30);
                $this->Ln(35);
            }
            
            // Título del reporte
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(0, 51, 102);
            $this->Cell(0, 10, 'REPORTE DE STOCK BAJO Y AGOTADO', 0, 1, 'C');
            
            // Información de la empresa
            $this->SetFont('Arial', '', 11);
            $this->SetTextColor(0);
            $this->Cell(0, 6, $this->empresa['nombre_empresa'], 0, 1, 'C');
            $this->Cell(0, 6, 'NIT: ' . $this->empresa['nit'], 0, 1, 'C');
            $this->Cell(0, 6, $this->empresa['direccion'], 0, 1, 'C');
            $this->Cell(0, 6, 'Fecha: ' . date('d/m/Y H:i'), 0, 1, 'C');
            $this->Ln(5);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(128);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }

        function ChapterTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(0, 51, 102);
            $this->SetTextColor(255);
            $this->Cell(0, 8, $title, 1, 1, 'L', true);
            $this->SetTextColor(0);
            $this->Ln(4);
        }

        function TableHeader() {
            $this->SetFont('Arial', 'B', 9);
            $this->SetFillColor(240, 240, 240);
            $this->SetTextColor(0, 51, 102);
            
            $this->Cell(30, 7, 'Codigo', 1, 0, 'C', true);
            $this->Cell(55, 7, 'Producto', 1, 0, 'L', true);
            $this->Cell(15, 7, 'Stock', 1, 0, 'C', true);
            $this->Cell(20, 7, 'Minimo', 1, 0, 'C', true);
            $this->Cell(20, 7, 'Faltante', 1, 0, 'C', true);
            $this->Cell(25, 7, 'Costo U.', 1, 0, 'R', true);
            $this->Cell(25, 7, 'Inversion', 1, 1, 'R', true);
            $this->SetTextColor(0);
        }
    }

    // Obtener información de la empresa
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE estado = 1 AND es_principal = 1 LIMIT 1");
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    // Consulta para obtener productos
    $stmt = $pdo->prepare("
        SELECT 
            i.*, 
            c.nombre as categoria_nombre,
            d.nombre as departamento_nombre,
            COALESCE(b.nombre, 'Sin asignar') as bodega,
            (i.stock * i.precio_costo) as valor_costo_total,
            (i.stock_minimo - i.stock) as cantidad_faltante,
            ((i.stock_minimo - i.stock) * i.precio_costo) as costo_reposicion
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

    // Crear nuevo PDF
    $pdf = new StockReportPDF($empresa);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Resumen ejecutivo
    $pdf->ChapterTitle('RESUMEN EJECUTIVO');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(248, 249, 250);
    
    // Tabla de resumen
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 8, 'Indicador', 1, 0, 'L', true);
    $pdf->Cell(90, 8, 'Valor', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(100, 7, 'Total productos con stock critico', 1, 0, 'L');
    $pdf->Cell(90, 7, $total_productos, 1, 1, 'C');
    
    $pdf->Cell(100, 7, 'Productos agotados', 1, 0, 'L');
    $pdf->Cell(90, 7, $productos_agotados, 1, 1, 'C');
    
    $pdf->Cell(100, 7, 'Productos bajo stock minimo', 1, 0, 'L');
    $pdf->Cell(90, 7, $productos_bajo_stock, 1, 1, 'C');
    
    $pdf->Cell(100, 7, 'Inversion necesaria', 1, 0, 'L');
    $pdf->Cell(90, 7, '$' . number_format($total_costo_reposicion, 2, ',', '.'), 1, 1, 'C');
    
    $pdf->Ln(10);

    // Productos Agotados
    if ($productos_agotados > 0) {
        $pdf->ChapterTitle('PRODUCTOS AGOTADOS');
        $pdf->TableHeader();
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($productos as $producto) {
            if ($producto['stock'] == 0) {
                $pdf->Cell(30, 6, $producto['codigo_barras'], 1, 0, 'C');
                $pdf->Cell(55, 6, $producto['nombre'], 1);
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Cell(15, 6, $producto['stock'], 1, 0, 'C');
                $pdf->SetTextColor(0);
                $pdf->Cell(20, 6, $producto['stock_minimo'], 1, 0, 'C');
                $pdf->Cell(20, 6, $producto['cantidad_faltante'], 1, 0, 'C');
                $pdf->Cell(25, 6, '$' . number_format($producto['precio_costo'], 2, ',', '.'), 1, 0, 'R');
                $pdf->Cell(25, 6, '$' . number_format($producto['costo_reposicion'], 2, ',', '.'), 1, 1, 'R');
            }
        }
        $pdf->Ln(10);
    }

    // Productos Bajo Stock
    if ($productos_bajo_stock > 0) {
        $pdf->ChapterTitle('PRODUCTOS BAJO STOCK MINIMO');
        $pdf->TableHeader();
        
        $pdf->SetFont('Arial', '', 8);
        foreach ($productos as $producto) {
            if ($producto['stock'] > 0 && $producto['stock'] <= $producto['stock_minimo']) {
                $pdf->Cell(30, 6, $producto['codigo_barras'], 1, 0, 'C');
                $pdf->Cell(55, 6, $producto['nombre'], 1);
                $pdf->SetTextColor(255, 128, 0);
                $pdf->Cell(15, 6, $producto['stock'], 1, 0, 'C');
                $pdf->SetTextColor(0);
                $pdf->Cell(20, 6, $producto['stock_minimo'], 1, 0, 'C');
                $pdf->Cell(20, 6, $producto['cantidad_faltante'], 1, 0, 'C');
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