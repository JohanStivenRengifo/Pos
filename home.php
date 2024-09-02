<?php
session_start();

// Verificar si el usuario está autenticado
if (isset($_SESSION['user_id'])) {
    // Redirigir al home si la sesión está activa
    header('Location: /home.php');
    exit();
} else {
    // Si no hay sesión, redirigir al login
    header('Location: public/auth/register.php');
    exit();
}
?>
