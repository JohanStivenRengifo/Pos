<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado mediante sesión o cookies
$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null;
$email = $_SESSION['email'] ?? $_COOKIE['email'] ?? null;

if (!$user_id || !$email) {
    header("Location: ../../index.php");
    exit();
}

// Función para obtener la información del usuario
function getUserInfo($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT email, nombre, empresa_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para obtener la información de la empresa
function getEmpresaInfo($empresa_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para crear o actualizar una empresa
function saveEmpresa($empresa_id, $nombre_empresa, $direccion, $telefono, $correo_contacto) {
    global $pdo;
    if ($empresa_id) {
        // Actualizar la empresa existente
        $stmt = $pdo->prepare("UPDATE empresas SET nombre_empresa = ?, direccion = ?, telefono = ?, correo_contacto = ? WHERE id = ?");
        return $stmt->execute([$nombre_empresa, $direccion, $telefono, $correo_contacto, $empresa_id]);
    } else {
        // Crear una nueva empresa
        $stmt = $pdo->prepare("INSERT INTO empresas (nombre_empresa, direccion, telefono, correo_contacto) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre_empresa, $direccion, $telefono, $correo_contacto]);
        return $pdo->lastInsertId(); // Devolver el ID de la empresa creada
    }
}

// Función para eliminar una empresa
function deleteEmpresa($empresa_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
    return $stmt->execute([$empresa_id]);
}

// Función para asociar una empresa a un usuario
function associateEmpresaToUser($user_id, $empresa_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET empresa_id = ? WHERE id = ?");
    return $stmt->execute([$empresa_id, $user_id]);
}

// Función para actualizar el email
function updateEmail($user_id, $new_email) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
    return $stmt->execute([$new_email, $user_id]);
}

// Función para actualizar la contraseña
function updatePassword($user_id, $new_password) {
    global $pdo;
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    return $stmt->execute([$hashed_password, $user_id]);
}

// Mensaje de estado
$message = '';

// Manejar el formulario de actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_email'])) {
        $new_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            if (updateEmail($user_id, $new_email)) {
                $message = "Correo electrónico actualizado correctamente.";
                $_SESSION['email'] = $new_email; // Actualizar la sesión
            } else {
                $message = "Error al actualizar el correo electrónico.";
            }
        } else {
            $message = "Correo electrónico no válido.";
        }
    } elseif (isset($_POST['update_password'])) {
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        if ($new_password === $confirm_password && strlen($new_password) >= 6) {
            if (updatePassword($user_id, $new_password)) {
                $message = "Contraseña actualizada correctamente.";
            } else {
                $message = "Error al actualizar la contraseña.";
            }
        } else {
            $message = "Las contraseñas no coinciden o son demasiado cortas (mínimo 6 caracteres).";
        }
    } elseif (isset($_POST['save_empresa'])) {
        // Guardar o actualizar la empresa
        $empresa_id = $_POST['empresa_id'] ?? null; // Obtener ID de la empresa si existe
        $nombre_empresa = trim($_POST['nombre_empresa']);
        $direccion = trim($_POST['direccion']);
        $telefono = trim($_POST['telefono']);
        $correo_contacto = trim($_POST['correo_contacto']);

        if (!empty($nombre_empresa) && !empty($direccion) && !empty($telefono) && filter_var($correo_contacto, FILTER_VALIDATE_EMAIL)) {
            if (saveEmpresa($empresa_id, $nombre_empresa, $direccion, $telefono, $correo_contacto)) {
                if (!$empresa_id) {
                    associateEmpresaToUser($user_id, $pdo->lastInsertId());
                }
                $message = "Empresa guardada correctamente.";
            } else {
                $message = "Error al guardar la empresa.";
            }
        } else {
            $message = "Complete correctamente todos los campos de la empresa.";
        }
    } elseif (isset($_POST['delete_empresa'])) {
        // Eliminar la empresa
        $empresa_id = $_POST['empresa_id'];
        // Desasociar empresa del usuario
        associateEmpresaToUser($user_id, null);
        if (deleteEmpresa($empresa_id)) {
            $message = "Empresa eliminada correctamente.";
        } else {
            $message = "Error al eliminar la empresa.";
        }
    }
}

// Obtener la información actual del usuario
$user_info = getUserInfo($user_id);

// Obtener la información de la empresa si está asociada
$empresa_info = null;
if ($user_info['empresa_id']) {
    $empresa_info = getEmpresaInfo($user_info['empresa_id']);
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Cuenta</title>
    <link rel="stylesheet" href="../../css/modulos.css">
</head>
<body>

<div class="sidebar">
    <h2>Menú Principal</h2>
    <ul>
        <li><a href="../../welcome.php">Inicio</a></li>
        <li><a href="../../modules/ventas/index.php">Ventas</a></li>
        <li><a href="../../modules/reportes/index.php">Reportes</a></li>
        <li><a href="../../modules/ingresos/index.php">Ingresos</a></li>
        <li><a href="../../modules/egresos/index.php">Egresos</a></li>
        <li><a href="../../modules/inventario/index.php">Productos</a></li>
        <li><a href="../../modules/clientes/index.php">Clientes</a></li>
        <li><a href="../../modules/proveedores/index.php">Proveedores</a></li>
        <li><a href="../../modules/config/index.php">Configuración</a></li>
        <form method="POST" action="">
            <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
        </form>
    </ul>
</div>

<div class="main-content">
    <h2>Configuración de la Cuenta</h2>

    <?php if (!empty($message)): ?>
        <div class="message"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="form-container">
        <h3>Actualizar Correo Electrónico</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Correo Electrónico Actual:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_info['email']); ?>" required>
            </div>
            <button type="submit" name="update_email" class="btn btn-primary">Actualizar Correo</button>
        </form>
    </div>

    <div class="form-container">
        <h3>Cambiar Contraseña</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label for="new_password">Nueva Contraseña:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmar Nueva Contraseña:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" name="update_password" class="btn btn-primary">Actualizar Contraseña</button>
        </form>
    </div>

    <div class="form-container">
        <h3>Información de la Empresa</h3>

        <?php if ($empresa_info): ?>
            <form method="POST" action="">
                <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa_info['id']); ?>">
                <div class="form-group">
                    <label for="nombre_empresa">Nombre de la Empresa:</label>
                    <input type="text" id="nombre_empresa" name="nombre_empresa" value="<?= htmlspecialchars($empresa_info['nombre_empresa']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="direccion">Dirección:</label>
                    <input type="text" id="direccion" name="direccion" value="<?= htmlspecialchars($empresa_info['direccion']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono:</label>
                    <input type="text" id="telefono" name="telefono" value="<?= htmlspecialchars($empresa_info['telefono']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="correo_contacto">Correo de Contacto:</label>
                    <input type="email" id="correo_contacto" name="correo_contacto" value="<?= htmlspecialchars($empresa_info['correo_contacto']); ?>" required>
                </div>
                <button type="submit" name="save_empresa" class="btn btn-primary">Actualizar Empresa</button>
            </form>
            <form method="POST" action="" style="margin-top: 20px;">
                <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa_info['id']); ?>">
                <button type="submit" name="delete_empresa" class="btn btn-danger">Eliminar Empresa</button>
            </form>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="empresa_id" value="">
                <div class="form-group">
                    <label for="nombre_empresa">Nombre de la Empresa:</label>
                    <input type="text" id="nombre_empresa" name="nombre_empresa" required>
                </div>
                <div class="form-group">
                    <label for="direccion">Dirección:</label>
                    <input type="text" id="direccion" name="direccion" required>
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono:</label>
                    <input type="text" id="telefono" name="telefono" required>
                </div>
                <div class="form-group">
                    <label for="correo_contacto">Correo de Contacto:</label>
                    <input type="email" id="correo_contacto" name="correo_contacto" required>
                </div>
                <button type="submit" name="save_empresa" class="btn btn-primary">Crear Empresa</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>