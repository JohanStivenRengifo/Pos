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

// Modificar la clase ApiResponse para incluir manejo de errores
class ApiResponse {
    public static function send($status, $message, $data = null) {
        if (headers_sent()) {
            return;
        }
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Agregar manejo de errores global
function handleError($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr - $errfile:$errline");
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    // Solo enviar respuesta JSON si es una petición AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        ApiResponse::send(false, "Error del servidor: $errstr");
    }
    return true;
}

set_error_handler('handleError');

// Verificar la conexión a la base de datos
try {
    if (!isset($pdo)) {
        throw new Exception("Error de conexión a la base de datos");
    }
} catch (Exception $e) {
    error_log("Error de base de datos: " . $e->getMessage());
    die("Error de conexión. Por favor, contacte al administrador.");
}

function getUserProveedores($user_id)
{
    global $pdo;
    try {
    $query = "SELECT * FROM proveedores WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getUserProveedores: " . $e->getMessage());
        return [];
    }
}

function getUserEgresos($user_id, $limit = 10, $offset = 0)
{
    global $pdo;
    try {
    $query = "SELECT e.*, 
              FORMAT(e.monto, 2) as monto_formateado,
              DATE_FORMAT(e.fecha, '%d/%m/%Y') as fecha_formateada 
              FROM egresos e 
              WHERE e.user_id = :user_id 
              ORDER BY e.fecha DESC 
              LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getUserEgresos: " . $e->getMessage());
        return [];
    }
}

// Actualizar la función addEgreso
function addEgreso($user_id, $data) {
    global $pdo;
    try {
        // Validar el estado - solo establecer pendiente si no se proporciona un estado
        if (empty($data['estado'])) {
            $data['estado'] = 'pendiente';
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
            ':categoria' => $data['categoria'] ?? 'General',
            ':metodo_pago' => $data['metodo_pago'] ?? 'Efectivo',
            ':estado' => $data['estado'],
            ':comprobante' => $comprobante_path,
            ':notas' => $data['notas'] ?? null
        ];

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
    try {
    $query = "SELECT valor FROM configuracion WHERE user_id = ? AND tipo = 'numero_factura'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['valor' => null];
    } catch (PDOException $e) {
        error_log("Error en getConfig: " . $e->getMessage());
        return ['valor' => null];
    }
}

function getTotalEgresos($user_id)
{
    global $pdo;
    try {
    $query = "SELECT COUNT(*) FROM egresos WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error en getTotalEgresos: " . $e->getMessage());
        return 0;
    }
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
    try {
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
                'estado' => trim($_POST['estado']),
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
    } catch (Exception $e) {
        error_log("Error en procesamiento AJAX: " . $e->getMessage());
        ApiResponse::send(false, 'Error del servidor: ' . $e->getMessage());
    }
}

// Obtener datos necesarios
$proveedores = getUserProveedores($user_id);
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = 10;
$offset = ($page - 1) * $limit;
$egresos = getUserEgresos($user_id, $limit, $offset);
$total_egresos = getTotalEgresos($user_id);
$total_pages = max(1, ceil($total_egresos / $limit));

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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>

    <!-- Configuración de Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                        },
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-in-out',
                        'slide-in': 'slideIn 0.3s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideIn: {
                            '0%': { transform: 'translateY(-10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>

    <!-- Estilos personalizados -->
    <style type="text/tailwindcss">
        @layer components {
            .btn-primary {
                @apply px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 
                       transition-colors duration-200 flex items-center gap-2 font-medium;
            }

            .btn-secondary {
                @apply px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 
                       transition-colors duration-200 flex items-center gap-2 font-medium;
            }

            .btn-danger {
                @apply px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 
                       transition-colors duration-200 flex items-center gap-2 font-medium;
            }

            .card {
                @apply bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden 
                       transition-all duration-300 hover:shadow-md;
            }

            .form-input {
                @apply w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 
                       focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-200;
            }

            .form-select {
                @apply w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 
                       focus:ring-primary-500/20 focus:border-primary-500 transition-all duration-200;
            }

            .form-label {
                @apply block text-sm font-medium text-gray-700 mb-1;
            }

            .badge {
                @apply px-2.5 py-1 rounded-full text-xs font-medium inline-flex items-center gap-1;
            }

            .badge-primary {
                @apply bg-primary-100 text-primary-700;
            }

            .badge-success {
                @apply bg-green-100 text-green-700;
            }

            .badge-warning {
                @apply bg-yellow-100 text-yellow-700;
            }

            .badge-danger {
                @apply bg-red-100 text-red-700;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-wrap -mx-4">
        <?php include '../../includes/sidebar.php'; ?>

            <div class="w-full lg:w-3/4 px-4">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <!-- Total Egresos Card -->
                    <div class="card p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Egresos (Mes)</h3>
                            <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                                <i class="fas fa-dollar-sign"></i>
                            </span>
            </div>
                        <p class="text-2xl font-bold text-gray-800">
                        $<?= number_format(getTotalEgresosMes($user_id), 2, ',', '.') ?>
                    </p>
                        <?php $porcentaje = calcularPorcentajeCambio($user_id); ?>
                        <p class="mt-2 text-sm">
                        <span class="<?= $porcentaje >= 0 ? 'text-red-500' : 'text-green-500' ?>">
                            <i class="fas fa-<?= $porcentaje >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <?= abs($porcentaje) ?>%
                        </span>
                        <span class="text-gray-500">vs mes anterior</span>
                        </p>
                </div>

                    <!-- Egresos Pendientes Card -->
                    <div class="card p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Egresos Pendientes</h3>
                            <span class="p-2 bg-yellow-50 text-yellow-600 rounded-lg">
                                <i class="fas fa-clock"></i>
                            </span>
                    </div>
                        <p class="text-2xl font-bold text-gray-800">
                            <?= getEgresosPendientes($user_id) ?>
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                            Próximo vencimiento: <?= getProximoVencimiento($user_id) ?>
                    </p>
                </div>

                    <!-- Promedio Egresos Card -->
                    <div class="card p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Promedio por Egreso</h3>
                            <span class="p-2 bg-green-50 text-green-600 rounded-lg">
                                <i class="fas fa-chart-line"></i>
                            </span>
                    </div>
                        <p class="text-2xl font-bold text-gray-800">
                            $<?= number_format(getPromedioEgresos($user_id), 2, ',', '.') ?>
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                            Categoría más frecuente: <?= getCategoriaFrecuente($user_id) ?>
                    </p>
                </div>
            </div>

                <!-- Main Content -->
                <div class="space-y-6">
                    <!-- Header Actions -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Gestión de Egresos</h1>
                            <p class="text-gray-600">Administra y controla todos los gastos de tu negocio</p>
                        </div>
                        <div class="flex gap-3">
                            <button class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors duration-200 gap-2 font-medium" onclick="toggleForm()">
                                <i class="fas fa-plus"></i>
                                Nuevo Egreso
                            </button>
                        </div>
                    </div>

                    <!-- Form Container -->
                    <div id="formContainer" class="card hidden animate-slide-in">
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
                    <form id="egresoForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <input type="hidden" name="action" value="add_egreso">
                        <input type="hidden" name="id" id="egreso_id">
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
                                        <i class="fas fa-dice"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Proveedor -->
                            <div class="form-group">
                                <label class="form-label">Proveedor</label>
                                <input type="text" 
                                       name="proveedor" 
                                       class="form-input" 
                                       placeholder="Nombre del proveedor o servicio"
                                       list="proveedoresList">
                                <datalist id="proveedoresList">
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?= htmlspecialchars($prov['nombre']) ?>">
                                    <?php endforeach; ?>
                                </datalist>
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
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:bg-gray-50 transition-colors duration-200">
                                    <input type="file" 
                                           name="comprobante" 
                                           id="comprobante"
                                           accept="image/*,.pdf"
                                           class="hidden">
                                    <div class="py-2">
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
                                        class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                    Limpiar
                                </button>
                                <button type="submit" 
                                        class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                                    Guardar Egreso
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

                    <!-- Table Container -->
                    <div class="card">
                <div class="p-6 border-b border-gray-100">
                            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                                <h2 class="text-lg font-semibold text-gray-800">
                                    Listado de Egresos
                                    <span class="text-sm font-normal text-gray-500">
                                        (<?= $total_egresos ?> registros)
                                    </span>
                        </h2>
                                <div class="flex gap-4">
                                    <div class="relative">
                                        <input type="text" 
                                               id="searchInput" 
                                               class="form-input pl-10" 
                                               placeholder="Buscar egreso...">
                                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    </div>
                                    <button onclick="exportarExcel()" class="btn-secondary">
                                        <i class="fas fa-file-excel"></i>
                                        Exportar Excel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nº Factura
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Proveedor
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Descripción
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Monto
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estado
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($egresos)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                            No hay egresos registrados
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($egresos as $egreso): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= htmlspecialchars($egreso['numero_factura']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= htmlspecialchars($egreso['proveedor']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                <?= htmlspecialchars($egreso['descripcion']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                $<?= $egreso['monto_formateado'] ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= $egreso['fecha_formateada'] ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?php
                                                    switch($egreso['estado']) {
                                                        case 'pagado':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'pendiente':
                                                            echo 'bg-yellow-100 text-yellow-800';
                                                            break;
                                                        case 'anulado':
                                                            echo 'bg-red-100 text-red-800';
                                                            break;
                                                    }
                                                    ?>">
                                                    <?= ucfirst($egreso['estado']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex gap-2">
                                                    <button onclick="editarEgreso(<?= $egreso['id'] ?>)" 
                                                            class="text-blue-600 hover:text-blue-900">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button onclick="confirmarEliminacion(<?= $egreso['id'] ?>)" 
                                                            class="text-red-600 hover:text-red-900">
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

                    <!-- Paginación -->
                    <?php if ($total_pages > 1): ?>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 flex justify-between sm:hidden">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?= $page-1 ?>" class="btn-secondary">Anterior</a>
                                    <?php endif; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?= $page+1 ?>" class="btn-secondary">Siguiente</a>
                                    <?php endif; ?>
                                </div>
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            Mostrando 
                                            <span class="font-medium"><?= ($offset + 1) ?></span>
                                            a 
                                            <span class="font-medium"><?= min($offset + $limit, $total_egresos) ?></span>
                                            de 
                                            <span class="font-medium"><?= $total_egresos ?></span>
                                            resultados
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <a href="?page=<?= $i ?>" 
                                                   class="<?= $i === $page ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                                    <?= $i ?>
                                                </a>
                                            <?php endfor; ?>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts mejorados -->
    <script>
        // Configuración de notificaciones
    var Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: function(toast) {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    // Función para alternar la visibilidad del formulario
    function toggleForm() {
        const formContainer = document.getElementById('formContainer');
        if (formContainer) {
            if (formContainer.classList.contains('hidden')) {
                formContainer.classList.remove('hidden');
                formContainer.classList.add('animate-slide-in');
            } else {
                formContainer.classList.add('hidden');
                formContainer.classList.remove('animate-slide-in');
            }
        }
    }

    // Búsqueda en tiempo real mejorada
    var searchInput = document.getElementById('searchInput');
    var searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                var searchTerm = searchInput.value.toLowerCase();
                var rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    var shouldShow = text.includes(searchTerm);
                    row.classList.toggle('hidden', !shouldShow);
                });
            }, 300);
        });
    }

    // Función para generar número de factura
    function generarNumeroFactura() {
        var prefijo = 'FE';
        var numeros = String(Math.floor(Math.random() * 1000000)).padStart(6, '0');
        var año = new Date().getFullYear().toString().slice(-2);
        var numeroFactura = prefijo + numeros + '-' + año;
        document.getElementById('numero_factura').value = numeroFactura;
    }

    // Función para establecer la fecha actual
    function setToday() {
        var today = new Date();
        var year = today.getFullYear();
        var month = String(today.getMonth() + 1).padStart(2, '0');
        var day = String(today.getDate()).padStart(2, '0');
        document.getElementById('fecha').value = year + '-' + month + '-' + day;
    }

    // Establecer fecha actual al cargar el formulario
    document.addEventListener('DOMContentLoaded', function() {
        var fechaInput = document.getElementById('fecha');
        if (fechaInput && !fechaInput.value) {
            setToday();
        }
    });

    // Función para filtrar egresos
    function filtrarEgresos(tipo) {
        var rows = document.querySelectorAll('tbody tr');
        var searchTerm = document.getElementById('searchInput').value.toLowerCase();
        
        rows.forEach(function(row) {
            var mostrar = true;
            var fecha = row.querySelector('td:nth-child(5)').textContent.trim();
            var estado = row.querySelector('td:nth-child(7)').textContent.trim().toLowerCase();
            
            switch(tipo) {
                case 'mes':
                    var hoy = new Date();
                    var fechaEgreso = parseFecha(fecha);
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
            
            if (searchTerm) {
                mostrar = mostrar && row.textContent.toLowerCase().includes(searchTerm);
            }
            
            row.style.display = mostrar ? '' : 'none';
        });
    }

    // Función auxiliar para parsear fechas
    function parseFecha(fechaStr) {
        var partes = fechaStr.split('/');
        return new Date(partes[2], partes[1] - 1, partes[0]);
    }

    // Función para guardar egreso
    function guardarEgreso(event) {
        event.preventDefault();
        
        var formData = new FormData(document.getElementById('egresoForm'));
        var isUpdate = formData.get('action') === 'update_egreso';
        
        Swal.fire({
            title: isUpdate ? 'Actualizando...' : 'Guardando...',
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
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.status) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
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
                mostrarError('Error de conexión');
            }
        });
    }

    // Agregar el event listener al formulario
    document.getElementById('egresoForm').addEventListener('submit', guardarEgreso);

    // Agregar la función de exportar Excel
    function exportarExcel() {
        try {
            // Mostrar indicador de carga
            Swal.fire({
                title: 'Generando Excel...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Obtener los datos de la tabla
            var data = [];
            
            // Encabezados personalizados
            var headers = [
                'Número Factura',
                'Proveedor',
                'Descripción',
                'Monto',
                'Fecha',
                'Estado'
            ];
            data.push(headers);
            
            // Obtener datos de las filas visibles
            document.querySelectorAll('tbody tr:not(.hidden)').forEach(function(tr) {
                if (!tr.querySelector('td[colspan]')) { // Excluir filas de "no hay datos"
                    var row = [];
                    // Solo obtener las columnas que queremos exportar (excluyendo acciones)
                    for (var i = 0; i < 6; i++) {
                        var td = tr.children[i];
                        row.push(td.textContent.trim());
                    }
                    data.push(row);
                }
            });

            // Crear y configurar la hoja de cálculo
            var wb = XLSX.utils.book_new();
            var ws = XLSX.utils.aoa_to_sheet(data);
            
            // Configurar anchos de columna
            ws['!cols'] = [
                {wch: 15}, // Número Factura
                {wch: 20}, // Proveedor
                {wch: 30}, // Descripción
                {wch: 12}, // Monto
                {wch: 12}, // Fecha
                {wch: 12}  // Estado
            ];

            // Agregar la hoja al libro
            XLSX.utils.book_append_sheet(wb, ws, "Egresos");
            
            // Generar el archivo
            var fecha = new Date().toLocaleDateString().replace(/\//g, '-');
            XLSX.writeFile(wb, `Egresos_${fecha}.xlsx`);

            // Cerrar el indicador de carga y mostrar éxito
            Swal.fire({
                icon: 'success',
                title: '¡Excel generado!',
                text: 'El archivo se ha descargado correctamente',
                timer: 2000,
                showConfirmButton: false
            });
        } catch (error) {
            console.error('Error al exportar:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo generar el archivo Excel'
            });
        }
    }

    // Agregar estas funciones en la sección de scripts
    function editarEgreso(id) {
        Swal.fire({
            title: 'Cargando...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'get_egreso',
                id: id
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                Swal.close();
                if (response.status) {
                    var form = document.getElementById('formContainer');
                    if (form.classList.contains('hidden')) {
                        toggleForm();
                    }

                    // Cambiar la acción del formulario a 'update_egreso'
                    document.querySelector('input[name="action"]').value = 'update_egreso';
                    
                    // Rellenar el formulario con los datos
                    document.getElementById('egreso_id').value = response.data.id;
                    document.getElementById('numero_factura').value = response.data.numero_factura;
                    document.querySelector('input[name="proveedor"]').value = response.data.proveedor;
                    document.querySelector('input[name="monto"]').value = response.data.monto;
                    document.querySelector('input[name="fecha"]').value = response.data.fecha_formateada;
                    document.querySelector('select[name="categoria"]').value = response.data.categoria;
                    document.querySelector('select[name="metodo_pago"]').value = response.data.metodo_pago;
                    document.querySelector('select[name="estado"]').value = response.data.estado;
                    document.querySelector('textarea[name="descripcion"]').value = response.data.descripcion;
                    document.querySelector('textarea[name="notas"]').value = response.data.notas || '';

                    form.scrollIntoView({ behavior: 'smooth' });
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
                    text: 'Error al cargar los datos del egreso'
                });
            }
        });
    }

    function confirmarEliminacion(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
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
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.status) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Eliminado!',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
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

    // Agregar función para resetear el formulario cuando se agrega un nuevo egreso
    function resetearFormulario() {
        document.getElementById('egresoForm').reset();
        document.querySelector('input[name="action"]').value = 'add_egreso';
        document.getElementById('egreso_id').value = '';
    }

    // Modificar el evento click del botón "Nuevo Egreso"
    document.querySelector('.btn-primary').addEventListener('click', function() {
        resetearFormulario();
        toggleForm();
    });
    </script>
</body>
</html>