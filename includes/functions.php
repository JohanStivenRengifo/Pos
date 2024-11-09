<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data));
}

function isUserLoggedIn($pdo)
{
    if (isset($_SESSION['user_id'])) {
        return true;
    } elseif (isset($_COOKIE['remember_token'])) {
        return validateRememberToken($pdo);
    }
    return false;
}

function getUserByEmail($pdo, $email)
{
    $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    return $stmt->fetch();
}

function loginUser($user)
{
    if (!$user || !isset($user['id']) || !isset($user['email'])) {
        error_log("Datos de usuario inválidos en loginUser");
        return false;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['nombre'] = $user['nombre'] ?? '';

    // Regenerar ID de sesión por seguridad
    if (!session_regenerate_id(true)) {
        error_log("Error regenerando ID de sesión");
        return false;
    }

    return true;
}

function setRememberMeCookie($pdo, $user)
{
    $token = bin2hex(random_bytes(32));
    $expires = time() + COOKIE_LIFETIME;

    $hashed_token = password_hash($token, PASSWORD_DEFAULT);

    setcookie('remember_token', $token, [
        'expires' => $expires,
        'path' => COOKIE_PATH,
        'domain' => COOKIE_DOMAIN,
        'secure' => COOKIE_SECURE,
        'httponly' => COOKIE_HTTPONLY,
        'samesite' => 'Strict'
    ]);

    $sql = "UPDATE users SET remember_token = :token, token_expires = :expires WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'token' => $hashed_token,
        'expires' => date('Y-m-d H:i:s', $expires),
        'id' => $user['id']
    ]);
}

function validateRememberToken($pdo)
{
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $sql = "SELECT * FROM users WHERE token_expires > NOW() LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($token, $user['remember_token'])) {
            loginUser($user);
            // Renovar el token
            setRememberMeCookie($pdo, $user);
            return true;
        }
    }
    return false;
}

/**
 * Verifica si un usuario ha excedido el número de intentos fallidos de login
 * @param PDO $pdo Conexión a la base de datos
 * @param int $user_id ID del usuario
 * @return bool True si el usuario está bloqueado, False en caso contrario
 */
function checkBruteForce($pdo, $user_id) {
    try {
        // Verificar intentos fallidos en los últimos 15 minutos
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE user_id = ? 
            AND success = 0 
            AND time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        
        $stmt->execute([$user_id]);
        $attempts = $stmt->fetchColumn();
        
        // Bloquear después de 5 intentos fallidos
        return $attempts >= 5;
    } catch (Exception $e) {
        error_log("Error verificando intentos de login: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si una IP ha excedido el número de intentos fallidos
 * @param PDO $pdo Conexión a la base de datos
 * @param string $ip_address Dirección IP
 * @return bool True si la IP está bloqueada, False en caso contrario
 */
function checkIPBlocked($pdo, $ip_address) {
    try {
        // Verificar intentos fallidos desde la misma IP en los últimos 15 minutos
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND success = 0 
            AND time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        
        $stmt->execute([$ip_address]);
        return $stmt->fetchColumn() >= 3; // Bloquear después de 3 intentos fallidos
    } catch (Exception $e) {
        error_log("Error verificando bloqueo de IP: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra un intento de login
 * @param PDO $pdo Conexión a la base de datos
 * @param int|null $user_id ID del usuario (null si no existe)
 * @param bool $success Si el intento fue exitoso
 * @param string $reason Razón del intento (opcional)
 */
function logLoginAttempt($pdo, $user_id = null, $success = false, $reason = '') {
    try {
        // Registrar en login_attempts
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (
                user_id,
                ip_address,
                time,
                success
            ) VALUES (?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $user_id,
            $_SERVER['REMOTE_ADDR'],
            $success ? 1 : 0
        ]);

        // Si el intento fue exitoso, registrar en login_history
        if ($success && $user_id) {
            $stmt = $pdo->prepare("
                INSERT INTO login_history (
                    user_id,
                    ip_address,
                    status,
                    login_time
                ) VALUES (?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $_SERVER['REMOTE_ADDR'],
                'success'
            ]);
        }
    } catch (Exception $e) {
        error_log("Error registrando intento de login: " . $e->getMessage());
    }
}

// Agregar función para registrar inicio de sesión exitoso
function registerSuccessfulLogin($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (user_id, time, ip_address, success) 
            VALUES (?, NOW(), ?, 1)
        ");
        $stmt->execute([$user_id, $_SERVER['REMOTE_ADDR']]);
        
        // Actualizar último acceso del usuario
        $stmt = $pdo->prepare("
            UPDATE users 
            SET ultimo_acceso = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        
    } catch (PDOException $e) {
        error_log("Error en registerSuccessfulLogin: " . $e->getMessage());
    }
}

function incrementLoginAttempts($pdo, $user_id)
{
    $sql = "INSERT INTO login_attempts (user_id, attempt_time) VALUES (:user_id, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
}

function resetLoginAttempts($pdo, $user_id)
{
    $sql = "DELETE FROM login_attempts WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
}

function logoutUser($pdo)
{
    $user_id = $_SESSION['user_id'] ?? null;
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();

    // Eliminar el token de remember_me de la base de datos
    if ($user_id) {
        $sql = "UPDATE users SET remember_token = NULL, token_expires = NULL WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $user_id]);
    }

    // Eliminar la cookie remember_token
    setcookie('remember_token', '', time() - 3600, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
}

/**
 * Actualiza el hash de la contraseña
 */
function updatePasswordHash($pdo, $user_id, $password)
{
    try {
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$new_hash, $user_id]);
    } catch (PDOException $e) {
        error_log("Error actualizando hash de contraseña: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra un intento de inicio de sesión exitoso
 */
function logSuccessfulLogin($pdo, $user_id, $ip_address)
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_history (user_id, ip_address, status, login_time)
            VALUES (?, ?, 'success', NOW())
        ");
        return $stmt->execute([$user_id, $ip_address]);
    } catch (PDOException $e) {
        error_log("Error registrando login exitoso: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si una petición es AJAX
 */
function isAjaxRequest()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Envía un email con formato HTML
 */
function sendFormattedEmail($to, $subject, $content)
{
    $from = "noreply@johanrengifo.cloud";
    $headers = array(
        'From' => $from,
        'Reply-To' => $from,
        'X-Mailer' => 'PHP/' . phpversion(),
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8'
    );

    $template = <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
        <style>
            body {
                font-size: 1.25rem;
                font-family: 'Roboto', sans-serif;
                padding: 20px;
                background-color: #FAFAFA;
                width: 75%;
                max-width: 1280px;
                min-width: 600px;
                margin: auto;
            }

            a {
                text-decoration: none;
                color: #00788a;
            }

            a:hover {
                text-decoration: underline;
            }

            h1, h2, h3, h4, h5, h6, .t_cht {
                color: #000 !important;
            }

            .ExternalClass p,
            .ExternalClass span,
            .ExternalClass font,
            .ExternalClass td {
                line-height: 100%;
            }

            .ExternalClass {
                width: 100%;
            }

            .header-image {
                width: 80%;
                max-width: 750px;
            }

            .main-content {
                padding: 50px;
                background-color: #fff;
                max-width: 660px;
                margin: auto;
            }

            .footer-text {
                color: #B6B6B6;
                font-size: 14px;
            }
        </style>
    </head>

    <body>
        <table cellpadding="12" cellspacing="0" width="100%" bgcolor="#FAFAFA" style="border-collapse: collapse;">
            <thead>
                <tr>
                    <td style="padding: 0;">
                        <img src="https://uploads-ssl.webflow.com/5e96c040bda7162df0a5646d/5f91d2a4d4d57838829dcef4_image-blue%20waves%402x.png" class="header-image" alt="Blue waves" />
                    </td>
                </tr>
                <tr>
                    <td style="text-align: center; padding-bottom: 20px;">
                        <h1 style="font-family: 'Times New Roman', serif; color: black; font-size: 2.5em; font-style: italic;">
                            VendEasy
                        </h1>
                        <p style="font-family: 'Times New Roman', serif; color: gray; font-size: 1em;">
                            Tu sistema contable inteligente
                        </p>
                    </td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="main-content">
                        <table width="100%">
                            <tr>
                                <td style="text-align: center;">
                                    $content
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td style="text-align: center; padding-top: 30px;">
                        <p class="footer-text">
                            Este es un correo automático, por favor no responda a este mensaje.
                        </p>
                    </td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>
    HTML;


    return mail($to, $subject, $template, $headers);
}

/**
 * Genera y envía un código OTP
 */
function generateAndSendOTP($userId, $userEmail)
{
    global $pdo;

    try {
        // Generar código OTP de 6 dígitos
        $otp = sprintf("%06d", mt_rand(0, 999999));

        // Establecer tiempo de expiración (15 minutos)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Invalidar códigos OTP anteriores
        $stmt = $pdo->prepare("UPDATE otp_codes SET used = TRUE WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Insertar nuevo código OTP
        $stmt = $pdo->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
        if (!$stmt->execute([$userId, $otp, $expiresAt])) {
            return false;
        }

        // Preparar contenido del email
        $emailContent = <<<HTML
        <h1 style="font-size: 30px; color: #202225; margin-top: 0;">Código de Verificación</h1>
        <p style="font-size: 18px; margin-bottom: 30px; color: #202225; max-width: 60ch; margin-left: auto; margin-right: auto">
            Tu código de verificación es:
        </p>
        <div style="background-color: #f5f5f5; padding: 20px; border-radius: 10px; margin: 20px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #1755F5;">$otp</span>
        </div>
        <p style="font-size: 16px; color: #666; margin-top: 20px;">
            Este código expirará en 15 minutos.<br>
            Si no has solicitado este código, puedes ignorar este mensaje.
        </p>
        HTML;

        // Enviar email
        return sendFormattedEmail(
            $userEmail,
            "Código de verificación - VendEasy",
            $emailContent
        );
    } catch (Exception $e) {
        error_log("Error en generateAndSendOTP: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si un código OTP es válido
 */
function verifyOTP($userId, $code)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT id 
            FROM otp_codes 
            WHERE user_id = ? 
            AND code = ? 
            AND used = FALSE 
            AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");

        if (!$stmt->execute([$userId, $code])) {
            error_log("Error verificando OTP: " . implode(", ", $stmt->errorInfo()));
            return false;
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Marcar código como usado
            $updateStmt = $pdo->prepare("UPDATE otp_codes SET used = TRUE WHERE id = ?");
            if (!$updateStmt->execute([$result['id']])) {
                error_log("Error marcando OTP como usado: " . implode(", ", $updateStmt->errorInfo()));
                return false;
            }
            return true;
        }

        return false;
    } catch (PDOException $e) {
        error_log("Error en verifyOTP: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene un usuario por su ID
 */
function getUserById($pdo, $user_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE id = ? 
            LIMIT 1
        ");

        if (!$stmt->execute([$user_id])) {
            error_log("Error ejecutando consulta getUserById: " . implode(", ", $stmt->errorInfo()));
            return false;
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            error_log("No se encontró usuario con ID: " . $user_id);
            return false;
        }

        return $user;
    } catch (PDOException $e) {
        error_log("Error en getUserById: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica si un usuario existe por su ID
 * @param PDO $pdo Conexión a la base de datos
 * @param int $user_id ID del usuario
 * @return bool True si existe, false si no
 */
function userExists($pdo, $user_id)
{
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Error en userExists: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene la fecha y hora actual en zona horaria de Bogotá
 * @param string $format Formato de fecha deseado
 * @return string Fecha formateada
 */
function getBogotaDateTime($format = 'Y-m-d H:i:s') {
    $datetime = new DateTime('now', new DateTimeZone('America/Bogota'));
    return $datetime->format($format);
}
