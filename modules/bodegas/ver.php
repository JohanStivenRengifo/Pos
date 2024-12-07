<?php
session_start();
require_once '../../config/db.php';

// Asignar $pdo a $conn para mantener consistencia
$conn = $pdo;

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Verificar que se recibió el ID de la bodega
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$bodega_id = intval($_GET['id']);
$bodega = null;
$productos = [];
$error_message = '';

try {
    // Obtener información de la bodega
    $stmt = $conn->prepare("
        SELECT * 
        FROM bodegas 
        WHERE id = ? AND usuario_id = ?
    ");
    $stmt->execute([$bodega_id, $_SESSION['user_id']]);
    $bodega = $stmt->fetch();

    if (!$bodega) {
        header('Location: index.php');
        exit;
    }

    // Obtener TODOS los productos del usuario
    $stmt = $conn->prepare("
        SELECT 
            id,
            codigo_barras,
            nombre,
            descripcion,
            unidad_medida,
            stock
        FROM inventario 
        WHERE user_id = ? 
        AND estado = 1
        ORDER BY nombre
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $todos_productos = $stmt->fetchAll();

    // Obtener productos asociados a la bodega
    $stmt = $conn->prepare("
        SELECT 
            ib.*,
            i.codigo_barras,
            i.nombre as producto_nombre,
            i.descripcion,
            i.unidad_medida,
            i.precio_venta,
            i.stock as stock_total
        FROM inventario_bodegas ib
        JOIN inventario i ON i.id = ib.producto_id
        WHERE ib.bodega_id = ?
        ORDER BY i.nombre
    ");
    $stmt->execute([$bodega_id]);
    $productos = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Error en ver.php: ' . $e->getMessage());
    $error_message = 'Error al cargar la información';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Bodega - <?= htmlspecialchars($bodega['nombre']) ?></title>
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

                <!-- Información de la Bodega -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($bodega['nombre']) ?></h2>
                            <p class="text-gray-600">Ubicación: <?= htmlspecialchars($bodega['ubicacion']) ?></p>
                        </div>
                        <div class="flex space-x-2">
                            <a href="index.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                                <i class="fas fa-arrow-left mr-2"></i>Volver
                            </a>
                            <button onclick="showProductosModal(<?= $bodega['id'] ?>)" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Agregar Producto
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-500">Estado</h3>
                            <p class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?= $bodega['estado'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $bodega['estado'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-500">Total Productos</h3>
                            <p class="mt-1 text-lg font-semibold"><?= count($productos) ?></p>
                        </div>
                    </div>

                    <!-- Lista de Productos -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidad</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exclusivo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= htmlspecialchars($producto['codigo_barras']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($producto['producto_nombre']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars($producto['descripcion']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= number_format($producto['cantidad'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= htmlspecialchars($producto['unidad_medida']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= number_format($producto['stock_total'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?= $producto['es_exclusivo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                            <?= $producto['es_exclusivo'] ? 'Sí' : 'No' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="editarProducto(<?= htmlspecialchars(json_encode($producto)) ?>)" 
                                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="eliminarProducto(<?= $producto['id'] ?>)"
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Productos -->
    <div id="productosModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white">
            <div class="flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-xl font-bold text-gray-900">Asignar Productos</h3>
                    <button onclick="closeProductosModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="productoForm" class="space-y-6 py-4">
                    <input type="hidden" name="action" value="asignar_producto">
                    <input type="hidden" name="bodega_id" value="<?= $bodega_id ?>">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Producto *</label>
                            <div class="relative">
                                <input type="text" 
                                       id="producto_busqueda" 
                                       placeholder="Buscar producto por nombre o código" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <input type="hidden" name="producto_id" id="producto_id" required>
                                <div id="sugerencias_productos" 
                                     class="hidden absolute z-10 w-full mt-1 bg-white shadow-lg max-h-60 rounded-md py-1 text-base overflow-auto focus:outline-none sm:text-sm">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad *</label>
                            <input type="number" name="cantidad" required step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <span id="cantidad-error" class="text-red-500 text-sm"></span>
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="es_exclusivo" value="1"
                                   class="rounded border-gray-300 text-blue-600">
                            <span class="ml-2 text-sm text-gray-600">Producto exclusivo para esta bodega</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">
                            Asignar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Componente de alerta -->
    <div id="alert-container" class="fixed top-4 right-4 z-50 max-w-sm"></div>

    <script>
        const productos = <?= json_encode($todos_productos) ?>;
        const inputBusqueda = document.getElementById('producto_busqueda');
        const inputProductoId = document.getElementById('producto_id');
        const sugerenciasDiv = document.getElementById('sugerencias_productos');

        function showProductosModal(bodegaId) {
            document.getElementById('productosModal').classList.remove('hidden');
        }

        function closeProductosModal() {
            document.getElementById('productosModal').classList.add('hidden');
        }

        // Función para mostrar alertas
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            
            const baseClasses = 'p-4 rounded-lg shadow-lg mb-4 transition-all duration-500 transform translate-x-0';
            const typeClasses = {
                'success': 'bg-green-100 border border-green-400 text-green-700',
                'error': 'bg-red-100 border border-red-400 text-red-700',
                'warning': 'bg-yellow-100 border border-yellow-400 text-yellow-700'
            };
            
            alertDiv.className = `${baseClasses} ${typeClasses[type]} translate-x-full`;
            alertDiv.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            ${type === 'success' 
                                ? '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>'
                                : '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>'
                            }
                        </svg>
                        <p>${message}</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            `;
            
            alertContainer.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.classList.remove('translate-x-full');
            }, 10);
            
            setTimeout(() => {
                alertDiv.classList.add('translate-x-full');
                setTimeout(() => alertDiv.remove(), 500);
            }, 5000);
        }

        // Función para mostrar sugerencias
        function mostrarSugerencias(productosFiltrados) {
            if (productosFiltrados.length > 0) {
                const html = productosFiltrados.map(p => `
                    <div class="cursor-pointer p-2 hover:bg-gray-100" 
                         onclick="seleccionarProducto('${p.id}', '${p.codigo_barras || ''} - ${p.nombre.replace(/'/g, "\\'")}')">
                        <div class="text-sm">
                            ${p.codigo_barras ? p.codigo_barras + ' - ' : ''}${p.nombre}
                        </div>
                        <div class="text-xs text-gray-500">
                            Stock: ${p.stock || 0} ${p.unidad_medida || 'unidades'}
                        </div>
                    </div>
                `).join('');
                
                sugerenciasDiv.innerHTML = html;
                sugerenciasDiv.classList.remove('hidden');
            } else {
                sugerenciasDiv.innerHTML = '<div class="p-2 text-sm text-gray-500">No se encontraron productos</div>';
                sugerenciasDiv.classList.remove('hidden');
            }
        }

        // Función para seleccionar producto
        function seleccionarProducto(id, texto) {
            inputProductoId.value = id;
            inputBusqueda.value = texto;
            sugerenciasDiv.classList.add('hidden');
        }

        // Configurar búsqueda y autocompletado
        if (inputBusqueda) {
            inputBusqueda.addEventListener('input', function() {
                const busqueda = this.value.toLowerCase().trim();
                if (busqueda.length > 0) {
                    const filtrados = productos.filter(p => 
                        p.nombre.toLowerCase().includes(busqueda) || 
                        (p.codigo_barras && p.codigo_barras.toLowerCase().includes(busqueda))
                    );
                    mostrarSugerencias(filtrados);
                } else {
                    sugerenciasDiv.classList.add('hidden');
                }
            });

            // Cerrar sugerencias al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!inputBusqueda.contains(e.target) && !sugerenciasDiv.contains(e.target)) {
                    sugerenciasDiv.classList.add('hidden');
                }
            });
        }

        // Manejar el envío del formulario
        document.getElementById('productoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('../bodegas/index.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    showAlert(data.message, 'success');
                    closeProductosModal();
                    location.reload();
                } else {
                    showAlert(data.message || 'Error al procesar la solicitud', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Ocurrió un error al procesar la solicitud', 'error');
            });
        });

        // Validación de cantidad en tiempo real
        const cantidadInput = document.querySelector('input[name="cantidad"]');
        if (cantidadInput) {
            cantidadInput.addEventListener('input', function(e) {
                const cantidad = parseFloat(this.value);
                const productoId = inputProductoId.value;
                const errorSpan = document.getElementById('cantidad-error');
                
                if (productoId && !isNaN(cantidad)) {
                    fetch(`../bodegas/index.php?action=get_stock&producto_id=${productoId}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status && data.stock_disponible !== undefined) {
                            if (cantidad > data.stock_disponible) {
                                this.setCustomValidity(`La cantidad máxima disponible es ${data.stock_disponible}`);
                                errorSpan.textContent = `La cantidad máxima disponible es ${data.stock_disponible}`;
                                errorSpan.classList.remove('hidden');
                            } else {
                                this.setCustomValidity('');
                                errorSpan.textContent = '';
                                errorSpan.classList.add('hidden');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('Error al verificar el stock disponible', 'error');
                    });
                }
            });
        }
    </script>
</body>
</html> 