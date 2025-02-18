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
function addProveedor($user_id, $data) {
    global $pdo;
    try {
        // Preparar los datos según el tipo de persona
        $nombre = null;
        $primer_nombre = null;
        $segundo_nombre = null;
        $apellidos = null;

        if ($data['tipo_persona'] === 'natural') {
            $nombre = $data['primer_nombre'] . ' ' . $data['apellidos'];
            $primer_nombre = $data['primer_nombre'];
            $segundo_nombre = $data['segundo_nombre'];
            $apellidos = $data['apellidos'];
        } else {
            $nombre = $data['nombre'];
        }

        $query = "INSERT INTO proveedores (
            user_id, nombre, email, telefono, direccion, tipo_identificacion,
            identificacion, dv, primer_nombre, segundo_nombre, apellidos,
            municipio_departamento, codigo_postal, tipo_persona,
            responsabilidad_tributaria, email2, telefono2, celular
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $user_id,
            $nombre,
            $data['email'],
            $data['telefono'],
            $data['direccion'],
            $data['tipo_identificacion'],
            $data['identificacion'],
            $data['dv'],
            $primer_nombre,
            $segundo_nombre,
            $apellidos,
            $data['municipio_departamento'],
            $data['codigo_postal'],
            $data['tipo_persona'],
            $data['responsabilidad_tributaria'],
            $data['email2'],
            $data['telefono2'],
            $data['celular']
        ]);

        // Manejar la foto de perfil si se proporcionó
        if ($result && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $proveedor_id = $pdo->lastInsertId();
            $foto = $_FILES['foto_perfil'];
            $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $nuevo_nombre = "proveedor_{$proveedor_id}.{$extension}";
            $ruta_destino = "../../uploads/proveedores/{$nuevo_nombre}";
            
            if (!is_dir("../../uploads/proveedores")) {
                mkdir("../../uploads/proveedores", 0777, true);
            }
            
            if (move_uploaded_file($foto['tmp_name'], $ruta_destino)) {
                $stmt = $pdo->prepare("UPDATE proveedores SET foto_perfil = ? WHERE id = ?");
                $stmt->execute([$nuevo_nombre, $proveedor_id]);
            }
        }

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
        
        if ($result && $stmt->rowCount() > 0) {
            return ['status' => true, 'message' => 'Proveedor eliminado exitosamente'];
        }
        return ['status' => false, 'message' => 'No se pudo eliminar el proveedor o no se encontró el registro'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function updateProveedor($id, $user_id, $data) {
    global $pdo;
    try {
        // Preparar los datos según el tipo de persona
        $nombre = null;
        $primer_nombre = null;
        $segundo_nombre = null;
        $apellidos = null;

        if ($data['tipo_persona'] === 'natural') {
            $nombre = $data['primer_nombre'] . ' ' . $data['apellidos'];
            $primer_nombre = $data['primer_nombre'];
            $segundo_nombre = $data['segundo_nombre'];
            $apellidos = $data['apellidos'];
        } else {
            $nombre = $data['nombre'];
        }

        $query = "UPDATE proveedores SET 
            nombre = ?, 
            email = ?, 
            telefono = ?, 
            direccion = ?,
            tipo_identificacion = ?,
            identificacion = ?,
            dv = ?,
            primer_nombre = ?,
            segundo_nombre = ?,
            apellidos = ?,
            municipio_departamento = ?,
            codigo_postal = ?,
            tipo_persona = ?,
            responsabilidad_tributaria = ?,
            email2 = ?,
            telefono2 = ?,
            celular = ?
            WHERE id = ? AND user_id = ?";

        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $nombre,
            $data['email'],
            $data['telefono'],
            $data['direccion'],
            $data['tipo_identificacion'],
            $data['identificacion'],
            $data['dv'],
            $primer_nombre,
            $segundo_nombre,
            $apellidos,
            $data['municipio_departamento'],
            $data['codigo_postal'],
            $data['tipo_persona'],
            $data['responsabilidad_tributaria'],
            $data['email2'],
            $data['telefono2'],
            $data['celular'],
            $id,
            $user_id
        ]);

        if ($result && $stmt->rowCount() > 0) {
            return ['status' => true, 'message' => 'Proveedor actualizado exitosamente'];
        }
        return ['status' => false, 'message' => 'No se pudo actualizar el proveedor o no se encontró el registro'];
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

            $result = addProveedor($user_id, [
                'nombre' => $nombre,
                'email' => $email,
                'telefono' => $telefono,
                'direccion' => $direccion,
                'tipo_identificacion' => $_POST['tipo_identificacion'],
                'identificacion' => $_POST['identificacion'],
                'dv' => $_POST['dv'],
                'primer_nombre' => $_POST['primer_nombre'],
                'segundo_nombre' => $_POST['segundo_nombre'],
                'apellidos' => $_POST['apellidos'],
                'municipio_departamento' => $_POST['municipio_departamento'],
                'codigo_postal' => $_POST['codigo_postal'],
                'tipo_persona' => $_POST['tipo_persona'],
                'responsabilidad_tributaria' => $_POST['responsabilidad_tributaria'],
                'email2' => $_POST['email2'],
                'telefono2' => $_POST['telefono2'],
                'celular' => $_POST['celular']
            ]);
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'update':
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            if (!$id) {
                ApiResponse::send(false, 'ID de proveedor inválido');
            }

            // Validar campos requeridos
            $required_fields = ['nombre', 'email', 'telefono', 'direccion', 'tipo_identificacion', 
                               'identificacion', 'tipo_persona', 'responsabilidad_tributaria'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    ApiResponse::send(false, 'Por favor, complete todos los campos requeridos.');
                }
            }

            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                ApiResponse::send(false, 'Por favor, ingrese un correo electrónico válido.');
            }

            $result = updateProveedor($id, $user_id, $_POST);
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
function getUserProveedores($user_id) {
    global $pdo;
    try {
        $query = "SELECT * FROM proveedores WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener proveedores: " . $e->getMessage());
        return [];
    }
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
        if (addProveedor($user_id, [
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $telefono,
            'direccion' => $direccion,
            'tipo_identificacion' => $_POST['tipo_identificacion'],
            'identificacion' => $_POST['identificacion'],
            'dv' => $_POST['dv'],
            'primer_nombre' => $_POST['primer_nombre'],
            'segundo_nombre' => $_POST['segundo_nombre'],
            'apellidos' => $_POST['apellidos'],
            'municipio_departamento' => $_POST['municipio_departamento'],
            'codigo_postal' => $_POST['codigo_postal'],
            'tipo_persona' => $_POST['tipo_persona'],
            'responsabilidad_tributaria' => $_POST['responsabilidad_tributaria'],
            'email2' => $_POST['email2'],
            'telefono2' => $_POST['telefono2'],
            'celular' => $_POST['celular']
        ])) {
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
    <title>Proveedores | Numercia</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
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
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 mb-2">Gestión de Proveedores</h1>
                        <p class="text-gray-600">Administra la información de tus proveedores de manera eficiente</p>
                    </div>
                    <div class="flex gap-4">
                        <button onclick="showAddProveedorForm()" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center">
                            <i class="fas fa-plus mr-2"></i>
                            Nuevo Proveedor
                        </button>
                        <button onclick="showExportOptions()" 
                                class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center">
                            <i class="fas fa-file-export mr-2"></i>
                            Exportar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Buscador -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <div class="relative">
                    <input type="text" 
                           id="searchProveedor"
                           placeholder="Buscar proveedor..." 
                           class="w-full px-4 py-2 pl-10 pr-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>

            <!-- Tabla de Proveedores -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perfil</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre Completo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Identificación</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contacto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ubicación</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($proveedores as $proveedor): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <?php if ($proveedor['foto_perfil']): ?>
                                        <img class="h-10 w-10 rounded-full object-cover" 
                                             src="/uploads/proveedores/<?= htmlspecialchars($proveedor['foto_perfil']) ?>" 
                                             alt="Foto de perfil">
                                    <?php else: ?>
                                        <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                            <i class="fas fa-user text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($proveedor['nombre']) ?>
                                </div>
                                <?php if ($proveedor['tipo_persona'] === 'juridica'): ?>
                                    <div class="text-sm text-gray-500">
                                        <i class="fas fa-building mr-1"></i> Persona Jurídica
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                           <?= $proveedor['tipo_identificacion'] === 'NIT' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                    <?= htmlspecialchars($proveedor['tipo_identificacion']) ?>
                                </span>
                                <div class="text-sm text-gray-900 mt-1">
                                    <?= htmlspecialchars($proveedor['identificacion']) ?>
                                    <?php if ($proveedor['dv']): ?>-<?= htmlspecialchars($proveedor['dv']) ?><?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <i class="fas fa-envelope mr-1 text-gray-400"></i>
                                    <?= htmlspecialchars($proveedor['email']) ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-phone mr-1 text-gray-400"></i>
                                    <?= htmlspecialchars($proveedor['telefono']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?= htmlspecialchars($proveedor['municipio_departamento']) ?>
                                </div>
                                <?php if ($proveedor['direccion']): ?>
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>
                                    <?= htmlspecialchars($proveedor['direccion']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="editProveedor(<?= htmlspecialchars(json_encode($proveedor)) ?>)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    <i class="fas fa-edit"></i>
                                    <span class="ml-1">Editar</span>
                                </button>
                                <button onclick="deleteProveedor(<?= $proveedor['id'] ?>)"
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash-alt"></i>
                                    <span class="ml-1">Eliminar</span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para el formulario (inicialmente oculto) -->
    <div id="proveedorModal" class="hidden fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800" id="modalTitle">Nuevo Proveedor</h2>
                <button onclick="closeProveedorModal()" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="proveedorForm" method="POST" class="space-y-6" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <!-- Información de Identificación -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Tipo de Identificación *
                            </label>
                            <select name="tipo_identificacion" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="NIT">NIT - Número de identificación tributaria</option>
                                <option value="CC">Cédula de Ciudadanía</option>
                                <option value="CE">Cédula de Extranjería</option>
                                <option value="PA">Pasaporte</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Número de Identificación *
                                </label>
                                <input type="text" name="identificacion" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    DV
                                </label>
                                <input type="text" name="dv" maxlength="2"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Proveedor -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Tipo de Persona *
                            </label>
                            <select name="tipo_persona" required onchange="togglePersonaFields(this.value)"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="juridica">Persona Jurídica</option>
                                <option value="natural">Persona Natural</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Responsabilidad Tributaria *
                            </label>
                            <select name="responsabilidad_tributaria" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="IVA">Responsable de IVA</option>
                                <option value="NO_IVA">No Responsable de IVA</option>
                            </select>
                        </div>
                        <div class="persona-juridica">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Razón Social *
                            </label>
                            <input type="text" name="nombre" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Email *
                            </label>
                            <input type="email" name="email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Teléfono *
                            </label>
                            <input type="tel" name="telefono" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Dirección *
                            </label>
                            <input type="text" name="direccion" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Ciudad/Departamento *
                            </label>
                            <input type="text" name="municipio_departamento" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeProveedorModal()"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Guardar Proveedor
                    </button>
                </div>
            </form>
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
    document.getElementById('proveedorForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            
            if (result.status) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: result.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                closeProveedorModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al procesar la solicitud'
            });
        }
    });

    // Función para editar proveedor
    async function editProveedor(proveedor) {
        // Llenar el formulario con los datos del proveedor
        document.getElementById('modalTitle').textContent = 'Editar Proveedor';
        const form = document.getElementById('proveedorForm');
        
        // Cambiar la acción del formulario
        form.querySelector('[name="action"]').value = 'update';
        
        // Añadir el ID del proveedor
        if (!form.querySelector('[name="id"]')) {
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            form.appendChild(idInput);
        }
        form.querySelector('[name="id"]').value = proveedor.id;

        // Llenar los campos
        form.querySelector('[name="tipo_identificacion"]').value = proveedor.tipo_identificacion;
        form.querySelector('[name="identificacion"]').value = proveedor.identificacion;
        form.querySelector('[name="dv"]').value = proveedor.dv || '';
        form.querySelector('[name="tipo_persona"]').value = proveedor.tipo_persona;
        form.querySelector('[name="responsabilidad_tributaria"]').value = proveedor.responsabilidad_tributaria;
        form.querySelector('[name="nombre"]').value = proveedor.nombre;
        form.querySelector('[name="email"]').value = proveedor.email;
        form.querySelector('[name="telefono"]').value = proveedor.telefono;
        form.querySelector('[name="direccion"]').value = proveedor.direccion;
        form.querySelector('[name="municipio_departamento"]').value = proveedor.municipio_departamento;

        // Mostrar el modal
        showAddProveedorForm();

        // Actualizar la visibilidad de los campos según el tipo de persona
        togglePersonaFields(proveedor.tipo_persona);
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

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }

                const data = await response.json();
                
                if (data.status) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    location.reload();
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al eliminar el proveedor'
                });
            }
        }
    }

    function showAddProveedorForm() {
        const modal = document.getElementById('proveedorModal');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeProveedorModal() {
        const modal = document.getElementById('proveedorModal');
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        document.getElementById('proveedorForm').reset();
    }

    // Cerrar modal al hacer clic fuera de él
    document.getElementById('proveedorModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeProveedorModal();
        }
    });

    // Mejorar la búsqueda
    document.getElementById('searchProveedor').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const shouldShow = text.includes(searchTerm);
            row.style.display = shouldShow ? '' : 'none';
            
            // Añadir una transición suave
            if (shouldShow) {
                row.classList.add('opacity-100');
                row.classList.remove('opacity-0');
            } else {
                row.classList.add('opacity-0');
                row.classList.remove('opacity-100');
            }
        });
    });

    function togglePersonaFields(tipo) {
        const personaNatural = document.querySelector('.persona-natural');
        const personaJuridica = document.querySelector('.persona-juridica');
        const nombreInput = document.querySelector('[name="nombre"]');
        
        if (tipo === 'natural') {
            personaNatural?.classList.remove('hidden');
            personaJuridica?.classList.add('hidden');
            nombreInput.required = false;
            document.querySelector('[name="primer_nombre"]')?.setAttribute('required', 'required');
            document.querySelector('[name="apellidos"]')?.setAttribute('required', 'required');
        } else {
            personaNatural?.classList.add('hidden');
            personaJuridica?.classList.remove('hidden');
            nombreInput.required = true;
            document.querySelector('[name="primer_nombre"]')?.removeAttribute('required');
            document.querySelector('[name="apellidos"]')?.removeAttribute('required');
        }
    }
    </script>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <script>
    function exportToExcel() {
        // Crear array con los datos y encabezados completos
        const data = [
            [
                'Tipo de Persona',
                'Tipo de Identificación',
                'Número de Identificación',
                'DV',
                'Razón Social/Nombre',
                'Primer Nombre',
                'Segundo Nombre',
                'Apellidos',
                'Responsabilidad Tributaria',
                'Email Principal',
                'Email Secundario',
                'Teléfono Principal',
                'Teléfono Secundario',
                'Celular',
                'Departamento/Municipio',
                'Dirección',
                'Código Postal',
                'Fecha de Registro'
            ]
        ];
        
        // Obtener todas las filas de la tabla
        const rows = document.querySelectorAll('table tbody tr');
        rows.forEach(row => {
            if (!row.querySelector('td[colspan]')) { // Excluir fila de "No hay proveedores"
                const proveedor = JSON.parse(row.querySelector('button[onclick^="editProveedor"]').getAttribute('onclick').split('(')[1].split(')')[0]);
                
                // Formatear la fecha
                const fecha = new Date(proveedor.created_at);
                const fechaFormateada = fecha.toLocaleDateString('es-CO', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                data.push([
                    proveedor.tipo_persona === 'juridica' ? 'Persona Jurídica' : 'Persona Natural',
                    proveedor.tipo_identificacion,
                    proveedor.identificacion,
                    proveedor.dv || '',
                    proveedor.nombre,
                    proveedor.primer_nombre || '',
                    proveedor.segundo_nombre || '',
                    proveedor.apellidos || '',
                    proveedor.responsabilidad_tributaria === 'IVA' ? 'Responsable de IVA' : 'No Responsable de IVA',
                    proveedor.email,
                    proveedor.email2 || '',
                    proveedor.telefono,
                    proveedor.telefono2 || '',
                    proveedor.celular || '',
                    proveedor.municipio_departamento,
                    proveedor.direccion,
                    proveedor.codigo_postal || '',
                    fechaFormateada
                ]);
            }
        });

        // Crear libro de trabajo y hoja
        const ws = XLSX.utils.aoa_to_sheet(data);

        // Ajustar el ancho de las columnas
        const wscols = data[0].map(() => ({ wch: 20 })); // Ancho de 20 para todas las columnas
        ws['!cols'] = wscols;

        // Aplicar estilos a la cabecera
        const headerRange = XLSX.utils.decode_range(ws['!ref']);
        for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
            const address = XLSX.utils.encode_col(C) + "1";
            if (!ws[address]) continue;
            ws[address].s = {
                fill: { fgColor: { rgb: "4F46E5" } }, // Color de fondo
                font: { bold: true, color: { rgb: "FFFFFF" } }, // Texto en negrita y blanco
                alignment: { horizontal: "center" } // Centrar texto
            };
        }

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Proveedores");

        // Generar archivo y descargarlo con la fecha actual
        const fecha = new Date().toLocaleDateString('es-CO', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        }).replace(/\//g, '-');
        
        XLSX.writeFile(wb, `Proveedores_Numercia_${fecha}.xlsx`);
    }

    // Actualizar el botón de exportar para mostrar opciones
    function showExportOptions() {
        Swal.fire({
            title: 'Exportar Proveedores',
            text: '¿En qué formato deseas exportar la información?',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Excel',
            denyButtonText: 'CSV',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#059669',
            denyButtonColor: '#6B7280',
            cancelButtonColor: '#EF4444'
        }).then((result) => {
            if (result.isConfirmed) {
                exportToExcel();
            } else if (result.isDenied) {
                // Aquí puedes implementar la exportación a CSV si lo deseas
                Swal.fire('Próximamente', 'La exportación a CSV estará disponible pronto', 'info');
            }
        });
    }
    </script>

    <style>
    /* Animaciones para el modal */
    .modal-enter {
        transform: translateY(-4rem);
        opacity: 0;
    }

    .modal-enter-active {
        transform: translateY(0);
        opacity: 1;
        transition: all 0.3s ease-out;
    }

    /* Transiciones para la tabla */
    tbody tr {
        transition: all 0.3s ease-in-out;
    }

    .opacity-0 {
        opacity: 0;
    }

    .opacity-100 {
        opacity: 1;
    }
    </style>
</body>
</html>
