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
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content ml-64">
            <!-- Encabezado de la página -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Configuración de la Cuenta</h1>
                <p class="text-gray-600">Gestiona tu información personal y configura los datos de tu empresa</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="mb-4 p-4 rounded-lg <?= strpos($message, 'correctamente') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Grid de configuraciones -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Actualizar Correo -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-envelope mr-2"></i>Correo Electrónico
                        </h2>
                    </div>
                    <form method="POST" action="" id="emailForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nuevo Correo Electrónico
                            </label>
                            <input type="email" name="email" 
                                   value="<?= htmlspecialchars($user_info['email']); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" 
                                   required>
                        </div>
                        <div id="emailOtpGroup" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Código de Verificación
                            </label>
                            <input type="text" name="email_otp" maxlength="6" pattern="\d{6}"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <p class="mt-1 text-sm text-gray-500">
                                Ingresa el código de 6 dígitos enviado a tu correo
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" id="requestEmailOtp"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                Solicitar código
                            </button>
                            <button type="submit" name="update_email" id="updateEmailBtn"
                                    class="hidden px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Actualizar Correo
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Cambiar Contraseña -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-lock mr-2"></i>Contraseña
                        </h2>
                    </div>
                    <form method="POST" action="" id="passwordForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nueva Contraseña
                            </label>
                            <input type="password" name="new_password" required
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Confirmar Nueva Contraseña
                            </label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div id="passwordOtpGroup" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Código de Verificación
                            </label>
                            <input type="text" name="password_otp" maxlength="6" pattern="\d{6}"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <p class="mt-1 text-sm text-gray-500">
                                Ingresa el código de 6 dígitos enviado a tu correo
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" id="requestPasswordOtp"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                Solicitar código
                            </button>
                            <button type="submit" name="update_password" id="updatePasswordBtn"
                                    class="hidden px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Actualizar Contraseña
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Gestión de Empresas -->
                <div class="bg-white rounded-lg shadow-sm p-6 md:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-building mr-2"></i>Empresas
                        </h2>
                        <a href="empresas/index.php" 
                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Administrar Empresas
                        </a>
                    </div>
                    <div class="prose">
                        <p class="text-gray-600">Gestiona la información de tus empresas desde un módulo dedicado.</p>
                        <ul class="list-disc list-inside text-gray-600">
                            <li>Crear nuevas empresas</li>
                            <li>Modificar información empresarial</li>
                            <li>Gestionar logos y documentación</li>
                            <li>Configurar datos de facturación</li>
                        </ul>
                    </div>
                </div>

                <!-- Gestión de Usuarios -->
                <div class="bg-white rounded-lg shadow-sm p-6 md:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-users mr-2"></i>Usuarios
                        </h2>
                        <a href="usuarios/index.php" 
                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Administrar Usuarios
                        </a>
                    </div>
                    <div class="prose">
                        <p class="text-gray-600">Gestiona los usuarios de tu empresa, sus roles y permisos.</p>
                        <ul class="list-disc list-inside text-gray-600">
                            <li>Crear nuevos usuarios</li>
                            <li>Modificar roles y permisos</li>
                            <li>Activar o desactivar cuentas</li>
                            <li>Ver historial de accesos</li>
                        </ul>
                    </div>
                </div>

                <!-- Zona de Peligro -->
                <div class="bg-white rounded-lg shadow-sm p-6 md:col-span-2 border-2 border-red-200">
                    <div class="bg-red-50 -m-6 mb-6 p-4 rounded-t-lg border-b border-red-200">
                        <h2 class="text-xl font-semibold text-red-700">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Zona de Peligro
                        </h2>
                    </div>
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-red-600">Eliminar Cuenta</h3>
                        <p class="text-gray-600">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            Esta acción es irreversible. Tu cuenta será desactivada inmediatamente y eliminada permanentemente después de 30 días.
                        </p>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <ul class="list-disc list-inside text-red-600 space-y-2">
                                <li>Se desactivará el acceso a tu cuenta inmediatamente</li>
                                <li>Perderás acceso a todos los datos y configuraciones</li>
                                <li>La eliminación será permanente después de 30 días</li>
                                <li>No podrás usar el mismo correo electrónico para registrarte nuevamente</li>
                            </ul>
                        </div>
                        <div class="mt-4">
                            <button type="button" 
                                    onclick="confirmarEliminacionCuenta(event)"
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                <i class="fas fa-user-times mr-2"></i>Eliminar mi cuenta permanentemente
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Objeto UI para funciones de interfaz
        const UI = {
            showLoading(button) {
                const originalContent = button.innerHTML;
                button.disabled = true;
                button.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Procesando...
                `;
                return function() {
                    button.disabled = false;
                    button.innerHTML = originalContent;
                };
            },

            showError(message) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message
                });
            },

            showSuccess(message) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: message,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        };

        // Función para manejar la solicitud de OTP
        async function handleOTPRequest(type) {
            try {
                const button = document.getElementById(`request${type}Otp`);
                if (!button) {
                    throw new Error(`Botón de OTP ${type} no encontrado`);
                }

                const restoreButton = UI.showLoading(button);

                const formData = new FormData();
                formData.append('action', `request_${type.toLowerCase()}_otp`);

                const response = await fetch('ajax_handlers.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Error en la solicitud al servidor');
                }

                let data;
                try {
                    data = await response.json();
                } catch (error) {
                    console.error('Error parsing JSON:', error);
                    throw new Error('Error al procesar la respuesta del servidor');
                }

                if (data.success) {
                    UI.showSuccess('Código enviado! Revisa tu correo electrónico');
                    
                    // Mostrar campo OTP y botón de actualización
                    const otpGroup = document.getElementById(`${type.toLowerCase()}OtpGroup`);
                    const updateBtn = document.getElementById(`update${type}Btn`);
                    
                    if (otpGroup) otpGroup.classList.remove('hidden');
                    if (updateBtn) updateBtn.classList.remove('hidden');
                    button.classList.add('hidden');
                } else {
                    throw new Error(data.message || 'Error al enviar el código');
                }
            } catch (error) {
                console.error('Error:', error);
                UI.showError(error.message || 'Ocurrió un error al procesar la solicitud');
            } finally {
                // Restaurar el botón a su estado original si existe la función
                if (typeof restoreButton === 'function') {
                    restoreButton();
                }
            }
        }

        // Configurar event listeners
        function setupEventListeners() {
            // Botones de solicitud OTP
            const emailOtpBtn = document.getElementById('requestEmailOtp');
            const passwordOtpBtn = document.getElementById('requestPasswordOtp');

            if (emailOtpBtn) {
                emailOtpBtn.addEventListener('click', () => handleOTPRequest('Email'));
            }

            if (passwordOtpBtn) {
                passwordOtpBtn.addEventListener('click', () => handleOTPRequest('Password'));
            }

            // Formulario de email
            const emailForm = document.getElementById('emailForm');
            if (emailForm) {
                emailForm.addEventListener('submit', function(e) {
                    const otpInput = this.querySelector('[name="email_otp"]');
                    if (otpInput && !otpInput.value) {
                        e.preventDefault();
                        UI.showError('Por favor ingresa el código de verificación');
                    }
                });
            }

            // Formulario de contraseña
            const passwordForm = document.getElementById('passwordForm');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const password = this.querySelector('[name="new_password"]')?.value;
                    const confirm = this.querySelector('[name="confirm_password"]')?.value;
                    const otpInput = this.querySelector('[name="password_otp"]');

                    if (password !== confirm) {
                        e.preventDefault();
                        UI.showError('Las contraseñas no coinciden');
                        return;
                    }

                    if (otpInput && !otpInput.value) {
                        e.preventDefault();
                        UI.showError('Por favor ingresa el código de verificación');
                    }
                });
            }
        }

        // Inicializar los event listeners
        setupEventListeners();
    });

    // Actualizar la función confirmarEliminacionCuenta en index.php
    async function confirmarEliminacionCuenta(event) {
        event.preventDefault();
        
        try {
            const result = await Swal.fire({
                title: '¿Estás seguro?',
                html: `
                    <div class="text-left">
                        <p class="text-red-600">
                            <i class="fas fa-exclamation-triangle"></i>
                            Esta acción es irreversible.
                        </p>
                        <p>Tu cuenta será desactivada inmediatamente y eliminada permanentemente después de 30 días.</p>
                        <div class="mt-3">
                            <p class="font-bold">Escribe ELIMINAR para confirmar:</p>
                        </div>
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
                }
            });

            if (result.isConfirmed) {
                // Mostrar loading
                Swal.fire({
                    title: 'Procesando...',
                    text: 'Eliminando cuenta...',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Enviar solicitud AJAX
                const formData = new FormData();
                formData.append('action', 'delete_account');

                const response = await fetch('ajax_handlers.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: '¡Cuenta eliminada!',
                        text: 'Tu cuenta ha sido desactivada correctamente.',
                        confirmButtonText: 'Entendido'
                    });
                    
                    // Redirigir al logout
                    window.location.href = '../../logout.php';
                } else {
                    throw new Error(data.message || 'Error al eliminar la cuenta');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Ocurrió un error al eliminar la cuenta'
            });
        }

        return false;
    }
    </script>
</body>
</html>
