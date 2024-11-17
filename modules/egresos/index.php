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

function getUserProveedores($user_id)
{
    global $pdo;
    $query = "SELECT * FROM proveedores WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserEgresos($user_id, $limit = 10, $offset = 0)
{
    global $pdo;
    $query = "SELECT e.*, 
              FORMAT(e.monto, 2) as monto_formateado,
              DATE_FORMAT(e.fecha, '%d/%m/%Y') as fecha_formateada 
              FROM egresos e 
              WHERE e.user_id = :user_id 
              ORDER BY e.fecha DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Actualizar la función addEgreso
function addEgreso($user_id, $data) {
    global $pdo;
    try {
        // Validar el estado
        if (!isset($data['estado']) || empty($data['estado'])) {
            $data['estado'] = 'pendiente'; // Valor por defecto si no se especifica
        }

        // Manejar el archivo de comprobante si existe
        $comprobante_path = null;
        if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/comprobantes/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('comprobante_') . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['comprobante']['tmp_name'], $target_path)) {
                $comprobante_path = 'uploads/comprobantes/' . $file_name;
            }
        }

        // Debug para verificar los datos antes de la inserción
        error_log("Datos a insertar: " . print_r($data, true));

        $query = "INSERT INTO egresos (
            user_id, 
            numero_factura, 
            proveedor, 
            descripcion, 
            monto, 
            fecha, 
            categoria, 
            metodo_pago, 
            estado, 
            comprobante, 
            notas,
            created_at,
            updated_at
        ) VALUES (
            :user_id, 
            :numero_factura, 
            :proveedor, 
            :descripcion, 
            :monto, 
            :fecha, 
            :categoria, 
            :metodo_pago, 
            :estado, 
            :comprobante, 
            :notas,
            NOW(),
            NOW()
        )";
        
        $stmt = $pdo->prepare($query);
        $params = [
            ':user_id' => $user_id,
            ':numero_factura' => $data['numero_factura'],
            ':proveedor' => $data['proveedor'],
            ':descripcion' => $data['descripcion'],
            ':monto' => $data['monto'],
            ':fecha' => $data['fecha'],
            ':categoria' => $data['categoria'],
            ':metodo_pago' => $data['metodo_pago'],
            ':estado' => $data['estado'],
            ':comprobante' => $comprobante_path,
            ':notas' => $data['notas']
        ];

        // Debug para verificar los parámetros
        error_log("Parámetros de la consulta: " . print_r($params, true));

        $result = $stmt->execute($params);
        
        if ($result) {
            return ['status' => true, 'message' => 'Egreso registrado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al registrar el egreso'];
    } catch (PDOException $e) {
        error_log("Error en addEgreso: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

// Agregar función para obtener detalles de un egreso
function getEgresoDetails($id, $user_id) {
    global $pdo;
    $query = "SELECT *, 
              FORMAT(monto, 2) as monto_formateado,
              DATE_FORMAT(fecha, '%Y-%m-%d') as fecha_formateada 
              FROM egresos 
              WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':id' => $id, ':user_id' => $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Agregar función para actualizar egreso
function updateEgreso($id, $user_id, $data) {
    global $pdo;
    try {
        // Verificar que el egreso pertenezca al usuario
        $checkQuery = "SELECT id FROM egresos WHERE id = :id AND user_id = :user_id";
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([':id' => $id, ':user_id' => $user_id]);
        if (!$stmt->fetch()) {
            return ['status' => false, 'message' => 'No tienes permiso para editar este egreso'];
        }

        $query = "UPDATE egresos 
                 SET numero_factura = :numero_factura,
                     proveedor = :proveedor,
                     descripcion = :descripcion,
                     monto = :monto,
                     fecha = :fecha,
                     categoria = :categoria,
                     metodo_pago = :metodo_pago,
                     estado = :estado,
                     notas = :notas,
                     updated_at = NOW()
                 WHERE id = :id AND user_id = :user_id";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':id' => $id,
            ':user_id' => $user_id,
            ':numero_factura' => $data['numero_factura'],
            ':proveedor' => $data['proveedor'],
            ':descripcion' => $data['descripcion'],
            ':monto' => $data['monto'],
            ':fecha' => $data['fecha'],
            ':categoria' => $data['categoria'],
            ':metodo_pago' => $data['metodo_pago'],
            ':estado' => $data['estado'],
            ':notas' => $data['notas']
        ]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Egreso actualizado correctamente'];
        }
        return ['status' => false, 'message' => 'Error al actualizar el egreso'];
    } catch (PDOException $e) {
        error_log("Error en updateEgreso: " . $e->getMessage());
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

// Agregar función para obtener categorías de egresos
function getCategoriasEgresos() {
    return [
        'Compras',
        'Servicios',
        'Nómina',
        'Impuestos',
        'Mantenimiento',
        'Otros'
    ];
}

// Agregar función para obtener métodos de pago
function getMetodosPago() {
    return [
        'Efectivo',
        'Transferencia',
        'Tarjeta',
        'Cheque',
        'Otro'
    ];
}

function getConfig($user_id) {
    global $pdo;
    $query = "SELECT valor FROM configuracion WHERE user_id = ? AND tipo = 'numero_factura'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTotalEgresos($user_id)
{
    global $pdo;
    $query = "SELECT COUNT(*) FROM egresos WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

$configuracion = getConfig($user_id);
$numero_factura_default = $configuracion['valor'] ?? '';

// Agregar esta función junto con las otras funciones
function deleteEgreso($id, $user_id) {
    global $pdo;
    try {
        $query = "DELETE FROM egresos WHERE id = :id AND user_id = :user_id";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            ':id' => $id,
            ':user_id' => $user_id
        ]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Egreso eliminado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al eliminar el egreso'];
    } catch (PDOException $e) {
        return ['status' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
    }
}

// Agregar esta función junto con las otras funciones
function getMetodoPagoIcon($metodo_pago) {
    switch (strtolower($metodo_pago)) {
        case 'efectivo':
            return 'money-bill-wave';
        case 'transferencia':
            return 'exchange-alt';
        case 'tarjeta':
            return 'credit-card';
        case 'cheque':
            return 'money-check-alt';
        case 'otro':
        default:
            return 'circle';
    }
}

// Procesar solicitudes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_egreso':
            $data = [
                'numero_factura' => trim($_POST['numero_factura']),
                'proveedor' => trim($_POST['proveedor']),
                'descripcion' => trim($_POST['descripcion']),
                'monto' => (float)trim($_POST['monto']),
                'fecha' => $_POST['fecha'],
                'categoria' => trim($_POST['categoria']),
                'metodo_pago' => trim($_POST['metodo_pago']),
                'notas' => trim($_POST['notas'])
            ];

            if (empty($data['numero_factura']) || empty($data['proveedor']) || 
                empty($data['descripcion']) || $data['monto'] <= 0 || empty($data['fecha'])) {
                ApiResponse::send(false, 'Por favor, complete todos los campos correctamente.');
            }

            $result = addEgreso($user_id, $data);
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'update_egreso':
            if (!isset($_POST['id'])) {
                ApiResponse::send(false, 'ID de egreso no proporcionado');
            }
            $id = (int)$_POST['id'];
            $data = [
                'numero_factura' => trim($_POST['numero_factura']),
                'proveedor' => trim($_POST['proveedor']),
                'descripcion' => trim($_POST['descripcion']),
                'monto' => (float)trim($_POST['monto']),
                'fecha' => $_POST['fecha'],
                'categoria' => trim($_POST['categoria']),
                'metodo_pago' => trim($_POST['metodo_pago']),
                'estado' => trim($_POST['estado']),
                'notas' => trim($_POST['notas'])
            ];

            $result = updateEgreso($id, $user_id, $data);
            ApiResponse::send($result['status'], $result['message']);
            break;

        case 'get_egreso':
            $id = (int)$_POST['id'];
            $egreso = getEgresoDetails($id, $user_id);
            
            if ($egreso) {
                ApiResponse::send(true, 'Egreso encontrado', $egreso);
            } else {
                ApiResponse::send(false, 'Egreso no encontrado');
            }
            break;

        case 'delete_egreso':
            $id = (int)$_POST['id'];
            $result = deleteEgreso($id, $user_id);
            ApiResponse::send($result['status'], $result['message']);
            break;

        default:
            ApiResponse::send(false, 'Acción no válida');
    }
}

// Obtener datos necesarios
$proveedores = getUserProveedores($user_id);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$egresos = getUserEgresos($user_id, $limit, $offset);
$total_egresos = getTotalEgresos($user_id);
$total_pages = ceil($total_egresos / $limit);

function getTotalEgresosMes($user_id) {
    global $pdo;
    try {
        // Consulta para obtener el total de egresos del mes actual
        $query = "SELECT COALESCE(SUM(ABS(monto)), 0) as total 
                  FROM egresos 
                  WHERE user_id = :user_id 
                  AND YEAR(fecha) = YEAR(CURRENT_DATE())
                  AND MONTH(fecha) = MONTH(CURRENT_DATE())
                  AND estado != 'anulado'";

        $stmt = $pdo->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        
        // Obtener el resultado y asegurarse de que sea un número
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = floatval($result['total']);

        // Para debug
        error_log("Total egresos mes para usuario $user_id: $total");
        
        return $total;
        
    } catch (PDOException $e) {
        error_log("Error en getTotalEgresosMes: " . $e->getMessage());
        return 0;
    }
}

function calcularPorcentajeCambio($user_id) {
    global $pdo;
    try {
        // Mes actual
        $queryActual = "SELECT COALESCE(SUM(ABS(monto)), 0) as total 
                       FROM egresos 
                       WHERE user_id = :user_id 
                       AND YEAR(fecha) = YEAR(CURRENT_DATE())
                       AND MONTH(fecha) = MONTH(CURRENT_DATE())
                       AND estado != 'anulado'";
        
        // Mes anterior
        $queryAnterior = "SELECT COALESCE(SUM(ABS(monto)), 0) as total 
                         FROM egresos 
                         WHERE user_id = :user_id 
                         AND YEAR(fecha) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                         AND MONTH(fecha) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                         AND estado != 'anulado'";
        
        // Obtener total mes actual
        $stmt = $pdo->prepare($queryActual);
        $stmt->execute([':user_id' => $user_id]);
        $totalActual = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        
        // Obtener total mes anterior
        $stmt = $pdo->prepare($queryAnterior);
        $stmt->execute([':user_id' => $user_id]);
        $totalAnterior = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        
        // Para debug
        error_log("Total actual: $totalActual, Total anterior: $totalAnterior");
        
        // Calcular porcentaje
        if ($totalAnterior == 0) {
            return $totalActual > 0 ? 100 : 0;
        }
        
        $porcentaje = (($totalActual - $totalAnterior) / $totalAnterior) * 100;
        return round($porcentaje, 1);
        
    } catch (PDOException $e) {
        error_log("Error en calcularPorcentajeCambio: " . $e->getMessage());
        return 0;
    }
}

function getPromedioEgresos($user_id) {
    global $pdo;
    try {
        $query = "SELECT 
                    COUNT(*) as total_registros,
                    COALESCE(SUM(monto), 0) as total_monto
                  FROM egresos 
                  WHERE user_id = :user_id 
                  AND estado != 'anulado'
                  AND MONTH(fecha) = MONTH(CURRENT_DATE()) 
                  AND YEAR(fecha) = YEAR(CURRENT_DATE())";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total_registros'] > 0) {
            return floatval($result['total_monto']) / $result['total_registros'];
        }
        
        return 0;
        
    } catch (PDOException $e) {
        error_log("Error calculando promedio de egresos: " . $e->getMessage());
        return 0;
    }
}

function getCategoriaFrecuente($user_id) {
    global $pdo;
    $query = "SELECT categoria, COUNT(*) as total 
              FROM egresos 
              WHERE user_id = ? 
              AND estado != 'anulado'
              AND MONTH(fecha) = MONTH(CURRENT_DATE()) 
              AND YEAR(fecha) = YEAR(CURRENT_DATE())
              GROUP BY categoria 
              ORDER BY total DESC 
              LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['categoria'] : 'N/A';
}

function getEgresosPendientes($user_id) {
    global $pdo;
    $query = "SELECT COUNT(*) 
              FROM egresos 
              WHERE user_id = ? 
              AND estado = 'pendiente'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function getProximoVencimiento($user_id) {
    global $pdo;
    $query = "SELECT DATE_FORMAT(fecha, '%d/%m/%Y') as fecha_formateada 
              FROM egresos 
              WHERE user_id = ? 
              AND estado = 'pendiente' 
              AND fecha >= CURRENT_DATE() 
              ORDER BY fecha ASC 
              LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['fecha_formateada'] : 'No hay pendientes';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egresos | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style type="text/tailwindcss">
        @layer components {
            .card-dashboard {
                @apply bg-white rounded-xl shadow-md p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg border border-gray-100;
            }
            
            .form-input {
                @apply w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200;
            }

            .form-select {
                @apply w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2.5 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200;
            }

            .form-label {
                @apply block text-sm font-medium text-gray-700 mb-2;
            }

            .form-group {
                @apply mb-4;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-body">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Gestión de Egresos</h1>
                <p class="text-gray-600 mt-2">Administra y controla todos los gastos de tu negocio</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card-dashboard">
                    <div class="card-icon bg-gradient-to-r from-blue-500 to-blue-600">
                        <i class="fas fa-money-bill-wave text-xl"></i>
                    </div>
                    <h3 class="text-gray-600 text-sm">Total Egresos Mes</h3>
                    <p class="text-2xl font-bold text-gray-800 mt-2">
                        $<?= number_format(getTotalEgresosMes($user_id), 2, ',', '.') ?>
                    </p>
                    <div class="mt-2 text-sm">
                        <?php $porcentaje = calcularPorcentajeCambio($user_id); ?>
                        <span class="<?= $porcentaje >= 0 ? 'text-red-500' : 'text-green-500' ?>">
                            <i class="fas fa-<?= $porcentaje >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <?= abs($porcentaje) ?>%
                        </span>
                        <span class="text-gray-500">vs mes anterior</span>
                    </div>
                </div>

                <div class="card-dashboard">
                    <div class="card-icon bg-gradient-to-r from-purple-500 to-purple-600">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <h3 class="text-gray-600 text-sm">Promedio por Egreso</h3>
                    <p class="text-2xl font-bold text-gray-800 mt-2">
                        $<?= number_format(getPromedioEgresos($user_id), 2, ',', '.') ?>
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                        Categoría más frecuente: <?= getCategoriaFrecuente($user_id) ?>
                    </p>
                </div>

                <div class="card-dashboard">
                    <div class="card-icon bg-gradient-to-r from-yellow-500 to-yellow-600">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <h3 class="text-gray-600 text-sm">Egresos Pendientes</h3>
                    <p class="text-2xl font-bold text-gray-800 mt-2">
                        <?= getEgresosPendientes($user_id) ?>
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                        Próximo vencimiento: <?= getProximoVencimiento($user_id) ?>
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8 border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-plus-circle text-blue-500"></i>
                            <span>Nuevo Egreso</span>
                        </h2>
                        <button onclick="toggleForm()" 
                                class="p-2 hover:bg-gray-100 rounded-lg transition-colors duration-200">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>

                <div class="p-6" id="formContainer">
                    <form id="egresoForm" class="grid grid-cols-1 md:grid-cols-2 gap-6" onsubmit="guardarEgreso(event)">
                        <input type="hidden" name="id" id="egreso_id" value="">
                        <!-- Primera columna -->
                        <div class="space-y-5">
                            <!-- Número de Factura -->
                            <div class="form-group">
                                <label class="form-label">Número de Factura</label>
                                <div class="flex">
                                    <input type="text" 
                                           name="numero_factura" 
                                           id="numero_factura"
                                           class="form-input rounded-r-none" 
                                           placeholder="FE123456-24"
                                           required>
                                    <button type="button" 
                                            onclick="generarNumeroFactura()"
                                            class="px-4 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg hover:bg-gray-200 transition-colors"
                                            title="Generar número automáticamente">
                                        <i class="fas fa-random"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Proveedor -->
                            <div class="form-group">
                                <label class="form-label">Proveedor</label>
                                <select name="proveedor" class="form-select" required>
                                    <option value="">Seleccionar proveedor...</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?= htmlspecialchars($prov['nombre']) ?>">
                                            <?= htmlspecialchars($prov['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Monto -->
                            <div class="form-group">
                                <label class="form-label">Monto</label>
                                <input type="number" 
                                       name="monto" 
                                       step="0.01" 
                                       class="form-input" 
                                       placeholder="0.00"
                                       required>
                            </div>

                            <!-- Estado -->
                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-select" required>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="pagado">Pagado</option>
                                    <option value="anulado">Anulado</option>
                                </select>
                            </div>
                        </div>

                        <!-- Segunda columna -->
                        <div class="space-y-5">
                            <!-- Fecha -->
                            <div class="form-group">
                                <label class="form-label">Fecha</label>
                                <div class="flex">
                                    <input type="date" 
                                           name="fecha" 
                                           id="fecha"
                                           class="form-input rounded-r-none" 
                                           required>
                                    <button type="button" 
                                            onclick="setToday()" 
                                            class="px-4 bg-gray-100 border border-l-0 border-gray-300 rounded-r-lg hover:bg-gray-200 transition-colors"
                                            title="Establecer fecha actual">
                                        <i class="fas fa-calendar-day"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Categoría -->
                            <div class="form-group">
                                <label class="form-label">Categoría</label>
                                <select name="categoria" class="form-select" required>
                                    <?php foreach (getCategoriasEgresos() as $cat): ?>
                                        <option value="<?= $cat ?>"><?= $cat ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Método de Pago -->
                            <div class="form-group">
                                <label class="form-label">Método de Pago</label>
                                <select name="metodo_pago" class="form-select" required>
                                    <?php foreach (getMetodosPago() as $metodo): ?>
                                        <option value="<?= $metodo ?>"><?= $metodo ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Comprobante -->
                            <div class="form-group">
                                <label class="form-label">Comprobante</label>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="file" 
                                           name="comprobante" 
                                           id="comprobante"
                                           accept="image/*,.pdf"
                                           class="hidden">
                                    <div id="file-info" class="py-2">
                                        <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl mb-2"></i>
                                        <p class="text-sm text-gray-500">Arrastra un archivo o haz clic para seleccionar</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Campos de ancho completo -->
                        <div class="col-span-2 space-y-5">
                            <!-- Descripción -->
                            <div class="form-group">
                                <label class="form-label">Descripción</label>
                                <textarea name="descripcion" 
                                          rows="2" 
                                          class="form-input resize-none" 
                                          placeholder="Describe el egreso..."
                                          required></textarea>
                            </div>

                            <!-- Notas -->
                            <div class="form-group">
                                <label class="form-label">Notas Adicionales</label>
                                <textarea name="notas" 
                                          rows="2" 
                                          class="form-input resize-none"
                                          placeholder="Información adicional (opcional)"></textarea>
                            </div>

                            <!-- Botones -->
                            <div class="flex justify-end gap-4 pt-4">
                                <button type="reset" 
                                        class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                    Limpiar
                                </button>
                                <button type="submit" 
                                        class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                    Guardar Egreso
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-list text-blue-500"></i>
                            <span>Listado de Egresos</span>
                            <span class="text-sm font-normal text-gray-500">(<?= $total_egresos ?> registros)</span>
                        </h2>
                        <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                            <!-- Búsqueda -->
                            <div class="relative flex-grow md:flex-grow-0">
                                <input type="text" 
                                       id="searchInput" 
                                       placeholder="Buscar egreso..."
                                       class="form-input pl-10">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros simplificados -->
                <div class="p-4 bg-gray-50 border-b border-gray-200">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-medium text-gray-600">Filtrar por:</span>
                        <button class="badge bg-blue-100 text-blue-800 hover:bg-blue-200 active" 
                                onclick="filtrarEgresos('todos')"
                                id="filtroTodos">
                            <i class="fas fa-list-ul mr-1"></i>Todos
                        </button>
                        <button class="badge bg-gray-100 text-gray-800 hover:bg-gray-200" 
                                onclick="filtrarEgresos('mes')"
                                id="filtroMes">
                            <i class="fas fa-calendar-alt mr-1"></i>Este Mes
                        </button>
                        <button class="badge bg-yellow-100 text-yellow-800 hover:bg-yellow-200" 
                                onclick="filtrarEgresos('pendientes')"
                                id="filtroPendientes">
                            <i class="fas fa-clock mr-1"></i>Pendientes
                        </button>
                    </div>
                </div>

                <!-- Tabla mejorada -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-receipt text-blue-500"></i>
                                        N° Factura
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-building text-blue-500"></i>
                                        Proveedor
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-align-left text-blue-500"></i>
                                        Descripción
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-dollar-sign text-blue-500"></i>
                                        Monto
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-calendar text-blue-500"></i>
                                        Fecha
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-tag text-blue-500"></i>
                                        Categoría
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-check-circle text-blue-500"></i>
                                        Estado
                                    </div>
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-cog text-blue-500"></i>
                                        Acciones
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($egresos)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-12">
                                        <div class="flex flex-col items-center justify-center text-gray-500">
                                            <i class="fas fa-inbox text-5xl mb-4 text-gray-300"></i>
                                            <p class="text-lg font-medium mb-1">No hay egresos registrados</p>
                                            <p class="text-sm text-gray-400">Los egresos que registres aparecerán aquí</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($egresos as $egreso): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($egreso['numero_factura']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-building text-gray-400"></i>
                                                <span class="text-sm text-gray-900">
                                                    <?= htmlspecialchars($egreso['proveedor']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2 max-w-md">
                                                <p class="text-sm text-gray-900 truncate">
                                                    <?= htmlspecialchars($egreso['descripcion']) ?>
                                                </p>
                                                <?php if (!empty($egreso['notas'])): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                        <i class="fas fa-sticky-note mr-1"></i>
                                                        Nota
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                                    -$<?= number_format($egreso['monto'], 2, ',', '.') ?>
                                                </span>
                                                <span class="text-gray-400" title="<?= htmlspecialchars($egreso['metodo_pago']) ?>">
                                                    <i class="fas fa-<?= getMetodoPagoIcon($egreso['metodo_pago']) ?>"></i>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-calendar text-gray-400"></i>
                                                <span class="text-sm text-gray-900">
                                                    <?= $egreso['fecha_formateada'] ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium
                                                <?php
                                                switch(strtolower($egreso['categoria'])) {
                                                    case 'compras':
                                                        echo 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'servicios':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'nómina':
                                                        echo 'bg-purple-100 text-purple-800';
                                                        break;
                                                    case 'impuestos':
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'mantenimiento':
                                                        echo 'bg-orange-100 text-orange-800';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?= htmlspecialchars($egreso['categoria']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-1.5 rounded-full text-xs font-medium
                                                <?php
                                                switch($egreso['estado']) {
                                                    case 'pendiente':
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'pagado':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'anulado':
                                                        echo 'bg-red-100 text-red-800';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <i class="fas fa-circle text-xs mr-1"></i>
                                                <?= ucfirst($egreso['estado']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-2">
                                                <?php if (!empty($egreso['comprobante'])): ?>
                                                    <button onclick="verComprobante('<?= htmlspecialchars($egreso['comprobante']) ?>')"
                                                            class="p-1.5 hover:bg-blue-50 rounded-lg text-blue-600 transition-colors"
                                                            title="Ver comprobante">
                                                        <i class="fas fa-file-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button onclick="editarEgreso(<?= $egreso['id'] ?>)"
                                                        class="p-1.5 hover:bg-yellow-50 rounded-lg text-yellow-600 transition-colors"
                                                        title="Editar egreso">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <button onclick="confirmarEliminacion(<?= $egreso['id'] ?>)"
                                                        class="p-1.5 hover:bg-red-50 rounded-lg text-red-600 transition-colors"
                                                        title="Eliminar egreso">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación mejorada -->
                <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                    <div class="flex justify-between items-center">
                        <p class="text-sm text-gray-700">
                            Mostrando <span class="font-medium"><?= ($offset + 1) ?></span> a 
                            <span class="font-medium"><?= min($offset + $limit, $total_egresos) ?></span> de 
                            <span class="font-medium"><?= $total_egresos ?></span> registros
                        </p>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=1" class="btn-secondary py-1 px-2">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?page=<?= $page-1 ?>" class="btn-secondary py-1 px-2">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>" 
                                   class="px-3 py-1 rounded-lg <?= ($page === $i) 
                                       ? 'bg-blue-600 text-white' 
                                       : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page+1 ?>" class="btn-secondary py-1 px-2">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?page=<?= $total_pages ?>" class="btn-secondary py-1 px-2">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mantener los scripts existentes pero mejorar las notificaciones y animaciones
    </script>

    <!-- Agregar este script para manejar el archivo seleccionado -->
    <script>
    document.querySelector('.border-dashed').addEventListener('click', function() {
        document.getElementById('comprobante').click();
    });

    document.getElementById('comprobante').addEventListener('change', function(e) {
        const fileInfo = document.getElementById('file-info');
        if (this.files.length > 0) {
            const file = this.files[0];
            fileInfo.innerHTML = `
                <div class="flex flex-col items-center">
                    <i class="fas fa-file-alt text-blue-500 text-xl mb-2"></i>
                    <p class="text-sm text-gray-700 font-medium">${file.name}</p>
                    <p class="text-xs text-gray-500">${(file.size / 1024).toFixed(2)} KB</p>
                    <button type="button" onclick="clearFile(event)" 
                            class="mt-2 text-red-500 hover:text-red-700 text-sm">
                        <i class="fas fa-times mr-1"></i>Eliminar
                    </button>
                </div>
            `;
        } else {
            fileInfo.innerHTML = `
                <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl mb-2"></i>
                <p class="text-sm text-gray-500">Arrastra un archivo o haz clic para seleccionar</p>
            `;
        }
    });

    function clearFile(event) {
        event.stopPropagation();
        const input = document.getElementById('comprobante');
        input.value = '';
        const fileInfo = document.getElementById('file-info');
        fileInfo.innerHTML = `
            <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl mb-2"></i>
            <p class="text-sm text-gray-500">Arrastra un archivo o haz clic para seleccionar</p>
        `;
    }

    function generarNumeroFactura() {
        const prefijo = 'FE';
        const numeros = Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
        const año = new Date().getFullYear().toString().slice(-2);
        const numeroFactura = `${prefijo}${numeros}-${año}`;
        document.getElementById('numero_factura').value = numeroFactura;
    }

    function setToday() {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        document.getElementById('fecha').value = `${year}-${month}-${day}`;
    }

    // Establecer fecha actual al cargar el formulario si no hay fecha seleccionada
    document.addEventListener('DOMContentLoaded', function() {
        const fechaInput = document.getElementById('fecha');
        if (!fechaInput.value) {
            setToday();
        }
    });
    </script>

    <!-- Scripts mejorados -->
    <script>
    // Mejorar las notificaciones
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#fff',
        showClass: {
            popup: 'animate__animated animate__fadeInRight'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutRight'
        }
    });

    // Función para alternar el formulario con animación
    function toggleForm() {
        const formContainer = document.getElementById('formContainer');
        const btn = document.querySelector('.fa-chevron-down');
        
        if (formContainer.style.maxHeight) {
            formContainer.style.maxHeight = null;
            btn.style.transform = 'rotate(0deg)';
        } else {
            formContainer.style.maxHeight = formContainer.scrollHeight + "px";
            btn.style.transform = 'rotate(180deg)';
        }
    }
    </script>

    <script>
    // Funciones de filtrado y búsqueda
    function filtrarEgresos(tipo) {
        const buttons = document.querySelectorAll('.badge');
        buttons.forEach(btn => btn.classList.remove('active'));
        
        document.getElementById(`filtro${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`).classList.add('active');
        
        const rows = document.querySelectorAll('tbody tr');
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        
        rows.forEach(row => {
            let mostrar = true;
            const fecha = row.querySelector('td:nth-child(5)').textContent.trim();
            const estado = row.querySelector('td:nth-child(7)').textContent.trim().toLowerCase();
            
            switch(tipo) {
                case 'mes':
                    const hoy = new Date();
                    const fechaEgreso = parseFecha(fecha);
                    mostrar = fechaEgreso.getMonth() === hoy.getMonth() && 
                             fechaEgreso.getFullYear() === hoy.getFullYear();
                    break;
                case 'pendientes':
                    mostrar = estado.includes('pendiente');
                    break;
                case 'todos':
                    mostrar = true;
                    break;
            }
            
            // Aplicar búsqueda si existe
            if (searchTerm) {
                const contenido = row.textContent.toLowerCase();
                mostrar = mostrar && contenido.includes(searchTerm);
            }
            
            row.style.display = mostrar ? '' : 'none';
        });
    }

    // Función para parsear fecha en formato dd/mm/yyyy
    function parseFecha(fechaStr) {
        const partes = fechaStr.split('/');
        return new Date(partes[2], partes[1] - 1, partes[0]);
    }

    // Búsqueda en tiempo real
    document.getElementById('searchInput').addEventListener('input', function() {
        filtrarEgresos(document.querySelector('.badge.active').textContent.toLowerCase().includes('todos') ? 'todos' : 
                       document.querySelector('.badge.active').textContent.toLowerCase().includes('mes') ? 'mes' : 'pendientes');
    });

    // Funciones para los botones de acción
    function editarEgreso(id) {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'get_egreso',
                id: id
            },
            success: function(response) {
                if (response.status) {
                    const egreso = response.data;
                    // Rellenar el formulario con los datos
                    document.getElementById('egreso_id').value = egreso.id;
                    document.getElementById('numero_factura').value = egreso.numero_factura;
                    document.querySelector('select[name="proveedor"]').value = egreso.proveedor;
                    document.querySelector('textarea[name="descripcion"]').value = egreso.descripcion;
                    document.querySelector('input[name="monto"]').value = egreso.monto;
                    document.querySelector('input[name="fecha"]').value = egreso.fecha_formateada;
                    document.querySelector('select[name="categoria"]').value = egreso.categoria;
                    document.querySelector('select[name="metodo_pago"]').value = egreso.metodo_pago;
                    document.querySelector('select[name="estado"]').value = egreso.estado;
                    document.querySelector('textarea[name="notas"]').value = egreso.notas || '';
                    
                    // Mostrar el formulario si está oculto
                    const formContainer = document.getElementById('formContainer');
                    if (!formContainer.style.maxHeight) {
                        toggleForm();
                    }
                    
                    // Scroll hacia el formulario
                    formContainer.scrollIntoView({ behavior: 'smooth' });
                } else {
                    mostrarError('Error al cargar el egreso');
                }
            },
            error: function() {
                mostrarError('Error de conexión');
            }
        });
    }

    function confirmarEliminacion(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                eliminarEgreso(id);
            }
        });
    }

    function eliminarEgreso(id) {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'delete_egreso',
                id: id
            },
            success: function(response) {
                if (response.status) {
                    Swal.fire({
                        title: '¡Eliminado!',
                        text: response.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    mostrarError(response.message);
                }
            },
            error: function() {
                mostrarError('Error de conexión');
            }
        });
    }

    function verComprobante(url) {
        window.open('../../' + url, '_blank');
    }

    function mostrarError(mensaje) {
        Swal.fire({
            title: 'Error',
            text: mensaje,
            icon: 'error',
            confirmButtonColor: '#3B82F6'
        });
    }

    // Estilo para el botón activo
    document.addEventListener('DOMContentLoaded', function() {
        const badges = document.querySelectorAll('.badge');
        badges.forEach(badge => {
            badge.addEventListener('click', function() {
                badges.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    });
    </script>

    <style>
    .badge.active {
        @apply ring-2 ring-offset-2;
    }
    </style>

    <!-- Agregar este script para manejar el envío del formulario -->
    <script>
    function guardarEgreso(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('egresoForm'));
        const egresoId = document.getElementById('egreso_id').value;
        
        // Determinar si es una actualización o un nuevo registro
        formData.append('action', egresoId ? 'update_egreso' : 'add_egreso');
        
        // Mostrar indicador de carga
        Swal.fire({
            title: egresoId ? 'Actualizando...' : 'Guardando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        // Limpiar formulario y recargar página
                        document.getElementById('egresoForm').reset();
                        document.getElementById('egreso_id').value = '';
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Hubo un problema al procesar la solicitud'
                });
            }
        });
    }
    </script>
</body>
</html>