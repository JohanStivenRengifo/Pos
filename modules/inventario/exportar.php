<?php
session_start();
require_once '../../config/db.php';
require_once '../../vendor/autoload.php'; // Asegúrate de tener instalado PhpSpreadsheet y TCPDF

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$formato = $_GET['formato'] ?? 'excel';

// Consulta SQL para obtener todos los datos del inventario
$query = "
    SELECT 
        i.*,
        c.nombre as categoria_nombre,
        d.nombre as departamento_nombre,
        GROUP_CONCAT(DISTINCT b.nombre) as bodegas,
        (i.stock * i.precio_venta) as valor_total
    FROM inventario i
    LEFT JOIN categorias c ON i.categoria_id = c.id
    LEFT JOIN departamentos d ON i.departamento_id = d.id
    LEFT JOIN inventario_bodegas ib ON i.id = ib.producto_id
    LEFT JOIN bodegas b ON ib.bodega_id = b.id
    WHERE i.user_id = :user_id
    GROUP BY i.id
";

$stmt = $pdo->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($formato === 'excel') {
    // Crear nuevo documento Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Establecer encabezados
    $headers = [
        'Código de Barras', 'Nombre', 'Descripción', 'Stock', 'Stock Mínimo',
        'Unidad Medida', 'Precio Costo', 'Margen Ganancia', 'Impuesto',
        'Precio Venta', 'Valor Total', 'Categoría', 'Departamento',
        'Bodegas', 'Ubicación', 'Estado', 'Fecha Ingreso'
    ];

    foreach (array_values($headers) as $i => $header) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
    }

    // Llenar datos
    $row = 2;
    foreach ($productos as $producto) {
        $sheet->setCellValueByColumnAndRow(1, $row, $producto['codigo_barras']);
        $sheet->setCellValueByColumnAndRow(2, $row, $producto['nombre']);
        $sheet->setCellValueByColumnAndRow(3, $row, $producto['descripcion']);
        $sheet->setCellValueByColumnAndRow(4, $row, $producto['stock']);
        $sheet->setCellValueByColumnAndRow(5, $row, $producto['stock_minimo']);
        $sheet->setCellValueByColumnAndRow(6, $row, $producto['unidad_medida']);
        $sheet->setCellValueByColumnAndRow(7, $row, $producto['precio_costo']);
        $sheet->setCellValueByColumnAndRow(8, $row, $producto['margen_ganancia']);
        $sheet->setCellValueByColumnAndRow(9, $row, $producto['impuesto']);
        $sheet->setCellValueByColumnAndRow(10, $row, $producto['precio_venta']);
        $sheet->setCellValueByColumnAndRow(11, $row, $producto['valor_total']);
        $sheet->setCellValueByColumnAndRow(12, $row, $producto['categoria_nombre']);
        $sheet->setCellValueByColumnAndRow(13, $row, $producto['departamento_nombre']);
        $sheet->setCellValueByColumnAndRow(14, $row, $producto['bodegas']);
        $sheet->setCellValueByColumnAndRow(15, $row, $producto['ubicacion']);
        $sheet->setCellValueByColumnAndRow(16, $row, $producto['estado']);
        $sheet->setCellValueByColumnAndRow(17, $row, $producto['fecha_ingreso']);
        $row++;
    }

    // Autoajustar columnas
    foreach (range('A', 'Q') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Configurar encabezados HTTP para descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="inventario_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Crear archivo Excel
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