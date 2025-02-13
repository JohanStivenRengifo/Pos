<?php
session_start();
require_once '../../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Verificar si el usuario tiene permisos de administrador
$stmt = $pdo->prepare("SELECT rol FROM users WHERE id = ? AND empresa_id = ?");
$stmt->execute([$_SESSION['user_id'], $_SESSION['empresa_id']]);
$usuario_actual = $stmt->fetch();

if ($usuario_actual['rol'] !== 'administrador') {
    header('Location: ../../../errors/403.php');
    exit;
}

// Obtener lista de usuarios de la empresa
$stmt = $pdo->prepare("
    SELECT id, nombre, email, rol, estado, fecha_creacion, fecha_desactivacion 
    FROM users 
    WHERE empresa_id = ? 
    ORDER BY fecha_creacion DESC
");
$stmt->execute([$_SESSION['empresa_id']]);
$usuarios = $stmt->fetchAll();

// Procesar acciones de usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    // Verificar si el email ya existe
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND empresa_id = ?");
                    $stmt->execute([$_POST['email'], $_SESSION['empresa_id']]);
                    if ($stmt->fetchColumn() > 0) {
                        $_SESSION['error_message'] = "El email ya está registrado en el sistema";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO users (nombre, email, password, rol, estado, empresa_id, fecha_creacion) 
                        VALUES (?, ?, ?, ?, 'activo', ?, CURRENT_TIMESTAMP)
                    ");
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt->execute([
                        $_POST['nombre'],
                        $_POST['email'],
                        $password_hash,
                        $_POST['rol'],
                        $_SESSION['empresa_id']
                    ]);
                    $_SESSION['success_message'] = "Usuario creado correctamente";
                    break;

                case 'update':
                    // Verificar si el email ya existe para otro usuario
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND empresa_id = ? AND id != ?");
                    $stmt->execute([$_POST['email'], $_SESSION['empresa_id'], $_POST['user_id']]);
                    if ($stmt->fetchColumn() > 0) {
                        $_SESSION['error_message'] = "El email ya está registrado en el sistema";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    }

                    $updates = ["nombre = ?", "email = ?", "rol = ?"];
                    $params = [$_POST['nombre'], $_POST['email'], $_POST['rol']];

                    if (!empty($_POST['password'])) {
                        $updates[] = "password = ?";
                        $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    }

                    $params[] = $_POST['user_id'];
                    $params[] = $_SESSION['empresa_id'];

                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET " . implode(", ", $updates) . "
                        WHERE id = ? AND empresa_id = ?
                    ");
                    $stmt->execute($params);
                    $_SESSION['success_message'] = "Usuario actualizado correctamente";
                    break;

                case 'toggle_status':
                    // Evitar que el usuario se desactive a sí mismo
                    if ($_POST['user_id'] == $_SESSION['user_id']) {
                        $_SESSION['error_message'] = "No puedes desactivar tu propia cuenta";
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit;
                    }

                    // Verificar si es el último administrador activo
                    if ($_POST['estado'] === 'activo') {
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM users 
                            WHERE empresa_id = ? AND rol = 'administrador' AND estado = 'activo' AND id != ?
                        ");
                        $stmt->execute([$_SESSION['empresa_id'], $_POST['user_id']]);
                        if ($stmt->fetchColumn() == 0) {
                            $_SESSION['error_message'] = "No puedes desactivar al último administrador activo";
                            header("Location: " . $_SERVER['PHP_SELF']);
                            exit;
                        }
                    }

                    $nuevo_estado = $_POST['estado'] === 'activo' ? 'inactivo' : 'activo';
                    $fecha_campo = $nuevo_estado === 'inactivo' ? 'fecha_desactivacion = CURRENT_TIMESTAMP' : 'fecha_desactivacion = NULL';
                    
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET estado = ?, {$fecha_campo}
                        WHERE id = ? AND empresa_id = ?
                    ");
                    $stmt->execute([$nuevo_estado, $_POST['user_id'], $_SESSION['empresa_id']]);
                    $_SESSION['success_message'] = "Estado del usuario actualizado correctamente";
                    break;
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error en la operación: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | VendEasy</title>
    <link rel="icon" href="../../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../../includes/sidebar.php'; ?>

            <!-- Contenido Principal -->
            <div class="w-full lg:w-3/4 px-4">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-users-cog mr-2"></i>Gestión de Usuarios
                        </h1>
                        <button onclick="mostrarFormularioCrear()" 
                                class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Nuevo Usuario
                        </button>
                    </div>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                            <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                            <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Tabla de Usuarios -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Creación</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($usuario['nombre']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($usuario['email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                                <?php echo ucfirst($usuario['rol']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $usuario['estado'] === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo ucfirst($usuario['estado']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d/m/Y', strtotime($usuario['fecha_creacion'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick="editarUsuario(<?php echo htmlspecialchars(json_encode($usuario)); ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                                <input type="hidden" name="estado" value="<?php echo $usuario['estado']; ?>">
                                                <button type="submit" class="<?php echo $usuario['estado'] === 'activo' ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                                    <i class="fas <?php echo $usuario['estado'] === 'activo' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                                </button>
                                            </form>
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

    <!-- Modal de Crear/Editar Usuario -->
    <div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Crear Usuario</h3>
                <form id="userForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="user_id" id="userId">

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" name="nombre" id="nombre" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <input type="password" name="password" id="password"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-sm text-gray-500" id="passwordHelp">La contraseña es requerida para nuevos usuarios</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rol</label>
                        <select name="rol" id="rol" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="administrador">Administrador</option>
                            <option value="contador">Contador</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="cajero">Cajero</option>
                            <option value="cliente">Cliente</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="cerrarModal()"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function mostrarFormularioCrear() {
        document.getElementById('modalTitle').textContent = 'Crear Usuario';
        document.getElementById('formAction').value = 'create';
        document.getElementById('userForm').reset();
        document.getElementById('userId').value = '';
        document.getElementById('password').required = true;
        document.getElementById('passwordHelp').textContent = 'La contraseña es requerida para nuevos usuarios';
        document.getElementById('userModal').classList.remove('hidden');
    }

    function editarUsuario(usuario) {
        document.getElementById('modalTitle').textContent = 'Editar Usuario';
        document.getElementById('formAction').value = 'update';
        document.getElementById('userId').value = usuario.id;
        document.getElementById('nombre').value = usuario.nombre;
        document.getElementById('email').value = usuario.email;
        document.getElementById('rol').value = usuario.rol;
        document.getElementById('password').required = false;
        document.getElementById('passwordHelp').textContent = 'Dejar en blanco para mantener la contraseña actual';
        document.getElementById('userModal').classList.remove('hidden');
    }

    function cerrarModal() {
        document.getElementById('userModal').classList.add('hidden');
    }

    // Cerrar modal al hacer clic fuera de él
    document.getElementById('userModal').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModal();
        }
    });
    </script>
</body>
</html> 