<?php
require_once('../../../../config/db.php');
require_once('../../../../vendor/autoload.php');

header('Content-Type: application/json');

try {
    // Obtener datos del POST
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $facturaId = $data['facturaId'] ?? '';

    if (empty($email) || empty($facturaId)) {
        throw new Exception('Datos incompletos');
    }

    $client = new \GuzzleHttp\Client();

    $response = $client->request('POST', "https://api.alegra.com/api/v1/invoices/{$facturaId}/email", [
        'headers' => [
            'accept' => 'application/json',
            'authorization' => 'Basic ' . base64_encode('johanrengifo78@gmail.com:f3c179c2337c190b3697'),
            'content-type' => 'application/json',
        ],
        'json' => [
            'email' => $email
        ]
    ]);

    $responseData = json_decode($response->getBody(), true);

    echo json_encode([
        'success' => true,
        'message' => 'Factura enviada correctamente'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 