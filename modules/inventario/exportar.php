<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '256M');

session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Verificar la existencia del directorio vendor y el autoload
$vendorPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendorPath)) {
    die('Error: Las dependencias no están instaladas. Por favor, ejecute los siguientes comandos en la terminal:<br><br>' .
        '<code>cd ' . __DIR__ . '/../../</code><br>' .
        '<code>composer require phpoffice/phpspreadsheet</code><br>' .
        '<code>composer require tecnickcom/tcpdf</code>');
}

require_once $vendorPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

try {
    $user_id = $_SESSION['user_id'];
    $formato = $_GET['formato'] ?? 'excel';

    // Verificar que el formato sea válido
    if (!in_array($formato, ['excel', 'pdf'])) {
        throw new Exception('Formato de exportación no válido');
    }

    // Consulta SQL mejorada
    $query = "
        SELECT 
            i.*,
            c.nombre as categoria_nombre,
            d.nombre as departamento_nombre,
            GROUP_CONCAT(DISTINCT b.nombre SEPARATOR ', ') as bodegas,
            (i.stock * i.precio_venta) as valor_total
        FROM inventario i
        LEFT JOIN categorias c ON i.categoria_id = c.id
        LEFT JOIN departamentos d ON i.departamento_id = d.id
        LEFT JOIN inventario_bodegas ib ON i.id = ib.producto_id
        LEFT JOIN bodegas b ON ib.bodega_id = b.id
        WHERE i.user_id = :user_id
        GROUP BY i.id
        ORDER BY i.nombre ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($productos)) {
        throw new Exception('No hay productos para exportar.');
    }

    if ($formato === 'excel') {
        // Crear nuevo documento Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario');

        // Establecer encabezados
        $headers = [
            'A' => ['Código de Barras', 15],
            'B' => ['Nombre', 40],
            'C' => ['Descripción', 50],
            'D' => ['Stock', 10],
            'E' => ['Stock Mínimo', 15],
            'F' => ['Unidad Medida', 15],
            'G' => ['Precio Costo', 15],
            'H' => ['Margen %', 12],
            'I' => ['IVA %', 10],
            'J' => ['Precio Venta', 15],
            'K' => ['Valor Total', 15],
            'L' => ['Categoría', 20],
            'M' => ['Departamento', 20],
            'N' => ['Bodegas', 30],
            'O' => ['Ubicación', 15],
            'P' => ['Estado', 12],
            'Q' => ['Fecha Ingreso', 20]
        ];

        // Aplicar encabezados y ajustar columnas
        foreach ($headers as $columna => $info) {
            $sheet->setCellValue($columna . '1', $info[0]);
            $sheet->getColumnDimension($columna)->setWidth($info[1]);
        }

        // Estilo para encabezados
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
                'name' => 'Arial',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E40AF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $sheet->getStyle('A1:Q1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Llenar datos
        $row = 2;
        foreach ($productos as $producto) {
            $sheet->setCellValue('A' . $row, $producto['codigo_barras']);
            $sheet->setCellValue('B' . $row, $producto['nombre']);
            $sheet->setCellValue('C' . $row, $producto['descripcion']);
            $sheet->setCellValue('D' . $row, $producto['stock']);
            $sheet->setCellValue('E' . $row, $producto['stock_minimo']);
            $sheet->setCellValue('F' . $row, $producto['unidad_medida']);
            $sheet->setCellValue('G' . $row, $producto['precio_costo']);
            $sheet->setCellValue('H' . $row, $producto['margen_ganancia']);
            $sheet->setCellValue('I' . $row, $producto['impuesto']);
            $sheet->setCellValue('J' . $row, $producto['precio_venta']);
            $sheet->setCellValue('K' . $row, $producto['valor_total']);
            $sheet->setCellValue('L' . $row, $producto['categoria_nombre']);
            $sheet->setCellValue('M' . $row, $producto['departamento_nombre']);
            $sheet->setCellValue('N' . $row, $producto['bodegas']);
            $sheet->setCellValue('O' . $row, $producto['ubicacion']);
            $sheet->setCellValue('P' . $row, $producto['estado']);
            $sheet->setCellValue('Q' . $row, $producto['fecha_ingreso']);
            $row++;
        }

        // Aplicar formatos
        $lastRow = $row - 1;
        $sheet->getStyle('G2:K' . $lastRow)->getNumberFormat()
              ->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
        $sheet->getStyle('H2:I' . $lastRow)->getNumberFormat()
              ->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);

        // Estilo para los datos
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A2:Q' . $lastRow)->applyFromArray($dataStyle);

        // Configurar la respuesta HTTP
        ob_end_clean(); // Limpiar cualquier salida previa
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="inventario_' . date('Y-m-d_H-i-s') . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Expires: Fri, 11 Nov 1980 00:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

    } else if ($formato === 'pdf') {
        // Crear nuevo documento PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Configurar documento
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('VendEasy');
        $pdf->SetTitle('Reporte de Inventario');

        // Configurar márgenes
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Agregar página
        $pdf->AddPage('L', 'A4');

        // Crear contenido HTML para el PDF
        $html = '<h1>Reporte de Inventario</h1>';
        $html .= '<table border="1" cellpadding="4">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Stock</th>
                    <th>Precio Costo</th>
                    <th>Precio Venta</th>
                    <th>Valor Total</th>
                    <th>Categoría</th>
                    <th>Departamento</th>
                    <th>Bodegas</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($productos as $producto) {
            $html .= '<tr>
                <td>' . htmlspecialchars($producto['codigo_barras']) . '</td>
                <td>' . htmlspecialchars($producto['nombre']) . '</td>
                <td>' . $producto['stock'] . '</td>
                <td>$' . number_format($producto['precio_costo'], 2) . '</td>
                <td>$' . number_format($producto['precio_venta'], 2) . '</td>
                <td>$' . number_format($producto['valor_total'], 2) . '</td>
                <td>' . htmlspecialchars($producto['categoria_nombre']) . '</td>
                <td>' . htmlspecialchars($producto['departamento_nombre']) . '</td>
                <td>' . htmlspecialchars($producto['bodegas']) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        // Generar PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // Cerrar y generar PDF
        $pdf->Output('inventario_' . date('Y-m-d') . '.pdf', 'D');
        exit;
    }

} catch (Exception $e) {
    error_log("Error en exportación: " . $e->getMessage());
    
    // Determinar si es una solicitud AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error al exportar: ' . $e->getMessage()
        ]);
    } else {
        echo '<div style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 1rem; margin: 1rem; border-radius: 0.25rem;">';
        echo '<h3 style="margin-top: 0;">Error al exportar</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p>Por favor, verifique que:</p>';
        echo '<ul style="margin-left: 20px;">';
        echo '<li>Todas las dependencias estén instaladas correctamente</li>';
        echo '<li>Tenga los permisos necesarios en el sistema</li>';
        echo '<li>Haya suficiente memoria disponible</li>';
        echo '</ul>';
        echo '<p><a href="javascript:history.back()" style="color: #721c24;">← Volver</a></p>';
        echo '</div>';
    }
    exit;
} 