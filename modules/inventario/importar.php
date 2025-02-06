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

// Primero, necesitamos modificar la tabla inventario para aumentar el tamaño del campo codigo_barras
$sqlAlterTable = "ALTER TABLE inventario MODIFY COLUMN codigo_barras VARCHAR(20) NOT NULL";

// Agregar esta línea justo después de la conexión a la base de datos, antes de procesar cualquier archivo
try {
    $pdo->exec($sqlAlterTable);
} catch (PDOException $e) {
    // Si hay un error, continuamos ya que la columna podría ya tener el tamaño correcto
}

// Función para crear y descargar la plantilla
function descargarPlantilla() {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Establecer encabezados
    $headers = [
        'A1' => 'Código de Barras*',
        'B1' => 'Nombre del Producto*',
        'C1' => 'Descripción',
        'D1' => 'Stock*',
        'E1' => 'Stock Mínimo*',
        'F1' => 'Unidad de Medida*',
        'G1' => 'Precio de Costo*',
        'H1' => 'Precio de Venta*',
        'I1' => 'Impuesto (%)*',
        'J1' => 'Departamento*',
        'K1' => 'Categoría*',
        'L1' => 'Bodega',
        'M1' => 'Ubicación en Bodega'
    ];
    
    // Ejemplos de datos
    $ejemplos = [
        [
            'Instrucciones',
            'Complete los campos según el formato indicado',
            'Los campos con * son obligatorios',
            'Unidades de medida permitidas: UNIDAD, KILOGRAMO, LITRO, METRO, CAJA, PAQUETE',
            'Los precios deben ser números positivos',
            'El impuesto debe ser un número entre 0 y 100',
            'Si la bodega no existe, se creará automáticamente',
            'La ubicación en bodega es opcional'
        ],
        [
            '7501234567890',
            'Smartphone Samsung Galaxy A52',
            'Teléfono inteligente 128GB',
            '10',
            '5',
            'UNIDAD',
            '300.00',
            '399.99',
            '18',
            'ELECTRÓNICOS',
            'CELULARES',
            'BODEGA PRINCIPAL',
            'ESTANTE A-1'
        ]
    ];

    // Aplicar los datos
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }

    // Aplicar los ejemplos
    $sheet->fromArray($ejemplos[0], null, 'A3');
    $sheet->fromArray($ejemplos[1], null, 'A4');

    // Autoajustar columnas
    foreach (range('A', 'M') as $col) {
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

// Agregar después de las funciones de departamentos y categorías

function obtenerOCrearBodega($pdo, $nombre, $ubicacion, $user_id, &$cache = []) {
    $nombre = trim(strtoupper($nombre));
    $cacheKey = $user_id . '_' . $nombre;
    
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    $stmt = $pdo->prepare("SELECT id FROM bodegas WHERE UPPER(nombre) = ? AND usuario_id = ?");
    $stmt->execute([$nombre, $user_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado) {
        $cache[$cacheKey] = $resultado['id'];
        return $resultado['id'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO bodegas (nombre, ubicacion, usuario_id, estado) VALUES (?, ?, ?, 1)");
    $stmt->execute([$nombre, $ubicacion, $user_id]);
    $id = $pdo->lastInsertId();
    $cache[$cacheKey] = $id;
    return $id;
}

// Función para limpiar y convertir valores monetarios
function limpiarValorMonetario($valor) {
    // Si es numérico, retornarlo directamente
    if (is_numeric($valor)) {
        return floatval($valor);
    }
    
    // Eliminar el símbolo de moneda y espacios
    $valor = trim(str_replace(['$', ' '], '', $valor));
    
    // Reemplazar la coma decimal por un marcador temporal
    $valor = str_replace(',', '#', $valor);
    
    // Eliminar todos los puntos (separadores de miles)
    $valor = str_replace('.', '', $valor);
    
    // Restaurar el punto decimal
    $valor = str_replace('#', '.', $valor);
    
    // Convertir a float
    return floatval($valor);
}

// Modificar la función validarDatosProducto para incluir la validación de bodega
function validarDatosProducto($datos) {
    $errores = [];
    
    // Validar código de barras - modificado para ser más flexible
    if (empty($datos[0])) {
        $errores[] = "Código de barras inválido: no debe estar vacío";
    } else if (strlen($datos[0]) > 20) { // Cambiamos a 20 caracteres para dar más margen
        $errores[] = "Código de barras inválido: no debe exceder 20 caracteres";
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
    $precio_costo = limpiarValorMonetario($datos[6]);
    if (!is_numeric($precio_costo) || $precio_costo <= 0) {
        $errores[] = "Precio de costo inválido: debe ser mayor que cero";
    }
    
    // Validar precio de venta
    $precio_venta = limpiarValorMonetario($datos[7]);
    if (!is_numeric($precio_venta) || $precio_venta < 0) {
        $errores[] = "Precio de venta inválido: debe ser un número positivo o cero";
    }
    
    // Validar impuesto
    if (!is_numeric($datos[8]) || $datos[8] < 0 || $datos[8] > 100) {
        $errores[] = "Impuesto inválido: debe ser un porcentaje entre 0 y 100";
    }
    
    // Validar bodega (opcional)
    if (!empty($datos[11]) && strlen($datos[11]) > 100) {
        $errores[] = "El nombre de la bodega no debe exceder los 100 caracteres";
    }

    // Validar ubicación en bodega (opcional)
    if (!empty($datos[12]) && strlen($datos[12]) > 50) {
        $errores[] = "La ubicación en bodega no debe exceder los 50 caracteres";
    }

    return $errores;
}

// Modificar la función calcularMargenGanancia para manejar correctamente los valores
function calcularMargenGanancia($precio_costo, $precio_venta, $impuesto) {
    if ($precio_costo <= 0) {
        return 0.01;
    }
    
    // Precio venta sin IVA = Precio venta / (1 + impuesto/100)
    $precio_venta_sin_iva = $precio_venta / (1 + ($impuesto/100));
    
    // Margen = ((Precio venta sin IVA - Precio costo) / Precio costo) * 100
    $margen = (($precio_venta_sin_iva - $precio_costo) / $precio_costo) * 100;
    
    return max(0.01, round($margen, 2));
}

// Agregar esta nueva función para obtener la previsualización
function obtenerPreview($archivo) {
    $spreadsheet = IOFactory::load($archivo);
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = min($worksheet->getHighestRow(), 10); // Limitamos a 10 filas para la preview
    
    $productos = [];
    for ($row = 3; $row <= $highestRow; $row++) {
        $datos = [];
        for ($col = 'A'; $col <= 'K'; $col++) {
            $datos[] = trim($worksheet->getCell($col . $row)->getValue());
        }
        
        // Saltar filas vacías
        if (empty($datos[0])) continue;
        
        $productos[] = [
            'codigo_barras' => $datos[0],
            'nombre' => $datos[1],
            'descripcion' => $datos[2],
            'stock' => $datos[3],
            'stock_minimo' => $datos[4],
            'unidad_medida' => $datos[5],
            'precio_costo' => $datos[6],
            'precio_venta' => $datos[7],
            'impuesto' => $datos[8],
            'departamento' => $datos[9],
            'categoria' => $datos[10]
        ];
    }
    
    return $productos;
}

// Modificar la parte de procesamiento del archivo para incluir modo preview
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'errors' => [], 'debug' => [], 'preview' => null];
    
    try {
        if (!isset($_FILES['archivo_excel'])) {
            throw new Exception("No se ha enviado ningún archivo");
        }

        // Validaciones iniciales del archivo
        if ($_FILES['archivo_excel']['size'] > MAX_TAMANO_ARCHIVO) {
            throw new Exception("El archivo excede el tamaño máximo permitido de " . (MAX_TAMANO_ARCHIVO / 1024 / 1024) . "MB");
        }
        
        $extension = strtolower(pathinfo($_FILES['archivo_excel']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'])) {
            throw new Exception("Formato de archivo no válido. Use .xlsx o .xls");
        }

        // Si es modo preview, devolver la previsualización
        if (isset($_POST['preview']) && $_POST['preview'] === 'true') {
            $response['preview'] = obtenerPreview($_FILES['archivo_excel']['tmp_name']);
            $response['success'] = true;
            $response['message'] = "Vista previa generada correctamente";
            echo json_encode($response);
            exit;
        }

        // Si no es preview, continuar con la importación normal
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
            departamento_id, categoria_id, bodega_id, ubicacion,
            fecha_ingreso, fecha_modificacion, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 'activo')";
        $stmtInsert = $pdo->prepare($sqlInsert);
        
        // Preparar la consulta de actualización
        $sqlUpdate = "UPDATE inventario SET 
            nombre = ?, descripcion = ?, stock = ?, stock_minimo = ?,
            unidad_medida = ?, precio_costo = ?, margen_ganancia = ?,
            impuesto = ?, precio_venta = ?, departamento_id = ?,
            categoria_id = ?, bodega_id = ?, ubicacion = ?, fecha_modificacion = NOW()
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
        $cacheBodegas = [];
        
        // Procesar cada fila
        for ($row = 3; $row <= $highestRow; $row++) {
            $datos = [];
            for ($col = 'A'; $col <= 'M'; $col++) {
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
                
                // Obtener IDs de departamento, categoría y bodega
                $departamento_id = obtenerOCrearDepartamento($pdo, $datos[9], $user_id, $cacheDepartamentos);
                $categoria_id = obtenerOCrearCategoria($pdo, $datos[10], $user_id, $cacheCategorias);
                $bodega_id = null;
                $ubicacion = null;
                if (!empty($datos[11])) {
                    $bodega_id = obtenerOCrearBodega($pdo, $datos[11], $datos[12], $user_id, $cacheBodegas);
                    $ubicacion = $datos[12];
                }
                
                // Obtener los precios limpios
                $precio_costo = limpiarValorMonetario($datos[6]);
                $precio_venta = limpiarValorMonetario($datos[7]);
                $impuesto = floatval($datos[8]);
                
                // Calcular el margen de ganancia con los valores limpios
                $margen_ganancia = calcularMargenGanancia(
                    $precio_costo,
                    $precio_venta,
                    $impuesto
                );
                
                // Verificar si el producto existe
                $stmtCheck->execute([$datos[0], $user_id]);
                $existe = $stmtCheck->fetch();
                
                if ($existe) {
                    // Actualizar producto existente
                    $stmtUpdate->execute([
                        $datos[1],            // nombre
                        $datos[2],            // descripcion
                        floatval($datos[3]),  // stock
                        floatval($datos[4]),  // stock_minimo
                        strtoupper($datos[5]),// unidad_medida
                        $precio_costo,        // precio_costo limpio
                        $margen_ganancia,     // margen_ganancia calculado
                        $impuesto,            // impuesto
                        $precio_venta,        // precio_venta limpio
                        $departamento_id,
                        $categoria_id,
                        $bodega_id,
                        $ubicacion,
                        $datos[0],            // codigo_barras
                        $user_id
                    ]);
                    $productosActualizados++;
                } else {
                    // Insertar nuevo producto
                    $stmtInsert->execute([
                        $user_id,
                        $datos[0],            // codigo_barras
                        $datos[1],            // nombre
                        $datos[2],            // descripcion
                        floatval($datos[3]),  // stock
                        floatval($datos[4]),  // stock_minimo
                        strtoupper($datos[5]),// unidad_medida
                        $precio_costo,        // precio_costo limpio
                        $margen_ganancia,     // margen_ganancia calculado
                        $impuesto,            // impuesto
                        $precio_venta,        // precio_venta limpio
                        $departamento_id,
                        $categoria_id,
                        $bodega_id,
                        $ubicacion
                    ]);
                    $productosImportados++;
                }
                
                // Si se especificó una bodega, crear la relación en inventario_bodegas
                if ($bodega_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO inventario_bodegas (bodega_id, producto_id, cantidad)
                        VALUES (?, LAST_INSERT_ID(), ?)
                        ON DUPLICATE KEY UPDATE cantidad = ?
                    ");
                    $stmt->execute([$bodega_id, floatval($datos[3]), floatval($datos[3])]);
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

                <!-- Agregar esto justo después del formulario de importación -->
                <div id="previewContainer" class="hidden mt-8">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-xl font-semibold mb-4">Vista Previa de Productos</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Costo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Venta</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departamento</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="previewTableBody">
                                    <!-- Los datos se insertarán aquí dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex justify-end space-x-4">
                            <button type="button" 
                                    onclick="cancelarImportacion()"
                                    class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                                Cancelar
                            </button>
                            <button type="button" 
                                    onclick="confirmarImportacion()"
                                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Confirmar Importación
                            </button>
                        </div>
                    </div>
                </div>
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

                // Mostrar previsualización
                mostrarPreview(file);
            }
        }

        // Función para mostrar la previsualización
        function mostrarPreview(file) {
            const formData = new FormData();
            formData.append('archivo_excel', file);
            formData.append('preview', 'true');
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success && response.preview) {
                        const tableBody = document.getElementById('previewTableBody');
                        tableBody.innerHTML = '';
                        
                        response.preview.forEach(producto => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${producto.codigo_barras}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${producto.nombre}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${producto.stock}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${producto.precio_costo}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${producto.precio_venta}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${producto.departamento}</td>
                            `;
                            tableBody.appendChild(row);
                        });
                        
                        document.getElementById('previewContainer').classList.remove('hidden');
                        document.getElementById('importForm').classList.add('hidden');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Error al generar la vista previa'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al procesar el archivo: ' + error
                    });
                }
            });
        }

        // Función para cancelar la importación
        function cancelarImportacion() {
            document.getElementById('previewContainer').classList.add('hidden');
            document.getElementById('importForm').classList.remove('hidden');
            document.getElementById('archivo_excel').value = '';
        }

        // Función para confirmar la importación
        function confirmarImportacion() {
            const file = document.getElementById('archivo_excel').files[0];
            if (file) {
                importarArchivo(file);
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
