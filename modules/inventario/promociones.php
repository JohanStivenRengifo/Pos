<?php
session_start();
require_once '../../config/db.php';

// Redirigir si el usuario no está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Inicializar promociones
$promociones = [];

// Consultar promociones
$query = "SELECT * FROM promociones WHERE user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$promociones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lógica para agregar, editar o eliminar promociones podría ir aquí
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promociones</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <style>
        /* Estilos adicionales para mejorar la tabla */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .btn {
            background-color: #4CAF50; /* Color verde */
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
    </style>
</head>
<body>

<div class="main-content">
    <h2>Promociones</h2>

    <div class="button-group">
        <a href="crear_promocion.php" class="btn">Agregar Nueva Promoción</a>
    </div>

    <?php if (count($promociones) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Descripción</th>
                    <th>Descuento (%)</th>
                    <th>Fecha de Inicio</th>
                    <th>Fecha de Fin</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promociones as $promocion): ?>
                    <tr>
                        <td><?= htmlspecialchars($promocion['id']); ?></td>
                        <td><?= htmlspecialchars($promocion['descripcion']); ?></td>
                        <td><?= htmlspecialchars($promocion['descuento']); ?></td>
                        <td><?= htmlspecialchars(date('d-m-Y', strtotime($promocion['fecha_inicio']))); ?></td>
                        <td><?= htmlspecialchars(date('d-m-Y', strtotime($promocion['fecha_fin']))); ?></td>
                        <td>
                            <a href="editar_promocion.php?id=<?= htmlspecialchars($promocion['id']); ?>" class="btn">Editar</a>
                            <a href="eliminar_promocion.php?id=<?= htmlspecialchars($promocion['id']); ?>" class="btn" onclick="return confirm('¿Estás seguro de que deseas eliminar esta promoción?');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay promociones disponibles.</p>
    <?php endif; ?>
</div>

</body>
</html>