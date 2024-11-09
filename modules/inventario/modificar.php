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
                     GROUP_CONCAT(DISTINCT ip.es_principal) as imagen_principales
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
                    producto_id, 
                    nombre_archivo, 
                    ruta, 
                    es_principal,
                    tamano,
                    tipo_mime,
                    orden
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

        // Registrar en historial
        $query = "INSERT INTO historial_productos (producto_id, user_id, tipo_cambio, detalle) 
                 VALUES (:producto_id, :user_id, 'modificacion', :detalle)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':producto_id' => $id,
            ':user_id' => $user_id,
            ':detalle' => json_encode([
                'codigo_barras' => $codigo_barras,
                'nombre' => $nombre,
                'stock' => $stock,
                'precio_costo' => $precio_costo,
                'precio_venta' => $precio_venta
            ])
        ]);

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
            $precio_venta  // Pasar el precio_venta como parámetro
        );

        $message = "Producto actualizado exitosamente.";
        $messageType = "success";
        
        // Recargar producto para mostrar cambios
        $producto = obtenerProducto($codigo_barras, $user_id);
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
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <style>
        .producto-form {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
            margin-bottom: 15px;
        }

        .form-group {
            padding: 10px;
            margin-bottom: 15px;
        }

        .col-md-2 { width: 16.66%; }
        .col-md-3 { width: 25%; }
        .col-md-4 { width: 33.33%; }
        .col-md-6 { width: 50%; }
        .col-md-12 { width: 100%; }

        .input-group {
            display: flex;
            align-items: center;
        }

        .input-group-text {
            background: #f8f9fa;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-right: none;
            border-radius: 4px 0 0 4px;
        }

        .input-group input {
            border-radius: 0 4px 4px 0;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            transition: border-color 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        .form-section {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }

        .form-section-title {
            margin: -15px -15px 15px -15px;
            padding: 10px 15px;
            background: #007bff;
            color: white;
            border-radius: 4px 4px 0 0;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn i {
            font-size: 16px;
        }

        .btn-primary {
            background: #007bff;
            color: white;
            border: none;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .dropzone-container {
            border: 2px dashed #ccc;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .dropzone-container.dragover {
            background: #e9ecef;
            border-color: #007bff;
        }

        .dropzone-upload {
            position: relative;
            min-height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-input {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            color: #6c757d;
        }

        .upload-label i {
            font-size: 2em;
        }

        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .image-preview {
            position: relative;
            width: 150px;
            height: 150px;
            border-radius: 4px;
            overflow: hidden;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-preview .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #dc3545;
        }

        .image-preview .main-image {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 3px;
            padding: 2px 5px;
            font-size: 0.8em;
            color: #28a745;
        }

        .image-guidelines {
            margin-top: 10px;
            color: #6c757d;
        }

        .image-preview .image-size {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 3px;
            padding: 2px 5px;
            font-size: 0.8em;
            color: #6c757d;
        }

        .dragover {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }

        .image-preview {
            transition: transform 0.2s ease;
        }

        .image-preview:hover {
            transform: scale(1.05);
        }

        .remove-image:hover {
            background: rgba(255, 255, 255, 1);
            color: #dc3545;
        }

        .image-counter {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
            text-align: center;
        }

        .image-counter small {
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
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
                <a href="/welcome.php">Dashboard</a>
                <a href="/modules/pos/index.php">POS</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php" class="active">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="#">Ayuda</a>
                    <a href="#">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Modificar Producto</h2>
            <div class="promo_card">
                <h1>Actualizar Información del Producto</h1>
                <span>Modifique los detalles del producto seleccionado.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($producto): ?>
                <div class="history_lists">
                    <div class="list1">
                        <form method="POST" action="" class="producto-form" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($producto['id']) ?>">
                            <input type="hidden" name="actualizar" value="1">
                            <input type="hidden" name="imagenes_eliminar" id="imagenes_eliminar" value="">

                            <!-- Sección de Información Básica -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-info-circle"></i> Información Básica
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="codigo_barras">Código de Barras: *</label>
                                        <div class="input-group">
                                            <input type="text" id="codigo_barras" name="codigo_barras" 
                                                   value="<?= htmlspecialchars($producto['codigo_barras']) ?>" 
                                                   required class="form-control" pattern="[0-9]{8,13}">
                                            <button type="button" class="btn btn-outline-secondary" id="generarCodigo">
                                                <i class="fas fa-barcode"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group col-md-8">
                                        <label for="nombre">Nombre del Producto: *</label>
                                        <input type="text" id="nombre" name="nombre" 
                                               value="<?= htmlspecialchars($producto['nombre']) ?>" 
                                               required class="form-control">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label for="descripcion">Descripción:</label>
                                        <textarea id="descripcion" name="descripcion" class="form-control" 
                                                  rows="3"><?= htmlspecialchars($producto['descripcion']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Sección de Inventario -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-box"></i> Información de Inventario
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="stock">Stock: *</label>
                                        <input type="number" id="stock" name="stock" 
                                               value="<?= htmlspecialchars($producto['stock']) ?>" 
                                               required class="form-control" min="0">
                                    </div>
                                    
                                    <div class="form-group col-md-4">
                                        <label for="stock_minimo">Stock Mínimo: *</label>
                                        <input type="number" id="stock_minimo" name="stock_minimo" 
                                               value="<?= htmlspecialchars($producto['stock_minimo']) ?>" 
                                               required class="form-control" min="0">
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label for="unidad_medida">Unidad de Medida: *</label>
                                        <select id="unidad_medida" name="unidad_medida" required class="form-control">
                                            <option value="">Seleccione...</option>
                                            <?php
                                            $unidades = ['UNIDAD', 'KG', 'GR', 'LT', 'MT', 'CM'];
                                            foreach ($unidades as $unidad): ?>
                                                <option value="<?= $unidad ?>" 
                                                    <?= $producto['unidad_medida'] === $unidad ? 'selected' : '' ?>>
                                                    <?= $unidad ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Sección de Precios -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-dollar-sign"></i> Información de Precios
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="precio_costo">Precio Costo: *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" id="precio_costo" name="precio_costo" 
                                                   value="<?= htmlspecialchars($producto['precio_costo']) ?>" 
                                                   required class="form-control" min="0">
                                        </div>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label for="margen_ganancia">Margen (%): *</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" id="margen_ganancia" name="margen_ganancia" 
                                                   value="<?= htmlspecialchars($producto['margen_ganancia']) ?>" 
                                                   required class="form-control" min="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label for="impuesto">IVA (%): *</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" id="impuesto" name="impuesto" 
                                                   value="<?= htmlspecialchars($producto['impuesto']) ?>" 
                                                   required class="form-control">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label for="precio_venta">Precio Venta:</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" 
                                                   step="0.01" 
                                                   id="precio_venta" 
                                                   name="precio_venta"
                                                   value="<?= htmlspecialchars($producto['precio_venta']) ?>" 
                                                   class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sección de Clasificación -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-tags"></i> Clasificación
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="categoria">Categoría: *</label>
                                        <select id="categoria" name="categoria" required class="form-control">
                                            <option value="">Seleccione una categoría...</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?= htmlspecialchars($categoria['id']) ?>" 
                                                    <?= $producto['categoria_id'] == $categoria['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($categoria['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label for="departamento">Departamento: *</label>
                                        <select id="departamento" name="departamento" required class="form-control">
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

                            <!-- Sección de Imágenes -->
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-images"></i> Galería de Imágenes
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <!-- Mostrar imágenes existentes -->
                                        <div id="imagenesExistentes" class="image-preview-container">
                                            <?php foreach ($imagenes as $imagen): ?>
                                                <div class="image-preview" data-id="<?= $imagen['id'] ?>">
                                                    <img src="../../<?= htmlspecialchars($imagen['ruta']) ?>" 
                                                         alt="Imagen del producto">
                                                    <span class="remove-image" onclick="marcarImagenParaEliminar(<?= $imagen['id'] ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </span>
                                                    <?php if ($imagen['es_principal']): ?>
                                                        <span class="main-image">Principal</span>
                                                    <?php endif; ?>
                                                    <span class="image-size">
                                                        <?= round($imagen['tamano'] / (1024 * 1024), 2) ?>MB
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Subir nuevas imágenes -->
                                        <div class="dropzone-container">
                                            <div id="imagePreviewContainer" class="image-preview-container"></div>
                                            <div class="dropzone-upload">
                                                <input type="file" id="imageUpload" name="imagenes[]" 
                                                       multiple accept="image/*" class="file-input" />
                                                <label for="imageUpload" class="upload-label">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span>Arrastra nuevas imágenes aquí o haz clic para seleccionar</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="image-guidelines">
                                            <small>
                                                * Formatos permitidos: JPG, PNG, WEBP<br>
                                                * Tamaño máximo por imagen: 25MB<br>
                                                * Puedes tener hasta 10 imágenes en total<br>
                                                * La primera imagen será la principal<br>
                                                * Se permiten imágenes en alta resolución (4K)
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    No se encontró el producto o no tiene permisos para modificarlo.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Constantes
        const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB
        const MAX_FILES = 10;
        const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
        let imagenesToDelete = [];
        
        // Elementos del DOM
        const elements = {
            precioCosto: document.getElementById('precio_costo'),
            margenGanancia: document.getElementById('margen_ganancia'),
            impuesto: document.getElementById('impuesto'),
            precioVenta: document.getElementById('precio_venta'),
            imageUpload: document.getElementById('imageUpload'),
            imagePreview: document.getElementById('imagePreviewContainer'),
            dropzone: document.querySelector('.dropzone-container'),
            form: document.querySelector('.producto-form'),
            imagenesEliminar: document.getElementById('imagenes_eliminar')
        };

        let uploadedFiles = [];

        // Calculadora de precio
        const calculadora = {
            calcularPrecioVenta() {
                const precioCosto = parseFloat(elements.precioCosto.value) || 0;
                const margenGanancia = parseFloat(elements.margenGanancia.value) || 0;
                const impuesto = parseFloat(elements.impuesto.value) || 0;
                
                if (precioCosto <= 0) return;
                
                // Solo calcular si no hay un precio de venta establecido
                if (!elements.precioVenta.value) {
                    const precioBase = precioCosto * (1 + (margenGanancia / 100));
                    const precioVenta = precioBase * (1 + (impuesto / 100));
                    elements.precioVenta.value = precioVenta.toFixed(2);
                }
            },

            calcularMargenDesdeVenta() {
                const precioCosto = parseFloat(elements.precioCosto.value) || 0;
                const precioVenta = parseFloat(elements.precioVenta.value) || 0;
                const impuesto = parseFloat(elements.impuesto.value) || 0;

                if (precioCosto <= 0 || precioVenta <= 0) return;

                // Calcular el margen necesario para llegar al precio de venta exacto
                const precioSinImpuesto = precioVenta / (1 + (impuesto / 100));
                const margen = ((precioSinImpuesto / precioCosto) - 1) * 100;
                
                // Actualizar solo el margen, manteniendo el precio de venta intacto
                elements.margenGanancia.value = margen.toFixed(2);
            },
            
            inicializar() {
                // Remover el atributo readonly del campo precio_venta
                elements.precioVenta.removeAttribute('readonly');

                // Eventos para cálculo automático
                elements.precioCosto.addEventListener('input', () => {
                    if (!elements.precioVenta.value) {
                        this.calcularPrecioVenta();
                    } else {
                        this.calcularMargenDesdeVenta();
                    }
                });

                elements.margenGanancia.addEventListener('input', () => {
                    if (!elements.precioVenta.value) {
                        this.calcularPrecioVenta();
                    }
                });

                elements.impuesto.addEventListener('input', () => {
                    if (!elements.precioVenta.value) {
                        this.calcularPrecioVenta();
                    } else {
                        this.calcularMargenDesdeVenta();
                    }
                });
                
                // Evento para calcular el margen cuando se modifica el precio de venta
                elements.precioVenta.addEventListener('input', () => {
                    this.calcularMargenDesdeVenta();
                });
            }
        };

        // Manejador de imágenes
        const imageHandler = {
            validarArchivo(file) {
                if (!ALLOWED_TYPES.includes(file.type)) {
                    throw new Error(`Tipo de archivo no permitido. Use: ${ALLOWED_TYPES.map(t => t.split('/')[1]).join(', ')}`);
                }
                if (file.size > MAX_FILE_SIZE) {
                    throw new Error(`El archivo excede el tamaño máximo de ${MAX_FILE_SIZE/1024/1024}MB`);
                }
            },

            async procesarArchivo(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = e => resolve(e.target.result);
                    reader.onerror = () => reject(new Error('Error al leer el archivo'));
                    reader.readAsDataURL(file);
                });
            },

            crearPreview(src, index) {
                const div = document.createElement('div');
                div.className = 'image-preview';
                div.innerHTML = `
                    <img src="${src}" alt="Preview ${index + 1}">
                    <span class="remove-image" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </span>
                    <span class="image-size">${(uploadedFiles[index].size / (1024 * 1024)).toFixed(2)}MB</span>
                `;
                return div;
            },

            async actualizarPreviews() {
                elements.imagePreview.innerHTML = '';
                for (let i = 0; i < uploadedFiles.length; i++) {
                    try {
                        const src = await this.procesarArchivo(uploadedFiles[i]);
                        const preview = this.crearPreview(src, i);
                        elements.imagePreview.appendChild(preview);
                        
                        preview.querySelector('.remove-image').addEventListener('click', () => {
                            uploadedFiles.splice(i, 1);
                            this.actualizarPreviews();
                        });
                    } catch (error) {
                        console.error('Error al procesar imagen:', error);
                    }
                }
            },

            async manejarArchivos(files) {
                // Obtener el número actual de imágenes existentes (no marcadas para eliminar)
                const imagenesExistentes = document.querySelectorAll('#imagenesExistentes .image-preview:not([style*="display: none"])').length;
                
                // Calcular el total de imágenes después de agregar las nuevas
                const totalImagenes = imagenesExistentes + uploadedFiles.length + files.length;
                
                if (totalImagenes > MAX_FILES) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Límite de imágenes excedido',
                        text: `Solo puedes tener hasta ${MAX_FILES} imágenes en total. Actualmente tienes ${imagenesExistentes} imagen(es) y estás intentando agregar ${files.length} más.`,
                        footer: `Puedes eliminar algunas imágenes existentes antes de agregar nuevas.`
                    });
                    return;
                }

                let errores = [];
                for (const file of files) {
                    try {
                        this.validarArchivo(file);
                        uploadedFiles.push(file);
                    } catch (error) {
                        errores.push(`${file.name}: ${error.message}`);
                    }
                }

                if (errores.length > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Algunas imágenes no se pudieron agregar',
                        html: errores.join('<br>'),
                        footer: 'Solo se agregaron las imágenes válidas'
                    });
                }

                await this.actualizarPreviews();
            }
        };

        // Manejador de Drag & Drop
        const dragDropHandler = {
            inicializar() {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(event => {
                    elements.dropzone.addEventListener(event, this.prevenirDefecto);
                    document.body.addEventListener(event, this.prevenirDefecto);
                });

                ['dragenter', 'dragover'].forEach(event => {
                    elements.dropzone.addEventListener(event, () => elements.dropzone.classList.add('dragover'));
                });

                ['dragleave', 'drop'].forEach(event => {
                    elements.dropzone.addEventListener(event, () => elements.dropzone.classList.remove('dragover'));
                });

                elements.dropzone.addEventListener('drop', e => {
                    imageHandler.manejarArchivos(e.dataTransfer.files);
                });
            },

            prevenirDefecto(e) {
                e.preventDefault();
                e.stopPropagation();
            }
        };

        // Marcar imágenes para eliminar
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
                    imagenesToDelete.push(imagenId);
                    elements.imagenesEliminar.value = JSON.stringify(imagenesToDelete);
                    document.querySelector(`.image-preview[data-id="${imagenId}"]`).style.display = 'none';
                    actualizarContadorImagenes();
                }
            });
        };

        // Establecer imagen principal
        window.establecerImagenPrincipal = function(imagenId) {
            Swal.fire({
                title: '¿Establecer como principal?',
                text: "Esta imagen será la que se muestre primero",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, establecer',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'actualizar_imagen_principal.php',
                        method: 'POST',
                        data: {
                            producto_id: document.querySelector('[name="id"]').value,
                            imagen_id: imagenId
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo actualizar la imagen principal', 'error');
                        }
                    });
                }
            });
        };

        // Validación del formulario
        const formValidator = {
            inicializar() {
                elements.form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    if (this.validarFormulario()) {
                        Swal.fire({
                            title: '¿Guardar cambios?',
                            text: "¿Está seguro de actualizar la información del producto?",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Sí, guardar',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                elements.form.submit();
                            }
                        });
                    }
                });
            },

            validarFormulario() {
                // Contar imágenes existentes no marcadas para eliminar
                const imagenesExistentes = document.querySelectorAll('#imagenesExistentes .image-preview:not([style*="display: none"])').length;
                // Contar nuevas imágenes a subir
                const imagenesNuevas = uploadedFiles.length;
                const totalImagenes = imagenesExistentes + imagenesNuevas;

                if (totalImagenes > MAX_FILES) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Demasiadas imágenes',
                        text: `El número total de imágenes (${totalImagenes}) supera el límite permitido de ${MAX_FILES}.`,
                        footer: 'Elimina algunas imágenes antes de guardar.'
                    });
                    return false;
                }

                if (totalImagenes === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin imágenes',
                        text: 'El producto debe tener al menos una imagen.',
                        footer: 'Agrega al menos una imagen antes de guardar.'
                    });
                    return false;
                }

                return true;
            }
        };

        // Agregar un contador visual de imágenes (opcional)
        function actualizarContadorImagenes() {
            const imagenesExistentes = document.querySelectorAll('#imagenesExistentes .image-preview:not([style*="display: none"])').length;
            const imagenesNuevas = uploadedFiles.length;
            const total = imagenesExistentes + imagenesNuevas;
            
            const contadorHtml = `
                <div class="image-counter">
                    <small>
                        Imágenes: ${total}/${MAX_FILES}
                        (${imagenesExistentes} existentes + ${imagenesNuevas} nuevas)
                    </small>
                </div>
            `;
            
            // Actualizar el contador en el DOM
            const contadorElement = document.querySelector('.image-counter') || document.createElement('div');
            contadorElement.innerHTML = contadorHtml;
            document.querySelector('.image-guidelines').insertAdjacentElement('beforebegin', contadorElement);
        }

        // Llamar a la función cada vez que cambie el número de imágenes
        elements.imageUpload.addEventListener('change', () => {
            setTimeout(actualizarContadorImagenes, 100);
        });

        // También actualizar cuando se elimine una imagen
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
                    imagenesToDelete.push(imagenId);
                    elements.imagenesEliminar.value = JSON.stringify(imagenesToDelete);
                    document.querySelector(`.image-preview[data-id="${imagenId}"]`).style.display = 'none';
                    actualizarContadorImagenes();
                }
            });
        };

        // Inicialización
        calculadora.inicializar();
        dragDropHandler.inicializar();
        formValidator.inicializar();

        elements.imageUpload.addEventListener('change', (e) => {
            imageHandler.manejarArchivos(e.target.files);
        });

        // Ordenamiento de imágenes (opcional)
        if (typeof Sortable !== 'undefined') {
            new Sortable(document.getElementById('imagenesExistentes'), {
                animation: 150,
                onEnd: function(evt) {
                    const imageIds = Array.from(evt.to.children).map(el => el.dataset.id);
                    $.ajax({
                        url: 'reordenar_imagenes.php',
                        method: 'POST',
                        data: { orden: imageIds },
                        success: function(response) {
                            if (!response.success) {
                                Swal.fire('Error', 'No se pudo actualizar el orden', 'error');
                            }
                        }
                    });
                }
            });
        }
    });
    </script>
</body>
</html>