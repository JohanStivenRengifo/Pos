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
        
        // Actualizar estado de la venta
        $stmt = $pdo->prepare("UPDATE ventas SET estado = 'anulada' WHERE id = ?");
        $stmt->execute([$id]);
        
        // Devolver productos al inventario
        $stmt = $pdo->prepare("SELECT * FROM detalle_venta WHERE venta_id = ?");
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
                  ORDER BY v.fecha DESC 
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

// Obtener datos necesarios
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$ventas = getUserVentas($user_id, $limit, $offset);
$total_ventas = countUserVentas($user_id);
$total_pages = ceil($total_ventas / $limit);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
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
                    <a href="#">Ayuda</a>
                    <a href="#">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Listado de Ventas</h2>
            <div class="promo_card">
                <h1>Gestión de Ventas</h1>
                <span>Aquí puedes ver y gestionar todas tus ventas.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Ventas Recientes</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Venta</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Numero de Factura</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td><?= htmlspecialchars($venta['id']); ?></td>
                                    <td><?= htmlspecialchars($venta['fecha']); ?></td>
                                    <td><?= htmlspecialchars($venta['cliente_nombre'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars(number_format($venta['total'], 2)); ?></td>
                                    <td><?= htmlspecialchars($venta['numero_factura']); ?></td>
                                    <td>
                                        <button class="btn-imprimir" data-id="<?= $venta['id']; ?>"><i class="fas fa-print"></i></button>
                                        <button class="btn-modificar" data-id="<?= $venta['id']; ?>"><i class="fas fa-edit"></i></button>
                                        <button class="btn-anular" data-id="<?= $venta['id']; ?>"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Paginación -->
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i; ?>" class="<?= $i === $page ? 'active' : ''; ?>"><?= $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Configuración global de notificaciones
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // Función para mostrar notificaciones
    function showNotification(type, message) {
        Toast.fire({
            icon: type,
            title: message
        });
    }

    // Función para mostrar errores
    function showError(title, message) {
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonText: 'Entendido'
        });
    }

    // Función para imprimir venta
    function imprimirVenta(id) {
        window.open(`../pos/imprimir_ticket.php?id=${id}`, '_blank');
    }

    // Función para editar venta
    function editarVenta(id) {
        window.location.href = `editar.php?id=${id}`;
    }

    // Función para anular venta
    async function anularVenta(id) {
        const result = await Swal.fire({
            title: '¿Anular venta?',
            text: "Esta acción no se puede deshacer y devolverá los productos al inventario",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, anular venta',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const formData = new FormData();
                    formData.append('action', 'anular_venta');
                    formData.append('id', id);

                    const response = await fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();
                    
                    if (!data.status) {
                        throw new Error(data.message);
                    }
                    
                    return data;
                } catch (error) {
                    Swal.showValidationMessage(`Error: ${error.message}`);
                }
            },
            allowOutsideClick: () => !Swal.isLoading()
        });

        if (result.isConfirmed) {
            showNotification('success', result.value.message);
            setTimeout(() => location.reload(), 1500);
        }
    }

    // Manejadores de eventos para los botones
    document.querySelectorAll('.btn-imprimir').forEach(btn => {
        btn.addEventListener('click', function() {
            imprimirVenta(this.dataset.id);
        });
    });

    document.querySelectorAll('.btn-modificar').forEach(btn => {
        btn.addEventListener('click', function() {
            editarVenta(this.dataset.id);
        });
    });

    document.querySelectorAll('.btn-anular').forEach(btn => {
        btn.addEventListener('click', function() {
            anularVenta(this.dataset.id);
        });
    });

    // Estilo personalizado para SweetAlert2
    const style = document.createElement('style');
    style.textContent = `
        .swal2-popup {
            font-family: 'Poppins', sans-serif;
            border-radius: 12px;
        }
        .swal2-title {
            color: #344767;
        }
        .swal2-html-container {
            color: #495057;
        }
        .swal2-confirm {
            background: linear-gradient(145deg, #007bff, #0056b3) !important;
        }
        .swal2-cancel {
            background: linear-gradient(145deg, #6c757d, #495057) !important;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>
