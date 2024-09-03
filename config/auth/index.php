<?php
// Inicia la sesión si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir el archivo db.php usando la ruta relativa correcta
require '../db.php';

// Definir constantes
define('OTP_LENGTH', 6);

// Función para generar OTP
function generateOTP($length = OTP_LENGTH) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Función para obtener la plantilla del correo electrónico OTP
function getOtpEmailTemplate($otp) {
    ob_start();
    include '../../templates/email_template.php'; 
    $template = ob_get_clean();
    return str_replace('{{otp}}', $otp, $template);
}

// Función para enviar el correo electrónico con el OTP
function sendOtpEmail($to, $otp) {
    $subject = 'Código de Verificación de OTP';
    $message = getOtpEmailTemplate($otp);
    $headers = "From: no-reply@vendeasy.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $message, $headers);
}

// Función para verificar OTP
function verifyOTP($inputOtp) {
    // Conectar a la base de datos principal
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, 'main_db');
    if ($db->connect_error) {
        handleError("Error de conexión: " . $db->connect_error);
    }

    // Preparar y ejecutar la consulta
    $stmt = $db->prepare("SELECT otp, otp_expires_at FROM users WHERE id = ?");
    if (!$stmt) {
        handleError("Error en la consulta: " . $db->error);
    }
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($storedOtp, $otpExpiresAt);
    $stmt->fetch();

    // Verificar OTP y su validez
    if ($storedOtp === $inputOtp && new DateTime() <= new DateTime($otpExpiresAt)) {
        // OTP verificado exitosamente
        $stmt->close();
        $db->close();

        // Limpiar OTP y expiración
        $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, 'main_db');
        $stmt = $db->prepare("UPDATE users SET otp = NULL, otp_expires_at = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        } else {
            handleError("Error al preparar la consulta de actualización de OTP: " . $db->error);
        }
        $db->close();

        // Redirigir al usuario a la siguiente página o proceso
        header('Location: dashboard.php');
        exit();
    } else {
        $stmt->close();
        $db->close();

        $_SESSION['error_message'] = "El OTP es incorrecto o ha expirado.";
        header('Location: verify_otp.php');
        exit();
    }
}

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

// Función para redirigir a la página de inicio de sesión si no está autenticado
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
}

// Función para cerrar la sesión del usuario
function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

// Función para manejar errores y redirigir
function handleError($message, $redirect = '/public/auth/register.php') {
    $_SESSION['error_message'] = $message;
    header("Location: $redirect");
    exit();
}

// Función para crear una base de datos
function createDatabase($dbname) {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD);
    if ($db->connect_error) {
        handleError("Error de conexión: " . $db->connect_error);
    }
    if ($db->query("CREATE DATABASE `$dbname`") === TRUE) {
        $db->select_db($dbname);
        return $db;
    } else {
        handleError("Error al crear la base de datos: " . $db->error);
    }
}

// Función para importar la plantilla de base de datos
function importDatabaseTemplate($db) {
    $sqlFilePath = '../../templates/db_template.sql'; 
    if (!file_exists($sqlFilePath)) {
        handleError("El archivo de plantilla de base de datos no se encuentra en la ruta especificada: $sqlFilePath");
    }

    $sql = file_get_contents($sqlFilePath);
    if ($sql === false) {
        handleError("Error al leer el archivo de plantilla de base de datos.");
    }

    if ($db->multi_query($sql)) {
        do {
            if ($result = $db->store_result()) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());
    } else {
        handleError("Error al ejecutar las consultas SQL: " . $db->error);
    }
}

// Función para registrar un nuevo usuario
function registerUser($userData) {
    // Verifica que los campos requeridos no estén vacíos
    $requiredFields = ['username', 'password', 'dbname', 'email', 'name', 'company_name'];
    foreach ($requiredFields as $field) {
        if (empty($userData[$field])) {
            handleError("El campo '$field' es obligatorio.");
        }
    }

    // Conexión a la base de datos
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, 'main_db');
    if ($db->connect_error) {
        handleError("Error de conexión: " . $db->connect_error);
    }

    // Preparar la consulta de inserción para el usuario
    $stmt = $db->prepare("INSERT INTO users (username, password, dbname, created_at, email, name, company_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        handleError("Error al preparar la consulta: " . $db->error);
    }

    $created_at = date('Y-m-d H:i:s');
    $stmt->bind_param(
        'sssssss',
        $userData['username'],
        $userData['password'],
        $userData['dbname'],
        $created_at,
        $userData['email'],
        $userData['name'],
        $userData['company_name']
    );

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;

        // Registrar la sesión activa
        $session_id = session_id();
        $stmt = $db->prepare("INSERT INTO sessions (user_id, session_id) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $session_id);
            $stmt->execute();
            $stmt->close();
        } else {
            handleError("Error al preparar la consulta de sesiones: " . $db->error);
        }

        // Registrar el log de acceso
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $action = "Registro de nuevo usuario y creación de base de datos";
        $stmt = $db->prepare("INSERT INTO access_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('isss', $user_id, $action, $ip_address, $created_at);
            $stmt->execute();
            $stmt->close();
        } else {
            handleError("Error al preparar la consulta de logs de acceso: " . $db->error);
        }

        // Redirigir al login
        header('Location: ../../index.php');
        exit();
    } else {
        handleError("Error al registrar el usuario: " . $stmt->error);
    }

    $stmt->close();
    $db->close();
}

// Función para manejar la autenticación de usuarios
function authenticateUser($username, $password) {
    // Conectar a la base de datos principal
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, 'main_db');

    // Verificar la conexión
    if ($db->connect_error) {
        throw new Exception("Error de conexión: " . $db->connect_error);
    }

    // Preparar y ejecutar la consulta
    $stmt = $db->prepare("SELECT id, password, dbname FROM users WHERE username = ?");
    if (!$stmt) {
        throw new Exception("Error en la consulta: " . $db->error);
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password, $dbname);
    $stmt->fetch();

    // Cerrar declaración y conexión
    $stmt->close();
    $db->close();

    // Verificar la contraseña
    if (password_verify($password, $hashed_password)) {
        return [
            'id' => $id,
            'dbname' => $dbname
        ];
    } else {
        return false;
    }
}

function loginUser($username, $password) {
    // Conectar a la base de datos principal
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, 'main_db');

    // Verificar la conexión
    if ($db->connect_error) {
        return ["status" => false, "message" => "Error de conexión: " . $db->connect_error];
    }

    // Preparar y ejecutar la consulta
    $stmt = $db->prepare("SELECT id, password, email FROM users WHERE username = ?");
    if (!$stmt) {
        return ["status" => false, "message" => "Error en la consulta: " . $db->error];
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($id, $hashed_password, $email);
    $stmt->fetch();

    // Cerrar declaración y conexión
    $stmt->close();
    $db->close();

    // Verificar la contraseña
    if ($id && password_verify($password, $hashed_password)) {
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['email'] = $email;

        // Generar y enviar OTP
        $otp = generateOTP(); // Genera un nuevo OTP
        $_SESSION['otp'] = $otp; // Almacena el OTP en la sesión

        $otpSent = sendOtpEmail($email, $otp); // Envía el OTP al correo del usuario

        if ($otpSent) {
            return ["status" => true, "otp_required" => true];
        } else {
            return ["status" => false, "message" => "Error al enviar el OTP. Inténtalo nuevamente."];
        }
    } else {
        return ["status" => false, "message" => "Usuario o contraseña incorrectos."];
    }
}
?>