<?php
// Verificar la sesión
session_start();
error_log("Iniciando prestamos/index.php");

if (!isset($_SESSION['user_id'])) {
    error_log("Usuario no autenticado, redirigiendo a login");
    header('Location: ../../login.php');
    exit();
}

error_log("Usuario autenticado: " . $_SESSION['user_id']);

// Incluir la conexión
try {
    require_once '../../config/db.php';
    error_log("Conexión a base de datos establecida");

    // Verificar si las tablas existen
    $tables_check = $pdo->query("SHOW TABLES LIKE 'prestamos'");
    if ($tables_check === false) {
        error_log("Error al verificar tabla prestamos: " . $pdo->errorInfo()[2]);
        throw new Exception("Error al verificar la estructura de la base de datos");
    }

    if ($tables_check->rowCount() == 0) {
        error_log("La tabla prestamos no existe");
        throw new Exception("La tabla de préstamos no existe. Por favor, ejecute el script SQL de instalación.");
    }

    error_log("Tabla prestamos verificada correctamente");

    // Incluir el header
    require_once '../../includes/header.php';
    error_log("Header incluido correctamente");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prestamos | VendEasy</title>
    <link rel="icon" href="../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <?php require_once '../../includes/sidebar.php'; ?>
        <div class="flex-1">
            <div class="container mx-auto px-4 py-8">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h1 class="text-2xl font-bold mb-6">Gestión de Préstamos</h1>
                    
                    <!-- Botones de acción -->
                    <div class="mb-6">
                        <a href="crear.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            <i class="fas fa-plus mr-2"></i>Nuevo Préstamo
                        </a>
                    </div>

                    <!-- Tabla de préstamos -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-6 py-3 text-left">ID</th>
                                    <th class="px-6 py-3 text-left">Cliente/Trabajador</th>
                                    <th class="px-6 py-3 text-left">Fecha Préstamo</th>
                                    <th class="px-6 py-3 text-left">Estado</th>
                                    <th class="px-6 py-3 text-left">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaPrestamos">
                                <!-- Aquí se cargarán los préstamos dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../js/prestamos.js"></script>
</body>
</html>
<?php
} catch (Exception $e) {
    error_log("Error en prestamos/index.php: " . $e->getMessage());
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error | VendEasy</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
            <div class="text-center">
                <h1 class="text-2xl font-bold text-red-600 mb-4">Error</h1>
                <p class="text-gray-600 mb-6"><?= htmlspecialchars($e->getMessage()) ?></p>
                <a href="../../index.php" class="text-blue-500 hover:text-blue-700">
                    Volver al inicio
                </a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}
?>