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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#1e293b',
                        accent: '#3b82f6'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Encabezado -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Surtir Inventario</h1>
                        <p class="mt-2 text-sm text-gray-600">
                            Actualiza el stock y precios de tus productos
                        </p>
                    </div>
                    <a href="index.php" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver
                    </a>
                </div>

                <!-- Formulario de búsqueda mejorado -->
                <form id="buscarProductoForm" class="bg-white rounded-xl shadow-lg p-6 mb-8 transform transition-all hover:shadow-xl">
                    <div class="space-y-6">
                        <div class="flex items-center gap-2 pb-2 border-b border-gray-200">
                            <i class="fas fa-search text-blue-500 text-xl"></i>
                            <h2 class="text-xl font-semibold text-gray-800">Buscar Producto</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                            <div class="col-span-2">
                                <label for="codigo_barras" class="block text-sm font-medium text-gray-700 mb-2">
                                    Código de Barras
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-barcode text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           id="codigo_barras" 
                                           name="codigo_barras" 
                                           required
                                           class="block w-full pl-10 pr-3 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                           placeholder="Escanea o ingresa el código de barras"
                                           autofocus>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Puedes usar un escáner o ingresar el código manualmente
                                </p>
                            </div>
                            <div>
                                <button type="submit" 
                                        class="w-full inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-105">
                                    <i class="fas fa-search mr-2"></i>
                                    Buscar Producto
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Formulario de actualización mejorado -->
                <div id="actualizarStockForm" style="display: none;">
                    <form id="stockForm" class="bg-white rounded-xl shadow-lg p-8 space-y-8 transform transition-all">
                        <input type="hidden" id="producto_id" name="id">
                        
                        <!-- Información del Producto -->
                        <div class="bg-blue-50 rounded-lg p-6 border border-blue-100">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fas fa-box text-blue-600 text-xl"></i>
                                <h2 class="text-xl font-semibold text-gray-800">Información del Producto</h2>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Nombre del Producto
                                    </label>
                                    <input type="text" 
                                           id="nombre_producto" 
                                           readonly 
                                           class="block w-full px-4 py-3 rounded-lg border-gray-300 bg-white text-gray-700 font-medium shadow-sm">
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Stock Actual
                                    </label>
                                    <div class="relative">
                                        <input type="number" 
                                               id="stock_actual" 
                                               readonly 
                                               class="block w-full px-4 py-3 rounded-lg border-gray-300 bg-white text-gray-700 font-medium shadow-sm">
                                        <div id="stockStatus" class="absolute -top-1 right-0 transform translate-y-full"></div>
                                    </div>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Stock Mínimo
                                    </label>
                                    <input type="number" 
                                           id="stock_minimo" 
                                           readonly 
                                           class="block w-full px-4 py-3 rounded-lg border-gray-300 bg-white text-gray-700 font-medium shadow-sm">
                                </div>
                            </div>
                        </div>

                        <!-- Actualización de Stock -->
                        <div class="bg-green-50 rounded-lg p-6 border border-green-100">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fas fa-edit text-green-600 text-xl"></i>
                                <h2 class="text-xl font-semibold text-gray-800">Actualización de Stock</h2>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Tipo de Operación
                                    </label>
                                    <select id="tipo_operacion" 
                                            class="block w-full px-4 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 shadow-sm">
                                        <option value="agregar" class="py-2">
                                            <i class="fas fa-plus"></i> Agregar Stock
                                        </option>
                                        <option value="restar" class="py-2">
                                            <i class="fas fa-minus"></i> Restar Stock
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Cantidad
                                    </label>
                                    <input type="number" 
                                           id="nueva_cantidad" 
                                           min="1" 
                                           required
                                           class="block w-full px-4 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 shadow-sm">
                                    <p class="text-sm text-gray-500">Ingrese la cantidad a modificar</p>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Stock Resultante
                                    </label>
                                    <input type="number" 
                                           id="stock_resultante" 
                                           readonly 
                                           class="block w-full px-4 py-3 rounded-lg border-gray-300 bg-gray-50 text-gray-700 font-medium shadow-sm">
                                    <div class="stock-warning hidden mt-1 text-sm text-red-600 font-medium">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        El stock no puede ser negativo
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Precios -->
                        <div class="bg-yellow-50 rounded-lg p-6 border border-yellow-100">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                                <h2 class="text-xl font-semibold text-gray-800">Actualización de Precios</h2>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Precio de Costo
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">$</span>
                                        </div>
                                        <input type="number" 
                                               id="nuevo_precio_costo" 
                                               step="0.01" 
                                               required
                                               class="block w-full pl-8 pr-3 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 shadow-sm">
                                    </div>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Margen (%)
                                    </label>
                                    <div class="relative">
                                        <input type="number" 
                                               id="nuevo_margen" 
                                               step="0.01" 
                                               required
                                               class="block w-full px-4 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 shadow-sm">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">%</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        IVA (%)
                                    </label>
                                    <div class="relative">
                                        <input type="number" 
                                               id="nuevo_impuesto" 
                                               step="0.01" 
                                               required
                                               value="18"
                                               class="block w-full px-4 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 shadow-sm">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">%</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Precio de Venta
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">$</span>
                                        </div>
                                        <input type="number" 
                                               id="nuevo_precio_venta" 
                                               step="0.01"
                                               class="block w-full pl-8 pr-3 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="bg-purple-50 rounded-lg p-6 border border-purple-100">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fas fa-align-left text-purple-600 text-xl"></i>
                                <h2 class="text-xl font-semibold text-gray-800">Descripción</h2>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Nueva Descripción
                                </label>
                                <textarea id="nueva_descripcion" 
                                          rows="4" 
                                          class="block w-full px-4 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 shadow-sm resize-none"
                                          placeholder="Actualiza la descripción del producto..."></textarea>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="flex justify-end gap-4 pt-6">
                            <button type="button" 
                                    onclick="limpiarFormulario()"
                                    class="inline-flex items-center px-6 py-3 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                                <i class="fas fa-undo mr-2"></i>
                                Limpiar
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-105">
                                <i class="fas fa-save mr-2"></i>
                                Actualizar Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Mantener el JavaScript existente pero actualizar las clases CSS -->
    <script>
        $(document).ready(function() {
            // Elementos del DOM
            const elements = {
                buscarForm: $('#buscarProductoForm'),
                actualizarForm: $('#actualizarStockForm'),
                stockForm: $('#stockForm'),
                codigoBarras: $('#codigo_barras'),
                // ... (resto de elementos)
            };

            // Manejar la búsqueda del producto
            elements.buscarForm.on('submit', function(e) {
                e.preventDefault();
                
                const codigo = elements.codigoBarras.val().trim();
                if (!codigo) {
                    Swal.fire('Error', 'Ingrese un código de barras', 'error');
                    return;
                }

                $.ajax({
                    url: 'surtir.php',
                    method: 'POST',
                    data: {
                        action: 'buscar_producto',
                        codigo_barras: codigo
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Mostrar el formulario de actualización
                            elements.actualizarForm.show();
                            
                            // Llenar los campos con la información del producto
                            $('#producto_id').val(response.producto.id);
                            $('#nombre_producto').val(response.producto.nombre);
                            $('#stock_actual').val(response.producto.stock);
                            $('#stock_minimo').val(response.producto.stock_minimo);
                            $('#nuevo_precio_costo').val(response.producto.precio_costo);
                            $('#nuevo_margen').val(response.producto.margen_ganancia);
                            $('#nuevo_impuesto').val(response.producto.impuesto);
                            $('#nuevo_precio_venta').val(response.producto.precio_venta);
                            $('#nueva_descripcion').val(response.producto.descripcion);

                            // Actualizar el estado del stock
                            actualizarEstadoStock(response.producto.stock, response.producto.stock_minimo);
                            
                            // Calcular stock resultante inicial
                            calcularStockResultante();
                        } else {
                            Swal.fire('Error', response.message || 'Producto no encontrado', 'error');
                            elements.actualizarForm.hide();
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Error al buscar el producto', 'error');
                        elements.actualizarForm.hide();
                    }
                });
            });

            // Función para actualizar el estado visual del stock
            function actualizarEstadoStock(stock, stockMinimo) {
                const stockStatus = $('#stockStatus');
                let clase = '';
                let texto = '';

                if (stock === 0) {
                    clase = 'bg-red-100 text-red-800';
                    texto = 'Agotado';
                } else if (stock <= stockMinimo) {
                    clase = 'bg-yellow-100 text-yellow-800';
                    texto = 'Stock Bajo';
                } else {
                    clase = 'bg-green-100 text-green-800';
                    texto = 'Stock Normal';
                }

                stockStatus.attr('class', 'px-2 py-1 rounded-full text-xs font-medium ' + clase);
                stockStatus.text(texto);
            }

            // Calcular stock resultante cuando cambie la cantidad o tipo de operación
            $('#nueva_cantidad, #tipo_operacion').on('input change', calcularStockResultante);

            function calcularStockResultante() {
                const stockActual = parseInt($('#stock_actual').val()) || 0;
                const nuevaCantidad = parseInt($('#nueva_cantidad').val()) || 0;
                const tipoOperacion = $('#tipo_operacion').val();
                
                const resultado = tipoOperacion === 'agregar' ? 
                    stockActual + nuevaCantidad : 
                    stockActual - nuevaCantidad;
                
                $('#stock_resultante').val(resultado);
                
                // Validar stock negativo
                const stockWarning = $('.stock-warning');
                if (resultado < 0) {
                    stockWarning.removeClass('hidden').addClass('block');
                    $('#stock_resultante').addClass('border-red-500');
                } else {
                    stockWarning.removeClass('block').addClass('hidden');
                    $('#stock_resultante').removeClass('border-red-500');
                }
            }

            // Manejar el envío del formulario de actualización
            elements.stockForm.on('submit', function(e) {
                e.preventDefault();
                
                const stockResultante = parseInt($('#stock_resultante').val());
                if (stockResultante < 0) {
                    Swal.fire('Error', 'El stock resultante no puede ser negativo', 'error');
                    return;
                }

                const formData = {
                    action: 'actualizar_stock',
                    id: $('#producto_id').val(),
                    nueva_cantidad: $('#nueva_cantidad').val(),
                    nuevo_precio_costo: $('#nuevo_precio_costo').val(),
                    nuevo_margen: $('#nuevo_margen').val(),
                    nuevo_impuesto: $('#nuevo_impuesto').val(),
                    nueva_descripcion: $('#nueva_descripcion').val(),
                    tipo_operacion: $('#tipo_operacion').val(),
                    nuevo_precio_venta: $('#nuevo_precio_venta').val()
                };

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
                        $.ajax({
                            url: 'surtir.php',
                            method: 'POST',
                            data: formData,
                            success: function(response) {
                                try {
                                    const data = JSON.parse(response);
                                    if (data.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: '¡Éxito!',
                                            text: data.message,
                                            showConfirmButton: false,
                                            timer: 1500
                                        }).then(() => {
                                            limpiarFormulario();
                                        });
                                    } else {
                                        Swal.fire('Error', data.message, 'error');
                                    }
                                } catch (e) {
                                    Swal.fire('Error', 'Error al procesar la respuesta', 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Error al enviar la solicitud', 'error');
                            }
                        });
                    }
                });
            });

            // Función para limpiar el formulario
            window.limpiarFormulario = function() {
                elements.buscarForm[0].reset();
                elements.stockForm[0].reset();
                elements.actualizarForm.hide();
            };
        });
    </script>
</body>
</html>