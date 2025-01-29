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

// Clase para manejar respuestas JSON
class ApiResponse {
    public static function send($status, $message, $data = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Función modificada para devolver respuesta estructurada
function anularVenta($id, $user_id) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Obtener detalles de la venta
        $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $venta = $stmt->fetch();
        
        if (!$venta) {
            throw new Exception('Venta no encontrada');
        }
        
        // Actualizar estado de la venta usando la columna 'anulada'
        $stmt = $pdo->prepare("UPDATE ventas SET anulada = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        // Devolver productos al inventario
        $stmt = $pdo->prepare("SELECT * FROM venta_detalles WHERE venta_id = ?");
        $stmt->execute([$id]);
        $detalles = $stmt->fetchAll();
        
        foreach ($detalles as $detalle) {
            $stmt = $pdo->prepare("UPDATE inventario SET stock = stock + ? WHERE id = ?");
            $stmt->execute([$detalle['cantidad'], $detalle['producto_id']]);
        }
        
        $pdo->commit();
        return ['status' => true, 'message' => 'Venta anulada exitosamente'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['status' => false, 'message' => 'Error al anular la venta: ' . $e->getMessage()];
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'anular_venta':
            $id = (int)$_POST['id'];
            $result = anularVenta($id, $user_id);
            ApiResponse::send($result['status'], $result['message']);
            break;
            
        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

// Función para obtener las ventas del usuario con paginación
function getUserVentas($user_id, $limit, $offset) {
    global $pdo;
    try {
        $query = "SELECT v.*, c.nombre AS cliente_nombre 
                  FROM ventas v 
                  LEFT JOIN clientes c ON v.cliente_id = c.id 
                  WHERE v.user_id = :user_id 
                  ORDER BY v.fecha DESC, v.anulada ASC 
                  LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getUserVentas: " . $e->getMessage());
        return [];
    }
}

// Función para contar el total de ventas del usuario
function countUserVentas($user_id) {
    global $pdo;
    try {
        $query = "SELECT COUNT(*) FROM ventas WHERE user_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error en countUserVentas: " . $e->getMessage());
        return 0;
    }
}

// Agregar estas funciones después de las funciones existentes y antes de obtener los datos necesarios

function getTotalVentasMonto($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as total 
            FROM ventas 
            WHERE user_id = ? 
            AND anulada = 0
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error en getTotalVentasMonto: " . $e->getMessage());
        return 0;
    }
}

function getVentasDia($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as total 
            FROM ventas 
            WHERE user_id = ? 
            AND DATE(fecha) = CURDATE() 
            AND anulada = 0
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error en getVentasDia: " . $e->getMessage());
        return 0;
    }
}

function getTotalAnuladas($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM ventas 
            WHERE user_id = ? 
            AND anulada = 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error en getTotalAnuladas: " . $e->getMessage());
        return 0;
    }
}

// Obtener datos necesarios
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$ventas = getUserVentas($user_id, $limit, $offset);
$total_ventas = countUserVentas($user_id);
$total_pages = ceil($total_ventas / $limit);

// Agregar estas líneas después de obtener $total_pages
$total_ventas_monto = getTotalVentasMonto($user_id);
$ventas_dia = getVentasDia($user_id);
$total_anuladas = getTotalAnuladas($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ventas | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-8">
            <!-- Encabezado -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Gestión de Ventas</h1>
                <p class="text-gray-600">Administra y monitorea todas las transacciones de ventas</p>
            </div>

            <!-- Tarjetas de estadísticas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">Ventas Totales</h3>
                            <p class="text-2xl font-semibold text-gray-800">$<?= number_format($total_ventas_monto, 2) ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-500">
                            <i class="fas fa-calendar-day text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">Ventas del Día</h3>
                            <p class="text-2xl font-semibold text-gray-800">$<?= number_format($ventas_dia, 2) ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-500">
                            <i class="fas fa-ban text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">Ventas Anuladas</h3>
                            <p class="text-2xl font-semibold text-gray-800"><?= $total_anuladas ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" 
                               id="searchInput" 
                               class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Buscar por cliente o factura...">
                    </div>

                    <select id="estadoFilter" 
                            class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos los estados</option>
                        <option value="activa">Activas</option>
                        <option value="anulada">Anuladas</option>
                    </select>

                    <input type="date" 
                           id="fechaDesde" 
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

                    <input type="date" 
                           id="fechaHasta" 
                           class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <!-- Tabla de Ventas -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Factura</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($ventas as $venta): ?>
                        <tr class="hover:bg-gray-50 venta-row" data-estado="<?= $venta['anulada'] ? 'anulada' : 'activa' ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($venta['id']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($venta['cliente_nombre'] ?? 'N/A') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?= number_format($venta['total'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($venta['numero_factura']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?= $venta['anulada'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= $venta['anulada'] ? 'Anulada' : 'Activa' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button onclick="imprimirVenta(<?= $venta['id'] ?>)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button onclick="editarVenta(<?= $venta['id'] ?>)" 
                                            class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!$venta['anulada']): ?>
                                    <button onclick="confirmarAnulacion(<?= $venta['id'] ?>)" 
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6 flex justify-center">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>" 
                           class="<?= $i === $page 
                                    ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' 
                                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> 
                                  relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            </div>
        </main>
    </div>

    <script>
    // Mantener el código JavaScript existente y agregar:
    
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const estadoFilter = document.getElementById('estadoFilter');
        const fechaDesde = document.getElementById('fechaDesde');
        const fechaHasta = document.getElementById('fechaHasta');
        const ventasRows = document.querySelectorAll('.venta-row');

        function parseLocalDate(dateStr) {
            // Convierte el formato dd/mm/yyyy HH:mm a un objeto Date
            const [datePart, timePart] = dateStr.split(' ');
            const [day, month, year] = datePart.split('/');
            const [hours, minutes] = timePart ? timePart.split(':') : ['00', '00'];
            
            return new Date(year, month - 1, day, hours, minutes);
        }

        function filterVentas() {
            const searchTerm = searchInput.value.toLowerCase();
            const estadoSelected = estadoFilter.value;
            
            // Convertir las fechas del filtro a objetos Date al inicio del día
            const dateFrom = fechaDesde.value ? new Date(fechaDesde.value + 'T00:00:00') : null;
            const dateTo = fechaHasta.value ? new Date(fechaHasta.value + 'T23:59:59') : null;

            ventasRows.forEach(row => {
                const cliente = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const factura = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
                const estado = row.dataset.estado;
                
                // Obtener y parsear la fecha de la venta
                const fechaStr = row.querySelector('td:nth-child(2)').textContent;
                const fechaVenta = parseLocalDate(fechaStr);

                const matchesSearch = cliente.includes(searchTerm) || factura.includes(searchTerm);
                const matchesEstado = !estadoSelected || estado === estadoSelected;
                
                // Comparación de fechas
                const matchesDate = (!dateFrom || fechaVenta >= dateFrom) && 
                                  (!dateTo || fechaVenta <= dateTo);

                row.style.display = matchesSearch && matchesEstado && matchesDate ? '' : 'none';
            });
        }

        // Agregar placeholders y títulos a los inputs de fecha
        fechaDesde.placeholder = 'Fecha desde';
        fechaHasta.placeholder = 'Fecha hasta';
        fechaDesde.title = 'Seleccionar fecha inicial';
        fechaHasta.title = 'Seleccionar fecha final';

        // Eventos para el filtrado
        searchInput.addEventListener('input', filterVentas);
        estadoFilter.addEventListener('change', filterVentas);
        fechaDesde.addEventListener('change', filterVentas);
        fechaHasta.addEventListener('change', filterVentas);

        // Inicializar filtros
        filterVentas();
    });

    // Función para imprimir venta
    function imprimirVenta(id) {
        window.open(`../pos/controllers/imprimir_factura.php?id=${id}`, '_blank');
    }

    // Función para editar venta
    function editarVenta(id) {
        window.location.href = `editar.php?id=${id}`;
    }

    function confirmarAnulacion(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¿Deseas anular esta venta? Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar',
            customClass: {
                popup: 'rounded-lg',
                confirmButton: 'px-4 py-2 rounded-md',
                cancelButton: 'px-4 py-2 rounded-md'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                realizarAnulacion(id);
            }
        });
    }

    function realizarAnulacion(id) {
        console.log('Iniciando proceso de anulación para venta:', id); // Debug
        
        fetch('anular_venta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => {
            console.log('Respuesta recibida:', response); // Debug
            return response.json();
        })
        .then(data => {
            console.log('Datos recibidos:', data); // Debug
            if (data.success) {
                Swal.fire({
                    title: '¡Éxito!',
                    text: data.message,
                    icon: 'success'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                throw new Error(data.message || 'Error al anular la venta');
            }
        })
        .catch(error => {
            console.error('Error:', error); // Debug
            Swal.fire({
                title: 'Error',
                text: error.message || 'Hubo un error al anular la venta',
                icon: 'error'
            });
        });
    }

    // Agregar estilos adicionales para los botones
    const additionalStyles = `
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
        }

        .action-button {
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
        }

        .btn-imprimir { color: #2196f3; }
        .btn-modificar { color: #4caf50; }
        .btn-anular { color: #f44336; }

        .btn-imprimir:hover { background-color: #e3f2fd; }
        .btn-modificar:hover { background-color: #e8f5e9; }
        .btn-anular:hover { background-color: #ffebee; }

        .action-button i {
            font-size: 1.1rem;
        }
    `;

    // Agregar los estilos al documento
    const styleSheet = document.createElement("style");
    styleSheet.textContent = additionalStyles;
    document.head.appendChild(styleSheet);
    </script>
</body>
</html>
