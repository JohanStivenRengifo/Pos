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

// Modificar la función para devolver respuesta estructurada
function addProveedor($user_id, $nombre, $email, $telefono, $direccion) {
    global $pdo;
    try {
        $query = "INSERT INTO proveedores (user_id, nombre, email, telefono, direccion) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$user_id, $nombre, $email, $telefono, $direccion]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Proveedor agregado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al agregar el proveedor'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function deleteProveedor($id, $user_id) {
    global $pdo;
    try {
        $query = "DELETE FROM proveedores WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$id, $user_id]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Proveedor eliminado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al eliminar el proveedor'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function updateProveedor($id, $user_id, $nombre, $email, $telefono, $direccion) {
    global $pdo;
    try {
        $query = "UPDATE proveedores SET nombre = ?, email = ?, telefono = ?, direccion = ? WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$nombre, $email, $telefono, $direccion, $id, $user_id]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Proveedor actualizado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al actualizar el proveedor'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $nombre = trim($_POST['nombre']);
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            $telefono = trim($_POST['telefono']);
            $direccion = trim($_POST['direccion']);

            if (empty($nombre) || empty($email) || empty($telefono) || empty($direccion)) {
                ApiResponse::send(false, 'Por favor, complete todos los campos.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ApiResponse::send(false, 'Por favor, ingrese un correo electrónico válido.');
            }

            $result = addProveedor($user_id, $nombre, $email, $telefono, $direccion);
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'update':
            $id = (int)$_POST['id'];
            $nombre = trim($_POST['nombre']);
            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
            $telefono = trim($_POST['telefono']);
            $direccion = trim($_POST['direccion']);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ApiResponse::send(false, 'Por favor, ingrese un correo electrónico válido.');
            }

            $result = updateProveedor($id, $user_id, $nombre, $email, $telefono, $direccion);
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'delete':
            $id = (int)$_POST['id'];
            $result = deleteProveedor($id, $user_id);
            ApiResponse::send($result['status'], $result['message']);
            break;

        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

// Función para obtener todos los proveedores asociados al usuario actual
function getUserProveedores($user_id)
{
    global $pdo;
    $query = "SELECT * FROM proveedores WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Guardar nuevo proveedor si se envía el formulario
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_proveedor'])) {
    $nombre = trim($_POST['nombre']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);

    // Validar los campos
    if (empty($nombre) || empty($email) || empty($telefono) || empty($direccion)) {
        $message = "Por favor, complete todos los campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Por favor, ingrese un correo electrónico válido.";
    } else {
        // Agregar proveedor a la base de datos
        if (addProveedor($user_id, $nombre, $email, $telefono, $direccion)) {
            $message = "Proveedor agregado exitosamente.";
        } else {
            $message = "Error al agregar el proveedor.";
        }
    }
}

// Obtener todos los proveedores del usuario
$proveedores = getUserProveedores($user_id);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores | VendEasy</title>
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
<?php include '../../includes/header.php'; ?>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const currentUrl = window.location.pathname;
                const sidebarLinks = document.querySelectorAll('.side_navbar a');
                sidebarLinks.forEach(link => {
                    if (link.getAttribute('href') === currentUrl) {
                        link.classList.add('active');
                    }
                });
            });
        </script>

        <div class="main-body">
            <h2>Gestionar Proveedores</h2>
            <div class="promo_card">
                <h1>Proveedores</h1>
                <span>Aquí puedes agregar y gestionar tus proveedores.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?= strpos($message, 'exitosamente') !== false ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Agregar Nuevo Proveedor</h4>
                    </div>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="nombre">Nombre del Proveedor:</label>
                            <input type="text" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Correo Electrónico:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono:</label>
                            <input type="text" id="telefono" name="telefono" required>
                        </div>
                        <div class="form-group">
                            <label for="direccion">Dirección:</label>
                            <input type="text" id="direccion" name="direccion" required>
                        </div>
                        <button type="submit" name="add_proveedor" class="btn btn-primary">Agregar Proveedor</button>
                    </form>
                </div>

                <div class="list2">
                    <div class="row">
                        <h4>Listado de Proveedores</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Correo Electrónico</th>
                                <th>Teléfono</th>
                                <th>Dirección</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proveedores as $proveedor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($proveedor['nombre']); ?></td>
                                    <td><?= htmlspecialchars($proveedor['email']); ?></td>
                                    <td><?= htmlspecialchars($proveedor['telefono']); ?></td>
                                    <td><?= htmlspecialchars($proveedor['direccion']); ?></td>
                                    <td>
                                        <button class="btn-edit" onclick="editProveedor(<?= htmlspecialchars(json_encode($proveedor)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="deleteProveedor(<?= $proveedor['id']; ?>)">
                                            <i class="fas fa-trash-alt"></i>
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

    // Función para validar email
    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Manejador del formulario de proveedor
    document.querySelector('form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add');

        if (!validateEmail(formData.get('email'))) {
            showError('Error de validación', 'Por favor, ingrese un correo electrónico válido');
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
                this.reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                showError('Error', data.message);
            }
        } catch (error) {
            showError('Error', 'Ocurrió un error al procesar la solicitud');
        }
    });

    // Función para editar proveedor
    async function editProveedor(proveedor) {
        const { value: formValues } = await Swal.fire({
            title: 'Editar Proveedor',
            html: `
                <form id="editForm">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input id="nombre" class="swal2-input" value="${proveedor.nombre}" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" class="swal2-input" value="${proveedor.email}" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input id="telefono" class="swal2-input" value="${proveedor.telefono}" required>
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <input id="direccion" class="swal2-input" value="${proveedor.direccion}" required>
                    </div>
                </form>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const email = document.getElementById('email').value;
                if (!validateEmail(email)) {
                    Swal.showValidationMessage('Por favor, ingrese un correo electrónico válido');
                    return false;
                }
                return {
                    nombre: document.getElementById('nombre').value,
                    email: email,
                    telefono: document.getElementById('telefono').value,
                    direccion: document.getElementById('direccion').value
                }
            }
        });

        if (formValues) {
            try {
                const formData = new FormData();
                formData.append('action', 'update');
                formData.append('id', proveedor.id);
                Object.entries(formValues).forEach(([key, value]) => {
                    formData.append(key, value);
                });

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
                showError('Error', 'Ocurrió un error al actualizar el proveedor');
            }
        }
    }

    // Función para eliminar proveedor
    async function deleteProveedor(id) {
        const result = await Swal.fire({
            title: '¿Eliminar proveedor?',
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
                formData.append('action', 'delete');
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
                showError('Error', 'Ocurrió un error al eliminar el proveedor');
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
        .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #344767;
        }
        .swal2-input {
            margin: 0.5rem 0 !important;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>

</html>
