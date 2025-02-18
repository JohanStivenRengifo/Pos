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
                created_at, updated_at, estado, es_principal, usuario_id, logo,
                ultimo_numero, plan_suscripcion, tipo_persona, tipo_identificacion,
                primer_nombre, segundo_nombre, apellidos, nombre_comercial,
                tipo_nacionalidad, responsabilidad_tributaria, moneda, pais,
                departamento, municipio, codigo_postal
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                NOW(), NOW(), 1, ?, ?, ?,
                ?, 'basico', ?, ?, 
                ?, ?, ?, ?,
                'nacional', ?, 'COP', 'Colombia',
                ?, ?, ?
            )";

            $stmt = $pdo->prepare($sql);
            
            // Procesar datos según tipo de persona
            $tipo_persona = $_POST['tipo_persona'];
            $primer_nombre = $tipo_persona === 'natural' ? $_POST['primer_nombre'] : null;
            $segundo_nombre = $tipo_persona === 'natural' ? $_POST['segundo_nombre'] : null;
            $apellidos = $tipo_persona === 'natural' ? $_POST['apellidos'] : null;
            $nombre_comercial = $tipo_persona === 'juridica' ? $datos['nombre_empresa'] : null;
            
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
                (int)$datos['numero_inicial'], // ultimo_numero
                $tipo_persona,
                $_POST['tipo_identificacion'] ?? 'NIT',
                $primer_nombre,
                $segundo_nombre,
                $apellidos,
                $nombre_comercial,
                $_POST['responsabilidad_tributaria'] ?? 'No responsable de IVA',
                $_POST['departamento'] ?? '',
                $_POST['municipio'] ?? '',
                $_POST['codigo_postal'] ?? ''
            ];

            if (!$stmt->execute($params)) {
                throw new Exception("Error al registrar la empresa");
            }

            $empresa_id = $pdo->lastInsertId();

            // Actualizar el empresa_id en la tabla users
            $stmt = $pdo->prepare("UPDATE users SET empresa_id = ? WHERE id = ?");
            if (!$stmt->execute([$empresa_id, $_SESSION['user_id']])) {
                throw new Exception("Error al actualizar la información del usuario");
            }

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
                'redirect' => 'planes.php'
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
    <title>Configuración de Empresa | Numercia</title>
    <link rel="icon" type="image/png" href="../../favicon/favicon.ico"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 3px solid #fff;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col md:flex-row bg-gray-50">
    <!-- Sección lateral con imagen y mensaje de bienvenida -->
    <div class="hidden lg:flex lg:w-1/2 bg-primary-600 text-white p-12 flex-col justify-between">
        <div>
            <h1 class="text-4xl font-bold mb-4">Numercia</h1>
            <p class="text-primary-100">Sistema integral de gestión empresarial</p>
        </div>
        <div class="space-y-6">
            <h2 class="text-3xl font-bold">Configura tu empresa</h2>
            <p class="text-xl text-primary-100">Personaliza la información de tu negocio para comenzar a facturar</p>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-building text-primary-300"></i>
                    <span>Datos fiscales</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-file-invoice text-primary-300"></i>
                    <span>Facturación</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-users text-primary-300"></i>
                    <span>Multiusuario</span>
                </div>
                <div class="flex items-center space-x-2">
                    <i class="fas fa-chart-bar text-primary-300"></i>
                    <span>Reportes</span>
                </div>
            </div>
        </div>
        <div class="text-sm text-primary-100">
            © <?= date('Y') ?> Numercia. Todos los derechos reservados.
        </div>
    </div>

    <!-- Formulario -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-bold text-gray-900">Configuración de Empresa</h2>
                <p class="mt-2 text-sm text-gray-600">Completa los datos de tu empresa para comenzar</p>
            </div>

            <!-- Progress Steps -->
            <div class="flex justify-between items-center mb-8">
                <div class="w-full">
                    <div class="flex items-center justify-between relative">
                        <div class="w-full absolute top-1/2 transform -translate-y-1/2">
                            <div class="h-1 bg-primary-200"></div>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 bg-primary-600 rounded-full flex items-center justify-center z-10">
                                <i class="fas fa-check text-white"></i>
                            </div>
                            <span class="text-sm mt-2">Registro</span>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 bg-primary-600 rounded-full flex items-center justify-center z-10">
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

            <form id="empresaForm" method="POST" action="" enctype="multipart/form-data" class="mt-8 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Tipo de Persona -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Persona</label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:border-primary-500 transition-colors">
                            <input type="radio" name="tipo_persona" value="natural" class="text-primary-600" required>
                            <span class="ml-2">Natural</span>
                        </label>
                        <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:border-primary-500 transition-colors">
                            <input type="radio" name="tipo_persona" value="juridica" class="text-primary-600">
                            <span class="ml-2">Jurídica</span>
                        </label>
                    </div>
                </div>

                <!-- Campos específicos para persona natural -->
                <div id="campos_natural" class="space-y-4 hidden">
                    <div>
                        <label for="primer_nombre" class="block text-sm font-medium text-gray-700">Primer Nombre</label>
                        <input type="text" id="primer_nombre" name="primer_nombre" 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="segundo_nombre" class="block text-sm font-medium text-gray-700">Segundo Nombre</label>
                        <input type="text" id="segundo_nombre" name="segundo_nombre" 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="apellidos" class="block text-sm font-medium text-gray-700">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>

                <!-- Campos adicionales -->
                <div class="space-y-4">
                    <div>
                        <label for="tipo_identificacion" class="block text-sm font-medium text-gray-700">
                            Tipo de Identificación
                        </label>
                        <select id="tipo_identificacion" name="tipo_identificacion" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                            <option value="NIT">NIT</option>
                            <option value="CC">Cédula de Ciudadanía</option>
                            <option value="CE">Cédula de Extranjería</option>
                            <option value="PP">Pasaporte</option>
                        </select>
                    </div>

                    <div>
                        <label for="responsabilidad_tributaria" class="block text-sm font-medium text-gray-700">
                            Responsabilidad Tributaria
                        </label>
                        <select id="responsabilidad_tributaria" name="responsabilidad_tributaria" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                            <option value="No responsable de IVA">No responsable de IVA</option>
                            <option value="Responsable de IVA">Responsable de IVA</option>
                            <option value="Gran Contribuyente">Gran Contribuyente</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="departamento" class="block text-sm font-medium text-gray-700">
                                Departamento
                            </label>
                            <input type="text" id="departamento" name="departamento"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div>
                            <label for="municipio" class="block text-sm font-medium text-gray-700">
                                Municipio
                            </label>
                            <input type="text" id="municipio" name="municipio"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                        </div>

                        <div>
                            <label for="codigo_postal" class="block text-sm font-medium text-gray-700">
                                Código Postal
                            </label>
                            <input type="text" id="codigo_postal" name="codigo_postal"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>
                </div>

                <!-- Logo Upload Section -->
                <div class="flex justify-center">
                    <div class="w-full max-w-xs">
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-primary-500 transition-colors">
                            <div class="space-y-1 text-center">
                                <div id="preview" class="hidden mb-3">
                                    <img src="" alt="Logo preview" class="mx-auto h-24 w-24 object-contain">
                                </div>
                                <div id="placeholder" class="flex justify-center">
                                    <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl"></i>
                                </div>
                                <div class="flex text-sm text-gray-600">
                                    <label for="logo" class="relative cursor-pointer rounded-md font-medium text-primary-600 hover:text-primary-500 focus-within:outline-none">
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
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
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
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
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
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
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
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
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
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
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
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
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
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
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
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
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
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" 
                    class="group relative w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150 ease-in-out">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-save"></i>
                    </span>
                    <span class="mx-auto">Guardar y Continuar</span>
                    <div class="spinner hidden absolute right-4 top-1/2 transform -translate-y-1/2"></div>
                </button>
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

                window.location.href = data.redirect || 'planes.php';
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

        // Agregar manejo de tipo de persona
        const tipoPersonaInputs = document.querySelectorAll('input[name="tipo_persona"]');
        const camposNatural = document.getElementById('campos_natural');

        tipoPersonaInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value === 'natural') {
                    camposNatural.classList.remove('hidden');
                } else {
                    camposNatural.classList.add('hidden');
                }
            });
        });
    });
    </script>
</body>
</html> 