<?php
require_once '../../../../config/db.php';
require_once '../../../../vendor/autoload.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$facturaId = $data['facturaId'] ?? null;

if (!$facturaId) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de factura no proporcionado'
    ]);
    exit;
}

try {
    $client = new \GuzzleHttp\Client();
    
    $response = $client->request('GET', "https://api.alegra.com/api/v1/invoices/{$facturaId}", [
        'headers' => [
            'accept' => 'application/json',
            'authorization' => 'Basic am9oYW5yZW5naWZvNzhAZ21haWwuY29tOmYzYzE3OWMzMjM3YzE5MGIzNjk3',
        ],
    ]);
    
    $factura = json_decode($response->getBody(), true);
    
    echo json_encode([
        'success' => true,
        'factura' => $factura
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener la factura: ' . $e->getMessage()
    ]);
} 