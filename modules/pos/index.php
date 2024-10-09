<?php
session_start();
require_once '../../config/db.php';
require_once './functions.php';

// Verifica si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Obtener datos de clientes y productos
$clientes = obtenerClientes($pdo, $user_id);
$productos = obtenerProductos($pdo, $user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Realizar Venta</title>
    <link rel="stylesheet" href="../../css/pos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container-fluid">
        <!-- Contenedor de alertas -->
        <div id="alertContainer" class="alert-container"></div>
        
        <div class="row">
            <!-- Panel izquierdo: Lista de productos -->
            <div class="col-md-8 left-panel">
                <div class="search-bar">
                    <input type="text" id="buscar-producto" class="form-control" placeholder="Buscar producto por nombre o código de barras...">
                </div>
                <div id="products-grid">
                    <?php foreach ($productos as $producto): ?>
                        <div class="product-card"
                             data-id="<?= $producto['id']; ?>"
                             data-nombre="<?= htmlspecialchars($producto['nombre']); ?>"
                             data-precio="<?= $producto['precio']; ?>"
                             data-cantidad="<?= $producto['cantidad']; ?>"
                             data-codigo="<?= htmlspecialchars($producto['codigo_barras']); ?>">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <p class="product-name text-center mb-1">
                                            <?= htmlspecialchars($producto['nombre']); ?>
                                        </p>
                                        <p class="text-center text-muted small">
                                            <?= htmlspecialchars($producto['codigo_barras']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="product-price text-center mb-1">
                                            $<?= number_format($producto['precio'], 2); ?>
                                        </p>
                                        <p class="text-center text-muted small">
                                            Stock: <?= $producto['cantidad']; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Panel derecho: Resumen de venta -->
            <div class="col-md-4 venta-sidebar">
                <h4 class="mb-3">Factura de venta</h4>
                
                <div class="form-group">
                    <label for="cliente-select">Cliente</label>
                    <select id="cliente-select" class="form-control">
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= $cliente['id']; ?>" <?= ($cliente['nombre'] === 'Consumidor Final') ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cliente['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th>Precio</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="venta-lista"></tbody>
                </table>

                <div class="form-group">
                    <label for="descuento">Descuento (%)</label>
                    <input type="number" id="descuento" class="form-control" min="0" max="100" value="0">
                </div>

                <div id="subtotal" class="text-right mb-2">Subtotal: $0.00</div>
                <div id="descuento-monto" class="text-right mb-2">Descuento: $0.00</div>
                <div class="venta-total mb-3" id="venta-total"><strong>Total: $0.00</strong></div>

                <div class="form-group">
                    <label for="metodo-pago">Método de Pago</label>
                    <select id="metodo-pago" class="form-control">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                </div>

                <button class="venta-boton btn btn-success btn-block" id="venta-boton">Confirmar Venta</button>
            </div>
        </div>
    </div>

    <script src="./ventas.js"></script>
</body>
</html>