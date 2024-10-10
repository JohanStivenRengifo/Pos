<?php
session_start();
require_once '../../config/db.php';

$user_id = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null;
$email = $_SESSION['email'] ?? $_COOKIE['email'] ?? null;

if (!$user_id || !$email) {
    header("Location: ../../index.php");
    exit();
}

function getUserInfo($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT email, nombre, empresa_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getEmpresaInfo($empresa_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveEmpresa($empresa_id, $nombre_empresa, $direccion, $telefono, $correo_contacto, $prefijo_factura, $numero_inicial, $numero_final) {
    global $pdo;
    if ($empresa_id) {
        $stmt = $pdo->prepare("UPDATE empresas SET nombre_empresa = ?, direccion = ?, telefono = ?, correo_contacto = ?, prefijo_factura = ?, numero_inicial = ?, numero_final = ? WHERE id = ?");
        return $stmt->execute([$nombre_empresa, $direccion, $telefono, $correo_contacto, $prefijo_factura, $numero_inicial, $numero_final, $empresa_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO empresas (nombre_empresa, direccion, telefono, correo_contacto, prefijo_factura, numero_inicial, numero_final) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre_empresa, $direccion, $telefono, $correo_contacto, $prefijo_factura, $numero_inicial, $numero_final]);
        return $pdo->lastInsertId();
    }
}

function deleteEmpresa($empresa_id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
    return $stmt->execute([$empresa_id]);
}

function associateEmpresaToUser($user_id, $empresa_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET empresa_id = ? WHERE id = ?");
    return $stmt->execute([$empresa_id, $user_id]);
}

function updateEmail($user_id, $new_email) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
    return $stmt->execute([$new_email, $user_id]);
}

function updatePassword($user_id, $new_password) {
    global $pdo;
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    return $stmt->execute([$hashed_password, $user_id]);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_email'])) {
        $new_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            if (updateEmail($user_id, $new_email)) {
                $message = "Correo electrónico actualizado correctamente.";
                $_SESSION['email'] = $new_email;
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
        $empresa_id = $_POST['empresa_id'] ?? null;
        $nombre_empresa = trim($_POST['nombre_empresa']);
        $direccion = trim($_POST['direccion']);
        $telefono = trim($_POST['telefono']);
        $correo_contacto = trim($_POST['correo_contacto']);
        $prefijo_factura = trim($_POST['prefijo_factura']);
        $numero_inicial = (int)$_POST['numero_inicial'];
        $numero_final = (int)$_POST['numero_final'];

        if (!empty($nombre_empresa) && !empty($direccion) && !empty($telefono) && filter_var($correo_contacto, FILTER_VALIDATE_EMAIL) && !empty($prefijo_factura) && $numero_inicial > 0 && $numero_final > $numero_inicial) {
            $new_empresa_id = saveEmpresa($empresa_id, $nombre_empresa, $direccion, $telefono, $correo_contacto, $prefijo_factura, $numero_inicial, $numero_final);
            if ($new_empresa_id) {
                associateEmpresaToUser($user_id, $new_empresa_id);
                $message = "Empresa guardada correctamente.";
            } else {
                $message = "Error al guardar la empresa.";
            }
        } else {
            $message = "Complete correctamente todos los campos de la empresa. Asegúrese de que el número inicial sea mayor que cero y el número final sea mayor que el inicial.";
        }
    } elseif (isset($_POST['delete_empresa'])) {
        $empresa_id = $_POST['empresa_id'];
        associateEmpresaToUser($user_id, null);
        if (deleteEmpresa($empresa_id)) {
            $message = "Empresa eliminada correctamente.";
        } else {
            $message = "Error al eliminar la empresa.";
        }
    }
}

$user_info = getUserInfo($user_id);
$empresa_info = $user_info['empresa_id'] ? getEmpresaInfo($user_info['empresa_id']) : null;

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
                <!-- Formulario para editar la empresa existente -->
                <form method="POST" action="">
                    <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa_info['id']); ?>">
                    <div class="form-group">
                        <label for="nombre_empresa">Nombre de la Empresa:</label>
                        <input type="text" id="nombre_empresa" name="nombre_empresa" value="<?= htmlspecialchars($empresa_info['nombre_empresa'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección:</label>
                        <input type="text" id="direccion" name="direccion" value="<?= htmlspecialchars($empresa_info['direccion'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="text" id="telefono" name="telefono" value="<?= htmlspecialchars($empresa_info['telefono'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="correo_contacto">Correo de Contacto:</label>
                        <input type="email" id="correo_contacto" name="correo_contacto" value="<?= htmlspecialchars($empresa_info['correo_contacto'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="prefijo_factura">Prefijo de Factura:</label>
                        <input type="text" id="prefijo_factura" name="prefijo_factura" value="<?= htmlspecialchars($empresa_info['prefijo_factura'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="numero_inicial">Número Inicial de Factura:</label>
                        <input type="number" id="numero_inicial" name="numero_inicial" value="<?= htmlspecialchars($empresa_info['numero_inicial'] ?? ''); ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label for="numero_final">Número Final de Factura:</label>
                        <input type="number" id="numero_final" name="numero_final" value="<?= htmlspecialchars($empresa_info['numero_final'] ?? ''); ?>" required min="1">
                    </div>
                    <button type="submit" name="save_empresa" class="btn btn-primary">Actualizar Empresa</button>
                    <button type="submit" name="delete_empresa" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar esta empresa?');">Eliminar Empresa</button>
                </form>
            <?php else: ?>
                <!-- Formulario para crear una nueva empresa -->
                <form method="POST" action="">
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
                    <div class="form-group">
                        <label for="prefijo_factura">Prefijo de Factura:</label>
                        <input type="text" id="prefijo_factura" name="prefijo_factura" required>
                    </div>
                    <div class="form-group">
                        <label for="numero_inicial">Número Inicial de Factura:</label>
                        <input type="number" id="numero_inicial" name="numero_inicial" required min="1">
                    </div>
                    <div class="form-group">
                        <label for="numero_final">Número Final de Factura:</label>
                        <input type="number" id="numero_final" name="numero_final" required min="1">
                    </div>
                    <button type="submit" name="save_empresa" class="btn btn-primary">Crear Empresa</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>