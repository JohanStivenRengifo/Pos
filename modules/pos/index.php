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
    <title>POS | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="/css/pos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #f39c12;
            --background-color: #f4f6f9;
            --text-color: #333;
            --card-bg: #ffffff;
            --hover-color: #3498db;
        }

        body {
            background-color: var(--background-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
        }

        .navbar {
            background-color: var(--primary-color);
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }

        .navbar-brand, .navbar .btn {
            color: #ffffff;
        }

        .left-panel, .venta-sidebar {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,.1);
            padding: 20px;
            height: calc(100vh - 76px);
            overflow-y: auto;
        }

        .product-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 8px;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,.15);
        }

        .search-bar {
            margin-bottom: 1rem;
        }

        #products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .venta-sidebar .form-group {
            margin-bottom: 1rem;
        }

        .venta-sidebar label {
            font-weight: 600;
            color: var(--text-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--hover-color);
            border-color: var(--hover-color);
            transform: translateY(-2px);
        }

        .form-control, .custom-select {
            border-radius: 20px;
            border: 1px solid #ced4da;
            transition: all 0.3s ease;
        }

        .form-control:focus, .custom-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }

        #venta-lista {
            max-height: 300px;
            overflow-y: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .slide-in {
            animation: slideIn 0.5s ease-out;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="#">
            <i class="fas fa-cash-register mr-2"></i>VendEasy POS
        </a>
        <div class="ml-auto">
            <button class="btn btn-outline-light btn-sm mr-2" id="sync-button">
                <i class="fas fa-sync-alt"></i> Sincronizar
            </button>
            <button class="btn btn-outline-light btn-sm mr-2" id="help-button">
                <i class="fas fa-question-circle"></i> Ayuda
            </button>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Panel izquierdo: Lista de productos -->
            <div class="col-md-8 left-panel slide-in">
                <div class="search-bar">
                    <div class="input-group">
                        <input type="text" id="buscar-producto" class="form-control" placeholder="Buscar productos por nombre o código de barras">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="scan-barcode">
                                <i class="fas fa-barcode"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div id="products-grid" class="fade-in">
                    <?php foreach ($productos as $producto): ?>
                        <div class="product-card"
                             data-id="<?= $producto['id']; ?>"
                             data-nombre="<?= htmlspecialchars($producto['nombre']); ?>"
                             data-precio="<?= $producto['precio']; ?>"
                             data-cantidad="<?= $producto['cantidad']; ?>"
                             data-codigo="<?= htmlspecialchars($producto['codigo_barras']); ?>">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column justify-content-between">
                                    <div>
                                        <h6 class="card-title mb-0"><?= htmlspecialchars($producto['nombre']); ?></h6>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($producto['codigo_barras']); ?></p>
                                    </div>
                                    <div>
                                        <p class="card-text text-primary mb-0">$<?= number_format($producto['precio'], 2); ?></p>
                                        <p class="text-muted small">Stock: <?= $producto['cantidad']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Panel derecho: Resumen de venta o cotización -->
            <div class="col-md-4 venta-sidebar slide-in">
                <h4 class="mb-3" id="titulo-documento">Factura de venta</h4>
                
                <div class="form-group">
                    <label for="tipo-documento">Tipo de documento</label>
                    <select id="tipo-documento" class="custom-select">
                        <option value="factura">Factura de venta</option>
                        <option value="cotizacion">Cotización</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="numeracion">Numeración</label>
                    <select id="numeracion" class="custom-select">
                        <option value="principal">Principal</option>
                        <option value="electronica">Electrónica</option>
                        <option value="pos-electronico">Documento POS electrónico</option>
                        <option value="factura-electronica">Factura electrónica</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cliente-select">Cliente</label>
                    <select id="cliente-select" class="custom-select">
                        <option value="">Seleccione un cliente</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?= $cliente['id']; ?>" <?= $cliente['nombre'] === 'Consumidor Final' ? 'selected' : ''; ?>><?= htmlspecialchars($cliente['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="venta-lista" class="mb-3">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cant.</th>
                                <th>Precio</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                    <p class="text-muted text-center" id="venta-lista-empty">Aquí verás los productos que elijas en tu próxima venta</p>
                </div>

                <div id="resumen-venta" class="mb-3">
                    <p class="mb-1"><strong>Subtotal:</strong> <span id="subtotal">$0.00</span></p>
                    <p class="mb-1"><strong>Descuento:</strong> <span id="descuento-monto">$0.00</span></p>
                    <p class="mb-0"><strong>Total:</strong> <span id="venta-total">$0.00</span></p>
                    <p class="mb-0"><strong>Cantidad total:</strong> <span id="cantidad-total">0</span></p>
                </div>

                <div class="form-group">
                    <label for="descuento">Descuento (%)</label>
                    <input type="number" id="descuento" class="form-control" min="0" max="100" value="0">
                </div>

                <div class="form-group">
                    <label for="metodo-pago">Método de pago</label>
                    <select id="metodo-pago" class="custom-select">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="credito">Crédito</option>
                    </select>
                </div>

                <div id="campos-credito" style="display: none;">
                    <div class="form-group"> 
                        <label for="plazo-credito">Plazo (meses)</label>
                        <input type="number" id="plazo-credito" class="form-control" min="1" value="1">
                    </div>
                    <div class="form-group">
                        <label for="interes-credito">Interés (%)</label>
                        <input type="number" id="interes-credito" class="form-control" min="0" step="0.01" value="0">
                    </div>
                </div>

                <button class="btn btn-primary btn-block" id="venta-boton">Vender</button>
                <button class="btn btn-outline-secondary btn-block mt-2" id="cancelar-venta">Cancelar</button>

                <button class="btn btn-info btn-block mt-3" id="imprimir-ticket-anterior">Imprimir ticket anterior</button>
            </div>
        </div>
    </div>

    <script src="./ventas.js"></script>
    <script>
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();

            $('#sync-button').click(function() {
                Swal.fire({
                    title: 'Sincronizando...',
                    text: 'Por favor espere mientras se sincronizan los datos con el servidor.',
                    icon: 'info',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                setTimeout(() => {
                    Swal.fire({
                        title: 'Sincronización completada',
                        text: 'Los datos se han sincronizado correctamente con el servidor.',
                        icon: 'success',
                        confirmButtonText: 'Aceptar'
                    });
                }, 12000);
            });

            $('#help-button').click(function() {
                Swal.fire({
                    title: 'Ayuda',
                    html: `
                        <ul class="text-left">
                            <li>Para agregar un producto, haz clic en su tarjeta o escanea su código de barras.</li>
                            <li>Puedes modificar la cantidad de un producto en el carrito.</li>
                            <li>Selecciona un cliente antes de finalizar la venta.</li>
                            <li>Puedes aplicar un descuento general a la venta.</li>
                            <li>Para ventas a crédito, selecciona el método de pago "Crédito" y completa los campos adicionales.</li>
                        </ul>
                    `,
                    icon: 'question',
                    confirmButtonText: 'Entendido'
                });
            });
        });
    </script>
</body>
</html>
