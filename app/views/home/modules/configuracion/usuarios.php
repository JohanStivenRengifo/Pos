<?php
$pdo = getPDOConnection();

// Verifica si el usuario está autenticado 
if (!isset($_SESSION['user_id'])) {
    die("Acceso no autorizado.");
}

// Obtener usuarios
function getUsers($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM users");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Agregar un nuevo usuario
function addUser($pdo, $first_name, $last_name, $email, $password, $role) {
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$first_name, $last_name, $email, $passwordHash, $role]);
}

// Actualizar un usuario existente
function updateUser($pdo, $user_id, $first_name, $last_name, $email, $role) {
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
    return $stmt->execute([$first_name, $last_name, $email, $role, $user_id]);
}

// Eliminar un usuario
function deleteUser($pdo, $user_id) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([$user_id]);
}

// Procesar solicitudes
$message = ['type' => '', 'text' => '']; // Inicializa el array de mensajes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_user'])) {
            if (addUser($pdo, $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['password'], $_POST['role'])) {
                $message = ['type' => 'success', 'text' => 'Usuario agregado con éxito.'];
            } else {
                $message = ['type' => 'error', 'text' => 'Error al agregar usuario.'];
            }
        } elseif (isset($_POST['update_user'])) {
            if (updateUser($pdo, $_POST['user_id'], $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['role'])) {
                $message = ['type' => 'success', 'text' => 'Usuario actualizado con éxito.'];
            } else {
                $message = ['type' => 'error', 'text' => 'Error al actualizar usuario.'];
            }
        } elseif (isset($_POST['delete_user'])) {
            if (deleteUser($pdo, $_POST['user_id'])) {
                $message = ['type' => 'success', 'text' => 'Usuario eliminado con éxito.'];
            } else {
                $message = ['type' => 'error', 'text' => 'Error al eliminar usuario.'];
            }
        }
    } catch (Exception $e) {
        $message = ['type' => 'error', 'text' => 'Error en la operación: ' . htmlspecialchars($e->getMessage())];
    }
}

// Mostrar usuarios
$users = getUsers($pdo);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f4f4f4;
        }
        h1, h2 {
            color: #333;
        }
        form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="email"], input[type="password"], select {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="submit"] {
            padding: 10px 15px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: #fff;
        }
        .message {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            color: green;
            border: 1px solid #d4edda;
            background-color: #d4edda;
        }
        .error {
            color: red;
            border: 1px solid #f8d7da;
            background-color: #f8d7da;
        }
    </style>
</head>
<body>
    <h1>Gestión de Usuarios</h1>
    
    <!-- Mensajes de éxito o error -->
    <?php if (!empty($message['text'])): ?>
        <div class="message <?php echo htmlspecialchars($message['type']); ?>">
            <?php echo htmlspecialchars($message['text']); ?>
        </div>
    <?php endif; ?>

    <!-- Formulario para agregar un usuario -->
    <h2>Agregar Usuario</h2>
    <form action="" method="post">
        <label for="first_name">Nombre:</label>
        <input type="text" id="first_name" name="first_name" required>
        <label for="last_name">Apellido:</label>
        <input type="text" id="last_name" name="last_name" required>
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" required>
        <label for="role">Rol:</label>
        <select id="role" name="role" required>
            <option value="vendedor">Vendedor</option>
            <option value="contador">Contador</option>
            <option value="administrador">Administrador</option>
        </select>
        <input type="submit" name="add_user" value="Agregar Usuario">
    </form>
    
    <!-- Formulario para actualizar un usuario -->
    <h2>Actualizar Usuario</h2>
    <form action="" method="post">
        <label for="user_id">ID de Usuario:</label>
        <input type="text" id="user_id" name="user_id" required>
        <label for="first_name">Nombre:</label>
        <input type="text" id="first_name" name="first_name" required>
        <label for="last_name">Apellido:</label>
        <input type="text" id="last_name" name="last_name" required>
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <label for="role">Rol:</label>
        <select id="role" name="role" required>
            <option value="vendedor">Vendedor</option>
            <option value="contador">Contador</option>
            <option value="administrador">Administrador</option>
        </select>
        <input type="submit" name="update_user" value="Actualizar Usuario">
    </form>
    
    <!-- Formulario para eliminar un usuario -->
    <h2>Eliminar Usuario</h2>
    <form action="" method="post">
        <label for="user_id">ID de Usuario:</label>
        <input type="text" id="user_id" name="user_id" required>
        <input type="submit" name="delete_user" value="Eliminar Usuario">
    </form>
    
    <!-- Lista de usuarios -->
    <h2>Lista de Usuarios</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Fecha de Creación</th>
                <th>Última Actualización</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($user['updated_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>