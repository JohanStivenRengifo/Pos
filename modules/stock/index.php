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

// Agregar la función getUserInventario
function getUserInventario($user_id) {
    global $pdo;
    try {
        $query = "SELECT * FROM inventario WHERE user_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Modificar la función para devolver respuesta estructurada
function addStock($id, $cantidad, $precio) {
    global $pdo;
    try {
        $query = "UPDATE inventario SET cantidad = cantidad + ?, precio = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$cantidad, $precio, $id]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Stock actualizado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al actualizar el stock'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_stock':
            $id = (int)$_POST['id'];
            $cantidad = (int)trim($_POST['cantidad']);
            $precio = (float)trim($_POST['precio']);

            if ($cantidad <= 0 || $precio <= 0) {
                ApiResponse::send(false, 'Por favor, ingrese una cantidad y precio válidos.');
            }

            $result = addStock($id, $cantidad, $precio);
            ApiResponse::send($result['status'], $result['message']);
            break;
            
        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

// Obtener datos necesarios
$productos = getUserInventario($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar Inventario | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
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
                <a href="/modules/inventario/index.php" class="active">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
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
            <h2>Actualizar Stock de Productos</h2>
            <div class="promo_card">
                <h1>Gestión de Inventario</h1>
                <span>Aquí puedes actualizar el stock de tus productos.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?= strpos($message, 'exitosamente') !== false ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Listado de Productos</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?= htmlspecialchars($producto['id']); ?></td>
                                    <td><?= htmlspecialchars($producto['nombre']); ?></td>
                                    <td><?= htmlspecialchars($producto['descripcion']); ?></td>
                                    <td><?= htmlspecialchars($producto['cantidad']); ?></td>
                                    <td><?= htmlspecialchars($producto['precio']); ?></td>
                                    <td>
                                        <button class="btn-edit" onclick="editStock(<?= htmlspecialchars(json_encode($producto)); ?>)">
                                            <i class="fas fa-edit"></i> Agregar Stock
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

    <!-- Modal para agregar stock -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Agregar Stock</h3>
            <form id="stockForm" method="POST" action="">
                <input type="hidden" id="stock_id" name="id">
                <div class="form-group">
                    <label for="stock_cantidad_actual">Cantidad Actual:</label>
                    <input type="number" id="stock_cantidad_actual" readonly>
                </div>
                <div class="form-group">
                    <label for="stock_cantidad">Cantidad a Agregar:</label>
                    <input type="number" id="stock_cantidad" name="cantidad" min="1" required>
                </div>
                <div class="form-group">
                    <label for="stock_precio_actual">Precio Actual:</label>
                    <input type="number" id="stock_precio_actual" readonly>
                </div>
                <div class="form-group">
                    <label for="stock_precio">Nuevo Precio:</label>
                    <input type="number" step="0.01" id="stock_precio" name="precio" required>
                </div>
                <button type="submit" name="add_stock" class="btn btn-primary">Actualizar Stock</button>
            </form>
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

    // Función para validar el formulario
    function validateStockForm(formData) {
        const cantidad = parseInt(formData.get('cantidad'));
        const precio = parseFloat(formData.get('precio'));
        
        if (cantidad <= 0) {
            showError('Error de validación', 'La cantidad debe ser mayor que cero');
            return false;
        }
        if (precio <= 0) {
            showError('Error de validación', 'El precio debe ser mayor que cero');
            return false;
        }
        return true;
    }

    // Función para editar stock
    async function editStock(producto) {
        const { value: formValues } = await Swal.fire({
            title: 'Agregar Stock',
            html: `
                <form id="stockForm">
                    <div class="form-group">
                        <label for="cantidad_actual">Cantidad Actual</label>
                        <input id="cantidad_actual" class="swal2-input" value="${producto.cantidad}" readonly>
                    </div>
                    <div class="form-group">
                        <label for="cantidad">Cantidad a Agregar</label>
                        <input id="cantidad" class="swal2-input" type="number" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="precio_actual">Precio Actual</label>
                        <input id="precio_actual" class="swal2-input" value="${producto.precio}" readonly>
                    </div>
                    <div class="form-group">
                        <label for="precio">Nuevo Precio</label>
                        <input id="precio" class="swal2-input" type="number" step="0.01" value="${producto.precio}" required>
                    </div>
                </form>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Actualizar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const cantidad = document.getElementById('cantidad').value;
                const precio = document.getElementById('precio').value;
                
                if (cantidad <= 0 || precio <= 0) {
                    Swal.showValidationMessage('Los valores deben ser mayores que cero');
                    return false;
                }
                
                return {
                    cantidad: cantidad,
                    precio: precio
                }
            }
        });

        if (formValues) {
            try {
                const formData = new FormData();
                formData.append('action', 'add_stock');
                formData.append('id', producto.id);
                Object.entries(formValues).forEach(([key, value]) => {
                    formData.append(key, value);
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
                showError('Error', 'Ocurrió un error al actualizar el stock');
            }
        }
    }

    // Estilo personalizado para SweetAlert2
    const style = document.createElement('style');
    style.textContent = `
        .swal2-popup {
            font-family: 'Poppins', sans-serif;
            border-radius: 12px;
        }
        .swal2-title {
            color: #344767;
        }
        .swal2-html-container {
            color: #495057;
        }
        .swal2-confirm {
            background: linear-gradient(145deg, #007bff, #0056b3) !important;
        }
        .swal2-cancel {
            background: linear-gradient(145deg, #6c757d, #495057) !important;
        }
        .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #344767;
        }
        .swal2-input {
            margin: 0.5rem 0 !important;
        }
        .swal2-input[readonly] {
            background-color: #e9ecef;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>
