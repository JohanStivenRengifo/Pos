<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Función para obtener un producto por su código de barras
function obtenerProducto($codigo_barras, $user_id)
{
    global $pdo;
    $query = "SELECT i.*, 
                     c.nombre as categoria_nombre,
                     d.nombre as departamento_nombre
              FROM inventario i
              LEFT JOIN categorias c ON i.categoria_id = c.id
              LEFT JOIN departamentos d ON i.departamento_id = d.id
              WHERE i.codigo_barras = ? AND i.user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$codigo_barras, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para actualizar el stock y precios de un producto
function actualizarStock($id, $nueva_cantidad, $nuevo_precio_costo, $nuevo_margen, $nuevo_impuesto, $nueva_descripcion, $user_id, $tipo_operacion = 'agregar')
{
    global $pdo;
    try {
        $pdo->beginTransaction();

        // Obtener stock actual
        $query = "SELECT stock FROM inventario WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id, $user_id]);
        $stock_actual = $stmt->fetchColumn();

        // Calcular nuevo stock según la operación
        $nuevo_stock = $tipo_operacion === 'agregar' ? 
            $stock_actual + $nueva_cantidad : 
            $stock_actual - $nueva_cantidad;

        // Validar que el stock no quede negativo
        if ($nuevo_stock < 0) {
            throw new Exception("El stock no puede quedar en negativo. Stock actual: $stock_actual");
        }

        // Usar el precio de venta exacto del POST si existe
        $nuevo_precio_venta = isset($_POST['nuevo_precio_venta']) && $_POST['nuevo_precio_venta'] !== '' ? 
            str_replace(',', '', $_POST['nuevo_precio_venta']) : 
            number_format($nuevo_precio_costo * (1 + ($nuevo_margen / 100)) * (1 + ($nuevo_impuesto / 100)), 2, '.', '');

        // Actualizar producto usando el precio de venta exacto
        $query = "UPDATE inventario 
                 SET stock = :nuevo_stock,
                     precio_costo = :nuevo_precio_costo,
                     margen_ganancia = :nuevo_margen,
                     impuesto = :nuevo_impuesto,
                     precio_venta = :nuevo_precio_venta,
                     descripcion = :nueva_descripcion,
                     fecha_modificacion = NOW()
                 WHERE id = :id AND user_id = :user_id";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':nuevo_stock' => $nuevo_stock,
            ':nuevo_precio_costo' => $nuevo_precio_costo,
            ':nuevo_margen' => $nuevo_margen,
            ':nuevo_impuesto' => $nuevo_impuesto,
            ':nuevo_precio_venta' => $nuevo_precio_venta,
            ':nueva_descripcion' => $nueva_descripcion,
            ':id' => $id,
            ':user_id' => $user_id
        ]);

        if (!$result) {
            throw new Exception("Error al actualizar el producto");
        }

        // Registrar en historial
        $query = "INSERT INTO historial_productos (producto_id, user_id, tipo_cambio, detalle) 
                 VALUES (:producto_id, :user_id, 'modificacion', :detalle)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':producto_id' => $id,
            ':user_id' => $user_id,
            ':detalle' => json_encode([
                'tipo_operacion' => $tipo_operacion,
                'cantidad' => $nueva_cantidad,
                'stock_anterior' => $stock_actual,
                'stock_nuevo' => $nuevo_stock,
                'nuevo_precio_costo' => $nuevo_precio_costo,
                'nuevo_precio_venta' => $nuevo_precio_venta,
                'nuevo_margen' => $nuevo_margen,
                'nuevo_impuesto' => $nuevo_impuesto
            ])
        ]);

        $pdo->commit();
        return [
            'success' => true,
            'nuevo_stock' => $nuevo_stock,
            'message' => "Stock actualizado correctamente. Nuevo stock: $nuevo_stock"
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error en actualizarStock: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Procesar solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    try {
        if ($_POST['action'] === 'buscar_producto') {
            $codigo_barras = trim($_POST['codigo_barras']);
            $producto = obtenerProducto($codigo_barras, $user_id);
            
            if ($producto) {
                $response['success'] = true;
                $response['producto'] = $producto;
            } else {
                throw new Exception("Producto no encontrado.");
            }
        } elseif ($_POST['action'] === 'actualizar_stock') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $nueva_cantidad = filter_input(INPUT_POST, 'nueva_cantidad', FILTER_VALIDATE_INT);
            $nuevo_precio_costo = filter_input(INPUT_POST, 'nuevo_precio_costo', FILTER_VALIDATE_FLOAT);
            $nuevo_margen = filter_input(INPUT_POST, 'nuevo_margen', FILTER_VALIDATE_FLOAT);
            $nuevo_impuesto = filter_input(INPUT_POST, 'nuevo_impuesto', FILTER_VALIDATE_FLOAT);
            $nueva_descripcion = trim(filter_input(INPUT_POST, 'nueva_descripcion'));
            $tipo_operacion = $_POST['tipo_operacion'] ?? 'agregar';

            if (!$id || !$nueva_cantidad || !$nuevo_precio_costo || 
                $nuevo_margen === false || $nuevo_impuesto === false) {
                throw new Exception("Datos inválidos");
            }

            if ($nueva_cantidad <= 0) {
                throw new Exception("La cantidad debe ser mayor a 0");
            }

            $result = actualizarStock(
                $id, 
                $nueva_cantidad, 
                $nuevo_precio_costo, 
                $nuevo_margen, 
                $nuevo_impuesto, 
                $nueva_descripcion, 
                $user_id,
                $tipo_operacion
            );

            if ($result['success']) {
                $response = $result;
            } else {
                throw new Exception($result['message']);
            }
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Error en surtir.php: " . $e->getMessage());
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surtir Inventario | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <style>
        /* Estilos generales mejorados */
        .producto-form {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .form-section {
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }

        .form-section:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .form-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2c3e50;
            font-size: 1.1em;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .form-section-title i {
            color: #007bff;
            font-size: 1.2em;
        }

        /* Campos de formulario mejorados */
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.15);
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            color: #6c757d;
        }

        /* Botones mejorados */
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #007bff;
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Animaciones y estados */
        .stock-changed {
            animation: highlight 1s ease;
        }

        @keyframes highlight {
            0% { background-color: #fff3cd; }
            100% { background-color: transparent; }
        }

        .is-invalid {
            border-color: #dc3545;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Indicadores visuales */
        .stock-status {
            padding: 8px;
            border-radius: 6px;
            font-weight: 500;
            text-align: center;
            margin-top: 5px;
        }

        .stock-normal { background: #d4edda; color: #155724; }
        .stock-warning { background: #fff3cd; color: #856404; }
        .stock-danger { background: #f8d7da; color: #721c24; }

        /* Layout mejorado */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: -10px;
        }

        .form-group {
            padding: 10px;
            flex: 1;
            min-width: 250px;
        }

        /* Resumen de operación */
        .operation-summary {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
        }

        /* Tooltips y ayudas */
        .help-text {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 4px;
        }

        .field-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
        }

        /* Responsive mejoras */
        @media (max-width: 768px) {
            .form-group {
                min-width: 100%;
            }

            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <a href="../../welcome.php">VendEasy</a>
        </div>
        <div class="header-icons">
            <i class="fas fa-bell"></i>
            <div class="account">
                <h4><?= htmlspecialchars($email) ?></h4>
            </div>
        </div>
    </header>
    <div class="container">
        <nav>
            <div class="side_navbar">
                <span>Menú Principal</span>
                <a href="#">Dashboard</a>
                <a href="/modules/pos/index.php">POS</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php" class="active">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="#">Ayuda</a>
                    <a href="#">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Surtir Inventario</h2>
            <div class="promo_card">
                <h1>Actualizar Stock de Productos</h1>
                <span>Ingrese el código de barras y la cantidad a agregar.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Buscar Producto</h4>
                    </div>
                    <form id="buscarProductoForm" class="producto-form">
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-search"></i> 
                                <span>Búsqueda Rápida</span>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-8">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-barcode"></i>
                                        </span>
                                        <input type="text" 
                                               id="codigo_barras" 
                                               name="codigo_barras" 
                                               required 
                                               class="form-control" 
                                               placeholder="Escanee o ingrese el código de barras"
                                               autofocus>
                                    </div>
                                    <div class="help-text">
                                        Puede usar un escáner o ingresar el código manualmente
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Buscar Producto
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="list1" id="actualizarStockForm" style="display: none;">
                    <div class="row">
                        <h4>Actualizar Stock</h4>
                    </div>
                    <form id="stockForm" class="producto-form">
                        <input type="hidden" id="producto_id" name="id">
                        
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-box"></i> 
                                <span>Información del Producto</span>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre del Producto</label>
                                    <input type="text" id="nombre_producto" readonly class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Stock Actual</label>
                                    <input type="number" id="stock_actual" readonly class="form-control">
                                    <div id="stockStatus" class="stock-status"></div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Stock Mínimo</label>
                                    <input type="number" id="stock_minimo" readonly class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-edit"></i> 
                                <span>Actualización de Stock</span>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Tipo de Operación</label>
                                    <select id="tipo_operacion" class="form-control">
                                        <option value="agregar">
                                            <i class="fas fa-plus"></i> Agregar Stock
                                        </option>
                                        <option value="restar">
                                            <i class="fas fa-minus"></i> Restar Stock
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Cantidad</label>
                                    <input type="number" 
                                           id="nueva_cantidad" 
                                           class="form-control" 
                                           min="1" 
                                           required>
                                    <div class="help-text">
                                        Ingrese la cantidad a modificar
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Stock Resultante</label>
                                    <input type="number" 
                                           id="stock_resultante" 
                                           readonly 
                                           class="form-control">
                                    <div class="stock-warning" style="display: none;">
                                        El stock no puede ser negativo
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-dollar-sign"></i> 
                                <span>Información de Precios</span>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Precio Costo</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" 
                                               step="0.01" 
                                               id="nuevo_precio_costo" 
                                               class="form-control" 
                                               required>
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Margen (%)</label>
                                    <div class="input-group">
                                        <input type="number" 
                                               step="0.01" 
                                               id="nuevo_margen" 
                                               class="form-control" 
                                               required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>IVA (%)</label>
                                    <div class="input-group">
                                        <input type="number" 
                                               step="0.01" 
                                               id="nuevo_impuesto" 
                                               class="form-control" 
                                               required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Precio Venta</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" 
                                               step="0.01" 
                                               id="nuevo_precio_venta" 
                                               class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-file-alt"></i> Información Adicional
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label for="nueva_descripcion">Nueva Descripción:</label>
                                    <textarea id="nueva_descripcion" name="nueva_descripcion" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Actualizar Stock
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="limpiarFormulario()">
                                <i class="fas fa-undo"></i> Limpiar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Elementos del DOM
            const elements = {
                precioCosto: $('#nuevo_precio_costo'),
                margenGanancia: $('#nuevo_margen'),
                impuesto: $('#nuevo_impuesto'),
                precioVenta: $('#nuevo_precio_venta'),
                stockActual: $('#stock_actual'),
                nuevaCantidad: $('#nueva_cantidad'),
                tipoOperacion: $('#tipo_operacion'),
                stockResultante: $('#stock_resultante'),
                productoId: $('#producto_id'),
                descripcion: $('#nueva_descripcion'),
                codigoBarras: $('#codigo_barras'),
                stockWarning: $('.stock-warning'),
                actualizarStockForm: $('#actualizarStockForm')
            };

            // Calculadora de precios
            const calculadora = {
                calcularPrecioVenta() {
                    const precioCosto = elements.precioCosto.val();
                    const margen = elements.margenGanancia.val();
                    const impuesto = elements.impuesto.val();
                    
                    if (!precioCosto || !elements.precioVenta.val()) {
                        const precioBase = parseFloat(precioCosto) * (1 + (parseFloat(margen) / 100));
                        const precioVenta = precioBase * (1 + (parseFloat(impuesto) / 100));
                        elements.precioVenta.val(precioVenta.toFixed(2));
                    }
                },

                calcularMargenDesdeVenta() {
                    const precioCosto = elements.precioCosto.val();
                    const precioVenta = elements.precioVenta.val();
                    const impuesto = elements.impuesto.val();

                    if (!precioCosto || !precioVenta) return;

                    const precioSinImpuesto = parseFloat(precioVenta) / (1 + (parseFloat(impuesto) / 100));
                    const margen = ((precioSinImpuesto / parseFloat(precioCosto)) - 1) * 100;
                    elements.margenGanancia.val(margen.toFixed(2));
                }
            };

            // Manejador de stock
            const stockHandler = {
                calcularStockResultante() {
                    const stockActual = parseInt(elements.stockActual.val()) || 0;
                    const nuevaCantidad = parseInt(elements.nuevaCantidad.val()) || 0;
                    const tipoOperacion = elements.tipoOperacion.val();
                    
                    const resultado = tipoOperacion === 'agregar' ? 
                        stockActual + nuevaCantidad : 
                        stockActual - nuevaCantidad;
                    
                    elements.stockResultante.val(resultado);
                    this.validarStock(resultado);
                },

                validarStock(resultado) {
                    const isValid = resultado >= 0;
                    elements.stockResultante.toggleClass('is-invalid', !isValid);
                    elements.nuevaCantidad.toggleClass('is-invalid', !isValid);
                    elements.stockWarning.toggle(!isValid);
                    return isValid;
                }
            };

            // Event listeners
            function setupEventListeners() {
                // Eventos para cálculos de precio
                elements.precioCosto.on('input', () => {
                    elements.precioVenta.val() ? calculadora.calcularMargenDesdeVenta() : calculadora.calcularPrecioVenta();
                });

                elements.margenGanancia.on('input', () => {
                    if (!elements.precioVenta.val()) calculadora.calcularPrecioVenta();
                });

                elements.impuesto.on('input', () => {
                    elements.precioVenta.val() ? calculadora.calcularMargenDesdeVenta() : calculadora.calcularPrecioVenta();
                });

                elements.precioVenta.on('input', calculadora.calcularMargenDesdeVenta);

                // Eventos para cálculos de stock
                elements.nuevaCantidad.on('input', () => stockHandler.calcularStockResultante());
                elements.tipoOperacion.on('change', () => stockHandler.calcularStockResultante());
            }

            // Manejador de formularios
            const formHandler = {
                buscarProducto(e) {
                    e.preventDefault();
                    const codigo_barras = elements.codigoBarras.val();

                    $.ajax({
                        url: 'surtir.php',
                        method: 'POST',
                        data: { action: 'buscar_producto', codigo_barras },
                        dataType: 'json',
                        success: this.procesarRespuestaBusqueda,
                        error: () => this.mostrarError('Hubo un problema al buscar el producto.')
                    });
                },

                procesarRespuestaBusqueda(response) {
                    if (response.success) {
                        const producto = response.producto;
                        formHandler.llenarFormulario(producto);
                        elements.actualizarStockForm.show();
                    } else {
                        formHandler.mostrarError(response.message);
                    }
                },

                llenarFormulario(producto) {
                    elements.productoId.val(producto.id);
                    $('#nombre_producto').val(producto.nombre);
                    $('#categoria_producto').val(producto.categoria_nombre);
                    $('#departamento_producto').val(producto.departamento_nombre);
                    elements.stockActual.val(producto.stock);
                    elements.precioCosto.val(producto.precio_costo);
                    elements.margenGanancia.val(producto.margen_ganancia);
                    elements.impuesto.val(producto.impuesto);
                    elements.descripcion.val(producto.descripcion);
                    calculadora.calcularPrecioVenta();
                },

                actualizarStock(e) {
                    e.preventDefault();
                    
                    if (!stockHandler.validarStock(parseInt(elements.stockResultante.val()))) {
                        this.mostrarError('El stock no puede quedar en negativo');
                        return;
                    }

                    this.confirmarActualizacion();
                },

                confirmarActualizacion() {
                    Swal.fire({
                        title: '¿Confirmar actualización?',
                        text: "¿Está seguro de actualizar el stock y precios del producto?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, actualizar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.enviarActualizacion();
                        }
                    });
                },

                enviarActualizacion() {
                    const formData = {
                        action: 'actualizar_stock',
                        id: elements.productoId.val(),
                        nueva_cantidad: elements.nuevaCantidad.val(),
                        nuevo_precio_costo: elements.precioCosto.val(),
                        nuevo_margen: elements.margenGanancia.val(),
                        nuevo_impuesto: elements.impuesto.val(),
                        nueva_descripcion: elements.descripcion.val(),
                        tipo_operacion: elements.tipoOperacion.val(),
                        nuevo_precio_venta: elements.precioVenta.val()
                    };

                    $.ajax({
                        url: 'surtir.php',
                        method: 'POST',
                        data: formData,
                        success: this.procesarRespuestaActualizacion,
                        error: (xhr, status, error) => {
                            console.error('Ajax error:', error);
                            this.mostrarError('Hubo un problema al actualizar el stock.');
                        }
                    });
                },

                procesarRespuestaActualizacion(response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        if (result.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
                                text: result.message
                            }).then(() => formHandler.limpiarFormulario());
                        } else {
                            formHandler.mostrarError(result.message || 'Error al actualizar');
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        formHandler.mostrarError('Hubo un problema al procesar la respuesta del servidor');
                    }
                },

                mostrarError(mensaje) {
                    Swal.fire('Error', mensaje, 'error');
                },

                limpiarFormulario() {
                    $('#buscarProductoForm')[0].reset();
                    $('#stockForm')[0].reset();
                    elements.actualizarStockForm.hide();
                }
            };

            // Inicialización
            setupEventListeners();
            $('#buscarProductoForm').on('submit', e => formHandler.buscarProducto(e));
            $('#stockForm').on('submit', e => formHandler.actualizarStock(e));
        });
    </script>
</body>

</html>