<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Configuración de paginación
$productos_por_pagina = 10; // Número de productos a mostrar por página
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_actual - 1) * $productos_por_pagina;

// Función para obtener los productos del inventario del usuario, incluyendo departamentos y categorías
function obtenerProductos($user_id, $limit, $offset)
{
    global $pdo;
    $query = "
        SELECT inventario.*, departamentos.nombre AS departamento, categorias.nombre AS categoria
        FROM inventario
        LEFT JOIN departamentos ON inventario.departamento_id = departamentos.id
        LEFT JOIN categorias ON inventario.categoria_id = categorias.id
        WHERE inventario.user_id = ?
        LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener el valor total del inventario
function obtenerValorTotalInventario($user_id)
{
    global $pdo;
    $query = "
        SELECT SUM(stock * precio_venta) AS valor_total
        FROM inventario
        WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Función para contar los productos con stock cero o menor a dos
function contarProductosBajos($user_id)
{
    global $pdo;
    $query = "
        SELECT COUNT(*) AS cantidad_bajos
        FROM inventario
        WHERE user_id = ? AND stock <= 2";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Obtener productos del usuario
$productos = obtenerProductos($_SESSION['user_id'], $productos_por_pagina, $offset);
// Obtener valor total del inventario
$valor_total_inventario = obtenerValorTotalInventario($_SESSION['user_id']);
// Contar productos con stock cero o menor a dos
$cantidad_productos_bajos = contarProductosBajos($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha384-k6RqeWeci5ZR/Lv4MR0sA0FfDOM5ch3kFccf/dD4Hp/v5/a48Kt7E3/qErQAwz2" crossorigin="anonymous">
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
            <a href="https://import-exel-gc0outo0x-johanrengifos-projects.vercel.app/" class="btn btn-primary">Importar Archivos</a>
            <a href="ventas_por_periodos.php" class="btn btn-primary">Ventas por Periodos</a>
            <a href="promociones.php" class="btn btn-primary">Promociones</a>
            <a href="catalogo.php" class="btn btn-primary">Catálogo</a>
            <a href="kardex.php" class="btn btn-primary">kardex</a> 
        </div>

        <h3>Valor Total de Inventario: $ <?= number_format($valor_total_inventario ?: 0, 2, ',', '.'); ?></h3>
        <h4>Número de productos en cero o inferior a 2: <?= $cantidad_productos_bajos; ?></h4> <!-- Cantidad de productos bajos -->

        <h3>Lista de Productos</h3>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Código de Barras</th>
                        <th>Cantidad</th>
                        <th>Precio Costo</th>
                        <th>Precio Venta</th>
                        <th>Departamento</th>
                        <th>Categoría</th>
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
                                <td><?= htmlspecialchars($producto['departamento']); ?></td>
                                <td><?= htmlspecialchars($producto['categoria']); ?></td>
                                <td>
                                    <a href="modificar.php?codigo_barras=<?= urlencode($producto['codigo_barras']); ?>" class="btn btn-edit"><i class="fas fa-edit"></i> Modificar</a>
                                    <a href="eliminar.php?codigo_barras=<?= urlencode($producto['codigo_barras']); ?>" class="btn btn-delete" onclick="return confirm('¿Estás seguro de que deseas eliminar este producto?');"><i class="fas fa-trash"></i> Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No hay productos disponibles.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="paginacion">
            <?php
            // Obtener el total de productos del usuario
            $total_productos_query = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE user_id = ?");
            $total_productos_query->execute([$_SESSION['user_id']]);
            $total_productos = $total_productos_query->fetchColumn();

            // Calcular el total de páginas
            $total_paginas = ceil($total_productos / $productos_por_pagina);

            // Generar los enlaces de paginación
            if ($total_paginas > 1): ?>
                <nav>
                    <ul class="pagination">
                        <?php if ($pagina_actual > 1): ?>
                            <li><a href="?pagina=<?= ($pagina_actual - 1); ?>" class="page-link">&laquo; Anterior</a></li>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="<?= ($i == $pagina_actual) ? 'active' : ''; ?>">
                                <a href="?pagina=<?= $i; ?>" class="page-link"><?= $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($pagina_actual < $total_paginas): ?>
                            <li><a href="?pagina=<?= ($pagina_actual + 1); ?>" class="page-link">Siguiente &raquo;</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>