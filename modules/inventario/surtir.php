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

        // Calcular nuevo precio de venta
        $precio_base = $nuevo_precio_costo * (1 + ($nuevo_margen / 100));
        $nuevo_precio_venta = $precio_base * (1 + ($nuevo_impuesto / 100));
        $nuevo_precio_venta = round($nuevo_precio_venta, 2);

        // Actualizar producto
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
        $success = $stmt->execute([
            ':nuevo_stock' => $nuevo_stock,
            ':nuevo_precio_costo' => $nuevo_precio_costo,
            ':nuevo_margen' => $nuevo_margen,
            ':nuevo_impuesto' => $nuevo_impuesto,
            ':nuevo_precio_venta' => $nuevo_precio_venta,
            ':nueva_descripcion' => $nueva_descripcion,
            ':id' => $id,
            ':user_id' => $user_id
        ]);

        if (!$success) {
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
                <a href="#" class="active">Dashboard</a>
                <a href="/modules/pos/index.php">POS</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
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
                                <i class="fas fa-search"></i> Búsqueda de Producto
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-8">
                                    <label for="codigo_barras">Código de Barras:</label>
                                    <input type="text" id="codigo_barras" name="codigo_barras" required class="form-control">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary form-control">
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
                                <i class="fas fa-info-circle"></i> Información del Producto
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre del Producto:</label>
                                    <input type="text" id="nombre_producto" readonly class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Categoría:</label>
                                    <input type="text" id="categoria_producto" readonly class="form-control">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Departamento:</label>
                                    <input type="text" id="departamento_producto" readonly class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-box"></i> Actualización de Stock
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Stock Actual:</label>
                                    <input type="number" id="stock_actual" readonly class="form-control">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="nueva_cantidad">Cantidad a Agregar: *</label>
                                    <input type="number" id="nueva_cantidad" name="nueva_cantidad" required min="1" class="form-control">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="tipo_operacion">Tipo de Operación: *</label>
                                    <select id="tipo_operacion" name="tipo_operacion" required class="form-control" onchange="actualizarIconoOperacion()">
                                        <option value="agregar" class="tipo-operacion-agregar">
                                            <i class="fas fa-plus"></i> Agregar Stock
                                        </option>
                                        <option value="restar" class="tipo-operacion-restar">
                                            <i class="fas fa-minus"></i> Restar Stock
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Stock Resultante:</label>
                                    <input type="number" id="stock_resultante" readonly class="form-control readonly-field">
                                    <div class="stock-warning" style="display: none;">
                                        El stock no puede ser negativo
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="fas fa-dollar-sign"></i> Información de Precios
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="nuevo_precio_costo">Nuevo Precio Costo: *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" id="nuevo_precio_costo" name="nuevo_precio_costo" required class="form-control">
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="nuevo_margen">Nuevo Margen (%): *</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" id="nuevo_margen" name="nuevo_margen" required class="form-control">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="nuevo_impuesto">Nuevo IVA (%): *</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" id="nuevo_impuesto" name="nuevo_impuesto" required class="form-control">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Nuevo Precio Venta:</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" id="nuevo_precio_venta" readonly class="form-control">
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
            // Función para calcular precio de venta
            function calcularPrecioVenta() {
                const precioCosto = parseFloat($('#nuevo_precio_costo').val()) || 0;
                const margen = parseFloat($('#nuevo_margen').val()) || 0;
                const impuesto = parseFloat($('#nuevo_impuesto').val()) || 0;
                
                const precioBase = precioCosto * (1 + (margen / 100));
                const precioVenta = precioBase * (1 + (impuesto / 100));
                
                $('#nuevo_precio_venta').val(precioVenta.toFixed(2));
            }

            // Calcular stock resultante
            function calcularStockResultante() {
                const stockActual = parseInt($('#stock_actual').val()) || 0;
                const nuevaCantidad = parseInt($('#nueva_cantidad').val()) || 0;
                const tipoOperacion = $('#tipo_operacion').val();
                const stockResultante = document.getElementById('stock_resultante');
                const stockWarning = document.querySelector('.stock-warning');
                
                const resultado = tipoOperacion === 'agregar' ? 
                    stockActual + nuevaCantidad : 
                    stockActual - nuevaCantidad;
                
                stockResultante.value = resultado;
                stockResultante.classList.add('stock-changed');
                
                // Validar stock negativo
                if (resultado < 0) {
                    stockResultante.classList.add('is-invalid');
                    stockWarning.style.display = 'block';
                    $('#nueva_cantidad').addClass('is-invalid');
                } else {
                    stockResultante.classList.remove('is-invalid');
                    stockWarning.style.display = 'none';
                    $('#nueva_cantidad').removeClass('is-invalid');
                }
                
                // Remover la animación después de que termine
                setTimeout(() => {
                    stockResultante.classList.remove('stock-changed');
                }, 1000);
            }

            // Event listeners para cálculos automáticos
            $('#nuevo_precio_costo, #nuevo_margen, #nuevo_impuesto').on('input', calcularPrecioVenta);
            $('#nueva_cantidad').on('input', calcularStockResultante);
            $('#tipo_operacion').on('change', calcularStockResultante);

            // Buscar producto
            $('#buscarProductoForm').on('submit', function(e) {
                e.preventDefault();
                const codigo_barras = $('#codigo_barras').val();

                $.ajax({
                    url: 'surtir.php',
                    method: 'POST',
                    data: {
                        action: 'buscar_producto',
                        codigo_barras: codigo_barras
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const producto = response.producto;
                            $('#producto_id').val(producto.id);
                            $('#nombre_producto').val(producto.nombre);
                            $('#categoria_producto').val(producto.categoria_nombre);
                            $('#departamento_producto').val(producto.departamento_nombre);
                            $('#stock_actual').val(producto.stock);
                            $('#nuevo_precio_costo').val(producto.precio_costo);
                            $('#nuevo_margen').val(producto.margen_ganancia);
                            $('#nuevo_impuesto').val(producto.impuesto);
                            $('#nueva_descripcion').val(producto.descripcion);
                            
                            calcularPrecioVenta();
                            $('#actualizarStockForm').show();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Hubo un problema al buscar el producto.', 'error');
                    }
                });
            });

            // Actualizar stock
            $('#stockForm').on('submit', function(e) {
                e.preventDefault();
                
                const stockResultante = parseInt($('#stock_resultante').val());
                if (stockResultante < 0) {
                    Swal.fire('Error', 'El stock no puede quedar en negativo', 'error');
                    return;
                }
                
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
                        const formData = {
                            action: 'actualizar_stock',
                            id: $('#producto_id').val(),
                            nueva_cantidad: $('#nueva_cantidad').val(),
                            nuevo_precio_costo: $('#nuevo_precio_costo').val(),
                            nuevo_margen: $('#nuevo_margen').val(),
                            nuevo_impuesto: $('#nuevo_impuesto').val(),
                            nueva_descripcion: $('#nueva_descripcion').val(),
                            tipo_operacion: $('#tipo_operacion').val()
                        };

                        $.ajax({
                            url: 'surtir.php',
                            method: 'POST',
                            data: formData,
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Éxito',
                                        text: response.message
                                    }).then(() => {
                                        limpiarFormulario();
                                    });
                                } else {
                                    Swal.fire('Error', response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Hubo un problema al actualizar el stock.', 'error');
                            }
                        });
                    }
                });
            });
        });

        function limpiarFormulario() {
            $('#buscarProductoForm')[0].reset();
            $('#stockForm')[0].reset();
            $('#actualizarStockForm').hide();
        }

        function actualizarIconoOperacion() {
            const select = document.getElementById('tipo_operacion');
            const stockResultante = document.getElementById('stock_resultante');
            const stockWarning = document.querySelector('.stock-warning');
            
            // Actualizar clases y estilos según la operación
            if (select.value === 'restar') {
                select.className = 'form-control tipo-operacion-restar';
                calcularStockResultante(); // Recalcular para validar stock negativo
            } else {
                select.className = 'form-control tipo-operacion-agregar';
                stockResultante.classList.remove('is-invalid');
                stockWarning.style.display = 'none';
            }
        }
    </script>
</body>

</html>