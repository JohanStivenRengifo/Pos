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

function getUserIngresos($user_id, $limit = 10, $offset = 0)
{
    global $pdo;
    $query = "SELECT * FROM ingresos WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addIngreso($user_id, $descripcion, $monto) {
    global $pdo;
    try {
        $query = "INSERT INTO ingresos (user_id, descripcion, monto) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$user_id, $descripcion, $monto]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Ingreso registrado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al registrar el ingreso'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function getTotalIngresos($user_id)
{
    global $pdo;
    $query = "SELECT COUNT(*) FROM ingresos WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$ingresos = getUserIngresos($user_id, $limit, $offset);
$total_ingresos = getTotalIngresos($user_id);
$total_pages = ceil($total_ingresos / $limit);

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_ingreso':
            $descripcion = trim($_POST['descripcion']);
            $monto = (float)trim($_POST['monto']);

            if (empty($descripcion) || $monto <= 0) {
                ApiResponse::send(false, 'Por favor, complete todos los campos correctamente.');
            }

            $result = addIngreso($user_id, $descripcion, $monto);
            ApiResponse::send($result['status'], $result['message']);
            break;
            
        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresos | VendEasy</title>
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
                <a href="/modules/ingresos/index.php" class="active">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
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
            <h2>Gestionar Ingresos</h2>
            <div class="promo_card">
                <h1>Registro de Ingresos</h1>
                <span>Aquí puedes agregar y visualizar tus ingresos.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Agregar Nuevo Ingreso</h4>
                    </div>
                    <form id="ingresoForm">
                        <div class="form-group">
                            <label for="descripcion">Descripción:</label>
                            <input type="text" id="descripcion" name="descripcion" required>
                        </div>
                        <div class="form-group">
                            <label for="monto">Monto:</label>
                            <input type="number" step="0.01" id="monto" name="monto" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Agregar Ingreso</button>
                    </form>
                </div>

                <div class="list2">
                    <div class="row">
                        <h4>Listado de Ingresos</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Monto</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ingresos as $ingreso): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ingreso['descripcion']); ?></td>
                                    <td>$<?= number_format($ingreso['monto'], 2); ?></td>
                                    <td><?= htmlspecialchars($ingreso['created_at']); ?></td>
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

    // Manejador del formulario de ingreso
    document.getElementById('ingresoForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add_ingreso');

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
                // Limpiar formulario
                this.reset();
                // Recargar tabla después de un breve delay
                setTimeout(() => location.reload(), 1500);
            } else {
                showError('Error', data.message);
            }
        } catch (error) {
            showError('Error', 'Ocurrió un error al procesar la solicitud');
        }
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
