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

// Configuración de paginación y filtros
class PaginationConfig {
    public $items_per_page = 10;
    public $current_page;
    public $offset;
    public $search_term;
    public $sort_column;
    public $sort_direction;
    public $filter_category;
    public $filter_department;
    public $filter_stock_status;

    public function __construct() {
        $this->current_page = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $this->offset = ($this->current_page - 1) * $this->items_per_page;
        $this->search_term = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
        $this->sort_column = isset($_GET['columna']) ? $this->validateSortColumn($_GET['columna']) : 'nombre';
        $this->sort_direction = isset($_GET['direccion']) && strtolower($_GET['direccion']) === 'desc' ? 'DESC' : 'ASC';
        $this->filter_category = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
        $this->filter_department = isset($_GET['departamento']) ? (int)$_GET['departamento'] : 0;
        $this->filter_stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
    }

    private function validateSortColumn($column) {
        $allowed_columns = ['nombre', 'codigo_barras', 'stock', 'precio_costo', 'precio_venta', 'valor_total', 'departamento', 'categoria'];
        return in_array($column, $allowed_columns) ? $column : 'nombre';
    }
}

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

// Clase para manejar el inventario
class InventoryManager {
    private $pdo;
    private $user_id;
    private $config;

    public function __construct($pdo, $user_id, PaginationConfig $config) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->config = $config;
    }

    public function getProducts() {
        $where_conditions = ["inventario.user_id = :user_id"];
        $params = [':user_id' => $this->user_id];

        if (!empty($this->config->search_term)) {
            $where_conditions[] = "(inventario.nombre LIKE :busqueda OR inventario.codigo_barras LIKE :busqueda)";
            $params[':busqueda'] = "%{$this->config->search_term}%";
        }

        if ($this->config->filter_category) {
            $where_conditions[] = "categoria_id = :categoria_id";
            $params[':categoria_id'] = $this->config->filter_category;
        }

        if ($this->config->filter_department) {
            $where_conditions[] = "departamento_id = :departamento_id";
            $params[':departamento_id'] = $this->config->filter_department;
        }

        if ($this->config->filter_stock_status === 'bajo') {
            $where_conditions[] = "stock <= stock_minimo";
        } elseif ($this->config->filter_stock_status === 'agotado') {
            $where_conditions[] = "stock = 0";
        }

        $where_clause = implode(" AND ", $where_conditions);

        $query = "
            SELECT 
                inventario.*, 
                (inventario.stock * inventario.precio_venta) AS valor_total,
                departamentos.nombre AS departamento,
                categorias.nombre AS categoria,
                COALESCE(ip.ruta, '') as imagen_principal
            FROM inventario
            LEFT JOIN departamentos ON inventario.departamento_id = departamentos.id
            LEFT JOIN categorias ON inventario.categoria_id = categorias.id
            LEFT JOIN imagenes_producto ip ON inventario.id = ip.producto_id AND ip.es_principal = 1
            WHERE {$where_clause}
            ORDER BY {$this->config->sort_column} {$this->config->sort_direction}
            LIMIT :limit OFFSET :offset";

        $limit = (int)$this->config->items_per_page;
        $offset = (int)$this->config->offset;

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalProducts() {
        $where_conditions = ["user_id = :user_id"];
        $params = [':user_id' => $this->user_id];

        if (!empty($this->config->search_term)) {
            $where_conditions[] = "(nombre LIKE :busqueda OR codigo_barras LIKE :busqueda)";
            $params[':busqueda'] = "%{$this->config->search_term}%";
        }

        if ($this->config->filter_category) {
            $where_conditions[] = "categoria_id = :categoria_id";
            $params[':categoria_id'] = $this->config->filter_category;
        }

        if ($this->config->filter_department) {
            $where_conditions[] = "departamento_id = :departamento_id";
            $params[':departamento_id'] = $this->config->filter_department;
        }

        $where_clause = implode(" AND ", $where_conditions);
        $query = "SELECT COUNT(*) FROM inventario WHERE {$where_clause}";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function getInventoryValue() {
        $query = "
            SELECT SUM(stock * precio_venta) as valor_total
            FROM inventario
            WHERE user_id = :user_id";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':user_id' => $this->user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getLowStockCount() {
        $query = "
            SELECT COUNT(*) 
            FROM inventario
            WHERE user_id = :user_id AND stock <= stock_minimo";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':user_id' => $this->user_id]);
        return $stmt->fetchColumn();
    }

    public function getCategories() {
        $stmt = $this->pdo->prepare("SELECT id, nombre FROM categorias WHERE estado = 'activo'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartments() {
        $stmt = $this->pdo->prepare("SELECT id, nombre FROM departamentos WHERE estado = 'activo'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteProduct($codigo_barras) {
        try {
            $this->pdo->beginTransaction();

            // Obtener ID del producto
            $stmt = $this->pdo->prepare("SELECT id FROM inventario WHERE codigo_barras = ? AND user_id = ?");
            $stmt->execute([$codigo_barras, $this->user_id]);
            $producto_id = $stmt->fetchColumn();

            if (!$producto_id) {
                throw new Exception("Producto no encontrado");
            }

            // Eliminar imágenes físicas
            $stmt = $this->pdo->prepare("SELECT ruta FROM imagenes_producto WHERE producto_id = ?");
            $stmt->execute([$producto_id]);
            $imagenes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($imagenes as $ruta) {
                $ruta_completa = __DIR__ . '/../../' . $ruta;
                if (file_exists($ruta_completa)) {
                    unlink($ruta_completa);
                }
            }

            // Eliminar el producto (las imágenes se eliminarán en cascada)
            $stmt = $this->pdo->prepare("DELETE FROM inventario WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$producto_id, $this->user_id]);

            if (!$result) {
                throw new Exception("Error al eliminar el producto");
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error al eliminar producto: " . $e->getMessage());
            throw $e;
        }
    }
}

// Inicializar configuración y manejador
$config = new PaginationConfig();
$inventory_manager = new InventoryManager($pdo, $user_id, $config);

// Definir la función formatoMoneda
function formatoMoneda($monto) {
    return '$' . number_format($monto, 2, ',', '.');
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'delete_producto':
                $codigo_barras = $_POST['codigo_barras'];
                $inventory_manager->deleteProduct($codigo_barras);
                ApiResponse::send(true, 'Producto eliminado exitosamente');
                break;
                
            default:
                ApiResponse::send(false, 'Acción no válida');
        }
    } catch (Exception $e) {
        ApiResponse::send(false, $e->getMessage());
    }
}

// Obtener datos necesarios
$productos = $inventory_manager->getProducts();
$total_productos = $inventory_manager->getTotalProducts();
$valor_total_inventario = $inventory_manager->getInventoryValue();
$cantidad_productos_bajos = $inventory_manager->getLowStockCount();
$categorias = $inventory_manager->getCategories();
$departamentos = $inventory_manager->getDepartments();
$total_paginas = ceil($total_productos / $config->items_per_page);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <style>
        .inventory-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .summary-icon.total {
            background: #e3f2fd;
            color: #1976d2;
        }

        .summary-icon.low {
            background: #fff3e0;
            color: #f57c00;
        }

        .summary-info h3 {
            margin: 0;
            font-size: 1.5em;
            color: #333;
        }

        .summary-info p {
            margin: 5px 0 0;
            color: #666;
        }

        .filters {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-weight: 500;
            color: #666;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
        }

        .product-table {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .product-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .product-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .product-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .product-table tr:hover {
            background: #f8f9fa;
        }

        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 4px;
            object-fit: cover;
        }

        .stock-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .stock-normal {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .stock-low {
            background: #fff3e0;
            color: #f57c00;
        }

        .stock-out {
            background: #ffebee;
            color: #c62828;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #1976d2;
        }

        .btn-delete {
            background: #dc3545;
        }

        .btn-view {
            background: #28a745;
        }

        .btn-action:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #007bff;
            text-decoration: none;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: #e9ecef;
        }

        .pagination a.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        @media (max-width: 768px) {
            .inventory-summary {
                grid-template-columns: 1fr;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .product-table {
                overflow-x: auto;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
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
                    <a href="#">Ayuda</a>
                    <a href="#">Soporte</a>
                </div>
            </div>
        </nav>

        <div class="main-body">
            <h2>Gestión de Inventario</h2>
            
            <div class="inventory-summary">
                <div class="summary-card">
                    <div class="summary-icon total">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="summary-info">
                        <h3><?= formatoMoneda($valor_total_inventario) ?></h3>
                        <p>Valor Total del Inventario</p>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon low">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="summary-info">
                        <h3><?= $cantidad_productos_bajos ?></h3>
                        <p>Productos con Stock Bajo</p>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <a href="crear.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Producto
                </a>
                <a href="surtir.php" class="btn btn-primary">
                    <i class="fas fa-boxes"></i> Surtir Inventario
                </a>
                <a href="importar.php" class="btn btn-primary">
                    <i class="fas fa-file-import"></i> Importar Productos
                </a>
                <a href="catalogo.php" class="btn btn-primary">
                    <i class="fas fa-book"></i> Ver Catálogo
                </a>
                <a href="reporte_stock_bajo.php" class="btn btn-warning">
                    <i class="fas fa-file-pdf"></i> Reporte Stock Bajo
                </a>
            </div>

            <div class="filters">
                <div class="filter-group">
                    <label for="busqueda">Buscar:</label>
                    <input type="text" id="busqueda" name="busqueda" 
                           value="<?= htmlspecialchars($config->search_term) ?>" 
                           placeholder="Nombre o código de barras">
                </div>
                
                <div class="filter-group">
                    <label for="categoria">Categoría:</label>
                    <select id="categoria" name="categoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= $categoria['id'] ?>" 
                                <?= $config->filter_category == $categoria['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($categoria['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="departamento">Departamento:</label>
                    <select id="departamento" name="departamento">
                        <option value="">Todos los departamentos</option>
                        <?php foreach ($departamentos as $departamento): ?>
                            <option value="<?= $departamento['id'] ?>" 
                                <?= $config->filter_department == $departamento['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($departamento['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="stock_status">Estado de Stock:</label>
                    <select id="stock_status" name="stock_status">
                        <option value="">Todos</option>
                        <option value="bajo" <?= $config->filter_stock_status === 'bajo' ? 'selected' : '' ?>>
                            Stock Bajo
                        </option>
                        <option value="agotado" <?= $config->filter_stock_status === 'agotado' ? 'selected' : '' ?>>
                            Agotado
                        </option>
                    </select>
                </div>
            </div>

            <div class="product-table">
                <table>
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>
                                <a href="?columna=nombre&direccion=<?= $config->sort_column === 'nombre' && $config->sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">
                                    Nombre
                                    <?php if ($config->sort_column === 'nombre'): ?>
                                        <i class="fas fa-sort-<?= $config->sort_direction === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?columna=codigo_barras&direccion=<?= $config->sort_column === 'codigo_barras' && $config->sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">
                                    Código
                                    <?php if ($config->sort_column === 'codigo_barras'): ?>
                                        <i class="fas fa-sort-<?= $config->sort_direction === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?columna=stock&direccion=<?= $config->sort_column === 'stock' && $config->sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">
                                    Stock
                                    <?php if ($config->sort_column === 'stock'): ?>
                                        <i class="fas fa-sort-<?= $config->sort_direction === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?columna=precio_venta&direccion=<?= $config->sort_column === 'precio_venta' && $config->sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">
                                    Precio
                                    <?php if ($config->sort_column === 'precio_venta'): ?>
                                        <i class="fas fa-sort-<?= $config->sort_direction === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Categoría</th>
                            <th>Departamento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td>
                                    <?php if ($producto['imagen_principal']): ?>
                                        <img src="../../<?= htmlspecialchars($producto['imagen_principal']) ?>" 
                                             alt="<?= htmlspecialchars($producto['nombre']) ?>" 
                                             class="product-image">
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                <td><?= htmlspecialchars($producto['codigo_barras']) ?></td>
                                <td>
                                    <span class="stock-status <?= 
                                        $producto['stock'] === 0 ? 'stock-out' : 
                                        ($producto['stock'] <= $producto['stock_minimo'] ? 'stock-low' : 'stock-normal') 
                                    ?>">
                                        <?= $producto['stock'] ?>
                                    </span>
                                </td>
                                <td><?= formatoMoneda($producto['precio_venta']) ?></td>
                                <td><?= htmlspecialchars($producto['categoria']) ?></td>
                                <td><?= htmlspecialchars($producto['departamento']) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="modificar.php?codigo_barras=<?= urlencode($producto['codigo_barras']) ?>" 
                                           class="btn-action btn-edit" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn-action btn-delete" 
                                                onclick="confirmarEliminacion('<?= htmlspecialchars($producto['codigo_barras']) ?>')"
                                                title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <a href="ver.php?codigo_barras=<?= urlencode($producto['codigo_barras']) ?>" 
                                           class="btn-action btn-view" title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="?pagina=<?= $i ?>&busqueda=<?= urlencode($config->search_term) ?>&columna=<?= $config->sort_column ?>&direccion=<?= $config->sort_direction ?>" 
                       class="<?= $config->current_page === $i ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Función para confirmar eliminación
        function confirmarEliminacion(codigoBarras) {
            Swal.fire({
                title: '¿Eliminar producto?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    eliminarProducto(codigoBarras);
                }
            });
        }

        // Función para eliminar producto
        function eliminarProducto(codigoBarras) {
            $.ajax({
                url: '',
                method: 'POST',
                data: {
                    action: 'delete_producto',
                    codigo_barras: codigoBarras
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.status) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: response.message,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Hubo un problema al eliminar el producto', 'error');
                }
            });
        }

        // Manejar filtros
        let timeoutId;
        const filtros = ['busqueda', 'categoria', 'departamento', 'stock_status'];
        
        filtros.forEach(filtro => {
            document.getElementById(filtro).addEventListener('input', function() {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => aplicarFiltros(), 500);
            });
            
            document.getElementById(filtro).addEventListener('change', function() {
                aplicarFiltros();
            });
        });

        function aplicarFiltros() {
            const params = new URLSearchParams(window.location.search);
            
            filtros.forEach(filtro => {
                const valor = document.getElementById(filtro).value;
                if (valor) {
                    params.set(filtro, valor);
                } else {
                    params.delete(filtro);
                }
            });
            
            window.location.search = params.toString();
        }

        // Mostrar tooltips en hover
        const tooltips = document.querySelectorAll('[title]');
        tooltips.forEach(el => {
            el.addEventListener('mouseenter', e => {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = e.target.getAttribute('title');
                document.body.appendChild(tooltip);
                
                const rect = e.target.getBoundingClientRect();
                tooltip.style.top = rect.bottom + 5 + 'px';
                tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
                
                e.target.addEventListener('mouseleave', () => tooltip.remove());
            });
        });
    </script>
</body>
</html>