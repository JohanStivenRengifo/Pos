<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? null;
$email = $_SESSION['email'] ?? null;

if (!$user_id) {
    header("Location: ../../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $venta_id = (int)$_GET['id'];

    // Obtener los detalles de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, c.nombre AS cliente_nombre, c.id AS cliente_id 
        FROM ventas v 
        LEFT JOIN clientes c ON v.cliente_id = c.id 
        WHERE v.id = ? AND v.user_id = ?
    ");
    $stmt->execute([$venta_id, $user_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($venta) {
        // Obtener los productos de la venta
        $stmt = $pdo->prepare("
            SELECT vd.*, i.nombre AS producto_nombre, i.stock 
            FROM venta_detalles vd 
            JOIN inventario i ON vd.producto_id = i.id 
            WHERE vd.venta_id = ?
        ");
        $stmt->execute([$venta_id]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener todos los productos del inventario
        $stmt = $pdo->prepare("
            SELECT id, nombre, precio_venta, stock 
            FROM inventario 
            WHERE user_id = ? AND stock > 0
            ORDER BY nombre ASC
        ");
        $stmt->execute([$user_id]);
        $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Venta | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-8">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-edit text-blue-500"></i>
                        Modificar Venta #<?= htmlspecialchars($venta['id']) ?>
                    </h2>
                </div>
                
                <form id="edit-form" class="space-y-6">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($venta['id']) ?>">
                    
                    <!-- Información de la venta -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user text-gray-400 mr-2"></i>Cliente
                            </label>
                            <input type="text" 
                                   value="<?= htmlspecialchars($venta['cliente_nombre'] ?? 'Cliente General') ?>" 
                                   class="w-full bg-white rounded-md border-gray-300 shadow-sm px-4 py-2" 
                                   readonly>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar text-gray-400 mr-2"></i>Fecha
                            </label>
                            <input type="text" 
                                   value="<?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?>" 
                                   class="w-full bg-white rounded-md border-gray-300 shadow-sm px-4 py-2" 
                                   readonly>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-receipt text-gray-400 mr-2"></i>N° Factura
                            </label>
                            <input type="text" 
                                   value="<?= htmlspecialchars($venta['numero_factura']) ?>" 
                                   class="w-full bg-white rounded-md border-gray-300 shadow-sm px-4 py-2" 
                                   readonly>
                        </div>
                    </div>

                    <!-- Tabla de productos -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Unitario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($productos as $producto): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <select name="productos[]" 
                                                class="producto-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                                required>
                                            <?php foreach ($inventario as $item): ?>
                                                <option value="<?= $item['id'] ?>" 
                                                        data-precio="<?= $item['precio_venta'] ?>"
                                                        data-stock="<?= $item['stock'] ?>"
                                                        <?= $item['id'] == $producto['producto_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($item['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-6 py-4">
                                        <input type="number" 
                                               name="cantidades[]" 
                                               value="<?= $producto['cantidad'] ?>" 
                                               min="1" 
                                               max="<?= $producto['stock'] + $producto['cantidad'] ?>" 
                                               class="cantidad-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                               required>
                                    </td>
                                    <td class="px-6 py-4">
                                        <input type="number" 
                                               name="precios[]" 
                                               value="<?= $producto['precio_unitario'] ?>" 
                                               step="0.01" 
                                               class="precio-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                               required>
                                    </td>
                                    <td class="px-6 py-4 subtotal font-medium">
                                        <?= number_format($producto['cantidad'] * $producto['precio_unitario'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <button type="button" 
                                                class="eliminar-producto text-red-600 hover:text-red-900 transition-colors">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Botón agregar producto -->
                    <div class="flex justify-start">
                        <button type="button" 
                                id="agregar-producto"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-plus mr-2"></i>
                            Agregar Producto
                        </button>
                    </div>

                    <!-- Total -->
                    <div class="flex justify-end items-center space-x-4 bg-gray-50 p-4 rounded-lg">
                        <label class="text-lg font-medium text-gray-700">Total:</label>
                        <input type="number" 
                               name="total" 
                               id="total" 
                               value="<?= $venta['total'] ?>" 
                               step="0.01" 
                               class="w-48 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-right font-bold" 
                               readonly>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-end space-x-4 pt-4">
                        <button type="button" 
                                onclick="window.location.href='index.php'"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    $(document).ready(function() {
        function actualizarTotal() {
            let total = 0;
            $('.subtotal').each(function() {
                total += parseFloat($(this).text()) || 0;
            });
            $('#total').val(total.toFixed(2));
        }

        function actualizarSubtotal(row) {
            const cantidad = parseFloat(row.find('.cantidad-input').val()) || 0;
            const precio = parseFloat(row.find('.precio-input').val()) || 0;
            const subtotal = cantidad * precio;
            row.find('.subtotal').text(subtotal.toFixed(2));
            actualizarTotal();
        }

        function validarStock(row) {
            const select = row.find('.producto-select');
            const cantidadInput = row.find('.cantidad-input');
            const stockDisponible = parseInt(select.find(':selected').data('stock'));
            const cantidad = parseInt(cantidadInput.val());
            
            if (cantidad > stockDisponible) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock insuficiente',
                    text: `Solo hay ${stockDisponible} unidades disponibles`,
                    customClass: {
                        popup: 'rounded-lg'
                    }
                });
                cantidadInput.val(stockDisponible);
                actualizarSubtotal(row);
            }
        }

        // Manejadores de eventos para elementos existentes y dinámicos
        $(document).on('input', '.cantidad-input, .precio-input', function() {
            const row = $(this).closest('tr');
            actualizarSubtotal(row);
            if ($(this).hasClass('cantidad-input')) {
                validarStock(row);
            }
        });

        $(document).on('change', '.producto-select', function() {
            const row = $(this).closest('tr');
            const precio = $(this).find(':selected').data('precio');
            row.find('.precio-input').val(precio);
            actualizarSubtotal(row);
        });

        // Manejador para eliminar productos (existentes y nuevos)
        $(document).on('click', '.eliminar-producto', function() {
            const row = $(this).closest('tr');
            Swal.fire({
                title: '¿Eliminar producto?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    popup: 'rounded-lg',
                    confirmButton: 'px-4 py-2 rounded-md',
                    cancelButton: 'px-4 py-2 rounded-md'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    row.remove();
                    actualizarTotal();
                }
            });
        });

        // Agregar nuevo producto
        $('#agregar-producto').on('click', function() {
            const newRow = `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <select name="productos[]" 
                                class="producto-select w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                required>
                            <option value="">Seleccione un producto</option>
                            <?php foreach ($inventario as $item): ?>
                                <option value="<?= $item['id'] ?>" 
                                        data-precio="<?= $item['precio_venta'] ?>"
                                        data-stock="<?= $item['stock'] ?>">
                                    <?= htmlspecialchars($item['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-6 py-4">
                        <input type="number" 
                               name="cantidades[]" 
                               value="1" 
                               min="1" 
                               class="cantidad-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                               required>
                    </td>
                    <td class="px-6 py-4">
                        <input type="number" 
                               name="precios[]" 
                               value="0" 
                               step="0.01" 
                               class="precio-input w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                               required>
                    </td>
                    <td class="px-6 py-4 subtotal font-medium">0.00</td>
                    <td class="px-6 py-4">
                        <button type="button" 
                                class="eliminar-producto text-red-600 hover:text-red-900 transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('table tbody').append(newRow);
        });

        $('#edit-form').on('submit', function(e) {
            e.preventDefault();
            
            if ($('.productos-table tbody tr').length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Debe agregar al menos un producto'
                });
                return;
            }

            Swal.fire({
                title: '¿Guardar cambios?',
                text: "¿Está seguro de guardar los cambios realizados?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4caf50',
                cancelButtonColor: '#f44336',
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'guardar_edicion.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                mostrarAlerta('success', response.message);
                            } else {
                                mostrarAlerta('error', response.message);
                            }
                        },
                        error: function() {
                            mostrarAlerta('error', 'Error al guardar los cambios');
                        }
                    });
                }
            });
        });

        // Actualizar las clases de SweetAlert2 para mantener consistencia con Tailwind
        function mostrarAlerta(tipo, mensaje) {
            Swal.fire({
                icon: tipo,
                title: tipo === 'success' ? '¡Éxito!' : 'Error',
                text: mensaje,
                confirmButtonColor: tipo === 'success' ? '#10B981' : '#EF4444',
                customClass: {
                    popup: 'rounded-lg',
                    confirmButton: 'px-4 py-2 rounded-md'
                }
            });
        }
    });
    </script>
</body>
</html>
<?php
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Venta no encontrada',
                confirmButtonColor: '#EF4444',
                customClass: {
                    popup: 'rounded-lg',
                    confirmButton: 'px-4 py-2 rounded-md'
                }
            }).then(() => {
                window.location.href = 'index.php';
            });
        </script>";
    }
} else {
    header("Location: index.php");
    exit();
}
?>