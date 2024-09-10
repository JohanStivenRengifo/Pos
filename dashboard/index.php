<?php
// Llamado a la Base de Datos
require_once '../config/db_connection.php';
$pdo = getPDOConnection();

// Inclusión de las partes comunes de la página
include_once __DIR__ . '/includes/header.php';
include_once __DIR__ . '/includes/sidebar.php';

// Sanitización y carga dinámica del módulo
$module = filter_input(INPUT_GET, 'module', FILTER_SANITIZE_SPECIAL_CHARS);
$module = $module ?: 'home';

$module_path = __DIR__ . "/modules/{$module}/index.php";
if (is_file($module_path) && file_exists($module_path)) {
    include_once $module_path;
} else {
    echo "<div class='content'>Módulo no encontrado.</div>";
}

// Inclusión del pie de página
include_once __DIR__ . '/includes/footer.php';
?>