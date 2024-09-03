<?php
session_start();
require_once '../db.php';
require_once './index.php';

function handlePostRequest() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar datos del formulario
        $requiredFields = ['username', 'name', 'email', 'phone', 'company_name', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $_SESSION['error_message'] = "El campo '$field' es obligatorio.";
                header('Location: ../../public/auth/register.php');
                exit();
            }
        }

        // Recoger y validar datos del formulario
        $userData = [
            'username' => htmlspecialchars($_POST['username']),
            'name' => htmlspecialchars($_POST['name']),
            'email' => filter_var($_POST['email'], FILTER_VALIDATE_EMAIL),
            'phone' => htmlspecialchars($_POST['phone']),
            'company_name' => htmlspecialchars($_POST['company_name']),
            'password' => password_hash($_POST['password'], PASSWORD_BCRYPT),
            'dbname' => preg_replace('/[^a-z0-9_]+/', '_', strtolower($_POST['company_name'])) . '_db'
        ];

        // Validar email
        if ($userData['email'] === false) {
            $_SESSION['error_message'] = "El correo electrónico no es válido.";
            header('Location: ../../public/auth/register.php');
            exit();
        }

        // Crear base de datos y su plantilla
        try {
            $dbname = $userData['dbname'];
            $db = createDatabase($dbname);
            importDatabaseTemplate($db);
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error al crear la base de datos o importar la plantilla: " . $e->getMessage();
            header('Location: ../../public/auth/register.php');
            exit();
        }

        // Registrar el usuario en la base de datos principal
        try {
            registerUser($userData);
            header('Location: ../../index.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error al registrar el usuario: " . $e->getMessage();
            header('Location: ../../public/auth/register.php');
            exit();
        }
    }
}

handlePostRequest();
?>