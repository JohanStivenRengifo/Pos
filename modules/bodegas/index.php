<?php
session_start();
require_once '../../config/db.php';

// Activar reporte de errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Asignar $pdo a $conn para mantener consistencia
$conn = $pdo;

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Verificar la conexión a la base de datos
if (!isset($conn)) {
    die('Error: No se pudo conectar a la base de datos');
}

// Inicializar variables
$bodegas = [];
$productos = [];
$error_message = '';

// Obtener lista de bodegas
try {
    $stmt = $conn->prepare("SELECT * FROM bodegas WHERE usuario_id = ? ORDER BY nombre");
    $stmt->execute([$_SESSION['user_id']]);
    $bodegas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error al obtener bodegas: ' . $e->getMessage());
    $error_message = 'Error al cargar las bodegas';
}

// Obtener lista de productos
try {
    $stmt = $conn->prepare("
        SELECT 
            id, 
            nombre,
            codigo_barras,
            descripcion,
            unidad_medida,
            precio_venta,
            stock
        FROM inventario 
        WHERE estado = 1 
        AND user_id = ?
        ORDER BY nombre
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $productos = $stmt->fetchAll();
    
    if (empty($productos)) {
        $error_message = 'No hay productos disponibles';
    }
} catch (PDOException $e) {
    error_log('Error al obtener productos: ' . $e->getMessage());
    $error_message = 'Error al cargar los productos';
    $productos = [];
}

// Agregar debug temporal
error_log('SESSION: ' . print_r($_SESSION, true));
error_log('Productos encontrados: ' . count($productos));

// Agregar esto después de la verificación de conexión
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $response = ['status' => false, 'message' => ''];
    
    try {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'get_stock':
                $producto_id = intval($_GET['producto_id']);
                
                // Obtener stock disponible y cantidad ya asignada
                $stmt = $conn->prepare("
                    SELECT i.stock, COALESCE(SUM(ib.cantidad), 0) as cantidad_asignada 
                    FROM inventario i 
                    LEFT JOIN inventario_bodegas ib ON i.id = ib.producto_id 
                    WHERE i.id = ? AND i.user_id = ? 
                    GROUP BY i.id, i.stock
                ");
                $stmt->execute([$producto_id, $_SESSION['user_id']]);
                $producto = $stmt->fetch();
                
                if ($producto) {
                    $stock_disponible = $producto['stock'] - $producto['cantidad_asignada'];
                    $response = [
                        'status' => true,
                        'stock_disponible' => $stock_disponible
                    ];
                } else {
                    throw new Exception('Producto no encontrado');
                }
                break;

            case 'add':
                // Validar datos requeridos
                if (empty($_POST['nombre'])) {
                    throw new Exception('El nombre de la bodega es requerido');
                }
                
                $nombre = trim($_POST['nombre']);
                $ubicacion = trim($_POST['ubicacion'] ?? '');
                
                // Verificar si ya existe una bodega con el mismo nombre
                $stmt = $conn->prepare("SELECT id FROM bodegas WHERE UPPER(nombre) = UPPER(?) AND usuario_id = ?");
                $stmt->execute([strtoupper($nombre), $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe una bodega con este nombre');
                }
                
                // Insertar nueva bodega
                $stmt = $conn->prepare("INSERT INTO bodegas (nombre, ubicacion, usuario_id, estado) VALUES (?, ?, ?, 1)");
                if ($stmt->execute([$nombre, $ubicacion, $_SESSION['user_id']])) {
                    $response = [
                        'status' => true, 
                        'message' => 'Bodega creada exitosamente',
                        'id' => $conn->lastInsertId()
                    ];
                } else {
                    throw new Exception('Error al crear la bodega');
                }
                break;
                
            case 'asignar_producto':
                $bodega_id = intval($_POST['bodega_id']);
                $producto_id = intval($_POST['producto_id']);
                $cantidad = floatval($_POST['cantidad']);
                $es_exclusivo = isset($_POST['es_exclusivo']) ? 1 : 0;
                
                // Verificar que la bodega pertenezca al usuario
                $stmt = $conn->prepare("SELECT id FROM bodegas WHERE id = ? AND usuario_id = ?");
                $stmt->execute([$bodega_id, $_SESSION['user_id']]);
                if (!$stmt->fetch()) {
                    throw new Exception('No tienes permiso para modificar esta bodega');
                }
                
                // Iniciar transacción
                $conn->beginTransaction();
                
                try {
                    // Verificar stock disponible y cantidad ya asignada
                    $stmt = $conn->prepare("
                        SELECT i.stock, COALESCE(SUM(ib.cantidad), 0) as cantidad_asignada 
                        FROM inventario i 
                        LEFT JOIN inventario_bodegas ib ON i.id = ib.producto_id 
                        WHERE i.id = ? AND i.user_id = ? 
                        GROUP BY i.id, i.stock
                    ");
                    $stmt->execute([$producto_id, $_SESSION['user_id']]);
                    $producto = $stmt->fetch();
                    
                    if (!$producto) {
                        throw new Exception('Producto no encontrado');
                    }
                    
                    // Verificar si el producto ya está asignado a la bodega
                    $stmt = $conn->prepare("SELECT id, cantidad FROM inventario_bodegas WHERE bodega_id = ? AND producto_id = ?");
                    $stmt->execute([$bodega_id, $producto_id]);
                    $existente = $stmt->fetch();
                    
                    // Calcular stock disponible
                    $stock_disponible = $producto['stock'] - $producto['cantidad_asignada'];
                    if ($existente) {
                        $stock_disponible += $existente['cantidad']; // Sumamos la cantidad actual para permitir actualización
                    }
                    
                    if ($cantidad > $stock_disponible) {
                        throw new Exception("No hay suficiente stock disponible. Stock disponible: $stock_disponible");
                    }
                    
                    if ($existente) {
                        // Actualizar cantidad existente sumando la nueva cantidad
                        $nueva_cantidad = $existente['cantidad'] + $cantidad; // Sumamos la nueva cantidad a la existente
                        
                        // Verificar que la nueva cantidad total no exceda el stock disponible
                        if ($nueva_cantidad > $stock_disponible) {
                            throw new Exception("La cantidad total excedería el stock disponible. Stock disponible: $stock_disponible");
                        }
                        
                        $stmt = $conn->prepare("
                            UPDATE inventario_bodegas 
                            SET cantidad = ?, es_exclusivo = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$nueva_cantidad, $es_exclusivo, $existente['id']]);
                        $mensaje = 'Cantidad actualizada exitosamente';
                    } else {
                        // Insertar nueva asignación
                        $stmt = $conn->prepare("
                            INSERT INTO inventario_bodegas 
                            (bodega_id, producto_id, cantidad, es_exclusivo) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$bodega_id, $producto_id, $cantidad, $es_exclusivo]);
                        $mensaje = 'Producto asignado exitosamente';
                    }
                    
                    $conn->commit();
                    $response = [
                        'status' => true, 
                        'message' => $mensaje,
                        'stock_disponible' => $stock_disponible - $nueva_cantidad
                    ];
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    throw $e;
                }
                break;
        }
    } catch (Exception $e) {
        $response = ['status' => false, 'message' => $e->getMessage()];
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
    <title>Gestión de Bodegas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow" role="alert">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm"><?= htmlspecialchars($error_message) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <!-- Encabezado con estadísticas -->
                    <div class="mb-8">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Gestión de Bodegas</h1>
                                <p class="mt-1 text-sm text-gray-600">Administra las bodegas y sus productos</p>
                            </div>
                            <button onclick="showModal()" 
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-colors">
                                <i class="fas fa-plus mr-2"></i>
                                Nueva Bodega
                            </button>
                        </div>

                        <!-- Estadísticas -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-lg border border-blue-200">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-blue-600 bg-opacity-10">
                                        <i class="fas fa-warehouse text-blue-600 text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-sm font-medium text-gray-600">Total Bodegas</h3>
                                        <p class="text-2xl font-semibold text-gray-900"><?= count($bodegas) ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-lg border border-green-200">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-green-600 bg-opacity-10">
                                        <i class="fas fa-box text-green-600 text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-sm font-medium text-gray-600">Total Productos</h3>
                                        <p class="text-2xl font-semibold text-gray-900"><?= count($productos) ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-lg border border-purple-200">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full bg-purple-600 bg-opacity-10">
                                        <i class="fas fa-boxes text-purple-600 text-xl"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-sm font-medium text-gray-600">Productos Asignados</h3>
                                        <p class="text-2xl font-semibold text-gray-900">
                                            <?php
                                            $stmt = $conn->prepare("SELECT COUNT(DISTINCT producto_id) as total FROM inventario_bodegas WHERE bodega_id IN (SELECT id FROM bodegas WHERE usuario_id = ?)");
                                            $stmt->execute([$_SESSION['user_id']]);
                                            echo $stmt->fetch()['total'];
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Bodegas -->
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Nombre</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Ubicación</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Productos</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Estado</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Última Actualización</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4">
                                        <span class="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <?php foreach ($bodegas as $bodega): 
                                    // Obtener cantidad de productos en la bodega
                                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM inventario_bodegas WHERE bodega_id = ?");
                                    $stmt->execute([$bodega['id']]);
                                    $total_productos = $stmt->fetch()['total'];
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($bodega['nombre']) ?></div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        <?= $bodega['ubicacion'] ? htmlspecialchars($bodega['ubicacion']) : '<span class="text-gray-400">No especificada</span>' ?>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?= $total_productos ?> productos
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $bodega['estado'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $bodega['estado'] ? 'Activa' : 'Inactiva' ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($bodega['updated_at'])) ?>
                                    </td>
                                    <td class="whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <button onclick="window.location.href='ver.php?id=<?= $bodega['id'] ?>'" 
                                                    class="text-green-600 hover:text-green-900 bg-green-100 p-2 rounded-lg transition-colors"
                                                    title="Ver productos">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="showProductosModal(<?= $bodega['id'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 bg-blue-100 p-2 rounded-lg transition-colors"
                                                    title="Asignar productos">
                                                <i class="fas fa-box"></i>
                                            </button>
                                            <button onclick="editBodega(<?= htmlspecialchars(json_encode($bodega)) ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900 bg-indigo-100 p-2 rounded-lg transition-colors"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteBodega(<?= $bodega['id'] ?>)" 
                                                    class="text-red-600 hover:text-red-900 bg-red-100 p-2 rounded-lg transition-colors"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para Bodegas -->
    <div id="bodegaModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Nueva Bodega</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="bodegaForm" class="space-y-6 py-4" action="crear.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" value="">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                        <input type="text" 
                               name="nombre" 
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Nombre de la bodega">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación</label>
                        <input type="text" 
                               name="ubicacion"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ubicación de la bodega">
                    </div>

                    <div class="flex justify-end space-x-4 pt-4 border-t">
                        <button type="button" 
                                onclick="closeModal()"
                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Guardar
                        </button>
                    </div>
                </form>
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
                    <input type="hidden" name="bodega_id" value="">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Producto *</label>
                            <div class="relative">
                                <input type="text" 
                                       id="producto_busqueda" 
                                       placeholder="Buscar producto por nombre o código" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <input type="hidden" name="producto_id" id="producto_id" required>
                                <svg class="absolute right-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" 
                                     fill="none" 
                                     stroke="currentColor" 
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" 
                                          stroke-linejoin="round" 
                                          stroke-width="2" 
                                          d="M19 9l-7 7-7-7" />
                                </svg>
                                <!-- Lista de sugerencias -->
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

    <!-- Modal para Ver Productos de Bodega -->
    <div id="verProductosModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-[800px] shadow-lg rounded-md bg-white">
            <div class="flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-xl font-bold text-gray-900">Productos en Bodega</h3>
                    <button onclick="closeVerProductosModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="mt-4">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidad</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Exclusivo</th>
                            </tr>
                        </thead>
                        <tbody id="productosAsignadosList" class="bg-white divide-y divide-gray-200">
                            <!-- Los productos se cargarán aquí dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Después del div del modal de productos, agregar el componente de alerta -->
    <div id="alert-container" class="fixed top-4 right-4 z-50 max-w-sm"></div>

    <script>
        // Función para mostrar el modal
        function showModal() {
            document.getElementById('bodegaModal').classList.remove('hidden');
        }

        // Función para cerrar el modal
        function closeModal() {
            document.getElementById('bodegaModal').classList.add('hidden');
            document.getElementById('bodegaForm').reset();
        }

        // Manejar el envío del formulario de bodega
        document.getElementById('bodegaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('crear.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Hubo un error al crear la bodega'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Hubo un error al procesar la solicitud'
                });
            });
        });

        // Función para editar bodega
        function editBodega(bodega) {
            const form = document.getElementById('bodegaForm');
            form.querySelector('input[name="id"]').value = bodega.id;
            form.querySelector('input[name="nombre"]').value = bodega.nombre;
            form.querySelector('input[name="ubicacion"]').value = bodega.ubicacion || '';
            form.querySelector('input[name="action"]').value = 'edit';
            document.getElementById('modalTitle').textContent = 'Editar Bodega';
            showModal();
        }

        // Función para eliminar bodega
        function deleteBodega(id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', id);

                    fetch('index.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Eliminado!',
                                text: data.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'No se pudo eliminar la bodega'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Hubo un error al procesar la solicitud'
                        });
                    });
                }
            });
        }

        function showProductosModal(bodegaId) {
            const modal = document.getElementById('productosModal');
            document.querySelector('#productoForm input[name="bodega_id"]').value = bodegaId;
            modal.classList.remove('hidden');
        }

        function closeProductosModal() {
            document.getElementById('productosModal').classList.add('hidden');
        }

        // Agregar el código de autocompletado
        document.addEventListener('DOMContentLoaded', function() {
            const productos = <?= json_encode($productos) ?>;
            const inputBusqueda = document.getElementById('producto_busqueda');
            const inputProductoId = document.getElementById('producto_id');
            const sugerenciasDiv = document.getElementById('sugerencias_productos');

            // Función para filtrar productos
            function filtrarProductos(busqueda) {
                return productos.filter(producto => {
                    const nombreMatch = producto.nombre.toLowerCase().includes(busqueda.toLowerCase());
                    const codigoMatch = producto.codigo_barras && 
                                      producto.codigo_barras.toLowerCase().includes(busqueda.toLowerCase());
                    return nombreMatch || codigoMatch;
                });
            }

            // Función para mostrar sugerencias
            function mostrarSugerencias(sugerencias) {
                if (sugerencias.length > 0) {
                    const html = sugerencias.map(producto => `
                        <div class="cursor-pointer p-2 hover:bg-gray-100" 
                             onclick="seleccionarProducto('${producto.id}', '${producto.codigo_barras || ''} - ${producto.nombre.replace(/'/g, "\\'")}')">
                            <div class="flex items-center">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        ${producto.codigo_barras ? producto.codigo_barras + ' - ' : ''}${producto.nombre}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Stock: ${producto.stock || 0} ${producto.unidad_medida || 'unidades'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('');
                    
                    sugerenciasDiv.innerHTML = html;
                    sugerenciasDiv.classList.remove('hidden');
                } else {
                    sugerenciasDiv.innerHTML = `
                        <div class="p-2 text-sm text-gray-500">
                            No se encontraron productos
                        </div>
                    `;
                    sugerenciasDiv.classList.remove('hidden');
                }
            }

            // Event listener para el input de búsqueda
            inputBusqueda.addEventListener('input', function(e) {
                const busqueda = e.target.value.trim();
                if (busqueda.length > 0) {
                    const sugerencias = filtrarProductos(busqueda);
                    mostrarSugerencias(sugerencias);
                } else {
                    sugerenciasDiv.classList.add('hidden');
                }
            });

            // Event listener para cerrar sugerencias al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!inputBusqueda.contains(e.target) && !sugerenciasDiv.contains(e.target)) {
                    sugerenciasDiv.classList.add('hidden');
                }
            });

            // Event listener para navegación con teclado
            inputBusqueda.addEventListener('keydown', function(e) {
                const items = sugerenciasDiv.children;
                let currentIndex = Array.from(items).findIndex(item => 
                    item.classList.contains('bg-gray-100')
                );

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (sugerenciasDiv.classList.contains('hidden')) {
                            const sugerencias = filtrarProductos(inputBusqueda.value.trim());
                            mostrarSugerencias(sugerencias);
                        } else if (currentIndex < items.length - 1) {
                            currentIndex++;
                        }
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        if (currentIndex > 0) {
                            currentIndex--;
                        }
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (currentIndex >= 0 && !sugerenciasDiv.classList.contains('hidden')) {
                            items[currentIndex].click();
                        }
                        break;
                    case 'Escape':
                        sugerenciasDiv.classList.add('hidden');
                        break;
                }

                // Actualizar selección visual
                Array.from(items).forEach(item => item.classList.remove('bg-gray-100'));
                if (currentIndex >= 0 && items[currentIndex]) {
                    items[currentIndex].classList.add('bg-gray-100');
                    items[currentIndex].scrollIntoView({ block: 'nearest' });
                }
            });
        });

        // Función para seleccionar un producto
        function seleccionarProducto(id, texto) {
            document.getElementById('producto_id').value = id;
            document.getElementById('producto_busqueda').value = texto;
            document.getElementById('sugerencias_productos').classList.add('hidden');
        }

        // Función para mostrar alertas
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            
            // Definir clases según el tipo de alerta
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
            
            // Animar entrada
            setTimeout(() => {
                alertDiv.classList.remove('translate-x-full');
            }, 10);
            
            // Auto-eliminar después de 5 segundos
            setTimeout(() => {
                alertDiv.classList.add('translate-x-full');
                setTimeout(() => alertDiv.remove(), 5000);
            }, 5000);
        }

        // Modificar el manejador del formulario de productos
        document.getElementById('productoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('index.php', {
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

        // Modificar la validación de cantidad en tiempo real
        document.querySelector('input[name="cantidad"]').addEventListener('input', function(e) {
            const cantidad = parseFloat(this.value);
            const productoId = document.getElementById('producto_id').value;
            const errorSpan = document.getElementById('cantidad-error');
            
            if (productoId && !isNaN(cantidad)) {
                fetch(`index.php?action=get_stock&producto_id=${productoId}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
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
    </script>
</body>
</html>
