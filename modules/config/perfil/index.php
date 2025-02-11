<?php
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/functions.php';

// Obtener información del usuario
$stmt = $pdo->prepare("
    SELECT u.*, e.nombre_empresa, e.plan_suscripcion
    FROM users u
    LEFT JOIN empresas e ON e.id = ?
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['empresa_id'], $_SESSION['user_id']]);
$user = $stmt->fetch();

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = trim($_POST['nombre']);
        $telefono = trim($_POST['telefono'] ?? '');
        $rol = trim($_POST['rol'] ?? $user['rol']);

        // Iniciar transacción
        $pdo->beginTransaction();

        // Actualizar información básica
        $stmt = $pdo->prepare("
            UPDATE users 
            SET nombre = ?, 
                telefono = ?,
                rol = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $telefono, $rol, $_SESSION['user_id']]);

        $pdo->commit();
        $_SESSION['success_message'] = "Perfil actualizado correctamente.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | VendEasy</title>
    <link rel="icon" type="image/png" href="../../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <?php include '../../../includes/header.php'; ?>

    <div class="flex">
        <?php include '../../../includes/sidebar.php'; ?>

        <main class="flex-1 p-8">
            <div class="max-w-4xl mx-auto">
                <!-- Encabezado con información del usuario -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
                    <div class="flex items-center space-x-6">
                        <div class="flex-shrink-0">
                            <div class="w-24 h-24 bg-primary-100 rounded-full flex items-center justify-center">
                                <span class="text-3xl font-bold text-primary-600">
                                    <?= strtoupper(substr($user['nombre'], 0, 1)) ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($user['nombre']) ?></h1>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                            <div class="mt-2 flex items-center space-x-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?= ucfirst($user['rol']) ?>
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $user['estado'] === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= ucfirst($user['estado']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: '<?= htmlspecialchars($_SESSION['success_message']) ?>',
                        timer: 3000,
                        showConfirmButton: false
                    });
                </script>
                <?php unset($_SESSION['success_message']); endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '<?= htmlspecialchars($_SESSION['error_message']) ?>',
                    });
                </script>
                <?php unset($_SESSION['error_message']); endif; ?>

                <!-- Formulario de Perfil -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Información Personal</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Actualiza tu información personal y preferencias de cuenta
                        </p>
                    </div>

                    <form method="POST" class="divide-y divide-gray-200">
                        <!-- Información básica -->
                        <div class="px-6 py-4 space-y-6">
                            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Nombre Completo
                                    </label>
                                    <input type="text" name="nombre" 
                                           value="<?= htmlspecialchars($user['nombre']) ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                           required>
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Teléfono
                                    </label>
                                    <input type="tel" name="telefono" 
                                           value="<?= htmlspecialchars($user['telefono'] ?? '') ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Rol
                                    </label>
                                    <input type="text" value="<?= ucfirst($user['rol']) ?>" 
                                           class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-gray-500"
                                           disabled>
                                </div>

                                <div class="sm:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Fecha de Registro
                                    </label>
                                    <input type="text" 
                                           value="<?= date('d/m/Y', strtotime($user['fecha_creacion'])) ?>"
                                           class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 text-gray-500"
                                           disabled>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="px-6 py-4 bg-gray-50 flex items-center justify-end space-x-3">
                            <button type="button" onclick="window.location.href='../index.php'"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Información adicional -->
                <div class="mt-8 bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-gray-900">Seguridad de la Cuenta</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Para cambiar tu correo electrónico o contraseña, ve a la sección de Configuración
                        </p>
                        <div class="mt-4">
                            <a href="../index.php" 
                               class="inline-flex items-center text-sm text-primary-600 hover:text-primary-500">
                                Ir a Configuración
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 