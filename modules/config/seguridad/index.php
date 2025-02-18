<?php
session_start();
require_once '../../../config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Función para verificar y crear la estructura de la base de datos
function verificarEstructuraDB($pdo) {
    try {
        // Verificar si existe la tabla user_sessions
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("CREATE TABLE user_sessions (
                session_id VARCHAR(255) PRIMARY KEY,
                user_id INT NOT NULL,
                empresa_id INT NOT NULL,
                user_agent VARCHAR(255),
                ip_address VARCHAR(45),
                location VARCHAR(255),
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (empresa_id) REFERENCES empresas(id)
            )");
        }

        // Registrar la sesión actual si no existe
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_sessions 
            (session_id, user_id, empresa_id, user_agent, ip_address, last_activity) 
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([
            session_id(),
            $_SESSION['user_id'],
            $_SESSION['empresa_id'],
            $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido',
            $_SERVER['REMOTE_ADDR'] ?? 'Desconocido'
        ]);

    } catch (PDOException $e) {
        error_log("Error en verificarEstructuraDB: " . $e->getMessage());
        return false;
    }
    return true;
}

// Verificar y crear estructura de BD si es necesario
if (!verificarEstructuraDB($pdo)) {
    $_SESSION['error_message'] = "Error al verificar la estructura de la base de datos";
    header("Location: ../index.php");
    exit;
}

try {
    // Obtener información del usuario
    $stmt = $pdo->prepare("SELECT id, nombre, email, password FROM users WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['empresa_id']]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        throw new Exception("Usuario no encontrado");
    }

    // Procesar cambio de contraseña
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'change_password':
                // Validar contraseña actual
                if (!password_verify($_POST['current_password'], $usuario['password'])) {
                    $_SESSION['error_message'] = "La contraseña actual es incorrecta";
                    break;
                }

                // Validar que la nueva contraseña cumpla con los requisitos
                if (strlen($_POST['new_password']) < 8) {
                    $_SESSION['error_message'] = "La nueva contraseña debe tener al menos 8 caracteres";
                    break;
                }

                if ($_POST['new_password'] !== $_POST['confirm_password']) {
                    $_SESSION['error_message'] = "Las contraseñas no coinciden";
                    break;
                }

                // Actualizar contraseña
                $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$new_password_hash, $_SESSION['user_id'], $_SESSION['empresa_id']]);

                $_SESSION['success_message'] = "Contraseña actualizada correctamente";
                break;

            case 'terminate_session':
                if (isset($_POST['session_id'])) {
                    if ($_POST['session_id'] === 'all') {
                        // Terminar todas las otras sesiones
                        $stmt = $pdo->prepare("
                            DELETE FROM user_sessions 
                            WHERE user_id = ? AND empresa_id = ? AND session_id != ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $_SESSION['empresa_id'], session_id()]);
                        $_SESSION['success_message'] = "Todas las otras sesiones han sido terminadas";
                    } else {
                        // Terminar una sesión específica
                        $stmt = $pdo->prepare("
                            DELETE FROM user_sessions 
                            WHERE user_id = ? AND empresa_id = ? AND session_id = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $_SESSION['empresa_id'], $_POST['session_id']]);
                        $_SESSION['success_message'] = "Sesión terminada correctamente";
                    }
                }
                break;
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Obtener sesiones activas
    $stmt = $pdo->prepare("
        SELECT 
            session_id,
            user_agent,
            ip_address,
            last_activity,
            location
        FROM user_sessions 
        WHERE user_id = ? AND empresa_id = ?
        ORDER BY last_activity DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['empresa_id']]);
    $sesiones = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en módulo de seguridad: " . $e->getMessage());
    $_SESSION['error_message'] = "Ha ocurrido un error. Por favor, inténtalo de nuevo más tarde.";
    $sesiones = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Seguridad | Numercia</title>
    <link rel="icon" href="../../../favicon/favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../../../includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
            <?php include '../../../includes/sidebar.php'; ?>

            <!-- Contenido Principal -->
            <div class="w-full lg:w-3/4 px-4">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-lock mr-2"></i>Configuración de Seguridad
                    </h1>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                            <?php 
                            echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                            <?php 
                            echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Configuración de Contraseña -->
                    <div class="bg-gray-50 p-6 rounded-lg mb-6">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Cambiar Contraseña</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="change_password">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Contraseña Actual</label>
                                <input type="password" name="current_password" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nueva Contraseña</label>
                                <input type="password" name="new_password" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Confirmar Nueva Contraseña</label>
                                <input type="password" name="confirm_password" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-colors">
                                    Actualizar Contraseña
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Autenticación de Dos Factores -->
                    <div class="bg-gray-50 p-6 rounded-lg mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-800">Autenticación de Dos Factores</h2>
                            <span class="px-3 py-1 text-xs font-semibold text-indigo-800 bg-indigo-100 rounded-full">
                                Próximamente
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            Próximamente podrás añadir una capa extra de seguridad a tu cuenta requiriendo un código además de tu contraseña.
                        </p>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-clock text-indigo-600"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-gray-700">
                                        Esta característica estará disponible en una próxima actualización. Te notificaremos cuando esté lista.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sesiones Activas -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Sesiones Activas</h2>
                        <div class="space-y-4">
                            <?php foreach ($sesiones as $sesion): ?>
                                <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow-sm">
                                    <div class="flex items-center">
                                        <div class="<?php echo $sesion['session_id'] === session_id() ? 'bg-green-100' : 'bg-gray-100'; ?> p-2 rounded-full">
                                            <i class="fas <?php echo strpos(strtolower($sesion['user_agent']), 'mobile') !== false ? 'fa-mobile-alt' : 'fa-desktop'; ?> 
                                               <?php echo $sesion['session_id'] === session_id() ? 'text-green-600' : 'text-gray-600'; ?>"></i>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($sesion['user_agent']); ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($sesion['location']); ?> • 
                                                <?php 
                                                $last_activity = strtotime($sesion['last_activity']);
                                                $diff = time() - $last_activity;
                                                if ($diff < 60) {
                                                    echo "Activa ahora";
                                                } elseif ($diff < 3600) {
                                                    echo "Hace " . floor($diff/60) . " minutos";
                                                } else {
                                                    echo "Hace " . floor($diff/3600) . " horas";
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if ($sesion['session_id'] !== session_id()): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="terminate_session">
                                            <input type="hidden" name="session_id" value="<?php echo $sesion['session_id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-700">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full">
                                            Sesión Actual
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($sesiones) > 1): ?>
                            <div class="mt-4">
                                <form method="POST">
                                    <input type="hidden" name="action" value="terminate_session">
                                    <input type="hidden" name="session_id" value="all">
                                    <button type="submit" class="text-red-600 hover:text-red-700 text-sm font-medium">
                                        <i class="fas fa-sign-out-alt mr-1"></i>
                                        Cerrar todas las otras sesiones
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .toggle-checkbox:checked {
            right: 0;
            border-color: #4F46E5;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #4F46E5;
        }
    </style>
</body>
</html> 