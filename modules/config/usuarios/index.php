<?php
session_start();
require_once '../../../config/db.php';

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

                // Obtener empresa_id del usuario actual
                $stmt = $pdo->prepare("SELECT empresa_id FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $empresa_id = $stmt->fetchColumn();

                if (!$empresa_id) {
                    throw new Exception("No hay una empresa asociada al usuario administrador");
                }

                $result = crearUsuario([
                    'nombre' => trim($_POST['nombre']),
                    'email' => trim($_POST['email']),
                    'password' => $_POST['password'],
                    'rol' => $_POST['rol'],
                    'empresa_id' => $empresa_id,
                    'estado' => 'activo'
                ]);

                $response['success'] = true;
                $response['message'] = 'Usuario creado exitosamente';
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

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];
$user_info = getUserInfo($user_id);
$usuarios = getUsuarios($user_info['empresa_id']);

// Funciones necesarias
function getUserInfo($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT email, nombre, empresa_id, rol FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
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
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nombre,
                u.email,
                u.rol,
                u.estado,
                u.fecha_creacion,
                (SELECT login_time 
                 FROM login_history lh 
                 WHERE lh.user_id = u.id 
                 AND lh.status = 'success'
                 ORDER BY login_time DESC 
                 LIMIT 1) as ultimo_acceso
            FROM users u 
            WHERE u.empresa_id = ?
            ORDER BY u.fecha_creacion DESC
        ");
        
        $stmt->execute([$empresa_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error obteniendo usuarios: " . $e->getMessage());
        throw new Exception("Error al obtener la lista de usuarios");
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

        $stmt = $pdo->prepare("
            INSERT INTO users (
                nombre, 
                email, 
                password, 
                rol, 
                empresa_id, 
                estado, 
                fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);
        $result = $stmt->execute([
            $data['nombre'],
            $data['email'],
            $hashed_password,
            $data['rol'],
            $data['empresa_id'],
            $data['estado']
        ]);

        if (!$result) {
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
    <link rel="icon" type="image/png" href="favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../../css/welcome.css">
    <link rel="stylesheet" href="../../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <style>
        /* Estilos mejorados para la gestión de usuarios */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: #2c3e50;
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 0.5rem;
        }

        .stat-card .description {
            color: #666;
            font-size: 0.9rem;
        }

        .users-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .users-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
        }

        .users-table td {
            padding: 1rem;
            border-top: 1px solid #eee;
            vertical-align: middle;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8f9fa;
            color: #2c3e50;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-icon.edit {
            background: #e3f2fd;
            color: #1976d2;
        }

        .btn-icon.delete {
            background: #fde7e7;
            color: #d32f2f;
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        /* Mejoras para el modal */
        .modal-content {
            border-radius: 12px;
        }

        .modal-header {
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1050;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-dialog {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 30px auto;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
            opacity: 1;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-header .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            margin: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s;
        }

        .modal-header .close:hover {
            background-color: #f8f9fa;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.25rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Animación para el loading spinner */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-spinner {
            display: inline-block;
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
    </style>
</head>
<body>
<?php include '../../../includes/header.php'; ?>
    <div class="container">
        <?php include '../../../includes/sidebar.php'; ?>
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
            <h2>Gestión de Usuarios</h2>
            <div class="promo_card">
                <h1>Administración de Usuarios</h1>
                <span>Gestiona los usuarios de tu empresa y sus permisos de manera eficiente.</span>
            </div>

            <!-- Tarjetas de estadísticas -->
            <div class="stats-cards">
                <div class="stat-card">
                    <h3>Total Usuarios</h3>
                    <div class="value"><?= count($usuarios) ?></div>
                    <div class="description">Usuarios registrados en el sistema</div>
                </div>
                <div class="stat-card">
                    <h3>Usuarios Activos</h3>
                    <div class="value"><?= count(array_filter($usuarios, fn($u) => $u['estado'] === 'activo')) ?></div>
                    <div class="description">Usuarios con acceso al sistema</div>
                </div>
                <div class="stat-card">
                    <h3>Último Registro</h3>
                    <div class="value">
                        <?php 
                        $ultimo = array_reduce($usuarios, function($carry, $item) {
                            return (!$carry || strtotime($item['fecha_creacion']) > strtotime($carry['fecha_creacion'])) ? $item : $carry;
                        });
                        echo $ultimo ? date('d/m/Y', strtotime($ultimo['fecha_creacion'])) : 'N/A';
                        ?>
                    </div>
                    <div class="description">Fecha del último usuario registrado</div>
                </div>
            </div>

            <!-- Filtros y búsqueda -->
            <div class="filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchUsuarios" placeholder="Buscar usuarios...">
                </div>
                <div class="filter-group">
                    <select id="filterRol" class="form-control">
                        <option value="">Todos los roles</option>
                        <?php foreach (getRoles() as $key => $value): ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($value) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="filterEstado" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary" onclick="abrirModalUsuario()">
                    <i class="fas fa-user-plus"></i> Nuevo Usuario
                </button>
            </div>

            <!-- Tabla de usuarios -->
            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Último Acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="usuariosTableBody">
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-users-slash"></i>
                                        <p>No hay usuarios registrados</p>
                                        <button class="btn btn-primary" onclick="abrirModalUsuario()">
                                            <i class="fas fa-user-plus"></i> Agregar el primer usuario
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                                    <td><?= htmlspecialchars(getRoles()[$usuario['rol']] ?? $usuario['rol']) ?></td>
                                    <td>
                                        <span class="badge <?= $usuario['estado'] === 'activo' ? 'badge-success' : 'badge-danger' ?>">
                                            <?= ucfirst($usuario['estado']) ?>
                                        </span>
                                    </td>
                                    <td><?= $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca' ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon edit" onclick="editarUsuario(<?= htmlspecialchars(json_encode($usuario)) ?>)" 
                                                    title="Editar usuario">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon delete" onclick="eliminarUsuario(<?= $usuario['id'] ?>)"
                                                    title="Eliminar usuario">
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

    <!-- Modal para crear/editar usuario -->
    <div class="modal" id="modalUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gestionar Usuario</h5>
                    <button type="button" class="close" onclick="cerrarModal()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formUsuario" class="needs-validation" novalidate>
                        <input type="hidden" id="user_id" name="user_id">
                        <div class="form-group">
                            <label for="nombre_usuario">Nombre: *</label>
                            <input type="text" class="form-control" id="nombre_usuario" name="nombre" required 
                                   minlength="3" maxlength="100">
                            <div class="invalid-feedback">
                                El nombre es requerido y debe tener entre 3 y 100 caracteres
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email_usuario">Email: *</label>
                            <input type="email" class="form-control" id="email_usuario" name="email" required>
                            <div class="invalid-feedback">
                                Por favor ingrese un email válido
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password_usuario">Contraseña:</label>
                            <input type="password" class="form-control" id="password_usuario" name="password" 
                                   minlength="6">
                            <small class="form-text text-muted password-hint" style="display: none;">
                                Dejar en blanco para mantener la contraseña actual
                            </small>
                            <div class="invalid-feedback">
                                La contraseña debe tener al menos 6 caracteres
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="rol_usuario">Rol: *</label>
                            <select class="form-control" id="rol_usuario" name="rol" required>
                                <?php foreach (getRoles() as $key => $value): ?>
                                    <option value="<?= htmlspecialchars($key) ?>">
                                        <?= htmlspecialchars($value) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Por favor seleccione un rol
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="estado_usuario">Estado: *</label>
                            <select class="form-control" id="estado_usuario" name="estado" required>
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModal()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="guardarUsuario()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Mantener el JavaScript existente y agregar estas mejoras:
    
    // Función para filtrar usuarios
    function filtrarUsuarios() {
        const searchTerm = document.getElementById('searchUsuarios').value.toLowerCase();
        const rolFiltro = document.getElementById('filterRol').value;
        const estadoFiltro = document.getElementById('filterEstado').value;
        
        const rows = document.querySelectorAll('#usuariosTableBody tr');
        
        rows.forEach(row => {
            if (row.querySelector('td.empty-state')) return;
            
            const nombre = row.cells[0].textContent.toLowerCase();
            const email = row.cells[1].textContent.toLowerCase();
            const rol = row.cells[2].textContent.toLowerCase();
            const estado = row.cells[3].textContent.toLowerCase();
            
            const matchSearch = nombre.includes(searchTerm) || email.includes(searchTerm);
            const matchRol = !rolFiltro || rol.includes(rolFiltro.toLowerCase());
            const matchEstado = !estadoFiltro || estado.includes(estadoFiltro.toLowerCase());
            
            row.style.display = matchSearch && matchRol && matchEstado ? '' : 'none';
        });
    }

    // Event listeners para filtros
    document.getElementById('searchUsuarios').addEventListener('input', filtrarUsuarios);
    document.getElementById('filterRol').addEventListener('change', filtrarUsuarios);
    document.getElementById('filterEstado').addEventListener('change', filtrarUsuarios);

    // Mejorar la experiencia del modal
    function abrirModalUsuario() {
        const modal = document.getElementById('modalUsuario');
        modal.style.display = 'block';
        
        // Animar entrada
        setTimeout(() => {
            modal.querySelector('.modal-content').style.transform = 'translateY(0)';
            modal.querySelector('.modal-content').style.opacity = '1';
        }, 10);

        // Focus en el primer campo
        setTimeout(() => {
            modal.querySelector('input[name="nombre"]').focus();
        }, 300);
    }

    let usuarioActual = null;

    // Funciones de UI
    const UI = {
        showLoading(button) {
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="loading-spinner"></span> Procesando...';
            return () => {
                button.disabled = false;
                button.innerHTML = originalContent;
            };
        },

        showModal(id) {
            const modal = document.getElementById(id);
            modal.classList.add('show');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        },

        hideModal(id) {
            const modal = document.getElementById(id);
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        },

        showNotification(title, message, type = 'success') {
            return Swal.fire({
                title,
                text: message,
                icon: type,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
    };

    // Funciones principales
    function abrirModalUsuario() {
        usuarioActual = null;
        const form = document.getElementById('formUsuario');
        form.reset();
        
        document.querySelector('.modal-title').textContent = 'Crear Nuevo Usuario';
        document.getElementById('user_id').value = '';
        document.getElementById('email_usuario').readOnly = false;
        document.getElementById('password_usuario').required = true;
        document.querySelector('.password-hint').style.display = 'none';
        
        UI.showModal('modalUsuario');
    }

    function cerrarModal() {
        UI.hideModal('modalUsuario');
    }

    async function guardarUsuario() {
        const form = document.getElementById('formUsuario');
        const submitBtn = document.querySelector('.modal-footer .btn-primary');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        try {
            const restoreButton = UI.showLoading(submitBtn);
            
            const formData = new FormData(form);
            formData.append('action', usuarioActual ? 'actualizar_usuario' : 'crear_usuario');

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Error al guardar usuario');
            }

            await UI.showNotification('¡Éxito!', data.message);
            cerrarModal();
            location.reload();

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
        }
    }

    function editarUsuario(usuario) {
        usuarioActual = usuario;
        document.querySelector('.modal-title').textContent = 'Editar Usuario';
        
        document.getElementById('user_id').value = usuario.id;
        document.getElementById('nombre_usuario').value = usuario.nombre;
        document.getElementById('email_usuario').value = usuario.email;
        document.getElementById('email_usuario').readOnly = true;
        document.getElementById('password_usuario').required = false;
        document.getElementById('rol_usuario').value = usuario.rol;
        document.getElementById('estado_usuario').value = usuario.estado;
        
        document.querySelector('.password-hint').style.display = 'block';
        
        UI.showModal('modalUsuario');
    }

    function eliminarUsuario(userId) {
        if (!userId) return;

        Swal.fire({
            title: '¿Estás seguro?',
            text: "El usuario será desactivado. Esta acción puede revertirse más tarde.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, desactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading
                Swal.fire({
                    title: 'Procesando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Realizar la petición
                const formData = new FormData();
                formData.append('action', 'eliminar_usuario');
                formData.append('user_id', userId);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Error al desactivar usuario');
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
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
        });
    }

    // Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Cerrar modal con click fuera
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('modalUsuario');
            if (e.target === modal) {
                cerrarModal();
            }
        });

        // Cerrar modal con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });
    });
    </script>

    <!-- Agregar este script justo antes del cierre de </body> -->
    <script>
    // Mejoras de UI/UX
    document.addEventListener('DOMContentLoaded', function() {
        // Tooltips para los botones de acción
        const tooltips = document.querySelectorAll('[title]');
        tooltips.forEach(el => {
            el.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = this.getAttribute('title');
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
                tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth)/2 + 'px';
                
                this.addEventListener('mouseleave', () => tooltip.remove());
            });
        });

        // Animación de entrada para las tarjetas de estadísticas
        const cards = document.querySelectorAll('.stat-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Mejorar la experiencia de búsqueda
        let searchTimeout;
        const searchInput = document.getElementById('searchUsuarios');
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const spinner = this.parentElement.querySelector('.spinner');
            if (!spinner) {
                const newSpinner = document.createElement('div');
                newSpinner.className = 'spinner';
                this.parentElement.appendChild(newSpinner);
            }
            
            searchTimeout = setTimeout(() => {
                filtrarUsuarios();
                const spinner = this.parentElement.querySelector('.spinner');
                if (spinner) spinner.remove();
            }, 300);
        });

        // Mejorar la experiencia de filtrado
        const filters = document.querySelectorAll('#filterRol, #filterEstado');
        filters.forEach(filter => {
            filter.addEventListener('change', function() {
                const badge = document.createElement('span');
                badge.className = 'filter-badge';
                badge.textContent = `${this.options[this.selectedIndex].text}`;
                
                // Mostrar badge de filtro activo
                const container = document.querySelector('.active-filters');
                if (!container) {
                    const newContainer = document.createElement('div');
                    newContainer.className = 'active-filters';
                    this.parentElement.appendChild(newContainer);
                }
                
                filtrarUsuarios();
            });
        });

        // Animación para filas de la tabla
        const rows = document.querySelectorAll('.users-table tbody tr');
        rows.forEach((row, index) => {
            row.style.opacity = '0';
            setTimeout(() => {
                row.style.transition = 'opacity 0.3s ease';
                row.style.opacity = '1';
            }, index * 50);
        });
    });

    // Mejorar la función de filtrado con feedback visual
    function filtrarUsuarios() {
        const searchTerm = document.getElementById('searchUsuarios').value.toLowerCase();
        const rolFiltro = document.getElementById('filterRol').value;
        const estadoFiltro = document.getElementById('filterEstado').value;
        
        const rows = document.querySelectorAll('#usuariosTableBody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            if (row.querySelector('.empty-state')) return;
            
            const nombre = row.cells[0].textContent.toLowerCase();
            const email = row.cells[1].textContent.toLowerCase();
            const rol = row.cells[2].textContent.toLowerCase();
            const estado = row.cells[3].textContent.toLowerCase();
            
            const matchSearch = nombre.includes(searchTerm) || email.includes(searchTerm);
            const matchRol = !rolFiltro || rol.includes(rolFiltro.toLowerCase());
            const matchEstado = !estadoFiltro || estado.includes(estadoFiltro.toLowerCase());
            
            if (matchSearch && matchRol && matchEstado) {
                row.style.display = '';
                row.style.animation = 'fadeIn 0.3s ease forwards';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Mostrar mensaje cuando no hay resultados
        const emptyMessage = document.querySelector('.no-results');
        if (visibleCount === 0 && !document.querySelector('.empty-state')) {
            if (!emptyMessage) {
                const message = document.createElement('tr');
                message.className = 'no-results';
                message.innerHTML = `
                    <td colspan="6" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <p>No se encontraron usuarios con los filtros seleccionados</p>
                            <button class="btn btn-secondary" onclick="limpiarFiltros()">
                                <i class="fas fa-times"></i> Limpiar filtros
                            </button>
                        </div>
                    </td>
                `;
                document.querySelector('#usuariosTableBody').appendChild(message);
            }
        } else if (emptyMessage) {
            emptyMessage.remove();
        }

        // Actualizar contador de resultados
        actualizarContador(visibleCount);
    }

    // Función para limpiar filtros
    function limpiarFiltros() {
        document.getElementById('searchUsuarios').value = '';
        document.getElementById('filterRol').value = '';
        document.getElementById('filterEstado').value = '';
        filtrarUsuarios();
    }

    // Función para actualizar el contador de resultados
    function actualizarContador(count) {
        const container = document.querySelector('.results-count');
        if (!container) {
            const newContainer = document.createElement('div');
            newContainer.className = 'results-count';
            document.querySelector('.filters').appendChild(newContainer);
        }
        document.querySelector('.results-count').textContent = 
            `Mostrando ${count} ${count === 1 ? 'usuario' : 'usuarios'}`;
    }
    </script>

    <style>
    /* Agregar estos estilos */
    .custom-tooltip {
        position: absolute;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 1000;
        pointer-events: none;
    }

    .spinner {
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3498db;
        border-radius: 50%;
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        animation: spin 1s linear infinite;
    }

    .filter-badge {
        background: #e3f2fd;
        color: #1976d2;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        margin-left: 8px;
    }

    .active-filters {
        display: flex;
        gap: 8px;
        margin-top: 8px;
    }

    .results-count {
        color: #666;
        font-size: 14px;
        margin-left: auto;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .empty-state {
        text-align: center;
        padding: 2rem;
    }

    .empty-state i {
        font-size: 2rem;
        color: #ccc;
        margin-bottom: 1rem;
    }

    /* Mejorar la apariencia de los inputs */
    .search-box input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    .form-control:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    /* Animaciones para las acciones */
    .btn-icon:active {
        transform: scale(0.95);
    }

    .stat-card {
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    </style>
</body>
</html> 