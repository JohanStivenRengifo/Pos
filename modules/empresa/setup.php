<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verificar la sesión
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('Sesión no válida');
        }

        // Validar CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Token de seguridad inválido');
        }

        // Validar campos requeridos
        $required_fields = [
            'nombre_empresa' => 'Nombre de la empresa',
            'nit' => 'NIT',
            'regimen_fiscal' => 'Régimen fiscal',
            'direccion' => 'Dirección',
            'telefono' => 'Teléfono',
            'correo_contacto' => 'Correo de contacto',
            'prefijo_factura' => 'Prefijo de factura',
            'numero_inicial' => 'Número inicial',
            'numero_final' => 'Número final'
        ];

        $datos = [];
        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo {$label} es obligatorio.");
            }
            $datos[$field] = trim($_POST[$field]);
        }

        // Validar números
        if (!is_numeric($datos['numero_inicial']) || !is_numeric($datos['numero_final'])) {
            throw new Exception('Los números inicial y final deben ser valores numéricos');
        }

        if ((int)$datos['numero_final'] <= (int)$datos['numero_inicial']) {
            throw new Exception('El número final debe ser mayor que el número inicial');
        }

        // Iniciar transacción
        $pdo->beginTransaction();

        try {
            // Procesar logo si existe
            $logo_path = null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png'];
                $filename = $_FILES['logo']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Formato de imagen no válido. Use JPG o PNG.');
                }

                $upload_dir = '../../uploads/logos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $logo_path = 'uploads/logos/' . uniqid() . '.' . $ext;
                if (!move_uploaded_file($_FILES['logo']['tmp_name'], '../../' . $logo_path)) {
                    throw new Exception('Error al subir el logo.');
                }
            }

            // Verificar empresa principal existente
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM empresas WHERE usuario_id = ? AND es_principal = true");
            $stmt->execute([$_SESSION['user_id']]);
            $tiene_principal = $stmt->fetchColumn() > 0;

            // Preparar la consulta
            $sql = "INSERT INTO empresas (
                nombre_empresa, nit, regimen_fiscal, direccion, telefono,
                correo_contacto, prefijo_factura, numero_inicial, numero_final,
                created_at, updated_at, estado, es_principal, usuario_id, logo, ultimo_numero
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                NOW(), NOW(), 1, ?, ?, ?, ?
            )";

            $stmt = $pdo->prepare($sql);
            
            $params = [
                $datos['nombre_empresa'],
                $datos['nit'],
                $datos['regimen_fiscal'],
                $datos['direccion'],
                $datos['telefono'],
                $datos['correo_contacto'],
                $datos['prefijo_factura'],
                (int)$datos['numero_inicial'],
                (int)$datos['numero_final'],
                !$tiene_principal, // es_principal
                $_SESSION['user_id'],
                $logo_path,
                (int)$datos['numero_inicial'] // ultimo_numero
            ];

            if (!$stmt->execute($params)) {
                throw new Exception("Error al registrar la empresa");
            }

            $empresa_id = $pdo->lastInsertId();

            // Registrar evento
            $event_data = json_encode([
                'action' => 'empresa_created',
                'empresa_id' => $empresa_id,
                'nombre_empresa' => $datos['nombre_empresa']
            ]);

            $stmt = $pdo->prepare("INSERT INTO user_events (user_id, event_type, event_data, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], 'empresa_created', $event_data]);

            $pdo->commit();
            $_SESSION['empresa_id'] = $empresa_id;

            $response = [
                'status' => true,
                'message' => 'Empresa registrada exitosamente',
                'redirect' => '../../welcome.php'
            ];
        } catch (Exception $e) {
            if (isset($logo_path) && file_exists('../../' . $logo_path)) {
                unlink('../../' . $logo_path);
            }
            throw $e;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Generar CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Empresa | VendEasy</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
            <!-- Header -->
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                    Configuración de tu Empresa
                </h2>
                <p class="text-gray-600">
                    Completa los detalles de tu empresa para comenzar a usar VendEasy
                </p>
            </div>

            <!-- Progress Steps -->
            <div class="flex justify-between items-center mb-8">
                <div class="w-full">
                    <div class="flex items-center justify-between relative">
                        <div class="w-full absolute top-1/2 transform -translate-y-1/2">
                            <div class="h-1 bg-blue-200"></div>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center z-10">
                                <i class="fas fa-check text-white"></i>
                            </div>
                            <span class="text-sm mt-2">Registro</span>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center z-10">
                                <span class="text-white">2</span>
                            </div>
                            <span class="text-sm mt-2">Empresa</span>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center z-10">
                                <span class="text-gray-600">3</span>
                            </div>
                            <span class="text-sm mt-2">¡Listo!</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">
                                <?= htmlspecialchars($error) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form id="empresaForm" method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Logo Upload Section -->
                <div class="flex justify-center">
                    <div class="w-full max-w-xs">
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-500 transition-colors">
                            <div class="space-y-1 text-center">
                                <div id="preview" class="hidden mb-3">
                                    <img src="" alt="Logo preview" class="mx-auto h-24 w-24 object-contain">
                                </div>
                                <div id="placeholder" class="flex justify-center">
                                    <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl"></i>
                                </div>
                                <div class="flex text-sm text-gray-600">
                                    <label for="logo" class="relative cursor-pointer rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                        <span>Sube el logo</span>
                                        <input id="logo" name="logo" type="file" class="sr-only" accept="image/png,image/jpeg">
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500">PNG o JPG hasta 2MB</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Información Básica -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-medium text-gray-900">Información Básica</h3>
                        
                        <div>
                            <label for="nombre_empresa" class="block text-sm font-medium text-gray-700">
                                Nombre de la Empresa
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-building text-gray-400"></i>
                                </div>
                                <input type="text" id="nombre_empresa" name="nombre_empresa" required
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label for="nit" class="block text-sm font-medium text-gray-700">
                                NIT
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-id-card text-gray-400"></i>
                                </div>
                                <input type="text" id="nit" name="nit" required
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label for="regimen_fiscal" class="block text-sm font-medium text-gray-700">
                                Régimen Fiscal
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-file-invoice-dollar text-gray-400"></i>
                                </div>
                                <select id="regimen_fiscal" name="regimen_fiscal" required
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Seleccione un régimen</option>
                                    <option value="Común">Régimen Común</option>
                                    <option value="Simplificado">Régimen Simplificado</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-medium text-gray-900">Información de Contacto</h3>
                        
                        <div>
                            <label for="direccion" class="block text-sm font-medium text-gray-700">
                                Dirección
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-map-marker-alt text-gray-400"></i>
                                </div>
                                <input type="text" id="direccion" name="direccion" required
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label for="telefono" class="block text-sm font-medium text-gray-700">
                                Teléfono
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-phone text-gray-400"></i>
                                </div>
                                <input type="tel" id="telefono" name="telefono" required
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label for="correo_contacto" class="block text-sm font-medium text-gray-700">
                                Correo de Contacto
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input type="email" id="correo_contacto" name="correo_contacto" required
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuración de Facturación -->
                <div class="pt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-6">Configuración de Facturación</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="prefijo_factura" class="block text-sm font-medium text-gray-700">
                                Prefijo Factura
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-hashtag text-gray-400"></i>
                                </div>
                                <input type="text" id="prefijo_factura" name="prefijo_factura" required
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label for="numero_inicial" class="block text-sm font-medium text-gray-700">
                                Número Inicial
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-sort-numeric-down text-gray-400"></i>
                                </div>
                                <input type="number" id="numero_inicial" name="numero_inicial" required min="1"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label for="numero_final" class="block text-sm font-medium text-gray-700">
                                Número Final
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-sort-numeric-up text-gray-400"></i>
                                </div>
                                <input type="number" id="numero_final" name="numero_final" required min="1"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-6">
                    <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <span>Guardar y Continuar</span>
                        <div class="spinner hidden ml-3">
                            <i class="fas fa-circle-notch fa-spin"></i>
                        </div>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('empresaForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const logoInput = document.getElementById('logo');
        const preview = document.getElementById('preview');
        const placeholder = document.getElementById('placeholder');
        const previewImg = preview.querySelector('img');

        // Preview de logo
        logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                }
                reader.readAsDataURL(file);
            }
        });

        // Validación del número final
        const numeroInicial = document.getElementById('numero_inicial');
        const numeroFinal = document.getElementById('numero_final');

        function validateNumbers() {
            const inicial = parseInt(numeroInicial.value);
            const final = parseInt(numeroFinal.value);
            
            if (final <= inicial) {
                numeroFinal.setCustomValidity('El número final debe ser mayor que el número inicial');
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    text: 'El número final debe ser mayor que el número inicial'
                });
            } else {
                numeroFinal.setCustomValidity('');
            }
        }

        numeroInicial.addEventListener('input', validateNumbers);
        numeroFinal.addEventListener('input', validateNumbers);

        // Manejo del formulario
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!form.checkValidity()) {
                return;
            }

            submitButton.classList.add('opacity-75', 'cursor-not-allowed');
            submitButton.querySelector('.spinner').classList.remove('hidden');

            try {
                const formData = new FormData(this);
                
                // Agregar el header X-Requested-With explícitamente
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                console.log('Respuesta del servidor:', data);

                if (!data.status) {
                    throw new Error(data.message || 'Error al guardar los datos');
                }

                await Swal.fire({
                    icon: 'success',
                    title: '¡Configuración guardada!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });

                window.location.href = data.redirect || '../../welcome.php';
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al guardar la configuración',
                    footer: '<a href="#">Contacta a soporte si el problema persiste</a>'
                });
            } finally {
                submitButton.classList.remove('opacity-75', 'cursor-not-allowed');
                submitButton.querySelector('.spinner').classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html> 