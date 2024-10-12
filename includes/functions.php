<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

function sanitizeInput($data) {
    return htmlspecialchars(trim($data));
}

function isUserLoggedIn() {
    return isset($_SESSION['user_id']) || (isset($_COOKIE['remember_token']) && validateRememberToken());
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

function setRememberMeCookie($user) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $expires = time() + COOKIE_LIFETIME;
    
    $hashed_token = password_hash($token, PASSWORD_DEFAULT);
    
    setcookie('remember_token', $token, $expires, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
    
    $sql = "UPDATE users SET remember_token = :token, token_expires = :expires WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'token' => $hashed_token,
        'expires' => date('Y-m-d H:i:s', $expires),
        'id' => $user['id']
    ]);
}

function validateRememberToken() {
    global $pdo;
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $sql = "SELECT * FROM users WHERE token_expires > NOW() LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user && password_verify($token, $user['remember_token'])) {
            loginUser($user);
            return true;
        }
    }
    return false;
}

function checkBruteForce($user_id) {
    global $pdo;
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

function incrementLoginAttempts($user_id) {
    global $pdo;
    $sql = "INSERT INTO login_attempts (user_id, attempt_time) VALUES (:user_id, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
}

function resetLoginAttempts($user_id) {
    global $pdo;
    $sql = "DELETE FROM login_attempts WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
}

function logoutUser() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    setcookie('remember_token', '', time() - 3600, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
}
