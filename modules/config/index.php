<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_info = getUserInfo($user_id);
$empresa_info = $user_info['empresa_id'] ? getEmpresaInfo($user_info['empresa_id']) : null;
$usuarios = getUsuarios($user_info['empresa_id']);

// Funciones OTP
function generateOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function saveOTP($user_id, $otp, $type) {
    global $pdo;
    try {
        // Eliminar OTPs anteriores del mismo tipo para este usuario
        $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Guardar nuevo OTP
        $stmt = $pdo->prepare("INSERT INTO otp_codes (user_id, code, created_at, expires_at) 
                              VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        return $stmt->execute([$user_id, $otp]);
    } catch (Exception $e) {
        error_log("Error guardando OTP: " . $e->getMessage());
        return false;
    }
}

function verifyOTP($user_id, $otp) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM otp_codes 
                              WHERE user_id = ? 
                              AND code = ? 
                              AND expires_at > NOW() 
                              AND used = 0");
        $stmt->execute([$user_id, $otp]);
        
        if ($stmt->rowCount() > 0) {
            // Marcar OTP como usado
            $stmt = $pdo->prepare("UPDATE otp_codes SET used = 1 WHERE user_id = ? AND code = ?");
            $stmt->execute([$user_id, $otp]);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error verificando OTP: " . $e->getMessage());
        return false;
    }
}

function sendOTPEmail($email, $otp, $type) {
    $subject = "Código de verificación - VendEasy";
    $message = "Tu código de verificación para " . 
               ($type == 'email' ? "cambiar tu correo" : "cambiar tu contraseña") . 
               " es: $otp\n\nEste código expirará en 10 minutos.";
    
    $headers = "From: noreply@vendeasy.com\r\n";
    $headers .= "Reply-To: noreply@vendeasy.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($email, $subject, $message, $headers);
}

// Agregar después de las funciones OTP existentes

function getUserInfo($user_id) {
    global $pdo;
    try {
        // Obtener información básica del usuario
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.nombre,
                   GROUP_CONCAT(DISTINCT ue.empresa_id) as empresas_ids
            FROM users u
            LEFT JOIN user_empresas ue ON u.id = ue.user_id
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Obtener todas las empresas asociadas al usuario
            $stmt = $pdo->prepare("
                SELECT e.*, ue.rol, ue.es_principal
                FROM empresas e
                JOIN user_empresas ue ON e.id = ue.empresa_id
                WHERE ue.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $user['empresas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $user;
    } catch (Exception $e) {
        error_log("Error obteniendo información del usuario: " . $e->getMessage());
        return false;
    }
}

function getEmpresaInfo($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT e.*
            FROM empresas e
            JOIN user_empresas ue ON e.id = ue.empresa_id
            WHERE ue.user_id = ? AND ue.es_principal = 1
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo información de la empresa: " . $e->getMessage());
        return false;
    }
}

function updateEmail($user_id, $new_email) {
    global $pdo;
    try {
        // Verificar si el email ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Este correo electrónico ya está registrado");
        }

        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        return $stmt->execute([$new_email, $user_id]);
    } catch (Exception $e) {
        error_log("Error actualizando email: " . $e->getMessage());
        throw $e;
    }
}

function updatePassword($user_id, $new_password) {
    global $pdo;
    try {
        // Validar longitud mínima de la contraseña
        if (strlen($new_password) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres");
        }

        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashed_password, $user_id]);
    } catch (Exception $e) {
        error_log("Error actualizando contraseña: " . $e->getMessage());
        throw $e;
    }
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
        return [];
    }
}

// Agregar después de las funciones existentes

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

function getRoles() {
    return [
        'administrador' => 'Administrador',
        'contador' => 'Contador', 
        'supervisor' => 'Supervisor',
        'cajero' => 'Cajero',
        'cliente' => 'Cliente'
    ];
}

// Agregar esta función después de las funciones existentes
function saveEmpresa($user_id, $empresa_id, $data, $logo = null) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Procesar el logo si se ha subido uno nuevo
        $logo_path = null;
        if ($logo && $logo['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $logo['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Formato de imagen no válido. Use: ' . implode(', ', $allowed));
            }
            
            // Usar la ruta absoluta del servidor
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/logos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generar nombre único para el archivo
            $new_filename = uniqid('logo_') . '.' . $ext;
            $logo_path = 'uploads/logos/' . $new_filename;
            
            // Ruta completa para mover el archivo
            $full_path = $upload_dir . $new_filename;
            
            // Mover el archivo
            if (!move_uploaded_file($logo['tmp_name'], $full_path)) {
                throw new Exception('Error al subir el logo');
            }
            
            // Eliminar logo anterior si existe
            if ($empresa_id) {
                $stmt = $pdo->prepare("SELECT logo FROM empresas WHERE id = ?");
                $stmt->execute([$empresa_id]);
                $old_logo = $stmt->fetchColumn();
                
                if ($old_logo && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $old_logo)) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $old_logo);
                }
            }
        }
        
        if ($empresa_id) {
            // Actualizar empresa existente
            $sql = "UPDATE empresas SET 
                nombre_empresa = ?, 
                nit = ?,
                regimen_fiscal = ?,
                direccion = ?, 
                telefono = ?, 
                correo_contacto = ?, 
                prefijo_factura = ?, 
                numero_inicial = ?, 
                numero_final = ?,
                updated_at = CURRENT_TIMESTAMP";
            
            $params = [
                $data['nombre_empresa'], 
                $data['nit'], 
                $data['regimen_fiscal'], 
                $data['direccion'], 
                $data['telefono'], 
                $data['correo_contacto'], 
                $data['prefijo_factura'], 
                $data['numero_inicial'], 
                $data['numero_final']
            ];
            
            if ($logo_path) {
                $sql .= ", logo = ?";
                $params[] = $logo_path;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $empresa_id;
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
        } else {
            // Crear nueva empresa
            $sql = "INSERT INTO empresas (
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
                created_at,
                updated_at";
            
            $values = "?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP";
            $params = [
                $data['nombre_empresa'], 
                $data['nit'], 
                $data['regimen_fiscal'], 
                $data['direccion'], 
                $data['telefono'], 
                $data['correo_contacto'], 
                $data['prefijo_factura'], 
                $data['numero_inicial'], 
                $data['numero_final']
            ];
            
            if ($logo_path) {
                $sql .= ", logo";
                $values .= ", ?";
                $params[] = $logo_path;
            }
            
            $sql .= ") VALUES (" . $values . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $empresa_id = $pdo->lastInsertId();
        }

        // Si es una nueva empresa, crear la relación usuario-empresa
        if (!$empresa_id) {
            $stmt = $pdo->prepare("
                INSERT INTO user_empresas (user_id, empresa_id, rol, es_principal) 
                SELECT ?, ?, 'administrador', 
                    CASE WHEN NOT EXISTS (
                        SELECT 1 FROM user_empresas WHERE user_id = ?
                    ) THEN 1 ELSE 0 END
            ");
            $stmt->execute([$user_id, $pdo->lastInsertId(), $user_id]);
        }

        $pdo->commit();
        return $empresa_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        // Si hubo error y se subió un logo, eliminarlo
        if (isset($logo_path) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $logo_path)) {
            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $logo_path);
        }
        error_log("Error guardando empresa: " . $e->getMessage());
        throw new Exception("Error al guardar la empresa: " . $e->getMessage());
    }
}

// Agregar función para obtener todas las empresas del usuario
function getUserEmpresas($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, ue.rol, ue.es_principal
            FROM empresas e
            JOIN user_empresas ue ON e.id = ue.empresa_id
            WHERE ue.user_id = ?
            ORDER BY ue.es_principal DESC, e.nombre_empresa ASC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo empresas del usuario: " . $e->getMessage());
        return [];
    }
}

// Agregar función para establecer empresa principal
function setEmpresaPrincipal($user_id, $empresa_id) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Quitar marca de principal de todas las empresas del usuario
        $stmt = $pdo->prepare("
            UPDATE user_empresas 
            SET es_principal = 0 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        // Establecer la nueva empresa principal
        $stmt = $pdo->prepare("
            UPDATE user_empresas 
            SET es_principal = 1 
            WHERE user_id = ? AND empresa_id = ?
        ");
        $stmt->execute([$user_id, $empresa_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error estableciendo empresa principal: " . $e->getMessage());
        return false;
    }
}

// Modificar la sección de manejo POST para incluir la gestión de múltiples empresas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_empresa'])) {
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

            $empresa_id = $_POST['empresa_id'] ?? null;
            $logo = isset($_FILES['logo']) ? $_FILES['logo'] : null;
            
            if (saveEmpresa($user_id, $empresa_id, $empresa_data, $logo)) {
                $message = "Empresa guardada correctamente";
                $messageType = "success";
            }
        } elseif (isset($_POST['set_empresa_principal'])) {
            $empresa_id = (int)$_POST['empresa_id'];
            if (setEmpresaPrincipal($user_id, $empresa_id)) {
                $message = "Empresa principal actualizada correctamente";
                $messageType = "success";
            }
        }
        
        // Actualizar información del usuario y empresas
        $user_info = getUserInfo($user_id);
        $empresa_info = getEmpresaInfo($user_id);
        $empresas_usuario = getUserEmpresas($user_id);
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
    }
}

// Manejar solicitudes POST normales

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_email'])) {
            if (!verifyOTP($user_id, $_POST['email_otp'])) {
                throw new Exception("Código de verificación inválido o expirado");
            }
            if (updateEmail($user_id, $_POST['email'])) {
                $_SESSION['email'] = $_POST['email'];
                $message = "Correo electrónico actualizado correctamente";
                $messageType = "success";
            }
        } 
        elseif (isset($_POST['update_password'])) {
            if (!verifyOTP($user_id, $_POST['password_otp'])) {
                throw new Exception("Código de verificación inválido o expirado");
            }
            if (updatePassword($user_id, $_POST['new_password'])) {
                $message = "Contraseña actualizada correctamente";
                $messageType = "success";
            }
        }
        // ... resto del código de manejo POST existente ...
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = "error";
    }
}

// Continuar con el resto del archivo (HTML, estilos, scripts, etc.)
?>
<!DOCTYPE html>
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

        /* Estilos para la previsualización del logo */
        .current-logo {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f8f9fa;
            max-width: 300px;
        }

        .logo-image {
            width: 200px;
            height: 200px;
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: white;
            padding: 10px;
        }

        .current-logo p {
            margin-top: 10px;
            margin-bottom: 0;
            color: #6c757d;
            font-size: 0.9em;
        }

        /* Estilo para el input de archivo */
        input[type="file"].form-control {
            padding: 8px;
            line-height: 1.5;
            background-color: #fff;
        }

        /* Previsualización de imagen antes de subir */
        #logo-preview {
            width: 200px;
            height: 200px;
            margin-top: 15px;
            display: none;
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: white;
            padding: 10px;
        }

        /* Añadir estos estilos */
        .header-icons {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .company-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }

        .company-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .default-logo {
            background: #e9ecef;
            color: #6c757d;
            font-size: 1.2rem;
        }

        .account {
            display: flex;
            align-items: center;
        }

        .account h4 {
            margin: 0;
            font-size: 0.9rem;
            color: #333;
        }

        /* Efecto hover para el logo */
        .company-logo:hover {
            transform: scale(1.05);
            transition: transform 0.2s ease;
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }

        /* Estilos para el formulario de empresa */
        .empresa-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .current-logo {
            margin-bottom: 1rem;
        }

        .logo-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: white;
            padding: 10px;
        }

        .logo-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .logo-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .logo-label:hover {
            background: #dee2e6;
        }

        .form-grid {
            display: grid;
            gap: 2rem;
            padding: 1.5rem;
        }

        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }

        .form-section h5 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #495057;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        /* Estilos para inputs de archivo */
        input[type="file"] {
            display: none;
        }

        /* Animaciones */
        .form-section {
            transition: transform 0.2s;
        }

        .form-section:hover {
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button {
                width: 100%;
            }
        }
    </style>
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
                    <form method="POST" action="" id="emailForm">
                        <div class="form-group">
                            <label for="email">Nuevo Correo Electrónico:</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_info['email']); ?>" required>
                        </div>
                        <div class="form-group" id="emailOtpGroup" style="display: none;">
                            <label for="email_otp">Código de Verificación:</label>
                            <input type="text" id="email_otp" name="email_otp" maxlength="6" pattern="\d{6}" required>
                            <small class="text-muted">Ingresa el código de 6 dígitos enviado a tu correo actual</small>
                        </div>
                        <button type="button" id="requestEmailOtp" class="btn btn-secondary">Solicitar código</button>
                        <button type="submit" name="update_email" id="updateEmailBtn" class="btn btn-primary" style="display: none;">
                            Actualizar Correo
                        </button>
                    </form>
                </div>

                <div class="list1">
                    <div class="row">
                        <h4>Cambiar Contraseña</h4>
                    </div>
                    <form method="POST" action="" id="passwordForm">
                        <div class="form-group">
                            <label for="new_password">Nueva Contraseña:</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmar Nueva Contraseña:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="form-group" id="passwordOtpGroup" style="display: none;">
                            <label for="password_otp">Código de Verificación:</label>
                            <input type="text" id="password_otp" name="password_otp" maxlength="6" pattern="\d{6}" required>
                            <small class="text-muted">Ingresa el código de 6 dígitos enviado a tu correo</small>
                        </div>
                        <button type="button" id="requestPasswordOtp" class="btn btn-secondary">Solicitar código</button>
                        <button type="submit" name="update_password" id="updatePasswordBtn" class="btn btn-primary" style="display: none;">
                            Actualizar Contraseña
                        </button>
                    </form>
                </div>

                <!-- Reemplazar la sección del formulario de empresa por esto -->
                <div class="list1">
                    <div class="row">
                        <h4>Gestión de Empresas</h4>
                        <a href="empresas/index.php" class="btn btn-primary">
                            <i class="fas fa-building"></i> Administrar Empresas
                        </a>
                    </div>
                    <div class="content-preview">
                        <p>Gestiona la información de tus empresas desde un módulo dedicado.</p>
                        <ul>
                            <li>Crear nuevas empresas</li>
                            <li>Modificar información empresarial</li>
                            <li>Gestionar logos y documentación</li>
                            <li>Configurar datos de facturación</li>
                        </ul>
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

    // Previsualización de imagen antes de subir
    document.getElementById('logo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Verificar el tamaño del archivo (2MB máximo)
            if (file.size > 2 * 1024 * 1024) {
                alert('El archivo es demasiado grande. El tamaño máximo es 2MB.');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                let preview = document.getElementById('logo-preview');
                if (!preview) {
                    preview = document.createElement('img');
                    preview.id = 'logo-preview';
                    document.querySelector('.form-group').appendChild(preview);
                }
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Funciones auxiliares
        async function requestOTP(type) {
            try {
                const formData = new FormData();
                formData.append(`request_${type}_otp`, '1');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Error en la solicitud');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Código enviado!',
                        text: 'Revisa tu correo electrónico'
                    });
                    
                    // Mostrar campo OTP y botón de actualización
                    document.getElementById(`${type}OtpGroup`).style.display = 'block';
                    document.getElementById(`update${type.charAt(0).toUpperCase() + type.slice(1)}Btn`).style.display = 'block';
                    document.getElementById(`request${type.charAt(0).toUpperCase() + type.slice(1)}Otp`).style.display = 'none';
                } else {
                    throw new Error(data.message || 'Error enviando el código');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al procesar la solicitud'
                });
            }
        }

        // Event Listeners
        document.getElementById('requestEmailOtp').addEventListener('click', () => requestOTP('email'));
        document.getElementById('requestPasswordOtp').addEventListener('click', () => requestOTP('password'));

        // Validación de contraseñas
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Las contraseñas no coinciden'
                });
            }
        });
    });

    function confirmarEliminarEmpresa() {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.querySelector('form').submit();
            }
        });
    }
    </script>
</body>

</html>
