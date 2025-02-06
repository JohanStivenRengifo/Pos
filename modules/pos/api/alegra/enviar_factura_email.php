<?php
require_once '../../../../config/db.php';
require_once '../../../../vendor/autoload.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$facturaId = $data['facturaId'] ?? null;
$email = $data['email'] ?? null;
$asunto = $data['asunto'] ?? 'Factura Electrónica';

if (!$facturaId || !$email) {
    echo json_encode([
        'success' => false,
        'message' => 'Faltan datos requeridos'
    ]);
    exit;
}

try {
    $client = new \GuzzleHttp\Client();
    
    $response = $client->request('POST', "https://api.alegra.com/api/v1/invoices/{$facturaId}/email", [
        'headers' => [
            'accept' => 'application/json',
            'authorization' => 'Basic am9oYW5yZW5naWZvNzhAZ21haWwuY29tOmYzYzE3OWMzMjM3YzE5MGIzNjk3',
            'content-type' => 'application/json',
        ],
        'json' => [
            'emails' => [$email],
            'sendCopyToUser' => true,
            'invoiceType' => 'copy',
            'emailMessage' => [
                'subject' => $asunto
            ]
        ]
    ]);
    
    $resultado = json_decode($response->getBody(), true);
    
    echo json_encode([
        'success' => true,
        'message' => 'Factura enviada correctamente'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar la factura: ' . $e->getMessage()
    ]);
} 