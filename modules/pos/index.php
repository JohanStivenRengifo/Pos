<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado, si no redirigir al login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Obtener los clientes del usuario actual
$clientes = obtenerClientes($user_id);

// Obtener los productos del inventario del usuario actual
$productos = obtenerProductos($user_id);

function obtenerClientes($userId)
{
    global $pdo; // Asegurarte de que $pdo está disponible
    $clientes_stmt = $pdo->prepare("SELECT id, nombre FROM clientes WHERE user_id = ?");
    $clientes_stmt->execute([$userId]);
    return $clientes_stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerProductos($userId)
{
    global $pdo; // Asegurarte de que $pdo está disponible
    $productos_stmt = $pdo->prepare("SELECT id, nombre, precio_venta AS precio, stock AS cantidad, codigo_barras FROM inventario WHERE user_id = ?");
    $productos_stmt->execute([$userId]);
    return $productos_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Realizar Venta</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f9fc;
        }

        .action-buttons {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }

        .action-buttons button {
            padding: 10px;
            border-radius: 5px;
            color: white;
            text-align: center;
        }

        .btn-create-client {
            background-color: #007bff;
        }

        .btn-create-product {
            background-color: #28a745;
        }

        .btn-print-ticket {
            background-color: #ff9800;
        }

        .venta-producto {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .venta-producto-actions button {
            margin-left: 10px;
        }
    </style>
</head>

<body>

    <div class="container">
        <h3 class="text-center">Realizar Venta</h3>
        <div class="alert alert-warning" id="alerta" style="display: none;" role="alert"></div>

        <!-- Selección de cliente -->
        <div class="form-group">
            <label for="cliente-select">Selecciona un cliente</label>
            <select id="cliente-select" class="form-control">
                <option value="">Selecciona un cliente</option>
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?= $cliente['id']; ?>" <?= ($cliente['nombre'] === 'Consumidor Final') ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($cliente['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Campo de búsqueda de productos -->
        <div class="form-group">
            <input type="text" id="buscar-producto" class="form-control" placeholder="Buscar producto por nombre o código de barras...">
        </div>

        <!-- Grid de productos -->
        <div class="row" id="products-grid">
            <?php foreach ($productos as $producto): ?>
                <div class="col-md-3 col-sm-6 col-xs-12 mb-4 product-card"
                    data-id="<?= $producto['id']; ?>"
                    data-nombre="<?= htmlspecialchars($producto['nombre']); ?>"
                    data-precio="<?= $producto['precio']; ?>"
                    data-cantidad="<?= $producto['cantidad']; ?>"
                    data-codigo="<?= $producto['codigo_barras']; ?>">

                    <div class="card h-100 shadow-sm position-relative" style="border-radius: 10px;">
                        <p class="position-absolute text-primary bg-white rounded p-1" style="top: 10px; left: 10px;"><?= $producto['codigo_barras']; ?></p>

                        <button type="button" class="position-absolute btn button-transparent p-0" style="top: 10px; right: 10px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon-star" style="color: #ffc107;">
                                <path d="M12 17.75l-6.172 3.245l1.179 -6.873l-5 -4.867l6.9 -1l3.086 -6.253l3.086 6.253l6.9 1l-5 4.867l1.179 6.873z"></path>
                            </svg>
                        </button>

                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <div class="text-center">
                                <div class="item-image-zone d-flex align-items-center justify-content-center mb-3">
                                    <!-- Icono de producto genérico -->
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon-tag" style="color: #6c757d;">
                                        <path d="M7.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                        <path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3z"></path>
                                    </svg>
                                </div>

                                <p class="item-view__name text-center text-truncate font-weight-bold"><?= htmlspecialchars($producto['nombre']); ?></p>
                                <p class="item-view__quantity text-center mb-2">Inventario: <?= $producto['cantidad']; ?></p>
                            </div>
                            <p class="item-view__price text-center text-success">$<?= number_format($producto['precio'], 2); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Detalles de la venta -->
        <div class="venta-sidebar">
            <h4>Factura de venta</h4>
            <div id="venta-lista" class="mb-3"></div>
            <div class="venta-total" id="venta-total"><strong>Total: $0.00</strong></div>
            <button class="venta-boton" id="venta-boton" aria-label="Vender productos" title="Vender productos">Vender</button>
        </div>

        <script src="./script.js" defer></script>

</body>

</html>