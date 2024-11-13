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

function getMetodoPagoIcon($metodo_pago) {
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

function addIngreso($user_id, $data) {
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
    <title>Ingresos | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" />
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
    /* Estilos base */
    body {
        font-family: 'Poppins', sans-serif;
    }

    /* Mejoras en el formulario */
    .modern-form {
        background: #fff;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }

    .form-group label {
        font-size: 0.9rem;
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

    /* Mejoras en la tabla */
    .table-responsive {
        background: #fff;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }

    .modern-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 1rem;
    }

    .modern-table th {
        background: #f8f9fa;
        padding: 15px;
        font-weight: 600;
        color: #344767;
        border-bottom: 2px solid #e9ecef;
        text-transform: uppercase;
        font-size: 0.85rem;
    }

    .modern-table td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        color: #495057;
        vertical-align: middle;
    }

    .modern-table tr:hover {
        background-color: #f8f9fa;
    }

    /* Mejoras en los badges */
    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    /* Mejoras en los botones */
    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(145deg, #007bff, #0056b3);
        color: white;
    }

    .btn-success {
        background: linear-gradient(145deg, #28a745, #1e7e34);
        color: white;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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

    /* Mejoras en la paginación */
    .pagination-container {
        margin-top: 30px;
        margin-bottom: 20px;
    }

    .modern-pagination {
        background: #fff;
        padding: 10px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .page-link {
        padding: 8px 16px;
        margin: 0 3px;
        border-radius: 6px;
        font-weight: 500;
    }

    /* Animaciones */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .modern-form, .table-responsive {
        animation: fadeIn 0.5s ease-out;
    }

    /* Mejoras en el layout del formulario */
    .form-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    /* Mejoras en los campos de moneda */
    .currency-input {
        position: relative;
    }

    .currency-input input {
        padding-left: 30px;
    }

    .currency-input::before {
        content: '$';
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #495057;
        font-weight: 500;
    }

    /* Mejoras en la tabla */
    .monto-column {
        font-weight: 500;
        color: #28a745;
    }

    /* Estilos para el modal de exportación */
    .export-options {
        padding: 20px;
    }

    .export-options label {
        display: block;
        margin-bottom: 10px;
    }

    .export-options select {
        width: 100%;
        padding: 8px;
        margin-bottom: 15px;
        border-radius: 6px;
        border: 1px solid #ddd;
    }

    /* Mejoras en los badges de categoría */
    .badge-ventas { background: #e8f5e9; color: #2e7d32; }
    .badge-servicios { background: #e3f2fd; color: #1565c0; }
    .badge-comisiones { background: #fff3e0; color: #f57c00; }
    .badge-otros { background: #f5f5f5; color: #616161; }

    /* En la sección de estilos, agregar: */
    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .btn-icon {
        padding: 6px;
        border: none;
        background: none;
        cursor: pointer;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .btn-icon:hover {
        transform: translateY(-2px);
    }

    .btn-icon.view {
        color: #0056b3;
    }

    .btn-icon.edit {
        color: #28a745;
    }

    .btn-icon.delete {
        color: #dc3545;
    }

    .btn-icon:hover.view {
        background-color: rgba(0, 86, 179, 0.1);
    }

    .btn-icon:hover.edit {
        background-color: rgba(40, 167, 69, 0.1);
    }

    .btn-icon:hover.delete {
        background-color: rgba(220, 53, 69, 0.1);
    }

    /* En la sección de estilos, agregar: */
    .detail-modal {
        font-family: 'Poppins', sans-serif;
    }

    .detail-content {
        padding: 20px;
    }

    .ingreso-detalles {
        text-align: left;
    }

    .detail-row {
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-row strong {
        display: block;
        margin-bottom: 5px;
        color: #344767;
    }

    .detail-row p {
        margin: 0;
        color: #495057;
    }

    .monto-destacado {
        font-size: 1.2em;
        color: #28a745;
        font-weight: 500;
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
            <h2>Gestionar Ingresos</h2>
            <div class="promo_card">
                <h1>Registro de Ingresos</h1>
                <span>Aquí puedes agregar y visualizar tus ingresos.</span>
            </div>

            <div class="history_lists">
                <div class="list1">
                    <div class="row">
                        <h4>Agregar Nuevo Ingreso</h4>
                    </div>
                    <form id="ingresoForm" class="modern-form">
                        <div class="form-container">
                            <div class="form-group">
                                <label for="descripcion">
                                    <i class="fas fa-file-alt"></i> Descripción
                                </label>
                                <input type="text" id="descripcion" name="descripcion" 
                                       placeholder="Ej: Venta de productos" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="categoria">
                                    <i class="fas fa-tags"></i> Categoría
                                </label>
                                <select id="categoria" name="categoria" required>
                                    <option value="">Seleccione una categoría</option>
                                    <option value="Ventas">Ventas</option>
                                    <option value="Servicios">Servicios</option>
                                    <option value="Comisiones">Comisiones</option>
                                    <option value="Otros">Otros</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="monto">
                                    <i class="fas fa-dollar-sign"></i> Monto (COP)
                                </label>
                                <div class="currency-input">
                                    <input type="number" step="1" id="monto" name="monto" 
                                           placeholder="0" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="metodo_pago">
                                    <i class="fas fa-credit-card"></i> Método de Pago
                                </label>
                                <select id="metodo_pago" name="metodo_pago" required>
                                    <option value="">Seleccione método de pago</option>
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label for="notas">
                                    <i class="fas fa-sticky-note"></i> Notas Adicionales
                                </label>
                                <textarea id="notas" name="notas" rows="3" 
                                          placeholder="Agregar notas o detalles adicionales"></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Agregar Ingreso
                        </button>
                    </form>
                </div>

                <div class="list2">
                    <div class="row header-actions">
                        <h4>Listado de Ingresos</h4>
                        <div class="actions">
                            <button id="exportExcel" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Exportar
                            </button>
                            <div class="search-box">
                                <input type="text" id="searchInput" placeholder="Buscar...">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Descripción</th>
                                    <th>Categoría</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ingresos as $ingreso): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ingreso['descripcion']); ?></td>
                                        <td>
                                            <span class="badge badge-<?= strtolower($ingreso['categoria']); ?>">
                                                <?= htmlspecialchars($ingreso['categoria']); ?>
                                            </span>
                                        </td>
                                        <td class="monto">$<?= $ingreso['monto_formateado']; ?></td>
                                        <td>
                                            <i class="fas fa-<?= getMetodoPagoIcon($ingreso['metodo_pago']); ?>"></i>
                                            <?= htmlspecialchars($ingreso['metodo_pago']); ?>
                                        </td>
                                        <td><?= $ingreso['fecha_formateada']; ?></td>
                                        <td class="actions">
                                            <div class="action-buttons">
                                                <button class="btn-icon view" onclick="verDetalles(<?= $ingreso['id']; ?>)" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-icon edit" onclick="editarIngreso(<?= $ingreso['id']; ?>)" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon delete" onclick="eliminarIngreso(<?= $ingreso['id']; ?>)" title="Eliminar">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación mejorada -->
                    <div class="pagination-container">
                        <div class="pagination modern-pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1" class="page-link">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?page=<?= $page-1; ?>" class="page-link">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i; ?>" 
                                   class="page-link <?= ($page === $i) ? 'active' : ''; ?>">
                                    <?= $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page+1; ?>" class="page-link">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?page=<?= $total_pages; ?>" class="page-link">
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

    // Función para mostrar notificaciones
    function showNotification(type, message) {
        Toast.fire({
            icon: type,
            title: message
        });
    }

    // Función para mostrar errores
    function showError(title, message) {
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonText: 'Entendido'
        });
    }

    // Función para validar el formulario
    function validateForm(formData) {
        const monto = parseFloat(formData.get('monto'));
        const descripcion = formData.get('descripcion').trim();
        
        if (descripcion.length < 3) {
            showError('Error de validación', 'La descripción debe tener al menos 3 caracteres');
            return false;
        }
        
        if (monto <= 0) {
            showError('Error de validación', 'El monto debe ser mayor que cero');
            return false;
        }
        
        return true;
    }

    // Manejador del formulario de ingreso
    document.getElementById('ingresoForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add_ingreso');

        if (!validateForm(formData)) {
            return;
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
                showNotification('success', data.message);
                // Limpiar formulario
                this.reset();
                // Recargar tabla después de un breve delay
                setTimeout(() => location.reload(), 1500);
            } else {
                showError('Error', data.message);
            }
        } catch (error) {
            showError('Error', 'Ocurrió un error al procesar la solicitud');
        }
    });

    // Estilo personalizado para SweetAlert2
    const style = document.createElement('style');
    style.textContent = `
        .swal2-popup {
            font-family: 'Poppins', sans-serif;
            border-radius: 12px;
        }
        .swal2-title {
            color: #344767;
        }
        .swal2-html-container {
            color: #495057;
        }
        .swal2-confirm {
            background: linear-gradient(145deg, #007bff, #0056b3) !important;
        }
        .swal2-cancel {
            background: linear-gradient(145deg, #6c757d, #495057) !important;
        }
    `;
    document.head.appendChild(style);

    // Función para filtrar la tabla
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const table = document.querySelector('.modern-table tbody');
        const rows = table.getElementsByTagName('tr');

        Array.from(rows).forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });

    // Función para formatear moneda colombiana
    function formatCOP(value) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(value);
    }

    // Función mejorada para exportar a Excel
    function exportarExcel() {
        Swal.fire({
            title: 'Exportar a Excel',
            html: `
                <div class="export-options">
                    <label for="dateRange">Rango de fechas:</label>
                    <select id="dateRange">
                        <option value="all">Todos los registros</option>
                        <option value="today">Hoy</option>
                        <option value="week">Esta semana</option>
                        <option value="month">Este mes</option>
                        <option value="custom">Personalizado</option>
                    </select>
                    
                    <div id="customDates" style="display: none;">
                        <label>Desde:</label>
                        <input type="date" id="startDate">
                        <label>Hasta:</label>
                        <input type="date" id="endDate">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Exportar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const range = document.getElementById('dateRange').value;
                const startDate = document.getElementById('startDate')?.value;
                const endDate = document.getElementById('endDate')?.value;
                
                return { range, startDate, endDate };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const table = document.querySelector('.modern-table');
                const ws = XLSX.utils.table_to_sheet(table);
                
                // Dar formato a las columnas
                const range = XLSX.utils.decode_range(ws['!ref']);
                for(let C = range.s.c; C <= range.e.c; C++) {
                    const address = XLSX.utils.encode_col(C) + "1";
                    if(!ws[address]) continue;
                    ws[address].s = {
                        font: { bold: true },
                        fill: { fgColor: { rgb: "E9ECEF" } }
                    };
                }

                // Ajustar anchos de columna
                const wscols = [
                    {wch: 30}, // Descripción
                    {wch: 15}, // Categoría
                    {wch: 15}, // Monto
                    {wch: 15}, // Método
                    {wch: 20}  // Fecha
                ];
                ws['!cols'] = wscols;

                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Ingresos");
                
                // Generar nombre de archivo con fecha
                const fecha = new Date().toISOString().split('T')[0];
                XLSX.writeFile(wb, `Ingresos_${fecha}.xlsx`);
            }
        });
    }

    // Actualizar el evento del botón de exportar
    document.getElementById('exportExcel').addEventListener('click', exportarExcel);

    // Agregar evento para mostrar/ocultar fechas personalizadas
    document.getElementById('dateRange')?.addEventListener('change', function() {
        const customDates = document.getElementById('customDates');
        if (this.value === 'custom') {
            customDates.style.display = 'block';
        } else {
            customDates.style.display = 'none';
        }
    });

    // Formatear montos en la tabla al cargar
    document.addEventListener('DOMContentLoaded', function() {
        const montos = document.querySelectorAll('.monto');
        montos.forEach(monto => {
            const valor = parseFloat(monto.textContent.replace(/[^\d.-]/g, ''));
            monto.textContent = formatCOP(valor);
        });
    });

    // Función para ver detalles
    async function verDetalles(id) {
        try {
            const response = await fetch(`get_ingreso.php?id=${id}`);
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            
            const data = await response.json();
            
            if (data.status && data.data) {
                const ingreso = data.data;
                Swal.fire({
                    title: 'Detalles del Ingreso',
                    html: `
                        <div class="ingreso-detalles">
                            <div class="detail-row">
                                <strong><i class="fas fa-file-alt"></i> Descripción:</strong>
                                <p>${ingreso.descripcion}</p>
                            </div>
                            <div class="detail-row">
                                <strong><i class="fas fa-dollar-sign"></i> Monto:</strong>
                                <p class="monto-destacado">${formatCOP(ingreso.monto)}</p>
                            </div>
                            <div class="detail-row">
                                <strong><i class="fas fa-tags"></i> Categoría:</strong>
                                <p><span class="badge badge-${ingreso.categoria.toLowerCase()}">${ingreso.categoria}</span></p>
                            </div>
                            <div class="detail-row">
                                <strong><i class="fas fa-credit-card"></i> Método de Pago:</strong>
                                <p><i class="fas fa-${getMetodoPagoIcon(ingreso.metodo_pago)}"></i> ${ingreso.metodo_pago}</p>
                            </div>
                            <div class="detail-row">
                                <strong><i class="fas fa-calendar"></i> Fecha:</strong>
                                <p>${ingreso.fecha_formateada}</p>
                            </div>
                            <div class="detail-row">
                                <strong><i class="fas fa-sticky-note"></i> Notas:</strong>
                                <p>${ingreso.notas || '<em>Sin notas adicionales</em>'}</p>
                            </div>
                        </div>
                    `,
                    width: '600px',
                    showCloseButton: true,
                    showConfirmButton: false,
                    customClass: {
                        popup: 'detail-modal',
                        content: 'detail-content'
                    }
                });
            } else {
                throw new Error(data.message || 'No se pudieron cargar los detalles');
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Error', 'No se pudieron cargar los detalles del ingreso');
        }
    }

    // Función para editar ingreso
    async function editarIngreso(id) {
        try {
            const response = await fetch(`get_ingreso.php?id=${id}`);
            const data = await response.json();
            
            if (data.status) {
                const ingreso = data.data;
                Swal.fire({
                    title: 'Editar Ingreso',
                    html: `
                        <form id="editForm" class="edit-form">
                            <input type="hidden" name="id" value="${ingreso.id}">
                            <div class="form-group">
                                <label>Descripción</label>
                                <input type="text" name="descripcion" value="${ingreso.descripcion}" required>
                            </div>
                            <div class="form-group">
                                <label>Monto</label>
                                <input type="number" step="0.01" name="monto" value="${ingreso.monto}" required>
                            </div>
                            <div class="form-group">
                                <label>Categoría</label>
                                <select name="categoria" required>
                                    ${getCategoriaOptions(ingreso.categoria)}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Método de Pago</label>
                                <select name="metodo_pago" required>
                                    ${getMetodoPagoOptions(ingreso.metodo_pago)}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Notas</label>
                                <textarea name="notas">${ingreso.notas || ''}</textarea>
                            </div>
                        </form>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
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
                            if (!result.status) {
                                throw new Error(result.message);
                            }
                            return result;
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        showNotification('success', 'Ingreso actualizado correctamente');
                        setTimeout(() => location.reload(), 1500);
                    }
                });
            }
        } catch (error) {
            showError('Error', 'Error al cargar el ingreso para editar');
        }
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

    // Función para eliminar ingreso
    function eliminarIngreso(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
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
                        showNotification('success', data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError('Error', data.message);
                    }
                })
                .catch(error => {
                    showError('Error', 'Error al procesar la solicitud');
                });
            }
        });
    }
    </script>
</body>
</html>
