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

        $query = "INSERT INTO egresos (user_id, numero_factura, proveedor, descripcion, monto, 
                                     fecha, categoria, metodo_pago, estado, comprobante, notas) 
                  VALUES (:user_id, :numero_factura, :proveedor, :descripcion, :monto, 
                         :fecha, :categoria, :metodo_pago, :estado, :comprobante, :notas)";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
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
        ]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Egreso registrado exitosamente'];
        }
        return ['status' => false, 'message' => 'Error al registrar el egreso'];
    } catch (PDOException $e) {
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
        $query = "UPDATE egresos 
                 SET numero_factura = :numero_factura,
                     proveedor = :proveedor,
                     descripcion = :descripcion,
                     monto = :monto,
                     fecha = :fecha,
                     categoria = :categoria,
                     metodo_pago = :metodo_pago,
                     notas = :notas
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
            ':notas' => $data['notas']
        ]);
        
        if ($result) {
            return ['status' => true, 'message' => 'Egreso actualizado correctamente'];
        }
        return ['status' => false, 'message' => 'Error al actualizar el egreso'];
    } catch (PDOException $e) {
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
            $id = (int)$_POST['id'];
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
    $query = "SELECT COALESCE(SUM(monto), 0) as total 
              FROM egresos 
              WHERE user_id = ? 
              AND MONTH(fecha) = MONTH(CURRENT_DATE()) 
              AND YEAR(fecha) = YEAR(CURRENT_DATE())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function calcularPorcentajeCambio($user_id) {
    global $pdo;
    // Implementar lógica para calcular el porcentaje de cambio
    return 5.2; // Ejemplo
}

function getPromedioEgresos($user_id) {
    global $pdo;
    $query = "SELECT AVG(monto) as promedio 
              FROM egresos 
              WHERE user_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function getCategoriaFrecuente($user_id) {
    global $pdo;
    $query = "SELECT categoria, COUNT(*) as total 
              FROM egresos 
              WHERE user_id = ? 
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
    // Implementar lógica para contar egresos pendientes
    return 3; // Ejemplo
}

function getProximoVencimiento($user_id) {
    global $pdo;
    // Implementar lógica para obtener próximo vencimiento
    return '15/12/2023'; // Ejemplo
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egresos | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <style>
    /* Mejoras en el layout del formulario */
    .form-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .form-row {
        grid-column: span 2;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group.full-width {
        grid-column: span 2;
    }

    /* Mejoras visuales en el formulario */
    .modern-form {
        background: #fff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }

    .form-section-title {
        grid-column: span 2;
        color: #344767;
        font-size: 1.1em;
        font-weight: 600;
        margin: 20px 0 10px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e9ecef;
    }

    /* Mejoras en los inputs */
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #344767;
        font-weight: 500;
        font-size: 0.9rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1.5px solid #e9ecef;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background-color: #f8f9fa;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: #007bff;
        background-color: #fff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
        outline: none;
    }

    /* Estilo para los íconos en los inputs */
    .input-icon-wrapper {
        position: relative;
    }

    .input-icon-wrapper i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }

    .input-icon-wrapper input,
    .input-icon-wrapper select {
        padding-left: 35px;
    }

    /* Estilos para la tabla */
    .table-container {
        overflow-x: auto;
        margin: 20px 0;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .modern-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: white;
    }

    .modern-table th {
        background: #f8f9fa;
        padding: 15px;
        font-weight: 600;
        color: #344767;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e9ecef;
    }

    .modern-table td {
        padding: 12px 15px;
        vertical-align: middle;
        border-bottom: 1px solid #e9ecef;
    }

    .modern-table tr:hover {
        background-color: #f8f9fa;
    }

    /* Estilos para elementos específicos */
    .factura-numero {
        font-family: 'Courier New', monospace;
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.9em;
        color: #495057;
    }

    .proveedor-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .proveedor-info i {
        color: #6c757d;
    }

    .descripcion-wrapper {
        max-width: 250px;
        position: relative;
    }

    .descripcion-text {
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .nota-badge {
        position: absolute;
        right: -5px;
        top: -5px;
        color: #ffc107;
        cursor: help;
    }

    .monto-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .monto-badge {
        font-weight: 600;
        color: #dc3545;
    }

    .metodo-pago-badge {
        color: #6c757d;
        font-size: 0.9em;
    }

    .fecha-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #6c757d;
    }

    .categoria-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 500;
    }

    .estado-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: 500;
    }

    .estado-badge.pendiente { background: #fff3cd; color: #856404; }
    .estado-badge.pagado { background: #d4edda; color: #155724; }
    .estado-badge.anulado { background: #f8d7da; color: #721c24; }

    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border: none;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-icon:hover {
        transform: translateY(-2px);
    }

    .btn-icon.view { color: #007bff; }
    .btn-icon.edit { color: #28a745; }
    .btn-icon.delete { color: #dc3545; }

    .btn-icon.view:hover { background: rgba(0,123,255,0.1); }
    .btn-icon.edit:hover { background: rgba(40,167,69,0.1); }
    .btn-icon.delete:hover { background: rgba(220,53,69,0.1); }

    /* Estilos para el encabezado de acciones */
    .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .actions {
        display: flex;
        gap: 15px;
        align-items: center;
    }

    /* Mejoras en la búsqueda */
    .search-box {
        position: relative;
        min-width: 250px;
    }

    .search-box input {
        width: 100%;
        padding: 10px 35px 10px 15px;
        border: 1.5px solid #e9ecef;
        border-radius: 8px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
    }

    .search-box i {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }

    /* Agregar estos estilos en la sección de estilos */
    .factura-numero {
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #495057;
        padding: 4px 8px;
        background: #f8f9fa;
        border-radius: 4px;
    }

    .descripcion-cell {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: help;
    }

    .metodo-pago {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 8px;
        background: #f8f9fa;
        border-radius: 4px;
        font-size: 0.9em;
    }

    .monto-negative {
        color: #dc3545;
        font-weight: 600;
        font-family: 'Courier New', monospace;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    .btn-icon:hover {
        transform: translateY(-2px);
    }

    .btn-icon.view:hover {
        background-color: rgba(0, 86, 179, 0.1);
        color: #0056b3;
    }

    .btn-icon.edit:hover {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }

    .btn-icon.delete:hover {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }

    /* Mejoras en los badges de categoría */
    .badge {
        padding: 6px 12px;
        border-radius: 15px;
        font-size: 0.85em;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .badge-compras { background: #e8f5e9; color: #2e7d32; }
    .badge-servicios { background: #e3f2fd; color: #1565c0; }
    .badge-nomina { background: #f3e5f5; color: #7b1fa2; }
    .badge-impuestos { background: #fff3e0; color: #e65100; }
    .badge-mantenimiento { background: #e1f5fe; color: #0277bd; }
    .badge-otros, .badge-general { background: #f5f5f5; color: #616161; }

    /* Mejoras en la tabla */
    .table-responsive {
        margin: 20px 0;
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .modern-table th {
        background: #f8f9fa;
        padding: 12px;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85em;
        color: #344767;
    }

    .modern-table td {
        padding: 12px;
        vertical-align: middle;
    }

    .modern-table tr:hover {
        background-color: #f8f9fa;
    }

    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .summary-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.3s ease;
    }

    .summary-card:hover {
        transform: translateY(-5px);
    }

    .card-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: linear-gradient(145deg, #007bff, #0056b3);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
    }

    .card-icon i {
        font-size: 24px;
        color: white;
    }

    .card-info h3 {
        margin: 0;
        font-size: 0.9rem;
        color: #666;
    }

    .card-info .amount {
        font-size: 1.5rem;
        font-weight: 600;
        color: #344767;
        margin: 5px 0;
    }

    .trend {
        font-size: 0.85rem;
        color: #666;
    }

    .trend i {
        margin-right: 5px;
    }

    .form-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #344767;
        font-weight: 500;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 15px;
        border: 1.5px solid #e9ecef;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
    }

    .table-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .quick-filters {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .filter-btn {
        padding: 8px 15px;
        border: none;
        border-radius: 20px;
        background: #f8f9fa;
        color: #495057;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .filter-btn.active {
        background: #007bff;
        color: white;
    }

    .pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e9ecef;
    }

    .page-btn {
        padding: 8px 12px;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        margin: 0 3px;
        color: #495057;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .page-btn.active {
        background: #007bff;
        color: white;
        border-color: #007bff;
    }

    .page-btn:hover:not(.active) {
        background: #f8f9fa;
    }

    /* Mejoras visuales en los botones */
    .btn-primary, .btn-secondary, .btn-export {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .btn-primary {
        background: linear-gradient(145deg, #007bff, #0056b3);
        color: white;
    }

    .btn-secondary {
        background: linear-gradient(145deg, #6c757d, #495057);
        color: white;
    }

    .btn-export {
        background: linear-gradient(145deg, #28a745, #1e7e34);
        color: white;
    }

    .btn-primary:hover, .btn-secondary:hover, .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    /* Mejoras en las alertas */
    .swal2-popup {
        border-radius: 15px;
        padding: 2em;
    }

    .swal2-title {
        color: #344767;
        font-size: 1.5em;
    }

    .swal2-html-container {
        color: #495057;
    }

    .swal2-confirm {
        background: linear-gradient(145deg, #007bff, #0056b3) !important;
        border-radius: 8px !important;
    }

    .swal2-cancel {
        background: linear-gradient(145deg, #6c757d, #495057) !important;
        border-radius: 8px !important;
    }

    /* Mejoras en el formulario */
    .form-section {
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .btn-collapse {
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        transition: transform 0.3s ease;
    }

    .btn-collapse.collapsed {
        transform: rotate(-180deg);
    }

    /* Animaciones para el formulario */
    .form-group {
        transition: all 0.3s ease;
    }

    .form-group:focus-within label {
        color: #007bff;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        transform: translateY(-2px);
    }

    /* Estado badges mejorados */
    .estado-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 500;
        text-transform: capitalize;
    }

    .estado-badge.pendiente {
        background: linear-gradient(145deg, #ffc107, #d39e00);
        color: white;
    }

    .estado-badge.pagado {
        background: linear-gradient(145deg, #28a745, #1e7e34);
        color: white;
    }

    .estado-badge.anulado {
        background: linear-gradient(145deg, #dc3545, #bd2130);
        color: white;
    }

    .file-input-wrapper {
        position: relative;
    }

    .file-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .file-input-preview {
        padding: 10px;
        border: 1.5px dashed #e9ecef;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8f9fa;
        min-height: 42px;
    }

    .file-name {
        color: #6c757d;
        font-size: 0.9em;
    }

    .btn-clear-file {
        background: none;
        border: none;
        color: #dc3545;
        cursor: pointer;
        padding: 5px;
        border-radius: 4px;
    }

    .btn-clear-file:hover {
        background: rgba(220, 53, 69, 0.1);
    }

    /* Estilos para el estado en la tabla */
    .estado-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .estado-badge.pendiente {
        background: #fff3cd;
        color: #856404;
    }

    .estado-badge.pagado {
        background: #d4edda;
        color: #155724;
    }

    .estado-badge.anulado {
        background: #f8d7da;
        color: #721c24;
    }

    .input-group {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-group input {
        padding-right: 40px; /* Espacio para el botón */
    }

    .btn-generate {
        position: absolute;
        right: 0;
        top: 0;
        height: 100%;
        width: 40px;
        border: none;
        background: none;
        color: #6c757d;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .btn-generate:hover {
        color: #007bff;
        background-color: rgba(0, 123, 255, 0.1);
    }

    .btn-generate:active {
        transform: scale(0.95);
    }

    .btn-today {
        position: absolute;
        right: 0;
        top: 0;
        height: 100%;
        width: 40px;
        border: none;
        background: none;
        color: #6c757d;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .btn-today:hover {
        color: #007bff;
        background-color: rgba(0, 123, 255, 0.1);
    }

    .btn-today:active {
        transform: scale(0.95);
    }

    /* Agregar estos estilos en la sección de estilos */
    .btn-clear-filters {
        padding: 8px 15px;
        border: none;
        border-radius: 20px;
        background: #dc3545;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.9em;
    }

    .btn-clear-filters:hover {
        background: #c82333;
        transform: translateY(-2px);
    }

    .btn-clear-filters i {
        font-size: 0.8em;
    }

    .filter-select {
        padding: 8px 15px;
        border: 1px solid #e9ecef;
        border-radius: 20px;
        background: #f8f9fa;
        color: #495057;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9em;
    }

    .filter-select:hover {
        border-color: #007bff;
    }

    .filter-select:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
    }

    .no-results-message td {
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const currentUrl = window.location.pathname;
                const sidebarLinks = document.querySelectorAll('.side_navbar a');
                sidebarLinks.forEach(link => {
                    if (link.getAttribute('href') === currentUrl) {
                        link.classList.add('active');
                    }
                });
            });
        </script>

        <div class="main-body">
            <h2>Gestionar Egresos</h2>
            
            <!-- Tarjetas de resumen -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="card-info">
                        <h3>Total Egresos Mes</h3>
                        <p class="amount">$<?= number_format(getTotalEgresosMes($user_id), 2, ',', '.') ?></p>
                        <span class="trend">
                            <?php $porcentaje = calcularPorcentajeCambio($user_id); ?>
                            <i class="fas fa-<?= $porcentaje >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <?= abs($porcentaje) ?>% vs mes anterior
                        </span>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-icon purple">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="card-info">
                        <h3>Promedio por Egreso</h3>
                        <p class="amount">$<?= number_format(getPromedioEgresos($user_id), 2, ',', '.') ?></p>
                        <span class="category">Principal: <?= getCategoriaFrecuente($user_id) ?></span>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-icon green">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="card-info">
                        <h3>Egresos Pendientes</h3>
                        <p class="amount"><?= getEgresosPendientes($user_id) ?></p>
                        <span class="date">Próximo: <?= getProximoVencimiento($user_id) ?></span>
                    </div>
                </div>
            </div>

            <!-- Formulario mejorado -->
            <div class="form-section">
                <div class="section-header">
                    <h3><i class="fas fa-plus-circle"></i> Nuevo Egreso</h3>
                    <button class="btn-collapse" onclick="toggleForm()">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                
                <form id="egresoForm" class="modern-form">
                    <div class="form-grid">
                        <!-- Primera columna -->
                        <div class="form-column">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-receipt"></i> Número de Factura
                                </label>
                                <div class="input-group">
                                    <input type="text" name="numero_factura" id="numero_factura" required>
                                    <button type="button" class="btn-generate" onclick="generarNumeroFactura()" title="Generar número">
                                        <i class="fas fa-random"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-truck"></i> Proveedor
                                </label>
                                <select name="proveedor" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?= htmlspecialchars($prov['nombre']) ?>">
                                            <?= htmlspecialchars($prov['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-dollar-sign"></i> Monto
                                </label>
                                <input type="number" name="monto" step="0.01" required>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-check-circle"></i> Estado
                                </label>
                                <select name="estado" required>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="pagado">Pagado</option>
                                    <option value="anulado">Anulado</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Segunda columna -->
                        <div class="form-column">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-calendar"></i> Fecha
                                </label>
                                <div class="input-group">
                                    <input type="date" name="fecha" id="fecha" required>
                                    <button type="button" class="btn-today" onclick="setToday()" title="Establecer fecha actual">
                                        <i class="fas fa-calendar-day"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-tags"></i> Categoría
                                </label>
                                <select name="categoria" required>
                                    <?php foreach (getCategoriasEgresos() as $cat): ?>
                                        <option value="<?= $cat ?>"><?= $cat ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-credit-card"></i> Método de Pago
                                </label>
                                <select name="metodo_pago" required>
                                    <?php foreach (getMetodosPago() as $metodo): ?>
                                        <option value="<?= $metodo ?>"><?= $metodo ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-file-upload"></i> Comprobante
                                </label>
                                <div class="file-input-wrapper">
                                    <input type="file" name="comprobante" accept="image/*,.pdf" class="file-input">
                                    <div class="file-input-preview">
                                        <span class="file-name">Ningún archivo seleccionado</span>
                                        <button type="button" class="btn-clear-file" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de descripción y notas -->
                    <div class="form-full-width">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-align-left"></i> Descripción
                            </label>
                            <textarea name="descripcion" rows="2" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-sticky-note"></i> Notas Adicionales
                            </label>
                            <textarea name="notas" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Egreso
                        </button>
                        <button type="reset" class="btn-secondary">
                            <i class="fas fa-undo"></i> Limpiar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabla mejorada -->
            <div class="table-section">
                <div class="table-header">
                    <div class="table-title">
                        <h3><i class="fas fa-list"></i> Listado de Egresos</h3>
                        <span class="badge"><?= $total_egresos ?> registros</span>
                    </div>
                    
                    <div class="table-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Buscar egreso...">
                        </div>
                        
                        <button class="btn-export" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i> Exportar PDF
                        </button>
                    </div>
                </div>

                <!-- Filtros rápidos -->
                <div class="quick-filters">
                    <button class="filter-btn active" data-filter="all">Todos</button>
                    <button class="filter-btn" data-filter="month">Este Mes</button>
                    <button class="filter-btn" data-filter="pending">Pendientes</button>
                    <select class="filter-select" id="categoryFilter">
                        <option value="">Todas las categorías</option>
                        <?php foreach (getCategoriasEgresos() as $cat): ?>
                            <option value="<?= $cat ?>"><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>N° Factura</th>
                                <th>Proveedor</th>
                                <th>Descripción</th>
                                <th>Monto</th>
                                <th>Fecha</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($egresos)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No hay egresos registrados</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($egresos as $egreso): ?>
                                    <tr>
                                        <td>
                                            <span class="factura-numero" title="Creado: <?= date('d/m/Y', strtotime($egreso['created_at'])) ?>">
                                                <?= htmlspecialchars($egreso['numero_factura']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="proveedor-info">
                                                <i class="fas fa-building"></i>
                                                <span><?= htmlspecialchars($egreso['proveedor']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="descripcion-wrapper" title="<?= htmlspecialchars($egreso['descripcion']) ?>">
                                                <p class="descripcion-text"><?= htmlspecialchars($egreso['descripcion']) ?></p>
                                                <?php if (!empty($egreso['notas'])): ?>
                                                    <span class="nota-badge" title="<?= htmlspecialchars($egreso['notas']) ?>">
                                                        <i class="fas fa-sticky-note"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="monto-wrapper">
                                                <span class="monto-badge negative">
                                                    -$<?= number_format($egreso['monto'], 2, ',', '.') ?>
                                                </span>
                                                <span class="metodo-pago-badge" title="Método de pago">
                                                    <i class="fas fa-<?= getMetodoPagoIcon($egreso['metodo_pago']) ?>"></i>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fecha-wrapper">
                                                <i class="fas fa-calendar"></i>
                                                <span><?= date('d/m/Y', strtotime($egreso['fecha'])) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="categoria-badge badge-<?= strtolower($egreso['categoria']) ?>">
                                                <?= htmlspecialchars($egreso['categoria']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="estado-badge <?= $egreso['estado'] ?>">
                                                <?= ucfirst($egreso['estado']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if (!empty($egreso['comprobante'])): ?>
                                                    <button class="btn-icon view" onclick="verComprobante('<?= htmlspecialchars($egreso['comprobante']) ?>')" 
                                                            title="Ver comprobante">
                                                        <i class="fas fa-file-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn-icon edit" onclick="editarEgreso(<?= $egreso['id'] ?>)" 
                                                        title="Editar egreso">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <button class="btn-icon delete" onclick="confirmarEliminacion(<?= $egreso['id'] ?>)" 
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
                <div class="pagination">
                    <div class="pagination-info">
                        Mostrando <?= ($offset + 1) ?> - <?= min($offset + $limit, $total_egresos) ?> 
                        de <?= $total_egresos ?> registros
                    </div>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a href="?page=1" class="page-btn">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?= $page-1 ?>" class="page-btn">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>" 
                               class="page-btn <?= ($page === $i) ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page+1 ?>" class="page-btn">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?= $total_pages ?>" class="page-btn">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script para exportar a PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

    <script>
    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Configuración del documento
        doc.setProperties({
            title: 'Reporte de Egresos',
            subject: 'Registro de egresos',
            author: 'VendEasy'
        });
        
        // Encabezado
        doc.setFontSize(20);
        doc.setTextColor(52, 71, 103);
        doc.text('Reporte de Egresos', 14, 20);
        
        // Información del reporte
        doc.setFontSize(10);
        doc.setTextColor(108, 117, 125);
        doc.text(`Fecha de generación: ${new Date().toLocaleDateString()}`, 14, 30);
        doc.text(`Total de registros: ${<?= $total_egresos ?>}`, 14, 35);
        
        // Resumen
        doc.setFontSize(12);
        doc.setTextColor(52, 71, 103);
        doc.text('Resumen del Periodo', 14, 45);
        
        // Tabla de resumen
        const summaryData = [
            ['Total Mes:', `$${formatMoney(<?= getTotalEgresosMes($user_id) ?>)}`],
            ['Promedio:', `$${formatMoney(<?= getPromedioEgresos($user_id) ?>)}`],
            ['Categoría Principal:', '<?= getCategoriaFrecuente($user_id) ?>']
        ];
        
        doc.autoTable({
            startY: 50,
            head: [['Concepto', 'Valor']],
            body: summaryData,
            theme: 'grid',
            headStyles: { fillColor: [52, 71, 103] },
            styles: { fontSize: 10 }
        });
        
        // Tabla principal de egresos
        doc.autoTable({
            startY: doc.previousAutoTable.finalY + 15,
            head: [['N° Factura', 'Proveedor', 'Descripción', 'Monto', 'Fecha', 'Categoría', 'Estado']],
            body: Array.from(document.querySelectorAll('.modern-table tbody tr')).map(row => [
                row.cells[0].textContent.trim(),
                row.cells[1].textContent.trim(),
                row.cells[2].textContent.trim(),
                row.cells[3].textContent.trim(),
                row.cells[4].textContent.trim(),
                row.cells[5].textContent.trim(),
                row.cells[6].textContent.trim()
            ]),
            styles: { fontSize: 8 },
            headStyles: { fillColor: [52, 71, 103] },
            alternateRowStyles: { fillColor: [245, 247, 250] }
        });
        
        // Pie de página
        const pageCount = doc.internal.getNumberOfPages();
        for(let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(108, 117, 125);
            doc.text(
                `Página ${i} de ${pageCount} - VendEasy`,
                doc.internal.pageSize.width / 2,
                doc.internal.pageSize.height - 10,
                { align: 'center' }
            );
        }
        
        // Guardar el PDF
        doc.save('reporte_egresos.pdf');
    }

    function formatMoney(amount) {
        return new Intl.NumberFormat('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    // Función para ver el comprobante
    function verComprobante(url) {
        Swal.fire({
            title: 'Comprobante',
            imageUrl: url,
            imageWidth: 400,
            imageHeight: 'auto',
            imageAlt: 'Comprobante del egreso',
            confirmButtonText: 'Cerrar'
        });
    }

    // Función para confirmar eliminación
    function confirmarEliminacion(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                eliminarEgreso(id);
            }
        });
    }

    // Funciones para filtros y búsqueda
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const categoryFilter = document.getElementById('categoryFilter');
        const tableRows = document.querySelectorAll('.modern-table tbody tr');
        
        // Función para filtrar filas
        function filterRows() {
            const searchTerm = searchInput.value.toLowerCase();
            const activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
            const selectedCategory = categoryFilter.value.toLowerCase();
            
            tableRows.forEach(row => {
                let showRow = true;
                
                // Filtro de búsqueda
                if (searchTerm) {
                    const textContent = row.textContent.toLowerCase();
                    showRow = textContent.includes(searchTerm);
                }
                
                // Filtro de período
                if (showRow && activeFilter !== 'all') {
                    const fecha = row.querySelector('.fecha-wrapper span').textContent;
                    const fechaObj = parseDate(fecha);
                    
                    if (activeFilter === 'month') {
                        const today = new Date();
                        showRow = fechaObj.getMonth() === today.getMonth() && 
                                 fechaObj.getFullYear() === today.getFullYear();
                    } else if (activeFilter === 'pending') {
                        const estado = row.querySelector('.estado-badge').textContent.toLowerCase();
                        showRow = estado === 'pendiente';
                    }
                }
                
                // Filtro de categoría
                if (showRow && selectedCategory) {
                    const categoria = row.querySelector('.categoria-badge').textContent.toLowerCase();
                    showRow = categoria === selectedCategory;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
            
            updateNoResultsMessage();
        }
        
        // Función para parsear fecha en formato dd/mm/yyyy
        function parseDate(dateStr) {
            const [day, month, year] = dateStr.split('/');
            return new Date(year, month - 1, day);
        }
        
        // Función para mostrar mensaje cuando no hay resultados
        function updateNoResultsMessage() {
            const visibleRows = document.querySelectorAll('.modern-table tbody tr:not([style*="display: none"])');
            const tbody = document.querySelector('.modern-table tbody');
            const existingMessage = document.querySelector('.no-results-message');
            
            if (visibleRows.length === 0) {
                if (!existingMessage) {
                    const messageRow = document.createElement('tr');
                    messageRow.className = 'no-results-message';
                    messageRow.innerHTML = `
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            <div style="color: #6c757d;">
                                <i class="fas fa-search" style="font-size: 24px; margin-bottom: 10px;"></i>
                                <p>No se encontraron resultados</p>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(messageRow);
                }
            } else if (existingMessage) {
                existingMessage.remove();
            }
        }
        
        // Event listeners
        searchInput.addEventListener('input', filterRows);
        
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                filterRows();
            });
        });
        
        categoryFilter.addEventListener('change', filterRows);
        
        // Agregar botón para limpiar filtros
        const clearFiltersBtn = document.createElement('button');
        clearFiltersBtn.className = 'btn-clear-filters';
        clearFiltersBtn.innerHTML = '<i class="fas fa-times"></i> Limpiar filtros';
        document.querySelector('.quick-filters').appendChild(clearFiltersBtn);
        
        clearFiltersBtn.addEventListener('click', function() {
            searchInput.value = '';
            categoryFilter.value = '';
            filterBtns.forEach(btn => {
                if (btn.dataset.filter === 'all') {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            filterRows();
            
            Toast.fire({
                icon: 'info',
                title: 'Filtros limpiados'
            });
        });
    });

    // Función mejorada para editar egreso
    function editarEgreso(id) {
        // Mostrar loading
        Swal.fire({
            title: 'Cargando...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Obtener datos del egreso
        $.post('', {
            action: 'get_egreso',
            id: id
        })
        .done(function(response) {
            Swal.close();
            
            if (response.status) {
                const egreso = response.data;
                
                // Llenar el formulario
                document.querySelector('[name="numero_factura"]').value = egreso.numero_factura;
                document.querySelector('[name="proveedor"]').value = egreso.proveedor;
                document.querySelector('[name="descripcion"]').value = egreso.descripcion;
                document.querySelector('[name="monto"]').value = egreso.monto;
                document.querySelector('[name="fecha"]').value = egreso.fecha_formateada;
                document.querySelector('[name="categoria"]').value = egreso.categoria;
                document.querySelector('[name="metodo_pago"]').value = egreso.metodo_pago;
                document.querySelector('[name="estado"]').value = egreso.estado;
                document.querySelector('[name="notas"]').value = egreso.notas || '';

                // Modificar el formulario para modo edición
                const form = document.getElementById('egresoForm');
                form.dataset.mode = 'edit';
                form.dataset.editId = id;

                // Cambiar texto del botón
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Actualizar Egreso';

                // Scroll suave al formulario
                document.querySelector('.form-section').scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });

                // Mostrar notificación
                Toast.fire({
                    icon: 'info',
                    title: 'Editando egreso'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message
                });
            }
        })
        .fail(function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al cargar los datos del egreso'
            });
        });
    }

    // Mejorar el manejador del formulario
    document.getElementById('egresoForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const isEdit = this.dataset.mode === 'edit';
        
        if (isEdit) {
            formData.append('action', 'update_egreso');
            formData.append('id', this.dataset.editId);
        } else {
            formData.append('action', 'add_egreso');
        }

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
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: data.message,
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al procesar la solicitud'
            });
        }
    });

    // Configuración global de notificaciones
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // Función para alternar el formulario
    function toggleForm() {
        const formSection = document.querySelector('.form-section');
        const btn = document.querySelector('.btn-collapse');
        const form = document.getElementById('egresoForm');
        
        if (formSection.style.maxHeight) {
            formSection.style.maxHeight = null;
            btn.classList.add('collapsed');
        } else {
            formSection.style.maxHeight = form.scrollHeight + "px";
            btn.classList.remove('collapsed');
        }
    }

    // Función para resetear el formulario
    document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: '¿Limpiar formulario?',
            text: "Se borrarán todos los datos ingresados",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6c757d',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('egresoForm').reset();
                Toast.fire({
                    icon: 'info',
                    title: 'Formulario limpiado'
                });
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.querySelector('.file-input');
        const fileName = document.querySelector('.file-name');
        const clearButton = document.querySelector('.btn-clear-file');
        
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                fileName.textContent = this.files[0].name;
                clearButton.style.display = 'block';
            } else {
                fileName.textContent = 'Ningún archivo seleccionado';
                clearButton.style.display = 'none';
            }
        });
        
        clearButton.addEventListener('click', function(e) {
            e.preventDefault();
            fileInput.value = '';
            fileName.textContent = 'Ningún archivo seleccionado';
            this.style.display = 'none';
        });
    });

    function generarNumeroFactura() {
        // Generar un prefijo de 2 letras
        const letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const prefijo = Array(2).fill().map(() => letras.charAt(Math.floor(Math.random() * letras.length))).join('');
        
        // Generar 6 números aleatorios
        const numeros = Array(6).fill().map(() => Math.floor(Math.random() * 10)).join('');
        
        // Obtener el año actual (últimos 2 dígitos)
        const año = new Date().getFullYear().toString().substr(-2);
        
        // Combinar todo en el formato deseado
        const numeroFactura = `${prefijo}${numeros}-${año}`;
        
        // Establecer el valor en el input
        document.getElementById('numero_factura').value = numeroFactura;
        
        // Mostrar una notificación
        Toast.fire({
            icon: 'success',
            title: 'Número de factura generado'
        });
    }

    // Opcional: Generar número al cargar el formulario si el campo está vacío
    document.addEventListener('DOMContentLoaded', function() {
        const numeroFacturaInput = document.getElementById('numero_factura');
        if (!numeroFacturaInput.value) {
            generarNumeroFactura();
        }
    });

    // Función para establecer la fecha actual
    function setToday() {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const formattedDate = `${year}-${month}-${day}`;
        
        document.getElementById('fecha').value = formattedDate;
        
        Toast.fire({
            icon: 'success',
            title: 'Fecha actualizada'
        });
    }

    // Establecer fecha actual al cargar el formulario
    document.addEventListener('DOMContentLoaded', function() {
        const fechaInput = document.getElementById('fecha');
        if (!fechaInput.value) {
            setToday();
        }
    });
    </script>
</body>
</html>