<?php
http_response_code(500);
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error del servidor | Numercia</title>
    <meta name="description" content="Lo sentimos, ha ocurrido un error interno del servidor.">
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
            <i class="fas fa-exclamation-triangle text-6xl text-red-500 mb-4"></i>
            <h1 class="text-9xl font-bold text-red-500 mb-4">500</h1>
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Error interno del servidor</h2>
            <p class="text-gray-600 mb-8">
                Lo sentimos, ha ocurrido un error inesperado. 
                Nuestro equipo técnico ha sido notificado y está trabajando en solucionarlo.
            </p>
        </div>
        
        <div class="space-y-4">
            <button onclick="location.reload()" class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center">
                <i class="fas fa-redo mr-2"></i>
                Intentar nuevamente
            </button>
            
            <button onclick="history.back()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-lg transition duration-300">
                <i class="fas fa-arrow-left mr-2"></i>
                Regresar
            </button>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200">
            <p class="text-gray-600 mb-2">Si el problema persiste, por favor</p>
            <a href="../contacto.php" class="text-red-500 hover:text-red-600 font-medium transition duration-300">
                contacta con soporte técnico
            </a>
        </div>
    </div>
</body>
</html> 