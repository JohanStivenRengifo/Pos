<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$email = $_SESSION['email'];

// Clase para manejar respuestas JSON
class ApiResponse {
    public static function send($status, $message, $data = null) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Funciones para manejar clientes
function getClientes($user_id) {
    global $pdo;
    $query = "SELECT * FROM clientes WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCliente($user_id, $data) {
    global $pdo;
    try {
        $query = "INSERT INTO clientes (user_id, nombre, email, telefono, tipo_identificacion, identificacion, 
                                      primer_nombre, segundo_nombre, apellidos, municipio_departamento, codigo_postal) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $user_id, 
            $data['nombre'],
            $data['email'],
            $data['telefono'],
            $data['tipo_identificacion'],
            $data['identificacion'],
            $data['primer_nombre'],
            $data['segundo_nombre'],
            $data['apellidos'],
            $data['municipio_departamento'],
            $data['codigo_postal']
        ]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Cliente agregado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al agregar el cliente'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function updateCliente($user_id, $cliente_id, $data) {
    global $pdo;
    try {
        $query = "UPDATE clientes SET 
                  nombre = ?, email = ?, telefono = ?, tipo_identificacion = ?,
                  identificacion = ?, primer_nombre = ?, segundo_nombre = ?,
                  apellidos = ?, municipio_departamento = ?, codigo_postal = ?
                  WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            $data['nombre'],
            $data['email'],
            $data['telefono'],
            $data['tipo_identificacion'],
            $data['identificacion'],
            $data['primer_nombre'],
            $data['segundo_nombre'],
            $data['apellidos'],
            $data['municipio_departamento'],
            $data['codigo_postal'],
            $cliente_id,
            $user_id
        ]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Cliente actualizado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al actualizar el cliente'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function deleteCliente($cliente_id, $user_id) {
    global $pdo;
    try {
        // Primero verificar si el cliente tiene ventas asociadas
        $query = "SELECT COUNT(*) FROM ventas WHERE cliente_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$cliente_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            return [
                'status' => false, 
                'message' => 'No se puede eliminar el cliente porque tiene ventas asociadas',
                'hasReferences' => true
            ];
        }

        // Si no tiene ventas, proceder con la eliminación
        $query = "DELETE FROM clientes WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$cliente_id, $user_id]);
        
        if ($result) {
            return [
                'status' => true, 
                'message' => 'Cliente eliminado exitosamente',
                'hasReferences' => false
            ];
        }
        return [
            'status' => false, 
            'message' => 'Error al eliminar el cliente',
            'hasReferences' => false
        ];
    } catch (PDOException $e) {
        return [
            'status' => false, 
            'message' => 'Error en la base de datos: ' . $e->getMessage(),
            'hasReferences' => strpos($e->getMessage(), 'foreign key constraint') !== false
        ];
    }
}

// Manejador de peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $result = addCliente($user_id, $_POST);
            ApiResponse::send($result['status'], $result['message']);
            break;
            
        case 'update':
            $cliente_id = (int)$_POST['cliente_id'];
            $result = updateCliente($user_id, $cliente_id, $_POST);
            ApiResponse::send($result['status'], $result['message']);
            break;
            
        case 'delete':
            $cliente_id = (int)$_POST['cliente_id'];
            $result = deleteCliente($cliente_id, $user_id);
            ApiResponse::send($result['status'], $result['message']);
            break;
            
        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

$clientes = getClientes($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php
    // Sistema de notificaciones mejorado
    if (!empty($message)) {
        $alertType = strpos($message, 'exitosamente') !== false || strpos($message, 'correctamente') !== false ? 'success' : 'error';
        $alertTitle = $alertType === 'success' ? '¡Éxito!' : '¡Atención!';
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: '$alertTitle',
                    text: '$message',
                    icon: '$alertType',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
            });
        </script>";
    }
    ?>

    <header class="header">
        <div class="logo">
            <a href="../../welcome.php">VendEasy</a>
        </div>
        <div class="header-icons">
            <i class="fas fa-bell"></i>
            <div class="account">
                <h4><?= htmlspecialchars($email) ?></h4>
            </div>
        </div>
    </header>
    <div class="container">
        <nav>
        <div class="side_navbar">
                <span>Menú Principal</span>
                <a href="/welcome.php">Dashboard</a>
                <a href="/modules/pos/index.php">POS</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
                <a href="/modules/clientes/index.php" class="active">Clientes</a>
                <a href="/modules/proveedores/index.php">Proveedores</a>
                <a href="/modules/reportes/index.php">Reportes</a>
                <a href="/modules/config/index.php">Configuración</a>

                <div class="links">
                    <span>Enlaces Rápidos</span>
                    <a href="/ayuda.php">Ayuda</a>
                    <a href="/contacto.php">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Gestión de Clientes</h2>
            <div class="promo_card">
                <h1>Clientes</h1>
                <span>Gestiona la información de tus clientes de manera eficiente</span>
                <button class="btn-add" onclick="showAddClienteForm()">
                    <i class="fas fa-plus"></i> Nuevo Cliente
                </button>
            </div>

            <!-- Actualización del modal y formulario -->
            <div id="clienteModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="modalTitle">Nuevo Cliente</h3>
                        <span class="close">&times;</span>
                    </div>
                    
                    <div class="modal-body">
                        <form id="clienteForm" method="POST" action="">
                            <!-- Sección: Información Personal -->
                            <div class="form-section">
                                <h4><i class="fas fa-user"></i> Información Personal</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="primer_nombre" class="required">Primer Nombre</label>
                                        <input type="text" id="primer_nombre" name="primer_nombre" required 
                                               placeholder="Ingrese el primer nombre">
                                    </div>
                                    <div class="form-group">
                                        <label for="segundo_nombre">Segundo Nombre</label>
                                        <input type="text" id="segundo_nombre" name="segundo_nombre" 
                                               placeholder="Ingrese el segundo nombre">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="apellidos" class="required">Apellidos</label>
                                        <input type="text" id="apellidos" name="apellidos" required 
                                               placeholder="Ingrese los apellidos">
                                    </div>
                                    <div class="form-group">
                                        <label for="nombre">Nombre Comercial</label>
                                        <input type="text" id="nombre" name="nombre" 
                                               placeholder="Ingrese el nombre comercial">
                                    </div>
                                </div>
                            </div>

                            <!-- Sección: Identificación -->
                            <div class="form-section">
                                <h4><i class="fas fa-id-card"></i> Identificación</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="tipo_identificacion" class="required">Tipo de Identificación</label>
                                        <select id="tipo_identificacion" name="tipo_identificacion" required>
                                            <option value="">Seleccione un tipo...</option>
                                            <option value="CC" selected>Cédula de Ciudadanía</option>
                                            <option value="CE">Cédula de Extranjería</option>
                                            <option value="NIT">NIT</option>
                                            <option value="PA">Pasaporte</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="identificacion" class="required">Número de Identificación</label>
                                        <input type="text" id="identificacion" name="identificacion" required 
                                               placeholder="Ingrese el número de identificación">
                                    </div>
                                </div>
                            </div>

                            <!-- Sección: Contacto -->
                            <div class="form-section">
                                <h4><i class="fas fa-envelope"></i> Información de Contacto</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email" class="required">Correo Electrónico</label>
                                        <input type="email" id="email" name="email" required 
                                               placeholder="ejemplo@correo.com">
                                    </div>
                                    <div class="form-group">
                                        <label for="telefono" class="required">Teléfono</label>
                                        <input type="tel" id="telefono" name="telefono" required 
                                               placeholder="Ingrese el número telefónico">
                                    </div>
                                </div>
                            </div>

                            <!-- Sección: Ubicación -->
                            <div class="form-section">
                                <h4><i class="fas fa-map-marker-alt"></i> Ubicación</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="departamento" class="required">Departamento</label>
                                        <select id="departamento" name="departamento" required>
                                            <option value="">Seleccione un departamento...</option>
                                            <option value="Amazonas">Amazonas</option>
                                            <option value="Antioquia">Antioquia</option>
                                            <option value="Arauca">Arauca</option>
                                            <option value="Atlántico">Atlántico</option>
                                            <option value="Bolívar">Bolívar</option>
                                            <option value="Boyacá">Boyacá</option>
                                            <option value="Caldas">Caldas</option>
                                            <option value="Caquetá">Caquetá</option>
                                            <option value="Casanare">Casanare</option>
                                            <option value="Cauca">Cauca</option>
                                            <option value="Cesar">Cesar</option>
                                            <option value="Chocó">Chocó</option>
                                            <option value="Córdoba">Córdoba</option>
                                            <option value="Cundinamarca">Cundinamarca</option>
                                            <option value="Guainía">Guainía</option>
                                            <option value="Guaviare">Guaviare</option>
                                            <option value="Huila">Huila</option>
                                            <option value="La Guajira">La Guajira</option>
                                            <option value="Magdalena">Magdalena</option>
                                            <option value="Meta">Meta</option>
                                            <option value="Nariño">Nariño</option>
                                            <option value="Norte de Santander">Norte de Santander</option>
                                            <option value="Putumayo">Putumayo</option>
                                            <option value="Quindío">Quindío</option>
                                            <option value="Risaralda">Risaralda</option>
                                            <option value="San Andrés y Providencia">San Andrés y Providencia</option>
                                            <option value="Santander">Santander</option>
                                            <option value="Sucre">Sucre</option>
                                            <option value="Tolima">Tolima</option>
                                            <option value="Valle del Cauca">Valle del Cauca</option>
                                            <option value="Vaupés">Vaupés</option>
                                            <option value="Vichada">Vichada</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="codigo_postal">Código Postal</label>
                                        <input type="text" id="codigo_postal" name="codigo_postal" 
                                               placeholder="Ingrese el código postal" readonly>
                                    </div>
                                    <script>
                                        document.getElementById('departamento').addEventListener('change', function() {
                                            const departamento = this.value;
                                            let codigoPostal = '';
                                            switch(departamento) {
                                                case 'Amazonas':
                                                    codigoPostal = '910001';
                                                    break;
                                                case 'Antioquia':
                                                    codigoPostal = '050001';
                                                    break;
                                                case 'Arauca':
                                                    codigoPostal = '810001';
                                                    break;
                                                case 'Atlántico':
                                                    codigoPostal = '080001';
                                                    break;
                                                case 'Bolívar':
                                                    codigoPostal = '130001';
                                                    break;
                                                case 'Boyacá':
                                                    codigoPostal = '150001';
                                                    break;
                                                case 'Caldas':
                                                    codigoPostal = '170001';
                                                    break;
                                                case 'Caquetá':
                                                    codigoPostal = '180001';
                                                    break;
                                                case 'Casanare':
                                                    codigoPostal = '850001';
                                                    break;
                                                case 'Cauca':
                                                    codigoPostal = '190001';
                                                    break;
                                                case 'Cesar':
                                                    codigoPostal = '200001';
                                                    break;
                                                case 'Chocó':
                                                    codigoPostal = '270001';
                                                    break;
                                                case 'Córdoba':
                                                    codigoPostal = '230001';
                                                    break;
                                                case 'Cundinamarca':
                                                    codigoPostal = '250001';
                                                    break;
                                                case 'Guainía':
                                                    codigoPostal = '940001';
                                                    break;
                                                case 'Guaviare':
                                                    codigoPostal = '950001';
                                                    break;
                                                case 'Huila':
                                                    codigoPostal = '410001';
                                                    break;
                                                case 'La Guajira':
                                                    codigoPostal = '440001';
                                                    break;
                                                case 'Magdalena':
                                                    codigoPostal = '470001';
                                                    break;
                                                case 'Meta':
                                                    codigoPostal = '500001';
                                                    break;
                                                case 'Nariño':
                                                    codigoPostal = '520001';
                                                    break;
                                                case 'Norte de Santander':
                                                    codigoPostal = '540001';
                                                    break;
                                                case 'Putumayo':
                                                    codigoPostal = '860001';
                                                    break;
                                                case 'Quindío':
                                                    codigoPostal = '630001';
                                                    break;
                                                case 'Risaralda':
                                                    codigoPostal = '660001';
                                                    break;
                                                case 'San Andrés y Providencia':
                                                    codigoPostal = '880001';
                                                    break;
                                                case 'Santander':
                                                    codigoPostal = '680001';
                                                    break;
                                                case 'Sucre':
                                                    codigoPostal = '700001';
                                                    break;
                                                case 'Tolima':
                                                    codigoPostal = '730001';
                                                    break;
                                                case 'Valle del Cauca':
                                                    codigoPostal = '760001';
                                                    break;
                                                case 'Vaupés':
                                                    codigoPostal = '970001';
                                                    break;
                                                case 'Vichada':
                                                    codigoPostal = '990001';
                                                    break;
                                                default:
                                                    codigoPostal = '';
                                            }
                                            document.getElementById('codigo_postal').value = codigoPostal;
                                        });
                                    </script>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" form="clienteForm" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cliente
                        </button>
                    </div>
                </div>
            </div>

            <div class="list2">
                <div class="table-header">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchCliente" placeholder="Buscar cliente...">
                    </div>
                    <div class="table-actions">
                        <button class="btn-export" onclick="exportarClientes()">
                            <i class="fas fa-file-export"></i> Exportar
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Identificación</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Ubicación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td>
                                        <div class="cliente-info">
                                            <span class="nombre-completo">
                                                <?= htmlspecialchars($cliente['primer_nombre'] . ' ' . $cliente['apellidos']); ?>
                                            </span>
                                            <?php if ($cliente['nombre']): ?>
                                                <span class="nombre-comercial">
                                                    <?= htmlspecialchars($cliente['nombre']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge">
                                            <?= htmlspecialchars($cliente['tipo_identificacion']); ?>
                                        </span>
                                        <?= htmlspecialchars($cliente['identificacion']); ?>
                                    </td>
                                    <td><?= htmlspecialchars($cliente['email']); ?></td>
                                    <td><?= htmlspecialchars($cliente['telefono']); ?></td>
                                    <td><?= htmlspecialchars($cliente['municipio_departamento']); ?></td>
                                    <td class="actions">
                                        <button class="btn-icon" onclick="editCliente(<?= htmlspecialchars(json_encode($cliente)); ?>)" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon delete" onclick="deleteCliente(<?= $cliente['id']; ?>)" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* Estilos actualizados para el modal y formulario */
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 15px;
        margin-bottom: 20px;
        border-bottom: 2px solid #f1f1f1;
    }

    .modal-header h3 {
        margin: 0;
        color: #2c3e50;
        font-size: 1.5em;
    }

    .modal-body {
        max-height: calc(90vh - 150px);
        overflow-y: auto;
        padding-right: 15px;
    }

    .form-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .form-section h4 {
        color: #2c3e50;
        margin: 0 0 15px 0;
        font-size: 1.1em;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-section h4 i {
        color: #007bff;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 15px;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #2c3e50;
        font-weight: 500;
    }

    .form-group label.required::after {
        content: ' *';
        color: #dc3545;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px 15px;
        border: 1.5px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s ease;
        background-color: white;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        outline: none;
    }

    .form-group input::placeholder {
        color: #adb5bd;
    }

    .form-actions {
        position: sticky;
        bottom: 0;
        background: white;
        padding: 15px 0;
        margin-top: 20px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        z-index: 1;
    }

    .btn-secondary,
    .btn-primary {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .invalid {
        border-color: #dc3545 !important;
        background-color: #fff8f8;
    }

    .invalid + .error-message {
        color: #dc3545;
        font-size: 12px;
        margin-top: 5px;
    }

    @media (max-width: 768px) {
        .modal-content {
            margin: 0;
            height: 100vh;
            border-radius: 0;
            max-height: none;
        }

        .form-section {
            padding: 15px;
        }

        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
    }

    /* Estilos mejorados */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        animation: fadeIn 0.3s ease;
        overflow-y: auto; /* Permitir scroll vertical */
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background-color: #fff;
        margin: 20px auto; /* Reducir el margen superior e inferior */
        padding: 25px;
        border-radius: 12px;
        width: 90%;
        max-width: 900px;
        position: relative;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        animation: slideIn 0.3s ease;
        max-height: 90vh; /* Altura máxima del 90% de la ventana */
        overflow-y: auto; /* Permitir scroll dentro del modal */
    }

    @keyframes slideIn {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .close {
        position: sticky;
        top: 0;
        right: 0;
        float: right;
        padding: 10px;
        z-index: 2;
        background: white;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .close:hover {
        color: #dc3545;
    }

    #modalTitle {
        color: #2c3e50;
        margin-bottom: 25px;
        font-size: 1.5em;
        border-bottom: 2px solid #f1f1f1;
        padding-bottom: 10px;
    }

    .form-row {
        display: flex;
        gap: 20px;
        margin-bottom: 15px;
        flex-wrap: wrap; /* Permitir que los campos se ajusten en pantallas pequeñas */
    }

    .form-group {
        flex: 1;
        min-width: 250px; /* Ancho mínimo para evitar campos demasiado estrechos */
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #2c3e50;
        font-weight: 500;
        font-size: 0.95em;
    }

    .form-group label.required::after {
        content: ' *';
        color: #dc3545;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px 15px;
        border: 1.5px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        outline: none;
    }

    .form-group input:hover,
    .form-group select:hover {
        border-color: #b0b0b0;
    }

    .form-actions {
        position: sticky;
        bottom: 0;
        background: white;
        padding: 15px 0;
        margin-top: 20px;
        border-top: 1px solid #eee;
        z-index: 1;
    }

    .btn-secondary,
    .btn-primary {
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        font-size: 14px;
    }

    .btn-secondary {
        background-color: #f8f9fa;
        color: #333;
        border: 1px solid #ddd;
    }

    .btn-secondary:hover {
        background-color: #e2e6ea;
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0056b3;
        transform: translateY(-1px);
    }

    /* Mejoras en la tabla */
    .table-header {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .search-box {
        display: flex;
        align-items: center;
        background: white;
        padding: 8px 15px;
        border-radius: 6px;
        width: 350px;
        border: 1px solid #e0e0e0;
        transition: all 0.3s ease;
    }

    .search-box:focus-within {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

    .search-box input {
        border: none;
        padding: 5px 10px;
        font-size: 14px;
        width: 100%;
    }

    .search-box input:focus {
        outline: none;
    }

    .table-responsive {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        background: #f8f9fa;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        color: #2c3e50;
        border-bottom: 2px solid #e0e0e0;
    }

    td {
        padding: 12px 15px;
        border-bottom: 1px solid #e0e0e0;
        vertical-align: middle;
    }

    tr:hover {
        background-color: #f8f9fa;
    }

    .badge {
        background: #e9ecef;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        color: #2c3e50;
    }

    .btn-icon {
        padding: 8px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .btn-icon:hover {
        background-color: #f8f9fa;
    }

    .btn-icon.delete:hover {
        background-color: #fee2e2;
        color: #dc3545;
    }

    /* Mejorar el scroll del modal */
    .modal-content::-webkit-scrollbar {
        width: 8px;
    }

    .modal-content::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .modal-content::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .modal-content::-webkit-scrollbar-thumb:hover {
        background: #666;
    }

    /* Ajustar el formulario para mejor visualización */
    #clienteForm {
        padding-right: 15px; /* Espacio para el scrollbar */
    }

    /* Mejorar la responsividad */
    @media (max-width: 768px) {
        .modal-content {
            margin: 10px;
            width: calc(100% - 20px);
            padding: 15px;
        }

        .form-row {
            flex-direction: column;
            gap: 10px;
        }

        .form-group {
            width: 100%;
        }
    }
    </style>

    <script>
    // JavaScript mejorado
    document.addEventListener('DOMContentLoaded', function() {
        initializeFormValidation();
        initializeModalHandling();
        initializeSearchFunctionality();
    });

    function initializeFormValidation() {
        const form = document.getElementById('clienteForm');
        
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                showError('Validación', 'Por favor complete todos los campos requeridos correctamente.');
                return;
            }

            try {
                const formData = new FormData(this);
                formData.append('action', 'add');

                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                
                if (data.status) {
                    showNotification('success', data.message);
                    closeModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError('Error', data.message);
                }
            } catch (error) {
                showError('Error', 'Ocurrió un error al procesar la solicitud');
            }
        });
    }

    function validateForm() {
        const requiredFields = document.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('invalid');
                isValid = false;
            } else {
                field.classList.remove('invalid');
            }
        });

        return isValid;
    }

    function initializeModalHandling() {
        const modal = document.getElementById('clienteModal');
        const modalContent = modal.querySelector('.modal-content');
        const closeBtn = document.querySelector('.close');

        closeBtn.addEventListener('click', closeModal);

        // Cerrar modal al hacer clic fuera
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        // Prevenir que el click dentro del modal lo cierre
        modalContent.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Manejar el scroll del modal
        modalContent.addEventListener('scroll', () => {
            const isAtTop = modalContent.scrollTop === 0;
            closeBtn.style.boxShadow = isAtTop ? 'none' : '0 2px 4px rgba(0,0,0,0.1)';
        });
    }

    function showAddClienteForm() {
        const modal = document.getElementById('clienteModal');
        const modalContent = modal.querySelector('.modal-content');
        
        document.getElementById('modalTitle').textContent = 'Nuevo Cliente';
        document.getElementById('clienteForm').reset();
        modal.style.display = 'block';
        
        // Scroll al inicio del modal
        modalContent.scrollTop = 0;
        
        // Prevenir scroll del body pero mantener la posición
        const scrollY = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollY}px`;
        document.body.style.width = '100%';
    }

    function closeModal() {
        const modal = document.getElementById('clienteModal');
        modal.style.display = 'none';
        
        // Restaurar el scroll del body
        const scrollY = document.body.style.top;
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        window.scrollTo(0, parseInt(scrollY || '0') * -1);
    }

    function initializeSearchFunctionality() {
        const searchInput = document.getElementById('searchCliente');
        let searchTimeout;

        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            
            searchTimeout = setTimeout(() => {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            }, 300); // Debounce de 300ms
        });
    }

    // Configuración global de notificaciones
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // Función para mostrar notificaciones
    function showNotification(type, message) {
        Toast.fire({
            icon: type,
            title: message
        });
    }

    // Función para mostrar errores
    function showError(title, message) {
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonText: 'Entendido'
        });
    }

    // Función para confirmar acciones
    async function confirmAction(title, text, icon = 'warning') {
        const result = await Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        });
        return result.isConfirmed;
    }

    // Función para mostrar formulario de edición
    async function showEditForm(cliente) {
        const { value: formValues } = await Swal.fire({
            title: 'Editar Cliente',
            html: `
                <form id="editForm">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input id="nombre" class="swal2-input" value="${cliente.nombre}">
                    </div>
                    <!-- Agregar más campos según necesidad -->
                </form>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                return {
                    nombre: document.getElementById('nombre').value,
                    // Recoger más valores según necesidad
                }
            }
        });
        return formValues;
    }

    // Función para eliminar cliente
    async function deleteCliente(id) {
        if (await confirmAction('¿Eliminar cliente?', 'Esta acción no se puede deshacer')) {
            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('cliente_id', id);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                
                if (data.status) {
                    showNotification('success', data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError('Error', data.message);
                }
            } catch (error) {
                showError('Error', 'Ocurrió un error al eliminar el cliente');
            }
        }
    }

    // Función para editar cliente
    async function editCliente(cliente) {
        const formValues = await showEditForm(cliente);
        if (formValues) {
            try {
                const formData = new FormData();
                formData.append('action', 'update');
                formData.append('cliente_id', cliente.id);
                Object.keys(formValues).forEach(key => {
                    formData.append(key, formValues[key]);
                });

                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                
                if (data.status) {
                    showNotification('success', data.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError('Error', data.message);
                }
            } catch (error) {
                showError('Error', 'Ocurrió un error al actualizar el cliente');
            }
        }
    }
    </script>
</body>
</html>
