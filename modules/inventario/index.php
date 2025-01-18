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
class PaginationConfig
{
    public $items_per_page = 10;
    public $current_page;
    public $offset;
    public $search_term;
    public $sort_column;
    public $sort_direction;
    public $filter_category;
    public $filter_department;
    public $filter_stock_status;

    public function __construct()
    {
        $this->current_page = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $this->offset = ($this->current_page - 1) * $this->items_per_page;
        $this->search_term = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
        $this->sort_column = isset($_GET['columna']) ? $this->validateSortColumn($_GET['columna']) : 'nombre';
        $this->sort_direction = isset($_GET['direccion']) && strtolower($_GET['direccion']) === 'desc' ? 'DESC' : 'ASC';
        $this->filter_category = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
        $this->filter_department = isset($_GET['departamento']) ? (int)$_GET['departamento'] : 0;
        $this->filter_stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
    }

    private function validateSortColumn($column)
    {
        $allowed_columns = ['nombre', 'codigo_barras', 'stock', 'precio_costo', 'precio_venta', 'valor_total', 'departamento', 'categoria'];
        return in_array($column, $allowed_columns) ? $column : 'nombre';
    }
}

// Clase para manejar respuestas JSON
class ApiResponse
{
    public static function send($status, $message, $data = null)
    {
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
class InventoryManager
{
    private $pdo;
    private $user_id;
    private $config;

    public function __construct($pdo, $user_id, PaginationConfig $config)
    {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->config = $config;
    }

    public function getProducts()
    {
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
                categorias.nombre AS categoria,
                COALESCE(b.nombre, 'Sin asignar') as bodega,
                COALESCE(ip.ruta, '') as imagen_principal
            FROM inventario
            LEFT JOIN departamentos ON inventario.departamento_id = departamentos.id
            LEFT JOIN categorias ON inventario.categoria_id = categorias.id
            LEFT JOIN inventario_bodegas ib ON inventario.id = ib.producto_id
            LEFT JOIN bodegas b ON ib.bodega_id = b.id
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

    public function getTotalProducts()
    {
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

    public function getInventoryValue()
    {
        $query = "
            SELECT SUM(stock * precio_venta) as valor_total
            FROM inventario
            WHERE user_id = :user_id";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':user_id' => $this->user_id]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function getLowStockCount()
    {
        $query = "
            SELECT COUNT(*) 
            FROM inventario
            WHERE user_id = :user_id AND stock <= stock_minimo";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':user_id' => $this->user_id]);
        return $stmt->fetchColumn();
    }

    public function getCategories()
    {
        $stmt = $this->pdo->prepare("SELECT id, nombre FROM categorias WHERE estado = 'activo'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartments()
    {
        $stmt = $this->pdo->prepare("SELECT id, nombre FROM departamentos WHERE estado = 'activo'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteProduct($codigo_barras)
    {
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

    public function vaciarInventario()
    {
        try {
            $this->pdo->beginTransaction();

            // Primero eliminar las imágenes físicas
            $stmt = $this->pdo->prepare("
                SELECT ip.ruta 
                FROM imagenes_producto ip
                JOIN inventario i ON ip.producto_id = i.id
                WHERE i.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $imagenes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($imagenes as $ruta) {
                $ruta_completa = __DIR__ . '/../../' . $ruta;
                if (file_exists($ruta_completa)) {
                    unlink($ruta_completa);
                }
            }

            // Eliminar todos los productos del usuario
            $stmt = $this->pdo->prepare("DELETE FROM inventario WHERE user_id = ?");
            $stmt->execute([$this->user_id]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception('Error al vaciar el inventario: ' . $e->getMessage());
        }
    }

    public function getTotalMasVendidos() {
        try {
            // Primero verificamos si la columna existe
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM information_schema.COLUMNS 
                WHERE TABLE_NAME = 'inventario' 
                AND COLUMN_NAME = 'ventas_totales'
            ");
            $stmt->execute();
            $columnaExiste = $stmt->fetchColumn() > 0;

            if ($columnaExiste) {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) 
                    FROM inventario 
                    WHERE user_id = :user_id AND ventas_totales > 0
                ");
                $stmt->execute([':user_id' => $this->user_id]);
                return $stmt->fetchColumn();
            }
            
            // Si la columna no existe, retornar 0
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getValorPromedio() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(AVG(NULLIF(precio_venta, 0)), 0) 
                FROM inventario 
                WHERE user_id = :user_id
            ");
            $stmt->execute([':user_id' => $this->user_id]);
            return $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}

// Inicializar configuración y manejador
$config = new PaginationConfig();
$inventory_manager = new InventoryManager($pdo, $user_id, $config);

// Definir la función formatoMoneda
function formatoMoneda($monto)
{
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

            case 'vaciar_inventario':
                $inventory_manager->vaciarInventario();
                ApiResponse::send(true, 'Inventario vaciado exitosamente');
                break;

            default:
                ApiResponse::send(false, 'Acción no válida');
        }
    } catch (Exception $e) {
        ApiResponse::send(false, $e->getMessage());
    }
}

// Obtener datos necesarios con manejo de errores
try {
    $productos = $inventory_manager->getProducts();
    $total_productos = $inventory_manager->getTotalProducts();
    $valor_total_inventario = $inventory_manager->getInventoryValue();
    $cantidad_productos_bajos = $inventory_manager->getLowStockCount();
    $categorias = $inventory_manager->getCategories();
    $departamentos = $inventory_manager->getDepartments();
    $total_paginas = ceil($total_productos / $config->items_per_page);

    $valor_promedio = $inventory_manager->getValorPromedio();
} catch (Exception $e) {
    // Inicializar valores por defecto en caso de error
    $valor_promedio = 0;
    
    // Opcional: Registrar el error
    error_log("Error al obtener datos del inventario: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#1e293b',
                        accent: '#3b82f6'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>

    <div class="flex">
        <?php include '../../includes/sidebar.php'; ?>

        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-3xl font-bold text-gray-900 mb-8">Gestión de Inventario</h1>

                <!-- Barra de acciones mejorada -->
                <div class="flex flex-wrap gap-4 mb-8">
                    <div class="flex flex-wrap gap-3">
                        <a href="crear.php"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-plus mr-2"></i> Nuevo Producto
                        </a>
                        <a href="surtir.php"
                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-boxes mr-2"></i> Surtir
                        </a>
                        <a href="importar.php"
                            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-file-import mr-2"></i> Importar
                        </a>
                        <a href="catalogo.php"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-book mr-2"></i> Catálogo
                        </a>
                    </div>
                    <div class="flex gap-3 ml-auto">
                        <a href="reporte_stock_bajo.php"
                            class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-file-pdf mr-2"></i> Reporte Stock Bajo
                        </a>
                        <button type="button"
                            onclick="confirmarVaciarInventario()"
                            class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-trash-alt mr-2"></i> Vaciar
                        </button>
                    </div>
                </div>

                <?php if (isset($_SESSION['message'])): ?>
                    <div class="<?= $_SESSION['message_type'] === 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700' ?> p-4 mb-6 rounded-r">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <?php if ($_SESSION['message_type'] === 'success'): ?>
                                    <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" />
                                    </svg>
                                <?php else: ?>
                                    <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium"><?= htmlspecialchars($_SESSION['message']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php
                    // Limpiar el mensaje después de mostrarlo
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                <?php endif; ?>

                <!-- Filtros mejorados -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-all duration-300 mb-8">
                    <div class="p-4 bg-gradient-to-r from-blue-600 to-blue-700 rounded-t-xl">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-filter mr-2"></i>
                            Filtros de búsqueda
                        </h3>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <!-- Búsqueda con icono -->
                            <div class="relative">
                                <label for="busqueda" class="block text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-search text-gray-400 mr-2"></i>
                                    Buscar producto
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <input type="text"
                                        id="busqueda"
                                        name="busqueda"
                                        value="<?= htmlspecialchars($config->search_term) ?>"
                                        class="block w-full rounded-lg border-gray-300 pl-4 pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-300"
                                        placeholder="Nombre o código de barras">
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <i class="fas fa-barcode text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Selector de categoría mejorado -->
                            <div>
                                <label for="categoria" class="block text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-tags text-gray-400 mr-2"></i>
                                    Categoría
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <select id="categoria"
                                        name="categoria"
                                        class="block w-full rounded-lg border-gray-300 pl-4 pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none transition-all duration-300">
                                        <option value="">Todas las categorías</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?= $categoria['id'] ?>"
                                                <?= $config->filter_category == $categoria['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($categoria['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Selector de departamento mejorado -->
                            <div>
                                <label for="departamento" class="block text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-building text-gray-400 mr-2"></i>
                                    Departamento
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <select id="departamento"
                                        name="departamento"
                                        class="block w-full rounded-lg border-gray-300 pl-4 pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none transition-all duration-300">
                                        <option value="">Todos los departamentos</option>
                                        <?php foreach ($departamentos as $departamento): ?>
                                            <option value="<?= $departamento['id'] ?>"
                                                <?= $config->filter_department == $departamento['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($departamento['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Selector de estado de stock -->
                            <div>
                                <label for="stock_status" class="block text-sm font-medium text-gray-700 mb-2 flex items-center">
                                    <i class="fas fa-box text-gray-400 mr-2"></i>
                                    Estado del stock
                                </label>
                                <div class="relative rounded-lg shadow-sm">
                                    <select id="stock_status"
                                        name="stock_status"
                                        class="block w-full rounded-lg border-gray-300 pl-4 pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none transition-all duration-300">
                                        <option value="">Todos los estados</option>
                                        <option value="bajo" <?= $config->filter_stock_status === 'bajo' ? 'selected' : '' ?>>
                                            Stock Bajo
                                        </option>
                                        <option value="agotado" <?= $config->filter_stock_status === 'agotado' ? 'selected' : '' ?>>
                                            Agotado
                                        </option>
                                        <option value="normal" <?= $config->filter_stock_status === 'normal' ? 'selected' : '' ?>>
                                            Normal
                                        </option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción para los filtros -->
                        <div class="flex items-center justify-end space-x-4 mt-6 pt-6 border-t border-gray-200">
                            <button type="button"
                                onclick="limpiarFiltros()"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300">
                                <i class="fas fa-undo mr-2"></i>
                                Limpiar filtros
                            </button>
                            <button type="button"
                                onclick="aplicarFiltros()"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300">
                                <i class="fas fa-filter mr-2"></i>
                                Aplicar filtros
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla de productos mejorada -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <!-- Encabezado de tabla con fondo distintivo -->
                    <div class="p-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Listado de Productos</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Imagen
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <a href="?columna=nombre&direccion=<?= $config->sort_column === 'nombre' && $config->sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>"
                                            class="group inline-flex items-center hover:text-blue-600 transition-colors">
                                            Nombre
                                            <span class="ml-2 opacity-0 group-hover:opacity-100">
                                                <i class="fas fa-sort text-gray-400"></i>
                                            </span>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <a href="?columna=codigo_barras&direccion=<?= $config->sort_column === 'codigo_barras' && $config->sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>"
                                            class="group inline-flex items-center hover:text-blue-600 transition-colors">
                                            Código
                                            <span class="ml-2 opacity-0 group-hover:opacity-100">
                                                <i class="fas fa-sort text-gray-400"></i>
                                            </span>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <a href="?columna=stock&direccion=<?= $config->sort_column === 'stock' && $config->sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>"
                                            class="group inline-flex items-center hover:text-blue-600 transition-colors">
                                            Stock
                                            <span class="ml-2 opacity-0 group-hover:opacity-100">
                                                <i class="fas fa-sort text-gray-400"></i>
                                            </span>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <a href="?columna=precio_venta&direccion=<?= $config->sort_column === 'precio_venta' && $config->sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>"
                                            class="group inline-flex items-center hover:text-blue-600 transition-colors">
                                            Precio
                                            <span class="ml-2 opacity-0 group-hover:opacity-100">
                                                <i class="fas fa-sort text-gray-400"></i>
                                            </span>
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Categoría
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Bodega
                                    </th>
                                    <th scope="col" class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($productos as $producto): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex-shrink-0 h-14 w-14">
                                                <?php if ($producto['imagen_principal']): ?>
                                                    <img class="h-14 w-14 rounded-lg object-cover shadow-sm hover:shadow-md transition-shadow"
                                                        src="../../<?= htmlspecialchars($producto['imagen_principal']) ?>"
                                                        alt="<?= htmlspecialchars($producto['nombre']) ?>">
                                                <?php else: ?>
                                                    <div class="h-14 w-14 rounded-lg bg-gray-100 flex items-center justify-center">
                                                        <i class="fas fa-image text-gray-400 text-xl"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900 hover:text-blue-600 transition-colors">
                                                <?= htmlspecialchars($producto['nombre']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-600">
                                                <?= htmlspecialchars($producto['codigo_barras']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $stockClass = '';
                                            $stockBadge = '';
                                            if ($producto['stock'] === 0) {
                                                $stockClass = 'bg-red-100 text-red-800';
                                                $stockBadge = 'Agotado';
                                            } elseif ($producto['stock'] <= $producto['stock_minimo']) {
                                                $stockClass = 'bg-yellow-100 text-yellow-800';
                                                $stockBadge = 'Bajo';
                                            } else {
                                                $stockClass = 'bg-green-100 text-green-800';
                                                $stockBadge = 'Normal';
                                            }
                                            ?>
                                            <div class="flex items-center">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $stockClass ?>">
                                                    <?= $producto['stock'] ?>
                                                </span>
                                                <span class="ml-2 text-xs text-gray-500"><?= $stockBadge ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 font-medium">
                                                <?= formatoMoneda($producto['precio_venta']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-600">
                                                <?= htmlspecialchars($producto['categoria']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-600">
                                                <?= htmlspecialchars($producto['bodega']) ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center space-x-3">
                                                <a href="ver.php?codigo_barras=<?= urlencode($producto['codigo_barras']) ?>"
                                                    class="text-blue-600 hover:text-blue-900 transition-colors"
                                                    title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="modificar.php?codigo_barras=<?= urlencode($producto['codigo_barras']) ?>"
                                                    class="text-green-600 hover:text-green-900 transition-colors"
                                                    title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="confirmarEliminacion('<?= htmlspecialchars($producto['codigo_barras']) ?>')"
                                                    class="text-red-600 hover:text-red-900 transition-colors"
                                                    title="Eliminar">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer de la tabla con información adicional -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>Mostrando <?= count($productos) ?> de <?= $total_productos ?> productos</span>
                        </div>
                    </div>
                </div>

                <!-- Paginación mejorada -->
                <div class="mt-6">
                    <nav class="flex justify-center" aria-label="Pagination">
                        <ul class="flex items-center space-x-2">
                            <?php if ($config->current_page > 1): ?>
                                <li>
                                    <a href="?pagina=<?= $config->current_page - 1 ?>"
                                        class="px-3 py-2 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                <li>
                                    <a href="?pagina=<?= $i ?>"
                                        class="<?= $config->current_page === $i
                                                    ? 'bg-blue-600 text-white'
                                                    : 'bg-white text-gray-700 hover:bg-gray-50' ?> 
                                              px-3 py-2 rounded-md border border-gray-300 text-sm font-medium">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($config->current_page < $total_paginas): ?>
                                <li>
                                    <a href="?pagina=<?= $config->current_page + 1 ?>"
                                        class="px-3 py-2 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Aquí iría el JavaScript actualizado con las nuevas clases de Tailwind -->
    <script>
        // Función para confirmar eliminación
        function confirmarEliminacion(codigoBarras) {
            Swal.fire({
                title: '¿Eliminar producto?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash-alt mr-2"></i>Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
                customClass: {
                    confirmButton: 'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium',
                    cancelButton: 'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium',
                    popup: 'rounded-lg'
                }
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

        // Función para limpiar todos los filtros
        function limpiarFiltros() {
            document.getElementById('busqueda').value = '';
            document.getElementById('categoria').value = '';
            document.getElementById('departamento').value = '';
            document.getElementById('stock_status').value = '';
            aplicarFiltros();
        }

        // Función mejorada para aplicar filtros
        function aplicarFiltros() {
            const params = new URLSearchParams(window.location.search);

            // Obtener valores de los filtros
            const busqueda = document.getElementById('busqueda').value;
            const categoria = document.getElementById('categoria').value;
            const departamento = document.getElementById('departamento').value;
            const stockStatus = document.getElementById('stock_status').value;

            // Actualizar o eliminar parámetros según corresponda
            if (busqueda) params.set('busqueda', busqueda);
            else params.delete('busqueda');
            if (categoria) params.set('categoria', categoria);
            else params.delete('categoria');
            if (departamento) params.set('departamento', departamento);
            else params.delete('departamento');
            if (stockStatus) params.set('stock_status', stockStatus);
            else params.delete('stock_status');

            // Mantener la página actual si existe
            const paginaActual = params.get('pagina');
            if (paginaActual) params.set('pagina', '1');

            // Redirigir con los nuevos parámetros
            window.location.search = params.toString();
        }

        // Event listeners para aplicar filtros al presionar Enter en el campo de búsqueda
        document.getElementById('busqueda').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                aplicarFiltros();
            }
        });

        // Aplicar filtros al cambiar los selectores
        ['categoria', 'departamento', 'stock_status'].forEach(filterId => {
            document.getElementById(filterId).addEventListener('change', aplicarFiltros);
        });

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

        // Función para confirmar vaciado de inventario
        function confirmarVaciarInventario() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "¡Se eliminarán todos los productos del inventario! Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash-alt mr-2"></i>Sí, vaciar inventario',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
                customClass: {
                    confirmButton: 'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium',
                    cancelButton: 'inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    vaciarInventario();
                }
            });
        }

        // Función para vaciar inventario
        function vaciarInventario() {
            $.ajax({
                url: '',
                method: 'POST',
                data: {
                    action: 'vaciar_inventario'
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.status) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Inventario vaciado!',
                            text: 'Todos los productos han sido eliminados correctamente.',
                            showConfirmButton: false,
                            timer: 1500,
                            customClass: {
                                popup: 'rounded-lg'
                            }
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Hubo un problema al vaciar el inventario',
                            customClass: {
                                popup: 'rounded-lg'
                            }
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al procesar la solicitud',
                        customClass: {
                            popup: 'rounded-lg'
                        }
                    });
                }
            });
        }
    </script>

    <!-- Estilos adicionales para mejorar la UI -->
    <style>
        .swal2-popup {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .swal2-styled.swal2-confirm,
        .swal2-styled.swal2-cancel {
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .tooltip {
            position: absolute;
            background: #1f2937;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            z-index: 50;
            white-space: nowrap;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .tooltip::before {
            content: '';
            position: absolute;
            top: -4px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 0 6px 6px 6px;
            border-style: solid;
            border-color: transparent transparent #1f2937 transparent;
        }
    </style>
</body>

</html>