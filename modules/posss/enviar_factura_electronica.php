<?php
require_once '../../config/db.php';
require_once '../../config/api_config.php';

header('Content-Type: application/json');

function obtenerTokenFactus() {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => FACTUS_API_URL . '/oauth/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => FACTUS_CLIENT_ID,
            'client_secret' => FACTUS_CLIENT_SECRET,
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false // Solo para desarrollo
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    if ($err) {
        error_log("Error al obtener token Factus: " . $err);
        throw new Exception("Error al obtener token: " . $err);
    }

    $result = json_decode($response, true);
    
    if (!isset($result['access_token'])) {
        error_log("Respuesta inválida de token Factus: " . print_r($result, true));
        throw new Exception('Token no recibido de Factus');
    }

    return $result['access_token'];
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $venta_id = $data['venta_id'] ?? null;

    if (!$venta_id) {
        throw new Exception('ID de venta no proporcionado');
    }

    // Obtener datos de la venta y productos
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            c.identificacion,
            c.nombre AS cliente_nombre,
            c.email,
            c.telefono,
            c.direccion,
            p.nombre AS producto_nombre,
            p.codigo_barras,
            vd.cantidad,
            vd.precio_unitario
        FROM ventas v 
        JOIN clientes c ON v.cliente_id = c.id 
        JOIN venta_detalles vd ON v.id = vd.venta_id 
        JOIN productos p ON vd.producto_id = p.id
        WHERE v.id = ? AND v.numeracion_tipo = 'electronica'
    ");
    
    $stmt->execute([$venta_id]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($resultados)) {
        throw new Exception('Venta no encontrada o no es de tipo electrónica');
    }

    // Organizar datos de la venta
    $venta = $resultados[0];
    $items = array_map(function($item) {
        return [
            'code' => $item['codigo_barras'],
            'description' => $item['producto_nombre'],
            'price' => floatval($item['precio_unitario']),
            'quantity' => intval($item['cantidad']),
            'total' => floatval($item['precio_unitario']) * intval($item['cantidad']),
            'unit_measure' => 'UND', // Unidad de medida estándar
            'type_item' => '01' // Código para productos según Factus
        ];
    }, $resultados);

    // Obtener token de Factus
    $token = obtenerTokenFactus();

    // Preparar datos para Factus según su documentación
    $facturaData = [
        'type_document' => '01', // Factura de venta
        'type_operation' => '10', // Estándar
        'prefix' => substr($venta['numero_factura'], 0, 4),
        'number' => substr($venta['numero_factura'], 4),
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'customer' => [
            'type_document_identification' => '13', // CC por defecto
            'identification_number' => $venta['identificacion'],
            'name' => $venta['cliente_nombre'],
            'email' => $venta['email'],
            'phone' => $venta['telefono'],
            'address' => $venta['direccion']
        ],
        'items' => $items,
        'legal_monetary_totals' => [
            'line_extension_amount' => floatval($venta['subtotal']),
            'tax_exclusive_amount' => floatval($venta['subtotal']),
            'tax_inclusive_amount' => floatval($venta['total']),
            'payable_amount' => floatval($venta['total'])
        ],
        'payment_form' => [
            'payment_form_id' => '1', // Contado
            'payment_method_id' => '10', // Efectivo por defecto
            'payment_due_date' => date('Y-m-d'),
            'duration_measure' => '0'
        ]
    ];

    // Log de datos a enviar
    error_log("Datos a enviar a Factus: " . json_encode($facturaData));

    // Enviar a Factus
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => FACTUS_API_URL . '/api/v2/invoices',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($facturaData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false // Solo para desarrollo
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);

    if ($err) {
        error_log("Error CURL al enviar factura: " . $err);
        throw new Exception("Error al enviar factura: " . $err);
    }

    $result = json_decode($response, true);
    
    // Log de respuesta
    error_log("Respuesta de Factus: " . print_r($result, true));

    if ($httpCode >= 400) {
        throw new Exception("Error de API Factus: " . ($result['message'] ?? 'Error desconocido'));
    }

    if (!isset($result['id'])) {
        throw new Exception('Respuesta inválida de Factus');
    }

    // Después de recibir la respuesta de Factus
    if ($err) {
        error_log("Error CURL al enviar factura: " . $err);
        throw new Exception("Error al enviar factura: " . $err);
    }

    $result = json_decode($response, true);
    error_log("Respuesta de Factus: " . print_r($result, true));

    // Antes de actualizar la base de datos
    error_log("Intentando actualizar venta con ID: " . $venta_id);
    error_log("Datos a actualizar: " . print_r([
        'factura_electronica_id' => $result['id'],
        'estado_factura' => $result['status'] ?? 'ENVIADA',
        'numeracion' => $result['number'] ?? null
    ], true));

    $stmt = $pdo->prepare("
        UPDATE ventas 
        SET factura_electronica_id = :factura_id,
            estado_factura = :estado,
            numeracion = :numeracion,
            fecha_envio_dian = CURRENT_TIMESTAMP
        WHERE id = :venta_id
    ");

    $updateResult = $stmt->execute([
        ':factura_id' => $result['id'],
        ':estado' => $result['status'] ?? 'ENVIADA',
        ':numeracion' => $result['number'] ?? null,
        ':venta_id' => $venta_id
    ]);

    if (!$updateResult) {
        error_log("Error al actualizar la venta: " . print_r($stmt->errorInfo(), true));
        throw new Exception('Error al actualizar el estado de la factura en la base de datos');
    }

    $rowsAffected = $stmt->rowCount();
    error_log("Filas actualizadas: " . $rowsAffected);

    echo json_encode([
        'success' => true,
        'message' => 'Factura electrónica enviada correctamente',
        'factura_id' => $result['id'],
        'numero_factura' => $result['number'] ?? null,
        'estado' => $result['status'] ?? 'ENVIADA',
        'fecha_envio' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Error en enviar_factura_electronica.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
} 