<?php
$pdo = getPDOConnection();

// Procesar solicitud POST para agregar un producto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_bodega = filter_input(INPUT_POST, 'codigo_bodega', FILTER_SANITIZE_NUMBER_INT);
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_SPECIAL_CHARS);
    $precio_costo = filter_input(INPUT_POST, 'precio_costo', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $impuestos = filter_input(INPUT_POST, 'impuestos', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_SANITIZE_NUMBER_INT);
    $codigo_barras = filter_input(INPUT_POST, 'codigo_barras', FILTER_SANITIZE_SPECIAL_CHARS);
    $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_SPECIAL_CHARS);

    // Calcular el precio de venta basado en el precio de costo y el impuesto
    $impuesto_porcentaje = (float)$impuestos;
    $precio_venta = $precio_costo * 1.20; // Aumento del 20% sobre el precio de costo
    $precio_venta += $precio_venta * ($impuesto_porcentaje / 100); // Añadir impuesto

    $stmt = $pdo->prepare("INSERT INTO productos (codigo_bodega, nombre, precio_costo, impuestos, precio_venta, cantidad, codigo_barras, descripcion) VALUES (:codigo_bodega, :nombre, :precio_costo, :impuestos, :precio_venta, :cantidad, :codigo_barras, :descripcion)");
    $stmt->execute([
        'codigo_bodega' => $codigo_bodega,
        'nombre' => $nombre,
        'precio_costo' => $precio_costo,
        'impuestos' => $impuestos,
        'precio_venta' => $precio_venta,
        'cantidad' => $cantidad,
        'codigo_barras' => $codigo_barras,
        'descripcion' => $descripcion
    ]);

    echo "<p>Producto agregado con éxito.</p>";
}

// Obtener productos
$stmt = $pdo->query("SELECT * FROM productos");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener bodegas para el dropdown
$stmt_bodegas = $pdo->query("SELECT * FROM bodegas");
$bodegas = $stmt_bodegas->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
    /* Estilo general */
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-color: #f4f4f9;
    }

    /* Estilo para encabezados */
    h2,
    h3 {
        color: #333;
        margin-top: 0;
        margin-bottom: 10px;
    }

    /* Estilo para formularios */
    form {
        background-color: #ffffff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    /* Estilo para etiquetas en formularios */
    form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #555;
    }

    /* Estilo para campos de entrada en formularios */
    form input[type="text"],
    form input[type="number"],
    form select,
    form textarea {
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        width: 100%;
        margin-bottom: 15px;
        box-sizing: border-box;
        font-size: 16px;
    }

    /* Estilo para el botón del formulario */
    form button {
        background-color: #007bff;
        color: #ffffff;
        border: none;
        padding: 12px 24px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.2s;
        font-size: 16px;
        display: inline-block;
    }

    form button:hover {
        background-color: #0056b3;
        transform: scale(1.05);
    }

    /* Estilo para la tabla */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background-color: #ffffff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    /* Estilo para los encabezados y celdas de la tabla */
    table th,
    table td {
        padding: 16px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    table th {
        background-color: #007bff;
        color: #ffffff;
        font-weight: 700;
    }

    table tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    table tr:hover {
        background-color: #e2e6ea;
    }

    /* Estilo para botones en la tabla */
    table button {
        background-color: #dc3545;
        color: #ffffff;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.2s;
        font-size: 14px;
    }

    table button:hover {
        background-color: #c82333;
        transform: scale(1.05);
    }

    table .update-button {
        background-color: #28a745;
    }

    table .update-button:hover {
        background-color: #218838;
    }

    table .delete-button {
        background-color: #dc3545;
    }

    table .delete-button:hover {
        background-color: #c82333;
    }

    /* Estilo responsivo */
    @media (max-width: 768px) {

        form input[type="text"],
        form input[type="number"],
        form select,
        form textarea {
            font-size: 14px;
        }

        form button {
            padding: 10px 20px;
            font-size: 14px;
        }

        table {
            font-size: 14px;
        }

        table th,
        table td {
            padding: 10px;
        }
    }

    @media (max-width: 480px) {
        form {
            padding: 15px;
        }

        form input[type="text"],
        form input[type="number"],
        form select,
        form textarea {
            font-size: 13px;
        }

        form button {
            padding: 8px 14px;
            font-size: 13px;
        }

        table {
            font-size: 12px;
        }

        table th,
        table td {
            padding: 8px;
        }
    }
</style>
<form action="?module=Inventarios&submodule=productos" method="post">
    <label for="codigo_bodega">Bodega:</label>
    <select id="codigo_bodega" name="codigo_bodega" required>
        <option value="">Seleccionar Bodega</option>
        <?php foreach ($bodegas as $bodega): ?>
            <option value="<?php echo htmlspecialchars($bodega['id']); ?>"><?php echo htmlspecialchars($bodega['nombre']); ?></option>
        <?php endforeach; ?>
    </select>

    <label for="nombre">Nombre:</label>
    <input type="text" id="nombre" name="nombre" required>

    <label for="precio_costo">Precio Costo:</label>
    <input type="number" step="0.01" id="precio_costo" name="precio_costo" required>

    <label for="impuestos">Impuestos (%):</label>
    <select id="impuestos" name="impuestos" required>
        <option value="5">5%</option>
        <option value="10">10%</option>
        <option value="15">15%</option>
        <option value="19">19%</option>
        <option value="30">30%</option>
    </select>

    <label for="cantidad">Cantidad:</label>
    <input type="number" id="cantidad" name="cantidad" required>

    <label for="codigo_barras">Código de Barras:</label>
    <input type="text" id="codigo_barras" name="codigo_barras" required>

    <label for="descripcion">Descripción:</label>
    <textarea id="descripcion" name="descripcion" rows="4" required></textarea>

    <button type="submit">Agregar Producto</button>
</form>

<h3>Listado de Productos</h3>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Bodega</th>
            <th>Nombre</th>
            <th>Precio Costo</th>
            <th>Impuestos</th>
            <th>Precio Venta</th>
            <th>Cantidad</th>
            <th>Código de Barras</th>
            <th>Descripción</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($productos as $producto): ?>
            <tr>
                <td><?php echo htmlspecialchars($producto['id']); ?></td>
                <td><?php echo htmlspecialchars($producto['codigo_bodega']); ?></td>
                <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                <td><?php echo htmlspecialchars($producto['precio_costo']); ?></td>
                <td><?php echo htmlspecialchars($producto['impuestos']); ?></td>
                <td><?php echo htmlspecialchars($producto['precio_venta']); ?></td>
                <td><?php echo htmlspecialchars($producto['cantidad']); ?></td>
                <td><?php echo htmlspecialchars($producto['codigo_barras']); ?></td>
                <td><?php echo htmlspecialchars($producto['descripcion']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>