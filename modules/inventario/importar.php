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
        'H' => ['Precio Venta', 'Precio de venta final (incluye IVA)'],
        'I' => ['Impuesto', 'Porcentaje de IVA (ej: 19)'],
        'J' => ['Departamento', 'Nombre del departamento'],
        'K' => ['Categoría', 'Nombre de la categoría']
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
        ['7501234567890', 'Producto Ejemplo', 'Descripción del producto', '100', '10', 'UNIDAD', '1000.00', '1500.00', '19', 'Electrónicos', 'Smartphones'],
        ['7502345678901', 'Otro Producto', 'Otra descripción', '50', '5', 'KG', '500.00', '750.00', '19', 'Alimentos', 'Frutas']
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

// Agregar funciones auxiliares para manejar departamentos y categorías
function obtenerOCrearDepartamento($pdo, $nombre, $user_id, &$cache = []) {
    $nombre = trim(strtoupper($nombre));
    $cacheKey = $user_id . '_' . $nombre;
    
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    $stmt = $pdo->prepare("SELECT id FROM departamentos WHERE UPPER(nombre) = ? AND user_id = ?");
    $stmt->execute([$nombre, $user_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado) {
        $cache[$cacheKey] = $resultado['id'];
        return $resultado['id'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO departamentos (nombre, user_id, estado) VALUES (?, ?, 'activo')");
    $stmt->execute([$nombre, $user_id]);
    $id = $pdo->lastInsertId();
    $cache[$cacheKey] = $id;
    return $id;
}

function obtenerOCrearCategoria($pdo, $nombre, $user_id, &$cache = []) {
    $nombre = trim(strtoupper($nombre));
    $cacheKey = $user_id . '_' . $nombre;
    
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE UPPER(nombre) = ? AND user_id = ?");
    $stmt->execute([$nombre, $user_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado) {
        $cache[$cacheKey] = $resultado['id'];
        return $resultado['id'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO categorias (nombre, user_id, estado) VALUES (?, ?, 'activo')");
    $stmt->execute([$nombre, $user_id]);
    $id = $pdo->lastInsertId();
    $cache[$cacheKey] = $id;
    return $id;
}

// Función para validar datos
function validarDatosProducto($datos) {
    $errores = [];
    
    // Validar código de barras
    if (empty($datos[0]) || !preg_match('/^\d{8,13}$/', $datos[0])) {
        $errores[] = "Código de barras inválido: debe tener entre 8 y 13 d��gitos";
    }
    
    // Validar nombre
    if (empty($datos[1]) || strlen($datos[1]) > 100) {
        $errores[] = "Nombre inválido: no debe estar vacío ni exceder 100 caracteres";
    }
    
    // Validar stock y stock mínimo
    if (!is_numeric($datos[3]) || $datos[3] < 0) {
        $errores[] = "Stock inválido: debe ser un número positivo";
    }
    if (!is_numeric($datos[4]) || $datos[4] < 0) {
        $errores[] = "Stock mínimo inválido: debe ser un número positivo";
    }
    
    // Validar unidad de medida
    $unidades_validas = ['UNIDAD', 'KG', 'GR', 'LT', 'MT', 'CM'];
    if (!in_array(strtoupper($datos[5]), $unidades_validas)) {
        $errores[] = "Unidad de medida inválida: debe ser una de " . implode(', ', $unidades_validas);
    }
    
    // Validar precio de costo
    if (!is_numeric($datos[6]) || $datos[6] < 0) {
        $errores[] = "Precio de costo inválido: debe ser un número positivo";
    }
    
    // Validar precio de venta
    if (!is_numeric($datos[7]) || $datos[7] < 0) {
        $errores[] = "Precio de venta inválido: debe ser un número positivo";
    }
    
    // Validar que el precio de venta sea mayor al precio de costo
    if (floatval($datos[7]) <= floatval($datos[6])) {
        $errores[] = "El precio de venta debe ser mayor al precio de costo";
    }
    
    // Validar impuesto
    if (!is_numeric($datos[8]) || $datos[8] < 0 || $datos[8] > 100) {
        $errores[] = "Impuesto inválido: debe ser un porcentaje entre 0 y 100";
    }
    
    return $errores;
}

// Agregar función para calcular el margen de ganancia
function calcularMargenGanancia($precio_costo, $precio_venta, $impuesto) {
    // Precio venta sin IVA = Precio venta / (1 + impuesto/100)
    $precio_venta_sin_iva = $precio_venta / (1 + ($impuesto/100));
    
    // Margen = ((Precio venta sin IVA / Precio costo) - 1) * 100
    $margen = (($precio_venta_sin_iva / $precio_costo) - 1) * 100;
    
    return round($margen, 2); // Redondear a 2 decimales
}

// Modificar la parte de procesamiento del archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'errors' => [], 'debug' => []];
    
    try {
        // Validaciones iniciales del archivo
        if ($_FILES['archivo_excel']['size'] > MAX_TAMANO_ARCHIVO) {
            throw new Exception("El archivo excede el tamaño máximo permitido de " . (MAX_TAMANO_ARCHIVO / 1024 / 1024) . "MB");
        }
        
        $extension = strtolower(pathinfo($_FILES['archivo_excel']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'])) {
            throw new Exception("Formato de archivo no válido. Use .xlsx o .xls");
        }
        
        // Cargar el archivo
        $spreadsheet = IOFactory::load($_FILES['archivo_excel']['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        
        // Preparar la consulta para verificar existencia
        $sqlCheck = "SELECT codigo_barras FROM inventario WHERE codigo_barras = ? AND user_id = ?";
        $stmtCheck = $pdo->prepare($sqlCheck);
        
        // Preparar la consulta de inserción
        $sqlInsert = "INSERT INTO inventario (
            user_id, codigo_barras, nombre, descripcion, stock, stock_minimo,
            unidad_medida, precio_costo, margen_ganancia, impuesto, precio_venta,
            departamento_id, categoria_id, fecha_ingreso, fecha_modificacion, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 'activo')";
        $stmtInsert = $pdo->prepare($sqlInsert);
        
        // Preparar la consulta de actualización
        $sqlUpdate = "UPDATE inventario SET 
            nombre = ?, descripcion = ?, stock = ?, stock_minimo = ?,
            unidad_medida = ?, precio_costo = ?, margen_ganancia = ?,
            impuesto = ?, precio_venta = ?, departamento_id = ?,
            categoria_id = ?, fecha_modificacion = NOW()
            WHERE codigo_barras = ? AND user_id = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        
        // Inicializar contadores y arrays
        $productosImportados = 0;
        $productosActualizados = 0;
        $errores = [];
        $procesados = [];
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        // Preparar caché
        $cacheDepartamentos = [];
        $cacheCategorias = [];
        
        // Procesar cada fila
        for ($row = 3; $row <= $highestRow; $row++) {
            $datos = [];
            for ($col = 'A'; $col <= 'K'; $col++) {
                $datos[] = trim($worksheet->getCell($col . $row)->getValue());
            }
            
            // Saltar filas vacías
            if (empty($datos[0])) continue;
            
            try {
                // Validar datos
                $erroresValidacion = validarDatosProducto($datos);
                if (!empty($erroresValidacion)) {
                    $errores[] = "Fila $row: " . implode(", ", $erroresValidacion);
                    continue;
                }
                
                // Obtener IDs de departamento y categoría
                $departamento_id = obtenerOCrearDepartamento($pdo, $datos[9], $user_id, $cacheDepartamentos);
                $categoria_id = obtenerOCrearCategoria($pdo, $datos[10], $user_id, $cacheCategorias);
                
                // Calcular precio de venta
                $precio_costo = floatval($datos[6]);
                $margen = floatval($datos[7]);
                $impuesto = floatval($datos[8]);
                $precio_sin_iva = $precio_costo * (1 + ($margen / 100));
                $precio_venta = $precio_sin_iva * (1 + ($impuesto / 100));
                
                // Verificar si el producto existe
                $stmtCheck->execute([$datos[0], $user_id]);
                $existe = $stmtCheck->fetch();
                
                if ($existe) {
                    // Calcular el margen de ganancia
                    $margen_ganancia = calcularMargenGanancia(
                        floatval($datos[6]),  // precio_costo
                        floatval($datos[7]),  // precio_venta
                        floatval($datos[8])   // impuesto
                    );

                    // Actualizar producto existente
                    $stmtUpdate->execute([
                        $datos[1],            // nombre
                        $datos[2],            // descripcion
                        floatval($datos[3]),  // stock
                        floatval($datos[4]),  // stock_minimo
                        strtoupper($datos[5]),// unidad_medida
                        $precio_costo,        // precio_costo
                        $margen_ganancia,     // margen_ganancia calculado
                        $impuesto,            // impuesto
                        floatval($datos[7]),  // precio_venta
                        $departamento_id,
                        $categoria_id,
                        $datos[0],            // codigo_barras
                        $user_id
                    ]);
                    $productosActualizados++;
                } else {
                    // Calcular el margen de ganancia
                    $margen_ganancia = calcularMargenGanancia(
                        floatval($datos[6]),  // precio_costo
                        floatval($datos[7]),  // precio_venta
                        floatval($datos[8])   // impuesto
                    );

                    // Insertar nuevo producto
                    $stmtInsert->execute([
                        $user_id,
                        $datos[0],            // codigo_barras
                        $datos[1],            // nombre
                        $datos[2],            // descripcion
                        floatval($datos[3]),  // stock
                        floatval($datos[4]),  // stock_minimo
                        strtoupper($datos[5]),// unidad_medida
                        $precio_costo,        // precio_costo
                        $margen_ganancia,     // margen_ganancia calculado
                        $impuesto,            // impuesto
                        floatval($datos[7]),  // precio_venta
                        $departamento_id,
                        $categoria_id
                    ]);
                    $productosImportados++;
                }
                
                $procesados[] = [
                    'fila' => $row,
                    'codigo' => $datos[0],
                    'accion' => $existe ? 'actualizado' : 'importado'
                ];
                
            } catch (Exception $e) {
                $errores[] = "Error en fila $row (código: {$datos[0]}): " . $e->getMessage();
                error_log("Error en importación: " . $e->getMessage());
            }
        }
        
        // Si hay errores, hacer rollback
        if (!empty($errores)) {
            $pdo->rollBack();
            throw new Exception("Se encontraron errores durante la importación");
        }
        
        // Si todo está bien, confirmar la transacción
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = "Proceso completado: $productosImportados productos nuevos importados, $productosActualizados productos actualizados.";
        $response['debug'] = [
            'total_procesados' => count($procesados),
            'detalle_procesados' => $procesados,
            'errores' => $errores
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['success'] = false;
        $response['message'] = "Error: " . $e->getMessage();
        $response['errors'] = $errores;
        $response['debug'] = [
            'error_detalle' => $e->getTraceAsString(),
            'productos_procesados' => $procesados
        ];
    }
    
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Productos | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#1e293b',
                        accent: '#3b82f6'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Encabezado -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Importar Productos</h1>
                        <p class="mt-2 text-sm text-gray-600">
                            Importa tus productos desde un archivo Excel
                        </p>
                    </div>
                    <a href="index.php" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver
                    </a>
                </div>

                <!-- Tarjeta de información -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <div class="flex items-center gap-3 mb-4 text-blue-600">
                        <i class="fas fa-info-circle text-2xl"></i>
                        <h2 class="text-xl font-semibold">Información Importante</h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                            <div class="flex items-center gap-2 mb-2 text-blue-700">
                                <i class="fas fa-file-excel"></i>
                                <h3 class="font-semibold">Formato del Archivo</h3>
                            </div>
                            <ul class="text-sm text-blue-600 space-y-1">
                                <li>• Formatos soportados: XLSX, XLS</li>
                                <li>• Tamaño máximo: <?= MAX_TAMANO_ARCHIVO / (1024 * 1024) ?>MB</li>
                                <li>• Use la plantilla proporcionada</li>
                            </ul>
                        </div>

                        <div class="bg-green-50 rounded-lg p-4 border border-green-100">
                            <div class="flex items-center gap-2 mb-2 text-green-700">
                                <i class="fas fa-check-circle"></i>
                                <h3 class="font-semibold">Límites y Validaciones</h3>
                            </div>
                            <ul class="text-sm text-green-600 space-y-1">
                                <li>• Máximo <?= number_format(MAX_PRODUCTOS_POR_CARGA) ?> productos</li>
                                <li>• Campos obligatorios marcados con *</li>
                                <li>• Validación automática de datos</li>
                            </ul>
                        </div>

                        <div class="bg-purple-50 rounded-lg p-4 border border-purple-100">
                            <div class="flex items-center gap-2 mb-2 text-purple-700">
                                <i class="fas fa-lightbulb"></i>
                                <h3 class="font-semibold">Recomendaciones</h3>
                            </div>
                            <ul class="text-sm text-purple-600 space-y-1">
                                <li>• Revise los datos antes de importar</li>
                                <li>• Haga una copia de seguridad</li>
                                <li>• Siga el formato de la plantilla</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="flex flex-wrap gap-4 mb-8">
                    <a href="?descargar_plantilla=1" 
                       class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all transform hover:scale-105 shadow-sm">
                        <i class="fas fa-download mr-2"></i>
                        Descargar Plantilla
                    </a>
                    <button type="button"
                            onclick="mostrarInstrucciones()"
                            class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all transform hover:scale-105 shadow-sm">
                        <i class="fas fa-question-circle mr-2"></i>
                        Ver Instrucciones
                    </button>
                </div>

                <!-- Zona de carga -->
                <form id="importForm" class="bg-white rounded-xl shadow-lg p-8">
                    <div class="space-y-6">
                        <!-- Área de arrastrar y soltar -->
                        <div id="dropZone" 
                             class="border-2 border-dashed border-gray-300 rounded-xl p-12 text-center hover:border-blue-500 transition-colors cursor-pointer bg-gray-50 hover:bg-blue-50">
                            <input type="file" 
                                   name="archivo_excel" 
                                   id="archivo_excel" 
                                   class="hidden" 
                                   accept=".xlsx,.xls">
                            
                            <div class="space-y-4">
                                <i class="fas fa-cloud-upload-alt text-6xl text-blue-500"></i>
                                <div>
                                    <h3 class="text-xl font-medium text-gray-700">
                                        Arrastra tu archivo Excel aquí
                                    </h3>
                                    <p class="text-gray-500 mt-1">o</p>
                                </div>
                                <button type="button" 
                                        onclick="document.getElementById('archivo_excel').click()"
                                        class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all transform hover:scale-105">
                                    <i class="fas fa-folder-open mr-2"></i>
                                    Seleccionar Archivo
                                </button>
                                <p class="text-sm text-gray-500">
                                    Archivos permitidos: XLSX, XLS (Máx. <?= MAX_TAMANO_ARCHIVO / (1024 * 1024) ?>MB)
                                </p>
                            </div>
                        </div>

                        <!-- Barra de progreso -->
                        <div id="progressContainer" class="hidden space-y-4">
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div id="progressBar" 
                                     class="h-full bg-blue-600 transition-all duration-300"
                                     style="width: 0%">
                                </div>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span id="progressText">Procesando...</span>
                                <span id="progressPercentage">0%</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Función para mostrar instrucciones
        function mostrarInstrucciones() {
            Swal.fire({
                title: 'Instrucciones de Uso',
                html: `
                    <div class="text-left space-y-4">
                        <div class="flex items-center gap-2 text-blue-600">
                            <i class="fas fa-file-excel"></i>
                            <h3 class="font-semibold">Preparación del Archivo</h3>
                        </div>
                        <ol class="list-decimal list-inside space-y-2 text-gray-600">
                            <li>Descargue la plantilla Excel proporcionada</li>
                            <li>Complete los datos según el formato indicado</li>
                            <li>No modifique la estructura de la plantilla</li>
                            <li>Verifique que los datos sean correctos</li>
                            <li>Guarde el archivo en formato XLSX o XLS</li>
                        </ol>

                        <div class="flex items-center gap-2 text-green-600 mt-4">
                            <i class="fas fa-check-circle"></i>
                            <h3 class="font-semibold">Proceso de Importación</h3>
                        </div>
                        <ol class="list-decimal list-inside space-y-2 text-gray-600">
                            <li>Seleccione o arrastre su archivo</li>
                            <li>Confirme la importación</li>
                            <li>Espere a que se complete el proceso</li>
                            <li>Verifique los resultados</li>
                        </ol>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3b82f6',
                customClass: {
                    container: 'text-left'
                }
            });
        }

        // Función para manejar la subida del archivo
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
                    html: `
                        <div class="text-left">
                            <p class="mb-2">Archivo: <strong>${file.name}</strong></p>
                            <p>Tamaño: <strong>${(file.size / 1024 / 1024).toFixed(2)} MB</strong></p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, importar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        importarArchivo(file);
                    }
                });
            }
        }

        // Función para importar el archivo
        function importarArchivo(file) {
            const formData = new FormData();
            formData.append('archivo_excel', file);
            
            // Mostrar progreso
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressPercentage = document.getElementById('progressPercentage');
            
            progressContainer.classList.remove('hidden');
            let progress = 0;
            
            const interval = setInterval(() => {
                progress += Math.random() * 30;
                if (progress > 90) clearInterval(interval);
                updateProgress(Math.min(progress, 90));
            }, 500);

            // Realizar la petición AJAX
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            updateProgress(percentComplete);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    clearInterval(interval);
                    updateProgress(100);
                    
                    setTimeout(() => {
                        progressContainer.classList.add('hidden');
                        
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Importación exitosa!',
                                text: response.message,
                                confirmButtonColor: '#3085d6'
                            }).then(() => {
                                window.location.href = 'index.php';
                            });
                        } else {
                            let errorMessage = response.message;
                            if (response.errors && response.errors.length > 0) {
                                errorMessage += '\n\nErrores encontrados:\n' + response.errors.join('\n');
                            }
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Error en la importación',
                                text: errorMessage,
                                confirmButtonColor: '#d33'
                            });
                        }
                    }, 500);
                },
                error: function(xhr, status, error) {
                    clearInterval(interval);
                    progressContainer.classList.add('hidden');
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al procesar el archivo: ' + error,
                        confirmButtonColor: '#d33'
                    });
                }
            });
        }

        // Función para actualizar la barra de progreso
        function updateProgress(value) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressPercentage = document.getElementById('progressPercentage');
            
            const percentage = Math.round(value);
            progressBar.style.width = `${percentage}%`;
            progressText.textContent = percentage === 100 ? 'Completado' : 'Procesando...';
            progressPercentage.textContent = `${percentage}%`;
        }

        // Event Listeners
        document.getElementById('archivo_excel').addEventListener('change', handleFiles);

        // Drag and Drop
        const dropZone = document.getElementById('dropZone');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('border-blue-500', 'bg-blue-50');
        }

        function unhighlight(e) {
            dropZone.classList.remove('border-blue-500', 'bg-blue-50');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles({ target: { files } });
        }
    </script>
</body>
</html>
