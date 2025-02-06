<?php
require_once '../../../../config/db.php';
require_once '../../../../vendor/autoload.php';

header('Content-Type: application/json');

try {
    $client = new \GuzzleHttp\Client();
    
    $response = $client->request('GET', 'https://api.alegra.com/api/v1/invoices', [
        'headers' => [
            'accept' => 'application/json',
            'authorization' => 'Basic am9oYW5yZW5naWZvNzhAZ21haWwuY29tOmYzYzE3OWMzMjM3YzE5MGIzNjk3',
        ],
    ]);
    
    $facturas = json_decode($response->getBody(), true);
    
    echo json_encode([
        'success' => true,
        'facturas' => $facturas
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener las facturas: ' . $e->getMessage()
    ]);
} 