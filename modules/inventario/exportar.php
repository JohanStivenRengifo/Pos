<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// Verificar extensiones requeridas
$required_extensions = ['zip', 'xml', 'gd', 'mbstring'];
$missing_extensions = [];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    die('Error: Las siguientes extensiones de PHP son requeridas: ' . implode(', ', $missing_extensions) . 
        '<br>Por favor, instálelas usando: <code>sudo apt-get install php-' . implode(' php-', $missing_extensions) . '</code>');
}

session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php'; // Asegúrate de tener PhpSpreadsheet instalado

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$formato = $_GET['formato'] ?? 'excel';

// Obtener datos del inventario
function obtenerDatosInventario($pdo, $user_id) {
    $query = "
        SELECT 
            i.codigo_barras,
            i.nombre,
            i.descripcion,
            i.stock,
            i.stock_minimo,
            i.precio_costo,
            i.precio_venta,
            c.nombre as categoria,
            d.nombre as departamento,
            GROUP_CONCAT(DISTINCT b.nombre) as bodegas,
            (i.stock * i.precio_venta) as valor_total
        FROM inventario i
        LEFT JOIN categorias c ON i.categoria_id = c.id
        LEFT JOIN departamentos d ON i.departamento_id = d.id
        LEFT JOIN inventario_bodegas ib ON i.id = ib.producto_id
        LEFT JOIN bodegas b ON ib.bodega_id = b.id
        WHERE i.user_id = :user_id
        GROUP BY i.id
        ORDER BY i.nombre ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$productos = obtenerDatosInventario($pdo, $user_id);

// Función para dar formato a números
function formatoNumero($numero) {
    return number_format($numero, 2, '.', ',');
}

// Cabeceras comunes para CSV y Excel
$headers = [
    'Código de Barras',
    'Nombre',
    'Descripción',
    'Stock',
    'Stock Mínimo',
    'Precio Costo',
    'Precio Venta',
    'Categoría',
    'Departamento',
    'Bodegas',
    'Valor Total'
];

switch ($formato) {
    case 'csv':
        // Configurar headers para descarga
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=inventario_' . date('Y-m-d') . '.csv');
        
        // Crear archivo CSV
        $output = fopen('php://output', 'w');
        
        // BOM para Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Escribir cabeceras
        fputcsv($output, $headers);
        
        // Escribir datos
        foreach ($productos as $producto) {
            $row = [
                $producto['codigo_barras'],
                $producto['nombre'],
                $producto['descripcion'],
                $producto['stock'],
                $producto['stock_minimo'],
                formatoNumero($producto['precio_costo']),
                formatoNumero($producto['precio_venta']),
                $producto['categoria'],
                $producto['departamento'],
                $producto['bodegas'],
                formatoNumero($producto['valor_total'])
            ];
            fputcsv($output, $row);
        }
        
        fclose($output);
        break;

    case 'excel':
        // Crear nuevo documento Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Estilo para cabeceras
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E40AF'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
        ];
        
        // Escribir cabeceras
        foreach ($headers as $index => $header) {
            $column = chr(65 + $index);
            $sheet->setCellValue($column . '1', $header);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
        
        // Escribir datos
        $row = 2;
        foreach ($productos as $producto) {
            $sheet->setCellValue('A' . $row, $producto['codigo_barras']);
            $sheet->setCellValue('B' . $row, $producto['nombre']);
            $sheet->setCellValue('C' . $row, $producto['descripcion']);
            $sheet->setCellValue('D' . $row, $producto['stock']);
            $sheet->setCellValue('E' . $row, $producto['stock_minimo']);
            $sheet->setCellValue('F' . $row, $producto['precio_costo']);
            $sheet->setCellValue('G' . $row, $producto['precio_venta']);
            $sheet->setCellValue('H' . $row, $producto['categoria']);
            $sheet->setCellValue('I' . $row, $producto['departamento']);
            $sheet->setCellValue('J' . $row, $producto['bodegas']);
            $sheet->setCellValue('K' . $row, $producto['valor_total']);
            
            // Formato para columnas numéricas
            $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('K' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            
            $row++;
        }
        
        // Configurar headers para descarga
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="inventario_' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        // Crear archivo Excel
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        break;

    default:
        header('Location: index.php');
        exit();
} 