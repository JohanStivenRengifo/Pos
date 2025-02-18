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

function getMetodoPagoIcon($metodo_pago)
{
    switch (strtolower($metodo_pago)) {
        case 'efectivo':
            return 'money-bill-wave';
        case 'transferencia':
            return 'exchange-alt';
        case 'tarjeta':
            return 'credit-card';
        case 'otro':
        default:
            return 'circle';
    }
}

function getUserIngresos($user_id, $limit = 10, $offset = 0)
{
    global $pdo;
    $query = "SELECT i.*, 
              FORMAT(i.monto, 2) as monto_formateado,
              DATE_FORMAT(i.created_at, '%d/%m/%Y %H:%i') as fecha_formateada 
              FROM ingresos i 
              WHERE i.user_id = :user_id 
              ORDER BY i.created_at DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addIngreso($user_id, $data)
{
    global $pdo;
    try {
        $query = "INSERT INTO ingresos (user_id, descripcion, monto, categoria, metodo_pago, notas) 
                  VALUES (:user_id, :descripcion, :monto, :categoria, :metodo_pago, :notas)";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':descripcion' => $data['descripcion'],
            ':monto' => $data['monto'],
            ':categoria' => $data['categoria'],
            ':metodo_pago' => $data['metodo_pago'],
            ':notas' => $data['notas']
        ]);

        if ($result) {
            return ['status' => true, 'message' => 'Ingreso registrado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al registrar el ingreso'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

function getTotalIngresos($user_id)
{
    global $pdo;
    $query = "SELECT COUNT(*) FROM ingresos WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$ingresos = getUserIngresos($user_id, $limit, $offset);
$total_ingresos = getTotalIngresos($user_id);
$total_pages = ceil($total_ingresos / $limit);

// Agregar estas funciones después de las funciones existentes y antes del HTML

function getTotalIngresosMes($user_id)
{
    global $pdo;
    $query = "SELECT COALESCE(SUM(monto), 0) as total 
              FROM ingresos 
              WHERE user_id = ? 
              AND MONTH(created_at) = MONTH(CURRENT_DATE())
              AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getComparacionMesAnterior($user_id)
{
    global $pdo;
    $query = "SELECT 
                (SELECT COALESCE(SUM(monto), 0)
                 FROM ingresos 
                 WHERE user_id = ? 
                 AND MONTH(created_at) = MONTH(CURRENT_DATE())
                 AND YEAR(created_at) = YEAR(CURRENT_DATE())) as mes_actual,
                (SELECT COALESCE(SUM(monto), 0)
                 FROM ingresos 
                 WHERE user_id = ? 
                 AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                 AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))) as mes_anterior";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['mes_anterior'] == 0) return 100;
    return (($result['mes_actual'] - $result['mes_anterior']) / $result['mes_anterior']) * 100;
}

function getPromedioIngreso($user_id)
{
    global $pdo;
    $query = "SELECT COALESCE(AVG(monto), 0) as promedio 
              FROM ingresos 
              WHERE user_id = ? 
              AND MONTH(created_at) = MONTH(CURRENT_DATE())
              AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['promedio'];
}

function getCategoriaFrecuente($user_id)
{
    global $pdo;
    $query = "SELECT categoria, COUNT(*) as total
              FROM ingresos 
              WHERE user_id = ? 
              AND MONTH(created_at) = MONTH(CURRENT_DATE())
              AND YEAR(created_at) = YEAR(CURRENT_DATE())
              GROUP BY categoria
              ORDER BY total DESC
              LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['categoria'] : 'Sin datos';
}

// Obtener los datos para las tarjetas
$total_mes = getTotalIngresosMes($user_id);
$comparacion = getComparacionMesAnterior($user_id);
$promedio = getPromedioIngreso($user_id);
$categoria_frecuente = getCategoriaFrecuente($user_id);

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_ingreso':
            $data = [
                'descripcion' => trim($_POST['descripcion']),
                'monto' => (float)trim($_POST['monto']),
                'categoria' => trim($_POST['categoria']),
                'metodo_pago' => trim($_POST['metodo_pago']),
                'notas' => trim($_POST['notas'])
            ];

            if (empty($data['descripcion']) || $data['monto'] <= 0) {
                ApiResponse::send(false, 'Por favor, complete todos los campos correctamente.');
            }

            $result = addIngreso($user_id, $data);
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'delete_ingreso':
            $id = (int)$_POST['id'];
            if (!$id) {
                ApiResponse::send(false, 'ID de ingreso no válido');
            }

            try {
                $query = "DELETE FROM ingresos WHERE id = :id AND user_id = :user_id";
                $stmt = $pdo->prepare($query);
                $result = $stmt->execute([':id' => $id, ':user_id' => $user_id]);

                if ($result) {
                    ApiResponse::send(true, 'Ingreso eliminado correctamente');
                } else {
                    ApiResponse::send(false, 'No se pudo eliminar el ingreso');
                }
            } catch (PDOException $e) {
                ApiResponse::send(false, 'Error al eliminar el ingreso');
            }
            break;

        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresos | Numercia</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico" />
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- XLSX -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-50 font-[Poppins]">
    <?php
    $rutaBase = "../../";  // Ruta base para los assets
    include '../../includes/header.php';
    ?>

    <div class="container mx-auto px-4">
        <?php
        // Definir la ruta activa para el sidebar
        $moduloActual = "ingresos";
        include '../../includes/sidebar.php';
        ?>

        <div class="main-content p-4 sm:ml-64">
            <!-- Header Section -->
            <div class="mb-8">
                <h1 class="text-3xl font-semibold text-gray-800">Gestión de Ingresos</h1>
                <p class="text-gray-600 mt-2">Administra y registra todos tus ingresos de forma eficiente</p>
            </div>

            <!-- Tarjetas de Resumen -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Ingresos Mes -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Ingresos Mes</h3>
                        <span class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-dollar-sign text-green-600"></i>
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-2xl font-semibold text-gray-800">
                                $<?= number_format($total_mes, 2, ',', '.') ?>
                            </h4>
                            <p class="text-sm mt-1 <?= $comparacion >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <i class="fas fa-<?= $comparacion >= 0 ? 'arrow-up' : 'arrow-down' ?> mr-1"></i>
                                <?= abs(round($comparacion, 1)) ?>% vs mes anterior
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Promedio por Ingreso -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500">Promedio por Ingreso</h3>
                        <span class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-chart-line text-blue-600"></i>
                        </span>
                    </div>
                    <div>
                        <h4 class="text-2xl font-semibold text-gray-800">
                            $<?= number_format($promedio, 2, ',', '.') ?>
                        </h4>
                        <p class="text-sm text-gray-500 mt-1">
                            Este mes
                        </p>
                    </div>
                </div>

                <!-- Categoría más frecuente -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500">Categoría más frecuente</h3>
                        <span class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-tags text-purple-600"></i>
                        </span>
                    </div>
                    <div>
                        <h4 class="text-2xl font-semibold text-gray-800">
                            <?= htmlspecialchars($categoria_frecuente) ?>
                        </h4>
                        <p class="text-sm text-gray-500 mt-1">
                            Este mes
                        </p>
                    </div>
                </div>

                <!-- Total Transacciones -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Transacciones</h3>
                        <span class="p-2 bg-yellow-100 rounded-lg">
                            <i class="fas fa-receipt text-yellow-600"></i>
                        </span>
                    </div>
                    <div>
                        <h4 class="text-2xl font-semibold text-gray-800">
                            <?= $total_ingresos ?>
                        </h4>
                        <p class="text-sm text-gray-500 mt-1">
                            Registros totales
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Section -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-8 border border-gray-100">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">
                    <i class="fas fa-plus-circle mr-2 text-blue-600"></i>
                    Agregar Nuevo Ingreso
                </h2>

                <form id="ingresoForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="action" value="add_ingreso">

                    <!-- Descripción y Monto en la primera fila -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-file-alt mr-2 text-blue-500"></i>Descripción
                        </label>
                        <input type="text"
                            name="descripcion"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                            placeholder="Ej: Venta de productos"
                            autocomplete="off"
                            required>
                    </div>

                    <!-- Monto con formato de moneda -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-dollar-sign mr-2 text-green-500"></i>Monto (COP)
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-medium">$</span>
                            <input type="text"
                                name="monto"
                                id="montoInput"
                                class="w-full pl-8 pr-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                                placeholder="0"
                                required>
                        </div>
                    </div>

                    <!-- Categoría y Método de Pago en la segunda fila -->
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-tags mr-2 text-yellow-500"></i>Categoría
                        </label>
                        <select name="categoria"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                            required>
                            <option value="">Seleccione una categoría</option>
                            <option value="Ventas">Ventas</option>
                            <option value="Servicios">Servicios</option>
                            <option value="Comisiones">Comisiones</option>
                            <option value="Otros">Otros</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-credit-card mr-2 text-purple-500"></i>Método de Pago
                        </label>
                        <select name="metodo_pago"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                            required>
                            <option value="">Seleccione método de pago</option>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Tarjeta">Tarjeta</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <!-- Notas en una fila completa -->
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-2 text-orange-500"></i>Notas Adicionales
                        </label>
                        <textarea name="notas"
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                            rows="3"
                            placeholder="Agregar notas o detalles adicionales"></textarea>
                    </div>

                    <!-- Botones de acción -->
                    <div class="col-span-2 flex gap-4">
                        <button type="submit"
                            class="flex-1 sm:flex-none px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                            <i class="fas fa-plus-circle mr-2"></i>
                            Registrar Ingreso
                        </button>
                        <button type="reset"
                            class="flex-1 sm:flex-none px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 transition-all duration-200">
                            <i class="fas fa-undo mr-2"></i>
                            Limpiar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <h2 class="text-xl font-semibold text-gray-800">Listado de Ingresos</h2>

                        <div class="flex items-center gap-4">
                            <button id="exportExcel"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-file-excel mr-2"></i>
                                Exportar
                            </button>

                            <div class="relative">
                                <input type="text"
                                    id="searchInput"
                                    class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Buscar...">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Descripción
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Categoría
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Monto
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Método
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($ingresos as $ingreso): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?= htmlspecialchars($ingreso['descripcion']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                        switch (strtolower($ingreso['categoria'])) {
                                            case 'ventas':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'servicios':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'comisiones':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                            <?= htmlspecialchars($ingreso['categoria']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-green-600">
                                            $<?= number_format($ingreso['monto'], 0, ',', '.') ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <i class="fas fa-<?= getMetodoPagoIcon($ingreso['metodo_pago']) ?> mr-2"></i>
                                            <?= htmlspecialchars($ingreso['metodo_pago']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?= $ingreso['fecha_formateada'] ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="verDetalles(<?= $ingreso['id'] ?>)"
                                                class="text-blue-600 hover:text-blue-900 p-1">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editarIngreso(<?= $ingreso['id'] ?>)"
                                                class="text-green-600 hover:text-green-900 p-1">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="eliminarIngreso(<?= $ingreso['id'] ?>)"
                                                class="text-red-600 hover:text-red-900 p-1">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-100">
                    <div class="flex justify-between items-center">
                        <p class="text-sm text-gray-700">
                            Mostrando <span class="font-medium"><?= $offset + 1 ?></span> a
                            <span class="font-medium"><?= min($offset + $limit, $total_ingresos) ?></span> de
                            <span class="font-medium"><?= $total_ingresos ?></span> resultados
                        </p>

                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>"
                                    class="px-3 py-1 rounded-md bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                    Anterior
                                </a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>"
                                    class="px-3 py-1 rounded-md bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                    Siguiente
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuración de notificaciones
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: {
                popup: 'bg-white rounded-lg shadow-xl border border-gray-100',
                title: 'text-gray-800 font-medium'
            }
        });

        // Reemplazar la función actual del botón exportar con esta nueva implementación

        document.getElementById('exportExcel').addEventListener('click', function() {
            // Obtener los datos de la tabla actual
            const rows = document.querySelectorAll('table tbody tr');
            const data = [];
            
            // Agregar encabezados con formato
            data.push([
                'REPORTE DE INGRESOS',
                '', '', '', '', ''  // Columnas vacías para el merge
            ]);
            data.push([]); // Fila vacía para espaciado
            
            // Agregar fecha de generación
            data.push([
                'Fecha de generación:',
                new Date().toLocaleDateString('es-CO', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }),
                '', '', '', ''
            ]);
            data.push([]); // Fila vacía para espaciado
            
            // Agregar encabezados de columnas
            data.push([
                'Descripción',
                'Categoría',
                'Monto',
                'Método de Pago',
                'Fecha',
                'Notas'
            ]);
            
            // Agregar datos
            rows.forEach(row => {
                const cells = Array.from(row.cells).slice(0, -1); // Excluir columna de acciones
                const rowData = cells.map(cell => cell.textContent.trim());
                data.push(rowData);
            });
            
            // Crear libro y hoja de trabajo
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(data);
            
            // Estilos y formato
            ws['!merges'] = [
                // Merge para el título
                {s: {r: 0, c: 0}, e: {r: 0, c: 5}},
                // Merge para la fecha
                {s: {r: 2, c: 1}, e: {r: 2, c: 5}}
            ];
            
            // Definir anchos de columna
            ws['!cols'] = [
                {wch: 35}, // Descripción
                {wch: 15}, // Categoría
                {wch: 15}, // Monto
                {wch: 15}, // Método de Pago
                {wch: 20}, // Fecha
                {wch: 35}  // Notas
            ];
            
            // Aplicar estilos a las celdas
            const titleCell = XLSX.utils.encode_cell({r: 0, c: 0});
            const headerRow = XLSX.utils.encode_range({r: 4, c: 0}, {r: 4, c: 5});
            
            // Estilo para el título
            ws[titleCell].s = {
                font: {bold: true, size: 14},
                alignment: {horizontal: 'center'}
            };
            
            // Estilo para los encabezados
            for(let col = 0; col <= 5; col++) {
                const cell = XLSX.utils.encode_cell({r: 4, c: col});
                ws[cell].s = {
                    font: {bold: true},
                    fill: {fgColor: {rgb: "E2E8F0"}},
                    alignment: {horizontal: 'center'},
                    border: {
                        top: {style: 'thin'},
                        bottom: {style: 'thin'},
                        left: {style: 'thin'},
                        right: {style: 'thin'}
                    }
                };
            }
            
            // Aplicar formato de moneda a la columna de monto
            for(let row = 5; row < data.length; row++) {
                const montoCell = XLSX.utils.encode_cell({r: row, c: 2});
                if(ws[montoCell]) {
                    ws[montoCell].z = '"$"#,##0.00';
                }
            }
            
            // Agregar la hoja al libro
            XLSX.utils.book_append_sheet(wb, ws, "Ingresos");
            
            // Generar nombre del archivo
            const fecha = new Date().toLocaleDateString('es-CO').replace(/\//g, '-');
            const fileName = `Reporte_Ingresos_${fecha}.xlsx`;
            
            try {
                // Guardar el archivo
                XLSX.writeFile(wb, fileName);
                
                Toast.fire({
                    icon: 'success',
                    title: 'Reporte exportado correctamente'
                });
            } catch (error) {
                console.error('Error al exportar:', error);
                Toast.fire({
                    icon: 'error',
                    title: 'Error al exportar el reporte'
                });
            }
        });

        // Función para ver detalles
        function verDetalles(id) {
            fetch(`get_ingreso.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        const ingreso = data.data;
                        Swal.fire({
                            title: 'Detalles del Ingreso',
                            html: `
                            <div class="text-left p-4">
                                <div class="mb-3">
                                    <strong class="text-gray-700">Descripción:</strong>
                                    <p class="mt-1">${ingreso.descripcion}</p>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-gray-700">Monto:</strong>
                                    <p class="mt-1 text-green-600 font-semibold">$${new Intl.NumberFormat('es-CO').format(ingreso.monto)}</p>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-gray-700">Categoría:</strong>
                                    <p class="mt-1">${ingreso.categoria}</p>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-gray-700">Método de Pago:</strong>
                                    <p class="mt-1">${ingreso.metodo_pago}</p>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-gray-700">Fecha:</strong>
                                    <p class="mt-1">${ingreso.fecha_formateada}</p>
                                </div>
                                <div class="mb-3">
                                    <strong class="text-gray-700">Notas:</strong>
                                    <p class="mt-1">${ingreso.notas || 'Sin notas adicionales'}</p>
                                </div>
                            </div>
                        `,
                            showCloseButton: true,
                            showConfirmButton: false,
                            width: '32rem',
                        });
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: 'Error al cargar los detalles'
                        });
                    }
                })
                .catch(error => {
                    Toast.fire({
                        icon: 'error',
                        title: 'Error al cargar los detalles'
                    });
                });
        }

        // Función para editar ingreso
        function editarIngreso(id) {
            fetch(`get_ingreso.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        const ingreso = data.data;
                        Swal.fire({
                            title: 'Editar Ingreso',
                            html: `
                            <form id="editForm" class="p-4">
                                <input type="hidden" name="id" value="${ingreso.id}">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                                    <input type="text" name="descripcion" value="${ingreso.descripcion}" 
                                           class="w-full px-3 py-2 border rounded-lg" required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Monto</label>
                                    <input type="number" name="monto" value="${ingreso.monto}" 
                                           class="w-full px-3 py-2 border rounded-lg" required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
                                    <select name="categoria" class="w-full px-3 py-2 border rounded-lg" required>
                                        ${getCategoriaOptions(ingreso.categoria)}
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Método de Pago</label>
                                    <select name="metodo_pago" class="w-full px-3 py-2 border rounded-lg" required>
                                        ${getMetodoPagoOptions(ingreso.metodo_pago)}
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Notas</label>
                                    <textarea name="notas" class="w-full px-3 py-2 border rounded-lg" rows="3">${ingreso.notas || ''}</textarea>
                                </div>
                            </form>
                        `,
                            showCancelButton: true,
                            confirmButtonText: 'Guardar',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#10B981',
                            cancelButtonColor: '#6B7280',
                            preConfirm: () => {
                                const form = document.getElementById('editForm');
                                const formData = new FormData(form);
                                formData.append('action', 'edit_ingreso');

                                return fetch('update_ingreso.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(result => {
                                        if (!result.status) throw new Error(result.message);
                                        return result;
                                    });
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                Toast.fire({
                                    icon: 'success',
                                    title: 'Ingreso actualizado correctamente'
                                });
                                setTimeout(() => location.reload(), 1500);
                            }
                        });
                    }
                });
        }

        // Función para eliminar ingreso
        function eliminarIngreso(id) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_ingreso');
                    formData.append('id', id);

                    fetch('', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status) {
                                Toast.fire({
                                    icon: 'success',
                                    title: 'Ingreso eliminado correctamente'
                                });
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                throw new Error(data.message);
                            }
                        })
                        .catch(error => {
                            Toast.fire({
                                icon: 'error',
                                title: error.message || 'Error al eliminar el ingreso'
                            });
                        });
                }
            });
        }

        // Funciones auxiliares
        function getCategoriaOptions(selectedCategoria) {
            const categorias = ['Ventas', 'Servicios', 'Comisiones', 'Otros'];
            return categorias.map(cat =>
                `<option value="${cat}" ${cat === selectedCategoria ? 'selected' : ''}>${cat}</option>`
            ).join('');
        }

        function getMetodoPagoOptions(selectedMetodo) {
            const metodos = ['Efectivo', 'Transferencia', 'Tarjeta', 'Otro'];
            return metodos.map(met =>
                `<option value="${met}" ${met === selectedMetodo ? 'selected' : ''}>${met}</option>`
            ).join('');
        }

        // Agregar al inicio del script existente
        document.addEventListener('DOMContentLoaded', function() {
            // Formateo de moneda para el campo monto
            const montoInput = document.getElementById('montoInput');

            montoInput.addEventListener('input', function(e) {
                // Eliminar todo excepto números
                let value = this.value.replace(/[^\d]/g, '');

                // Formatear con separadores de miles
                if (value.length > 0) {
                    value = new Intl.NumberFormat('es-CO').format(value);
                }

                this.value = value;
            });

            // Antes de enviar el formulario, eliminar el formato de moneda
            document.getElementById('ingresoForm').addEventListener('submit', function(e) {
                const montoValue = montoInput.value.replace(/\D/g, '');
                montoInput.value = montoValue;
            });

            // Animación de carga al enviar el formulario
            document.getElementById('ingresoForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;

                // Cambiar el botón a estado de carga
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Procesando...';

                const formData = new FormData(this);

                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.status) {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        this.reset();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'Error al registrar el ingreso'
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Toast.fire({
                        icon: 'error',
                        title: 'Error al procesar la solicitud'
                    });
                } finally {
                    // Restaurar el botón
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        });
    </script>
</body>

</html>