<?php
function verificarRol($rolRequerido) {
    if ($_SESSION['role'] !== $rolRequerido) {
        die("No tienes permiso para acceder a esta página.");
    }
}
?>
