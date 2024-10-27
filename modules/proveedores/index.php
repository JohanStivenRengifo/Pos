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

// Función para obtener todos los proveedores asociados al usuario actual
function getUserProveedores($user_id)
{
    global $pdo;
    $query = "SELECT * FROM proveedores WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para agregar un nuevo proveedor
function addProveedor($user_id, $nombre, $email, $telefono, $direccion)
{
    global $pdo;
    $query = "INSERT INTO proveedores (user_id, nombre, email, telefono, direccion) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id, $nombre, $email, $telefono, $direccion]);
}

// Guardar nuevo proveedor si se envía el formulario
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_proveedor'])) {
    $nombre = trim($_POST['nombre']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);

    // Validar los campos
    if (empty($nombre) || empty($email) || empty($telefono) || empty($direccion)) {
        $message = "Por favor, complete todos los campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Por favor, ingrese un correo electrónico válido.";
    } else {
        // Agregar proveedor a la base de datos
        if (addProveedor($user_id, $nombre, $email, $telefono, $direccion)) {
            $message = "Proveedor agregado exitosamente.";
        } else {
            $message = "Error al agregar el proveedor.";
        }
    }
}

// Obtener todos los proveedores del usuario
$proveedores = getUserProveedores($user_id);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - VendEasy</title>
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
                <a href="/welcome.php" >Dashboard</a>
                <a href="/modules/pos/index.php">Punto de Venta</a>
                <a href="/modules/ingresos/index.php">Ingresos</a>
                <a href="/modules/egresos/index.php">Egresos</a>
                <a href="/modules/ventas/index.php">Ventas</a>
                <a href="/modules/inventario/index.php">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
                <a href="/modules/proveedores/index.php" class="active">Proveedores</a>
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
            <h2>Gestionar Proveedores</h2>
            <div class="promo_card">
                <h1>Proveedores</h1>
                <span>Aquí puedes agregar y gestionar tus proveedores.</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?= strpos($message, 'exitosamente') !== false ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Agregar Nuevo Proveedor</h4>
                    </div>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="nombre">Nombre del Proveedor:</label>
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
                            <label for="direccion">Dirección:</label>
                            <input type="text" id="direccion" name="direccion" required>
                        </div>
                        <button type="submit" name="add_proveedor" class="btn btn-primary">Agregar Proveedor</button>
                    </form>
                </div>

                <div class="list2">
                    <div class="row">
                        <h4>Listado de Proveedores</h4>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Correo Electrónico</th>
                                <th>Teléfono</th>
                                <th>Dirección</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proveedores as $proveedor): ?>
                                <tr>
                                    <td><?= htmlspecialchars($proveedor['nombre']); ?></td>
                                    <td><?= htmlspecialchars($proveedor['email']); ?></td>
                                    <td><?= htmlspecialchars($proveedor['telefono']); ?></td>
                                    <td><?= htmlspecialchars($proveedor['direccion']); ?></td>
                                    <td>
                                        <button class="btn-edit" onclick="editProveedor(<?= htmlspecialchars(json_encode($proveedor)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete" onclick="deleteProveedor(<?= $proveedor['id']; ?>)">
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
        function editProveedor(proveedor) {
            // Implementar la lógica para editar un proveedor
            console.log("Editar proveedor:", proveedor);
            // Aquí puedes abrir un modal o redirigir a una página de edición
        }

        function deleteProveedor(id) {
            // Implementar la lógica para eliminar un proveedor
            console.log("Eliminar proveedor con ID:", id);
            // Aquí puedes usar SweetAlert2 para confirmar la eliminación
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
                    // Aquí iría la lógica para eliminar el proveedor de la base de datos
                    Swal.fire(
                        'Eliminado',
                        'El proveedor ha sido eliminado.',
                        'success'
                    )
                }
            })
        }
    </script>
</body>

</html>