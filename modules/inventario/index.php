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
    public $items_per_page;
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
        $this->items_per_page = isset($_GET['items_per_page']) ? $this->validateItemsPerPage($_GET['items_per_page']) : 10;
        $this->current_page = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
        $this->offset = ($this->current_page - 1) * $this->items_per_page;
        $this->search_term = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
        $this->sort_column = isset($_GET['columna']) ? $this->validateSortColumn($_GET['columna']) : 'nombre';
        $this->sort_direction = isset($_GET['direccion']) && strtolower($_GET['direccion']) === 'desc' ? 'DESC' : 'ASC';
        $this->filter_category = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
        $this->filter_department = isset($_GET['departamento']) ? (int)$_GET['departamento'] : 0;
        $this->filter_stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';
    }

    private function validateItemsPerPage($items)
    {
        $allowed_items = [10, 20, 30, 80, 100];
        $items = (int)$items;
        return in_array($items, $allowed_items) ? $items : 10;
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
                GROUP_CONCAT(DISTINCT b.nombre) as bodegas,
                COALESCE(ip.ruta, '') as imagen_principal
            FROM inventario
            LEFT JOIN departamentos ON inventario.departamento_id = departamentos.id
            LEFT JOIN categorias ON inventario.categoria_id = categorias.id
            LEFT JOIN inventario_bodegas ib ON inventario.id = ib.producto_id
            LEFT JOIN bodegas b ON ib.bodega_id = b.id
            LEFT JOIN imagenes_producto ip ON inventario.id = ip.producto_id AND ip.es_principal = 1
            WHERE {$where_clause}
            GROUP BY inventario.id
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

            // Verificar si tiene ventas o cotizaciones asociadas
            $stmt = $this->pdo->prepare("
                SELECT 
                    CASE 
                        WHEN EXISTS (SELECT 1 FROM venta_detalles WHERE producto_id = ?) THEN 1
                        WHEN EXISTS (SELECT 1 FROM cotizacion_detalles WHERE producto_id = ?) THEN 1
                        ELSE 0
                    END as tiene_referencias
            ");
            $stmt->execute([$producto_id, $producto_id]);
            $tiene_referencias = $stmt->fetchColumn();

            if ($tiene_referencias) {
                throw new Exception("No se puede eliminar el producto porque tiene ventas o cotizaciones asociadas");
            }

            // Eliminar registros relacionados en orden
            // 1. Eliminar registros de inventario_bodegas
            $stmt = $this->pdo->prepare("DELETE FROM inventario_bodegas WHERE producto_id = ?");
            $stmt->execute([$producto_id]);

            // 2. Eliminar imágenes físicas y sus registros
            $stmt = $this->pdo->prepare("SELECT ruta FROM imagenes_producto WHERE producto_id = ?");
            $stmt->execute([$producto_id]);
            $imagenes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($imagenes as $ruta) {
                $ruta_completa = __DIR__ . '/../../' . $ruta;
                if (file_exists($ruta_completa)) {
                    unlink($ruta_completa);
                }
            }

            $stmt = $this->pdo->prepare("DELETE FROM imagenes_producto WHERE producto_id = ?");
            $stmt->execute([$producto_id]);

            // 3. Finalmente eliminar el producto
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

            // Obtener productos con referencias (ventas o cotizaciones)
            $query = "SELECT DISTINCT i.id, i.nombre, i.codigo_barras 
                     FROM inventario i
                     LEFT JOIN venta_detalles vd ON i.id = vd.producto_id 
                     LEFT JOIN cotizacion_detalles cd ON i.id = cd.producto_id
                     WHERE i.user_id = ? AND (vd.id IS NOT NULL OR cd.id IS NOT NULL)";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$this->user_id]);
            $productos_con_referencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener IDs de productos sin referencias
            $query = "SELECT i.id 
                     FROM inventario i 
                     LEFT JOIN venta_detalles vd ON i.id = vd.producto_id 
                     LEFT JOIN cotizacion_detalles cd ON i.id = cd.producto_id
                     WHERE i.user_id = ? AND vd.id IS NULL AND cd.id IS NULL";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$this->user_id]);
            $productos_eliminables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($productos_eliminables)) {
                throw new Exception("No hay productos que se puedan eliminar. Todos los productos tienen ventas o cotizaciones asociadas.", 2);
            }

            // Eliminar imágenes físicas de productos sin referencias
            if (!empty($productos_eliminables)) {
                $query = "SELECT ruta 
                         FROM imagenes_producto 
                         WHERE producto_id IN (" . implode(',', $productos_eliminables) . ")";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute();
                $imagenes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($imagenes as $ruta) {
                    $ruta_completa = __DIR__ . '/../../' . $ruta;
                    if (file_exists($ruta_completa)) {
                        unlink($ruta_completa);
                    }
                }

                // Eliminar productos sin referencias
                $query = "DELETE FROM inventario 
                         WHERE id IN (" . implode(',', $productos_eliminables) . ") 
                         AND user_id = ?";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([$this->user_id]);
            }

            $this->pdo->commit();
            $mensaje = empty($productos_con_referencias) 
                ? 'Inventario vaciado exitosamente' 
                : 'Se eliminaron los productos sin ventas ni cotizaciones asociadas';

            return [
                'success' => true,
                'message' => $mensaje,
                'productos_eliminados' => count($productos_eliminables),
                'productos_con_referencias' => $productos_con_referencias
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
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
                $result = $inventory_manager->vaciarInventario();
                if ($result['success']) {
                    ApiResponse::send(true, $result['message'], [
                        'productos_eliminados' => $result['productos_eliminados']
                    ]);
                } else {
                    ApiResponse::send(false, $result['message'], [
                        'productos_con_referencias' => $result['productos_con_referencias'] ?? null
                    ]);
                }
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
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-plus mr-2"></i> Nuevo Producto
                        </a>
                        <a href="surtir.php"
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-boxes mr-2"></i> Surtir
                        </a>
                        <a href="importar.php"
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-lg hover:from-purple-700 hover:to-purple-800 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-file-import mr-2"></i> Importar
                        </a>
                        <a href="catalogo.php"
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-lg hover:from-indigo-700 hover:to-indigo-800 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-book mr-2"></i> Catálogo
                        </a>
                    </div>
                    <div class="flex gap-3 ml-auto">
                        <!-- Botón de exportar PDF -->
                        <a href="exportar.php?formato=pdf" 
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-all transform hover:scale-105 shadow-sm">
                            <i class="fas fa-file-pdf mr-2"></i> Exportar PDF
                        </a>
                        <!-- Botón de vaciar inventario -->
                        <button type="button"
                            onclick="confirmarVaciarInventario()"
                            class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:from-red-700 hover:to-red-800 transition-all transform hover:scale-105 shadow-sm">
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
                    <div class="p-4 bg-gradient-to-r from-blue-600 to-blue-800 rounded-t-xl">
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
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="p-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-box mr-2 text-blue-600"></i>
                            Listado de Productos
                        </h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
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
                                    <tr class="hover:bg-blue-50 transition-colors duration-200">
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
                                                <?php 
                                                if (!empty($producto['bodegas'])) {
                                                    $bodegas = explode(',', $producto['bodegas']);
                                                    echo htmlspecialchars(implode(', ', array_unique($bodegas)));
                                                } else {
                                                    echo 'Sin asignar';
                                                }
                                                ?>
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
                    <div class="px-6 py-4 bg-gradient-to-r from-gray-50 to-gray-100 border-t border-gray-200">
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span class="font-medium">
                                Mostrando <?= count($productos) ?> de <?= $total_productos ?> productos
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Paginación mejorada -->
                <div class="mt-6 flex items-center justify-between bg-white p-4 rounded-lg shadow-sm">
                    <!-- Selector de items por página -->
                    <div class="flex items-center space-x-2">
                        <label for="items_per_page" class="text-sm text-gray-600">Mostrar:</label>
                        <select id="items_per_page" 
                                class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                onchange="cambiarItemsPorPagina(this.value)">
                            <?php
                            $opciones = [10, 20, 30, 80, 100];
                            foreach ($opciones as $opcion) {
                                $selected = $config->items_per_page == $opcion ? 'selected' : '';
                                echo "<option value=\"{$opcion}\" {$selected}>{$opcion}</option>";
                            }
                            ?>
                        </select>
                        <span class="text-sm text-gray-600">items</span>
                    </div>

                    <!-- Paginación mejorada -->
                    <nav class="flex items-center justify-center space-x-1" aria-label="Pagination">
                        <?php
                        $rango = 2; // Número de páginas a mostrar antes y después de la página actual
                        $inicio_rango = max(1, $config->current_page - $rango);
                        $fin_rango = min($total_paginas, $config->current_page + $rango);

                        // Botón Primera página
                        if ($config->current_page > 1):
                        ?>
                            <a href="?pagina=1<?= isset($_GET['items_per_page']) ? '&items_per_page=' . $_GET['items_per_page'] : '' ?>" 
                               class="px-3 py-2 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?pagina=<?= $config->current_page - 1 ?><?= isset($_GET['items_per_page']) ? '&items_per_page=' . $_GET['items_per_page'] : '' ?>" 
                               class="px-3 py-2 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        // Mostrar puntos suspensivos al inicio si es necesario
                        if ($inicio_rango > 1) {
                            echo '<span class="px-3 py-2 text-gray-500">...</span>';
                        }

                        // Mostrar páginas
                        for ($i = $inicio_rango; $i <= $fin_rango; $i++):
                            $is_current = $config->current_page === $i;
                        ?>
                            <a href="?pagina=<?= $i ?><?= isset($_GET['items_per_page']) ? '&items_per_page=' . $_GET['items_per_page'] : '' ?>"
                               class="<?= $is_current 
                                         ? 'bg-blue-600 text-white hover:bg-blue-700' 
                                         : 'bg-white text-gray-700 hover:bg-gray-50' ?> 
                                      px-3 py-2 rounded-md border border-gray-300 text-sm font-medium">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php
                        // Mostrar puntos suspensivos al final si es necesario
                        if ($fin_rango < $total_paginas) {
                            echo '<span class="px-3 py-2 text-gray-500">...</span>';
                        }

                        // Botón Última página
                        if ($config->current_page < $total_paginas):
                        ?>
                            <a href="?pagina=<?= $config->current_page + 1 ?><?= isset($_GET['items_per_page']) ? '&items_per_page=' . $_GET['items_per_page'] : '' ?>" 
                               class="px-3 py-2 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?pagina=<?= $total_paginas ?><?= isset($_GET['items_per_page']) ? '&items_per_page=' . $_GET['items_per_page'] : '' ?>" 
                               class="px-3 py-2 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>

                    <!-- Información de paginación -->
                    <div class="text-sm text-gray-600">
                        Mostrando <?= ($config->current_page - 1) * $config->items_per_page + 1 ?> 
                        a <?= min($config->current_page * $config->items_per_page, $total_productos) ?> 
                        de <?= $total_productos ?> resultados
                    </div>
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
            
            // Mantener items_per_page si existe
            const itemsPerPage = params.get('items_per_page');
            
            // Obtener valores de los filtros
            const busqueda = document.getElementById('busqueda').value;
            const categoria = document.getElementById('categoria').value;
            const departamento = document.getElementById('departamento').value;
            const stockStatus = document.getElementById('stock_status').value;

            // Limpiar parámetros existentes
            params.forEach((value, key) => {
                if (key !== 'items_per_page') {
                    params.delete(key);
                }
            });

            // Agregar nuevos parámetros
            if (busqueda) params.set('busqueda', busqueda);
            if (categoria) params.set('categoria', categoria);
            if (departamento) params.set('departamento', departamento);
            if (stockStatus) params.set('stock_status', stockStatus);
            
            // Establecer la página en 1
            params.set('pagina', '1');

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
                        let mensaje = `Se eliminaron ${response.data.productos_eliminados} productos correctamente.`;
                        
                        if (response.data.productos_con_referencias && response.data.productos_con_referencias.length > 0) {
                            let productosHtml = response.data.productos_con_referencias.map(p => 
                                `<li>${p.nombre} (${p.codigo_barras})</li>`
                            ).join('');
                            
                            Swal.fire({
                                icon: 'info',
                                title: 'Inventario parcialmente vaciado',
                                html: `
                                    <p>${mensaje}</p>
                                    <p class="mt-4">Los siguientes productos tienen ventas o cotizaciones asociadas y se mantuvieron en el inventario:</p>
                                    <ul class="text-left mt-4 list-disc pl-5">
                                        ${productosHtml}
                                    </ul>
                                    <p class="mt-4">Estos productos se mantienen para preservar el historial de ventas y cotizaciones.</p>
                                `,
                                customClass: {
                                    popup: 'rounded-lg',
                                    htmlContainer: 'text-sm'
                                }
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Inventario vaciado!',
                                text: mensaje,
                                showConfirmButton: false,
                                timer: 1500,
                                customClass: {
                                    popup: 'rounded-lg'
                                }
                            }).then(() => {
                                location.reload();
                            });
                        }
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

        function cambiarItemsPorPagina(items) {
            const params = new URLSearchParams(window.location.search);
            params.set('items_per_page', items);
            params.set('pagina', '1'); // Volver a la primera página al cambiar los items por página
            window.location.search = params.toString();
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

        /* Efecto hover suave para botones */
        .hover\:scale-105:hover {
            transition: all 0.2s ease-in-out;
        }

        /* Sombras mejoradas */
        .shadow-custom {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 
                        0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* Animación para mensajes de alerta */
        .alert-animate {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Mejorar aspecto de la tabla */
        .table-hover tr:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }

        /* Estilo para los iconos en los encabezados */
        .header-icon {
            @apply text-blue-600 opacity-75 group-hover:opacity-100 transition-opacity;
        }
    </style>
</body>

</html>