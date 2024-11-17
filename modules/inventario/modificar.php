<?php
ini_set('upload_max_filesize', '25M');
ini_set('post_max_size', '26M');
ini_set('memory_limit', '256M');

session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

define('MAX_FILES', 10); // Máximo de imágenes permitidas por producto

function obtenerCategorias()
{
    global $pdo;
    $query = "SELECT id, nombre FROM categorias WHERE estado = 'activo'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerDepartamentos()
{
    global $pdo;
    $query = "SELECT id, nombre FROM departamentos WHERE estado = 'activo'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerProducto($codigo_barras, $user_id)
{
    global $pdo;
    $query = "SELECT i.*, 
                     GROUP_CONCAT(DISTINCT ip.id) as imagen_ids,
                     GROUP_CONCAT(DISTINCT ip.ruta) as imagen_rutas,
                     GROUP_CONCAT(DISTINCT ip.es_principal) as imagen_principales,
                     GROUP_CONCAT(DISTINCT ip.nombre_archivo) as imagen_nombres,
                     GROUP_CONCAT(DISTINCT ip.tamano) as imagen_tamanos,
                     GROUP_CONCAT(DISTINCT ip.tipo_mime) as imagen_tipos
              FROM inventario i
              LEFT JOIN imagenes_producto ip ON i.id = ip.producto_id
              WHERE i.codigo_barras = ? AND i.user_id = ?
              GROUP BY i.id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$codigo_barras, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function obtenerImagenesProducto($producto_id)
{
    global $pdo;
    $query = "SELECT * FROM imagenes_producto 
              WHERE producto_id = ? 
              ORDER BY es_principal DESC, orden ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$producto_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function actualizarProducto($id, $codigo_barras, $nombre, $descripcion, $stock, $precio_costo, $impuesto, $margen_ganancia, $categoria_id, $departamento_id, $stock_minimo, $unidad_medida, $imagenes_nuevas, $imagenes_eliminar, $user_id, $precio_venta = null)
{
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Si no se proporciona precio_venta, calcularlo
        if ($precio_venta === null) {
            $precio_base = $precio_costo * (1 + ($margen_ganancia / 100));
            $precio_venta = $precio_base * (1 + ($impuesto / 100));
            $precio_venta = round($precio_venta, 2);
        }
        
        // Actualizar producto
        $query = "UPDATE inventario SET 
                    codigo_barras = :codigo_barras,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    stock = :stock,
                    stock_minimo = :stock_minimo,
                    unidad_medida = :unidad_medida,
                    precio_costo = :precio_costo,
                    margen_ganancia = :margen_ganancia,
                    impuesto = :impuesto,
                    precio_venta = :precio_venta,
                    categoria_id = :categoria_id,
                    departamento_id = :departamento_id,
                    fecha_modificacion = NOW()
                 WHERE id = :id AND user_id = :user_id";
        
        $stmt = $pdo->prepare($query);
        $success = $stmt->execute([
            ':codigo_barras' => $codigo_barras,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':stock' => $stock,
            ':stock_minimo' => $stock_minimo,
            ':unidad_medida' => $unidad_medida,
            ':precio_costo' => $precio_costo,
            ':margen_ganancia' => $margen_ganancia,
            ':impuesto' => $impuesto,
            ':precio_venta' => $precio_venta,
            ':categoria_id' => $categoria_id,
            ':departamento_id' => $departamento_id,
            ':id' => $id,
            ':user_id' => $user_id
        ]);

        if (!$success) {
            throw new Exception("Error al actualizar el producto");
        }

        // Eliminar imágenes marcadas
        if (!empty($imagenes_eliminar)) {
            $imagenes_eliminar = json_decode($imagenes_eliminar, true);
            foreach ($imagenes_eliminar as $imagen_id) {
                // Obtener ruta de la imagen antes de eliminar
                $query = "SELECT ruta FROM imagenes_producto WHERE id = ? AND producto_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$imagen_id, $id]);
                $imagen = $stmt->fetch();

                if ($imagen) {
                    // Eliminar archivo físico
                    $ruta_completa = __DIR__ . '/../../' . $imagen['ruta'];
                    if (file_exists($ruta_completa)) {
                        unlink($ruta_completa);
                    }

                    // Eliminar registro de la base de datos
                    $query = "DELETE FROM imagenes_producto WHERE id = ? AND producto_id = ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$imagen_id, $id]);
                }
            }
        }

        // Procesar nuevas imágenes
        if (isset($imagenes_nuevas) && is_array($imagenes_nuevas) && !empty($imagenes_nuevas['tmp_name'][0])) {
            $upload_dir = __DIR__ . '/../../uploads/productos/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Error al crear el directorio de uploads");
                }
                chmod($upload_dir, 0777);
            }

            foreach ($imagenes_nuevas['tmp_name'] as $key => $tmp_name) {
                if (!is_uploaded_file($tmp_name)) {
                    continue;
                }

                $file_name = $imagenes_nuevas['name'][$key];
                $file_type = $imagenes_nuevas['type'][$key];
                $file_size = $imagenes_nuevas['size'][$key];

                if (!in_array($file_type, ['image/jpeg', 'image/png', 'image/webp'])) {
                    continue;
                }

                if ($file_size > 25 * 1024 * 1024) {
                    continue;
                }

                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_name = uniqid('prod_' . $id . '_') . '.' . $file_ext;
                $destination = $upload_dir . $new_name;

                if (!move_uploaded_file($tmp_name, $destination)) {
                    throw new Exception("Error al mover el archivo: " . $file_name);
                }

                $query = "INSERT INTO imagenes_producto (
                    producto_id, nombre_archivo, ruta, es_principal,
                    tamano, tipo_mime, orden
                ) VALUES (
                    :producto_id,
                    :nombre_archivo,
                    :ruta,
                    :es_principal,
                    :tamano,
                    :tipo_mime,
                    :orden
                )";

                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':producto_id' => $id,
                    ':nombre_archivo' => $file_name,
                    ':ruta' => 'uploads/productos/' . $new_name,
                    ':es_principal' => 0,
                    ':tamano' => $file_size,
                    ':tipo_mime' => $file_type,
                    ':orden' => $key
                ]);
            }
        }

        // Actualizar tiene_galeria basado en la existencia de imágenes
        $query = "SELECT COUNT(*) FROM imagenes_producto WHERE producto_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id]);
        $tiene_galeria = (int)($stmt->fetchColumn() > 0);

        $query = "UPDATE inventario SET tiene_galeria = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tiene_galeria, $id]);

        // Si no hay imágenes, limpiar imagen_principal
        if ($tiene_galeria === 0) {
            $query = "UPDATE inventario SET imagen_principal = NULL WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$id]);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error en actualizarProducto: " . $e->getMessage());
        throw new Exception("Error al actualizar el producto: " . $e->getMessage());
    }
}

// Verificar si se ha proporcionado un ID válido
$producto = null;
$message = '';
$messageType = '';

if (isset($_GET['codigo_barras'])) {
    $codigo_barras = trim($_GET['codigo_barras']);
    if (!empty($codigo_barras)) {
        $producto = obtenerProducto($codigo_barras, $user_id);
        if (!$producto) {
            $message = "Producto no encontrado.";
            $messageType = "error";
        }
    } else {
        $message = "Código de barras inválido.";
        $messageType = "error";
    }
} else {
    $message = "No se proporcionó un código de barras.";
    $messageType = "error";
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar'])) {
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $codigo_barras = trim(filter_input(INPUT_POST, 'codigo_barras'));
        
        // Verificar si el código de barras ya existe para otro producto
        if ($codigo_barras !== $producto['codigo_barras']) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE codigo_barras = ? AND id != ?");
            $stmt->execute([$codigo_barras, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("El código de barras ya está en uso por otro producto.");
            }
        }

        // Obtener los valores del formulario
        $nombre = trim(filter_input(INPUT_POST, 'nombre'));
        $descripcion = trim(filter_input(INPUT_POST, 'descripcion'));
        $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
        $precio_costo = filter_input(INPUT_POST, 'precio_costo', FILTER_VALIDATE_FLOAT);
        $impuesto = filter_input(INPUT_POST, 'impuesto', FILTER_VALIDATE_FLOAT);
        $margen_ganancia = filter_input(INPUT_POST, 'margen_ganancia', FILTER_VALIDATE_FLOAT);
        $categoria_id = filter_input(INPUT_POST, 'categoria', FILTER_VALIDATE_INT);
        $departamento_id = filter_input(INPUT_POST, 'departamento', FILTER_VALIDATE_INT);
        $stock_minimo = filter_input(INPUT_POST, 'stock_minimo', FILTER_VALIDATE_INT);
        $unidad_medida = trim(filter_input(INPUT_POST, 'unidad_medida'));
        $imagenes_eliminar = $_POST['imagenes_eliminar'] ?? null;
        $precio_venta = filter_input(INPUT_POST, 'precio_venta', FILTER_VALIDATE_FLOAT);

        if (!$id || empty($nombre) || $stock === false || $precio_costo === false) {
            throw new Exception("Por favor, complete todos los campos obligatorios correctamente.");
        }

        actualizarProducto(
            $id,
            $codigo_barras,
            $nombre,
            $descripcion,
            $stock,
            $precio_costo,
            $impuesto,
            $margen_ganancia,
            $categoria_id,
            $departamento_id,
            $stock_minimo,
            $unidad_medida,
            $_FILES['imagenes'] ?? [],
            $imagenes_eliminar,
            $user_id,
            $precio_venta
        );

        // Mostrar mensaje de éxito y redirigir
        $_SESSION['message'] = "Producto actualizado exitosamente.";
        $_SESSION['message_type'] = "success";
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
        error_log("Error al actualizar producto: " . $e->getMessage());
    }
}

// Agregar mensaje de depuración
if (!$producto) {
    error_log("Debug - Código de barras: " . ($codigo_barras ?? 'no proporcionado'));
    error_log("Debug - User ID: " . $user_id);
}

$categorias = obtenerCategorias();
$departamentos = obtenerDepartamentos();
$imagenes = $producto ? obtenerImagenesProducto($producto['id']) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Producto | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-body p-4">
            <!-- Encabezado con breadcrumbs -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 mb-2">Modificar Producto</h1>
                        <nav class="text-gray-500 text-sm">
                            <ol class="list-none p-0 inline-flex">
                                <li class="flex items-center">
                                    <a href="../inventario" class="hover:text-blue-600">Inventario</a>
                                    <svg class="w-3 h-3 mx-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"/>
                                    </svg>
                                </li>
                                <li class="text-gray-700">Modificar Producto</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Última modificación:</p>
                        <p class="text-sm font-medium"><?= date('d/m/Y H:i', strtotime($producto['fecha_modificacion'])) ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="<?= $messageType === 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700' ?> p-4 mb-6 rounded-r">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <?php if ($messageType === 'success'): ?>
                                <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                                </svg>
                            <?php else: ?>
                                <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?= htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($producto): ?>
                <form method="POST" action="" class="space-y-6" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($producto['id']) ?>">
                    <input type="hidden" name="actualizar" value="1">
                    <input type="hidden" name="imagenes_eliminar" id="imagenes_eliminar" value="">

                    <!-- Tarjeta de Información Básica -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                            <h2 class="text-lg font-medium text-gray-900 flex items-center">
                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                Información Básica
                            </h2>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Código de Barras -->
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Código de Barras *
                                </label>
                                <div class="flex rounded-md shadow-sm">
                                    <input type="text" 
                                           id="codigo_barras" 
                                           name="codigo_barras"
                                           value="<?= htmlspecialchars($producto['codigo_barras']) ?>"
                                           class="flex-1 min-w-0 block w-full px-3 py-2 rounded-l-md border border-gray-300 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                           required>
                                    <button type="button" 
                                            class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 text-sm font-medium rounded-r-md text-gray-700 bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            id="generarCodigo">
                                        <i class="fas fa-barcode"></i>
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">El código debe tener entre 8 y 13 dígitos</p>
                            </div>

                            <!-- Nombre del Producto -->
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre del Producto *
                                </label>
                                <input type="text" 
                                       id="nombre" 
                                       name="nombre"
                                       value="<?= htmlspecialchars($producto['nombre']) ?>"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                       required>
                            </div>

                            <!-- Descripción -->
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Descripción
                                </label>
                                <textarea id="descripcion" 
                                          name="descripcion"
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                          placeholder="Describe las características del producto..."><?= htmlspecialchars($producto['descripcion']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta de Inventario -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                            <h2 class="text-lg font-medium text-gray-900 flex items-center">
                                <i class="fas fa-box text-blue-500 mr-2"></i>
                                Control de Inventario
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Stock Actual -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Stock Actual *
                                    </label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="number"
                                               id="stock"
                                               name="stock"
                                               value="<?= htmlspecialchars($producto['stock']) ?>"
                                               class="block w-full pr-10 border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                               required
                                               min="0">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">
                                                unid
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stock Mínimo -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Stock Mínimo *
                                    </label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="number"
                                               id="stock_minimo"
                                               name="stock_minimo"
                                               value="<?= htmlspecialchars($producto['stock_minimo']) ?>"
                                               class="block w-full pr-10 border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                               required
                                               min="0">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">
                                                unid
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Unidad de Medida -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Unidad de Medida *
                                    </label>
                                    <select id="unidad_medida"
                                            name="unidad_medida"
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md sm:text-sm"
                                            required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach (['UNIDAD', 'KG', 'GR', 'LT', 'MT', 'CM'] as $unidad): ?>
                                            <option value="<?= $unidad ?>" 
                                                <?= $producto['unidad_medida'] === $unidad ? 'selected' : '' ?>>
                                                <?= $unidad ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Indicador de Stock -->
                            <div class="mt-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Nivel de Stock</span>
                                    <span class="text-sm text-gray-500">
                                        <?= $producto['stock'] ?> de <?= max($producto['stock'], $producto['stock_minimo'] * 2) ?>
                                    </span>
                                </div>
                                <?php
                                $stockLevel = ($producto['stock'] / max($producto['stock_minimo'] * 2, 1)) * 100;
                                $barColor = $stockLevel > 50 ? 'bg-green-500' : ($stockLevel > 25 ? 'bg-yellow-500' : 'bg-red-500');
                                ?>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="<?= $barColor ?> h-2 rounded-full" style="width: <?= min($stockLevel, 100) ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta de Precios -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                            <h2 class="text-lg font-medium text-gray-900 flex items-center">
                                <i class="fas fa-dollar-sign text-blue-500 mr-2"></i>
                                Información de Precios
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <!-- Precio Costo -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Precio Costo *
                                    </label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input type="number" 
                                               step="0.01" 
                                               id="precio_costo" 
                                               name="precio_costo"
                                               value="<?= htmlspecialchars($producto['precio_costo']) ?>"
                                               class="block w-full pl-7 pr-12 border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                               required>
                                    </div>
                                </div>

                                <!-- Margen de Ganancia -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Margen (%) *
                                    </label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="number" 
                                               step="0.01" 
                                               id="margen_ganancia" 
                                               name="margen_ganancia"
                                               value="<?= htmlspecialchars($producto['margen_ganancia']) ?>"
                                               class="block w-full pr-10 border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                               required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">%</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- IVA -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        IVA (%) *
                                    </label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input type="number" 
                                               step="0.01" 
                                               id="impuesto" 
                                               name="impuesto"
                                               value="<?= htmlspecialchars($producto['impuesto']) ?>"
                                               class="block w-full pr-10 border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                               required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">%</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Precio Venta -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Precio Venta
                                    </label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input type="number" 
                                               step="0.01" 
                                               id="precio_venta" 
                                               name="precio_venta"
                                               value="<?= htmlspecialchars($producto['precio_venta']) ?>"
                                               class="block w-full pl-7 pr-12 border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- Indicador de Margen -->
                            <div class="mt-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Margen de Ganancia</span>
                                    <span class="text-sm text-gray-500">
                                        <?= number_format($producto['margen_ganancia'], 2) ?>%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" 
                                         style="width: <?= min($producto['margen_ganancia'], 100) ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta de Clasificación -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                            <h2 class="text-lg font-medium text-gray-900 flex items-center">
                                <i class="fas fa-tags text-blue-500 mr-2"></i>
                                Clasificación
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Categoría -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Categoría *
                                    </label>
                                    <select id="categoria" 
                                            name="categoria"
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md sm:text-sm"
                                            required>
                                        <option value="">Seleccione una categoría...</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?= htmlspecialchars($categoria['id']) ?>" 
                                                <?= $producto['categoria_id'] == $categoria['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($categoria['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Departamento -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Departamento *
                                    </label>
                                    <select id="departamento" 
                                            name="departamento"
                                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md sm:text-sm"
                                            required>
                                        <option value="">Seleccione un departamento...</option>
                                        <?php foreach ($departamentos as $departamento): ?>
                                            <option value="<?= htmlspecialchars($departamento['id']) ?>" 
                                                <?= $producto['departamento_id'] == $departamento['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($departamento['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta de Galería de Imágenes -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                            <h2 class="text-lg font-medium text-gray-900 flex items-center">
                                <i class="fas fa-images text-blue-500 mr-2"></i>
                                Galería de Imágenes
                            </h2>
                        </div>
                        <div class="p-6">
                            <!-- Contador de Imágenes -->
                            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700">
                                        Imágenes actuales: <span class="font-bold"><?= count($imagenes) ?></span>
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        Máximo: <?= MAX_FILES ?> imágenes
                                    </span>
                                </div>
                            </div>

                            <!-- Imagen Principal -->
                            <?php if ($producto['imagen_principal']): ?>
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-4">
                                    Imagen Principal
                                </label>
                                <div class="relative group">
                                    <div class="aspect-w-16 aspect-h-9 w-full overflow-hidden rounded-lg bg-gray-200">
                                        <img src="../../<?= htmlspecialchars($producto['imagen_principal']) ?>" 
                                             alt="Imagen principal"
                                             class="h-full w-full object-cover object-center">
                                        <div class="absolute top-2 left-2">
                                            <span class="px-2 py-1 bg-blue-500 text-white text-xs rounded-md">
                                                Principal
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Galería de Imágenes -->
                            <?php if ($producto['tiene_galeria']): ?>
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-4">
                                    Galería de Imágenes
                                </label>
                                <div id="imagenesExistentes" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                    <?php foreach ($imagenes as $imagen): ?>
                                        <div class="relative group" data-id="<?= $imagen['id'] ?>">
                                            <div class="aspect-w-1 aspect-h-1 w-full overflow-hidden rounded-lg bg-gray-200">
                                                <img src="../../<?= htmlspecialchars($imagen['ruta']) ?>" 
                                                     alt="<?= htmlspecialchars($imagen['nombre_archivo']) ?>"
                                                     class="h-full w-full object-cover object-center">
                                                
                                                <!-- Overlay con acciones -->
                                                <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center space-x-2">
                                                    <button type="button" 
                                                            onclick="marcarImagenParaEliminar(<?= $imagen['id'] ?>)"
                                                            class="p-2 bg-red-500 text-white rounded-full hover:bg-red-600 focus:outline-none">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                    <?php if (!$imagen['es_principal']): ?>
                                                        <button type="button"
                                                                onclick="establecerImagenPrincipal(<?= $imagen['id'] ?>)"
                                                                class="p-2 bg-blue-500 text-white rounded-full hover:bg-blue-600 focus:outline-none">
                                                            <i class="fas fa-star"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Indicadores -->
                                                <div class="absolute top-2 left-2 flex flex-col gap-1">
                                                    <?php if ($imagen['es_principal']): ?>
                                                        <span class="px-2 py-1 bg-blue-500 text-white text-xs rounded-md">
                                                            Principal
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="px-2 py-1 bg-gray-800 bg-opacity-75 text-white text-xs rounded-md">
                                                        <?= number_format($imagen['tamano'] / (1024 * 1024), 2) ?> MB
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Subir Nuevas Imágenes -->
                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-4">
                                    Agregar Nuevas Imágenes
                                </label>
                                <div class="dropzone-container border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-blue-500 transition-colors">
                                    <div class="text-center">
                                        <div class="mt-1 flex justify-center">
                                            <div class="space-y-1 text-center">
                                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                                <div class="flex text-sm text-gray-600">
                                                    <label for="imageUpload" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                        <span>Seleccionar archivos</span>
                                                        <input id="imageUpload" 
                                                               name="imagenes[]" 
                                                               type="file" 
                                                               multiple 
                                                               accept="image/*" 
                                                               class="sr-only">
                                                    </label>
                                                    <p class="pl-1">o arrastrar y soltar</p>
                                                </div>
                                                <p class="text-xs text-gray-500">
                                                    PNG, JPG, WEBP hasta <?= MAX_FILES ?> archivos
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="imagePreviewContainer" class="mt-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                        <!-- Las previsualizaciones se agregarán aquí dinámicamente -->
                                    </div>
                                </div>
                            </div>

                            <!-- Guía de Imágenes -->
                            <div class="mt-4 bg-blue-50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-blue-800 mb-2">Guía para las imágenes:</h4>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Formatos permitidos: JPG, PNG, WEBP
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Tamaño máximo por imagen: 25MB
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Máximo <?= MAX_FILES ?> imágenes por producto
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        La primera imagen será la principal por defecto
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex justify-end space-x-3 bg-gray-50 px-6 py-4 rounded-lg">
                        <a href="index.php" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                No se encontró el producto
                            </h3>
                            <p class="mt-2 text-sm text-red-700">
                                El producto solicitado no existe o no tienes permisos para modificarlo.
                            </p>
                            <div class="mt-4">
                                <a href="index.php" class="text-sm font-medium text-red-800 hover:text-red-900">
                                    Volver al inventario <span aria-hidden="true">&rarr;</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Constantes
        const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB
        const MAX_FILES = <?= MAX_FILES ?>; // Usar la constante PHP
        const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
        let imagenesToDelete = [];
        let uploadedFiles = [];
        
        // Elementos del DOM
        const dropZone = document.querySelector('.dropzone-container');
        const fileInput = document.getElementById('imageUpload');
        const previewContainer = document.getElementById('imagePreviewContainer');
        const imagenesEliminarInput = document.getElementById('imagenes_eliminar');

        // Función para manejar la eliminación de imágenes existentes
        window.marcarImagenParaEliminar = function(imagenId) {
            Swal.fire({
                title: '¿Eliminar imagen?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const imagen = document.querySelector(`[data-id="${imagenId}"]`);
                    if (imagen) {
                        imagen.style.opacity = '0.5';
                        imagen.style.pointerEvents = 'none';
                    imagenesToDelete.push(imagenId);
                        imagenesEliminarInput.value = JSON.stringify(imagenesToDelete);
                    actualizarContadorImagenes();
                    }
                }
            });
        };

        // Función para establecer imagen principal
        window.establecerImagenPrincipal = function(imagenId) {
            Swal.fire({
                title: '¿Establecer como principal?',
                text: "Esta será la imagen destacada del producto",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, establecer',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('imagen_id', imagenId);
                    formData.append('producto_id', document.querySelector('[name="id"]').value);

                    fetch('actualizar_imagen_principal.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                                location.reload();
                            } else {
                            throw new Error(data.message || 'Error al actualizar');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', error.message, 'error');
                    });
                }
            });
        };

        // Función para validar archivos
        function validarArchivo(file) {
            if (!ALLOWED_TYPES.includes(file.type)) {
                throw new Error(`Tipo de archivo no permitido: ${file.type}`);
            }
            if (file.size > MAX_FILE_SIZE) {
                throw new Error(`El archivo excede ${MAX_FILE_SIZE/1024/1024}MB`);
            }
        }

        // Función para crear preview de imagen
        function crearPreviewImagen(file) {
            const reader = new FileReader();
            const preview = document.createElement('div');
            preview.className = 'relative group';

            reader.onload = function(e) {
                preview.innerHTML = `
                    <div class="aspect-w-1 aspect-h-1 w-full overflow-hidden rounded-lg bg-gray-200">
                        <img src="${e.target.result}" class="h-full w-full object-cover object-center">
                        <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <button type="button" class="p-2 bg-red-500 text-white rounded-full hover:bg-red-600 focus:outline-none">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                        <div class="absolute top-2 left-2">
                            <span class="px-2 py-1 bg-gray-800 bg-opacity-75 text-white text-xs rounded-md">
                                ${(file.size / (1024 * 1024)).toFixed(2)} MB
                            </span>
                        </div>
                    </div>
                `;

                preview.querySelector('button').onclick = function() {
                    const index = uploadedFiles.indexOf(file);
                    if (index > -1) {
                        uploadedFiles.splice(index, 1);
                        preview.remove();
                        actualizarContadorImagenes();
                    }
                };
            };

            reader.readAsDataURL(file);
            return preview;
        }

        // Función para actualizar contador de imágenes
        function actualizarContadorImagenes() {
            const imagenesExistentes = document.querySelectorAll('#imagenesExistentes .relative:not([style*="opacity: 0.5"])').length;
            const imagenesNuevas = uploadedFiles.length;
            const total = imagenesExistentes + imagenesNuevas;
            
            document.querySelector('.text-sm.font-medium.text-gray-700 span').textContent = total;
        }

        // Event Listeners para Drag & Drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
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

        // Manejar archivos soltados
        dropZone.addEventListener('drop', handleDrop, false);
        fileInput.addEventListener('change', handleFiles, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles({ target: { files: files } });
        }

        function handleFiles(e) {
            const files = [...e.target.files];
            const imagenesExistentes = document.querySelectorAll('#imagenesExistentes .relative:not([style*="opacity: 0.5"])').length;
            
            if (files.length + imagenesExistentes + uploadedFiles.length > MAX_FILES) {
                Swal.fire('Error', `Solo puedes tener hasta ${MAX_FILES} imágenes en total`, 'error');
                return;
            }

            files.forEach(file => {
                try {
                    validarArchivo(file);
                    uploadedFiles.push(file);
                    previewContainer.appendChild(crearPreviewImagen(file));
                    actualizarContadorImagenes();
                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                }
            });
        }

        // Inicialización
        actualizarContadorImagenes();
    });
    </script>
</body>
</html>