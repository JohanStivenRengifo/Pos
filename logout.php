<?php
session_start();
require_once 'config/db.php';
require_once 'config/config.php';

// Clase para manejar respuestas JSON
class ApiResponse {
    public static function send($status, $message, $data = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Función mejorada para cerrar sesión
function logoutUser() {
    try {
        // Limpiar todas las variables de sesión
        $_SESSION = array();

        // Destruir la cookie de sesión si existe
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destruir la sesión
        session_destroy();

        // Verificar si es una petición AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            ApiResponse::send(true, 'Sesión cerrada exitosamente');
        }

        // Redireccionar a la página de inicio
        header("Location: " . APP_URL . "/index.php");
        exit();
    } catch (Exception $e) {
        // Log del error
        error_log("Error en logout: " . $e->getMessage());
        
        // Si es AJAX, enviar respuesta de error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            ApiResponse::send(false, 'Error al cerrar sesión');
        }
        
        // Redireccionar a página de error
        header("Location: " . APP_URL . "/error.php");
        exit();
    }
}

// Ejecutar el cierre de sesión
logoutUser();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cerrando sesión - VendEasy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
            font-family: 'Arial', sans-serif;
        }
        .logout-message {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="logout-message">
        <h2>Cerrando sesión...</h2>
        <p>Por favor, espere un momento.</p>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Cerrando sesión',
            text: 'Por favor, espere un momento...',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Redireccionar después de un breve delay
        setTimeout(() => {
            window.location.href = '<?= APP_URL ?>/index.php';
        }, 1500);
    });

    // Prevenir navegación hacia atrás después del logout
    window.history.forward();
    function noBack() {
        window.history.forward();
    }
    </script>
</body>
</html>