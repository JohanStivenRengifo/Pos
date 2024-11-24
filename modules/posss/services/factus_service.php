<?php
require_once __DIR__ . '/../../../config/api_config.php';

class FactusService {
    private $access_token;
    private $api_url;

    public function __construct() {
        $this->api_url = FACTUS_API_URL;
        error_log("Iniciando FactusService - Obteniendo token...");
        $this->getAccessToken();
    }

    private function getAccessToken() {
        $curl = curl_init();
        
        $postFields = [
            'grant_type' => 'client_credentials',
            'client_id' => FACTUS_CLIENT_ID,
            'client_secret' => FACTUS_CLIENT_SECRET,
        ];
        
        error_log("Solicitando token con datos: " . json_encode($postFields));
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_url . '/oauth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => FACTUS_API_TIMEOUT,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => false, // Solo para desarrollo
            CURLOPT_VERBOSE => true
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        
        error_log("Respuesta token [HTTP $httpCode]: " . $response);
        if ($err) error_log("Error curl: " . $err);
        
        curl_close($curl);

        if ($err) {
            throw new Exception("Error al obtener token: " . $err);
        }

        $result = json_decode($response, true);
        if (!isset($result['access_token'])) {
            throw new Exception("Error al obtener token de acceso: " . json_encode($result));
        }

        $this->access_token = $result['access_token'];
        error_log("Token obtenido exitosamente");
    }

    public function enviarFactura($datos_venta) {
        error_log("Iniciando envío de factura electrónica...");
        $curl = curl_init();

        $payload = $this->prepararDatosFactura($datos_venta);
        error_log("Payload preparado para Factus: " . json_encode($payload, JSON_PRETTY_PRINT));

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_url . '/v1/bills/validate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => FACTUS_API_TIMEOUT,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->access_token,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false, // Solo para desarrollo
            CURLOPT_VERBOSE => true
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        
        error_log("Respuesta Factus [HTTP $httpCode]: " . $response);
        if ($err) error_log("Error curl: " . $err);
        
        curl_close($curl);

        if ($err) {
            throw new Exception("Error al enviar factura: " . $err);
        }

        $responseData = json_decode($response, true);
        
        // Verificar si hay errores en la respuesta
        if ($httpCode !== 201 || isset($responseData['error'])) {
            $errorMsg = isset($responseData['error']) ? 
                       $responseData['error_description'] : 
                       'Error desconocido al procesar la factura';
            throw new Exception("Error en la API de Factus: " . $errorMsg);
        }

        error_log("Factura enviada exitosamente. Respuesta: " . json_encode($responseData['data'] ?? $responseData));
        return $responseData;
    }

    private function prepararDatosFactura($datos_venta) {
        // Validar datos requeridos
        if (empty($datos_venta['cliente']['documento'])) {
            throw new Exception("El documento del cliente es requerido");
        }

        $datosPreparados = [
            "numbering_range_id" => 4,
            "reference_code" => $datos_venta['numero'],
            "observation" => $datos_venta['observacion'] ?? "",
            "payment_method_code" => $this->mapearMetodoPago($datos_venta['metodo_pago']),
            "customer" => [
                "identification" => $datos_venta['cliente']['documento'],
                "dv" => $datos_venta['cliente']['dv'] ?? "",
                "company" => $datos_venta['cliente']['es_empresa'] ? $datos_venta['cliente']['nombre'] : "",
                "trade_name" => $datos_venta['cliente']['nombre_comercial'] ?? "",
                "names" => !$datos_venta['cliente']['es_empresa'] ? $datos_venta['cliente']['nombre'] : "",
                "address" => $datos_venta['cliente']['direccion'],
                "email" => $datos_venta['cliente']['email'],
                "phone" => $datos_venta['cliente']['telefono'],
                "legal_organization_id" => $datos_venta['cliente']['es_empresa'] ? "1" : "2",
                "tribute_id" => "21",
                "identification_document_id" => $this->mapearTipoDocumento($datos_venta['cliente']['tipo_documento']),
                "municipality_id" => $datos_venta['cliente']['municipio_id'] ?? "980"
            ],
            "items" => array_map(function($item) {
                return [
                    "code_reference" => $item['codigo'],
                    "name" => $item['nombre'],
                    "quantity" => (int)$item['cantidad'],
                    "discount" => floatval($item['descuento'] ?? 0),
                    "discount_rate" => floatval($item['tasa_descuento'] ?? 0),
                    "price" => floatval($item['precio']),
                    "tax_rate" => $item['tasa_impuesto'] ?? "19.00",
                    "unit_measure_id" => 70,
                    "standard_code_id" => 1,
                    "is_excluded" => (int)($item['excluido_impuesto'] ?? 0),
                    "tribute_id" => 1,
                    "withholding_taxes" => []
                ];
            }, $datos_venta['productos'])
        ];

        error_log("Datos preparados para Factus: " . json_encode($datosPreparados, JSON_PRETTY_PRINT));
        return $datosPreparados;
    }

    private function mapearMetodoPago($metodo_interno) {
        $mapeo = [
            'efectivo' => '10',
            'tarjeta_debito' => '49',
            'tarjeta_credito' => '48',
            'transferencia' => '47',
            'cheque' => '20',
            'credito' => '1'
        ];
        return $mapeo[$metodo_interno] ?? '10';
    }

    private function mapearTipoDocumento($tipo_documento) {
        $mapeo = [
            'CC' => '3',  // Cédula ciudadanía
            'NIT' => '6', // NIT
            'CE' => '5',  // Cédula extranjería
            'PP' => '7',  // Pasaporte
            'TI' => '2',  // Tarjeta identidad
            'RC' => '1'   // Registro civil
        ];
        return $mapeo[$tipo_documento] ?? '3';
    }
}