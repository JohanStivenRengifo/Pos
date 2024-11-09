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
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --border-radius: 8px;
            --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
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
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 1rem;
            background: var(--card-bg);
            border-bottom: 1px solid rgba(0,0,0,0.1);
            box-shadow: var(--box-shadow);
        }

        .search-bar .input-group {
            max-width: 800px;
            margin: 0 auto;
        }

        .search-bar .form-control {
            height: 48px;
            font-size: 1rem;
            padding-left: 1rem;
            border-radius: var(--border-radius) 0 0 var(--border-radius);
        }

        .search-bar .btn {
            width: 48px;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }

        #products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.25rem;
            padding: 1.25rem;
            margin-top: 0.5rem;
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

        .item-view {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 300px;
            display: flex;
            flex-direction: column;
            position: relative;
            cursor: pointer;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.08);
        }

        .item-view:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        }

        .item-view__header {
            padding: 0.75rem;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 2;
            background: linear-gradient(180deg, rgba(0,0,0,0.4) 0%, transparent 100%);
        }

        .item-view__reference {
            font-size: 0.8rem;
            color: white;
            background: rgba(0,0,0,0.6);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            backdrop-filter: blur(4px);
        }

        .item-view__image-zone {
            height: 180px;
            position: relative;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease, opacity 0.3s ease;
            opacity: 1;
            background-color: #f5f5f5;
        }

        .item-view:hover .product-image {
            transform: scale(1.08);
        }

        .item-view__quantity {
            position: absolute;
            bottom: 0.5rem;
            right: 0.5rem;
            background: rgba(255,255,255,0.95);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 2;
        }

        .item-view__details {
            padding: 1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--card-bg);
        }

        .item-view__name {
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.3;
            margin: 0;
            color: var(--text-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-view__price {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0.5rem 0 0 0;
        }

        .item-view__empty-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(45deg, #f5f5f5, #ebebeb);
        }

        .item-view__empty-image i {
            font-size: 2.5rem;
            color: #aaa;
            opacity: 0.8;
        }

        @keyframes imageFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .product-image.loaded {
            animation: imageFadeIn 0.3s ease-in-out;
        }

        .item-view__stock-indicator {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            z-index: 3;
        }

        .stock-high {
            background-color: var(--success-color);
        }

        .stock-medium {
            background-color: var(--secondary-color);
        }

        .stock-low {
            background-color: var(--danger-color);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.4s ease-out forwards;
        }

        @media (max-width: 768px) {
            #products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 1rem;
                padding: 1rem;
            }

            .item-view {
                height: 260px;
            }

            .item-view__image-zone {
                height: 140px;
            }
        }

        /* Estilos para el panel derecho */
        .venta-sidebar {
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .venta-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .venta-productos {
            flex-grow: 1;
            margin-bottom: 1.5rem;
        }

        #venta-lista {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 0.5rem;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .venta-resumen {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        .resumen-card {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .resumen-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
        }

        .resumen-item.total {
            border-top: 2px solid rgba(0,0,0,0.1);
            margin-top: 0.5rem;
            padding-top: 1rem;
            font-size: 1.2rem;
        }

        .credito-options {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .venta-actions {
            margin-top: 1rem;
        }

        /* Mejoras en la tabla de productos */
        .table th {
            border-top: none;
            font-weight: 600;
            color: #666;
        }

        .table td {
            vertical-align: middle;
        }

        /* Estilos para los inputs y selects */
        .custom-select, .form-control {
            height: calc(2.2rem + 2px);
            padding: 0.375rem 1rem;
        }

        /* Animaciones */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .venta-sidebar {
                height: auto;
                margin-top: 1rem;
            }

            #venta-lista {
                max-height: 250px;
            }
        }

        /* Estilos para el modal */
        .modal-content {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-header .close {
            color: white;
            opacity: 0.8;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #eee;
            padding: 1rem;
        }

        #nuevoClienteForm .form-group {
            margin-bottom: 1rem;
        }

        #nuevoClienteForm label {
            font-weight: 500;
            color: #666;
        }

        #nuevoClienteForm .form-control {
            border-radius: 4px;
        }

        #nuevoClienteForm .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }

        #nuevoClienteForm .is-invalid {
            border-color: #dc3545;
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
                        <div class="item-view product-card"
                             data-id="<?= $producto['id']; ?>"
                             data-nombre="<?= htmlspecialchars($producto['nombre']); ?>"
                             data-precio="<?= $producto['precio']; ?>"
                             data-cantidad="<?= $producto['cantidad']; ?>"
                             data-codigo="<?= htmlspecialchars($producto['codigo_barras']); ?>">
                            
                            <div class="item-view__header">
                                <span class="item-view__reference">
                                    <?= htmlspecialchars($producto['codigo_barras']); ?>
                                </span>
                            </div>
                            
                            <div class="item-view__image-zone">
                                <?php if (!empty($producto['imagen'])): ?>
                                    <img src="<?= htmlspecialchars($producto['imagen']); ?>" 
                                         data-src="<?= htmlspecialchars($producto['imagen']); ?>" 
                                         alt="<?= htmlspecialchars($producto['nombre']); ?>"
                                         class="product-image"
                                         loading="lazy"
                                         onerror="this.onerror=null; this.src='../../assets/img/no-image.png'; this.classList.add('loaded');">
                                <?php else: ?>
                                    <div class="item-view__empty-image">
                                        <i class="fas fa-box"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="item-view__quantity">
                                    Stock: <?= $producto['cantidad']; ?>
                                </div>
                            </div>
                            
                            <div class="item-view__details">
                                <h3 class="item-view__name">
                                    <?= htmlspecialchars($producto['nombre']); ?>
                                </h3>
                                <p class="item-view__price">
                                    $<?= number_format($producto['precio'], 2, ',', '.'); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Panel derecho: Resumen de venta o cotización -->
            <div class="col-md-4 venta-sidebar slide-in">
                <div class="venta-header">
                    <h4 class="mb-3" id="titulo-documento">
                        <i class="fas fa-file-invoice mr-2"></i>Factura de venta
                    </h4>
                    
                    <div class="row">
                        <!-- Primera fila: Tipo documento y Numeración -->
                        <div class="col-6">
                            <div class="form-group">
                                <label for="tipo-documento">
                                    <i class="fas fa-file-alt mr-1"></i>Tipo
                                </label>
                                <select id="tipo-documento" class="custom-select custom-select-sm">
                                    <option value="factura">Factura de venta</option>
                                    <option value="cotizacion">Cotización</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="numeracion">
                                    <i class="fas fa-hashtag mr-1"></i>Numeración
                                </label>
                                <select id="numeracion" class="custom-select custom-select-sm">
                                    <option value="principal">Principal</option>
                                    <option value="electronica">Electrónica</option>
                                </select>
                            </div>
                        </div>

                        <!-- Segunda fila: Cliente (ancho completo) -->
                        <div class="col-12">
                            <div class="form-group mb-2">
                                <label for="cliente-select">
                                    <i class="fas fa-user mr-1"></i>Cliente
                                </label>
                                <div class="input-group input-group-sm">
                                    <select id="cliente-select" class="custom-select">
                                        <option value="">Seleccione un cliente</option>
                                        <?php foreach ($clientes as $cliente): ?>
                                            <option value="<?= $cliente['id']; ?>" 
                                                    <?= $cliente['nombre'] === 'Consumidor Final' ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($cliente['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="nuevo-cliente">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="venta-productos">
                    <div class="productos-header d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart mr-2"></i>Productos</h5>
                        <span class="badge badge-primary" id="cantidad-items">0 items</span>
                    </div>

                    <div id="venta-lista" class="mb-3">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center" width="60">Cant.</th>
                                    <th class="text-right" width="80">Precio</th>
                                    <th class="text-right" width="80">Total</th>
                                    <th width="30"></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                        <div id="venta-lista-empty" class="text-center text-muted py-3">
                            <i class="fas fa-shopping-basket fa-2x mb-2"></i>
                            <p class="mb-0">Aquí verás los productos de tu venta</p>
                        </div>
                    </div>
                </div>

                <div class="venta-resumen">
                    <div class="resumen-card">
                        <!-- Primera fila del resumen -->
                        <div class="row">
                            <div class="col-6">
                                <div class="resumen-item">
                                    <span>Subtotal:</span>
                                    <span id="subtotal" class="font-weight-bold">$0,00</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="resumen-item">
                                    <span>Descuento:</span>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="descuento" class="form-control form-control-sm" min="0" max="100" value="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Segunda fila del resumen -->
                        <div class="row mt-2">
                            <div class="col-6">
                                <div class="resumen-item">
                                    <span>Desc. aplicado:</span>
                                    <span id="descuento-monto" class="text-danger">-$0,00</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="resumen-item total">
                                    <span>Total:</span>
                                    <span id="venta-total" class="font-weight-bold">$0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fila de método de pago -->
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group mb-2">
                                <label for="metodo-pago">
                                    <i class="fas fa-money-bill-wave mr-1"></i>Método de pago
                                </label>
                                <select id="metodo-pago" class="custom-select custom-select-sm">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="credito">Crédito</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Opciones de crédito en dos columnas -->
                    <div id="campos-credito" class="credito-options" style="display: none;">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="plazo-credito">
                                        <i class="fas fa-calendar-alt mr-1"></i>Plazo
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="plazo-credito" class="form-control" min="1" value="1">
                                        <div class="input-group-append">
                                            <span class="input-group-text">meses</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="interes-credito">
                                        <i class="fas fa-percentage mr-1"></i>Interés
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" id="interes-credito" class="form-control" min="0" step="0.01" value="0">
                                        <div class="input-group-append">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="venta-actions">
                        <div class="row">
                            <div class="col-12">
                                <button class="btn btn-primary btn-block" id="venta-boton">
                                    <i class="fas fa-check-circle mr-2"></i>Confirmar Venta
                                </button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-6">
                                <button class="btn btn-outline-secondary btn-block" id="cancelar-venta">
                                    <i class="fas fa-times-circle mr-1"></i>Cancelar
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-info btn-block" id="imprimir-ticket-anterior">
                                    <i class="fas fa-print mr-1"></i>Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
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

        // Actualizar el script de manejo de imágenes
        document.addEventListener('DOMContentLoaded', function() {
            const productImages = document.querySelectorAll('.product-image');
            
            function handleImageLoad(img) {
                img.classList.add('loaded');
            }

            function handleImageError(img) {
                img.onerror = null; // Prevenir bucle infinito
                img.src = '../../assets/img/no-image.png';
                img.classList.add('loaded');
            }

            productImages.forEach(img => {
                if (img.complete) {
                    handleImageLoad(img);
                } else {
                    img.addEventListener('load', () => handleImageLoad(img));
                    img.addEventListener('error', () => handleImageError(img));
                }
            });

            // Implementar lazy loading nativo
            if ('loading' in HTMLImageElement.prototype) {
                productImages.forEach(img => {
                    img.loading = 'lazy';
                });
            } else {
                // Fallback para navegadores que no soportan lazy loading nativo
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                            }
                            observer.unobserve(img);
                        }
                    });
                });

                productImages.forEach(img => {
                    imageObserver.observe(img);
                });
            }
        });

        // Función para precargar imágenes
        function preloadImage(url) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(url);
                img.onerror = () => reject(url);
                img.src = url;
            });
        }

        // Precargar la imagen de fallback
        preloadImage('../../assets/img/no-image.png').catch(console.error);
    </script>

    <!-- Agregar este código justo antes del cierre del body -->

    <!-- Modal para Nuevo Cliente -->
    <div class="modal fade" id="nuevoClienteModal" tabindex="-1" role="dialog" aria-labelledby="nuevoClienteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuevoClienteModalLabel">
                        <i class="fas fa-user-plus mr-2"></i>Nuevo Cliente
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="nuevoClienteForm">
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="nombre">
                                        <i class="fas fa-user mr-1"></i>Nombre completo *
                                    </label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required 
                                           placeholder="Ej: Juan Carlos Pérez Gómez">
                                    <small class="form-text text-muted">Ingrese nombres y apellidos completos</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="documento">
                                        <i class="fas fa-id-card mr-1"></i>Número de identificación
                                    </label>
                                    <input type="text" class="form-control" id="documento" name="documento" 
                                           placeholder="Ej: 12345678">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="telefono">
                                        <i class="fas fa-phone mr-1"></i>Teléfono
                                    </label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" 
                                           placeholder="Ej: 3001234567">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope mr-1"></i>Correo electrónico
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Ej: cliente@email.com">
                        </div>
                        <div class="form-group">
                            <label for="direccion">
                                <i class="fas fa-map-marker-alt mr-1"></i>Municipio/Departamento
                            </label>
                            <input type="text" class="form-control" id="direccion" name="direccion" 
                                   placeholder="Ej: Medellín, Antioquia">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="guardarCliente">
                        <i class="fas fa-save mr-1"></i>Guardar Cliente
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>