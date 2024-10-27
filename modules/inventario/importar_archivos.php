<?php
session_start();
require_once '../../config/db.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

$mensaje = '';

// Función para crear y descargar la plantilla
function descargarPlantilla() {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Añadir encabezados
    $sheet->setCellValue('A1', 'Código de Barras');
    $sheet->setCellValue('B1', 'Nombre');
    $sheet->setCellValue('C1', 'Descripción');
    $sheet->setCellValue('D1', 'Stock');
    $sheet->setCellValue('E1', 'Precio Costo');
    $sheet->setCellValue('F1', 'Impuesto');
    $sheet->setCellValue('G1', 'Precio Venta');
    $sheet->setCellValue('H1', 'Otro Dato');
    $sheet->setCellValue('I1', 'Departamento ID');
    $sheet->setCellValue('J1', 'Categoría ID');

    // Estilo para los encabezados
    $sheet->getStyle('A1:J1')->getFont()->setBold(true);

    // Autoajustar el ancho de las columnas
    foreach(range('A','J') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Crear el escritor de Excel
    $writer = new Xlsx($spreadsheet);

    // Configurar las cabeceras para la descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="plantilla_inventario.xlsx"');
    header('Cache-Control: max-age=0');

    // Guardar el archivo directamente en la salida
    $writer->save('php://output');
    exit;
}

// Si se solicita la descarga de la plantilla
if (isset($_GET['descargar_plantilla'])) {
    descargarPlantilla();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel'])) {
    $archivo = $_FILES['archivo_excel'];
    
    // Verificar si es un archivo Excel
    $extensiones_permitidas = ['xlsx', 'xls'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    if (in_array($extension, $extensiones_permitidas)) {
        $ruta_temporal = $archivo['tmp_name'];
        
        try {
            $spreadsheet = IOFactory::load($ruta_temporal);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Preparar la consulta SQL para insertar o actualizar productos
            $sql = "INSERT INTO inventario (user_id, codigo_barras, nombre, descripcion, stock, precio_costo, impuesto, precio_venta, otro_dato, fecha_ingreso, departamento_id, categoria_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    nombre = VALUES(nombre), 
                    descripcion = VALUES(descripcion),
                    stock = VALUES(stock), 
                    precio_costo = VALUES(precio_costo), 
                    impuesto = VALUES(impuesto),
                    precio_venta = VALUES(precio_venta), 
                    otro_dato = VALUES(otro_dato),
                    fecha_ingreso = NOW(),
                    departamento_id = VALUES(departamento_id), 
                    categoria_id = VALUES(categoria_id)";
            $stmt = $pdo->prepare($sql);

            // Leer y procesar cada fila del Excel
            foreach ($worksheet->getRowIterator(2) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $datos = [];
                foreach ($cellIterator as $cell) {
                    $datos[] = $cell->getValue();
                }
                
                if (count($datos) >= 10) {
                    $stmt->execute([
                        $user_id,
                        $datos[0], // codigo_barras
                        $datos[1], // nombre
                        $datos[2], // descripcion
                        $datos[3], // stock
                        $datos[4], // precio_costo
                        $datos[5], // impuesto
                        $datos[6], // precio_venta
                        $datos[7], // otro_dato
                        $datos[8], // departamento_id
                        $datos[9]  // categoria_id
                    ]);
                }
            }
            
            $mensaje = "Importación realizada con éxito.";
        } catch (Exception $e) {
            $mensaje = "Error al procesar el archivo: " . $e->getMessage();
        }
    } else {
        $mensaje = "Por favor, sube un archivo Excel válido (.xlsx o .xls).";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Archivos - VendEasy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
</head>
<body>
    <header class="header">
        <div class="logo">
            <a href="../../welcome.php">VendEasy</a>
        </div>
        <div class="header-icons">
            <i class="fas fa-bell"></i>
            <div class="account">
                <h4><?= htmlspecialchars($email) ?></h4>
            </div>
        </div>
    </header>
    <div class="container">
        <nav>
            <div class="side_navbar">
                <span>Menú Principal</span>
                <a href="../../welcome.php">Dashboard</a>
                <a href="../pos/index.php">Punto de Venta</a>
                <a href="../ventas/index.php">Ventas</a>
                <a href="../inventario/index.php" class="active">Inventario</a>
                <a href="../clientes/index.php">Clientes</a>
                <a href="../reportes/index.php">Reportes</a>
                <a href="../config/index.php">Configuración</a>
            </div>
        </nav>

        <div class="main-body">
            <h2>Importar Archivos</h2>
            <div class="promo_card">
                <h1>Importación de Inventario</h1>
                <span>Importe su inventario desde un archivo Excel.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Importar Archivo Excel</h4>
                    </div>
                    <p>Descargue la plantilla para asegurarse de que su archivo Excel tenga el formato correcto:</p>
                    <a href="?descargar_plantilla=1" class="btn btn-secondary">Descargar Plantilla</a>
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="archivo_excel">Seleccione el archivo Excel:</label>
                            <input type="file" name="archivo_excel" id="archivo_excel" accept=".xlsx, .xls" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Importar Excel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        <?php if (!empty($mensaje)): ?>
        Swal.fire({
            icon: '<?= strpos($mensaje, 'éxito') !== false ? 'success' : 'error' ?>',
            title: 'Notificación',
            text: '<?= $mensaje ?>'
        });
        <?php endif; ?>
    });
    </script>
</body>
</html>
