<?php
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '51M');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

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
    $query = "SELECT id, nombre FROM categorias";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerDepartamentos()
{
    global $pdo;
    $query = "SELECT id, nombre FROM departamentos";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function codigoBarrasExistente($codigo_barras)
{
    global $pdo;
    $query = "SELECT COUNT(*) FROM inventario WHERE codigo_barras = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$codigo_barras]);
    return $stmt->fetchColumn() > 0;
}

function agregarProducto($user_id, $data, $imagenes) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Usar el precio de venta proporcionado o calcularlo
        $precio_venta = $data['precio_venta'];
        if (empty($precio_venta) || $precio_venta <= 0) {
            $precio_base = $data['precio_costo'] * (1 + ($data['margen_ganancia'] / 100));
            $precio_venta = $precio_base * (1 + ($data['impuesto'] / 100));
        }
        $precio_venta = round($precio_venta, 2);
        
        // Preparar la consulta usando consultas preparadas
        $query = "INSERT INTO inventario (
            user_id, codigo_barras, nombre, descripcion, stock, 
            stock_minimo, unidad_medida, precio_costo, margen_ganancia,
            impuesto, precio_venta, categoria_id, departamento_id, 
            fecha_ingreso, estado
        ) VALUES (
            :user_id, :codigo_barras, :nombre, :descripcion, :stock,
            :stock_minimo, :unidad_medida, :precio_costo, :margen_ganancia,
            :impuesto, :precio_venta, :categoria_id, :departamento_id,
            NOW(), 'activo'
        )";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':user_id' => $user_id,
            ':codigo_barras' => $data['codigo_barras'],
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'],
            ':stock' => $data['stock'],
            ':stock_minimo' => $data['stock_minimo'],
            ':unidad_medida' => $data['unidad_medida'],
            ':precio_costo' => $data['precio_costo'],
            ':margen_ganancia' => $data['margen_ganancia'],
            ':impuesto' => $data['impuesto'],
            ':precio_venta' => $precio_venta,
            ':categoria_id' => $data['categoria_id'],
            ':departamento_id' => $data['departamento_id']
        ]);

        $producto_id = $pdo->lastInsertId();
        
        // Procesar imágenes
        if (!empty($imagenes['tmp_name'][0])) {
            $upload_dir = __DIR__ . '/../../uploads/productos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($imagenes['tmp_name'] as $key => $tmp_name) {
                if (!is_uploaded_file($tmp_name)) continue;
                
                $imagen_info = procesarImagen([
                    'name' => $imagenes['name'][$key],
                    'type' => $imagenes['type'][$key],
                    'tmp_name' => $tmp_name,
                    'size' => $imagenes['size'][$key]
                ], $upload_dir);
                
                // Guardar información de la imagen
                $stmt = $pdo->prepare("
                    INSERT INTO imagenes_producto (
                        producto_id, nombre_archivo, ruta, 
                        es_principal, tamano, tipo_mime, orden
                    ) VALUES (
                        :producto_id, :nombre_archivo, :ruta,
                        :es_principal, :tamano, :tipo_mime, :orden
                    )
                ");
                
                $stmt->execute([
                    ':producto_id' => $producto_id,
                    ':nombre_archivo' => $imagen_info['nombre'],
                    ':ruta' => $imagen_info['ruta'],
                    ':es_principal' => ($key === 0) ? 1 : 0,
                    ':tamano' => $imagen_info['tamano'],
                    ':tipo_mime' => $imagen_info['tipo'],
                    ':orden' => $key
                ]);
            }
        }
        
        // Intentar registrar en el log si la tabla existe
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'log_actividades'");
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO log_actividades (
                        user_id, tipo_actividad, descripcion, 
                        fecha_hora, ip_address
                    ) VALUES (
                        :user_id, 'crear_producto', 
                        :descripcion, NOW(), :ip_address
                    )
                ");
                
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':descripcion' => "Creación del producto: {$data['nombre']} (SKU: {$data['codigo_barras']})",
                    ':ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
            }
        } catch (Exception $e) {
            // Si hay error con el log, solo lo registramos pero no interrumpimos la operación
            error_log("Error al registrar en log_actividades: " . $e->getMessage());
        }
        
        $pdo->commit();
        return $producto_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error en agregarProducto: " . $e->getMessage());
        throw new Exception("Error al procesar el producto: " . $e->getMessage());
    }
}

function validarProducto($data) {
    $errores = [];
    
    // Validar código de barras
    if (!preg_match('/^[0-9]{8,13}$/', $data['codigo_barras'])) {
        $errores[] = "El código de barras debe tener entre 8 y 13 dígitos numéricos";
    }
    
    // Validar nombre
    if (strlen($data['nombre']) < 3 || strlen($data['nombre']) > 100) {
        $errores[] = "El nombre debe tener entre 3 y 100 caracteres";
    }
    
    // Validar precios
    if ($data['precio_costo'] <= 0) {
        $errores[] = "El precio de costo debe ser mayor a 0";
    }

    // Calcular precio de venta si no está establecido manualmente
    if (empty($data['precio_venta']) || $data['precio_venta'] <= 0) {
        $precio_base = $data['precio_costo'] * (1 + ($data['margen_ganancia'] / 100));
        $data['precio_venta'] = $precio_base * (1 + ($data['impuesto'] / 100));
    }
    
    // Validar que el precio de venta sea mayor al costo
    if ($data['precio_venta'] <= $data['precio_costo']) {
        $errores[] = "El precio de venta debe ser mayor al precio de costo";
    }
    
    if ($data['impuesto'] < 0 || $data['impuesto'] > 100) {
        $errores[] = "El impuesto debe estar entre 0% y 100%";
    }
    
    // Validar stock
    if ($data['stock'] < 0) {
        $errores[] = "El stock no puede ser negativo";
    }
    if ($data['stock_minimo'] < 0) {
        $errores[] = "El stock mínimo no puede ser negativo";
    }
    if ($data['stock_minimo'] > $data['stock']) {
        $errores[] = "El stock mínimo no puede ser mayor al stock actual";
    }
    
    return $errores;
}

function procesarImagen($file, $upload_dir) {
    try {
        $info = getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new Exception("Archivo no válido");
        }

        // Validar dimensiones (4K = 3840x2160)
        $max_width = 3840;  // 4K horizontal
        $max_height = 2160; // 4K vertical
        
        // Obtener dimensiones originales
        $width = $info[0];
        $height = $info[1];

        // Generar nombre único
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $nuevo_nombre = uniqid('prod_') . '.' . $extension;
        $ruta_destino = $upload_dir . $nuevo_nombre;

        // Procesar imagen según su tipo
        $imagen = null;
        switch ($info['mime']) {
            case 'image/jpeg':
                $imagen = imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $imagen = imagecreatefrompng($file['tmp_name']);
                // Mantener transparencia para PNG
                imagealphablending($imagen, true);
                imagesavealpha($imagen, true);
                break;
            case 'image/webp':
                $imagen = imagecreatefromwebp($file['tmp_name']);
                break;
            default:
                throw new Exception("Formato de imagen no soportado");
        }

        if (!$imagen) {
            throw new Exception("Error al procesar la imagen");
        }

        // Redimensionar solo si excede las dimensiones máximas
        if ($width > $max_width || $height > $max_height) {
            // Calcular nuevas dimensiones manteniendo proporción
            if ($width > $height) {
                $new_width = $max_width;
                $new_height = floor($height * ($max_width / $width));
            } else {
                $new_height = $max_height;
                $new_width = floor($width * ($max_height / $height));
            }

            // Crear nueva imagen redimensionada
            $imagen_redimensionada = imagecreatetruecolor($new_width, $new_height);
            
            // Mantener transparencia si es PNG
            if ($info['mime'] === 'image/png') {
                imagealphablending($imagen_redimensionada, false);
                imagesavealpha($imagen_redimensionada, true);
            }

            // Redimensionar
            imagecopyresampled(
                $imagen_redimensionada, $imagen,
                0, 0, 0, 0,
                $new_width, $new_height,
                $width, $height
            );

            $imagen = $imagen_redimensionada;
        }

        // Guardar imagen con la mejor calidad posible según el formato
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($imagen, $ruta_destino, 95); // Alta calidad (95%)
                break;
            case 'png':
                imagepng($imagen, $ruta_destino, 1); // Máxima calidad para PNG
                break;
            case 'webp':
                imagewebp($imagen, $ruta_destino, 95); // Alta calidad (95%)
                break;
        }

        // Liberar memoria
        if (isset($imagen_redimensionada)) {
            imagedestroy($imagen_redimensionada);
        }
        imagedestroy($imagen);

        return [
            'nombre' => basename($file['name']),
            'ruta' => 'uploads/productos/' . $nuevo_nombre,
            'tipo' => $info['mime'],
            'tamano' => filesize($ruta_destino),
            'ancho' => $width,
            'alto' => $height
        ];

    } catch (Exception $e) {
        error_log("Error procesando imagen: " . $e->getMessage());
        throw $e;
    }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Recopilar y sanitizar datos
        $producto_data = [
            'codigo_barras' => trim(filter_input(INPUT_POST, 'codigo_barras')),
            'nombre' => trim(filter_input(INPUT_POST, 'nombre')),
            'descripcion' => trim(filter_input(INPUT_POST, 'descripcion')),
            'stock' => filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT),
            'stock_minimo' => filter_input(INPUT_POST, 'stock_minimo', FILTER_VALIDATE_INT),
            'unidad_medida' => trim(filter_input(INPUT_POST, 'unidad_medida')),
            'precio_costo' => filter_input(INPUT_POST, 'precio_costo', FILTER_VALIDATE_FLOAT),
            'margen_ganancia' => filter_input(INPUT_POST, 'margen_ganancia', FILTER_VALIDATE_FLOAT),
            'impuesto' => filter_input(INPUT_POST, 'impuesto', FILTER_VALIDATE_FLOAT),
            'categoria_id' => filter_input(INPUT_POST, 'categoria', FILTER_VALIDATE_INT),
            'departamento_id' => filter_input(INPUT_POST, 'departamento', FILTER_VALIDATE_INT),
            'precio_venta' => filter_input(INPUT_POST, 'precio_venta', FILTER_VALIDATE_FLOAT) ?: 0
        ];

        // Validar datos
        $errores = validarProducto($producto_data);
        if (!empty($errores)) {
            throw new Exception(implode("<br>", $errores));
        }

        // Verificar código de barras duplicado
        if (codigoBarrasExistente($producto_data['codigo_barras'])) {
            throw new Exception("El código de barras ya está registrado");
        }

        // Agregar producto
        $producto_id = agregarProducto($user_id, $producto_data, $_FILES['imagenes'] ?? []);

        // Modificar la redirección para usar código de barras
        $_SESSION['success_message'] = "Producto agregado exitosamente";
        header("Location: ver.php?codigo_barras=" . urlencode($producto_data['codigo_barras']));
        exit;

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
    }
}

$categorias = obtenerCategorias();
$departamentos = obtenerDepartamentos();

function formatoMoneda($monto)
{
    return '$' . number_format($monto, 2, ',', '.');
}

// Modificar las funciones de crear categoría y departamento

function categoriaExiste($nombre) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE LOWER(nombre) = LOWER(?)");
    $stmt->execute([trim($nombre)]);
    return $stmt->fetchColumn() > 0;
}

function departamentoExiste($nombre) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departamentos WHERE LOWER(nombre) = LOWER(?)");
    $stmt->execute([trim($nombre)]);
    return $stmt->fetchColumn() > 0;
}

function crearCategoria($nombre, $user_id) {
    global $pdo;
    try {
        $nombre = trim($nombre);
        
        // Validar longitud del nombre
        if (strlen($nombre) < 3 || strlen($nombre) > 50) {
            throw new Exception("El nombre debe tener entre 3 y 50 caracteres");
        }

        // Verificar si ya existe
        if (categoriaExiste($nombre)) {
            throw new Exception("Ya existe una categoría con este nombre");
        }

        $stmt = $pdo->prepare("
            INSERT INTO categorias (
                nombre, 
                descripcion,
                estado, 
                fecha_creacion
            ) VALUES (
                ?, 
                ?, 
                'activo', 
                NOW()
            )
        ");
        
        $descripcion = "Categoría creada desde el módulo de inventario";
        $stmt->execute([$nombre, $descripcion]);
        $id = $pdo->lastInsertId();

        // Registrar en el log
        $stmt = $pdo->prepare("
            INSERT INTO log_actividades (
                user_id, 
                tipo_actividad, 
                descripcion, 
                fecha_hora, 
                ip_address
            ) VALUES (
                ?, 
                'crear_categoria', 
                ?, 
                NOW(), 
                ?
            )
        ");
        
        $stmt->execute([
            $user_id,
            "Creación de categoría: {$nombre}",
            $_SERVER['REMOTE_ADDR']
        ]);

        return [
            'id' => $id,
            'nombre' => $nombre
        ];
    } catch (Exception $e) {
        error_log("Error al crear categoría: " . $e->getMessage());
        throw new Exception("Error al crear la categoría: " . $e->getMessage());
    }
}

function crearDepartamento($nombre, $user_id) {
    global $pdo;
    try {
        $nombre = trim($nombre);
        
        // Validar longitud del nombre
        if (strlen($nombre) < 3 || strlen($nombre) > 50) {
            throw new Exception("El nombre debe tener entre 3 y 50 caracteres");
        }

        // Verificar si ya existe
        if (departamentoExiste($nombre)) {
            throw new Exception("Ya existe un departamento con este nombre");
        }

        $stmt = $pdo->prepare("
            INSERT INTO departamentos (
                nombre, 
                descripcion,
                estado, 
                fecha_creacion
            ) VALUES (
                ?, 
                ?, 
                'activo', 
                NOW()
            )
        ");
        
        $descripcion = "Departamento creado desde el módulo de inventario";
        $stmt->execute([$nombre, $descripcion]);
        $id = $pdo->lastInsertId();

        // Registrar en el log
        $stmt = $pdo->prepare("
            INSERT INTO log_actividades (
                user_id, 
                tipo_actividad, 
                descripcion, 
                fecha_hora, 
                ip_address
            ) VALUES (
                ?, 
                'crear_departamento', 
                ?, 
                NOW(), 
                ?
            )
        ");
        
        $stmt->execute([
            $user_id,
            "Creación de departamento: {$nombre}",
            $_SERVER['REMOTE_ADDR']
        ]);

        return [
            'id' => $id,
            'nombre' => $nombre
        ];
    } catch (Exception $e) {
        error_log("Error al crear departamento: " . $e->getMessage());
        throw new Exception("Error al crear el departamento: " . $e->getMessage());
    }
}

// Colocar después de la validación de sesión y antes de las funciones
if (isset($_POST['action']) && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $nombre = trim($_POST['nombre'] ?? '');
        if (empty($nombre)) {
            throw new Exception("El nombre es requerido");
        }

        switch ($_POST['action']) {
            case 'crear_categoria':
                if (categoriaExiste($nombre)) {
                    throw new Exception("Ya existe una categoría con este nombre");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO categorias (
                        nombre, 
                        descripcion,
                        estado, 
                        fecha_creacion
                    ) VALUES (?, ?, 'activo', NOW())
                ");
                
                $descripcion = "Categoría creada desde el módulo de inventario";
                if (!$stmt->execute([$nombre, $descripcion])) {
                    throw new Exception("Error al crear la categoría");
                }
                
                $id = $pdo->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $id,
                        'nombre' => $nombre
                    ]
                ]);
                exit;

            case 'crear_departamento':
                if (departamentoExiste($nombre)) {
                    throw new Exception("Ya existe un departamento con este nombre");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO departamentos (
                        nombre, 
                        descripcion,
                        estado, 
                        fecha_creacion
                    ) VALUES (?, ?, 'activo', NOW())
                ");
                
                $descripcion = "Departamento creado desde el módulo de inventario";
                if (!$stmt->execute([$nombre, $descripcion])) {
                    throw new Exception("Error al crear el departamento");
                }
                
                $id = $pdo->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $id,
                        'nombre' => $nombre
                    ]
                ]);
                exit;

            default:
                throw new Exception("Acción no válida");
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear | VendEasy</title>
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
            <h2>Crear Nuevo Producto</h2>
            <div class="promo_card">
                <h1>Agregar Producto al Inventario</h1>
                <span>Ingrese los detalles del nuevo producto.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?= strpos($message, 'exitosamente') !== false ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Formulario de Nuevo Producto</h4>
                    </div>
                    <form method="POST" action="" class="producto-form" enctype="multipart/form-data">
                        <!-- Sección de Información Básica -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-info-circle"></i> Información Básica
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="codigo_barras">Código de Barras: *</label>
                                    <div class="input-group">
                                        <input type="text" id="codigo_barras" name="codigo_barras" required 
                                               class="form-control" pattern="[0-9]{8,13}">
                                        <button type="button" class="btn btn-outline-secondary" id="generarCodigo">
                                            <i class="fas fa-barcode"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group col-md-8">
                                    <label for="nombre">Nombre del Producto: *</label>
                                    <input type="text" id="nombre" name="nombre" required class="form-control">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="descripcion">Descripción:</label>
                                    <textarea id="descripcion" name="descripcion" class="form-control" rows="3"></textarea>
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
                                    <label for="stock">Stock Inicial: *</label>
                                    <input type="number" id="stock" name="stock" min="0" required 
                                           class="form-control">
                                </div>
                                
                                <div class="form-group col-md-4">
                                    <label for="stock_minimo">Stock Mínimo: *</label>
                                    <input type="number" id="stock_minimo" name="stock_minimo" 
                                           min="0" required class="form-control">
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="unidad_medida">Unidad de Medida: *</label>
                                    <select id="unidad_medida" name="unidad_medida" required class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="UNIDAD">Unidad</option>
                                        <option value="KG">Kilogramo</option>
                                        <option value="GR">Gramo</option>
                                        <option value="LT">Litro</option>
                                        <option value="MT">Metro</option>
                                        <option value="CM">Centímetro</option>
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
                                               required class="form-control" min="0">
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="margen_ganancia">Margen (%): *</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" id="margen_ganancia" 
                                               name="margen_ganancia" required class="form-control" min="0">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="impuesto">IVA (%): *</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" id="impuesto" name="impuesto" 
                                               required class="form-control" value="19">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="precio_venta">Precio Venta:</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" id="precio_venta" name="precio_venta"
                                               class="form-control">
                                        <button type="button" id="togglePrecioManual" class="btn btn-outline-secondary">
                                            <i class="fas fa-lock"></i>
                                        </button>
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
                                    <div class="input-group">
                                        <select id="categoria" name="categoria" required class="form-control">
                                            <option value="">Seleccione una categoría...</option>
                                            <?php foreach ($categorias as $categoria): ?>
                                                <option value="<?= htmlspecialchars($categoria['id']); ?>">
                                                    <?= htmlspecialchars($categoria['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="nueva">+ Crear nueva categoría</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary" id="btnNuevaCategoria" style="display:none;">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <!-- Campo para nueva categoría -->
                                    <div id="nuevaCategoriaForm" style="display:none; margin-top:10px;">
                                        <div class="input-group">
                                            <input type="text" id="nuevaCategoria" class="form-control" 
                                                   placeholder="Nombre de la nueva categoría">
                                            <button type="button" class="btn btn-success" id="guardarCategoria">
                                                <i class="fas fa-save"></i> Guardar
                                            </button>
                                            <button type="button" class="btn btn-danger" id="cancelarCategoria">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label for="departamento">Departamento: *</label>
                                    <div class="input-group">
                                        <select id="departamento" name="departamento" required class="form-control">
                                            <option value="">Seleccione un departamento...</option>
                                            <?php foreach ($departamentos as $departamento): ?>
                                                <option value="<?= htmlspecialchars($departamento['id']); ?>">
                                                    <?= htmlspecialchars($departamento['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="nuevo">+ Crear nuevo departamento</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary" id="btnNuevoDepartamento" style="display:none;">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <!-- Campo para nuevo departamento -->
                                    <div id="nuevoDepartamentoForm" style="display:none; margin-top:10px;">
                                        <div class="input-group">
                                            <input type="text" id="nuevoDepartamento" class="form-control" 
                                                   placeholder="Nombre del nuevo departamento">
                                            <button type="button" class="btn btn-success" id="guardarDepartamento">
                                                <i class="fas fa-save"></i> Guardar
                                            </button>
                                            <button type="button" class="btn btn-danger" id="cancelarDepartamento">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Agregar después de la sección de Clasificación y antes de form-actions -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-images"></i> Galeria de Imágenes
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <div class="dropzone-container">
                                        <div id="imagePreviewContainer" class="image-preview-container"></div>
                                        <div class="dropzone-upload">
                                            <input type="file" id="imageUpload" name="imagenes[]" multiple accept="image/*" class="file-input" />
                                            <label for="imageUpload" class="upload-label">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <span>Arrastra las imágenes aquí o haz clic para seleccionar</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="image-guidelines">
                                        <small>
                                            * Formatos permitidos: JPG, PNG, WEBP<br>
                                            * Tamaño máximo por imagen: 25MB<br>
                                            * Puedes subir hasta 10 imágenes<br>
                                            * La primera imagen será la principal<br>
                                            * Las imágenes se pueden reordenar arrastrándolas<br>
                                            * Se permiten imágenes en alta resolución (4K)
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Producto
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Limpiar Formulario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Constantes
            const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB en bytes
            const MAX_FILES = 10; // Aumentado a 10 imágenes
            const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
            
            // Elementos del DOM
            const elements = {
                precioCosto: document.getElementById('precio_costo'),
                margenGanancia: document.getElementById('margen_ganancia'),
                impuesto: document.getElementById('impuesto'),
                precioVenta: document.getElementById('precio_venta'),
                imageUpload: document.getElementById('imageUpload'),
                imagePreview: document.getElementById('imagePreviewContainer'),
                dropzone: document.querySelector('.dropzone-container'),
                form: document.querySelector('.producto-form')
            };
            
            let uploadedFiles = [];

            // Calculadora de precio
            const calculadora = {
                precioManual: false,

                calcularPrecioVenta() {
                    if (this.precioManual) return;
                    
                    const precioCosto = parseFloat(elements.precioCosto.value) || 0;
                    const margenGanancia = parseFloat(elements.margenGanancia.value) || 0;
                    const impuesto = parseFloat(elements.impuesto.value) || 0;
                    
                    const precioBase = precioCosto * (1 + (margenGanancia / 100));
                    const precioVenta = precioBase * (1 + (impuesto / 100));
                    
                    elements.precioVenta.value = precioVenta.toFixed(2);
                },

                calcularMargenDesdeVenta() {
                    if (!this.precioManual) return;

                    const precioCosto = parseFloat(elements.precioCosto.value) || 0;
                    const precioVenta = parseFloat(elements.precioVenta.value) || 0;
                    const impuesto = parseFloat(elements.impuesto.value) || 0;

                    if (precioCosto <= 0 || precioVenta <= 0) return;

                    // Descontar el IVA del precio de venta
                    const precioSinIva = precioVenta / (1 + (impuesto / 100));
                    
                    // Calcular el margen
                    const margen = ((precioSinIva - precioCosto) / precioCosto) * 100;
                    elements.margenGanancia.value = margen.toFixed(2);
                },

                togglePrecioManual() {
                    this.precioManual = !this.precioManual;
                    const btn = document.getElementById('togglePrecioManual');
                    const icon = btn.querySelector('i');

                    if (this.precioManual) {
                        elements.precioVenta.readOnly = false;
                        elements.margenGanancia.readOnly = true;
                        icon.className = 'fas fa-unlock';
                        btn.title = 'Precio manual activado';
                        elements.precioVenta.focus();
                    } else {
                        elements.precioVenta.readOnly = true;
                        elements.margenGanancia.readOnly = false;
                        icon.className = 'fas fa-lock';
                        btn.title = 'Precio automático activado';
                        this.calcularPrecioVenta();
                    }
                },

                inicializar() {
                    // Configuración inicial
                    elements.precioVenta.readOnly = true;
                    
                    // Event listeners para cálculos automáticos
                    ['precioCosto', 'margenGanancia', 'impuesto'].forEach(id => {
                        elements[id].addEventListener('input', () => this.calcularPrecioVenta());
                    });

                    // Event listener para precio manual
                    elements.precioVenta.addEventListener('input', () => this.calcularMargenDesdeVenta());
                    
                    // Event listener para el botón de toggle
                    document.getElementById('togglePrecioManual').addEventListener('click', () => this.togglePrecioManual());
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
                        ${index === 0 ? '<span class="main-image">Principal</span>' : ''}
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
                    if (uploadedFiles.length + files.length > MAX_FILES) {
                        alert(`Solo puedes subir hasta ${MAX_FILES} imágenes`);
                        return;
                    }

                    for (const file of files) {
                        try {
                            this.validarArchivo(file);
                            uploadedFiles.push(file);
                        } catch (error) {
                            alert(error.message);
                            continue;
                        }
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

            // Generador de código de barras
            const barcodeGenerator = {
                inicializar() {
                    document.getElementById('generarCodigo').addEventListener('click', () => {
                        const codigo = Math.floor(Math.random() * 9000000000000) + 1000000000000;
                        document.getElementById('codigo_barras').value = codigo;
                    });
                }
            };

            // Validación del formulario
            const formValidator = {
                inicializar() {
                    elements.form.addEventListener('submit', (e) => {
                        e.preventDefault();
                        if (this.validarFormulario()) {
                            elements.form.submit();
                        }
                    });
                },

                validarFormulario() {
                    // Implementar validaciones adicionales aquí
                    return true;
                }
            };

            // Inicialización
            calculadora.inicializar();
            dragDropHandler.inicializar();
            barcodeGenerator.inicializar();
            formValidator.inicializar();

            elements.imageUpload.addEventListener('change', (e) => {
                imageHandler.manejarArchivos(e.target.files);
            });

            // Reemplazar el código de clasificacionHandler
            const clasificacionHandler = {
                async obtenerCategorias() {
                    const response = await fetch('ajax_handlers.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'obtener_categorias'
                        })
                    });
                    
                    if (!response.ok) throw new Error('Error al obtener categorías');
                    return await response.json();
                },

                async obtenerDepartamentos() {
                    const response = await fetch('ajax_handlers.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'obtener_departamentos'
                        })
                    });
                    
                    if (!response.ok) throw new Error('Error al obtener departamentos');
                    return await response.json();
                },

                async crearCategoria(nombre) {
                    const response = await fetch('ajax_handlers.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'crear_categoria',
                            nombre: nombre
                        })
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.error || 'Error al crear la categoría');
                    }
                    return data;
                },

                async crearDepartamento(nombre) {
                    const response = await fetch('ajax_handlers.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'crear_departamento',
                            nombre: nombre
                        })
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.error || 'Error al crear el departamento');
                    }
                    return data;
                },

                actualizarSelect(selectId, options, selectedValue = '') {
                    const select = document.getElementById(selectId);
                    const ultimaOpcion = select.lastElementChild.cloneNode(true);
                    select.innerHTML = '<option value="">Seleccione...</option>';
                    
                    options.forEach(option => {
                        const optionElement = new Option(option.nombre, option.id);
                        select.add(optionElement);
                    });
                    
                    select.add(ultimaOpcion);
                    if (selectedValue) select.value = selectedValue;
                },

                async recargarCategorias(selectedValue = '') {
                    try {
                        const categorias = await this.obtenerCategorias();
                        this.actualizarSelect('categoria', categorias, selectedValue);
                    } catch (error) {
                        console.error('Error al recargar categorías:', error);
                    }
                },

                async recargarDepartamentos(selectedValue = '') {
                    try {
                        const departamentos = await this.obtenerDepartamentos();
                        this.actualizarSelect('departamento', departamentos, selectedValue);
                    } catch (error) {
                        console.error('Error al recargar departamentos:', error);
                    }
                },

                inicializar() {
                    // Manejadores para categoría
                    document.getElementById('categoria').addEventListener('change', (e) => {
                        if (e.target.value === 'nueva') {
                            document.getElementById('nuevaCategoriaForm').style.display = 'block';
                            e.target.value = '';
                        }
                    });

                    document.getElementById('guardarCategoria').addEventListener('click', async () => {
                        const nombreInput = document.getElementById('nuevaCategoria');
                        const nombre = nombreInput.value.trim();
                        
                        try {
                            if (!nombre) {
                                throw new Error('El nombre de la categoría es requerido');
                            }

                            const result = await this.crearCategoria(nombre);
                            await this.recargarCategorias(result.data.id);
                            
                            // Limpiar y ocultar formulario
                            nombreInput.value = '';
                            document.getElementById('nuevaCategoriaForm').style.display = 'none';
                            
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: 'Categoría creada correctamente'
                            });
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message
                            });
                        }
                    });

                    // Manejadores para departamento
                    document.getElementById('departamento').addEventListener('change', (e) => {
                        if (e.target.value === 'nuevo') {
                            document.getElementById('nuevoDepartamentoForm').style.display = 'block';
                            e.target.value = '';
                        }
                    });

                    document.getElementById('guardarDepartamento').addEventListener('click', async () => {
                        const nombreInput = document.getElementById('nuevoDepartamento');
                        const nombre = nombreInput.value.trim();
                        
                        try {
                            if (!nombre) {
                                throw new Error('El nombre del departamento es requerido');
                            }

                            const result = await this.crearDepartamento(nombre);
                            await this.recargarDepartamentos(result.data.id);
                            
                            // Limpiar y ocultar formulario
                            nombreInput.value = '';
                            document.getElementById('nuevoDepartamentoForm').style.display = 'none';
                            
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: 'Departamento creado correctamente'
                            });
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message
                            });
                        }
                    });

                    // Manejadores para cancelar
                    document.getElementById('cancelarCategoria').addEventListener('click', () => {
                        document.getElementById('nuevaCategoria').value = '';
                        document.getElementById('nuevaCategoriaForm').style.display = 'none';
                    });

                    document.getElementById('cancelarDepartamento').addEventListener('click', () => {
                        document.getElementById('nuevoDepartamento').value = '';
                        document.getElementById('nuevoDepartamentoForm').style.display = 'none';
                    });
                }
            };

            // Agregar a la inicialización
            clasificacionHandler.inicializar();
        });
    </script>
</body>

</html>