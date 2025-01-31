<?php
require_once(__DIR__ . '/../../../vendor/autoload.php');

class AlegraIntegration {
    private $client;
    private $credentials;
    private $taxes = null;

    public function __construct() {
        $this->credentials = base64_encode('johanrengifo78@gmail.com:f3c179c3237c190b3697');
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => 'https://api.alegra.com/api/v1/',
            'headers' => [
                'Authorization' => 'Basic ' . $this->credentials,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    private function getTaxes() {
        if ($this->taxes === null) {
            try {
                $response = $this->client->request('GET', 'taxes');
                $this->taxes = json_decode($response->getBody()->getContents(), true);
            } catch (\Exception $e) {
                error_log("Error obteniendo impuestos de Alegra: " . $e->getMessage());
                $this->taxes = [];
            }
        }
        return $this->taxes;
    }

    private function getDefaultTax() {
        $taxes = $this->getTaxes();
        // Buscar el impuesto por nombre o porcentaje
        foreach ($taxes as $tax) {
            // Puedes ajustar esta lógica según tus necesidades
            if ($tax['percentage'] == 19) { // Para IVA 19%
                return $tax['id'];
            }
        }
        // Si no encuentra el impuesto específico, retornar null
        return null;
    }

    public function findContactByIdentification($identification) {
        try {
            $response = $this->client->request('GET', 'contacts', [
                'query' => ['identification' => $identification]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            // Si encontramos contactos, retornar el primero
            if (!empty($result)) {
                return [
                    'success' => true,
                    'data' => $result[0]
                ];
            }

            return [
                'success' => false,
                'error' => 'Contacto no encontrado'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function createContact($clientData) {
        try {
            // Primero buscar si el contacto ya existe
            $existingContact = $this->findContactByIdentification($clientData['documento']);
            
            if ($existingContact['success']) {
                return $existingContact;
            }

            // Preparar el nombre del cliente en formato objeto
            $nombres = explode(' ', $clientData['nombre']);
            $nameObject = [
                'firstName' => $nombres[0] ?? '',
                'secondName' => $nombres[1] ?? '',
                'lastName' => $nombres[2] ?? '',
                'secondLastName' => $nombres[3] ?? ''
            ];

            // Configurar el payload base
            $payload = [
                'name' => $clientData['nombre'],
                'identification' => $clientData['documento'],
                'nameObject' => $nameObject,
                'identificationObject' => [
                    'type' => $this->getIdentificationType($clientData['tipo_persona'], $clientData['documento']),
                    'number' => $clientData['documento']
                ],
                'type' => ['client'],
                'settings' => [
                    'sendElectronicDocuments' => true
                ],
                'kindOfPerson' => $this->mapPersonType($clientData['tipo_persona']),
                'regime' => 'SIMPLIFIED_REGIME',  // Forzar régimen simplificado
                'address' => [
                    'address' => $clientData['direccion'] ?? 'COLOMBIA',
                    'city' => 'BOGOTÁ',
                    'department' => 'BOGOTÁ',
                    'country' => 'Colombia'
                ]
            ];

            // Si no es consumidor final, agregar datos adicionales
            if ($clientData['documento'] !== '222222222222') {
                $payload = array_merge($payload, [
                    'email' => $clientData['email'] ?? '',
                    'phonePrimary' => $clientData['telefono'] ?? '',
                    'mobile' => $clientData['celular'] ?? '',
                    'term' => [
                        'id' => '1',
                        'name' => 'De contado',
                        'days' => '0'
                    ]
                ]);
            }

            $response = $this->client->request('POST', 'contacts', [
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            return [
                'success' => true,
                'data' => $result
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getIdentificationType($tipoPersona, $identificacion) {
        // Si es consumidor final
        if ($identificacion === '222222222222') {
            return 'CC';
        }

        // Mapeo según documentación de Alegra Colombia
        $typeMap = [
            'juridica' => 'NIT',
            'natural' => 'CC',
            'extranjero' => 'CE'
        ];

        return $typeMap[$tipoPersona] ?? 'CC';
    }

    private function mapPersonType($tipoPersona) {
        // Mapeo según documentación de Alegra Colombia
        $typeMap = [
            'juridica' => 'LEGAL_ENTITY',
            'natural' => 'PERSON_ENTITY'
        ];

        return $typeMap[$tipoPersona] ?? 'PERSON_ENTITY';
    }

    private function mapRegime($responsabilidad) {
        // Mapeo según documentación de Alegra Colombia
        $regimeMap = [
            'iva' => 'COMMON_REGIME',
            'no_iva' => 'SIMPLIFIED_REGIME',
            'regimen_simple' => 'SIMPLIFIED_REGIME',
            'gran_contribuyente' => 'COMMON_REGIME'
        ];

        return $regimeMap[$responsabilidad] ?? 'SIMPLIFIED_REGIME';
    }

    public function findOrCreateItem($item) {
        try {
            // Primero buscar si el item existe
            $existingItem = $this->findItemByName($item['nombre']);
            
            if ($existingItem['success']) {
                return $existingItem;
            }

            // Si no existe, crear nuevo item
            $payload = [
                'name' => $item['nombre'],
                'price' => floatval($item['precio']),
                'reference' => $item['codigo_barras'] ?? '',
                'description' => $item['nombre'],
                'inventory' => [
                    'unit' => 'unit',
                    'available' => intval($item['cantidad'])
                ],
                'itemType' => 'PRODUCT'
            ];

            // Agregar campos opcionales si están disponibles
            if (!empty($item['codigo_barras'])) {
                $payload['reference'] = $item['codigo_barras'];
            }

            if (!empty($item['categoria'])) {
                $payload['category'] = [
                    'id' => 1
                ];
            }

            $response = $this->client->request('POST', 'items', [
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            // Guardar el ID de Alegra en tu base de datos
            if (isset($result['id'])) {
                $this->saveAlegraItemId($item['id'], $result['id']);
            }

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function saveAlegraItemId($localId, $alegraId) {
        try {
            global $pdo;
            $stmt = $pdo->prepare("
                UPDATE inventario 
                SET alegra_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$alegraId, $localId]);
        } catch (\Exception $e) {
            // Log el error pero no interrumpir el flujo
            error_log("Error al guardar alegra_id para item: " . $e->getMessage());
        }
    }

    public function findItemByName($name) {
        try {
            $response = $this->client->request('GET', 'items', [
                'query' => ['query' => $name]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            // Si encontramos items, retornar el primero que coincida exactamente
            if (!empty($result)) {
                foreach ($result as $item) {
                    if (strtolower($item['name']) === strtolower($name)) {
                        return [
                            'success' => true,
                            'data' => $item
                        ];
                    }
                }
            }

            return [
                'success' => false,
                'error' => 'Item no encontrado'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getInvoiceDetails($invoiceId) {
        try {
            // Intentar obtener el PDF usando el endpoint específico de descarga
            try {
                $downloadResponse = $this->client->request('GET', "invoices/{$invoiceId}/pdf/download");
                
                // Si la respuesta es exitosa, la URL estará en los headers
                $headers = $downloadResponse->getHeaders();
                if (isset($headers['Location'][0])) {
                    return [
                        'success' => true,
                        'data' => [
                            'pdf_url' => $headers['Location'][0],
                            'cufe' => null,
                            'qr_code' => null,
                            'xml_url' => null
                        ]
                    ];
                }
            } catch (\Exception $e) {
                error_log('Error obteniendo PDF por descarga directa: ' . $e->getMessage());
            }

            // Si la descarga directa falla, intentar obtener la URL del PDF
            try {
                $pdfResponse = $this->client->request('GET', "invoices/{$invoiceId}/pdf");
                $pdfResult = json_decode($pdfResponse->getBody()->getContents(), true);
                
                error_log('Respuesta PDF de Alegra: ' . print_r($pdfResult, true));
                
                if (!empty($pdfResult['downloadLink'])) {
                    return [
                        'success' => true,
                        'data' => [
                            'pdf_url' => $pdfResult['downloadLink'],
                            'cufe' => null,
                            'qr_code' => null,
                            'xml_url' => null
                        ]
                    ];
                }
            } catch (\Exception $e) {
                error_log('Error obteniendo URL del PDF: ' . $e->getMessage());
            }

            // Como último recurso, intentar obtener los detalles completos de la factura
            $response = $this->client->request('GET', "invoices/{$invoiceId}");
            $result = json_decode($response->getBody()->getContents(), true);

            error_log('Respuesta completa de factura: ' . print_r($result, true));

            // Buscar la URL del PDF en varios campos posibles
            $pdfUrl = $result['pdfLink'] ?? 
                      $result['downloadPdfUrl'] ?? 
                      $result['pdf'] ?? 
                      $result['downloadLink'] ?? 
                      $result['pdfURL'] ?? 
                      null;

            if (empty($pdfUrl)) {
                throw new Exception('No se encontró ninguna URL de PDF disponible');
            }

            return [
                'success' => true,
                'data' => [
                    'pdf_url' => $pdfUrl,
                    'cufe' => $result['electronic']['cufe'] ?? null,
                    'qr_code' => $result['electronic']['qrCode'] ?? null,
                    'xml_url' => $result['electronic']['xmlURL'] ?? null
                ]
            ];

        } catch (\Exception $e) {
            error_log('Error en getInvoiceDetails: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getElectronicNumberTemplate() {
        try {
            $response = $this->client->request('GET', 'number-templates', [
                'query' => [
                    'documentType' => 'invoice',
                    'limit' => 10
                ]
            ]);

            $templates = json_decode($response->getBody()->getContents(), true);
            
            // Buscar una plantilla electrónica activa
            foreach ($templates as $template) {
                if ($template['isElectronic'] && $template['status'] === 'active') {
                    return [
                        'success' => true,
                        'data' => $template
                    ];
                }
            }

            throw new Exception('No se encontró una plantilla de numeración electrónica activa');

        } catch (\Exception $e) {
            error_log('Error obteniendo plantilla de numeración: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function createInvoice($data) {
        try {
            // Primero verificar si el cliente existe en Alegra
            $clienteAlegra = $this->getOrCreateContact($data['cliente_id']);
            if (!$clienteAlegra['success']) {
                throw new Exception($clienteAlegra['error']);
            }

            // Procesar items
            $items = [];
            foreach ($data['items'] as $item) {
                $itemAlegra = $this->findOrCreateItem($item);
                if (!$itemAlegra['success']) {
                    throw new Exception('Error al procesar item: ' . $itemAlegra['error']);
                }

                $items[] = [
                    'id' => $itemAlegra['data']['id'],
                    'price' => floatval($item['precio']),
                    'quantity' => intval($item['cantidad'])
                ];
            }

            // Construir el payload para el borrador
            $payload = [
                'date' => date('Y-m-d'),
                'dueDate' => date('Y-m-d'),
                'client' => [
                    'id' => $clienteAlegra['data']['id']
                ],
                'items' => $items,
                'paymentForm' => 'CASH'  // Forma de pago de contado según DIAN
            ];

            // 1. Crear borrador de factura
            $response = $this->client->request('POST', 'invoices', [
                'json' => $payload
            ]);
            $draftInvoice = json_decode($response->getBody()->getContents(), true);

            if (!isset($draftInvoice['id'])) {
                throw new Exception('No se pudo crear el borrador de la factura');
            }

            // 2. Consultar la factura creada
            $response = $this->client->request('GET', "invoices/{$draftInvoice['id']}");
            $invoice = json_decode($response->getBody()->getContents(), true);

            // Antes del timbrado
            error_log('Intentando timbrar factura ID: ' . $draftInvoice['id']);

            // 3. Timbrar la factura ante la DIAN
            $stampPayload = [
                'ids' => [$draftInvoice['id']]
            ];

            error_log('Payload de timbrado: ' . json_encode($stampPayload));

            $response = $this->client->request('POST', 'invoices/stamp', [
                'json' => $stampPayload
            ]);

            $stampResult = json_decode($response->getBody()->getContents(), true);
            error_log('Respuesta de timbrado: ' . json_encode($stampResult));

            // Verificar el resultado del timbrado
            if (isset($stampResult['error'])) {
                throw new Exception('Error al timbrar la factura: ' . ($stampResult['error']['message'] ?? 'Error desconocido'));
            }

            // Esperar un momento y consultar el estado final de la factura
            sleep(2); // Dar tiempo a que se procese el timbrado
            
            $response = $this->client->request('GET', "invoices/{$draftInvoice['id']}");
            $finalInvoice = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'data' => $finalInvoice,
                'stampResult' => $stampResult
            ];

        } catch (\Exception $e) {
            error_log('Error en createInvoice: ' . $e->getMessage());
            error_log('Payload: ' . print_r($payload ?? [], true));
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getOrCreateContact($clienteId) {
        try {
            // Obtener datos del cliente de la base de datos
            global $pdo;
            $stmt = $pdo->prepare("
                SELECT 
                    CONCAT(COALESCE(primer_nombre, ''), ' ', COALESCE(segundo_nombre, ''), ' ', COALESCE(apellidos, '')) as nombre,
                    identificacion as documento,
                    email,
                    telefono,
                    celular,
                    direccion,
                    alegra_id,
                    tipo_persona,
                    responsabilidad_tributaria
                FROM clientes 
                WHERE id = ?
            ");
            $stmt->execute([$clienteId]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cliente) {
                throw new Exception('Cliente no encontrado en la base de datos');
            }

            // Si el cliente ya tiene un ID de Alegra, retornarlo
            if (!empty($cliente['alegra_id'])) {
                return [
                    'success' => true,
                    'data' => ['id' => $cliente['alegra_id']]
                ];
            }

            // Si no tiene ID de Alegra, crear el contacto
            $result = $this->createContact($cliente);
            
            if ($result['success']) {
                // Guardar el ID de Alegra en la base de datos
                $stmt = $pdo->prepare("
                    UPDATE clientes 
                    SET alegra_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$result['data']['id'], $clienteId]);
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function mapPaymentMeans($localMethod) {
        // Ya que solo usaremos efectivo, siempre retornamos CASH
        return 'CASH';
    }

    public function sendInvoiceEmail($invoiceId, $email = null) {
        try {
            $payload = [];
            if ($email) {
                $payload['email'] = $email;
            }

            $response = $this->client->request('POST', "invoices/{$invoiceId}/email", [
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            return [
                'success' => true,
                'message' => 'Factura enviada por correo exitosamente'
            ];

        } catch (\Exception $e) {
            error_log('Error enviando email: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getDefaultAccount() {
        try {
            $response = $this->client->request('GET', 'accounts');
            $accounts = json_decode($response->getBody()->getContents(), true);
            
            // Retornar la primera cuenta activa
            foreach ($accounts as $account) {
                if ($account['status'] === 'active') {
                    return $account['id'];
                }
            }
            
            return 1; // ID por defecto si no se encuentra ninguna
        } catch (\Exception $e) {
            error_log('Error obteniendo cuentas: ' . $e->getMessage());
            return 1; // ID por defecto en caso de error
        }
    }
} 