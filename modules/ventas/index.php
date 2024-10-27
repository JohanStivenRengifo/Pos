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

// Configuración de paginación
$limit = 10; // Número de ventas por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Función para obtener las ventas del usuario con paginación
function getUserVentas($user_id, $limit, $offset) {
    global $pdo;
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
}

// Función para contar el total de ventas del usuario
function countUserVentas($user_id) {
    global $pdo;
    $query = "SELECT COUNT(*) FROM ventas WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Obtener las ventas y el total
$ventas = getUserVentas($user_id, $limit, $offset);
$total_ventas = countUserVentas($user_id);
$total_pages = ceil($total_ventas / $limit);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - VendEasy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
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
                <a href="/modules/pos/index.php">Punto de Venta</a>
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
    $(document).ready(function() {
        $('.btn-imprimir').on('click', function() {
            var ventaId = $(this).data('id');
            window.open(`../pos/imprimir_ticket.php?id=${ventaId}`, '_blank');
        });

        $('.btn-modificar').on('click', function() {
            var ventaId = $(this).data('id');
            window.location.href = `editar.php?id=${ventaId}`;
        });

        $('.btn-anular').on('click', function() {
            var ventaId = $(this).data('id');
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¿Deseas anular esta venta? Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, anular venta',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'anular_venta.php',
                        type: 'POST',
                        data: { id: ventaId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Anulada!',
                                    response.message,
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    'Error',
                                    response.message,
                                    'error'
                                );
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.fire(
                                'Error',
                                'Error al anular la venta: ' + error,
                                'error'
                            );
                        }
                    });
                }
            });
        });
    });
    </script>
</body>
</html>
