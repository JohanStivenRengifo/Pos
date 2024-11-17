<?php
// Activar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log para verificar que el archivo se está ejecutando
error_log("Iniciando ajax_handlers.php");

session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

// Log para verificar la solicitud
error_log("REQUEST: " . print_r($_POST, true));
error_log("SESSION: " . print_r($_SESSION, true));

// Función para enviar respuesta JSON
function sendJsonResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    error_log("RESPONSE: " . print_r($response, true));
    echo json_encode($response);
    exit();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    error_log("Usuario no autorizado");
    sendJsonResponse(false, 'No autorizado');
}

// Verificar si se recibió una acción
if (!isset($_POST['action'])) {
    error_log("No se especificó acción");
    sendJsonResponse(false, 'Acción no especificada');
}

$user_id = $_SESSION['user_id'];
error_log("User ID: " . $user_id);

// Verificar conexión a la base de datos
try {
    $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    error_log("Conexión a BD exitosa");
} catch (Exception $e) {
    error_log("Error de conexión a BD: " . $e->getMessage());
    sendJsonResponse(false, 'Error de conexión a la base de datos');
}

// Verificar y crear la tabla otp_codes si no existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'otp_codes'");
    if ($stmt->rowCount() == 0) {
        error_log("Creando tabla otp_codes");
        $sql = "CREATE TABLE IF NOT EXISTS otp_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code VARCHAR(6) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            INDEX idx_user_code (user_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($sql);
        error_log("Tabla otp_codes creada exitosamente");
    }
} catch (PDOException $e) {
    error_log("Error con tabla otp_codes: " . $e->getMessage());
    sendJsonResponse(false, 'Error de configuración de base de datos: ' . $e->getMessage());
}

// Verificar que getUserInfo existe y funciona
if (!function_exists('getUserInfo')) {
    error_log("Función getUserInfo no existe");
    sendJsonResponse(false, 'Error de configuración: función getUserInfo no encontrada');
}

// Función para obtener información del usuario
function getUserInfo($user_id) {
    global $pdo;
    try {
        // Consulta adaptada para usar el enum correcto
        $stmt = $pdo->prepare("
            SELECT 
                id,
                nombre,
                rol,
                estado,
                empresa_id,
                email,
                fecha_creacion
            FROM users 
            WHERE id = ? 
            AND estado = 'activo'
            LIMIT 1
        ");
        
        error_log("Ejecutando consulta para usuario ID: $user_id");
        $stmt->execute([$user_id]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("Usuario no encontrado o inactivo. ID: $user_id");
            return false;
        }

        error_log("Usuario encontrado exitosamente: " . print_r($user, true));
        return $user;
        
    } catch (PDOException $e) {
        error_log("Error en getUserInfo: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// Función para generar OTP
function generateOTP() {
    try {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        error_log("Error generando OTP: " . $e->getMessage());
        return false;
    }
}

// Función modificada para guardar OTP según la estructura real de la tabla
function saveOTP($user_id, $otp, $type) {
    global $pdo;
    try {
        error_log("Intentando guardar OTP para usuario: $user_id");
        
        // Eliminar OTPs anteriores para este usuario
        $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        error_log("OTPs anteriores eliminados");
        
        // Insertar nuevo OTP usando la estructura real de la tabla
        $stmt = $pdo->prepare("
            INSERT INTO otp_codes (
                user_id, 
                code, 
                created_at, 
                expires_at,
                used
            ) VALUES (
                :user_id,
                :code,
                NOW(),
                DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                0
            )
        ");

        $params = [
            ':user_id' => $user_id,
            ':code' => $otp
        ];

        error_log("Ejecutando inserción con parámetros: " . print_r($params, true));
        
        $result = $stmt->execute($params);
        
        if ($result) {
            error_log("OTP guardado exitosamente");
            return true;
        } else {
            error_log("Error al guardar OTP: " . print_r($stmt->errorInfo(), true));
            return false;
        }
    } catch (PDOException $e) {
        error_log("Error en saveOTP: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// Función para enviar email
function sendOTPEmail($email, $otp, $type) {
    try {
        $subject = "Código de verificación - VendEasy";
        $message = "Tu código de verificación para " . 
                   ($type == 'email' ? "cambiar tu correo" : "cambiar tu contraseña") . 
                   " es: $otp\n\nEste código expirará en 10 minutos.";
        
        $headers = "From: noreply@vendeasy.com\r\n";
        $headers .= "Reply-To: noreply@vendeasy.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        if (!mail($email, $subject, $message, $headers)) {
            error_log("Error enviando email a: $email");
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log("Error en sendOTPEmail: " . $e->getMessage());
        return false;
    }
}

// Actualizar la función deleteUserAccount
function deleteUserAccount($user_id) {
    global $pdo;
    try {
        error_log("Iniciando eliminación de cuenta para usuario: $user_id");
        
        $pdo->beginTransaction();

        // Verificar si el usuario existe y está activo
        $stmt = $pdo->prepare("SELECT estado FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Usuario no encontrado");
        }

        if ($user['estado'] === 'eliminado' || $user['estado'] === 'inactivo') {
            throw new Exception("La cuenta ya está desactivada");
        }

        // Actualizar el estado del usuario a 'eliminado' y establecer fecha de desactivación
        $stmt = $pdo->prepare("
            UPDATE users 
            SET estado = 'eliminado',
                fecha_desactivacion = NOW()
            WHERE id = ?
        ");
        
        if (!$stmt->execute([$user_id])) {
            throw new Exception("Error al desactivar la cuenta");
        }

        error_log("Cuenta desactivada exitosamente");

        // Registrar la acción en el log de auditoría si la tabla existe
        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (
                    user_id, 
                    action, 
                    details, 
                    created_at
                ) VALUES (
                    ?, 
                    'account_deletion', 
                    'Cuenta desactivada por solicitud del usuario', 
                    NOW()
                )
            ");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            // Si falla el log de auditoría, solo lo registramos pero no interrumpimos el proceso
            error_log("Error al registrar en audit_log: " . $e->getMessage());
        }

        $pdo->commit();
        error_log("Proceso de eliminación completado exitosamente");
        return true;

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error en deleteUserAccount: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }
}

try {
    if ($_POST['action'] === 'request_email_otp' || $_POST['action'] === 'request_password_otp') {
        $type = str_replace('request_', '', str_replace('_otp', '', $_POST['action']));
        error_log("Procesando solicitud OTP tipo: $type");
        
        // Obtener información del usuario
        $user_info = getUserInfo($user_id);
        if (!$user_info) {
            throw new Exception('Error al obtener información del usuario');
        }
        
        // Generar OTP
        $otp = generateOTP();
        if (!$otp) {
            throw new Exception('Error al generar el código');
        }
        error_log("OTP generado: $otp");
        
        // Guardar OTP con manejo de errores específico
        if (!saveOTP($user_id, $otp, $type)) {
            error_log("Falló al guardar OTP en la base de datos");
            throw new Exception('Error al guardar el código en la base de datos');
        }
        
        // Enviar email
        if (!sendOTPEmail($user_info['email'], $otp, $type)) {
            throw new Exception('Error al enviar el correo');
        }
        
        sendJsonResponse(true, 'Código enviado correctamente');
    }
    elseif ($_POST['action'] === 'delete_account') {
        error_log("Procesando solicitud de eliminación de cuenta para usuario: $user_id");
        
        if (deleteUserAccount($user_id)) {
            // Limpiar la sesión
            session_destroy();
            sendJsonResponse(true, 'Cuenta eliminada correctamente');
        } else {
            throw new Exception('Error al eliminar la cuenta');
        }
    }
} catch (Exception $e) {
    error_log('Error en ajax_handlers.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, $e->getMessage());
} 