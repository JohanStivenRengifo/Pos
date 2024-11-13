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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../../css/welcome.css">
    <link rel="stylesheet" href="../../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-dialog {
            width: 100%;
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .modal-content {
            padding: 25px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-body {
            padding: 20px 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .table {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .table th {
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            padding: 15px;
        }

        .table td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        #preview-logo img {
            border: 1px solid #dee2e6;
            padding: 5px;
            border-radius: 4px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .form-column {
            display: flex;
            flex-direction: column;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        /* Estilo específico para el campo de logo */
        .form-group:first-child {
            grid-column: 1 / -1; /* Ocupa ambas columnas */
            margin-bottom: 2rem;
        }

        /* Mejoras visuales para el modal */
        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #dee2e6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }

        .history_lists .row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .list1 {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .table-responsive {
            margin-top: 0;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            vertical-align: middle;
        }

        /* Mejoras para las tarjetas superiores */
        .history_lists .list1 h4 {
            color: #333;
            margin-bottom: 0;
        }

        .history_lists .list1 .row {
            margin-bottom: 0;
        }

        /* Estilo para los datos de la empresa principal */
        .history_lists .list1 strong {
            color: #333;
        }

        .modal.show {
            display: block !important;
        }
        
        .modal {
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-dialog {
            margin: 1.75rem auto;
            max-width: 800px;
        }
    </style>
</head>

<body>
    <?php include '../../../includes/header.php'; ?>
    <div class="container">
        <?php include '../../../includes/sidebar.php'; ?>
        
        <div class="main-body">
            <h2>Gestión de Empresas</h2>
            <div class="promo_card">
                <h1>Administra tus Empresas</h1>
                <span>Configura y gestiona la información de tus empresas.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="history_lists">
                <div class="row" style="display: flex; gap: 20px; margin-bottom: 20px;">
                    <!-- Tarjeta para crear nueva empresa -->
                    <div class="list1" style="flex: 1;">
                        <div class="row">
                            <h4>Empresas Registradas</h4>
                            <button class="btn btn-primary" onclick="abrirModalEmpresa()">
                                <i class="fas fa-plus"></i> Nueva Empresa
                            </button>
                        </div>
                        <p style="margin-top: 10px; color: #666;">
                            Gestiona la información de tus empresas registradas
                        </p>
                    </div>

                    <!-- Tarjeta de balance general -->
                    <div class="list1" style="flex: 1;">
                        <div class="row">
                            <h4>Balance General</h4>
                            <?php
                            // Obtener la empresa principal
                            $empresa_principal = null;
                            foreach ($empresas_usuario as $empresa) {
                                if ($empresa['es_principal']) {
                                    $empresa_principal = $empresa;
                                    break;
                                }
                            }
                            ?>
                        </div>
                        <?php if ($empresa_principal): ?>
                            <div style="margin-top: 10px;">
                                <p style="margin-bottom: 5px; color: #666;">
                                    Empresa Principal: <strong><?= htmlspecialchars($empresa_principal['nombre_empresa']) ?></strong>
                                </p>
                                <p style="color: #666;">
                                    NIT: <strong><?= htmlspecialchars($empresa_principal['nit']) ?></strong>
                                </p>
                            </div>
                        <?php else: ?>
                            <p style="margin-top: 10px; color: #666;">
                                No hay empresa principal configurada
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lista de empresas (ocupando el espacio completo) -->
                <div class="list1" style="width: 100%;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Logo</th>
                                    <th>Nombre</th>
                                    <th>NIT</th>
                                    <th style="width: 100px;">Estado</th>
                                    <th style="width: 120px;">Principal</th>
                                    <th style="width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($empresas_usuario)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No hay empresas registradas</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($empresas_usuario as $empresa): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($empresa['logo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $empresa['logo'])): ?>
                                                <img src="/<?= htmlspecialchars($empresa['logo']); ?>" 
                                                     alt="Logo" 
                                                     style="width: 50px; height: 50px; object-fit: contain;">
                                            <?php else: ?>
                                                <i class="fas fa-building fa-2x"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($empresa['nombre_empresa']); ?></td>
                                        <td><?= htmlspecialchars($empresa['nit']); ?></td>
                                        <td>
                                            <span class="badge <?= $empresa['estado'] ? 'badge-success' : 'badge-danger' ?>">
                                                <?= $empresa['estado'] ? 'Activa' : 'Inactiva' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($empresa['es_principal']): ?>
                                                <span class="badge badge-success">Principal</span>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="empresa_id" value="<?= $empresa['id']; ?>">
                                                    <button type="submit" name="set_empresa_principal" class="btn btn-sm btn-outline-primary">
                                                        Hacer Principal
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick='editarEmpresa(<?= htmlspecialchars(json_encode($empresa), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if (!$empresa['es_principal']): ?>
                                                    <button class="btn btn-sm btn-danger" 
                                                            onclick="confirmarEliminarEmpresa(<?= $empresa['id']; ?>)">
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

    <!-- Modal para crear/editar empresa -->
    <div class="modal" id="modalEmpresa">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Empresa</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="formEmpresa" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="empresa_id" name="empresa_id">
                        
                        <!-- Logo en fila completa -->
                        <div class="form-group">
                            <label for="logo">Logo de la Empresa</label>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                            <div id="preview-logo" class="mt-2"></div>
                        </div>

                        <!-- Grid de 2 columnas -->
                        <div class="form-grid">
                            <!-- Columna 1 -->
                            <div class="form-column">
                                <div class="form-group">
                                    <label for="nombre_empresa">Nombre de la Empresa *</label>
                                    <input type="text" class="form-control" id="nombre_empresa" name="nombre_empresa" required>
                                </div>

                                <div class="form-group">
                                    <label for="regimen_fiscal">Régimen Fiscal *</label>
                                    <select class="form-control" id="regimen_fiscal" name="regimen_fiscal" required>
                                        <option value="">Seleccione...</option>
                                        <option value="1">Régimen Común</option>
                                        <option value="2">Régimen Simplificado</option>
                                        <option value="3">Régimen Especial</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="telefono">Teléfono *</label>
                                    <input type="text" class="form-control" id="telefono" name="telefono" required>
                                </div>

                                <div class="form-group">
                                    <label for="prefijo_factura">Prefijo de Factura *</label>
                                    <input type="text" class="form-control" id="prefijo_factura" name="prefijo_factura" required>
                                </div>

                                <div class="form-group">
                                    <label for="numero_inicial">Número Inicial *</label>
                                    <input type="number" class="form-control" id="numero_inicial" name="numero_inicial" required min="1">
                                </div>
                            </div>

                            <!-- Columna 2 -->
                            <div class="form-column">
                                <div class="form-group">
                                    <label for="nit">NIT *</label>
                                    <input type="text" class="form-control" id="nit" name="nit" required>
                                </div>

                                <div class="form-group">
                                    <label for="direccion">Dirección *</label>
                                    <input type="text" class="form-control" id="direccion" name="direccion" required>
                                </div>

                                <div class="form-group">
                                    <label for="correo_contacto">Correo de Contacto *</label>
                                    <input type="email" class="form-control" id="correo_contacto" name="correo_contacto" required>
                                </div>

                                <div class="form-group">
                                    <label for="numero_final">Número Final *</label>
                                    <input type="number" class="form-control" id="numero_final" name="numero_final" required min="1">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" name="save_empresa" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function abrirModalEmpresa() {
            // Limpiar el formulario
            document.getElementById('formEmpresa').reset();
            document.getElementById('empresa_id').value = '';
            
            // Mostrar el modal
            const modal = document.getElementById('modalEmpresa');
            modal.classList.add('show');
            modal.style.display = 'block'; // Importante: hacer visible el modal
            
            // Actualizar el título
            document.querySelector('.modal-title').textContent = 'Nueva Empresa';
            
            // Limpiar la previsualización del logo
            document.getElementById('preview-logo').innerHTML = '';
        }

        // Asegurarse de que el modal se pueda cerrar correctamente
        function cerrarModal() {
            const modal = document.getElementById('modalEmpresa');
            modal.classList.remove('show');
            modal.style.display = 'none';
        }

        // Eventos para cerrar el modal
        document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', cerrarModal);
        });

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalEmpresa');
            if (event.target == modal) {
                cerrarModal();
            }
        }

        // Funciones para manejar el modal
        function abrirModalEmpresa() {
            document.getElementById('formEmpresa').reset();
            document.getElementById('empresa_id').value = '';
            document.querySelector('.modal-title').textContent = 'Nueva Empresa';
            document.getElementById('modalEmpresa').classList.add('show');
            document.getElementById('preview-logo').innerHTML = '';
        }

        function editarEmpresa(empresa) {
            console.log('Datos de empresa recibidos:', empresa); // Para debug
            
            // Mostrar el modal
            const modal = document.getElementById('modalEmpresa');
            modal.classList.add('show');
            modal.style.display = 'block'; // Asegurarse que el modal sea visible
            
            document.querySelector('.modal-title').textContent = 'Editar Empresa';
            
            // Llenar el formulario con los datos de la empresa
            const campos = {
                'empresa_id': empresa.id,
                'nombre_empresa': empresa.nombre_empresa,
                'nit': empresa.nit,
                'regimen_fiscal': empresa.regimen_fiscal,
                'direccion': empresa.direccion,
                'telefono': empresa.telefono,
                'correo_contacto': empresa.correo_contacto,
                'prefijo_factura': empresa.prefijo_factura,
                'numero_inicial': empresa.numero_inicial,
                'numero_final': empresa.numero_final
            };

            // Llenar cada campo del formulario
            Object.keys(campos).forEach(campo => {
                const elemento = document.getElementById(campo);
                if (elemento) {
                    elemento.value = campos[campo] || '';
                }
            });

            // Mostrar logo actual si existe
            const previewLogo = document.getElementById('preview-logo');
            if (empresa.logo) {
                previewLogo.innerHTML = `
                    <div class="mt-2">
                        <p>Logo actual:</p>
                        <img src="/${empresa.logo}" alt="Logo actual" 
                             style="max-width: 150px; max-height: 150px; object-fit: contain;">
                    </div>`;
            } else {
                previewLogo.innerHTML = '<p class="mt-2">No hay logo cargado</p>';
            }
        }

        // Modificar la función de cerrar modal
        document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', () => {
                const modal = document.getElementById('modalEmpresa');
                modal.classList.remove('show');
                modal.style.display = 'none';
            });
        });

        // Agregar evento para cerrar modal haciendo clic fuera
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('modalEmpresa');
            if (event.target === modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
            }
        });

        // Previsualización del logo
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
                        <div class="mt-2">
                            <p>Vista previa:</p>
                            <img src="${e.target.result}" alt="Preview" 
                                 style="max-width: 150px; max-height: 150px; object-fit: contain;">
                        </div>`;
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

        // Validación del formulario
        document.getElementById('formEmpresa').addEventListener('submit', function(e) {
            const numeroInicial = parseInt(document.getElementById('numero_inicial').value);
            const numeroFinal = parseInt(document.getElementById('numero_final').value);

            if (numeroFinal <= numeroInicial) {
                e.preventDefault();
                alert('El número final debe ser mayor que el número inicial');
                return false;
            }
        });

        // Mejorar el manejo de mensajes
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