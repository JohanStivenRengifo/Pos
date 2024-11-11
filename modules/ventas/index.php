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
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <style>
        .filters-section {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .search-box i {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .filter-dropdown {
            min-width: 150px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .date-filter {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .date-filter input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .ventas-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .ventas-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #344767;
        }

        .ventas-table td {
            padding: 1rem;
            border-top: 1px solid #eee;
        }

        .estado-venta {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .estado-venta.activa {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .estado-venta.anulada {
            background-color: #ffebee;
            color: #c62828;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-button {
            padding: 0.5rem;
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

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .stat-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #344767;
            margin-top: 0.5rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination a.active {
            background: #2196f3;
            color: white;
            border-color: #2196f3;
        }

        .pagination a:hover:not(.active) {
            background: #f5f5f5;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <a href="/welcome.php">Dashboard</a>
                <a href="/modules/pos/index.php">POS</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php" class="active">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="/ayuda.php">Ayuda</a>
                    <a href="/contacto.php">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <div class="page-header">
                <h2>Gestión de Ventas</h2>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <h3>Ventas Totales</h3>
                    <div class="value">$<?= number_format($total_ventas_monto, 2) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Ventas del Día</h3>
                    <div class="value">$<?= number_format($ventas_dia, 2) ?></div>
                </div>
                <div class="stat-card">
                    <h3>Ventas Anuladas</h3>
                    <div class="value"><?= $total_anuladas ?></div>
                </div>
            </div>

            <div class="filters-section">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Buscar por cliente o número de factura...">
                </div>
                <select class="filter-dropdown" id="estadoFilter">
                    <option value="">Todos los estados</option>
                    <option value="activa">Activas</option>
                    <option value="anulada">Anuladas</option>
                </select>
                <div class="date-filter">
                    <input type="date" id="fechaDesde" placeholder="Desde">
                    <input type="date" id="fechaHasta" placeholder="Hasta">
                </div>
            </div>

            <table class="ventas-table">
                <thead>
                    <tr>
                        <th>ID Venta</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>N° Factura</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $venta): ?>
                        <tr class="venta-row" data-estado="<?= $venta['anulada'] ? 'anulada' : 'activa' ?>">
                            <td><?= htmlspecialchars($venta['id']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></td>
                            <td><?= htmlspecialchars($venta['cliente_nombre'] ?? 'N/A') ?></td>
                            <td>$<?= number_format($venta['total'], 2) ?></td>
                            <td><?= htmlspecialchars($venta['numero_factura']) ?></td>
                            <td>
                                <span class="estado-venta <?= $venta['anulada'] ? 'anulada' : 'activa' ?>">
                                    <?= $venta['anulada'] ? 'Anulada' : 'Activa' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-button btn-imprimir" 
                                            onclick="imprimirVenta(<?= $venta['id'] ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="action-button btn-modificar" 
                                            onclick="editarVenta(<?= $venta['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!$venta['anulada']): ?>
                                        <button type="button" 
                                                class="action-button btn-anular" 
                                                onclick="confirmarAnulacion(<?= $venta['id'] ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <script>
    // Mantener el código JavaScript existente y agregar:
    
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const estadoFilter = document.getElementById('estadoFilter');
        const fechaDesde = document.getElementById('fechaDesde');
        const fechaHasta = document.getElementById('fechaHasta');
        const ventasRows = document.querySelectorAll('.venta-row');

        function filterVentas() {
            const searchTerm = searchInput.value.toLowerCase();
            const estadoSelected = estadoFilter.value;
            const dateFrom = fechaDesde.value ? new Date(fechaDesde.value) : null;
            const dateTo = fechaHasta.value ? new Date(fechaHasta.value) : null;

            ventasRows.forEach(row => {
                const cliente = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const factura = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
                const estado = row.dataset.estado;
                const fecha = new Date(row.querySelector('td:nth-child(2)').textContent);

                const matchesSearch = cliente.includes(searchTerm) || factura.includes(searchTerm);
                const matchesEstado = !estadoSelected || estado === estadoSelected;
                const matchesDate = (!dateFrom || fecha >= dateFrom) && (!dateTo || fecha <= dateTo);

                row.style.display = matchesSearch && matchesEstado && matchesDate ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterVentas);
        estadoFilter.addEventListener('change', filterVentas);
        fechaDesde.addEventListener('change', filterVentas);
        fechaHasta.addEventListener('change', filterVentas);
    });

    // Función para imprimir venta
    function imprimirVenta(id) {
        window.open(`../pos/imprimir_ticket.php?id=${id}`, '_blank');
    }

    // Función para editar venta
    function editarVenta(id) {
        window.location.href = `editar.php?id=${id}`;
    }

    function confirmarAnulacion(id) {
        console.log('Iniciando confirmación de anulación para venta:', id); // Debug
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¿Deseas anular esta venta? Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
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
