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

function getUserProveedores($user_id)
{
    global $pdo;
    $query = "SELECT * FROM proveedores WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserEgresos($user_id, $limit = 10, $offset = 0)
{
    global $pdo;
    $query = "SELECT * FROM egresos WHERE user_id = ? ORDER BY fecha DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->bindParam(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addEgreso($user_id, $numero_factura, $proveedor, $descripcion, $monto, $fecha)
{
    global $pdo;
    $query = "INSERT INTO egresos (user_id, numero_factura, proveedor, descripcion, monto, fecha) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $numero_factura, $proveedor, $descripcion, $monto, $fecha]);
}

function deleteEgreso($id, $user_id)
{
    global $pdo;
    $query = "DELETE FROM egresos WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$id, $user_id]);
}

function getConfig($user_id) {
    global $pdo;
    $query = "SELECT valor FROM configuracion WHERE user_id = ? AND tipo = 'numero_factura'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTotalEgresos($user_id)
{
    global $pdo;
    $query = "SELECT COUNT(*) FROM egresos WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

$configuracion = getConfig($user_id);
$numero_factura_default = $configuracion['valor'] ?? '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$egresos = getUserEgresos($user_id, $limit, $offset);
$total_egresos = getTotalEgresos($user_id);
$total_pages = ceil($total_egresos / $limit);

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_egreso':
                $numero_factura = trim($_POST['numero_factura']);
                $proveedor = trim($_POST['proveedor']);
                $descripcion = trim($_POST['descripcion']);
                $monto = (float)trim($_POST['monto']);
                $fecha = $_POST['fecha'];

                if (empty($numero_factura) || empty($proveedor) || empty($descripcion) || $monto <= 0 || empty($fecha)) {
                    $response['message'] = "Por favor, complete todos los campos correctamente.";
                } else {
                    if (addEgreso($user_id, $numero_factura, $proveedor, $descripcion, $monto, $fecha)) {
                        $response['success'] = true;
                        $response['message'] = "Egreso agregado exitosamente.";
                    } else {
                        $response['message'] = "Error al agregar el egreso.";
                    }
                }
                break;

            case 'delete_egreso':
                $id = (int)$_POST['id'];
                if (deleteEgreso($id, $user_id)) {
                    $response['success'] = true;
                    $response['message'] = "Egreso eliminado exitosamente.";
                } else {
                    $response['message'] = "Error al eliminar el egreso.";
                }
                break;
        }
    }

    echo json_encode($response);
    exit;
}

$proveedores = getUserProveedores($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egresos - VendEasy</title>
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
                <a href="/modules/egresos/index.php" class="active">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
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
            <h2>Gestionar Egresos</h2>
            <div class="promo_card">
                <h1>Registro de Egresos</h1>
                <span>Aquí puedes agregar y visualizar tus egresos.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Agregar Nuevo Egreso</h4>
                    </div>
                    <form id="egresoForm">
                        <div class="form-group">
                            <label for="numero_factura">Número de Factura:</label>
                            <input type="text" id="numero_factura" name="numero_factura" value="<?= htmlspecialchars($numero_factura_default); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="proveedor">Proveedor:</label>
                            <select id="proveedor" name="proveedor" required>
                                <option value="">Seleccione un proveedor</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?= htmlspecialchars($prov['nombre']) ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="descripcion">Descripción:</label>
                            <textarea id="descripcion" name="descripcion" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="monto">Monto:</label>
                            <input type="number" step="0.01" id="monto" name="monto" required>
                        </div>
                        <div class="form-group">
                            <label for="fecha">Fecha:</label>
                            <input type="date" id="fecha" name="fecha" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Agregar Egreso</button>
                    </form>
                </div>

                <div class="list2">
                    <div class="row">
                        <h4>Listado de Egresos</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Número de Factura</th>
                                <th>Proveedor</th>
                                <th>Descripción</th>
                                <th>Monto</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($egresos as $egreso): ?>
                                <tr>
                                    <td><?= htmlspecialchars($egreso['id']); ?></td>
                                    <td><?= htmlspecialchars($egreso['numero_factura']); ?></td>
                                    <td><?= htmlspecialchars($egreso['proveedor']); ?></td>
                                    <td><?= htmlspecialchars($egreso['descripcion']); ?></td>
                                    <td>$<?= htmlspecialchars(number_format($egreso['monto'], 2)); ?></td>
                                    <td><?= htmlspecialchars($egreso['fecha']); ?></td>
                                    <td>
                                        <button class="btn-delete" onclick="deleteEgreso(<?= $egreso['id']; ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Paginación -->
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i; ?>" class="<?= ($page === $i) ? 'active' : ''; ?>"><?= $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        $('#egresoForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'index.php',
                method: 'POST',
                data: $(this).serialize() + '&action=add_egreso',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: response.message,
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al procesar la solicitud.',
                    });
                }
            });
        });
    });

    function deleteEgreso(id) {
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
                $.ajax({
                    url: 'index.php',
                    method: 'POST',
                    data: { action: 'delete_egreso', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
                                text: response.message,
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message,
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Hubo un problema al procesar la solicitud.',
                        });
                    }
                });
            }
        });
    }
    </script>
</body>
</html>
