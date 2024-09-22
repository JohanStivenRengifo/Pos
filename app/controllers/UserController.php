<?php
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../models/User.php';

class UserController
{
    private $pdo;
    private $userModel;

    public function __construct()
    {
        $this->pdo = getPDOConnection();
        $this->userModel = new User($this->pdo);
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = htmlspecialchars(trim($_POST['email']));
            $password = $_POST['password'];

            $user = $this->userModel->authenticate($email, $password);
            if ($user) {
                // Iniciar sesión
                session_start();
                $_SESSION['user_id'] = $user['id'];
                setcookie('user_id', $user['id'], time() + (86400 * 30), "/");
                header("Location: app/views/home/index.php");
                exit;
            } else {
                $this->renderLoginView("Usuario o contraseña incorrectos.");
            }
        } else {
            $this->renderLoginView();
        }
    }

    public function register()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = htmlspecialchars(trim($_POST['name']));
        $email = htmlspecialchars(trim($_POST['email']));
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Contraseñas coincidan
        if ($password !== $confirm_password) {
            $this->renderRegisterView("Las contraseñas no coinciden.");
            return;
        }

        // Email y contraseña
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->renderRegisterView("Email inválido.");
            return;
        }

        if (strlen($password) < 8) {
            $this->renderRegisterView("La contraseña debe tener al menos 8 caracteres.");
            return;
        }

        // Llama al método createUser del modelo
        if ($this->userModel->createUser($name, $email, $password)) {
            header("Location: views/auth/login.php");
            exit;
        } else {
            $this->renderRegisterView("Error al crear usuario.");
        }
    } else {
        $this->renderRegisterView();
    }
}


    public function logout()
    {
        session_start();
        session_destroy();
        setcookie('user_id', '', time() - 3600, "/");
        header("Location: views/auth/login.php");
        exit;
    }

    public function dashboard()
    {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            header("Location: app/views/auth/login.php");
            exit;
        }

        require_once __DIR__ . '/../app/views/home/index.php';
    }

    private function renderLoginView($error = null)
    {
        require_once __DIR__ . '/../views/auth/login.php';
    }

    private function renderRegisterView($error = null)
    {
        require_once __DIR__ . '/../views/auth/register.php';
    }

    public function handleRequest($action)
    {
        switch ($action) {
            case 'login':
                $this->login();
                break;
            case 'register':
                $this->register();
                break;
            case 'logout':
                $this->logout();
                break;
            default:
                $this->dashboard();
        }
    }
}