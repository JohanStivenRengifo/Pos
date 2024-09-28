<?php
session_start();
require_once '../../config/db.php';

// Redirigir si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Inicializar variables
$error = '';
$mensaje = '';

// Obtener todos los productos para el formulario
$query_productos = "SELECT * FROM inventario WHERE user_id = ?";
$stmt_productos = $pdo->prepare($query_productos);
$stmt_productos->execute([$_SESSION['user_id']]);
$productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos del formulario
    $descripcion = trim($_POST['descripcion']);
    $descuento = (float)$_POST['descuento'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $producto_ids = $_POST['productos'] ?? []; // Obtener los IDs de los productos seleccionados

    // Validar campos
    if (empty($descripcion) || $descuento < 0 || empty($fecha_inicio) || empty($fecha_fin) || empty($producto_ids)) {
        $error = "Por favor completa todos los campos correctamente.";
    } else {
        // Insertar la nueva promoción en la base de datos
        $query = "INSERT INTO promociones (user_id, descripcion, descuento, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id'], $descripcion, $descuento, $fecha_inicio, $fecha_fin]);

        // Obtener el ID de la última promoción insertada
        $promocion_id = $pdo->lastInsertId();

        // Asociar productos con la promoción
        $query_asociar = "INSERT INTO promociones_productos (promocion_id, producto_id) VALUES (?, ?)";
        $stmt_asociar = $pdo->prepare($query_asociar);
        foreach ($producto_ids as $producto_id) {
            $stmt_asociar->execute([$promocion_id, $producto_id]);
        }

        $mensaje = "Promoción creada exitosamente.";
        // Limpiar el formulario
        $descripcion = '';
        $descuento = '';
        $fecha_inicio = '';
        $fecha_fin = '';
        $producto_ids = [];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Promoción</title>
    <link rel="stylesheet" href="../../css/modulos.css">
</head>
<body>

<div class="main-content">
    <h2>Crear Nueva Promoción</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?= htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <p style="color: green;"><?= htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>

    <form action="crear_promocion.php" method="post">
        <div class="form-group">
            <label for="descripcion">Descripción:</label>
            <input type="text" name="descripcion" id="descripcion" required value="<?= htmlspecialchars($descripcion ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="descuento">Descuento (%):</label>
            <input type="number" name="descuento" id="descuento" required min="0" value="<?= htmlspecialchars($descuento ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="fecha_inicio">Fecha de Inicio:</label>
            <input type="date" name="fecha_inicio" id="fecha_inicio" required value="<?= htmlspecialchars($fecha_inicio ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="fecha_fin">Fecha de Fin:</label>
            <input type="date" name="fecha_fin" id="fecha_fin" required value="<?= htmlspecialchars($fecha_fin ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="productos">Selecciona Productos:</label>
            <select name="productos[]" id="productos" multiple required>
                <?php foreach ($productos as $producto): ?>
                    <option value="<?= htmlspecialchars($producto['id']); ?>">
                        <?= htmlspecialchars($producto['nombre']); ?> (Código: <?= htmlspecialchars($producto['codigo_barras']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-primary">Crear Promoción</button>
        </div>
    </form>

    <a href="promociones.php" class="btn btn-secondary">Regresar a Promociones</a>
</div>

</body>
</html>