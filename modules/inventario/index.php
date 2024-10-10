<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Configuración de paginación
$productos_por_pagina = 100; 
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
    WHERE inventario.user_id = ? 
    AND (inventario.nombre LIKE ? OR inventario.codigo_barras LIKE ?)
    ORDER BY $columna_orden $direccion_orden
    LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, "%$busqueda%", "%$busqueda%", $limit, $offset]);
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

$valor_total_inventario = obtenerValorTotalInventario($_SESSION['user_id'], $busqueda);

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

$cantidad_productos_bajos = obtenerCantidadProductosBajos($_SESSION['user_id'], $busqueda);

// Agregar esta función para eliminar todos los productos
function eliminarTodosLosProductos($user_id) {
    global $pdo;
    $query = "DELETE FROM inventario WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    return $stmt->execute([$user_id]);
}

// Manejar la solicitud de eliminación de todos los productos
if (isset($_POST['eliminar_todos']) && isset($_POST['confirmar_eliminacion'])) {
    if (eliminarTodosLosProductos($_SESSION['user_id'])) {
        $mensaje = "Todos los productos han sido eliminados.";
    } else {
        $mensaje = "Hubo un error al intentar eliminar todos los productos.";
    }
}

// Obtener productos del usuario
$productos = obtenerProductos($_SESSION['user_id'], $productos_por_pagina, $offset, $busqueda, $columna_orden, $direccion_orden);
$valor_total_inventario = obtenerValorTotalInventario($_SESSION['user_id'], $busqueda);
$cantidad_productos_bajos = obtenerCantidadProductosBajos($_SESSION['user_id'], $busqueda);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha384-k6RqeWeci5ZR/Lv4MR0sA0FfDOM5ch3kFccf/dD4Hp/v5/a48Kt7E3/qErQAwz2" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/notificaciones.css">
    <style>
        /* Estilos mejorados para la tabla */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }

        .table th {
            background-color: #f4f4f4;
        }

        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table tr:hover {
            background-color: #f1f1f1;
        }

        .table th a {
            color: inherit;
            text-decoration: none;
        }

        .table th a:hover {
            color: #007bff;
        }

        .sort-icon {
            margin-left: 5px;
        }

        /* Estilos para la paginación */
        .pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            padding: 0;
        }

        .pagination li {
            margin: 0 5px;
        }

        .pagination a {
            text-decoration: none;
            padding: 8px 12px;
            border: 1px solid #007bff;
            border-radius: 5px;
            color: #007bff;
        }

        .pagination a:hover {
            background-color: #007bff;
            color: white;
        }

        .pagination .active a {
            background-color: #007bff;
            color: white;
        }
    </style>
    <script>
    function confirmarEliminacion() {
        if (confirm("¿Estás seguro de que deseas eliminar TODOS los productos? Esta acción no se puede deshacer.")) {
            document.getElementById('confirmar_eliminacion').value = 'true';
            return true;
        }
        return false;
    }
    </script>
</head>

<body>
    <div class="sidebar">
        <h2>Menú Principal</h2>
        <ul>
            <li><a href="../../welcome.php">Inicio</a></li>
            <li><a href="../../modules/ventas/index.php">Ventas</a></li>
            <li><a href="../../modules/reportes/index.php">Reportes</a></li>
            <li><a href="../../modules/inventario/index.php" class="active">Productos</a></li>
            <li><a href="../../modules/clientes/index.php">Clientes</a></li>
            <li><a href="../../modules/proveedores/index.php">Proveedores</a></li>
            <li><a href="../../modules/config/index.php">Configuración</a></li>
            <li>
                <form method="POST" action="">
                    <button type="submit" name="logout" class="logout-button">Cerrar Sesión</button>
                </form>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <h2>Ítems de Venta</h2>
        <p>Crea, edita y administra cada detalle de tus ítems de venta</p>

        <div class="button-group">
            <a href="crear.php" class="btn btn-primary">Nuevo Ítem de Venta</a>
            <a href="surtir.php" class="btn btn-primary">Surtir Inventario</a>
            <a href="importar_archivos.php" class="btn btn-primary">Importar Archivos</a>
            <a href="ventas_por_periodos.php" class="btn btn-primary">Ventas por Periodos</a>
            <a href="promociones.php" class="btn btn-primary">Promociones</a>
            <a href="catalogo.php" class="btn btn-primary">Catálogo</a>
            <a href="kardex.php" class="btn btn-primary">Kardex</a>
        </div>

        <h3>Valor Total de Inventario: $ <?= number_format($valor_total_inventario ?: 0, 2, ',', '.'); ?></h3>
        <h4>Número de productos en cero o inferior a 2: <?= $cantidad_productos_bajos; ?></h4> <!-- Cantidad de productos bajos -->

        <!-- Barra de búsqueda -->
        <form method="GET" action="">
            <input type="text" name="busqueda" placeholder="Buscar por nombre o código de barras" value="<?= htmlspecialchars($busqueda); ?>">
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>

        <h3>Lista de Productos</h3>

        <div class="table-container">
            <table class="table">
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
                    <?php if (count($productos) > 0): ?>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><?= htmlspecialchars($producto['nombre']); ?></td>
                                <td><?= htmlspecialchars($producto['codigo_barras']); ?></td>
                                <td><?= htmlspecialchars($producto['stock']); ?></td>
                                <td><?= '$ ' . number_format($producto['precio_costo'], 2, ',', '.'); ?></td>
                                <td><?= '$ ' . number_format($producto['precio_venta'], 2, ',', '.'); ?></td>
                                <td><?= '$ ' . number_format($producto['stock'] * $producto['precio_venta'], 2, ',', '.'); ?></td> <!-- Valor Total -->
                                <td><?= htmlspecialchars($producto['departamento'] ?: 'No asociado'); ?></td>
                                <td><?= htmlspecialchars($producto['categoria'] ?: 'No asociado'); ?></td>
                                <td>
                                    <a href="modificar.php?codigo_barras=<?= urlencode($producto['codigo_barras']); ?>" class="btn btn-edit"><i class="fas fa-edit"></i> Modificar</a>
                                    <a href="eliminar.php?codigo_barras=<?= urlencode($producto['codigo_barras']); ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de que deseas eliminar este producto?');"><i class="fas fa-trash"></i> Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">No hay productos disponibles.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="paginacion">
            <?php
            // Obtener el total de productos del usuario, incluyendo la búsqueda
            $total_productos_query = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE user_id = ? AND (nombre LIKE ? OR codigo_barras LIKE ?)");
            $total_productos_query->execute([$_SESSION['user_id'], "%$busqueda%", "%$busqueda%"]);
            $total_productos = $total_productos_query->fetchColumn();
            $total_paginas = ceil($total_productos / $productos_por_pagina);

            // Mostrar botones de paginación
            echo '<ul class="pagination">';
            for ($i = 1; $i <= $total_paginas; $i++): ?>
                <li class="<?= ($pagina_actual === $i) ? 'active' : ''; ?>">
                    <a href="?pagina=<?= $i; ?>&busqueda=<?= urlencode($busqueda); ?>"><?= $i; ?></a>
                </li>
            <?php endfor;
            echo '</ul>';
            ?>
        </div>

        <?php
        if (isset($_POST['logout'])) {
            session_destroy();
            header("Location: ../../index.php");
            exit();
        }
        ?>

        <!-- En la parte HTML, donde quieras mostrar el mensaje -->
        <div id="notificaciones">
            <?php
            if (isset($_GET['mensaje'])) {
                $tipo = 'info';
                switch ($_GET['mensaje']) {
                    case 'importacion_exitosa':
                        $mensaje = "La importación se ha realizado con éxito.";
                        $tipo = 'exito';
                        break;
                    case 'eliminacion_exitosa':
                        $mensaje = "Todos los productos han sido eliminados.";
                        $tipo = 'exito';
                        break;
                    case 'error_eliminacion':
                        $mensaje = "Hubo un error al intentar eliminar todos los productos.";
                        $tipo = 'error';
                        break;
                    default:
                        $mensaje = $_GET['mensaje'];
                }
                echo "<div class='notificacion notificacion-$tipo'>";
                echo "<span class='notificacion-cerrar' onclick='this.parentElement.style.display=\"none\";'>&times;</span>";
                echo $mensaje;
                echo "</div>";
            }
            ?>
        </div>

        <!-- Agregar este botón después de la tabla de productos -->
        <form method="POST" onsubmit="return confirmarEliminacion()">
            <input type="hidden" name="confirmar_eliminacion" id="confirmar_eliminacion" value="false">
            <button type="submit" name="eliminar_todos" class="btn btn-danger">Eliminar Todos los Productos</button>
        </form>

        <!-- Mostrar mensaje de éxito o error -->
        <?php if (isset($mensaje)): ?>
            <div class="mensaje"><?php echo $mensaje; ?></div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var notificaciones = document.querySelectorAll('.notificacion');
        notificaciones.forEach(function(notificacion) {
            setTimeout(function() {
                notificacion.style.opacity = '0';
                setTimeout(function() {
                    notificacion.style.display = 'none';
                }, 500);
            }, 5000);
        });
    });
    </script>
</body>

</html>