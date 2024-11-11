<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Crear un archivo separado para manejar las peticiones AJAX
if (isset($_POST['action'])) {
    // Asegurarnos de que no haya salida previa
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    try {
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
                $response['data'] = [
                    'nombre' => trim($_POST['nombre']),
                    'email' => trim($_POST['email']),
                    'rol' => $_POST['rol']
                ];
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

                $result = eliminarUsuario($_POST['user_id'], $empresa_id);

                $response['success'] = true;
                $response['message'] = 'Usuario eliminado exitosamente';
                break;

            default:
                throw new Exception("Acción no válida");
        }
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
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

function getUserInfo($user_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT email, nombre, empresa_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getEmpresaInfo($empresa_id)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveEmpresa($empresa_id, $nombre_empresa, $nit, $regimen_fiscal, $direccion, $telefono, $correo_contacto, $prefijo_factura, $numero_inicial, $numero_final, $usuario_id)
{
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        if ($empresa_id) {
            // Actualizar empresa existente
            $stmt = $pdo->prepare("UPDATE empresas SET 
                nombre_empresa = ?, 
                nit = ?,
                regimen_fiscal = ?,
                direccion = ?, 
                telefono = ?, 
                correo_contacto = ?, 
                prefijo_factura = ?, 
                numero_inicial = ?, 
                numero_final = ?,
                updated_at = CURRENT_TIMESTAMP,
                usuario_id = ?
                WHERE id = ?");
            $result = $stmt->execute([
                $nombre_empresa, 
                $nit, 
                $regimen_fiscal, 
                $direccion, 
                $telefono, 
                $correo_contacto, 
                $prefijo_factura, 
                $numero_inicial, 
                $numero_final,
                $usuario_id,
                $empresa_id
            ]);
        } else {
            // Crear nueva empresa
            $stmt = $pdo->prepare("INSERT INTO empresas (
                nombre_empresa, 
                nit,
                regimen_fiscal,
                direccion, 
                telefono, 
                correo_contacto, 
                prefijo_factura, 
                numero_inicial, 
                numero_final,
                estado,
                usuario_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP)");
            
            $stmt->execute([
                $nombre_empresa, 
                $nit, 
                $regimen_fiscal, 
                $direccion, 
                $telefono, 
                $correo_contacto, 
                $prefijo_factura, 
                $numero_inicial, 
                $numero_final,
                $usuario_id
            ]);
            
            $empresa_id = $pdo->lastInsertId();
        }

        // Actualizar el empresa_id del usuario
        $stmt = $pdo->prepare("UPDATE users SET empresa_id = ? WHERE id = ?");
        $stmt->execute([$empresa_id, $usuario_id]);

        $pdo->commit();
        return $empresa_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error guardando empresa: " . $e->getMessage());
        throw new Exception("Error al guardar la empresa: " . $e->getMessage());
    }
}

function deleteEmpresa($empresa_id)
{
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
    return $stmt->execute([$empresa_id]);
}

function associateEmpresaToUser($user_id, $empresa_id)
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET empresa_id = ? WHERE id = ?");
    return $stmt->execute([$empresa_id, $user_id]);
}

function updateEmail($user_id, $new_email)
{
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
    return $stmt->execute([$new_email, $user_id]);
}

function updatePassword($user_id, $new_password)
{
    global $pdo;
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    return $stmt->execute([$hashed_password, $user_id]);
}

function updateNombre($user_id, $nuevo_nombre) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET nombre = ? WHERE id = ?");
    return $stmt->execute([$nuevo_nombre, $user_id]);
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
                fecha_creacion,
                ultimo_acceso
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)
        ");

        $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt->execute([
            $data['nombre'],
            $data['email'],
            $hashed_password,
            $data['rol'],
            $data['empresa_id'],
            $data['estado']
        ]);

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
        throw $e;
    }
}

function eliminarUsuario($user_id, $empresa_id) {
    global $pdo;
    try {
        $pdo->beginTransaction();

        // Verificar que el usuario pertenezca a la empresa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$user_id, $empresa_id]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception("Usuario no encontrado o no pertenece a tu empresa");
        }

        // Actualizamos el estado y la fecha de desactivación
        $stmt = $pdo->prepare("UPDATE users SET estado = 'inactivo', fecha_desactivacion = CURRENT_TIMESTAMP WHERE id = ? AND empresa_id = ?");
        $result = $stmt->execute([$user_id, $empresa_id]);

        if (!$result) {
            throw new Exception("No se pudo desactivar el usuario");
        }

        // Programar eliminación automática
        programarEliminacionUsuario();

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error desactivando usuario: " . $e->getMessage());
        throw new Exception("Error al desactivar el usuario: " . $e->getMessage());
    }
}

function programarEliminacionUsuario() {
    global $pdo;
    try {
        // Eliminar usuarios que han estado desactivados por más de 30 días
        $stmt = $pdo->prepare("
            DELETE u FROM users u
            WHERE u.estado = 'inactivo' 
            AND u.fecha_desactivacion IS NOT NULL
            AND u.fecha_desactivacion <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY)
            AND NOT EXISTS (
                -- Verificar si el usuario tiene ventas
                SELECT 1 FROM ventas v WHERE v.usuario_id = u.id
                UNION
                -- Verificar si el usuario tiene otras relaciones importantes
                SELECT 1 FROM login_history lh WHERE lh.user_id = u.id
            )
        ");
        
        $stmt->execute();
        
        // Registrar en el log cuántos usuarios fueron eliminados
        if ($stmt->rowCount() > 0) {
            error_log("Se eliminaron " . $stmt->rowCount() . " usuarios inactivos automáticamente");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error en la eliminación automática de usuarios: " . $e->getMessage());
        return false;
    }
}

// Agregar esta función para obtener información sobre usuarios próximos a ser eliminados
function getUsuariosProximosEliminar($empresa_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                nombre,
                email,
                fecha_desactivacion,
                DATE_ADD(fecha_desactivacion, INTERVAL 30 DAY) as fecha_eliminacion
            FROM users 
            WHERE empresa_id = ? 
            AND estado = 'inactivo' 
            AND fecha_desactivacion IS NOT NULL
            AND fecha_desactivacion > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY)
            ORDER BY fecha_desactivacion DESC
        ");
        
        $stmt->execute([$empresa_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo usuarios próximos a eliminar: " . $e->getMessage());
        return [];
    }
}

// Agregar al inicio del archivo, después de las validaciones de sesión
if (isset($_POST['action'])) {
    // Asegurarnos de que no haya salida previa
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // Validar CSRF token si está configurado
        if (isset($_SESSION['csrf_token']) && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
            throw new Exception('Token de seguridad inválido');
        }

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
                ], $user_info['empresa_id']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Usuario creado exitosamente'
                ]);
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

                echo json_encode([
                    'success' => true,
                    'message' => 'Usuario actualizado exitosamente'
                ]);
                break;

            case 'eliminar_usuario':
                if (empty($_POST['user_id'])) {
                    throw new Exception("ID de usuario no proporcionado");
                }

                if ($_POST['user_id'] == $user_id) {
                    throw new Exception("No puedes eliminar tu propio usuario");
                }

                $result = eliminarUsuario($_POST['user_id'], $user_info['empresa_id']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Usuario eliminado exitosamente'
                ]);
                break;

            default:
                throw new Exception("Acción no válida");
        }
        exit;
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Agregar función para verificar disponibilidad de email
function verificarEmailDisponible($email, $exclude_user_id = null) {
    global $pdo;
    $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
    $params = [$email];

    if ($exclude_user_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_user_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() == 0;
}

// Agregar función para registrar actividades
function registrarActividad($user_id, $tipo, $descripcion) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_actividades (
                user_id, 
                tipo_actividad, 
                descripcion, 
                fecha_hora, 
                ip_address
            ) VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $user_id,
            $tipo,
            $descripcion,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        error_log("Error registrando actividad: " . $e->getMessage());
    }
}

// Al inicio del archivo, después de las validaciones de sesión
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_email'])) {
            if (updateEmail($user_id, $_POST['email'])) {
                $_SESSION['email'] = $_POST['email'];
                $message = "Correo electrónico actualizado correctamente";
                $messageType = "success";
            }
        } elseif (isset($_POST['update_nombre'])) {
            if (empty($_POST['nombre'])) {
                throw new Exception("El nombre es requerido");
            }
            if (updateNombre($user_id, $_POST['nombre'])) {
                $message = "Nombre actualizado correctamente";
                $messageType = "success";
                // Actualizar la información del usuario
                $user_info = getUserInfo($user_id);
            }
        } elseif (isset($_POST['update_password'])) {
            if (updatePassword($user_id, $_POST['new_password'])) {
                $message = "Contraseña actualizada correctamente";
                $messageType = "success";
            }
        } elseif (isset($_POST['save_empresa'])) {
            $empresa_data = [
                'nombre_empresa' => trim($_POST['nombre_empresa']),
                'nit' => trim($_POST['nit']),
                'regimen_fiscal' => trim($_POST['regimen_fiscal']),
                'direccion' => trim($_POST['direccion']),
                'telefono' => trim($_POST['telefono']),
                'correo_contacto' => trim($_POST['correo_contacto']),
                'prefijo_factura' => trim($_POST['prefijo_factura']),
                'numero_inicial' => (int)$_POST['numero_inicial'],
                'numero_final' => (int)$_POST['numero_final']
            ];

            $empresa_id = $user_info['empresa_id'] ?? null;
            
            if (saveEmpresa(
                $empresa_id,
                $empresa_data['nombre_empresa'],
                $empresa_data['nit'],
                $empresa_data['regimen_fiscal'],
                $empresa_data['direccion'],
                $empresa_data['telefono'],
                $empresa_data['correo_contacto'],
                $empresa_data['prefijo_factura'],
                $empresa_data['numero_inicial'],
                $empresa_data['numero_final'],
                $user_id
            )) {
                $message = "Empresa guardada correctamente";
                $messageType = "success";
                
                // Actualizar la información del usuario y empresa
                $user_info = getUserInfo($user_id);
                $empresa_info = $user_info['empresa_id'] ? getEmpresaInfo($user_info['empresa_id']) : null;
            }
        } elseif (isset($_POST['delete_empresa'])) {
            if (deleteEmpresa($user_info['empresa_id'])) {
                associateEmpresaToUser($user_id, null);
                $message = "Empresa eliminada correctamente";
                $messageType = "success";
            }
        } elseif (isset($_POST['delete_account'])) {
            // Verificar que no sea el último administrador
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM users 
                WHERE empresa_id = ? AND rol = 'administrador' AND estado = 'activo'
            ");
            $stmt->execute([$user_info['empresa_id']]);
            $adminCount = $stmt->fetchColumn();

            if ($adminCount <= 1 && $user_info['rol'] === 'administrador') {
                throw new Exception("No puedes eliminar tu cuenta porque eres el único administrador");
            }

            // Desactivar la cuenta
            $stmt = $pdo->prepare("
                UPDATE users 
                SET estado = 'eliminado', 
                    fecha_desactivacion = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            if ($stmt->execute([$user_id])) {
                session_destroy();
                header("Location: ../../index.php?message=cuenta_eliminada");
                exit;
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
    }
}

// Obtener información necesaria para la vista
$user_info = getUserInfo($user_id);
$empresa_info = $user_info['empresa_id'] ? getEmpresaInfo($user_info['empresa_id']) : null;
$usuarios = getUsuarios($user_info['empresa_id']);

?><!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - VendEasy</title>
    <link rel="icon" type="image/png" href="favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <style>
        /* Estilos para la sección de usuarios */
        .users-section {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .users-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .users-table tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
        }

        /* Estilos para el modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-dialog {
            width: 100%;
            max-width: 500px;
            margin: 1.75rem auto;
        }

        .modal-content {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.16);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-footer {
            padding: 1rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.375rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            transition: border-color 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        .text-muted {
            color: #6c757d;
            font-size: 0.875em;
        }

        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal.show {
            animation: fadeIn 0.3s;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .users-header {
                flex-direction: column;
                gap: 10px;
            }

            .users-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Mejoras generales */
        .list1 {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            transition: transform 0.2s;
        }

        .list1:hover {
            transform: translateY(-2px);
        }

        .row {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .row h4 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.2em;
        }

        /* Estilos del modal mejorados */
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
            animation: modalSlideIn 0.3s ease;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            max-height: calc(100vh - 60px);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            padding: 1.25rem;
            border-bottom: 1px solid #dee2e6;
            border-radius: 12px 12px 0 0;
            z-index: 1;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .modal-footer {
            position: sticky;
            bottom: 0;
            background: #fff;
            padding: 1.25rem;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 12px 12px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            z-index: 1;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .text-muted {
            color: #6c757d;
            font-size: 0.875em;
        }

        /* Animación del modal */
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Mejorar estilos de formulario dentro del modal */
        .modal .form-group {
            margin-bottom: 1.5rem;
        }

        .modal .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: all 0.2s;
        }

        .modal .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* Mejorar botones del modal */
        .modal .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .modal .btn:hover {
            transform: translateY(-1px);
        }

        /* Estilo para campos inválidos */
        .modal .form-control.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,...");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .modal .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 0.25rem;
        }

        .modal .was-validated .form-control:invalid ~ .invalid-feedback {
            display: block;
        }

        /* Botones mejorados */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }

        /* Badges mejorados */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .badge-success {
            background: #2ecc71;
            color: white;
        }

        .badge-danger {
            background: #e74c3c;
            color: white;
        }

        /* Tabla mejorada */
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 1rem 0;
        }

        .table th {
            background: #f8f9fa;
            padding: 1rem;
            font-weight: 600;
            color: #2c3e50;
            text-align: left;
            border-bottom: 2px solid #eee;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        /* Animaciones */
        @keyframes slideIn {
            from {
                transform: translateY(-100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal.show .modal-content {
            animation: slideIn 0.3s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 1rem;
            }

            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Tooltips */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }

        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.5rem 1rem;
            background: rgba(0,0,0,0.8);
            color: white;
            border-radius: 4px;
            font-size: 0.875rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s;
        }

        [data-tooltip]:hover:before {
            opacity: 1;
            visibility: visible;
        }

        .danger-zone {
            border: 1px solid #dc3545;
            margin-top: 2rem;
        }

        .danger-zone .row {
            background-color: #dc3545;
            color: white;
        }

        .danger-content {
            padding: 1.5rem;
        }

        .danger-content h5 {
            color: #dc3545;
            margin-bottom: 1rem;
        }

        .text-danger {
            color: #dc3545;
        }

        .text-danger i {
            margin-right: 0.5rem;
        }

        .danger-zone {
            border: 2px solid #dc3545;
            margin-top: 2rem;
            background: #fff5f5;
        }

        .danger-zone .row {
            background-color: #dc3545;
            color: white;
            padding: 1rem;
        }

        .danger-zone .row h4 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .danger-zone .row h4:before {
            content: "⚠️";
        }

        .danger-content {
            padding: 2rem;
        }

        .danger-content h5 {
            color: #dc3545;
            font-size: 1.25rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .danger-details {
            background: #fff;
            border: 1px solid #ffcccc;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1rem 0;
        }

        .danger-details ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .danger-details li {
            margin-bottom: 0.5rem;
        }

        .danger-details li:last-child {
            margin-bottom: 0;
        }

        .text-danger {
            color: #dc3545;
        }

        .text-danger i {
            margin-right: 0.5rem;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }

        /* Grid Layout */
        .grid-layout {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .grid-span-2 {
            grid-column: span 2;
        }

        /* Ajustes responsivos */
        @media (max-width: 768px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }

            .grid-span-2 {
                grid-column: span 1;
            }
        }

        /* Ajustes para mantener consistencia visual */
        .list1 {
            margin-top: 0;
            height: fit-content;
        }

        .danger-zone {
            margin-top: 0;
        }

        /* Mejorar espaciado interno */
        .list1 .row {
            padding: 1rem 1.5rem;
        }

        .table-responsive {
            padding: 1.5rem;
        }

        /* Estilos para el formulario de empresa */
        .empresa-form {
            padding: 1.5rem;
        }

        .grid-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .grid-form .form-group {
            margin: 0;
        }

        .grid-form .grid-span-2 {
            grid-column: span 2;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
            align-items: center;
            margin-top: 1rem;
        }

        /* Mejorar apariencia de inputs */
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .grid-form {
                grid-template-columns: 1fr;
            }

            .grid-form .grid-span-2 {
                grid-column: span 1;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button {
                width: 100%;
            }
        }

        .content-preview {
            padding: 1.5rem;
        }

        .content-preview p {
            color: #666;
            margin-bottom: 1rem;
        }

        .content-preview ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .content-preview li {
            padding: 0.5rem 0;
            color: #666;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-preview li:before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
        }

        .swal2-danger-zone {
            border: 2px solid #dc3545;
        }

        .text-left {
            text-align: left;
        }

        .font-weight-bold {
            font-weight: bold;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .swal2-danger-zone {
            border: 2px solid #dc3545;
        }

        .swal2-danger-zone .swal2-title {
            color: #dc3545;
        }

        .swal2-danger-zone .swal2-html-container {
            text-align: left;
        }

        .swal2-danger-zone .danger-details {
            background: #fff;
            border: 1px solid #ffcccc;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .swal2-danger-zone ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .swal2-danger-zone li {
            margin-bottom: 0.5rem;
            color: #dc3545;
        }

        .swal2-danger-zone .swal2-input {
            border: 1px solid #ced4da;
            border-radius: 8px;
            padding: 0.75rem;
            margin: 1rem 0;
            font-size: 1rem;
        }

        .swal2-danger-zone .swal2-input:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .text-danger {
            color: #dc3545 !important;
        }

        .mt-3 {
            margin-top: 1rem !important;
        }

        .text-left {
            text-align: left !important;
        }

        .font-weight-bold {
            font-weight: bold !important;
        }
    </style>
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
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php" class="active">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="/ayuda.php">Ayuda</a>
                    <a href="/contacto.php">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Configuración de la Cuenta</h2>
            <div class="promo_card">
                <h1>Gestiona tu Cuenta y Empresa</h1>
                <span>Actualiza tu información personal y configura los datos de tu empresa.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?= strpos($message, 'correctamente') !== false ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Actualizar Correo Electrónico</h4>
                    </div>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="email">Correo Electrónico:</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_info['email']); ?>" required>
                        </div>
                        <button type="submit" name="update_email" class="btn btn-primary">Actualizar Correo</button>
                    </form>
                </div>

                <div class="list1">
                    <div class="row">
                        <h4>Actualizar Nombre</h4>
                    </div>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="nombre">Nombre:</label>
                            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($user_info['nombre']); ?>" required>
                        </div>
                        <button type="submit" name="update_nombre" class="btn btn-primary">Actualizar Nombre</button>
                    </form>
                </div>

                <div class="list1">
                    <div class="row">
                        <h4>Cambiar Contraseña</h4>
                    </div>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="new_password">Nueva Contraseña:</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmar Nueva Contraseña:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="update_password" class="btn btn-primary">Actualizar Contraseña</button>
                    </form>
                </div>

                <!-- Después del div de Cambiar Contraseña, reemplazar la sección de Información de la Empresa por esto: -->
                <div class="grid-layout">
                    <!-- Información de la Empresa - 2 columnas -->
                    <div class="list1 grid-span-2">
                        <div class="row">
                            <h4>Información de la Empresa</h4>
                        </div>
                        <form method="POST" action="" class="empresa-form">
                            <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa_info['id'] ?? ''); ?>">
                            <div class="grid-form">
                                <!-- Primera fila -->
                                <div class="form-group grid-span-2">
                                    <label for="nombre_empresa">Nombre de la Empresa:</label>
                                    <input type="text" id="nombre_empresa" name="nombre_empresa" 
                                           value="<?= htmlspecialchars($empresa_info['nombre_empresa'] ?? ''); ?>" required>
                                </div>

                                <!-- Segunda fila -->
                                <div class="form-group">
                                    <label for="nit">NIT:</label>
                                    <input type="text" id="nit" name="nit" 
                                           value="<?= htmlspecialchars($empresa_info['nit'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="regimen_fiscal">Régimen Fiscal:</label>
                                    <select id="regimen_fiscal" name="regimen_fiscal" required>
                                        <option value="1" <?= ($empresa_info['regimen_fiscal'] ?? '') == '1' ? 'selected' : '' ?>>Régimen Común</option>
                                        <option value="2" <?= ($empresa_info['regimen_fiscal'] ?? '') == '2' ? 'selected' : '' ?>>Simplificado</option>
                                        <option value="3" <?= ($empresa_info['regimen_fiscal'] ?? '') == '3' ? 'selected' : '' ?>>Especial</option>
                                        <option value="4" <?= ($empresa_info['regimen_fiscal'] ?? '') == '4' ? 'selected' : '' ?>>Contribuyentes del Impuesto al Consumo</option>
                                        <option value="5" <?= ($empresa_info['regimen_fiscal'] ?? '') == '5' ? 'selected' : '' ?>>Grandes Contribuyentes</option>
                                    </select>
                                </div>

                                <!-- Tercera fila -->
                                <div class="form-group grid-span-2">
                                    <label for="direccion">Dirección:</label>
                                    <input type="text" id="direccion" name="direccion" 
                                           value="<?= htmlspecialchars($empresa_info['direccion'] ?? ''); ?>" required>
                                </div>

                                <!-- Cuarta fila -->
                                <div class="form-group">
                                    <label for="telefono">Teléfono:</label>
                                    <input type="text" id="telefono" name="telefono" 
                                           value="<?= htmlspecialchars($empresa_info['telefono'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="correo_contacto">Correo de Contacto:</label>
                                    <input type="email" id="correo_contacto" name="correo_contacto" 
                                           value="<?= htmlspecialchars($empresa_info['correo_contacto'] ?? ''); ?>" required>
                                </div>

                                <!-- Quinta fila -->
                                <div class="form-group">
                                    <label for="prefijo_factura">Prefijo de Factura:</label>
                                    <input type="text" id="prefijo_factura" name="prefijo_factura" 
                                           value="<?= htmlspecialchars($empresa_info['prefijo_factura'] ?? ''); ?>" required>
                                </div>

                                <!-- Sexta fila -->
                                <div class="form-group">
                                    <label for="numero_inicial">Número Inicial:</label>
                                    <input type="number" id="numero_inicial" name="numero_inicial" 
                                           value="<?= htmlspecialchars($empresa_info['numero_inicial'] ?? ''); ?>" required min="1">
                                </div>
                                <div class="form-group">
                                    <label for="numero_final">Número Final:</label>
                                    <input type="number" id="numero_final" name="numero_final" 
                                           value="<?= htmlspecialchars($empresa_info['numero_final'] ?? ''); ?>" required min="1">
                                </div>

                                <!-- Séptima fila - Botones -->
                                <div class="form-actions grid-span-2">
                                    <button type="submit" name="save_empresa" class="btn btn-primary">
                                        <?= $empresa_info ? 'Actualizar Empresa' : 'Crear Empresa' ?>
                                    </button>
                                    <?php if ($empresa_info): ?>
                                        <button type="submit" name="delete_empresa" class="btn btn-danger" 
                                                onclick="return confirm('¿Estás seguro de que quieres eliminar esta empresa?');">
                                            Eliminar Empresa
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Continuar con el grid-layout existente para usuarios y zona de peligro -->
                <div class="grid-layout">
                    <!-- Enlace a Gestión de Usuarios -->
                    <div class="list1 grid-span-2">
                        <div class="row">
                            <h4>Gestión de Usuarios</h4>
                            <a href="usuarios/index.php" class="btn btn-primary">
                                <i class="fas fa-users"></i> Administrar Usuarios
                            </a>
                        </div>
                        <div class="content-preview">
                            <p>Gestiona los usuarios de tu empresa, sus roles y permisos desde un módulo dedicado.</p>
                            <ul>
                                <li>Crear nuevos usuarios</li>
                                <li>Modificar roles y permisos</li>
                                <li>Activar o desactivar cuentas</li>
                                <li>Ver historial de accesos</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Zona de Peligro - 2 columnas -->
                    <div class="list1 danger-zone grid-span-2">
                        <div class="row">
                            <h4>Zona de Peligro</h4>
                        </div>
                        <div class="danger-content">
                            <h5>Eliminar Cuenta</h5>
                            <p class="text-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                Esta acción es irreversible. Tu cuenta será desactivada inmediatamente y eliminada permanentemente después de 30 días.
                            </p>
                            <div class="danger-details">
                                <ul class="text-danger">
                                    <li>Se desactivará el acceso a tu cuenta inmediatamente</li>
                                    <li>Perderás acceso a todos los datos y configuraciones</li>
                                    <li>La eliminación será permanente después de 30 días</li>
                                    <li>No podrás usar el mismo correo electrónico para registrarte nuevamente</li>
                                </ul>
                            </div>
                            <form method="POST" action="" onsubmit="return confirmarEliminacionCuenta(event)">
                                <input type="hidden" name="delete_account" value="1">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-user-times"></i> Eliminar mi cuenta permanentemente
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    let usuarioActual = null;

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
            document.body.style.overflow = 'hidden';
            
            // Animar entrada
            const dialog = modal.querySelector('.modal-dialog');
            dialog.style.transform = 'translateY(-50px)';
            setTimeout(() => {
                dialog.style.transform = 'translateY(0)';
            }, 10);
        },

        hideModal(id) {
            const modal = document.getElementById(id);
            const dialog = modal.querySelector('.modal-dialog');
            
            // Animar salida
            dialog.style.transform = 'translateY(-50px)';
            setTimeout(() => {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }, 300);
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
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        }
    };

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

            await Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });

            UI.hideModal('modalUsuario');
            location.reload();

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar';
        }
    }

    function editarUsuario(usuario) {
        usuarioActual = usuario;
        document.querySelector('.modal-title').textContent = 'Editar Usuario';
        
        // Llenar el formulario con los datos del usuario
        document.getElementById('user_id').value = usuario.id;
        document.getElementById('nombre_usuario').value = usuario.nombre;
        document.getElementById('email_usuario').value = usuario.email;
        document.getElementById('email_usuario').readOnly = true;
        document.getElementById('password_usuario').required = false;
        document.getElementById('rol_usuario').value = usuario.rol;
        document.getElementById('estado_usuario').value = usuario.estado;
        
        // Mostrar mensaje sobre la contraseña
        document.querySelector('.password-hint').style.display = 'block';
        
        UI.showModal('modalUsuario');
    }

    function eliminarUsuario(userId) {
        if (!userId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'ID de usuario no válido'
            });
            return;
        }

        Swal.fire({
            title: '¿Estás seguro?',
            text: "El usuario será desactivado. Esta acción puede revertirse más tarde.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, desactivar',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const formData = new FormData();
                    formData.append('action', 'eliminar_usuario');
                    formData.append('user_id', userId);

                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    
                    if (!data.success) {
                        throw new Error(data.message || 'Error al desactivar usuario');
                    }
                    
                    return data;
                } catch (error) {
                    Swal.showValidationMessage(`Error: ${error.message}`);
                    throw error;
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Usuario desactivado',
                    text: 'El usuario ha sido desactivado correctamente',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        }).catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
        });
    }

    // Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Cerrar modal con el botón de cerrar
        document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', () => {
                UI.hideModal('modalUsuario');
            });
        });

        // Cerrar modal al hacer clic fuera
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('modalUsuario');
            if (e.target === modal) {
                UI.hideModal('modalUsuario');
            }
        });

        // Cerrar modal con la tecla Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                UI.hideModal('modalUsuario');
            }
        });
    });

    async function confirmarEliminacionCuenta(event) {
        event.preventDefault();

        try {
            const result = await Swal.fire({
                title: '¿Estás seguro?',
                html: `
                    <div class="text-left">
                        <p class="text-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Esta acción es irreversible.
                        </p>
                        <p>Tu cuenta será desactivada inmediatamente y eliminada permanentemente después de 30 días.</p>
                        <div class="danger-details mt-3">
                            <ul class="text-danger">
                                <li>Se desactivará el acceso a tu cuenta inmediatamente</li>
                                <li>Perderás acceso a todos los datos y configuraciones</li>
                                <li>La eliminación será permanente después de 30 días</li>
                                <li>No podrás usar el mismo correo electrónico para registrarte nuevamente</li>
                            </ul>
                        </div>
                        <p class="font-weight-bold mt-3">Escribe ELIMINAR para confirmar:</p>
                    </div>
                `,
                input: 'text',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar mi cuenta',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    if (value !== 'ELIMINAR') {
                        return 'Por favor escribe ELIMINAR para confirmar';
                    }
                },
                customClass: {
                    popup: 'swal2-danger-zone',
                    content: 'text-left',
                    input: 'form-control'
                }
            });

            if (result.isConfirmed) {
                const form = event.target;
                form.submit();
                return true;
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al procesar la solicitud'
            });
        }

        return false;
    }
    </script>
</body>

</html>
