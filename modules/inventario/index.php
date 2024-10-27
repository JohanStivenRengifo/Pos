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

// Configuración de paginación
$productos_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $productos_por_pagina;

// Parámetro de búsqueda
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';

// Parámetros de ordenación
$columna_orden = isset($_GET['columna']) ? $_GET['columna'] : 'nombre';
$direccion_orden = isset($_GET['direccion']) && $_GET['direccion'] === 'desc' ? 'desc' : 'asc';

// Función para obtener los productos del inventario del usuario, con ordenación
function obtenerProductos($user_id, $limit, $offset, $busqueda, $columna_orden, $direccion_orden)
{
    global $pdo;
    $query = "
    SELECT inventario.*, 
           (inventario.stock * inventario.precio_venta) AS valor_total, 
           departamentos.nombre AS departamento, 
           categorias.nombre AS categoria
    FROM inventario
    LEFT JOIN departamentos ON inventario.departamento_id = departamentos.id
    LEFT JOIN categorias ON inventario.categoria_id = categorias.id
    WHERE inventario.user_id = :user_id 
    AND (inventario.nombre LIKE :busqueda OR inventario.codigo_barras LIKE :busqueda)
    ORDER BY " . $columna_orden . " " . $direccion_orden . "
    LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($query);
    $busqueda_param = "%$busqueda%";
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':busqueda', $busqueda_param, PDO::PARAM_STR);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerValorTotalInventario($user_id, $busqueda)
{
    global $pdo;
    $query = "
        SELECT SUM(inventario.stock * inventario.precio_venta) AS valor_total
        FROM inventario
        WHERE inventario.user_id = ? AND (inventario.nombre LIKE ? OR inventario.codigo_barras LIKE ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, "%$busqueda%", "%$busqueda%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['valor_total'] ?? 0;
}

function obtenerCantidadProductosBajos($user_id, $busqueda)
{
    global $pdo;
    $query = "
        SELECT COUNT(*) AS cantidad_bajos
        FROM inventario
        WHERE inventario.user_id = ? AND inventario.stock <= 2 AND (inventario.nombre LIKE ? OR inventario.codigo_barras LIKE ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, "%$busqueda%", "%$busqueda%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['cantidad_bajos'] ?? 0;
}

// Obtener productos del usuario
$productos = obtenerProductos($user_id, $productos_por_pagina, $offset, $busqueda, $columna_orden, $direccion_orden);
$valor_total_inventario = obtenerValorTotalInventario($user_id, $busqueda);
$cantidad_productos_bajos = obtenerCantidadProductosBajos($user_id, $busqueda);

// Obtener el total de productos para la paginación
$total_productos_query = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE user_id = ? AND (nombre LIKE ? OR codigo_barras LIKE ?)");
$total_productos_query->execute([$user_id, "%$busqueda%", "%$busqueda%"]);
$total_productos = $total_productos_query->fetchColumn();
$total_paginas = ceil($total_productos / $productos_por_pagina);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - VendEasy</title>
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
                <a href="/modules/inventario/index.php" class="active">Inventario</a>
                <a href="/modules/clientes/index.php">Clientes</a>
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
            <h2>Ítems de Venta</h2>
            <div class="promo_card">
                <h1>Gestión de Inventario</h1>
                <span>Crea, edita y administra cada detalle de tus ítems de venta</span>
            </div>

            <div class="button-group">
                <a href="crear.php" class="btn btn-primary">Nuevo Ítem de Venta</a>
                <a href="surtir.php" class="btn btn-primary">Surtir Inventario</a>
                <a href="importar_archivos.php" class="btn btn-primary">Importar Archivos</a>
                <a href="catalogo.php" class="btn btn-primary">Catálogo</a>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Resumen de Inventario</h4>
                    </div>
                    <div class="inventory-summary">
                        <p>Valor Total de Inventario: $ <?= number_format($valor_total_inventario, 2, ',', '.'); ?></p>
                        <p>Número de productos en cero o inferior a 2: <?= $cantidad_productos_bajos; ?></p>
                    </div>
                </div>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Lista de Productos</h4>
                    </div>
                    <form method="GET" action="" class="search-form">
                        <input type="text" name="busqueda" placeholder="Buscar por nombre o código de barras" value="<?= htmlspecialchars($busqueda); ?>">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </form>
                    <table>
                        <thead>
                            <tr>
                                <th><a href="?columna=nombre&direccion=<?= $columna_orden === 'nombre' && $direccion_orden === 'asc' ? 'desc' : 'asc'; ?>&busqueda=<?= urlencode($busqueda); ?>">Nombre<?= $columna_orden === 'nombre' ? ($direccion_orden === 'asc' ? ' ↑' : ' ↓') : ''; ?></a></th>
                                <th><a href="?columna=codigo_barras&direccion=<?= $columna_orden === 'codigo_barras' && $direccion_orden === 'asc' ? 'desc' : 'asc'; ?>&busqueda=<?= urlencode($busqueda); ?>">Código de Barras<?= $columna_orden === 'codigo_barras' ? ($direccion_orden === 'asc' ? ' ↑' : ' ↓') : ''; ?></a></th>
                                <th><a href="?columna=stock&direccion=<?= $columna_orden === 'stock' && $direccion_orden === 'asc' ? 'desc' : 'asc'; ?>&busqueda=<?= urlencode($busqueda); ?>">Cantidad<?= $columna_orden === 'stock' ? ($direccion_orden === 'asc' ? ' ↑' : ' ↓') : ''; ?></a></th>
                                <th><a href="?columna=precio_costo&direccion=<?= $columna_orden === 'precio_costo' && $direccion_orden === 'asc' ? 'desc' : 'asc'; ?>&busqueda=<?= urlencode($busqueda); ?>">Precio Costo<?= $columna_orden === 'precio_costo' ? ($direccion_orden === 'asc' ? ' ↑' : ' ↓') : ''; ?></a></th>
                                <th><a href="?columna=precio_venta&direccion=<?= $columna_orden === 'precio_venta' && $direccion_orden === 'asc' ? 'desc' : 'asc'; ?>&busqueda=<?= urlencode($busqueda); ?>">Precio Venta<?= $columna_orden === 'precio_venta' ? ($direccion_orden === 'asc' ? ' ↑' : ' ↓') : ''; ?></a></th>
                                <th><a href="?columna=valor_total&direccion=<?= $columna_orden === 'valor_total' && $direccion_orden === 'asc' ? 'desc' : 'asc'; ?>&busqueda=<?= urlencode($busqueda); ?>">Valor Total<?= $columna_orden === 'valor_total' ? ($direccion_orden === 'asc' ? ' ↑' : ' ↓') : ''; ?></a></th>
                                <th><a href="?columna=departamento&direccion=<?= $columna_orden === 'departamento' && $direccion_orden === 'asc' ? 'desc' : 'asc'; ?>&busqueda=<?= urlencode($busqueda); ?>">Departamento<?= $columna_orden === 'departamento' ? ($direccion_orden === 'asc' ? ' ↑' : ' ↓') : ''; ?></a></th>
                                <th><a href="?columna=categoria&direccion=<?= $columna_orden === 'categoria' && $direccion_orden === 'asc' ? 'desc' : 'asc'; ?>&busqueda=<?= urlencode($busqueda); ?>">Categoría<?= $columna_orden === 'categoria' ? ($direccion_orden === 'asc' ? ' ↑' : ' ↓') : ''; ?></a></th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <tr>
                                    <td><?= htmlspecialchars($producto['nombre']); ?></td>
                                    <td><?= htmlspecialchars($producto['codigo_barras']); ?></td>
                                    <td><?= htmlspecialchars($producto['stock']); ?></td>
                                    <td><?= '$ ' . number_format($producto['precio_costo'], 2, ',', '.'); ?></td>
                                    <td><?= '$ ' . number_format($producto['precio_venta'], 2, ',', '.'); ?></td>
                                    <td><?= '$ ' . number_format($producto['valor_total'], 2, ',', '.'); ?></td>
                                    <td><?= htmlspecialchars($producto['departamento'] ?: 'No asociado'); ?></td>
                                    <td><?= htmlspecialchars($producto['categoria'] ?: 'No asociado'); ?></td>
                                    <td>
                                        <a href="modificar.php?codigo_barras=<?= urlencode($producto['codigo_barras']); ?>" class="btn-edit"><i class="fas fa-edit"></i></a>
                                        <a href="eliminar.php?codigo_barras=<?= urlencode($producto['codigo_barras']); ?>" class="btn-delete" onclick="return confirm('¿Estás seguro de que deseas eliminar este producto?');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Paginación -->
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <a href="?pagina=<?= $i; ?>&busqueda=<?= urlencode($busqueda); ?>&columna=<?= $columna_orden; ?>&direccion=<?= $direccion_orden; ?>" class="<?= ($pagina_actual === $i) ? 'active' : ''; ?>"><?= $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php
            if (isset($_GET['mensaje'])) {
                $tipo = 'info';
                switch ($_GET['mensaje']) {
                    case 'importacion_exitosa':
                        $mensaje = "La importación se ha realizado con éxito.";
                        $tipo = 'success';
                        break;
                    case 'eliminacion_exitosa':
                        $mensaje = "Todos los productos han sido eliminados.";
                        $tipo = 'success';
                        break;
                    case 'error_eliminacion':
                        $mensaje = "Hubo un error al intentar eliminar todos los productos.";
                        $tipo = 'error';
                        break;
                    default:
                        $mensaje = $_GET['mensaje'];
                }
                echo "Swal.fire({
                icon: '$tipo',
                title: 'Notificación',
                text: '$mensaje'
            });";
            }
            ?>
        });
    </script>
</body>

</html>