<?php
http_response_code(401);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No autorizado | Numercia</title>
    <meta name="description" content="Necesitas iniciar sesión para acceder a este recurso.">
    <link rel="icon" type="image/png" href="../favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full bg-white rounded-2xl shadow-xl p-8 text-center">
        <div class="mb-8">
            <i class="fas fa-user-lock text-6xl text-blue-500 mb-4"></i>
            <h1 class="text-9xl font-bold text-blue-500 mb-4">401</h1>
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">No autorizado</h2>
            <p class="text-gray-600 mb-8">
                Necesitas iniciar sesión para acceder a este recurso.
                Por favor, inicia sesión o regístrate si aún no tienes una cuenta.
            </p>
        </div>
        
        <div class="space-y-4">
            <a href="../modules/auth/login.php" class="w-full inline-block bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-lg transition duration-300">
                <i class="fas fa-sign-in-alt mr-2"></i>
                Iniciar sesión
            </a>
            
            <a href="../modules/auth/register.php" class="w-full inline-block bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-lg transition duration-300">
                <i class="fas fa-user-plus mr-2"></i>
                Crear cuenta
            </a>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
            <p class="text-gray-600 mb-2">¿Olvidaste tu contraseña?</p>
            <a href="../modules/auth/recuperar-password.php" class="text-blue-500 hover:text-blue-600 font-medium transition duration-300">
                Recuperar acceso
            </a>
        </div>
    </div>
</body>
</html> 