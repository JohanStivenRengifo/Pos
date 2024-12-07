<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$error_message = '';
$success_message = '';
$clientes = [];

try {
    // Verificar si la tabla clientes existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'clientes'");
    if ($checkTable->rowCount() == 0) {
        throw new Exception("La tabla 'clientes' no existe en la base de datos");
    }

    // Obtener listado de clientes asociados al usuario actual
    $query = "SELECT id, CONCAT(COALESCE(primer_nombre, ''), ' ', COALESCE(segundo_nombre, ''), ' ', COALESCE(apellidos, '')) as nombre,
                     identificacion, tipo_identificacion, telefono, email 
              FROM clientes 
              WHERE user_id = ?
              ORDER BY primer_nombre, apellidos";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($clientes)) {
        $error_message = "No hay clientes registrados. Por favor, registre al menos un cliente antes de crear una cotización.";
    }

    // Procesar el formulario cuando se envía
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verificar si las tablas necesarias existen
        $checkCotizaciones = $pdo->query("SHOW TABLES LIKE 'cotizaciones'");
        $checkDetalles = $pdo->query("SHOW TABLES LIKE 'cotizacion_detalles'");
        
        if ($checkCotizaciones->rowCount() == 0) {
            // Si la tabla cotizaciones no existe, la creamos
            $pdo->exec("CREATE TABLE IF NOT EXISTS cotizaciones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                numero VARCHAR(50) NOT NULL,
                cliente_id INT NOT NULL,
                fecha DATE NOT NULL,
                fecha_vencimiento DATE NOT NULL,
                total DECIMAL(10,2) NOT NULL DEFAULT 0,
                estado VARCHAR(20) NOT NULL DEFAULT 'Pendiente',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cliente_id) REFERENCES clientes(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if ($checkDetalles->rowCount() == 0) {
            // Si la tabla cotizacion_detalles no existe, la creamos
            $pdo->exec("CREATE TABLE IF NOT EXISTS cotizacion_detalles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cotizacion_id INT NOT NULL,
                producto_id INT NOT NULL,
                descripcion VARCHAR(255) NOT NULL,
                cantidad DECIMAL(10,2) NOT NULL,
                precio_unitario DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

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

        $cliente_id = $_POST['cliente_id'];
        $fecha = $_POST['fecha'];
        $fecha_vencimiento = date('Y-m-d', strtotime($fecha . ' + 30 days'));
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
            // Generar número de cotización
            $fecha_formato = date('Ymd');
            $query = "SELECT MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)) as ultimo
                     FROM cotizaciones 
                     WHERE numero LIKE ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute(["COT-$fecha_formato-%"]);
            $resultado = $stmt->fetch();
            $siguiente = ($resultado['ultimo'] ?? 0) + 1;
            $numero = sprintf("COT-%s-%03d", $fecha_formato, $siguiente);

            // Insertar la cotización
            $query = "INSERT INTO cotizaciones (numero, cliente_id, fecha, fecha_vencimiento, total, estado) 
                     VALUES (?, ?, ?, ?, ?, 'Pendiente')";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$numero, $cliente_id, $fecha, $fecha_vencimiento, $total]);
            $cotizacion_id = $pdo->lastInsertId();

            // Insertar los detalles de la cotización
            $query = "INSERT INTO cotizacion_detalles (cotizacion_id, producto_id, descripcion, cantidad, precio_unitario, subtotal) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            
            foreach ($productos as $producto) {
                $subtotal = $producto['cantidad'] * $producto['precio'];
                $descripcion = !empty($producto['descripcion']) ? $producto['descripcion'] : $producto['nombre'];
                
                $stmt->execute([
                    $cotizacion_id,
                    $producto['id'],
                    $descripcion,
                    $producto['cantidad'],
                    $producto['precio'],
                    $subtotal
                ]);
            }

            $pdo->commit();
            header("Location: ver.php?id=" . $cotizacion_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    error_log("Error en cotizaciones/crear.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Cotización | VendEasy</title>
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
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Nueva Cotización</h2>
                        <a href="index.php" class="text-blue-600 hover:text-blue-800 flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </a>
                    </div>

                    <form id="cotizacionForm" method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-gray-50 p-4 rounded-lg">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                                <select name="cliente_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm">
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= htmlspecialchars($cliente['id']) ?>">
                                            <?= htmlspecialchars($cliente['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Emisión *</label>
                                <input type="date" name="fecha" required
                                       value="<?= date('Y-m-d') ?>"
                                       onchange="actualizarFechaVencimiento(this.value)"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Vencimiento</label>
                                <input type="date" name="fecha_vencimiento" required readonly
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 focus:outline-none text-sm">
                            </div>
                        </div>

                        <div class="border-t pt-6">
                            <h4 class="text-lg font-medium mb-4 text-gray-700">Agregar Productos</h4>
                            
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
                                        <!-- Los productos se agregarán aquí dinámicamente -->
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
                                Guardar Cotización
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
                                <span class="text-gray-600">$${parseFloat(producto.precio).toFixed(2)}</span>
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
            precio: parseFloat(producto.precio) || 0,
            impuesto: parseFloat(producto.impuesto) || 0
        };
        document.getElementById('producto_busqueda').value = producto.nombre;
        document.getElementById('sugerencias_productos').classList.add('hidden');
    }

    // Función para agregar el producto seleccionado
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
        const descuento = 0; // Iniciamos en 0
        const impuesto = parseFloat(productoSeleccionado.impuesto || 0);
        
        const precioConDescuento = precio * (1 - descuento/100);
        const subtotalSinIva = cantidad * precioConDescuento;
        const valorIva = subtotalSinIva * (impuesto/100);
        const subtotal = subtotalSinIva + valorIva;
        
        const productoIndex = productos.length;

        // Agregar a la lista de productos
        productos.push({
            id: productoSeleccionado.id,
            item: productoIndex + 1,
            referencia: productoSeleccionado.codigo_barras,
            nombre: productoSeleccionado.nombre,
            descripcion: productoSeleccionado.descripcion || '',
            cantidad: cantidad,
            precio: precio,
            descuento: descuento,
            impuesto: impuesto,
            subtotalSinIva: subtotalSinIva,
            valorIva: valorIva,
            subtotal: subtotal
        });

        // Agregar fila a la tabla
        const tbody = document.getElementById('productos_container');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="px-6 py-4">
                ${productoIndex + 1}
                <input type="hidden" name="productos[${productoIndex}][id]" value="${productoSeleccionado.id}">
            </td>
            <td class="px-6 py-4">
                ${productoSeleccionado.codigo_barras}
                <input type="hidden" name="productos[${productoIndex}][referencia]" value="${productoSeleccionado.codigo_barras}">
            </td>
            <td class="px-6 py-4">
                $${precio.toFixed(2)}
                <input type="hidden" name="productos[${productoIndex}][precio]" value="${precio}">
            </td>
            <td class="px-6 py-4">
                <input type="number" 
                       min="0" 
                       max="100" 
                       value="${descuento}"
                       class="w-16 px-2 py-1 border border-gray-300 rounded-md"
                       onchange="actualizarDescuento(this, ${productoIndex})"
                       name="productos[${productoIndex}][descuento]">%
            </td>
            <td class="px-6 py-4">
                ${impuesto}%
                <input type="hidden" name="productos[${productoIndex}][impuesto]" value="${impuesto}">
            </td>
            <td class="px-6 py-4">
                ${productoSeleccionado.descripcion || productoSeleccionado.nombre}
                <input type="hidden" name="productos[${productoIndex}][descripcion]" value="${productoSeleccionado.descripcion || productoSeleccionado.nombre}">
            </td>
            <td class="px-6 py-4">
                <input type="number" 
                       min="1" 
                       value="${cantidad}"
                       class="w-20 px-2 py-1 border border-gray-300 rounded-md"
                       onchange="actualizarCantidad(this, ${productoIndex})"
                       name="productos[${productoIndex}][cantidad]">
            </td>
            <td class="px-6 py-4" id="total_producto_${productoIndex}">$${subtotal.toFixed(2)}</td>
            <td class="px-6 py-4">
                <button type="button" onclick="eliminarProducto(this)" class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);

        // Actualizar totales
        actualizarTotales();

        // Limpiar selección
        productoSeleccionado = null;
        document.getElementById('producto_busqueda').value = '';
        document.getElementById('cantidad_producto').value = '1';
    }

    // Función para eliminar producto
    function eliminarProducto(button) {
        const fila = button.closest('tr');
        const index = Array.from(fila.parentNode.children).indexOf(fila);
        productos.splice(index, 1);
        fila.remove();
        actualizarTotales();
    }

    // Función para actualizar totales
    function actualizarTotales() {
        const subtotalSinIva = productos.reduce((sum, producto) => sum + producto.subtotalSinIva, 0);
        const totalDescuentos = productos.reduce((sum, producto) => 
            sum + (producto.cantidad * producto.precio * producto.descuento/100), 0);
        const totalIva = productos.reduce((sum, producto) => sum + producto.valorIva, 0);
        const total = productos.reduce((sum, producto) => sum + producto.subtotal, 0);

        document.getElementById('subtotal_cotizacion').textContent = `$${subtotalSinIva.toFixed(2)}`;
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

    // Validación del formulario
    document.getElementById('cotizacionForm').addEventListener('submit', function(e) {
        if (productos.length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un producto a la cotización');
        }
    });

    // Agrega estas nuevas funciones para manejar los cambios:

    function actualizarDescuento(input, index) {
        const descuento = parseFloat(input.value) || 0;
        if (descuento < 0 || descuento > 100) {
            alert('El descuento debe estar entre 0 y 100');
            input.value = productos[index].descuento;
            return;
        }

        const producto = productos[index];
        producto.descuento = descuento;
        
        const precioConDescuento = producto.precio * (1 - descuento/100);
        producto.subtotalSinIva = producto.cantidad * precioConDescuento;
        producto.valorIva = producto.subtotalSinIva * (producto.impuesto/100);
        producto.subtotal = producto.subtotalSinIva + producto.valorIva;

        // Actualizar el total en la fila
        document.getElementById(`total_producto_${index}`).textContent = 
            `$${producto.subtotal.toFixed(2)}`;

        actualizarTotales();
    }

    function actualizarCantidad(input, index) {
        const cantidad = parseFloat(input.value) || 0;
        if (cantidad <= 0) {
            alert('La cantidad debe ser mayor a 0');
            input.value = productos[index].cantidad;
            return;
        }

        const producto = productos[index];
        producto.cantidad = cantidad;
        
        const precioConDescuento = producto.precio * (1 - producto.descuento/100);
        producto.subtotalSinIva = cantidad * precioConDescuento;
        producto.valorIva = producto.subtotalSinIva * (producto.impuesto/100);
        producto.subtotal = producto.subtotalSinIva + producto.valorIva;

        // Actualizar el total en la fila
        document.getElementById(`total_producto_${index}`).textContent = 
            `$${producto.subtotal.toFixed(2)}`;

        actualizarTotales();
    }

    function actualizarFechaVencimiento(fecha) {
        if (fecha) {
            // Crear objeto Date con la fecha seleccionada
            const fechaEmision = new Date(fecha);
            // Agregar 30 días
            fechaEmision.setDate(fechaEmision.getDate() + 30);
            // Formatear la fecha para el input date (YYYY-MM-DD)
            const fechaVencimiento = fechaEmision.toISOString().split('T')[0];
            // Actualizar el campo de fecha de vencimiento
            document.querySelector('input[name="fecha_vencimiento"]').value = fechaVencimiento;
        }
    }

    // Establecer fecha de vencimiento inicial
    document.addEventListener('DOMContentLoaded', function() {
        actualizarFechaVencimiento(document.querySelector('input[name="fecha"]').value);
    });
    </script>
</body>
</html> 