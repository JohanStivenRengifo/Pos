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
        $query = "DELETE FROM clientes WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$cliente_id, $user_id]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Cliente eliminado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al eliminar el cliente'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
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
                    <a href="#">Ayuda</a>
                    <a href="#">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Gestión de Clientes</h2>
            <div class="promo_card">
                <h1>Clientes</h1>
                <span>Aquí puedes agregar y gestionar tus clientes.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Agregar Nuevo Cliente</h4>
                    </div>
                    <form id="clienteForm" method="POST" action="">
                        <div class="form-group">
                            <label for="nombre">Nombre del Cliente:</label>
                            <input type="text" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Correo Electrónico:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono:</label>
                            <input type="text" id="telefono" name="telefono" required>
                        </div>
                        <div class="form-group">
                            <label for="tipo_identificacion">Tipo de Identificación:</label>
                            <input type="text" id="tipo_identificacion" name="tipo_identificacion" required>
                        </div>
                        <div class="form-group">
                            <label for="identificacion">Número de Identificación:</label>
                            <input type="text" id="identificacion" name="identificacion" required>
                        </div>
                        <div class="form-group">
                            <label for="primer_nombre">Primer Nombre:</label>
                            <input type="text" id="primer_nombre" name="primer_nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="segundo_nombre">Segundo Nombre:</label>
                            <input type="text" id="segundo_nombre" name="segundo_nombre">
                        </div>
                        <div class="form-group">
                            <label for="apellidos">Apellidos:</label>
                            <input type="text" id="apellidos" name="apellidos" required>
                        </div>
                        <div class="form-group">
                            <label for="municipio_departamento">Municipio/Departamento:</label>
                            <input type="text" id="municipio_departamento" name="municipio_departamento" required>
                        </div>
                        <div class="form-group">
                            <label for="codigo_postal">Código Postal:</label>
                            <input type="text" id="codigo_postal" name="codigo_postal" required>
                        </div>
                        <button type="submit" name="add_cliente" class="btn btn-primary">Agregar Cliente</button>
                    </form>
                </div>

                <div class="list2">
                    <div class="row">
                        <h4>Lista de Clientes</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Identificación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cliente['nombre']); ?></td>
                                    <td><?= htmlspecialchars($cliente['email']); ?></td>
                                    <td><?= htmlspecialchars($cliente['telefono']); ?></td>
                                    <td><?= htmlspecialchars($cliente['identificacion']); ?></td>
                                    <td>
                                        <button class="btn-edit" onclick="editCliente(<?= htmlspecialchars(json_encode($cliente)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="deleteCliente(<?= $cliente['id']; ?>)">
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

    <script>
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

    // Manejador del formulario de cliente
    document.getElementById('clienteForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
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
                setTimeout(() => location.reload(), 1500);
            } else {
                showError('Error', data.message);
            }
        } catch (error) {
            showError('Error', 'Ocurrió un error al procesar la solicitud');
        }
    });

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
