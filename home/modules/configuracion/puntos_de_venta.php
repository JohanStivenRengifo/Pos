<?php
$pdo = getPDOConnection();

// Verifica si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    die("Acceso no autorizado.");
}

// Manejar la adición de un nuevo punto de venta
if (isset($_POST['add_point_of_sale'])) {
    $nombre = $_POST['nombre'];
    $ubicacion = $_POST['ubicacion'];
    $descripcion = $_POST['descripcion'];

    if (!empty($nombre) && !empty($ubicacion)) {
        $stmt = $pdo->prepare("INSERT INTO puntos_de_venta (nombre, ubicacion, descripcion) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $ubicacion, $descripcion]);
        $success = "Punto de venta añadido exitosamente.";
    } else {
        $error = "Nombre y ubicación son obligatorios.";
    }
}

// Manejar la edición de un punto de venta
if (isset($_POST['edit_point_of_sale'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $ubicacion = $_POST['ubicacion'];
    $descripcion = $_POST['descripcion'];

    if (!empty($nombre) && !empty($ubicacion)) {
        $stmt = $pdo->prepare("UPDATE puntos_de_venta SET nombre = ?, ubicacion = ?, descripcion = ? WHERE id = ?");
        $stmt->execute([$nombre, $ubicacion, $descripcion, $id]);
        $success = "Punto de venta actualizado exitosamente.";
    } else {
        $error = "Nombre y ubicación son obligatorios.";
    }
}

// Manejar la eliminación de un punto de venta
if (isset($_POST['delete_point_of_sale'])) {
    $id = $_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM puntos_de_venta WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Punto de venta eliminado exitosamente.";
}

// Obtener la lista de puntos de venta
function getPointsOfSale($pdo) {
    $stmt = $pdo->query("SELECT * FROM puntos_de_venta ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$puntos_de_venta = getPointsOfSale($pdo);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos de Venta</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f4f4f4;
        }
        h1 {
            color: #333;
        }
        form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="submit"], button {
            padding: 10px 15px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 5px;
        }
        input[type="submit"]:hover, button:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: #fff;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            color: green;
            border: 1px solid #d4edda;
            background-color: #d4edda;
        }
        .error {
            color: red;
            border: 1px solid #f8d7da;
            background-color: #f8d7da;
        }
    </style>
</head>
<body>
    <h1>Puntos de Venta</h1>

    <!-- Mensajes de éxito o error -->
    <?php if (isset($success)): ?>
        <div class="message success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Formulario para añadir un nuevo punto de venta -->
    <form action="" method="post">
        <h2>Añadir Nuevo Punto de Venta</h2>
        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" required>

        <label for="ubicacion">Ubicación:</label>
        <input type="text" id="ubicacion" name="ubicacion" required>

        <label for="descripcion">Descripción:</label>
        <textarea id="descripcion" name="descripcion"></textarea>

        <input type="submit" name="add_point_of_sale" value="Añadir Punto de Venta">
    </form>

    <!-- Lista de puntos de venta -->
    <h2>Lista de Puntos de Venta</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Ubicación</th>
                <th>Descripción</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($puntos_de_venta as $punto): ?>
                <tr>
                    <td><?php echo htmlspecialchars($punto['id']); ?></td>
                    <td><?php echo htmlspecialchars($punto['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($punto['ubicacion']); ?></td>
                    <td><?php echo htmlspecialchars($punto['descripcion']); ?></td>
                    <td>
                        <!-- Botón para editar -->
                        <button onclick="editPointOfSale(<?php echo htmlspecialchars($punto['id']); ?>, '<?php echo htmlspecialchars($punto['nombre']); ?>', '<?php echo htmlspecialchars($punto['ubicacion']); ?>', '<?php echo htmlspecialchars($punto['descripcion']); ?>')">Editar</button>

                        <!-- Formulario para eliminar -->
                        <form action="" method="post" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($punto['id']); ?>">
                            <input type="submit" name="delete_point_of_sale" value="Eliminar" onclick="return confirm('¿Estás seguro de que quieres eliminar este punto de venta?');">
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Formulario para editar punto de venta (oculto por defecto) -->
    <div id="edit-form" style="display:none;">
        <h2>Editar Punto de Venta</h2>
        <form action="" method="post">
            <input type="hidden" id="edit_id" name="id">
            <label for="edit_nombre">Nombre:</label>
            <input type="text" id="edit_nombre" name="nombre" required>

            <label for="edit_ubicacion">Ubicación:</label>
            <input type="text" id="edit_ubicacion" name="ubicacion" required>

            <label for="edit_descripcion">Descripción:</label>
            <textarea id="edit_descripcion" name="descripcion"></textarea>

            <input type="submit" name="edit_point_of_sale" value="Actualizar Punto de Venta">
            <button type="button" onclick="hideEditForm()">Cancelar</button>
        </form>
    </div>

    <script>
        function editPointOfSale(id, nombre, ubicacion, descripcion) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_ubicacion').value = ubicacion;
            document.getElementById('edit_descripcion').value = descripcion;
            document.getElementById('edit-form').style.display = 'block';
        }

        function hideEditForm() {
            document.getElementById('edit-form').style.display = 'none';
        }
    </script>
</body>
</html>
