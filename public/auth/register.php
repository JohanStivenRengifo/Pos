<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $company_name = strtolower($_POST['company_name']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Crear nombre de la base de datos
    $dbname = preg_replace('/[^a-z0-9_]+/', '_', $company_name) . '_db';

    // Conectar a MySQL
    $db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD);

    // Verificar la conexión
    if ($db->connect_error) {
        die("Error de conexión: " . $db->connect_error);
    }

    // Crear la base de datos
    if ($db->query("CREATE DATABASE $dbname") === TRUE) {
        $db->select_db($dbname);

        // Importar la plantilla de base de datos
        $sql = file_get_contents('../../templates/db_template.sql');
        if ($db->multi_query($sql)) {
            do {
                if ($result = $db->store_result()) {
                    $result->free();
                }
            } while ($db->more_results() && $db->next_result());
        } else {
            die("Error al crear las tablas: " . $db->error);
        }

        // Registrar el usuario en la base de datos principal
        $db->select_db(MAIN_DB);
        $stmt = $db->prepare("INSERT INTO users (name, email, phone, username, password, dbname) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('ssssss', $name, $email, $phone, $company_name, $password, $dbname);
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;

                // Registrar la sesión activa
                session_start();
                $session_id = session_id();
                $stmt = $db->prepare("INSERT INTO sessions (user_id, session_id) VALUES (?, ?)");
                if ($stmt) {
                    $stmt->bind_param('is', $user_id, $session_id);
                    $stmt->execute();
                    $stmt->close();
                }

                // Registrar el log de acceso
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $action = "Registro de nuevo usuario y creación de base de datos";
                $stmt = $db->prepare("INSERT INTO access_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('iss', $user_id, $action, $ip_address);
                    $stmt->execute();
                    $stmt->close();
                }

                // Redirigir al login
                header('Location: login.php');
                exit();
            } else {
                die("Error al registrar el usuario: " . $stmt->error);
            }
            $stmt->close();
        } else {
            die("Error al preparar la consulta: " . $db->error);
        }
    } else {
        die("Error al crear la base de datos: " . $db->error);
    }

    // Cerrar la conexión
    $db->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>
    <link rel="stylesheet" href="../../src/css/registro.css">
    <script defer src="../../src/js/theme-toggle.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <h1>Crear una Cuenta</h1>
            <button id="theme-toggle" class="theme-toggle">🌙</button>
        </header>
        <div class="form-container">
            <form action="register.php" method="post">
                <div class="form-group">
                    <label for="name">Nombre Completo:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Teléfono:</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="company_name">Nombre de la Empresa:</label>
                    <input type="text" id="company_name" name="company_name" required>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="submit-btn">Registrarse</button>
            </form>
        </div>
    </div>
</body>
</html>