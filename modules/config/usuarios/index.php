<?php
session_start();
require_once '../../../config/db.php';

// Código de depuración - Agregar al inicio del archivo después de require_once '../../../config/db.php';
error_log("=== INICIO DE DEPURACIÓN ===");

// Verificar sesión actual
error_log("SESSION user_id: " . ($_SESSION['user_id'] ?? 'no definido'));

// Consulta directa a la tabla users
try {
    $debug_stmt = $pdo->query("
        SELECT id, nombre, email, empresa_id, estado 
        FROM users 
        WHERE estado = 'activo'
    ");
    error_log("Usuarios activos en la base de datos:");
    error_log(print_r($debug_stmt->fetchAll(PDO::FETCH_ASSOC), true));
} catch (Exception $e) {
    error_log("Error en consulta de depuración: " . $e->getMessage());
}

// Consulta a la tabla empresas
try {
    $debug_stmt = $pdo->query("
        SELECT id, nombre, usuario_id
        FROM empresas
    ");
    error_log("Empresas en la base de datos:");
    error_log(print_r($debug_stmt->fetchAll(PDO::FETCH_ASSOC), true));
} catch (Exception $e) {
    error_log("Error en consulta de empresas: " . $e->getMessage());
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../index.php");
    exit();
}

// Manejar las peticiones AJAX
if (isset($_POST['action'])) {
    // Limpiar cualquier salida previa
    if (ob_get_length()) ob_clean();
    
    // Asegurarnos de que estamos enviando JSON
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Sesión no válida");
        }

        $response = ['success' => false, 'message' => '', 'data' => null];
        
        switch ($_POST['action']) {
            case 'crear_usuario':
                if (empty($_POST['nombre']) || empty($_POST['email']) || 
                    empty($_POST['password']) || empty($_POST['rol'])) {
                    throw new Exception("Todos los campos son requeridos");
                }

                // Validar email
                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Email inválido");
                }

                // Validar contraseña
                if (strlen($_POST['password']) < 6) {
                    throw new Exception("La contraseña debe tener al menos 6 caracteres");
                }

                $result = crearUsuario([
                    'nombre' => trim($_POST['nombre']),
                    'email' => trim($_POST['email']),
                    'password' => $_POST['password'],
                    'rol' => $_POST['rol']
                ]);

                if ($result) {
                    $response['success'] = true;
                    $response['message'] = 'Usuario creado exitosamente';
                } else {
                    throw new Exception("Error al crear el usuario");
                }
                break;

            case 'actualizar_usuario':
                if (empty($_POST['user_id']) || empty($_POST['nombre']) || 
                    empty($_POST['rol']) || !isset($_POST['estado'])) {
                    throw new Exception("Datos incompletos");
                }

                $result = actualizarUsuario($_POST['user_id'], [
                    'nombre' => trim($_POST['nombre']),
                    'rol' => $_POST['rol'],
                    'estado' => $_POST['estado'],
                    'password' => $_POST['password'] ?? ''
                ]);

                $response['success'] = true;
                $response['message'] = 'Usuario actualizado exitosamente';
                break;

            case 'eliminar_usuario':
                if (empty($_POST['user_id'])) {
                    throw new Exception("ID de usuario no proporcionado");
                }

                if ($_POST['user_id'] == $_SESSION['user_id']) {
                    throw new Exception("No puedes eliminar tu propio usuario");
                }

                // Obtener empresa_id del usuario actual
                $stmt = $pdo->prepare("SELECT empresa_id FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $empresa_id = $stmt->fetchColumn();

                if (!$empresa_id) {
                    throw new Exception("No hay una empresa asociada al usuario");
                }

                if (eliminarUsuario($_POST['user_id'], $empresa_id)) {
                    $response['success'] = true;
                    $response['message'] = 'Usuario desactivado exitosamente';
                } else {
                    throw new Exception("No se pudo desactivar el usuario");
                }
                break;

            default:
                throw new Exception("Acción no válida");
        }
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

$user_id = $_SESSION['user_id'] ?? null;
$email = $_SESSION['email'] ?? null;

if (!$user_id) {
    header("Location: ../../../index.php");
    exit();
}

$user_info = getUserInfo($user_id);
error_log("User Info para ID {$user_id}: " . print_r($user_info, true));

if (!$user_info || empty($user_info['empresa_id'])) {
    error_log("No se pudo obtener la empresa_id para el usuario {$user_id}");
    $error_message = "No se pudo obtener la información del usuario o la empresa asociada.";
    $usuarios = [];
} else {
    error_log("Obteniendo usuarios para empresa_id: " . $user_info['empresa_id']);
    $usuarios = getUsuarios($user_info['empresa_id']);
    error_log("Usuarios obtenidos: " . count($usuarios));
}

// Funciones necesarias
function getUserInfo($user_id) {
    global $pdo;
    try {
        error_log("Obteniendo información para user_id: " . $user_id);
        
        // Consulta principal
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.email,
                u.nombre,
                u.rol,
                u.empresa_id,
                e.id as empresa_relacionada_id
            FROM users u
            LEFT JOIN empresas e ON e.usuario_id = u.id
            WHERE u.id = ?
            AND u.estado = 'activo'
        ");
        
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Resultado inicial getUserInfo: " . print_r($result, true));
        
        if (!$result) {
            error_log("No se encontró el usuario: " . $user_id);
            return false;
        }

        // Determinar empresa_id
        $empresa_id = $result['empresa_id'] ?? $result['empresa_relacionada_id'] ?? null;
        
        if (!$empresa_id) {
            // Buscar en la tabla empresas
            $stmt = $pdo->prepare("
                SELECT id as empresa_id
                FROM empresas
                WHERE usuario_id = ?
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($empresa) {
                $empresa_id = $empresa['empresa_id'];
            }
        }
        
        $result['empresa_id'] = $empresa_id;
        error_log("Información final del usuario: " . print_r($result, true));
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error en getUserInfo: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

function getRoles() {
    return [
        'administrador' => 'Administrador',
        'contador' => 'Contador', 
        'supervisor' => 'Supervisor',
        'cajero' => 'Cajero',
        'cliente' => 'Cliente'
    ];
}

function getUsuarios($empresa_id) {
    global $pdo;
    
    error_log("Buscando usuarios para empresa_id: " . $empresa_id);
    
    if (empty($empresa_id)) {
        error_log("empresa_id está vacío");
        return [];
    }
    
    try {
        // Primera búsqueda - usuarios directamente relacionados
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                u.id,
                u.nombre,
                u.email,
                u.rol,
                u.estado,
                u.fecha_creacion,
                u.empresa_id
            FROM users u
            WHERE u.empresa_id = :empresa_id
            AND u.estado = 'activo'
        ");
        
        $stmt->execute([':empresa_id' => $empresa_id]);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Primera búsqueda - usuarios encontrados: " . count($usuarios));
        
        // Segunda búsqueda - usuarios relacionados a través de la tabla empresas
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                u.id,
                u.nombre,
                u.email,
                u.rol,
                u.estado,
                u.fecha_creacion,
                :empresa_id as empresa_id
            FROM users u
            INNER JOIN empresas e ON e.usuario_id = u.id
            WHERE e.id = :empresa_id
            AND u.estado = 'activo'
            AND u.id NOT IN (SELECT id FROM users WHERE empresa_id = :empresa_id)
        ");
        
        $stmt->execute([':empresa_id' => $empresa_id]);
        $usuarios_adicionales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Segunda búsqueda - usuarios adicionales encontrados: " . count($usuarios_adicionales));
        
        $todos_usuarios = array_merge($usuarios, $usuarios_adicionales);
        error_log("Total usuarios encontrados: " . count($todos_usuarios));
        error_log("Usuarios encontrados: " . print_r($todos_usuarios, true));
        
        return $todos_usuarios;
        
    } catch (PDOException $e) {
        error_log("Error en getUsuarios: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

// Agregar estas funciones después de getUsuarios()

function crearUsuario($data) {
    global $pdo;
    try {
        $pdo->beginTransaction();

        // Verificar email único
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("El correo electrónico ya está registrado");
        }

        // Validar rol
        $roles_validos = array_keys(getRoles());
        if (!in_array($data['rol'], $roles_validos)) {
            throw new Exception("Rol no válido");
        }

        // Obtener empresa_id del usuario que está creando
        $stmt = $pdo->prepare("
            SELECT u.empresa_id, e.id as empresa_id_from_empresas 
            FROM users u 
            LEFT JOIN empresas e ON e.usuario_id = u.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $empresa_id = $result['empresa_id'] ?? $result['empresa_id_from_empresas'];

        if (!$empresa_id) {
            throw new Exception("No se pudo determinar la empresa para asociar al nuevo usuario");
        }

        // Insertar nuevo usuario
        $stmt = $pdo->prepare("
            INSERT INTO users (
                nombre, 
                email, 
                password, 
                rol, 
                empresa_id, 
                estado,
                fecha_creacion
            ) VALUES (
                :nombre,
                :email,
                :password,
                :rol,
                :empresa_id,
                :estado,
                CURRENT_TIMESTAMP
            )
        ");

        $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $params = [
            ':nombre' => $data['nombre'],
            ':email' => $data['email'],
            ':password' => $hashed_password,
            ':rol' => $data['rol'],
            ':empresa_id' => $empresa_id, // Usar el empresa_id obtenido del usuario creador
            ':estado' => 'activo'
        ];

        if (!$stmt->execute($params)) {
            throw new Exception("Error al crear el usuario");
        }

        $nuevo_usuario_id = $pdo->lastInsertId();
        $pdo->commit();
        return $nuevo_usuario_id;

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error creando usuario: " . $e->getMessage());
        throw new Exception("Error al crear el usuario: " . $e->getMessage());
    }
}

function actualizarUsuario($user_id, $data) {
    global $pdo;
    try {
        $sql = "UPDATE users SET nombre = ?, rol = ?, estado = ?";
        $params = [$data['nombre'], $data['rol'], $data['estado']];

        // Si se proporciona una nueva contraseña, actualizarla
        if (!empty($data['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Error actualizando usuario: " . $e->getMessage());
        throw new Exception("Error al actualizar el usuario");
    }
}

function eliminarUsuario($user_id, $empresa_id) {
    global $pdo;
    try {
        // Verificar que el usuario pertenezca a la empresa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$user_id, $empresa_id]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Usuario no encontrado o no pertenece a tu empresa");
        }

        // Actualizar estado y fecha de desactivación
        $stmt = $pdo->prepare("
            UPDATE users 
            SET estado = 'inactivo', 
                fecha_desactivacion = CURRENT_TIMESTAMP 
            WHERE id = ? AND empresa_id = ?
        ");
        
        if (!$stmt->execute([$user_id, $empresa_id])) {
            throw new Exception("Error al desactivar el usuario");
        }

        return true;

    } catch (Exception $e) {
        error_log("Error desactivando usuario: " . $e->getMessage());
        throw new Exception("Error al desactivar el usuario");
    }
}

// Incluir las demás funciones necesarias (crearUsuario, actualizarUsuario, eliminarUsuario)
// ... (copiar las funciones del archivo original)

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - VendEasy</title>
    <link rel="icon" type="image/png" href="../../../favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4">
        <?php include '../../../includes/sidebar.php'; ?>

        <div class="p-4 sm:ml-64">
            <!-- Encabezado -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800">Gestión de Usuarios</h2>
                <p class="text-gray-600">Administra los usuarios y sus permisos de manera eficiente</p>
            </div>

            <!-- Tarjetas de estadísticas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500">Total Usuarios</p>
                            <p class="text-2xl font-semibold"><?= count($usuarios) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-user-check text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500">Usuarios Activos</p>
                            <p class="text-2xl font-semibold">
                                <?= count(array_filter($usuarios, fn($u) => $u['estado'] === 'activo')) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-500">Último Registro</p>
                            <p class="text-2xl font-semibold">
                                <?php 
                                $ultimo = array_reduce($usuarios, function($carry, $item) {
                                    return (!$carry || strtotime($item['fecha_creacion']) > strtotime($carry['fecha_creacion'])) ? $item : $carry;
                                });
                                echo $ultimo ? date('d/m/Y', strtotime($ultimo['fecha_creacion'])) : 'N/A';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra de acciones -->
            <div class="flex flex-wrap gap-4 mb-6">
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input type="text" 
                               id="searchUsuarios" 
                               class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                               placeholder="Buscar usuarios...">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>

                <select id="filterRol" 
                        class="rounded-lg border border-gray-300 py-2 px-4 focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos los roles</option>
                    <?php foreach (getRoles() as $key => $value): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($value) ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="filterEstado" 
                        class="rounded-lg border border-gray-300 py-2 px-4 focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos los estados</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>

                <button onclick="abrirModalUsuario()" 
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    <span>Nuevo Usuario</span>
                </button>
            </div>

            <!-- Tabla de usuarios -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Usuario
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Rol
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Último Acceso
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12">
                                    <div class="text-center">
                                        <i class="fas fa-users-slash text-4xl text-gray-400 mb-4"></i>
                                        <p class="text-gray-500 mb-4">No hay usuarios registrados</p>
                                        <button onclick="abrirModalUsuario()" 
                                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                            Agregar el primer usuario
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <span class="text-blue-600 font-medium">
                                                        <?= strtoupper(substr($usuario['nombre'], 0, 2)) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($usuario['nombre']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= htmlspecialchars($usuario['email']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-sm
                                            <?php
                                            switch($usuario['rol']) {
                                                case 'administrador':
                                                    echo 'bg-purple-100 text-purple-800';
                                                    break;
                                                case 'contador':
                                                    echo 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'supervisor':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= htmlspecialchars(getRoles()[$usuario['rol']] ?? $usuario['rol']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-sm
                                            <?= $usuario['estado'] === 'activo' 
                                                ? 'bg-green-100 text-green-800' 
                                                : 'bg-red-100 text-red-800' ?>">
                                            <?= ucfirst($usuario['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?= $usuario['ultimo_acceso'] 
                                            ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) 
                                            : 'Nunca' ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end space-x-2">
                                            <button onclick="editarUsuario(<?= htmlspecialchars(json_encode($usuario)) ?>)"
                                                    class="text-blue-600 hover:text-blue-900 p-2 rounded-full hover:bg-blue-50">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="eliminarUsuario(<?= $usuario['id'] ?>)"
                                                    class="text-red-600 hover:text-red-900 p-2 rounded-full hover:bg-red-50">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Usuario - Versión mejorada -->
    <div id="modalUsuario" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-8 border w-[600px] shadow-xl rounded-xl bg-white">
            <!-- Encabezado del modal -->
            <div class="flex justify-between items-center pb-6 border-b">
                <h3 class="text-2xl font-bold text-gray-900" id="modalTitle">Crear Nuevo Usuario</h3>
                <button onclick="cerrarModal()" class="text-gray-400 hover:text-gray-500 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="formUsuario" class="mt-6">
                <input type="hidden" id="user_id" name="user_id">
                
                <!-- Grid de 2 columnas -->
                <div class="grid grid-cols-2 gap-6">
                    <!-- Columna izquierda -->
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Nombre completo
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       id="nombre_usuario" 
                                       name="nombre"
                                       class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Ingrese el nombre completo"
                                       required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Correo electrónico
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" 
                                       id="email_usuario" 
                                       name="email"
                                       class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="correo@ejemplo.com"
                                       required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Contraseña
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       id="password_usuario" 
                                       name="password"
                                       class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Mínimo 6 caracteres"
                                       minlength="6">
                                <p class="text-sm text-gray-500 mt-1.5 password-hint hidden">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Dejar en blanco para mantener la contraseña actual
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Columna derecha -->
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Rol del usuario
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                    <i class="fas fa-user-tag"></i>
                                </span>
                                <select id="rol_usuario" 
                                        name="rol"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none"
                                        required>
                                    <option value="">Seleccione un rol</option>
                                    <?php foreach (getRoles() as $key => $value): ?>
                                        <option value="<?= htmlspecialchars($key) ?>">
                                            <?= htmlspecialchars($value) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 pointer-events-none">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Estado
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">
                                    <i class="fas fa-toggle-on"></i>
                                </span>
                                <select id="estado_usuario" 
                                        name="estado"
                                        class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none"
                                        required>
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 pointer-events-none">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="flex justify-end space-x-4 mt-8 pt-6 border-t">
                    <button type="button" 
                            onclick="cerrarModal()"
                            class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors flex items-center">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Mantener las funciones JavaScript existentes pero actualizar las notificaciones y UI
    
    function showNotification(title, message, type = 'success') {
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
        })

        Toast.fire({
            icon: type,
            title: message
        })
    }

    function eliminarUsuario(userId) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "El usuario será desactivado",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Sí, desactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mantener la lógica existente de eliminación
                const formData = new FormData();
                formData.append('action', 'eliminar_usuario');
                formData.append('user_id', userId);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('¡Éxito!', data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message
                    });
                });
            }
        });
    }

    // Variables globales
    let usuarioActual = null;

    // Función para abrir el modal
    function abrirModalUsuario() {
        usuarioActual = null;
        const form = document.getElementById('formUsuario');
        form.reset();
        
        // Configurar modal para nuevo usuario
        document.querySelector('#modalTitle').textContent = 'Crear Nuevo Usuario';
        document.getElementById('user_id').value = '';
        document.getElementById('email_usuario').readOnly = false;
        document.getElementById('password_usuario').required = true;
        document.querySelector('.password-hint').style.display = 'none';
        
        // Mostrar modal
        document.getElementById('modalUsuario').classList.remove('hidden');
    }

    // Función para cerrar el modal
    function cerrarModal() {
        document.getElementById('modalUsuario').classList.add('hidden');
        document.getElementById('formUsuario').reset();
    }

    // Función para editar usuario
    function editarUsuario(usuario) {
        usuarioActual = usuario;
        
        // Llenar el formulario con los datos del usuario
        document.querySelector('#modalTitle').textContent = 'Editar Usuario';
        document.getElementById('user_id').value = usuario.id;
        document.getElementById('nombre_usuario').value = usuario.nombre;
        document.getElementById('email_usuario').value = usuario.email;
        document.getElementById('email_usuario').readOnly = true;
        document.getElementById('password_usuario').required = false;
        document.getElementById('rol_usuario').value = usuario.rol;
        document.getElementById('estado_usuario').value = usuario.estado;
        
        // Mostrar hint de contraseña en modo edición
        document.querySelector('.password-hint').style.display = 'block';
        
        // Mostrar modal
        document.getElementById('modalUsuario').classList.remove('hidden');
    }

    // Función para guardar usuario
    function guardarUsuario() {
        const form = document.getElementById('formUsuario');
        const formData = new FormData(form);
        
        // Agregar la acción correspondiente
        formData.append('action', usuarioActual ? 'actualizar_usuario' : 'crear_usuario');

        // Validaciones básicas
        if (!formData.get('nombre').trim()) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El nombre es requerido'
            });
            return;
        }

        if (!usuarioActual && !formData.get('email').trim()) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El email es requerido'
            });
            return;
        }

        if (!usuarioActual && !formData.get('password').trim()) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'La contraseña es requerida para nuevos usuarios'
            });
            return;
        }

        // Mostrar indicador de carga
        Swal.fire({
            title: 'Guardando...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Realizar la petición
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: data.message,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error(data.message || 'Error al guardar usuario');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al procesar la solicitud'
            });
        });
    }

    // Reemplazar la función filtrarUsuarios actual por esta versión corregida
    function filtrarUsuarios() {
        const searchTerm = document.getElementById('searchUsuarios').value.toLowerCase();
        const rolFiltro = document.getElementById('filterRol').value.toLowerCase();
        const estadoFiltro = document.getElementById('filterEstado').value.toLowerCase();
        
        const rows = document.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            // Ignorar la fila de "no hay usuarios" si existe
            if (row.querySelector('.text-center')) return;
            
            // Obtener los valores de las celdas
            const nombreCell = row.querySelector('.text-sm.font-medium.text-gray-900');
            const emailCell = row.querySelector('.text-sm.text-gray-500');
            const rolCell = row.querySelector('td:nth-child(2) span');
            const estadoCell = row.querySelector('td:nth-child(3) span');
            
            if (!nombreCell || !emailCell || !rolCell || !estadoCell) return;
            
            const nombre = nombreCell.textContent.toLowerCase();
            const email = emailCell.textContent.toLowerCase();
            const rol = rolCell.textContent.toLowerCase();
            const estado = estadoCell.textContent.toLowerCase();
            
            // Aplicar filtros
            const matchSearch = nombre.includes(searchTerm) || email.includes(searchTerm);
            const matchRol = !rolFiltro || rol.includes(rolFiltro);
            const matchEstado = !estadoFiltro || estado.includes(estadoFiltro);
            
            // Mostrar u ocultar fila
            if (matchSearch && matchRol && matchEstado) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Manejar mensaje de no resultados
        const tbody = document.querySelector('tbody');
        const existingNoResults = document.querySelector('.no-results');
        
        if (visibleCount === 0) {
            if (!existingNoResults) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results';
                noResultsRow.innerHTML = `
                    <td colspan="5" class="px-6 py-12">
                        <div class="text-center">
                            <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500 mb-4">No se encontraron usuarios con los filtros seleccionados</p>
                            <button onclick="limpiarFiltros()" 
                                    class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
                                Limpiar filtros
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(noResultsRow);
            }
        } else if (existingNoResults) {
            existingNoResults.remove();
        }
    }

    // Función para limpiar filtros
    function limpiarFiltros() {
        document.getElementById('searchUsuarios').value = '';
        document.getElementById('filterRol').value = '';
        document.getElementById('filterEstado').value = '';
        
        // Mostrar todas las filas
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            if (!row.querySelector('.text-center')) {
                row.style.display = '';
            }
        });
        
        // Eliminar mensaje de no resultados si existe
        const noResults = document.querySelector('.no-results');
        if (noResults) {
            noResults.remove();
        }
    }

    // Asegurarse de que los event listeners estén correctamente configurados
    document.addEventListener('DOMContentLoaded', function() {
        // Event listener para la búsqueda con debounce
        const searchInput = document.getElementById('searchUsuarios');
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filtrarUsuarios, 300);
        });

        // Event listeners para los filtros
        document.getElementById('filterRol').addEventListener('change', filtrarUsuarios);
        document.getElementById('filterEstado').addEventListener('change', filtrarUsuarios);

        // Agregar el event listener para el formulario
        document.getElementById('formUsuario').addEventListener('submit', function(e) {
            e.preventDefault();
            guardarUsuario();
        });
    });
    </script>
</body>
</html> 