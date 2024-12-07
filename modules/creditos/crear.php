<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$success_message = '';
$clientes = [];

try {
    // Obtener listado de clientes
    $query = "SELECT id, 
                     CONCAT(COALESCE(primer_nombre, ''), ' ', 
                           COALESCE(segundo_nombre, ''), ' ', 
                           COALESCE(apellidos, '')) as nombre, 
                     identificacion, 
                     tipo_identificacion, 
                     telefono, 
                     email 
              FROM clientes 
              WHERE user_id = ? 
              ORDER BY primer_nombre, apellidos";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($clientes)) {
        $error_message = "No hay clientes registrados. Por favor, registre al menos un cliente antes de crear un crédito.";
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar datos del formulario
        if (empty($_POST['cliente_id'])) {
            throw new Exception("Debe seleccionar un cliente");
        }
        if (empty($_POST['fecha'])) {
            throw new Exception("Debe especificar una fecha");
        }
        if (empty($_POST['productos'])) {
            throw new Exception("Debe agregar al menos un producto");
        }
        if (empty($_POST['plazo']) || !is_numeric($_POST['plazo'])) {
            throw new Exception("El plazo debe ser un número válido");
        }
        if (!isset($_POST['interes']) || !is_numeric($_POST['interes'])) {
            throw new Exception("El interés debe ser un número válido");
        }
        if (empty($_POST['cuotas']) || !is_numeric($_POST['cuotas'])) {
            throw new Exception("El número de cuotas debe ser válido");
        }

        $cliente_id = $_POST['cliente_id'];
        $fecha = $_POST['fecha'];
        $productos = $_POST['productos'];
        $plazo = intval($_POST['plazo']);
        $interes = floatval($_POST['interes']);
        $cuotas = intval($_POST['cuotas']);

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
            // Generar número de factura
            $fecha_formato = date('Ymd');
            $query = "SELECT MAX(CAST(SUBSTRING_INDEX(numero_factura, '-', -1) AS UNSIGNED)) as ultimo 
                     FROM ventas 
                     WHERE numero_factura LIKE ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute(["VNT-$fecha_formato-%"]);
            $resultado = $stmt->fetch();
            $siguiente = ($resultado['ultimo'] ?? 0) + 1;
            $numero_factura = sprintf("VNT-%s-%03d", $fecha_formato, $siguiente);

            // Insertar la venta
            $query = "INSERT INTO ventas (
                user_id,
                cliente_id,
                total,
                subtotal,
                descuento,
                metodo_pago,
                fecha,
                numero_factura,
                tipo_documento,
                estado_factura
            ) VALUES (
                ?, ?, ?, ?, 0, 'Crédito', ?, ?, 'Factura', 'EMITIDA'
            )";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'],
                $cliente_id,
                $total,
                $total, // subtotal igual al total ya que no hay descuento
                $fecha,
                $numero_factura
            ]);
            
            $venta_id = $pdo->lastInsertId();

            // Insertar los detalles de la venta
            $query = "INSERT INTO venta_detalles (
                venta_id,
                producto_id,
                cantidad,
                precio_unitario
            ) VALUES (?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($query);
            foreach ($productos as $producto) {
                $stmt->execute([
                    $venta_id,
                    $producto['id'],
                    $producto['cantidad'],
                    $producto['precio']
                ]);
            }

            // Calcular valores del crédito
            $monto_total = $total * (1 + ($interes / 100));
            $fecha_inicio = $fecha;
            $fecha_vencimiento = date('Y-m-d', strtotime($fecha_inicio . " +$plazo days"));
            $valor_cuota = $monto_total / $cuotas;

            // Insertar el crédito
            $query = "INSERT INTO creditos (
                venta_id, plazo, interes, monto_total, saldo_pendiente,
                fecha_inicio, fecha_vencimiento, cuotas, valor_cuota, estado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $venta_id,
                $plazo,
                $interes,
                $monto_total,
                $monto_total,
                $fecha_inicio,
                $fecha_vencimiento,
                $cuotas,
                $valor_cuota
            ]);
            
            $credito_id = $pdo->lastInsertId();

            // Generar plan de pagos
            $fecha_cuota = $fecha_inicio;
            $intervalo_dias = floor($plazo / $cuotas);

            for ($i = 1; $i <= $cuotas; $i++) {
                $fecha_cuota = date('Y-m-d', strtotime($fecha_cuota . " +$intervalo_dias days"));
                
                $query = "INSERT INTO creditos_pagos (
                    credito_id, 
                    numero_cuota, 
                    monto, 
                    interes_pagado,
                    capital_pagado, 
                    fecha_vencimiento_cuota,
                    estado
                ) VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')";
                
                $interes_cuota = ($valor_cuota * $interes) / 100;
                $capital_cuota = $valor_cuota - $interes_cuota;

                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    $credito_id,
                    $i,
                    $valor_cuota,
                    $interes_cuota,
                    $capital_cuota,
                    $fecha_cuota
                ]);
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Crédito creado exitosamente";
            header("Location: ver.php?id=" . $credito_id);
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
    <title>Nuevo Crédito | VendEasy</title>
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
                        <h2 class="text-2xl font-bold text-gray-800">Nuevo Crédito</h2>
                        <a href="index.php" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </a>
                    </div>

                    <form id="creditoForm" method="POST" class="space-y-6">
                        <!-- Información básica -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-gray-50 p-4 rounded-lg">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                                <select name="cliente_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= htmlspecialchars($cliente['id']) ?>">
                                            <?= htmlspecialchars($cliente['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha *</label>
                                <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Plazo (días) *</label>
                                <input type="number" name="plazo" required min="1" value="30"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Interés (%) *</label>
                                <input type="number" name="interes" required min="0" step="0.01" value="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número de Cuotas *</label>
                                <input type="number" name="cuotas" required min="1" value="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- Productos -->
                        <div class="border-t pt-6">
                            <h4 class="text-lg font-medium mb-4 text-gray-700">Agregar Productos</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-lg mb-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Buscar Producto</label>
                                    <div class="relative">
                                        <input type="text" id="producto_busqueda" 
                                               placeholder="Buscar por nombre o código"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        <div id="sugerencias_productos" 
                                             class="hidden absolute z-10 w-full mt-1 bg-white shadow-lg max-h-60 rounded-md py-1 text-sm overflow-auto">
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                                    <div class="flex">
                                        <input type="number" id="cantidad_producto" min="1" step="0.01" value="1"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-l-md focus:ring-blue-500 focus:border-blue-500">
                                        <button type="button" onclick="agregarProductoSeleccionado()"
                                                class="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700 transition-colors">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabla de productos -->
                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ítem</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Referencia</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cant.</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productos_container" class="bg-white divide-y divide-gray-200">
                                        <!-- Los productos se agregarán aquí dinámicamente -->
                                    </tbody>
                                    <tfoot class="bg-gray-50">
                                        <tr>
                                            <td colspan="5" class="px-3 py-2 text-right font-medium">Total:</td>
                                            <td class="px-3 py-2 font-bold" id="total_credito">$0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="flex justify-end space-x-4 pt-6 border-t">
                            <a href="index.php" 
                               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                Cancelar
                            </a>
                            <button type="submit"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Crear Crédito
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let productoSeleccionado = null;
        let productos = [];

        // Función para buscar productos
        function buscarProductos(busqueda) {
            if (busqueda.length < 2) {
                document.getElementById('sugerencias_productos').classList.add('hidden');
                return;
            }

            fetch(`../cotizaciones/buscar_productos.php?q=${encodeURIComponent(busqueda)}`)
                .then(response => response.json())
                .then(data => {
                    const sugerencias = document.getElementById('sugerencias_productos');
                    sugerencias.innerHTML = '';

                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(producto => {
                            // Asegurarnos de que el precio sea un número válido
                            const precio = parseFloat(producto.precio || producto.precio_venta || 0);
                            const div = document.createElement('div');
                            div.className = 'cursor-pointer p-2 hover:bg-gray-100';
                            div.innerHTML = `
                                <div class="flex justify-between">
                                    <span>${producto.nombre}</span>
                                    <span class="text-gray-600">$${precio.toFixed(2)}</span>
                                </div>
                                <div class="text-sm text-gray-500">Stock: ${producto.stock || 0}</div>
                            `;
                            div.onclick = () => seleccionarProducto({
                                ...producto,
                                precio_venta: precio // Asegurarnos de que el precio esté definido
                            });
                            sugerencias.appendChild(div);
                        });
                        sugerencias.classList.remove('hidden');
                    } else {
                        sugerencias.innerHTML = '<div class="p-2 text-gray-500">No se encontraron productos</div>';
                        sugerencias.classList.remove('hidden');
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Función para seleccionar un producto
        function seleccionarProducto(producto) {
            productoSeleccionado = producto;
            document.getElementById('producto_busqueda').value = producto.nombre;
            document.getElementById('sugerencias_productos').classList.add('hidden');
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

            // Asegurarnos de que el precio sea un número válido
            const precio = parseFloat(productoSeleccionado.precio || productoSeleccionado.precio_venta || 0);
            const subtotal = cantidad * precio;
            const productoIndex = productos.length;

            productos.push({
                id: productoSeleccionado.id,
                nombre: productoSeleccionado.nombre,
                codigo: productoSeleccionado.codigo_barras || productoSeleccionado.codigo || '-',
                cantidad: cantidad,
                precio: precio,
                subtotal: subtotal
            });

            actualizarTablaProductos();
            actualizarTotal();

            // Limpiar selección
            productoSeleccionado = null;
            document.getElementById('producto_busqueda').value = '';
            document.getElementById('cantidad_producto').value = '1';
        }

        // Función para actualizar la tabla de productos
        function actualizarTablaProductos() {
            const tbody = document.getElementById('productos_container');
            tbody.innerHTML = '';

            productos.forEach((producto, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-3 py-2">${index + 1}
                        <input type="hidden" name="productos[${index}][id]" value="${producto.id}">
                    </td>
                    <td class="px-3 py-2">${producto.codigo || '-'}</td>
                    <td class="px-3 py-2">$${producto.precio.toFixed(2)}
                        <input type="hidden" name="productos[${index}][precio]" value="${producto.precio}">
                    </td>
                    <td class="px-3 py-2">${producto.nombre}
                        <input type="hidden" name="productos[${index}][nombre]" value="${producto.nombre}">
                    </td>
                    <td class="px-3 py-2">
                        <input type="number" name="productos[${index}][cantidad]" value="${producto.cantidad}"
                               min="1" step="0.01" onchange="actualizarCantidad(${index}, this.value)"
                               class="w-20 px-2 py-1 border border-gray-300 rounded-md">
                    </td>
                    <td class="px-3 py-2">$${producto.subtotal.toFixed(2)}</td>
                    <td class="px-3 py-2">
                        <button type="button" onclick="eliminarProducto(${index})"
                                class="text-red-600 hover:text-red-900">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Función para actualizar cantidad
        function actualizarCantidad(index, nuevaCantidad) {
            cantidad = parseFloat(nuevaCantidad);
            if (isNaN(cantidad) || cantidad <= 0) {
                alert('La cantidad debe ser mayor a 0');
                actualizarTablaProductos();
                return;
            }
            productos[index].cantidad = cantidad;
            productos[index].subtotal = cantidad * productos[index].precio;
            actualizarTablaProductos();
            actualizarTotal();
        }

        // Función para eliminar producto
        function eliminarProducto(index) {
            productos.splice(index, 1);
            actualizarTablaProductos();
            actualizarTotal();
        }

        // Función para actualizar el total
        function actualizarTotal() {
            const total = productos.reduce((sum, producto) => sum + producto.subtotal, 0);
            document.getElementById('total_credito').textContent = `$${total.toFixed(2)}`;
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

        // Validación del formulario
        document.getElementById('creditoForm').addEventListener('submit', function(e) {
            if (productos.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos un producto al crédito');
            }
        });
    </script>
</body>
</html>