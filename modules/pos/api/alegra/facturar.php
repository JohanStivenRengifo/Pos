<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../config/alegra.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);

    if (!$data) {
        throw new Exception('Datos inválidos');
    }

    // Configurar la petición a Alegra
    $ch = curl_init('https://api.alegra.com/api/v1/invoices');
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(ALEGRA_API_KEY . ':' . ALEGRA_API_TOKEN)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Error al comunicarse con Alegra: ' . $response);
    }

    $responseData = json_decode($response, true);
    
    echo json_encode([
        'success' => true,
        'id' => $responseData['id'],
        'numero' => $responseData['numberTemplate']['number']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 