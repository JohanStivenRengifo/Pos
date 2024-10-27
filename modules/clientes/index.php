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

// Funciones para manejar clientes
function getClientes($user_id) {
    global $pdo;
    $query = "SELECT * FROM clientes WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCliente($user_id, $nombre, $email, $telefono, $tipo_identificacion, $identificacion, $primer_nombre, $segundo_nombre, $apellidos, $municipio_departamento, $codigo_postal) {
    global $pdo;
    $query = "INSERT INTO clientes (user_id, nombre, email, telefono, tipo_identificacion, identificacion, primer_nombre, segundo_nombre, apellidos, municipio_departamento, codigo_postal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $nombre, $email, $telefono, $tipo_identificacion, $identificacion, $primer_nombre, $segundo_nombre, $apellidos, $municipio_departamento, $codigo_postal]);
}

function deleteCliente($cliente_id, $user_id) {
    global $pdo;
    $query = "DELETE FROM clientes WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$cliente_id, $user_id]);
}

// Mensajes
$message = '';

// Procesar formulario para agregar cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_cliente'])) {
    $nombre = trim($_POST['nombre']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $telefono = trim($_POST['telefono']);
    $tipo_identificacion = trim($_POST['tipo_identificacion']);
    $identificacion = trim($_POST['identificacion']);
    $primer_nombre = trim($_POST['primer_nombre']);
    $segundo_nombre = trim($_POST['segundo_nombre']);
    $apellidos = trim($_POST['apellidos']);
    $municipio_departamento = trim($_POST['municipio_departamento']);
    $codigo_postal = trim($_POST['codigo_postal']);

    // Validación
    if (empty($nombre) || empty($email) || empty($telefono) || empty($tipo_identificacion) || empty($identificacion)) {
        $message = "Por favor complete todos los campos obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Ingrese un correo electrónico válido.";
    } else {
        if (addCliente($user_id, $nombre, $email, $telefono, $tipo_identificacion, $identificacion, $primer_nombre, $segundo_nombre, $apellidos, $municipio_departamento, $codigo_postal)) {
            $message = "Cliente agregado exitosamente.";
        } else {
            $message = "Error al agregar cliente.";
        }
    }
}

// Procesar eliminación de cliente
if (isset($_POST['delete_cliente'])) {
    $cliente_id = (int)$_POST['cliente_id'];
    if (deleteCliente($cliente_id, $user_id)) {
        $message = "Cliente eliminado correctamente.";
    } else {
        $message = "Error al eliminar cliente.";
    }
}

$clientes = getClientes($user_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - VendEasy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
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
                <a href="/modules/pos/index.php">Punto de Venta</a>
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

            <?php if (!empty($message)): ?>
                <div class="alert <?= strpos($message, 'exitosamente') !== false ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Agregar Nuevo Cliente</h4>
                    </div>
                    <form method="POST" action="">
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
    function editCliente(cliente) {
        // Implementar la lógica para editar un cliente
        console.log("Editar cliente:", cliente);
        // Aquí puedes abrir un modal o redirigir a una página de edición
    }

    function deleteCliente(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Enviar solicitud de eliminación
                $.post('', { delete_cliente: true, cliente_id: id }, function(response) {
                    Swal.fire(
                        'Eliminado',
                        'El cliente ha sido eliminado.',
                        'success'
                    ).then(() => {
                        location.reload();
                    });
                }).fail(function() {
                    Swal.fire(
                        'Error',
                        'No se pudo eliminar el cliente.',
                        'error'
                    );
                });
            }
        });
    }
    </script>
</body>
</html>
