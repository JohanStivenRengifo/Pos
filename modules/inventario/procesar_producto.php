<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => false, 'message' => 'No autorizado']));
}

try {
    $pdo->beginTransaction();

    // Validar datos básicos
    $user_id = $_SESSION['user_id'];
    $codigo_barras = trim($_POST['codigo_barras']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $categoria_id = filter_var($_POST['categoria_id'], FILTER_VALIDATE_INT);
    $departamento_id = filter_var($_POST['departamento_id'], FILTER_VALIDATE_INT);
    $unidad_medida_id = filter_var($_POST['unidad_medida'], FILTER_VALIDATE_INT);
    $estado = trim($_POST['estado']);
    $precio_costo = filter_var($_POST['precio_costo'], FILTER_VALIDATE_FLOAT);
    $precio_venta = filter_var($_POST['precio_venta'], FILTER_VALIDATE_FLOAT);
    $margen_ganancia = filter_var($_POST['margen_ganancia'], FILTER_VALIDATE_FLOAT);
    $impuesto = filter_var($_POST['impuesto'], FILTER_VALIDATE_FLOAT);

    // Validar unidad de medida
    if (!$unidad_medida_id) {
        throw new Exception("Unidad de medida inválida");
    }

    // Verificar que la unidad de medida existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM unidades_medida WHERE id = ?");
    $stmt->execute([$unidad_medida_id]);
    if ($stmt->fetchColumn() == 0) {
        throw new Exception("La unidad de medida seleccionada no existe");
    }

    // Verificar código de barras único
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE codigo_barras = ? AND user_id = ?");
    $stmt->execute([$codigo_barras, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("El código de barras ya existe");
    }

    // Insertar producto
    $query = "INSERT INTO inventario (
        user_id, codigo_barras, nombre, descripcion, stock, stock_minimo,
        unidad_medida, precio_costo, margen_ganancia, impuesto, precio_venta,
        categoria_id, departamento_id, estado, tiene_galeria, fecha_ingreso,
        fecha_modificacion
    ) VALUES (
        :user_id, :codigo_barras, :nombre, :descripcion, 0, 0,
        :unidad_medida, :precio_costo, :margen_ganancia, :impuesto, :precio_venta,
        :categoria_id, :departamento_id, :estado, :tiene_galeria, NOW(),
        NOW()
    )";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':user_id' => $user_id,
        ':codigo_barras' => $codigo_barras,
        ':nombre' => $nombre,
        ':descripcion' => $descripcion,
        ':unidad_medida' => $unidad_medida_id,
        ':precio_costo' => $precio_costo,
        ':margen_ganancia' => $margen_ganancia,
        ':impuesto' => $impuesto,
        ':precio_venta' => $precio_venta,
        ':categoria_id' => $categoria_id,
        ':departamento_id' => $departamento_id,
        ':estado' => $estado,
        ':tiene_galeria' => isset($_FILES['imagenes']) ? 1 : 0
    ]);

    $producto_id = $pdo->lastInsertId();

    // Procesar imágenes si existen
    if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
        $upload_dir = __DIR__ . '/../../uploads/productos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmp_name) {
            if (!is_uploaded_file($tmp_name)) {
                continue;
            }

            $file_name = $_FILES['imagenes']['name'][$key];
            $file_type = $_FILES['imagenes']['type'][$key];
            $file_size = $_FILES['imagenes']['size'][$key];

            // Validar tipo de archivo
            if (!in_array($file_type, ['image/jpeg', 'image/png', 'image/webp'])) {
                continue;
            }

            // Validar tamaño (25MB máximo)
            if ($file_size > 25 * 1024 * 1024) {
                continue;
            }

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_name = uniqid('prod_' . $producto_id . '_') . '.' . $file_ext;
            $destination = $upload_dir . $new_name;

            if (move_uploaded_file($tmp_name, $destination)) {
                // Insertar registro de imagen
                $query = "INSERT INTO imagenes_producto (
                    producto_id, nombre_archivo, ruta, es_principal,
                    tamano, tipo_mime, orden
                ) VALUES (
                    :producto_id, :nombre_archivo, :ruta, :es_principal,
                    :tamano, :tipo_mime, :orden
                )";

                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':producto_id' => $producto_id,
                    ':nombre_archivo' => $file_name,
                    ':ruta' => 'uploads/productos/' . $new_name,
                    ':es_principal' => $key === 0 ? 1 : 0, // Primera imagen como principal
                    ':tamano' => $file_size,
                    ':tipo_mime' => $file_type,
                    ':orden' => $key
                ]);

                // Si es la primera imagen, actualizar imagen_principal en inventario
                if ($key === 0) {
                    $stmt = $pdo->prepare("UPDATE inventario SET imagen_principal = ? WHERE id = ?");
                    $stmt->execute(['uploads/productos/' . $new_name, $producto_id]);
                }
            }
        }
    }

    // Registrar en historial
    $query = "INSERT INTO historial_productos (producto_id, user_id, tipo_cambio, detalle) 
             VALUES (:producto_id, :user_id, 'creacion', :detalle)";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':producto_id' => $producto_id,
        ':user_id' => $user_id,
        ':detalle' => json_encode([
            'codigo_barras' => $codigo_barras,
            'nombre' => $nombre,
            'precio_costo' => $precio_costo,
            'precio_venta' => $precio_venta
        ])
    ]);

    $pdo->commit();
    echo json_encode(['status' => true, 'message' => 'Producto creado exitosamente']);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error al crear producto: " . $e->getMessage());
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>