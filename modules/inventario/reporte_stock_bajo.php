<?php
ob_start();
session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Clase para manejar el reporte
class StockReport {
    private $pdo;
    private $user_id;
    private $empresa;
    private $productos;

    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->loadData();
    }

    private function loadData() {
        $this->empresa = $this->getEmpresaInfo();
        $this->productos = $this->getProductosBajoStock();
    }

    private function getEmpresaInfo() {
        $stmt = $this->pdo->prepare("
            SELECT e.* 
            FROM empresas e 
            INNER JOIN users u ON u.empresa_id = e.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$this->user_id]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $empresa ?: [
            'nombre_empresa' => 'Empresa No Configurada',
            'nit' => 'N/A',
            'direccion' => 'N/A',
            'telefono' => 'N/A',
            'correo_contacto' => 'N/A'
        ];
    }

    private function getProductosBajoStock() {
        $stmt = $this->pdo->prepare("
            SELECT 
                i.*, 
                c.nombre as categoria,
                d.nombre as departamento,
                (i.stock_minimo - i.stock) as faltante,
                (i.stock_minimo - i.stock) * i.precio_costo as valor_faltante
            FROM inventario i
            LEFT JOIN categorias c ON i.categoria_id = c.id
            LEFT JOIN departamentos d ON i.departamento_id = d.id
            WHERE i.user_id = ? AND i.stock <= i.stock_minimo
            ORDER BY i.stock ASC, i.nombre ASC
        ");
        $stmt->execute([$this->user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generatePDF() {
        // Crear nuevo documento PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        
        // Configurar documento
        $pdf->SetCreator('VendEasy');
        $pdf->SetAuthor($this->empresa['nombre_empresa']);
        $pdf->SetTitle('Reporte de Stock Bajo - ' . date('Y-m-d'));
        
        // Eliminar cabecera y pie de página por defecto
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Agregar página
        $pdf->AddPage();
        
        // Cabecera del reporte
        $this->addHeader($pdf);
        
        // Información de la empresa
        $this->addCompanyInfo($pdf);
        
        // Contenido del reporte
        $this->addContent($pdf);
        
        // Resumen y estadísticas
        $this->addSummary($pdf);
        
        // Pie de página
        $this->addFooter($pdf);
        
        // Limpiar cualquier salida anterior
        ob_clean();
        
        // Generar PDF
        $pdf->Output('Reporte_Stock_Bajo_' . date('Y-m-d') . '.pdf', 'D');
    }

    private function addHeader($pdf) {
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 15, 'Reporte de Productos con Stock Bajo', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generado el: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $pdf->Ln(5);
    }

    private function addCompanyInfo($pdf) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, $this->empresa['nombre_empresa'], 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $pdf->Cell(30, 6, 'NIT:', 0, 0, 'L');
        $pdf->Cell(0, 6, $this->empresa['nit'], 0, 1, 'L');
        
        $pdf->Cell(30, 6, 'Dirección:', 0, 0, 'L');
        $pdf->Cell(0, 6, $this->empresa['direccion'], 0, 1, 'L');
        
        $pdf->Cell(30, 6, 'Teléfono:', 0, 0, 'L');
        $pdf->Cell(0, 6, $this->empresa['telefono'], 0, 1, 'L');
        
        $pdf->Cell(30, 6, 'Email:', 0, 0, 'L');
        $pdf->Cell(0, 6, $this->empresa['correo_contacto'], 0, 1, 'L');
        
        $pdf->Ln(5);
    }

    private function addContent($pdf) {
        if (empty($this->productos)) {
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, 'No hay productos con stock bajo en este momento.', 0, 1, 'C');
            return;
        }

        // Cabecera de la tabla
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        
        $this->addTableHeader($pdf);
        
        // Contenido de la tabla
        $pdf->SetFont('helvetica', '', 8);
        $fill = false;
        
        foreach ($this->productos as $producto) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                $this->addTableHeader($pdf);
            }
            
            $this->addTableRow($pdf, $producto, $fill);
            $fill = !$fill;
        }
    }

    private function addTableHeader($pdf) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(240, 240, 240);
        
        $pdf->Cell(50, 7, 'Producto', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Código', 1, 0, 'C', true);
        $pdf->Cell(15, 7, 'Stock', 1, 0, 'C', true);
        $pdf->Cell(15, 7, 'Mínimo', 1, 0, 'C', true);
        $pdf->Cell(15, 7, 'Falta', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Categoría', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Valor Faltante', 1, 1, 'C', true);
    }

    private function addTableRow($pdf, $producto, $fill) {
        $pdf->SetFillColor(245, 245, 245);
        
        $pdf->Cell(50, 6, $producto['nombre'], 1, 0, 'L', $fill);
        $pdf->Cell(25, 6, $producto['codigo_barras'], 1, 0, 'C', $fill);
        $pdf->Cell(15, 6, $producto['stock'], 1, 0, 'C', $fill);
        $pdf->Cell(15, 6, $producto['stock_minimo'], 1, 0, 'C', $fill);
        $pdf->Cell(15, 6, $producto['faltante'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $producto['categoria'] ?? 'Sin categoría', 1, 0, 'L', $fill);
        $pdf->Cell(40, 6, '$' . number_format($producto['valor_faltante'], 2), 1, 1, 'R', $fill);
    }

    private function addSummary($pdf) {
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Resumen del Reporte:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $total_productos = count($this->productos);
        $total_faltante = array_sum(array_column($this->productos, 'valor_faltante'));
        
        $pdf->Cell(0, 6, "Total de productos con stock bajo: {$total_productos}", 0, 1, 'L');
        $pdf->Cell(0, 6, "Valor total faltante: $" . number_format($total_faltante, 2), 0, 1, 'L');
    }

    private function addFooter($pdf) {
        $pdf->SetY(-15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Página ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'C');
    }
}

// Generar el reporte
try {
    $report = new StockReport($pdo, $user_id);
    $report->generatePDF();
} catch (Exception $e) {
    error_log("Error generando reporte: " . $e->getMessage());
    header("Location: index.php?error=reporte");
    exit;
}