<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$clientes = obtenerClientes($user_id);
$productos = obtenerProductos($user_id);

function obtenerClientes($userId)
{
    global $pdo;
    $clientes_stmt = $pdo->prepare("SELECT id, nombre FROM clientes WHERE user_id = ?");
    $clientes_stmt->execute([$userId]);
    return $clientes_stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerProductos($userId)
{
    global $pdo;
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

    <style>
        /* Estilos generales */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f2f4f8;
            color: #333;
        }

        h1,
        h2,
        h3 {
            color: #4A4A4A;
        }

        .container {
            margin-top: 20px;
        }

        .action-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }

        .action-buttons button {
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .action-buttons button:hover {
            transform: translateY(-2px);
        }

        .btn-create-client {
            background-color: #007bff;
            border: none;
            color: white;
        }

        .btn-create-product {
            background-color: #28a745;
            border: none;
            color: white;
        }

        .btn-print-ticket {
            background-color: #ff9800;
            border: none;
            color: white;
        }

        .venta-producto {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.3s ease;
        }

        .venta-producto:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .venta-producto-actions button {
            margin-left: 10px;
            background-color: #e9ecef;
            border: none;
            border-radius: 5px;
            padding: 8px;
            transition: background-color 0.2s ease;
        }

        .venta-producto-actions button:hover {
            background-color: #dee2e6;
        }

        .venta-producto p {
            margin: 0;
            font-size: 14px;
        }

        /* Responsividad */
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }

            .action-buttons button {
                width: 100%;
                margin-bottom: 10px;
            }
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
                    <div class="card h-100 shadow-sm position-relative" style="border-radius: 12px; border: 1px solid #e0e0e0;">
                        <p class="position-absolute text-secondary bg-light rounded p-1" style="top: 10px; left: 10px;"><?= $producto['codigo_barras']; ?></p>
                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <div class="text-center">
                                <div class="item-image-zone d-flex align-items-center justify-content-center mb-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icon-tag text-muted">
                                        <path d="M7.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                                        <path d="M3 6v5.172a2 2 0 0 0 .586 1.414l7.71 7.71a2.41 2.41 0 0 0 3.408 0l5.592 -5.592a2.41 2.41 0 0 0 0 -3.408l-7.71 -7.71a2 2 0 0 0 -1.414 -.586h-5.172a3 3 0 0 0 -3 3z"></path>
                                    </svg>
                                </div>
                                <p class="item-view__name text-center text-truncate font-weight-bold mb-1"><?= htmlspecialchars($producto['nombre']); ?></p>
                                <p class="item-view__quantity text-center text-muted">Inventario: <?= $producto['cantidad']; ?></p>
                            </div>
                            <p class="item-view__price text-center text-primary font-weight-bold">$<?= number_format($producto['precio'], 2); ?></p>
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