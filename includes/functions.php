<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

function sanitizeInput($data) {
    return htmlspecialchars(trim($data));
}

function isUserLoggedIn($pdo) {
    if (isset($_SESSION['user_id'])) {
        return true;
    } elseif (isset($_COOKIE['remember_token'])) {
        return validateRememberToken($pdo);
    }
    return false;
}

function getUserByEmail($pdo, $email) {
    $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    return $stmt->fetch();
}

function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    session_regenerate_id(true);
}

function setRememberMeCookie($pdo, $user) {
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

function validateRememberToken($pdo) {
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

function checkBruteForce($pdo, $user_id) {
    $sql = "SELECT COUNT(*) as attempt_count FROM login_attempts 
            WHERE user_id = :user_id AND attempt_time > DATE_SUB(NOW(), INTERVAL :timeout SECOND)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $user_id,
        'timeout' => LOGIN_ATTEMPT_TIMEOUT
    ]);
    $result = $stmt->fetch();
    
    return $result['attempt_count'] >= MAX_LOGIN_ATTEMPTS;
}

function incrementLoginAttempts($pdo, $user_id) {
    $sql = "INSERT INTO login_attempts (user_id, attempt_time) VALUES (:user_id, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
}

function resetLoginAttempts($pdo, $user_id) {
    $sql = "DELETE FROM login_attempts WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
}

function logoutUser($pdo) {
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
