<?php
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarTokenCSRF($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    if ($_SESSION['csrf_token'] !== $token) {
        return false;
    }

    return true;
}

?>