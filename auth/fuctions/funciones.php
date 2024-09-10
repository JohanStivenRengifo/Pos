<?php
function generarTokenCSRF() {
    return bin2hex(random_bytes(32));
}

function validarTokenCSRF($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("Token CSRF no válido.");
    }
}
?>