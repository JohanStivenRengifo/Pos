<?php
session_start();

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
}
?>