<?php
require_once '../config/db_connection.php';
$pdo = getPDOConnection();

// Procesar formulario de agregar, editar y eliminar bodega
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_SPECIAL_CHARS);
    $ubicacion = filter_input(INPUT_POST, 'ubicacion', FILTER_SANITIZE_SPECIAL_CHARS);
    $capacidad = filter_input(INPUT_POST, 'capacidad', FILTER_VALIDATE_INT);
    $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_SPECIAL_CHARS);

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Agregar nueva bodega
            $stmt = $pdo->prepare("INSERT INTO bodegas (nombre, ubicacion, capacidad, descripcion) VALUES (:nombre, :ubicacion, :capacidad, :descripcion)");
            $stmt->execute(['nombre' => $nombre, 'ubicacion' => $ubicacion, 'capacidad' => $capacidad, 'descripcion' => $descripcion]);
            $message = "<p>Bodega agregada con éxito.</p>";
        } elseif ($_POST['action'] === 'edit') {
            // Editar bodega
            $stmt = $pdo->prepare("UPDATE bodegas SET nombre = :nombre, ubicacion = :ubicacion, capacidad = :capacidad, descripcion = :descripcion WHERE id = :id");
            $stmt->execute(['nombre' => $nombre, 'ubicacion' => $ubicacion, 'capacidad' => $capacidad, 'descripcion' => $descripcion, 'id' => $id]);
            $message = "<p>Bodega actualizada con éxito.</p>";
        } elseif ($_POST['action'] === 'delete') {
            // Eliminar bodega
            $stmt = $pdo->prepare("DELETE FROM bodegas WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $message = "<p>Bodega eliminada con éxito.</p>";
        }
    }
}

// Obtener bodegas
$stmt = $pdo->query("SELECT * FROM bodegas");
$bodegas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Bodegas - Sistema Contable</title>
    <link rel="stylesheet" href="../../css/home.css">
    <style>
        /* Estilo general */
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f8f9fa;
        }

        h2,
        h3 {
            color: #333;
        }

        /* Estilo para formularios */
        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        form input[type="text"],
        form input[type="number"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
            max-width: 300px;
            margin-bottom: 10px;
        }

        form button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 16px;
            display: inline-block;
        }

        form button:hover {
            background-color: #0056b3;
        }

        /* Estilo para la tabla */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background-color: #007bff;
            color: white;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tr:hover {
            background-color: #e9ecef;
        }

        table button {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-size: 14px;
        }

        table button:hover {
            background-color: #c82333;
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
            form input[type="number"] {
                max-width: 100%;
            }

            table {
                font-size: 14px;
            }

            table th,
            table td {
                padding: 8px;
            }

            form button {
                padding: 8px 16px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            form {
                padding: 15px;
            }

            form input[type="text"],
            form input[type="number"] {
                font-size: 14px;
            }

            form button {
                padding: 8px 12px;
                font-size: 14px;
            }

            table {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 6px;
            }
        }
    </style>
</head>

<body>
    <h2>Bodegas</h2>
    <?php if (isset($message)): ?>
        <div><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Formulario para agregar nueva bodega -->
    <form action="?module=Inventarios" method="post">
        <input type="hidden" name="action" value="add">
        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" required>
        <label for="ubicacion">Dirección:</label>
        <input type="text" id="ubicacion" name="ubicacion" required>
        <label for="capacidad">Capacidad:</label>
        <input type="number" id="capacidad" name="capacidad" required>
        <label for="descripcion">Descripción:</label>
        <input type="text" id="descripcion" name="descripcion">
        <button type="submit">Agregar Bodega</button>
    </form>

    <h3>Listado de Bodegas</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Capacidad</th>
                <th>Descripción</th>
                <th>Fecha Creación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bodegas as $bodega): ?>
                <tr>
                    <td><?php echo htmlspecialchars($bodega['id'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($bodega['nombre'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($bodega['ubicacion'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($bodega['capacidad'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($bodega['descripcion'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($bodega['fecha_creacion'] ?? ''); ?></td>
                    <td>
                        <!-- Formulario para editar bodega -->
                        <form action="?module=Inventarios" method="post" style="display:inline;">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($bodega['id'] ?? ''); ?>">
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($bodega['nombre'] ?? ''); ?>" required>
                            <input type="text" name="ubicacion" value="<?php echo htmlspecialchars($bodega['ubicacion'] ?? ''); ?>" required>
                            <input type="number" name="capacidad" value="<?php echo htmlspecialchars($bodega['capacidad'] ?? ''); ?>" required>
                            <input type="text" name="descripcion" value="<?php echo htmlspecialchars($bodega['descripcion'] ?? ''); ?>">
                            <button type="submit" class="update-button">Actualizar</button>
                        </form>
                        <!-- Formulario para eliminar bodega -->
                        <form action="?module=Inventarios" method="post" style="display:inline;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($bodega['id'] ?? ''); ?>">
                            <button type="submit" class="delete-button" onclick="return confirm('¿Estás seguro de eliminar esta bodega?');">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>