<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Inicializar variables
$producto = null;
$error = '';
$mensaje = '';

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si se ha ingresado el código de barras
    $codigo_barras = $_POST['codigo_barras'] ?? null;

    if ($codigo_barras) {
        // Buscar el producto en la base de datos
        $query = "SELECT * FROM inventario WHERE codigo_barras = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$codigo_barras, $_SESSION['user_id']]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar si el producto fue encontrado
        if ($producto) {
            // Si se ingresan nuevas cantidades o valores, actualizar el producto
            if (isset($_POST['nueva_cantidad'], $_POST['nuevo_precio_costo'], $_POST['nuevo_precio_venta'], $_POST['nuevo_impuesto'], $_POST['nueva_descripcion'])) {
                $nueva_cantidad = (int)$_POST['nueva_cantidad'];
                $nuevo_precio_costo = (float)str_replace(',', '.', str_replace('.', '', $_POST['nuevo_precio_costo'])); // Formato de moneda
                $nuevo_impuesto = (float)$_POST['nuevo_impuesto'];
                $nueva_descripcion = $_POST['nueva_descripcion'];

                // Calcular el precio de venta si no se proporciona
                if (empty($_POST['nuevo_precio_venta'])) {
                    $nuevo_precio_venta = $nuevo_precio_costo + ($nuevo_precio_costo * ($nuevo_impuesto / 100));
                    $nuevo_precio_venta = round($nuevo_precio_venta, 2); // Redondear a 2 decimales
                } else {
                    $nuevo_precio_venta = (float)str_replace(',', '.', str_replace('.', '', $_POST['nuevo_precio_venta'])); // Formato de moneda
                }

                // Validar que los campos numéricos sean válidos
                if (!is_numeric($nueva_cantidad) || !is_numeric($nuevo_precio_costo) || !is_numeric($nuevo_precio_venta) || !is_numeric($nuevo_impuesto)) {
                    $error = "Por favor ingresa valores numéricos válidos en los campos de cantidad, precio e impuesto.";
                } else {
                    // Verificar si la cantidad es válida (no puede quedar negativa)
                    $cantidad_final = $producto['stock'] + $nueva_cantidad;
                    if ($cantidad_final < 0) {
                        $error = "La cantidad final no puede ser negativa.";
                    } else {
                        // Actualizar el producto en la base de datos
                        $updateQuery = "UPDATE inventario 
                                        SET stock = ?, precio_costo = ?, precio_venta = ?, impuesto = ?, descripcion = ? 
                                        WHERE codigo_barras = ? AND user_id = ?";
                        $updateStmt = $pdo->prepare($updateQuery);
                        $updateStmt->execute([$cantidad_final, $nuevo_precio_costo, $nuevo_precio_venta, $nuevo_impuesto, $nueva_descripcion, $codigo_barras, $_SESSION['user_id']]);

                        $mensaje = "Inventario surtido correctamente.";
                        // Volver a obtener el producto actualizado
                        $stmt->execute([$codigo_barras, $_SESSION['user_id']]);
                        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            }
        } else {
            $error = "Producto no encontrado con el código de barras proporcionado.";
        }
    } else {
        $error = "Por favor, ingresa un código de barras.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surtir Inventario</title>
    <link rel="stylesheet" href="../../css/modulos.css">
</head>
<body>

<div class="main-content">
    <h2>Surtir Inventario</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?= htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <p style="color: green;"><?= htmlspecialchars($mensaje); ?></p>
    <?php endif; ?>

    <!-- Formulario para ingresar el código de barras -->
    <form action="surtir.php" method="post">
        <div class="form-group">
            <label for="codigo_barras">Código de Barras del Producto:</label>
            <input type="text" name="codigo_barras" id="codigo_barras" required value="<?= isset($codigo_barras) ? htmlspecialchars($codigo_barras) : ''; ?>">
        </div>

        <!-- Mostrar el formulario para surtir inventario si se encontró el producto -->
        <?php if ($producto): ?>
            <h3>Producto: <?= htmlspecialchars($producto['nombre']); ?></h3>
            <p><strong>Cantidad actual en inventario:</strong> <?= htmlspecialchars($producto['stock']); ?></p>

            <div class="form-group">
                <label for="nueva_cantidad">Cantidad a añadir o restar:</label>
                <input type="number" name="nueva_cantidad" id="nueva_cantidad" required>
                <small>Puedes ingresar valores negativos para reducir el inventario.</small>
            </div>

            <div class="form-group">
                <label for="nuevo_precio_costo">Nuevo Precio Costo:</label>
                <input type="text" name="nuevo_precio_costo" id="nuevo_precio_costo" value="<?= htmlspecialchars(number_format($producto['precio_costo'], 2, ',', '.')); ?>" required>
            </div>

            <div class="form-group">
                <label for="nuevo_precio_venta">Nuevo Precio Venta:</label>
                <input type="text" name="nuevo_precio_venta" id="nuevo_precio_venta" value="<?= htmlspecialchars(number_format($producto['precio_venta'], 2, ',', '.')); ?>">
                <small>Si dejas este campo vacío, el precio de venta se calculará automáticamente.</small>
            </div>

            <div class="form-group">
                <label for="nuevo_impuesto">Nuevo Impuesto (%):</label>
                <input type="text" name="nuevo_impuesto" id="nuevo_impuesto" value="<?= htmlspecialchars($producto['impuesto']); ?>" required>
            </div>

            <div class="form-group">
                <label for="nueva_descripcion">Nueva Descripción:</label>
                <textarea name="nueva_descripcion" id="nueva_descripcion" rows="3"><?= htmlspecialchars($producto['descripcion']); ?></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Surtir Inventario</button>
            </div>
        <?php endif; ?>
    </form>

    <a href="index.php" class="btn btn-secondary">Regresar a la lista de productos</a>
</div>

</body>
</html>