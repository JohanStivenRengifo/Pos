<?php
session_start();
require_once '../../config/db.php';
require_once './functions.php';

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	exit(json_encode(['error' => 'No autorizado']));
}

$user_id = $_SESSION['user_id'];
$nombre = $_POST['nombre'] ?? '';
$documento = $_POST['documento'] ?? '';
$email = $_POST['email'] ?? '';

if (empty($nombre) || empty($documento)) {
	http_response_code(400);
	exit(json_encode(['error' => 'Faltan datos requeridos']));
}

$cliente = guardarCliente($pdo, $user_id, $nombre, $documento, $email);

if ($cliente) {
	echo json_encode($cliente);
} else {
	http_response_code(500);
	exit(json_encode(['error' => 'No se pudo guardar el cliente']));
}
