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
class ApiResponse
{
    public static function send($status, $message, $data = null)
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Funciones para manejar clientes
function getClientes($user_id)
{
    global $pdo;
    $query = "SELECT * FROM clientes WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCliente($user_id, $data)
{
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

        $query = "INSERT INTO clientes (
            user_id, nombre, email, telefono, tipo_identificacion, identificacion,
            primer_nombre, segundo_nombre, apellidos, municipio_departamento, codigo_postal,
            tipo_persona, responsabilidad_tributaria, direccion, email2, telefono2,
            celular
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $user_id,
            $nombre,
            $data['email'],
            $data['telefono'],
            $data['tipo_identificacion'],
            $data['identificacion'],
            $primer_nombre,
            $segundo_nombre,
            $apellidos,
            $data['municipio_departamento'],
            $data['codigo_postal'],
            $data['tipo_persona'],
            $data['responsabilidad_tributaria'],
            $data['direccion'],
            $data['email2'],
            $data['telefono2'],
            $data['celular']
        ]);

        // Manejar la foto de perfil si se proporcionó
        if ($result && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $cliente_id = $pdo->lastInsertId();
            $foto = $_FILES['foto_perfil'];
            $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $nuevo_nombre = "cliente_{$cliente_id}.{$extension}";
            $ruta_destino = "../../uploads/clientes/{$nuevo_nombre}";
            
            if (!is_dir("../../uploads/clientes")) {
                mkdir("../../uploads/clientes", 0777, true);
            }
            
            if (move_uploaded_file($foto['tmp_name'], $ruta_destino)) {
                $stmt = $pdo->prepare("UPDATE clientes SET foto_perfil = ? WHERE id = ?");
                $stmt->execute([$nuevo_nombre, $cliente_id]);
            }
        }

        if ($result) {
            return ['status' => true, 'message' => 'Cliente agregado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al agregar el cliente'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function updateCliente($user_id, $cliente_id, $data)
{
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

        $query = "UPDATE clientes SET 
                  nombre = ?, email = ?, telefono = ?, tipo_identificacion = ?,
                  identificacion = ?, primer_nombre = ?, segundo_nombre = ?,
                  apellidos = ?, municipio_departamento = ?, codigo_postal = ?,
                  tipo_persona = ?, responsabilidad_tributaria = ?, direccion = ?,
                  email2 = ?, telefono2 = ?, celular = ?
                  WHERE id = ? AND user_id = ?";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $nombre,
            $data['email'],
            $data['telefono'],
            $data['tipo_identificacion'],
            $data['identificacion'],
            $primer_nombre,
            $segundo_nombre,
            $apellidos,
            $data['municipio_departamento'],
            $data['codigo_postal'],
            $data['tipo_persona'],
            $data['responsabilidad_tributaria'],
            $data['direccion'],
            $data['email2'],
            $data['telefono2'],
            $data['celular'],
            $cliente_id,
            $user_id
        ]);

        // Manejar la actualización de la foto de perfil
        if ($result && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $foto = $_FILES['foto_perfil'];
            $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $nuevo_nombre = "cliente_{$cliente_id}.{$extension}";
            $ruta_destino = "../../uploads/clientes/{$nuevo_nombre}";
            
            if (!is_dir("../../uploads/clientes")) {
                mkdir("../../uploads/clientes", 0777, true);
            }
            
            if (move_uploaded_file($foto['tmp_name'], $ruta_destino)) {
                $stmt = $pdo->prepare("UPDATE clientes SET foto_perfil = ? WHERE id = ?");
                $stmt->execute([$nuevo_nombre, $cliente_id]);
            }
        }

        if ($result) {
            return ['status' => true, 'message' => 'Cliente actualizado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al actualizar el cliente'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function deleteCliente($cliente_id, $user_id)
{
    global $pdo;
    try {
        // Primero verificar si el cliente tiene ventas asociadas
        $query = "SELECT COUNT(*) FROM ventas WHERE cliente_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$cliente_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            return [
                'status' => false,
                'message' => 'No se puede eliminar el cliente porque tiene ventas asociadas',
                'hasReferences' => true
            ];
        }

        // Si no tiene ventas, proceder con la eliminación
        $query = "DELETE FROM clientes WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$cliente_id, $user_id]);

        if ($result) {
            return [
                'status' => true,
                'message' => 'Cliente eliminado exitosamente',
                'hasReferences' => false
            ];
        }
        return [
            'status' => false,
            'message' => 'Error al eliminar el cliente',
            'hasReferences' => false
        ];
    } catch (PDOException $e) {
        return [
            'status' => false,
            'message' => 'Error en la base de datos: ' . $e->getMessage(),
            'hasReferences' => strpos($e->getMessage(), 'foreign key constraint') !== false
        ];
    }
}

// Manejador de peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $result = addCliente($user_id, $_POST);
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'update':
            $cliente_id = (int)$_POST['cliente_id'];
            $result = updateCliente($user_id, $cliente_id, $_POST);
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'delete':
            $cliente_id = (int)$_POST['cliente_id'];
            $result = deleteCliente($cliente_id, $user_id);
            ApiResponse::send($result['status'], $result['message']);
            break;

        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

$clientes = getClientes($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="w-full lg:w-3/4 px-4">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-4">Gestión de Clientes</h1>
                    <p class="text-gray-600 mb-6">Administra la información de tus clientes de manera eficiente</p>
                    
                    <div class="flex flex-wrap gap-4 mb-6">
                        <button onclick="showAddClienteForm()" 
                                class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>
                            Nuevo Cliente
                        </button>
                        <button onclick="exportarClientes()" 
                                class="flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-file-export mr-2"></i>
                            Exportar
                        </button>
                    </div>

                    <div class="relative mb-6">
                        <input type="text" 
                               id="searchCliente"
                               placeholder="Buscar cliente..." 
                               class="w-full px-4 py-2 pl-10 pr-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>

                    <div class="overflow-x-auto bg-white rounded-lg shadow">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Perfil
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nombre Completo
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Identificación
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Contacto
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ubicación
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($clientes as $cliente): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex-shrink-0 h-12 w-12 group">
                                            <?php if ($cliente['foto_perfil']): ?>
                                                <img class="h-12 w-12 rounded-full object-cover border-2 border-gray-200 group-hover:border-blue-500 transition-colors duration-200" 
                                                     src="/uploads/clientes/<?= htmlspecialchars($cliente['foto_perfil']) ?>" 
                                                     alt="Foto de perfil">
                                            <?php else: ?>
                                                <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center group-hover:bg-gray-200 transition-colors duration-200">
                                                    <i class="fas fa-user text-gray-400 text-xl"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <div class="text-sm font-semibold text-gray-900">
                                                <?= htmlspecialchars($cliente['primer_nombre'] . ' ' . $cliente['apellidos']) ?>
                                            </div>
                                            <?php if ($cliente['nombre'] && $cliente['tipo_persona'] === 'juridica'): ?>
                                            <div class="text-sm text-gray-500 mt-1">
                                                <i class="fas fa-building text-gray-400 mr-1"></i>
                                                <?= htmlspecialchars($cliente['nombre']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                       <?php echo match($cliente['tipo_identificacion']) {
                                                           'CC' => 'bg-blue-100 text-blue-800',
                                                           'CE' => 'bg-purple-100 text-purple-800',
                                                           'NIT' => 'bg-green-100 text-green-800',
                                                           'PA' => 'bg-yellow-100 text-yellow-800',
                                                           default => 'bg-gray-100 text-gray-800'
                                                       } ?>">
                                                <?= htmlspecialchars($cliente['tipo_identificacion']) ?>
                                            </span>
                                            <div class="text-sm text-gray-900 mt-1 font-medium">
                                                <?= htmlspecialchars($cliente['identificacion']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col space-y-1">
                                            <div class="text-sm text-gray-900 flex items-center">
                                                <i class="fas fa-envelope text-gray-400 mr-2 w-4"></i>
                                                <?= htmlspecialchars($cliente['email']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500 flex items-center">
                                                <i class="fas fa-phone text-gray-400 mr-2 w-4"></i>
                                                <?= htmlspecialchars($cliente['telefono']) ?>
                                            </div>
                                            <?php if ($cliente['celular']): ?>
                                            <div class="text-sm text-gray-500 flex items-center">
                                                <i class="fas fa-mobile-alt text-gray-400 mr-2 w-4"></i>
                                                <?= htmlspecialchars($cliente['celular']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col space-y-1">
                                            <div class="text-sm text-gray-900 flex items-center">
                                                <i class="fas fa-map text-gray-400 mr-2 w-4"></i>
                                                <?= htmlspecialchars($cliente['municipio_departamento']) ?>
                                            </div>
                                            <?php if ($cliente['direccion']): ?>
                                            <div class="text-sm text-gray-500 flex items-center">
                                                <i class="fas fa-map-marker-alt text-gray-400 mr-2 w-4"></i>
                                                <?= htmlspecialchars($cliente['direccion']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <button onclick="editCliente(<?= htmlspecialchars(json_encode($cliente)) ?>)"
                                                    class="text-indigo-600 hover:text-indigo-900 transition-colors duration-200 flex items-center">
                                                <i class="fas fa-edit mr-1"></i>
                                                <span class="text-xs">Editar</span>
                                            </button>
                                            <button onclick="deleteCliente(<?= $cliente['id'] ?>)"
                                                    class="text-red-600 hover:text-red-900 transition-colors duration-200 flex items-center">
                                                <i class="fas fa-trash-alt mr-1"></i>
                                                <span class="text-xs">Eliminar</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="clienteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
            <div class="flex flex-col max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Nuevo Cliente</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="clienteForm" class="space-y-6 py-4" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    
                    <!-- Foto de Perfil -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-lg font-medium text-gray-700 mb-4">
                            <i class="fas fa-camera text-blue-500 mr-2"></i>
                            Foto de Perfil
                        </h4>
                        <div class="flex items-center space-x-4">
                            <div class="w-32 h-32 relative">
                                <img id="preview-foto" src="/assets/img/default-avatar.png" 
                                     class="w-full h-full object-cover rounded-full border-4 border-white shadow-lg">
                                <label class="absolute bottom-0 right-0 bg-blue-500 text-white rounded-full p-2 cursor-pointer hover:bg-blue-600">
                                    <i class="fas fa-upload"></i>
                                    <input type="file" name="foto_perfil" class="hidden" accept="image/*" 
                                           onchange="previewImage(this, 'preview-foto')">
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Identificación -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-lg font-medium text-gray-700 mb-4">
                            <i class="fas fa-id-card text-blue-500 mr-2"></i>
                            Información de Identificación
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Tipo de Persona *
                                </label>
                                <select name="tipo_persona" required onchange="togglePersonaFields(this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="natural">Persona Natural</option>
                                    <option value="juridica">Persona Jurídica</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Tipo de Identificación *
                                </label>
                                <select name="tipo_identificacion" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="CC">Cédula de Ciudadanía</option>
                                    <option value="CE">Cédula de Extranjería</option>
                                    <option value="NIT">NIT</option>
                                    <option value="PA">Pasaporte</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Número de Identificación *
                                </label>
                                <input type="text" name="identificacion" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Responsabilidad Tributaria
                                </label>
                                <select name="responsabilidad_tributaria"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccione...</option>
                                    <option value="IVA">Responsable de IVA</option>
                                    <option value="NO_IVA">No Responsable de IVA</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Información Personal/Empresarial -->
                    <div class="bg-gray-50 p-4 rounded-lg persona-natural">
                        <h4 class="text-lg font-medium text-gray-700 mb-4">
                            <i class="fas fa-user text-blue-500 mr-2"></i>
                            Información Personal
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Primer Nombre *
                                </label>
                                <input type="text" name="primer_nombre"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Segundo Nombre
                                </label>
                                <input type="text" name="segundo_nombre"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Apellidos *
                                </label>
                                <input type="text" name="apellidos"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg persona-juridica hidden">
                        <h4 class="text-lg font-medium text-gray-700 mb-4">
                            <i class="fas fa-building text-blue-500 mr-2"></i>
                            Información Empresarial
                        </h4>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nombre de la Empresa *
                            </label>
                            <input type="text" name="nombre"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Ubicación -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-lg font-medium text-gray-700 mb-4">
                            <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
                            Ubicación
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Departamento/Municipio *
                                </label>
                                <select name="municipio_departamento" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccione un departamento...</option>
                                    <option value="Amazonas">Amazonas</option>
                                    <option value="Antioquia">Antioquia</option>
                                    <option value="Arauca">Arauca</option>
                                    <option value="Atlántico">Atlántico</option>
                                    <option value="Bogotá D.C.">Bogotá D.C.</option>
                                    <option value="Bolívar">Bolívar</option>
                                    <option value="Boyacá">Boyacá</option>
                                    <option value="Caldas">Caldas</option>
                                    <option value="Caquetá">Caquetá</option>
                                    <option value="Casanare">Casanare</option>
                                    <option value="Cauca">Cauca</option>
                                    <option value="Cesar">Cesar</option>
                                    <option value="Chocó">Chocó</option>
                                    <option value="Córdoba">Córdoba</option>
                                    <option value="Cundinamarca">Cundinamarca</option>
                                    <option value="Guainía">Guainía</option>
                                    <option value="Guaviare">Guaviare</option>
                                    <option value="Huila">Huila</option>
                                    <option value="La Guajira">La Guajira</option>
                                    <option value="Magdalena">Magdalena</option>
                                    <option value="Meta">Meta</option>
                                    <option value="Nariño">Nariño</option>
                                    <option value="Norte de Santander">Norte de Santander</option>
                                    <option value="Putumayo">Putumayo</option>
                                    <option value="Quindío">Quindío</option>
                                    <option value="Risaralda">Risaralda</option>
                                    <option value="San Andrés y Providencia">San Andrés y Providencia</option>
                                    <option value="Santander">Santander</option>
                                    <option value="Sucre">Sucre</option>
                                    <option value="Tolima">Tolima</option>
                                    <option value="Valle del Cauca">Valle del Cauca</option>
                                    <option value="Vaupés">Vaupés</option>
                                    <option value="Vichada">Vichada</option>
                                </select>
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
                                    Código Postal
                                </label>
                                <input type="text" name="codigo_postal"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-lg font-medium text-gray-700 mb-4">
                            <i class="fas fa-address-book text-blue-500 mr-2"></i>
                            Información de Contacto
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Principal *
                                </label>
                                <input type="email" name="email" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Secundario
                                </label>
                                <input type="email" name="email2"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Teléfono Principal *
                                </label>
                                <input type="tel" name="telefono" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Teléfono Secundario
                                </label>
                                <input type="tel" name="telefono2"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Celular
                                </label>
                                <input type="tel" name="celular"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </form>

                <div class="flex justify-end space-x-4 pt-4 border-t mt-6">
                    <button type="button" onclick="closeModal()"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                    <button type="submit" form="clienteForm"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Guardar Cliente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showAddClienteForm() {
        const modal = document.getElementById('clienteModal');
        modal.classList.remove('hidden');
        modal.classList.add('fade-in');
        // ... resto del código
    }

    function closeModal() {
        const modal = document.getElementById('clienteModal');
        modal.classList.add('fade-out');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('fade-out');
        }, 300);
        // ... resto del código
    }

    function showNotification(type, message) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: type === 'success' ? '#10B981' : '#EF4444',
            color: '#ffffff',
            customClass: {
                popup: 'rounded-lg',
                title: 'text-white'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
    }

    // ... mantener el resto de funciones JavaScript actualizando las clases CSS

    document.getElementById('clienteForm').addEventListener('submit', async function(e) {
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
                showNotification('success', result.message);
                closeModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('error', result.message);
            }
        } catch (error) {
            showNotification('error', 'Error al procesar la solicitud');
        }
    });

    // Función para manejar el código postal según el departamento
    document.getElementById('departamento').addEventListener('change', function() {
        const codigosPostales = {
            'Amazonas': '910001',
            'Antioquia': '050001',
            'Arauca': '810001',
            'Atlántico': '080001',
            'Bogotá D.C.': '110111',
            'Bolívar': '130001',
            'Boyacá': '150001',
            'Caldas': '170001',
            'Caquetá': '180001',
            'Casanare': '850001',
            'Cauca': '190001',
            'Cesar': '200001',
            'Chocó': '270001',
            'Córdoba': '230001',
            'Cundinamarca': '250001',
            'Guainía': '940001',
            'Guaviare': '950001',
            'Huila': '410001',
            'La Guajira': '440001',
            'Magdalena': '470001',
            'Meta': '500001',
            'Nariño': '520001',
            'Norte de Santander': '540001',
            'Putumayo': '860001',
            'Quindío': '630001',
            'Risaralda': '660001',
            'San Andrés y Providencia': '880001',
            'Santander': '680001',
            'Sucre': '700001',
            'Tolima': '730001',
            'Valle del Cauca': '760001',
            'Vaupés': '970001',
            'Vichada': '990001'
        };
        
        const codigoPostalInput = document.getElementById('codigo_postal');
        codigoPostalInput.value = codigosPostales[this.value] || '';
        
        // Hacer el campo de solo lectura pero con apariencia normal
        codigoPostalInput.readOnly = true;
        codigoPostalInput.style.backgroundColor = '#f3f4f6';
        codigoPostalInput.style.cursor = 'default';
    });

    function exportarClientes() {
        Swal.fire({
            title: 'Exportar Clientes',
            text: 'Selecciona el formato de exportación',
            icon: 'question',
            showCancelButton: true,
            showCloseButton: true,
            showDenyButton: true,
            confirmButtonText: 'Excel',
            cancelButtonText: 'CSV',
            denyButtonText: 'Cancelar',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6B7280',
            denyButtonColor: '#EF4444',
            background: '#ffffff',
            color: '#1F2937'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'export.php?format=excel';
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                window.location.href = 'export.php?format=csv';
            }
        });
    }

    async function editCliente(cliente) {
        // Mostrar el modal
        const modal = document.getElementById('clienteModal');
        modal.classList.remove('hidden');
        
        // Actualizar el título del modal
        document.getElementById('modalTitle').textContent = 'Editar Cliente';
        
        // Actualizar el formulario para edición
        const form = document.getElementById('clienteForm');
        form.action.value = 'update';
        
        // Agregar el ID del cliente
        const clienteIdInput = document.createElement('input');
        clienteIdInput.type = 'hidden';
        clienteIdInput.name = 'cliente_id';
        clienteIdInput.value = cliente.id;
        form.appendChild(clienteIdInput);
        
        // Actualizar la foto de perfil
        const previewImg = document.getElementById('preview-foto');
        if (cliente.foto_perfil) {
            previewImg.src = `/uploads/clientes/${cliente.foto_perfil}`;
        } else {
            previewImg.src = '/assets/img/default-avatar.png';
        }
        
        // Rellenar los campos del formulario
        form.tipo_persona.value = cliente.tipo_persona || 'natural';
        form.tipo_identificacion.value = cliente.tipo_identificacion;
        form.identificacion.value = cliente.identificacion;
        form.responsabilidad_tributaria.value = cliente.responsabilidad_tributaria || '';
        form.email.value = cliente.email;
        form.email2.value = cliente.email2 || '';
        form.telefono.value = cliente.telefono;
        form.telefono2.value = cliente.telefono2 || '';
        form.celular.value = cliente.celular || '';
        form.municipio_departamento.value = cliente.municipio_departamento;
        form.direccion.value = cliente.direccion || '';
        form.codigo_postal.value = cliente.codigo_postal || '';

        // Manejar campos según tipo de persona
        togglePersonaFields(cliente.tipo_persona);
        if (cliente.tipo_persona === 'natural') {
            form.primer_nombre.value = cliente.primer_nombre;
            form.segundo_nombre.value = cliente.segundo_nombre || '';
            form.apellidos.value = cliente.apellidos;
        } else {
            form.nombre.value = cliente.nombre;
        }
    }

    async function deleteCliente(clienteId) {
        const result = await Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            background: '#ffffff',
            color: '#1F2937'
        });

        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('cliente_id', clienteId);

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
                    showNotification('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    if (result.hasReferences) {
                        Swal.fire({
                            title: 'No se puede eliminar',
                            text: result.message,
                            icon: 'error',
                            confirmButtonText: 'Entendido'
                        });
                    } else {
                        showNotification('error', result.message);
                    }
                }
            } catch (error) {
                showNotification('error', 'Error al procesar la solicitud');
            }
        }
    }

    // Función para filtrar clientes
    document.getElementById('searchCliente').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    function previewImage(input, previewId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function togglePersonaFields(tipo) {
        const personaNatural = document.querySelector('.persona-natural');
        const personaJuridica = document.querySelector('.persona-juridica');
        
        if (tipo === 'natural') {
            personaNatural.classList.remove('hidden');
            personaJuridica.classList.add('hidden');
            document.querySelector('[name="primer_nombre"]').required = true;
            document.querySelector('[name="apellidos"]').required = true;
            document.querySelector('[name="nombre"]').required = false;
        } else {
            personaNatural.classList.add('hidden');
            personaJuridica.classList.remove('hidden');
            document.querySelector('[name="primer_nombre"]').required = false;
            document.querySelector('[name="apellidos"]').required = false;
            document.querySelector('[name="nombre"]').required = true;
        }
    }
    </script>

    <style>
    .fade-in {
        animation: fadeIn 0.3s ease-in-out;
    }

    .fade-out {
        animation: fadeOut 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    </style>
</body>
</html>