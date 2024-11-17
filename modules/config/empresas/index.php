<?php
session_start();
require_once '../../../config/db.php';
require_once 'functions.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

try {
    // Usar la conexión existente ($pdo)
    
    // Consulta para obtener las empresas del usuario
    $query = "SELECT * FROM empresas WHERE usuario_id = ? ORDER BY es_principal DESC, nombre_empresa ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $empresas_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Manejar solicitudes POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save_empresa'])) {
            $empresa_data = [
                'nombre_empresa' => trim($_POST['nombre_empresa']),
                'nit' => trim($_POST['nit']),
                'regimen_fiscal' => trim($_POST['regimen_fiscal']),
                'direccion' => trim($_POST['direccion']),
                'telefono' => trim($_POST['telefono']),
                'correo_contacto' => trim($_POST['correo_contacto']),
                'prefijo_factura' => trim($_POST['prefijo_factura']),
                'numero_inicial' => (int)$_POST['numero_inicial'],
                'numero_final' => (int)$_POST['numero_final'],
                'usuario_id' => $user_id,
                'estado' => 1
            ];

            $empresa_id = $_POST['empresa_id'] ?? null;
            $logo = isset($_FILES['logo']) ? $_FILES['logo'] : null;
            
            if ($empresa_id) {
                // Actualizar empresa existente
                $updateQuery = "UPDATE empresas SET 
                    nombre_empresa = ?, nit = ?, regimen_fiscal = ?, 
                    direccion = ?, telefono = ?, correo_contacto = ?,
                    prefijo_factura = ?, numero_inicial = ?, numero_final = ?,
                    updated_at = NOW()
                    WHERE id = ? AND usuario_id = ?";
                
                $stmt = $pdo->prepare($updateQuery);
                $stmt->execute([
                    $empresa_data['nombre_empresa'],
                    $empresa_data['nit'],
                    $empresa_data['regimen_fiscal'],
                    $empresa_data['direccion'],
                    $empresa_data['telefono'],
                    $empresa_data['correo_contacto'],
                    $empresa_data['prefijo_factura'],
                    $empresa_data['numero_inicial'],
                    $empresa_data['numero_final'],
                    $empresa_id,
                    $user_id
                ]);
            } else {
                // Insertar nueva empresa
                $insertQuery = "INSERT INTO empresas (
                    nombre_empresa, nit, regimen_fiscal, direccion, 
                    telefono, correo_contacto, prefijo_factura, 
                    numero_inicial, numero_final, usuario_id, estado, 
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
                
                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute([
                    $empresa_data['nombre_empresa'],
                    $empresa_data['nit'],
                    $empresa_data['regimen_fiscal'],
                    $empresa_data['direccion'],
                    $empresa_data['telefono'],
                    $empresa_data['correo_contacto'],
                    $empresa_data['prefijo_factura'],
                    $empresa_data['numero_inicial'],
                    $empresa_data['numero_final'],
                    $user_id
                ]);
                $empresa_id = $pdo->lastInsertId();
            }

            // Manejar la subida del logo si existe
            if ($logo && $logo['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../../uploads/logos/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileExtension = strtolower(pathinfo($logo['name'], PATHINFO_EXTENSION));
                $newFileName = 'logo_' . $empresa_id . '_' . time() . '.' . $fileExtension;
                $uploadFile = $uploadDir . $newFileName;

                if (move_uploaded_file($logo['tmp_name'], $uploadFile)) {
                    // Actualizar la ruta del logo en la base de datos
                    $logoPath = 'uploads/logos/' . $newFileName;
                    $updateLogoQuery = "UPDATE empresas SET logo = ? WHERE id = ?";
                    $stmt = $pdo->prepare($updateLogoQuery);
                    $stmt->execute([$logoPath, $empresa_id]);
                }
            }

            $message = "Empresa guardada correctamente";
            $messageType = "success";
            
            // Recargar la lista de empresas
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id]);
            $empresas_usuario = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (isset($_POST['set_empresa_principal'])) {
            $empresa_id = (int)$_POST['empresa_id'];
            if (setEmpresaPrincipal($user_id, $empresa_id)) {
                $message = "Empresa principal actualizada correctamente";
                $messageType = "success";
                // Recargar la lista de empresas
                $empresas_usuario = getUserEmpresas($user_id);
            }
        } elseif (isset($_POST['eliminar_empresa'])) {
            $empresa_id = (int)$_POST['empresa_id'];
            if (eliminarEmpresa($empresa_id, $user_id)) {
                $message = "Empresa eliminada correctamente";
                $messageType = "success";
                // Recargar la lista de empresas
                $empresas_usuario = getUserEmpresas($user_id);
            }
        }
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $messageType = "error";
    error_log("Error en el módulo de empresas: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empresas - VendEasy</title>
    <link rel="icon" type="image/png" href="../../../favicon/favicon.ico"/>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
</head>

<body class="bg-gray-50">
    <?php include '../../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col md:flex-row gap-6">
            
            <!-- Sidebar -->
            <?php include '../../../includes/sidebar.php'; ?>

            <!-- Contenido Principal -->
            <div class="flex-1">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Gestión de Empresas</h1>
                    <p class="mt-2 text-gray-600">Administra la información de tus empresas registradas</p>
                </div>

                <!-- Tarjetas de Resumen -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Tarjeta de Empresas -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900">Empresas Registradas</h2>
                                <p class="text-gray-600 mt-1">Total: <?= count($empresas_usuario) ?> empresas</p>
                            </div>
                            <button 
                                onclick="abrirModalEmpresa()" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                                <i class="fas fa-plus"></i>
                                Nueva Empresa
                            </button>
                        </div>
                    </div>

                    <!-- Tarjeta de Empresa Principal -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-900">Empresa Principal</h2>
                        <?php if ($empresa_principal): ?>
                            <div class="mt-3">
                                <div class="flex items-center gap-4">
                                    <?php if (!empty($empresa_principal['logo'])): ?>
                                        <img src="/<?= htmlspecialchars($empresa_principal['logo']) ?>" 
                                             alt="Logo" 
                                             class="w-12 h-12 object-contain rounded-lg">
                                    <?php else: ?>
                                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-building text-gray-400 text-2xl"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">
                                            <?= htmlspecialchars($empresa_principal['nombre_empresa']) ?>
                                        </h3>
                                        <p class="text-gray-600">NIT: <?= htmlspecialchars($empresa_principal['nit']) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="mt-3 text-gray-600">No hay empresa principal configurada</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tabla de Empresas -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIT</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Principal</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($empresas_usuario)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                            No hay empresas registradas
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($empresas_usuario as $empresa): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if (!empty($empresa['logo'])): ?>
                                                    <img src="/<?= htmlspecialchars($empresa['logo']) ?>" 
                                                         alt="Logo" 
                                                         class="w-10 h-10 object-contain rounded">
                                                <?php else: ?>
                                                    <i class="fas fa-building text-gray-400 text-2xl"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($empresa['nombre_empresa']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500">
                                                    <?= htmlspecialchars($empresa['nit']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= $empresa['estado'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= $empresa['estado'] ? 'Activa' : 'Inactiva' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($empresa['es_principal']): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        Principal
                                                    </span>
                                                <?php else: ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="empresa_id" value="<?= $empresa['id'] ?>">
                                                        <button type="submit" 
                                                                name="set_empresa_principal"
                                                                class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                                            Hacer Principal
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex gap-2">
                                                    <button onclick='editarEmpresa(<?= json_encode($empresa) ?>)'
                                                            class="text-indigo-600 hover:text-indigo-900">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (!$empresa['es_principal']): ?>
                                                        <button onclick="confirmarEliminarEmpresa(<?= $empresa['id'] ?>)"
                                                                class="text-red-600 hover:text-red-900">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Empresa con diseño mejorado -->
    <div id="modalEmpresa" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-8 border w-full max-w-4xl shadow-xl rounded-lg bg-white">
            <div class="flex flex-col">
                <!-- Encabezado del Modal -->
                <div class="flex justify-between items-center pb-4 mb-6 border-b border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900" id="modalTitle">Nueva Empresa</h3>
                    <button class="text-gray-400 hover:text-gray-500 transition-colors" onclick="cerrarModal()">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="formEmpresa" method="POST" enctype="multipart/form-data" class="space-y-8">
                    <input type="hidden" id="empresa_id" name="empresa_id">

                    <!-- Logo Upload con diseño mejorado -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Logo de la Empresa</label>
                        <div class="flex items-center gap-6">
                            <div id="preview-logo" 
                                 class="flex-shrink-0 h-32 w-32 bg-white rounded-lg shadow-sm border-2 border-dashed border-gray-300 
                                        flex items-center justify-center hover:border-blue-500 transition-colors cursor-pointer"
                                 onclick="document.getElementById('logo').click()">
                                <i class="fas fa-upload text-gray-400 text-3xl"></i>
                            </div>
                            <div class="flex-1">
                                <input type="file" 
                                       id="logo" 
                                       name="logo" 
                                       accept="image/*"
                                       class="hidden">
                                <label for="logo" 
                                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm 
                                              font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 
                                              focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 
                                              cursor-pointer transition-colors">
                                    <i class="fas fa-image mr-2"></i>
                                    Seleccionar imagen
                                </label>
                                <p class="mt-2 text-sm text-gray-500">
                                    PNG, JPG o GIF (Máximo 2MB)
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Grid de 2 columnas con diseño mejorado -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Columna 1 -->
                        <div class="space-y-6">
                            <div class="form-group">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Nombre de la Empresa <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="nombre_empresa" 
                                       name="nombre_empresa" 
                                       required
                                       placeholder="Nombre de tu empresa"
                                       class="form-input block w-full rounded-md border-gray-300 shadow-sm 
                                              focus:border-blue-500 focus:ring-blue-500 transition-colors">
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Régimen Fiscal <span class="text-red-500">*</span>
                                </label>
                                <select id="regimen_fiscal" 
                                        name="regimen_fiscal" 
                                        required
                                        class="form-select block w-full rounded-md border-gray-300 shadow-sm 
                                               focus:border-blue-500 focus:ring-blue-500 transition-colors">
                                    <option value="">Seleccione un régimen...</option>
                                    <option value="1">Régimen Común</option>
                                    <option value="2">Régimen Simplificado</option>
                                    <option value="3">Régimen Especial</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Teléfono <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-phone text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           id="telefono" 
                                           name="telefono" 
                                           required
                                           placeholder="(123) 456-7890"
                                           class="form-input block w-full pl-10 rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500 transition-colors">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Prefijo Factura <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="prefijo_factura" 
                                       name="prefijo_factura" 
                                       required
                                       value="EF"
                                       class="form-input block w-full rounded-md border-gray-300 shadow-sm 
                                              focus:border-blue-500 focus:ring-blue-500 transition-colors">
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Número Inicial <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       id="numero_inicial" 
                                       name="numero_inicial" 
                                       required
                                       min="1"
                                       value="1"
                                       class="form-input block w-full rounded-md border-gray-300 shadow-sm 
                                              focus:border-blue-500 focus:ring-blue-500 transition-colors">
                            </div>
                        </div>

                        <!-- Columna 2 -->
                        <div class="space-y-6">
                            <div class="form-group">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    NIT <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-id-card text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           id="nit" 
                                           name="nit" 
                                           required
                                           placeholder="Número de identificación tributaria"
                                           class="form-input block w-full pl-10 rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500 transition-colors">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Dirección <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-map-marker-alt text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           id="direccion" 
                                           name="direccion" 
                                           required
                                           placeholder="Dirección completa"
                                           class="form-input block w-full pl-10 rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500 transition-colors">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Correo de Contacto <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-gray-400"></i>
                                    </div>
                                    <input type="email" 
                                           id="correo_contacto" 
                                           name="correo_contacto" 
                                           required
                                           placeholder="correo@empresa.com"
                                           class="form-input block w-full pl-10 rounded-md border-gray-300 shadow-sm 
                                                  focus:border-blue-500 focus:ring-blue-500 transition-colors">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    Número Final <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       id="numero_final" 
                                       name="numero_final" 
                                       required
                                       min="1"
                                       value="999999999"
                                       class="form-input block w-full rounded-md border-gray-300 shadow-sm 
                                              focus:border-blue-500 focus:ring-blue-500 transition-colors">
                            </div>
                        </div>
                    </div>

                    <!-- Botones del formulario con diseño mejorado -->
                    <div class="flex justify-end space-x-4 pt-6 mt-6 border-t border-gray-200">
                        <button type="button" 
                                onclick="cerrarModal()"
                                class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 
                                       rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 
                                       focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                        <button type="submit" 
                                name="save_empresa"
                                class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 border border-transparent 
                                       rounded-lg shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 
                                       focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function abrirModalEmpresa() {
            document.getElementById('formEmpresa').reset();
            document.getElementById('empresa_id').value = '';
            document.getElementById('modalTitle').textContent = 'Nueva Empresa';
            document.getElementById('modalEmpresa').classList.remove('hidden');
            document.getElementById('preview-logo').innerHTML = '<i class="fas fa-upload text-gray-400 text-2xl"></i>';
        }

        function cerrarModal() {
            document.getElementById('modalEmpresa').classList.add('hidden');
        }

        function editarEmpresa(empresa) {
            document.getElementById('modalTitle').textContent = 'Editar Empresa';
            document.getElementById('empresa_id').value = empresa.id;
            
            // Llenar los campos del formulario
            const campos = [
                'nombre_empresa', 'nit', 'regimen_fiscal', 'direccion',
                'telefono', 'correo_contacto', 'prefijo_factura',
                'numero_inicial', 'numero_final'
            ];

            campos.forEach(campo => {
                if (document.getElementById(campo)) {
                    document.getElementById(campo).value = empresa[campo] || '';
                }
            });

            // Mostrar logo actual si existe
            const previewLogo = document.getElementById('preview-logo');
            if (empresa.logo) {
                previewLogo.innerHTML = `
                    <img src="/${empresa.logo}" 
                         alt="Logo actual" 
                         class="h-24 w-24 object-contain rounded-lg">`;
            } else {
                previewLogo.innerHTML = '<i class="fas fa-upload text-gray-400 text-2xl"></i>';
            }

            document.getElementById('modalEmpresa').classList.remove('hidden');
        }

        // Preview del logo al seleccionar archivo
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewLogo = document.getElementById('preview-logo');
            
            if (file) {
                if (!file.type.startsWith('image/')) {
                    alert('Por favor, seleccione un archivo de imagen válido');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewLogo.innerHTML = `
                        <img src="${e.target.result}" 
                             alt="Preview" 
                             class="h-24 w-24 object-contain rounded-lg">`;
                }
                reader.readAsDataURL(file);
            }
        });

        // Confirmar eliminación
        function confirmarEliminarEmpresa(empresaId) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="eliminar_empresa" value="1">
                        <input type="hidden" name="empresa_id" value="${empresaId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Mostrar mensajes de éxito/error con SweetAlert2
        <?php if (!empty($message)): ?>
            Swal.fire({
                icon: '<?= $messageType === 'success' ? 'success' : 'error' ?>',
                title: '<?= addslashes($message) ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>
    </script>
</body>
</html> 