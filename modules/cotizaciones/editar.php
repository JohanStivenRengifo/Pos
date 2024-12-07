<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$success_message = '';
$cotizacion = null;
$detalles = [];
$clientes = [];

try {
    if (!isset($_GET['id'])) {
        throw new Exception("ID de cotización no especificado");
    }

    // Obtener listado de clientes
    $query = "SELECT id, CONCAT(COALESCE(primer_nombre, ''), ' ', COALESCE(segundo_nombre, ''), ' ', COALESCE(apellidos, '')) as nombre,
                     identificacion, tipo_identificacion 
              FROM clientes 
              WHERE user_id = ?
              ORDER BY primer_nombre, apellidos";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener la cotización
    $query = "SELECT c.*, 
                     CONCAT(cl.primer_nombre, ' ', cl.segundo_nombre, ' ', cl.apellidos) as cliente_nombre
              FROM cotizaciones c
              LEFT JOIN clientes cl ON c.cliente_id = cl.id
              WHERE c.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $cotizacion = $stmt->fetch();

    if (!$cotizacion) {
        throw new Exception("Cotización no encontrada");
    }

    // Obtener los detalles de la cotización
    $query = "SELECT cd.*, 
                     i.codigo_barras, 
                     i.nombre as producto_nombre, 
                     i.precio_venta,
                     i.impuesto 
              FROM cotizacion_detalles cd
              LEFT JOIN inventario i ON cd.producto_id = i.id
              WHERE cd.cotizacion_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET['id']]);
    $detalles = $stmt->fetchAll();

    // Procesar el formulario cuando se envía
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cliente_id = $_POST['cliente_id'];
        $fecha = $_POST['fecha'];
        $productos = $_POST['productos'];
        
        // Calcular el total
        $total = 0;
        foreach ($productos as $producto) {
            if (!is_numeric($producto['cantidad']) || !is_numeric($producto['precio'])) {
                throw new Exception("Los valores de cantidad y precio deben ser numéricos");
            }
            $subtotal = $producto['cantidad'] * $producto['precio'];
            $total += $subtotal;
        }

        // Iniciar transacción
        $pdo->beginTransaction();

        try {
            // Actualizar la cotización
            $query = "UPDATE cotizaciones 
                     SET cliente_id = ?, fecha = ?, total = ?
                     WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$cliente_id, $fecha, $total, $_GET['id']]);

            // Eliminar detalles anteriores
            $query = "DELETE FROM cotizacion_detalles WHERE cotizacion_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_GET['id']]);

            // Insertar nuevos detalles
            $query = "INSERT INTO cotizacion_detalles (cotizacion_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            
            foreach ($productos as $producto) {
                $subtotal = $producto['cantidad'] * $producto['precio'];
                $descripcion = !empty($producto['descripcion']) ? $producto['descripcion'] : $producto['nombre'];
                
                $stmt->execute([
                    $_GET['id'],
                    $producto['id'],
                    $descripcion,
                    $producto['cantidad'],
                    $producto['precio'],
                    $subtotal
                ]);
            }

            $pdo->commit();
            $success_message = "Cotización actualizada exitosamente";
            header("Location: ver.php?id=" . $_GET['id']);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cotización | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../includes/sidebar.php'; ?>
            
            <div class="w-full lg:w-3/4 px-4">
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Editar Cotización #<?= htmlspecialchars($cotizacion['numero']) ?></h2>
                        <a href="index.php" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </a>
                    </div>

                    <form id="cotizacionForm" method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-4 rounded-lg">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                                <select name="cliente_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm">
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= htmlspecialchars($cliente['id']) ?>"
                                                <?= $cliente['id'] == $cotizacion['cliente_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cliente['nombre']) ?> 
                                            (<?= htmlspecialchars($cliente['tipo_identificacion'] . ': ' . $cliente['identificacion']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha *</label>
                                <input type="date" name="fecha" required
                                       value="<?= htmlspecialchars($cotizacion['fecha']) ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm">
                            </div>
                        </div>

                        <!-- Sección de Productos -->
                        <div class="border-t pt-6">
                            <h4 class="text-lg font-medium mb-4 text-gray-700">Productos</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-lg mb-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar Producto</label>
                                    <div class="relative">
                                        <input type="text" 
                                               id="producto_busqueda" 
                                               placeholder="Buscar por nombre o código" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        <div id="sugerencias_productos" 
                                             class="hidden absolute z-10 w-full mt-1 bg-white shadow-lg max-h-60 rounded-md py-1 text-sm overflow-auto">
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                                    <div class="flex">
                                        <input type="number" 
                                               id="cantidad_producto" 
                                               min="1" 
                                               step="0.01" 
                                               value="1"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-l-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        <button type="button" 
                                                onclick="agregarProductoSeleccionado()"
                                                class="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700 transition-colors">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de Productos -->
                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr class="text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <th class="px-3 py-2 text-left">Ítem</th>
                                            <th class="px-3 py-2 text-left">Referencia</th>
                                            <th class="px-3 py-2 text-left">Precio</th>
                                            <th class="px-3 py-2 text-left">Desc %</th>
                                            <th class="px-3 py-2 text-left">Imp.</th>
                                            <th class="px-3 py-2 text-left">Descripción</th>
                                            <th class="px-3 py-2 text-left">Cant.</th>
                                            <th class="px-3 py-2 text-left">Total</th>
                                            <th class="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productos_container" class="bg-white divide-y divide-gray-200 text-sm">
                                        <!-- Los productos se cargarán aquí -->
                                    </tbody>
                                    <tfoot class="bg-gray-50 text-sm font-medium">
                                        <tr>
                                            <td colspan="7" class="px-3 py-2 text-right">Subtotal:</td>
                                            <td class="px-3 py-2" id="subtotal_cotizacion">$0.00</td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="7" class="px-3 py-2 text-right">IVA:</td>
                                            <td class="px-3 py-2" id="iva_cotizacion">$0.00</td>
                                            <td></td>
                                        </tr>
                                        <tr class="font-bold">
                                            <td colspan="7" class="px-3 py-2 text-right">Total:</td>
                                            <td class="px-3 py-2" id="total_cotizacion">$0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t">
                            <a href="index.php" 
                               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm">
                                Cancelar
                            </a>
                            <button type="submit"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                                Actualizar Cotización
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    let productoSeleccionado = null;
    let productos = <?= json_encode($detalles) ?>;

    // Función para buscar productos
    function buscarProductos(busqueda) {
        if (busqueda.length < 2) {
            document.getElementById('sugerencias_productos').classList.add('hidden');
            return;
        }

        fetch(`buscar_productos.php?q=${encodeURIComponent(busqueda)}`)
            .then(response => response.json())
            .then(data => {
                const sugerencias = document.getElementById('sugerencias_productos');
                sugerencias.innerHTML = '';
                
                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(producto => {
                        const div = document.createElement('div');
                        div.className = 'cursor-pointer p-2 hover:bg-gray-100';
                        div.innerHTML = `
                            <div class="flex justify-between">
                                <span>${producto.nombre}</span>
                                <span class="text-gray-600">$${parseFloat(producto.precio_venta).toFixed(2)}</span>
                            </div>
                            <div class="text-sm text-gray-500">Código: ${producto.codigo_barras}</div>
                        `;
                        div.onclick = () => seleccionarProducto(producto);
                        sugerencias.appendChild(div);
                    });
                    
                    sugerencias.classList.remove('hidden');
                } else {
                    sugerencias.innerHTML = '<div class="p-2 text-gray-500">No se encontraron productos</div>';
                    sugerencias.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const sugerencias = document.getElementById('sugerencias_productos');
                sugerencias.innerHTML = '<div class="p-2 text-red-500">Error al buscar productos</div>';
                sugerencias.classList.remove('hidden');
            });
    }

    // Función para seleccionar un producto
    function seleccionarProducto(producto) {
        productoSeleccionado = {
            id: producto.id,
            nombre: producto.nombre,
            codigo_barras: producto.codigo_barras,
            descripcion: producto.descripcion || producto.nombre,
            precio: parseFloat(producto.precio_venta) || 0,
            impuesto: parseFloat(producto.impuesto) || 0
        };
        document.getElementById('producto_busqueda').value = producto.nombre;
        document.getElementById('sugerencias_productos').classList.add('hidden');
    }

    // Función para agregar producto a la tabla
    function agregarProductoATabla(producto, index) {
        const tbody = document.getElementById('productos_container');
        const tr = document.createElement('tr');
        
        // Asegurarse de que los valores numéricos sean válidos
        const precio = parseFloat(producto.precio) || 0;
        const cantidad = parseFloat(producto.cantidad) || 0;
        const descuento = parseFloat(producto.descuento) || 0;
        const impuesto = parseFloat(producto.impuesto) || 0;
        const subtotal = cantidad * precio;

        tr.innerHTML = `
            <td class="px-3 py-2 text-gray-900">
                ${index + 1}
                <input type="hidden" name="productos[${index}][id]" value="${producto.id}">
            </td>
            <td class="px-3 py-2 text-gray-600">
                ${producto.codigo_barras || ''}
                <input type="hidden" name="productos[${index}][codigo_barras]" value="${producto.codigo_barras || ''}">
            </td>
            <td class="px-3 py-2 text-gray-900">
                $${precio.toFixed(2)}
                <input type="hidden" name="productos[${index}][precio]" value="${precio}">
            </td>
            <td class="px-3 py-2">
                <input type="number" 
                       min="0" 
                       max="100" 
                       value="${descuento}"
                       class="w-14 px-1 py-0.5 border border-gray-300 rounded text-sm"
                       onchange="actualizarDescuento(this, ${index})"
                       name="productos[${index}][descuento]">%
            </td>
            <td class="px-3 py-2 text-gray-600">
                ${impuesto}%
                <input type="hidden" name="productos[${index}][impuesto]" value="${impuesto}">
            </td>
            <td class="px-3 py-2 text-gray-900">
                ${producto.descripcion || producto.nombre || ''}
                <input type="hidden" name="productos[${index}][nombre]" value="${producto.nombre || ''}">
                <input type="hidden" name="productos[${index}][descripcion]" value="${producto.descripcion || producto.nombre || ''}">
            </td>
            <td class="px-3 py-2">
                <input type="number" 
                       min="1" 
                       value="${cantidad}"
                       class="w-16 px-1 py-0.5 border border-gray-300 rounded text-sm"
                       onchange="actualizarCantidad(this, ${index})"
                       name="productos[${index}][cantidad]">
            </td>
            <td class="px-3 py-2 font-medium text-gray-900" id="total_producto_${index}">
                $${subtotal.toFixed(2)}
            </td>
            <td class="px-3 py-2">
                <button type="button" 
                        onclick="eliminarProducto(this)" 
                        class="text-red-600 hover:text-red-900 transition-colors">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    // Función para agregar producto seleccionado
    function agregarProductoSeleccionado() {
        if (!productoSeleccionado) {
            alert('Por favor, seleccione un producto primero');
            return;
        }

        const cantidad = parseFloat(document.getElementById('cantidad_producto').value);
        if (isNaN(cantidad) || cantidad <= 0) {
            alert('Por favor, ingrese una cantidad válida');
            return;
        }

        const precio = parseFloat(productoSeleccionado.precio);
        if (isNaN(precio) || precio <= 0) {
            alert('El producto no tiene un precio válido');
            return;
        }

        productoSeleccionado.cantidad = cantidad;
        agregarProductoATabla(productoSeleccionado, productos.length);
        productos.push(productoSeleccionado);

        // Limpiar selección
        productoSeleccionado = null;
        document.getElementById('producto_busqueda').value = '';
        document.getElementById('cantidad_producto').value = '1';
        
        actualizarTotales();
    }

    // Función para eliminar producto
    function eliminarProducto(button) {
        const fila = button.closest('tr');
        const index = Array.from(fila.parentNode.children).indexOf(fila);
        productos.splice(index, 1);
        fila.remove();
        actualizarTotales();
        
        // Actualizar índices de los productos restantes
        const filas = document.getElementById('productos_container').getElementsByTagName('tr');
        for (let i = 0; i < filas.length; i++) {
            actualizarIndicesProducto(filas[i], i);
        }
    }

    // Función para actualizar índices
    function actualizarIndicesProducto(fila, index) {
        fila.querySelector('td:first-child').textContent = index + 1;
        const inputs = fila.getElementsByTagName('input');
        for (let input of inputs) {
            input.name = input.name.replace(/\[\d+\]/, `[${index}]`);
        }
    }

    // Función para actualizar totales
    function actualizarTotales() {
        let subtotal = 0;
        let totalIva = 0;
        let total = 0;

        productos.forEach((producto, index) => {
            const precio = parseFloat(producto.precio) || 0;
            const cantidad = parseFloat(producto.cantidad) || 0;
            const descuento = parseFloat(producto.descuento) || 0;
            const impuesto = parseFloat(producto.impuesto) || 0;

            const precioConDescuento = precio * (1 - descuento/100);
            const subtotalProducto = cantidad * precioConDescuento;
            const ivaProducto = subtotalProducto * (impuesto/100);
            
            subtotal += subtotalProducto;
            totalIva += ivaProducto;
            total += subtotalProducto + ivaProducto;

            if (document.getElementById(`total_producto_${index}`)) {
                document.getElementById(`total_producto_${index}`).textContent = 
                    `$${(subtotalProducto + ivaProducto).toFixed(2)}`;
            }
        });

        document.getElementById('subtotal_cotizacion').textContent = `$${subtotal.toFixed(2)}`;
        document.getElementById('iva_cotizacion').textContent = `$${totalIva.toFixed(2)}`;
        document.getElementById('total_cotizacion').textContent = `$${total.toFixed(2)}`;
    }

    // Event listeners
    document.getElementById('producto_busqueda').addEventListener('input', (e) => {
        buscarProductos(e.target.value);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#sugerencias_productos') && !e.target.closest('#producto_busqueda')) {
            document.getElementById('sugerencias_productos').classList.add('hidden');
        }
    });

    // Cargar productos existentes
    window.addEventListener('DOMContentLoaded', function() {
        productos.forEach((producto, index) => {
            const productoFormateado = {
                id: producto.producto_id || 0,
                nombre: producto.producto_nombre || producto.descripcion || '',
                codigo_barras: producto.codigo_barras || '',
                descripcion: producto.descripcion || producto.producto_nombre || '',
                precio: parseFloat(producto.precio_unitario) || 0,
                cantidad: parseFloat(producto.cantidad) || 0,
                impuesto: parseFloat(producto.impuesto) || 0,
                descuento: parseFloat(producto.descuento) || 0
            };
            
            // Actualizar el array de productos con los valores formateados
            productos[index] = productoFormateado;
            
            agregarProductoATabla(productoFormateado, index);
        });
        actualizarTotales();
    });
    </script>
</body>
</html> 