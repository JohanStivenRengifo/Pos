<?php
require_once(__DIR__ . '/../../../vendor/autoload.php');

class AlegraIntegration
{
    private $client;
    private $credentials;
    private $taxes = null;

    public function __construct()
    {
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

    private function getTaxes()
    {
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

    private function getDefaultTax()
    {
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

    public function findContactByIdentification($identification)
    {
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

    public function createContact($clientData)
    {
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

    private function getIdentificationType($tipoPersona, $identificacion)
    {
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

    private function mapPersonType($tipoPersona)
    {
        // Mapeo según documentación de Alegra Colombia
        $typeMap = [
            'juridica' => 'LEGAL_ENTITY',
            'natural' => 'PERSON_ENTITY'
        ];

        return $typeMap[$tipoPersona] ?? 'PERSON_ENTITY';
    }

    private function mapRegime($responsabilidad)
    {
        // Mapeo según documentación de Alegra Colombia
        $regimeMap = [
            'iva' => 'COMMON_REGIME',
            'no_iva' => 'SIMPLIFIED_REGIME',
            'regimen_simple' => 'SIMPLIFIED_REGIME',
            'gran_contribuyente' => 'COMMON_REGIME'
        ];

        return $regimeMap[$responsabilidad] ?? 'SIMPLIFIED_REGIME';
    }

    public function findOrCreateItem($item)
    {
        try {
            // Primero buscar si el item existe
            $existingItem = $this->findItemByName($item['nombre']);

            if ($existingItem['success']) {
                return $existingItem;
            }

            // Si no existe, crear nuevo item sin IVA
            $payload = [
                'name' => $item['nombre'],
                'price' => floatval($item['precio']),
                'reference' => $item['codigo_barras'] ?? '',
                'description' => $item['nombre'],
                'inventory' => [
                    'unit' => 'unit',
                    'available' => intval($item['cantidad'])
                ],
                'itemType' => 'PRODUCT',
                'tax' => [], // Array vacío de impuestos
                'priceList' => [
                    [
                        'idPriceList' => 1,
                        'price' => floatval($item['precio'])
                    ]
                ]
            ];

            // Agregar campos opcionales si están disponibles
            if (!empty($item['codigo_barras'])) {
                $payload['reference'] = $item['codigo_barras'];
            }

            error_log('Creando item sin IVA: ' . json_encode($payload));

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
            error_log('Error creando item: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function saveAlegraItemId($localId, $alegraId)
    {
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

    public function findItemByName($name)
    {
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

    public function getInvoiceDetails($invoiceId)
    {
        try {
            // Obtener los detalles completos de la factura
            $response = $this->client->request('GET', "invoices/{$invoiceId}");
            $result = json_decode($response->getBody()->getContents(), true);

            error_log('Respuesta de factura Alegra: ' . print_r($result, true));

            // Verificar si la factura existe y está lista
            if (!isset($result['id'])) {
                throw new Exception('No se pudo obtener la factura');
            }

            // Extraer información relevante
            $invoiceData = [
                'status' => $result['status'],
                'total' => $result['total'],
                'date' => $result['date'],
                'number' => $result['numberTemplate']['number'],
                'prefix' => $result['numberTemplate']['prefix'] ?? '',
                'payment_method' => $result['paymentMethod'],
                'payment_form' => $result['paymentForm'],
                'items' => $result['items'],
                'client' => $result['client'],
                'electronic' => [
                    'cufe' => $result['electronic']['cufe'] ?? null,
                    'qr_code' => $result['electronic']['qrCode'] ?? null,
                    'xml_url' => $result['electronic']['xmlURL'] ?? null,
                    'pdf_url' => null
                ]
            ];

            // Intentar obtener el PDF
            try {
                $pdfResponse = $this->client->request('GET', "invoices/{$invoiceId}/pdf");
                $pdfResult = json_decode($pdfResponse->getBody()->getContents(), true);
                
                if (!empty($pdfResult['downloadLink'])) {
                    $invoiceData['electronic']['pdf_url'] = $pdfResult['downloadLink'];
                }
            } catch (\Exception $e) {
                error_log('Error obteniendo PDF: ' . $e->getMessage());
            }

            return [
                'success' => true,
                'data' => $invoiceData
            ];
        } catch (\Exception $e) {
            error_log('Error en getInvoiceDetails: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getElectronicNumberTemplate()
    {
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

    public function createInvoice($data)
    {
        try {
            error_log('Iniciando proceso de facturación electrónica');

            // 1. Preparar los datos básicos
            $clienteAlegra = $this->getOrCreateContact($data['cliente_id']);
            if (!$clienteAlegra['success']) {
                throw new Exception('Error con el cliente: ' . $clienteAlegra['error']);
            }

            $numberTemplate = $this->getElectronicNumberTemplate();
            if (!$numberTemplate['success']) {
                throw new Exception('Error con la plantilla: ' . $numberTemplate['error']);
            }

            $seller = $this->getDefaultSeller();
            if (!$seller['success']) {
                throw new Exception('Error con el vendedor: ' . $seller['error']);
            }

            // 2. Preparar los items SIN impuestos (no responsables de IVA)
            $items = [];
            foreach ($data['items'] as $item) {
                $itemAlegra = $this->findOrCreateItem($item);
                if (!$itemAlegra['success']) {
                    throw new Exception('Error con el item: ' . $itemAlegra['error']);
                }

                $items[] = [
                    'id' => $itemAlegra['data']['id'],
                    'price' => floatval($item['precio']),
                    'quantity' => intval($item['cantidad'])
                    // Removemos el campo 'tax' ya que no somos responsables de IVA
                ];
            }

            // 3. Construir el payload para crear la factura
            $invoicePayload = [
                'date' => date('Y-m-d'),
                'dueDate' => date('Y-m-d'),
                'client' => [
                    'id' => $clienteAlegra['data']['id']
                ],
                'items' => $items,
                'numberTemplate' => [
                    'id' => $numberTemplate['data']['id']
                ],
                'paymentForm' => 'CASH',
                'paymentMethod' => 'CASH',
                'seller' => [
                    'id' => $seller['data']['id']
                ],
                'anotation' => 'Factura de venta',
                'currency' => [
                    'code' => 'COP'
                ],
                'operationType' => 'STANDARD',
                'documentType' => 'NATIONAL'
            ];

            // Removemos el campo payments ya que lo manejaremos después de crear la factura
            
            error_log('Payload de factura: ' . json_encode($invoicePayload));

            // 4. Crear la factura con manejo mejorado de errores
            try {
                $response = $this->client->request('POST', 'invoices', [
                    'json' => $invoicePayload
                ]);
                
                $responseBody = $response->getBody()->getContents();
                error_log('Respuesta cruda de Alegra: ' . $responseBody);
                
                $invoice = json_decode($responseBody, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Error decodificando respuesta JSON: ' . json_last_error_msg());
                }
                
                if (!isset($invoice['id'])) {
                    throw new Exception('No se pudo crear la factura. Respuesta: ' . $responseBody);
                }
                
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                error_log('Error de cliente HTTP: ' . $errorBody);
                throw new Exception('Error creando factura: ' . $errorBody);
            }

            error_log('Factura creada con ID: ' . $invoice['id']);

            // 5. Verificar que la factura existe
            $response = $this->client->request('GET', "invoices/{$invoice['id']}");
            $invoiceStatus = json_decode($response->getBody()->getContents(), true);

            if (!isset($invoiceStatus['id'])) {
                throw new Exception('No se pudo verificar la factura creada');
            }

            error_log('Factura verificada, procediendo a timbrar');

            // 6. Timbrar la factura
            $stampPayload = [
                'ids' => [$invoice['id']]
            ];

            error_log('Timbrando factura con payload: ' . json_encode($stampPayload));

            $response = $this->client->request('POST', 'invoices/stamp', [
                'json' => $stampPayload
            ]);

            $stampResult = json_decode($response->getBody()->getContents(), true);
            error_log('Resultado del timbrado: ' . json_encode($stampResult));

            // 7. Esperar y verificar el estado final
            sleep(3);
            $response = $this->client->request('GET', "invoices/{$invoice['id']}");
            $finalInvoice = json_decode($response->getBody()->getContents(), true);

            // 8. Crear el pago
            $paymentResult = $this->createPayment(
                $invoice['id'],
                $finalInvoice['total'],
                $this->mapPaymentMeans('CASH')
            );

            return [
                'success' => true,
                'data' => $finalInvoice,
                'stampResult' => $stampResult,
                'paymentResult' => $paymentResult
            ];
        } catch (\Exception $e) {
            error_log('Error en createInvoice: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getOrCreateContact($clienteId)
    {
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
        // Retornamos 'cash' en minúsculas según la documentación
        return 'cash';
    }

    public function sendInvoiceEmail($invoiceId, $email = null)
    {
        try {
            $payload = [];
            if ($email) {
                $payload['email'] = $email;
            }

            error_log('Intentando enviar factura ' . $invoiceId . ' por correo a: ' . ($email ?? 'email del cliente'));

            $response = $this->client->request('POST', "invoices/{$invoiceId}/email", [
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            error_log('Respuesta de envío de email: ' . print_r($result, true));

            return [
                'success' => true,
                'message' => 'Factura enviada por correo exitosamente',
                'data' => $result
            ];
        } catch (\Exception $e) {
            error_log('Error enviando email: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getDefaultAccount()
    {
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

    public function createPayment($invoiceId, $amount, $paymentMethod = 'CASH')
    {
        try {
            error_log('Creando pago para factura ' . $invoiceId . ' por valor de ' . $amount);

            // Obtener la cuenta por defecto
            $accountId = $this->getDefaultAccount();

            $payload = [
                'date' => date('Y-m-d'),
                'amount' => floatval($amount),
                'account' => [
                    'id' => $accountId
                ],
                'paymentMethod' => $paymentMethod,
                'invoices' => [
                    [
                        'id' => $invoiceId,
                        'amount' => floatval($amount)
                    ]
                ]
            ];

            error_log('Payload de pago: ' . json_encode($payload));

            $response = $this->client->request('POST', 'payments', [
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            error_log('Respuesta de creación de pago: ' . json_encode($result));

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (\Exception $e) {
            error_log('Error creando pago: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getPayments($invoiceId = null)
    {
        try {
            $query = [];
            if ($invoiceId) {
                $query['invoice'] = $invoiceId;
            }

            $response = $this->client->request('GET', 'payments', [
                'query' => $query
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (\Exception $e) {
            error_log('Error consultando pagos: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getDefaultSeller()
    {
        try {
            error_log('Iniciando búsqueda/creación de vendedor por defecto');

            // 1. Primero intentar obtener vendedores existentes
            $response = $this->client->request('GET', 'sellers');
            $sellers = json_decode($response->getBody()->getContents(), true);

            error_log('Respuesta de vendedores: ' . json_encode($sellers));

            // Verificar si la respuesta es un array
            if (!is_array($sellers)) {
                error_log('La respuesta de vendedores no es un array, creando vendedor nuevo');
                $sellers = [];
            }

            // 2. Buscar un vendedor activo o con el nombre específico
            foreach ($sellers as $seller) {
                if (
                    $seller['status'] === 'active' ||
                    (isset($seller['name']) && $seller['name'] === 'Johan Stiven Rengifo')
                ) {
                    error_log('Vendedor encontrado: ' . json_encode($seller));

                    // Si el vendedor existe pero no está activo, intentar activarlo
                    if ($seller['status'] !== 'active') {
                        try {
                            $updateResponse = $this->client->request('PUT', 'sellers/' . $seller['id'], [
                                'json' => [
                                    'status' => 'active'
                                ]
                            ]);
                            $updatedSeller = json_decode($updateResponse->getBody()->getContents(), true);
                            error_log('Vendedor activado: ' . json_encode($updatedSeller));
                            return [
                                'success' => true,
                                'data' => $updatedSeller
                            ];
                        } catch (\Exception $e) {
                            error_log('Error activando vendedor existente: ' . $e->getMessage());
                        }
                    }

                    return [
                        'success' => true,
                        'data' => $seller
                    ];
                }
            }

            // 3. Si no hay vendedor adecuado, crear uno nuevo
            error_log('Creando nuevo vendedor...');

            $createPayload = [
                'name' => 'Johan Stiven Rengifo',
                'identification' => '1048067754',
                'observations' => 'Vendedor principal',
                'status' => 'active',
                'email' => 'johanrengifo78@gmail.com',
                'phone' => '3216371125'
            ];

            error_log('Payload para crear vendedor: ' . json_encode($createPayload));

            try {
                $response = $this->client->request('POST', 'sellers', [
                    'json' => $createPayload
                ]);

                $newSeller = json_decode($response->getBody()->getContents(), true);
                error_log('Respuesta de creación de vendedor: ' . json_encode($newSeller));

                if (!isset($newSeller['id'])) {
                    throw new Exception('La respuesta no contiene ID de vendedor');
                }

                return [
                    'success' => true,
                    'data' => $newSeller
                ];
            } catch (\Exception $e) {
                error_log('Error creando nuevo vendedor: ' . $e->getMessage());
                throw new Exception('No se pudo crear el vendedor: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            error_log('Error en getDefaultSeller: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
