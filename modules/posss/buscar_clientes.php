<?php
session_start();
require_once '../../config/db.php';
require_once './functions.php';

if (!isset($_SESSION['user_id'])) {
    exit(json_encode([]));
}

$user_id = $_SESSION['user_id'];
$query = $_GET['query'] ?? '';

$clientes = buscarClientes($pdo, $user_id, $query);
echo json_encode($clientes);
