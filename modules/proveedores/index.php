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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    <div class="container flex">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-body p-6 w-full">
            <!-- Encabezado -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Gestión de Proveedores</h1>
                <p class="text-gray-600">Administra tus proveedores de manera eficiente</p>
            </div>

            <!-- Formulario de Nuevo Proveedor -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-plus-circle mr-2 text-blue-500"></i>
                    Nuevo Proveedor
                </h2>
                
                <form method="POST" class="max-w-2xl mx-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nombre del Proveedor
                            </label>
                            <input type="text" name="nombre" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Correo Electrónico
                            </label>
                            <input type="email" name="email" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Teléfono
                            </label>
                            <input type="tel" name="telefono" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Dirección
                            </label>
                            <input type="text" name="direccion" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mt-4 text-right">
                        <button type="submit" name="add_proveedor"
                            class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-6 rounded-md transition duration-200">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Proveedor
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabla de Proveedores -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-list mr-2 text-blue-500"></i>
                        Listado de Proveedores
                    </h2>
                    <div class="flex items-center gap-2">
                        <button onclick="exportToExcel()" 
                                class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-md transition duration-200 flex items-center">
                            <i class="fas fa-file-excel mr-2"></i>
                            Exportar Excel
                        </button>
                        <span class="text-sm text-gray-500">
                            Total: <?= count($proveedores) ?> proveedores
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Nombre
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Contacto
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Dirección
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($proveedores)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    No hay proveedores registrados
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($proveedores as $proveedor): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($proveedor['nombre']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <i class="fas fa-envelope mr-1 text-gray-400"></i>
                                            <?= htmlspecialchars($proveedor['email']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-phone mr-1 text-gray-400"></i>
                                            <?= htmlspecialchars($proveedor['telefono']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>
                                            <?= htmlspecialchars($proveedor['direccion']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <button onclick="editProveedor(<?= htmlspecialchars(json_encode($proveedor)); ?>)"
                                            class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteProveedor(<?= $proveedor['id']; ?>)"
                                            class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Mantener el código JavaScript existente pero actualizar los estilos de SweetAlert2
    const swalCustomClass = {
        popup: 'rounded-lg shadow-lg',
        confirmButton: 'bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-md transition duration-200',
        cancelButton: 'bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-md transition duration-200',
        input: 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500'
    };

    // Actualizar la configuración de Toast
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: swalCustomClass,
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
    </script>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <script>
    function exportToExcel() {
        // Crear array con los datos
        const data = [
            ['Nombre', 'Email', 'Teléfono', 'Dirección'] // Encabezados
        ];
        
        // Obtener todas las filas de la tabla
        const rows = document.querySelectorAll('table tbody tr');
        rows.forEach(row => {
            if (!row.querySelector('td[colspan]')) { // Excluir fila de "No hay proveedores"
                const nombre = row.querySelector('td:nth-child(1)').textContent.trim();
                const email = row.querySelector('td:nth-child(2)').textContent.trim().split('\n')[0].trim();
                const telefono = row.querySelector('td:nth-child(2)').textContent.trim().split('\n')[1].trim();
                const direccion = row.querySelector('td:nth-child(3)').textContent.trim();
                
                data.push([nombre, email, telefono, direccion]);
            }
        });

        // Crear libro de trabajo y hoja
        const ws = XLSX.utils.aoa_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Proveedores");

        // Generar archivo y descargarlo
        XLSX.writeFile(wb, "Proveedores_VendEasy.xlsx");
    }
    </script>
</body>
</html>
