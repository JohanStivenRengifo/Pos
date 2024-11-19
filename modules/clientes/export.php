<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'message' => 'No autorizado']);
    exit();
}

$user_id = $_SESSION['user_id'];
$format = $_GET['format'] ?? 'excel';

// Obtener los clientes
$query = "SELECT 
    nombre,
    primer_nombre,
    segundo_nombre,
    apellidos,
    tipo_identificacion,
    identificacion,
    email,
    telefono,
    municipio_departamento,
    codigo_postal
FROM clientes 
WHERE user_id = ?";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Definir headers del archivo
$headers = [
    'Nombre Comercial',
    'Primer Nombre',
    'Segundo Nombre',
    'Apellidos',
    'Tipo Identificación',
    'Identificación',
    'Email',
    'Teléfono',
    'Ubicación',
    'Código Postal'
];

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="clientes.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Escribir headers
    fputcsv($output, $headers);
    
    // Escribir datos
    foreach ($clientes as $cliente) {
        fputcsv($output, $cliente);
    }
    
    fclose($output);
} else {
    require_once '../../vendor/autoload.php'; // Asegúrate de tener PhpSpreadsheet instalado
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Escribir headers
    foreach ($headers as $index => $header) {
        $sheet->setCellValue(
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1) . '1', 
            $header
        );
    }
    
    // Escribir datos
    $row = 2;
    foreach ($clientes as $cliente) {
        $col = 1;
        foreach ($cliente as $value) {
            $sheet->setCellValue(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row, 
                $value
            );
            $col++;
        }
        $row++;
    }
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="clientes.xlsx"');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
} 