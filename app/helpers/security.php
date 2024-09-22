<?php
/**
 * Sanitiza los datos de entrada para prevenir ataques XSS.
 *
 * @param string $data Entrada a sanitizar
 * @return string Dato sanitizado
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica si un correo electrónico es válido.
 *
 * @param string $email Correo electrónico a validar
 * @return bool Verdadero si es válido, falso si no
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Verifica que una contraseña cumpla con los requisitos de seguridad.
 * 
 * @param string $password Contraseña a validar
 * @return bool Verdadero si la contraseña es segura, falso si no
 */
function validatePassword($password) {
    return preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password);
}

/**
 * Genera un hash seguro de una contraseña.
 *
 * @param string $password Contraseña a encriptar
 * @return string Hash de la contraseña
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verifica una contraseña con su hash.
 *
 * @param string $password Contraseña sin encriptar
 * @param string $hashedPassword Hash almacenado
 * @return bool Verdadero si coinciden, falso si no
 */
function verifyPassword($password, $hashedPassword) {
    return password_verify($password, $hashedPassword);
}

/**
 * Previene ataques de fuerza bruta mediante un contador de intentos fallidos.
 * Puede ser usado junto con el almacenamiento de sesiones o base de datos.
 *
 * @param string $user Identificador del usuario
 * @param int $maxAttempts Número máximo de intentos permitidos
 * @param int $lockoutTime Tiempo de bloqueo en segundos
 * @return bool Verdadero si está bloqueado, falso si no
 */
function preventBruteForce($user, $maxAttempts = 5, $lockoutTime = 900) {
    // Iniciar la sesión si no está ya iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Asegurarse de que el array 'login_attempts' existe en la sesión
    if (!isset($_SESSION['login_attempts']) || !is_array($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }

    // Inicializar los intentos de login del usuario si no existen
    if (!isset($_SESSION['login_attempts'][$user])) {
        $_SESSION['login_attempts'][$user] = [
            'count' => 0,
            'last_attempt' => time(),
            'lockout_until' => 0
        ];
    }

    // Obtener los intentos del usuario por referencia
    $attempts = &$_SESSION['login_attempts'][$user];

    // Verificar si el usuario está bloqueado
    if ($attempts['lockout_until'] > time()) {
        return true; // Usuario bloqueado
    }

    // Restablecer el contador si ha pasado más tiempo que el de bloqueo desde el último intento
    if (time() - $attempts['last_attempt'] > $lockoutTime) {
        $attempts['count'] = 0;
    }

    // Verificar si los intentos han alcanzado el máximo permitido
    if ($attempts['count'] >= $maxAttempts) {
        $attempts['lockout_until'] = time() + $lockoutTime;
        $attempts['count'] = 0; // Reiniciar contador tras bloqueo
        return true; // Usuario bloqueado
    }

    // Actualizar el último intento
    $attempts['last_attempt'] = time();

    return false; // Usuario no bloqueado
}

/**
 * Aumenta el contador de intentos fallidos para el usuario.
 *
 * @param string $user Identificador del usuario
 */
function incrementFailedAttempts($user) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['login_attempts'][$user])) {
        $_SESSION['login_attempts'][$user] = ['count' => 0, 'last_attempt' => time()];
    }

    $_SESSION['login_attempts'][$user]['count'] += 1;
    $_SESSION['login_attempts'][$user]['last_attempt'] = time();
}

?>