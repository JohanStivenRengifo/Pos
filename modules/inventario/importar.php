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

// Agregar constantes para límites
define('MAX_PRODUCTOS_POR_CARGA', 5000);
define('MAX_TAMANO_ARCHIVO', 25 * 1024 * 1024); // 25MB

// Función para crear y descargar la plantilla
function descargarPlantilla() {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Estilo para encabezados
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => '007BFF']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
    ];
    
    // Añadir encabezados con descripciones
    $headers = [
        'A' => ['Código de Barras', 'Código único del producto (8-13 dígitos)'],
        'B' => ['Nombre', 'Nombre del producto (máx. 100 caracteres)'],
        'C' => ['Descripción', 'Descripción detallada del producto'],
        'D' => ['Stock', 'Cantidad inicial en inventario'],
        'E' => ['Stock Mínimo', 'Cantidad mínima antes de alerta'],
        'F' => ['Unidad Medida', 'UNIDAD, KG, GR, LT, MT, CM'],
        'G' => ['Precio Costo', 'Precio de compra sin IVA'],
        'H' => ['Margen Ganancia', 'Porcentaje de ganancia'],
        'I' => ['Impuesto', 'Porcentaje de IVA (ej: 19)'],
        'J' => ['Departamento ID', 'ID del departamento'],
        'K' => ['Categoría ID', 'ID de la categoría']
    ];

    // Aplicar encabezados y estilos
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($col . '1', $header[0]);
        $sheet->setCellValue($col . '2', $header[1]);
    }
    
    // Aplicar estilos
    $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
    $sheet->getStyle('A2:K2')->getFont()->setItalic(true);
    
    // Agregar datos de ejemplo
    $ejemplos = [
        ['7501234567890', 'Producto Ejemplo', 'Descripción del producto', '100', '10', 'UNIDAD', '1000.00', '30', '19', '1', '1'],
        ['7502345678901', 'Otro Producto', 'Otra descripción', '50', '5', 'KG', '500.00', '25', '19', '2', '2']
    ];
    
    // Agregar los ejemplos usando coordenadas de celda directas
    $sheet->setCellValue('A3', $ejemplos[0][0]);
    $sheet->setCellValue('B3', $ejemplos[0][1]);
    $sheet->setCellValue('C3', $ejemplos[0][2]);
    $sheet->setCellValue('D3', $ejemplos[0][3]);
    $sheet->setCellValue('E3', $ejemplos[0][4]);
    $sheet->setCellValue('F3', $ejemplos[0][5]);
    $sheet->setCellValue('G3', $ejemplos[0][6]);
    $sheet->setCellValue('H3', $ejemplos[0][7]);
    $sheet->setCellValue('I3', $ejemplos[0][8]);
    $sheet->setCellValue('J3', $ejemplos[0][9]);
    $sheet->setCellValue('K3', $ejemplos[0][10]);

    $sheet->setCellValue('A4', $ejemplos[1][0]);
    $sheet->setCellValue('B4', $ejemplos[1][1]);
    $sheet->setCellValue('C4', $ejemplos[1][2]);
    $sheet->setCellValue('D4', $ejemplos[1][3]);
    $sheet->setCellValue('E4', $ejemplos[1][4]);
    $sheet->setCellValue('F4', $ejemplos[1][5]);
    $sheet->setCellValue('G4', $ejemplos[1][6]);
    $sheet->setCellValue('H4', $ejemplos[1][7]);
    $sheet->setCellValue('I4', $ejemplos[1][8]);
    $sheet->setCellValue('J4', $ejemplos[1][9]);
    $sheet->setCellValue('K4', $ejemplos[1][10]);

    // Autoajustar columnas
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Configurar la hoja
    $sheet->setTitle('Plantilla Inventario');
    
    // Crear el escritor de Excel
    $writer = new Xlsx($spreadsheet);
    
    // Configurar las cabeceras
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="plantilla_inventario.xlsx"');
    header('Cache-Control: max-age=0');
    
    // Guardar
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
            // Cargar el archivo Excel
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

            // Desactivar el autocommit para mejorar el rendimiento
            $pdo->beginTransaction();

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
            
            // Confirmar la transacción
            $pdo->commit();
            $mensaje = "Importación realizada con éxito.";
        } catch (Exception $e) {
            $pdo->rollBack();
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
    <title>Importar Archivos | VendEasy</title> 
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <style>
        .importar-container {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .info-card {
            background: #e3f2fd;
            border-left: 4px solid #1976d2;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .info-card h3 {
            color: #1976d2;
            margin: 0 0 10px 0;
        }

        .info-card ul {
            margin: 0;
            padding-left: 20px;
        }

        .info-card li {
            margin-bottom: 5px;
            color: #555;
        }

        .upload-zone {
            border: 2px dashed #ccc;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
            transition: all 0.3s ease;
        }

        .upload-zone.dragover {
            background: #e3f2fd;
            border-color: #1976d2;
        }

        .upload-icon {
            font-size: 48px;
            color: #1976d2;
            margin-bottom: 15px;
        }

        .progress-container {
            margin-top: 20px;
            display: none;
        }

        .progress {
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-bar {
            height: 100%;
            background: #1976d2;
            width: 0%;
            transition: width 0.3s ease;
        }

        .progress-text {
            text-align: center;
            color: #666;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #1976d2;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .file-input {
            display: none;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading {
            animation: spin 1s linear infinite;
        }
    </style>
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
                <a href="../pos/index.php">POS</a>
                <a href="../ventas/index.php">Ventas</a>
                <a href="../inventario/index.php" class="active">Inventario</a>
                <a href="../clientes/index.php">Clientes</a>
                <a href="../reportes/index.php">Reportes</a>
                <a href="../config/index.php">Configuración</a>
            </div>
        </nav>

        <div class="main-body">
            <h2>Importar Productos</h2>
            
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Información Importante</h3>
                <ul>
                    <li>Puede importar hasta <strong><?= number_format(MAX_PRODUCTOS_POR_CARGA) ?></strong> productos por archivo</li>
                    <li>Tamaño máximo del archivo: <strong><?= MAX_TAMANO_ARCHIVO / (1024 * 1024) ?>MB</strong></li>
                    <li>Formatos soportados: <strong>XLSX, XLS</strong></li>
                    <li>La plantilla incluye ejemplos y validaciones</li>
                    <li>Los campos marcados con * son obligatorios</li>
                </ul>
            </div>

            <div class="importar-container">
                <div class="btn-group">
                    <a href="?descargar_plantilla=1" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Descargar Plantilla
                    </a>
                    <a href="#" class="btn btn-secondary" onclick="mostrarInstrucciones()">
                        <i class="fas fa-question-circle"></i> Ver Instrucciones
                    </a>
                </div>

                <form id="importForm" action="" method="post" enctype="multipart/form-data">
                    <div class="upload-zone" id="dropZone">
                        <div class="upload-icon">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <h3>Arrastra tu archivo Excel aquí</h3>
                        <p>o</p>
                        <input type="file" name="archivo_excel" id="archivo_excel" 
                               class="file-input" accept=".xlsx,.xls">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('archivo_excel').click()">
                            <i class="fas fa-folder-open"></i> Seleccionar Archivo
                        </button>
                    </div>

                    <div class="progress-container" id="progressContainer">
                        <div class="progress">
                            <div class="progress-bar" id="progressBar"></div>
                        </div>
                        <div class="progress-text" id="progressText">Procesando...</div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Funciones para drag & drop
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('archivo_excel');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        dropZone.addEventListener('drop', handleDrop, false);
        fileInput.addEventListener('change', handleFiles);

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight(e) {
            dropZone.classList.add('dragover');
        }

        function unhighlight(e) {
            dropZone.classList.remove('dragover');
        }

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles({ target: { files } });
        }

        function handleFiles(e) {
            const files = e.target.files;
            if (files.length > 0) {
                const file = files[0];
                
                // Validar tipo de archivo
                if (!file.name.match(/\.(xlsx|xls)$/i)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Archivo no válido',
                        text: 'Por favor, seleccione un archivo Excel (.xlsx, .xls)'
                    });
                    return;
                }

                // Validar tamaño
                if (file.size > <?= MAX_TAMANO_ARCHIVO ?>) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Archivo demasiado grande',
                        text: 'El archivo no debe superar los <?= MAX_TAMANO_ARCHIVO / (1024 * 1024) ?>MB'
                    });
                    return;
                }

                // Mostrar confirmación
                Swal.fire({
                    title: '¿Importar archivo?',
                    text: `¿Desea importar el archivo ${file.name}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, importar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        importarArchivo();
                    }
                });
            }
        }

        function importarArchivo() {
            const formData = new FormData(document.getElementById('importForm'));
            
            // Mostrar progreso
            progressContainer.style.display = 'block';
            let progress = 0;
            
            const interval = setInterval(() => {
                progress += Math.random() * 30;
                if (progress > 90) clearInterval(interval);
                updateProgress(Math.min(progress, 90));
            }, 500);

            fetch('importar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(interval);
                updateProgress(100);
                
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Importación exitosa!',
                            text: data.message
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message
                        });
                    }
                }, 500);
            })
            .catch(error => {
                clearInterval(interval);
                progressContainer.style.display = 'none';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Hubo un problema al procesar el archivo'
                });
            });
        }

        function updateProgress(value) {
            progressBar.style.width = `${value}%`;
            progressText.textContent = `Procesando... ${Math.round(value)}%`;
        }

        function mostrarInstrucciones() {
            Swal.fire({
                title: 'Instrucciones de Uso',
                html: `
                    <div style="text-align: left">
                        <ol>
                            <li>Descargue la plantilla Excel</li>
                            <li>Complete los datos según el formato indicado</li>
                            <li>No modifique la estructura de la plantilla</li>
                            <li>Verifique que los IDs de departamentos y categorías existan</li>
                            <li>Puede importar hasta ${MAX_PRODUCTOS_POR_CARGA.toLocaleString()} productos</li>
                            <li>El archivo no debe superar los ${MAX_TAMANO_ARCHIVO/(1024*1024)}MB</li>
                        </ol>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Entendido'
            });
        }
    </script>
</body>
</html>
