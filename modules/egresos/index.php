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

// Modificar la función addEgreso para devolver respuesta estructurada
function addEgreso($user_id, $numero_factura, $proveedor, $descripcion, $monto, $fecha) {
    global $pdo;
    try {
        $query = "INSERT INTO egresos (user_id, numero_factura, proveedor, descripcion, monto, fecha) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$user_id, $numero_factura, $proveedor, $descripcion, $monto, $fecha]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Egreso registrado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al registrar el egreso'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

// Modificar la función deleteEgreso para devolver respuesta estructurada
function deleteEgreso($id, $user_id) {
    global $pdo;
    try {
        $query = "DELETE FROM egresos WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$id, $user_id]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Egreso eliminado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al eliminar el egreso'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
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

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_egreso':
            $numero_factura = trim($_POST['numero_factura']);
            $proveedor = trim($_POST['proveedor']);
            $descripcion = trim($_POST['descripcion']);
            $monto = (float)trim($_POST['monto']);
            $fecha = $_POST['fecha'];

            if (empty($numero_factura) || empty($proveedor) || empty($descripcion) || $monto <= 0 || empty($fecha)) {
                ApiResponse::send(false, 'Por favor, complete todos los campos correctamente.');
            }

            $result = addEgreso($user_id, $numero_factura, $proveedor, $descripcion, $monto, $fecha);
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'delete_egreso':
            $id = (int)$_POST['id'];
            $result = deleteEgreso($id, $user_id);
            ApiResponse::send($result['status'], $result['message']);
            break;

        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

// Obtener datos necesarios
$proveedores = getUserProveedores($user_id);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$egresos = getUserEgresos($user_id, $limit, $offset);
$total_egresos = getTotalEgresos($user_id);
$total_pages = ceil($total_egresos / $limit);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egresos | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
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

    // Función para validar el formulario
    function validateForm(formData) {
        const monto = parseFloat(formData.get('monto'));
        if (monto <= 0) {
            showError('Error de validación', 'El monto debe ser mayor que cero');
            return false;
        }
        return true;
    }

    // Manejador del formulario de egreso
    document.getElementById('egresoForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add_egreso');

        if (!validateForm(formData)) {
            return;
        }

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            
            if (data.status) {
                showNotification('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showError('Error', data.message);
            }
        } catch (error) {
            showError('Error', 'Ocurrió un error al procesar la solicitud');
        }
    });

    // Función para eliminar egreso
    async function deleteEgreso(id) {
        const result = await Swal.fire({
            title: '¿Eliminar egreso?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete_egreso');
                formData.append('id', id);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                
                if (data.status) {
                    showNotification('success', data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError('Error', data.message);
                }
            } catch (error) {
                showError('Error', 'Ocurrió un error al eliminar el egreso');
            }
        }
    }

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
