<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? null;
$email = $_SESSION['email'] ?? null;

if (!$user_id) {
    header("Location: ../../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $venta_id = (int)$_GET['id'];

    // Obtener los detalles de la venta
    $stmt = $pdo->prepare("
        SELECT v.*, c.nombre AS cliente_nombre, c.id AS cliente_id 
        FROM ventas v 
        LEFT JOIN clientes c ON v.cliente_id = c.id 
        WHERE v.id = ? AND v.user_id = ?
    ");
    $stmt->execute([$venta_id, $user_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($venta) {
        // Obtener los productos de la venta
        $stmt = $pdo->prepare("
            SELECT vd.*, i.nombre AS producto_nombre, i.stock 
            FROM venta_detalles vd 
            JOIN inventario i ON vd.producto_id = i.id 
            WHERE vd.venta_id = ?
        ");
        $stmt->execute([$venta_id]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener todos los productos del inventario
        $stmt = $pdo->prepare("
            SELECT id, nombre, precio_venta, stock 
            FROM inventario 
            WHERE user_id = ? AND stock > 0
            ORDER BY nombre ASC
        ");
        $stmt->execute([$user_id]);
        $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Venta | VendEasy</title>
    <link rel="icon" type="image/png" href="/favicon/favicon.ico"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/welcome.css">
    <link rel="stylesheet" href="../../css/modulos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .main-content {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px;
        }

        .venta-header {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-group {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
        }

        .info-group label {
            display: block;
            color: #666;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .productos-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        .productos-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #344767;
        }

        .productos-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }

        .productos-table select,
        .productos-table input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #2196f3;
            color: white;
        }

        .btn-secondary {
            background: #f44336;
            color: white;
        }

        .btn-success {
            background: #4caf50;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .eliminar-producto {
            color: #f44336;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .eliminar-producto:hover {
            background: #ffebee;
        }

        .totales {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .totales label {
            font-weight: 600;
            color: #344767;
        }

        .totales input {
            width: 200px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1.1rem;
            font-weight: 600;
            text-align: right;
        }

        .stock-warning {
            color: #f44336;
            font-size: 0.85rem;
            margin-top: 0.25rem;
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
            <div class="main-content">
                <h2><i class="fas fa-edit"></i> Modificar Venta #<?= htmlspecialchars($venta['id']) ?></h2>
                
                <form id="edit-form">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($venta['id']) ?>">
                    
                    <div class="venta-header">
                        <div class="info-group">
                            <label><i class="fas fa-user"></i> Cliente</label>
                            <input type="text" value="<?= htmlspecialchars($venta['cliente_nombre'] ?? 'Cliente General') ?>" readonly>
                        </div>
                        
                        <div class="info-group">
                            <label><i class="fas fa-calendar"></i> Fecha</label>
                            <input type="text" value="<?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?>" readonly>
                        </div>
                        
                        <div class="info-group">
                            <label><i class="fas fa-receipt"></i> N° Factura</label>
                            <input type="text" value="<?= htmlspecialchars($venta['numero_factura']) ?>" readonly>
                        </div>
                    </div>

                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td>
                                    <select name="productos[]" class="producto-select" required>
                                        <?php foreach ($inventario as $item): ?>
                                            <option value="<?= $item['id'] ?>" 
                                                    data-precio="<?= $item['precio_venta'] ?>"
                                                    data-stock="<?= $item['stock'] ?>"
                                                    <?= $item['id'] == $producto['producto_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($item['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="cantidades[]" 
                                           value="<?= $producto['cantidad'] ?>" 
                                           min="1" max="<?= $producto['stock'] + $producto['cantidad'] ?>" 
                                           class="cantidad-input" required>
                                </td>
                                <td>
                                    <input type="number" name="precios[]" 
                                           value="<?= $producto['precio_unitario'] ?>" 
                                           step="0.01" class="precio-input" required>
                                </td>
                                <td class="subtotal">
                                    <?= number_format($producto['cantidad'] * $producto['precio_unitario'], 2) ?>
                                </td>
                                <td>
                                    <button type="button" class="eliminar-producto">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <button type="button" class="btn btn-primary" id="agregar-producto">
                        <i class="fas fa-plus"></i> Agregar Producto
                    </button>

                    <div class="totales">
                        <label>Total:</label>
                        <input type="number" name="total" id="total" 
                               value="<?= $venta['total'] ?>" step="0.01" readonly>
                    </div>

                    <div class="action-buttons">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        function actualizarTotal() {
            let total = 0;
            $('.subtotal').each(function() {
                total += parseFloat($(this).text()) || 0;
            });
            $('#total').val(total.toFixed(2));
        }

        function actualizarSubtotal(row) {
            const cantidad = parseFloat(row.find('.cantidad-input').val()) || 0;
            const precio = parseFloat(row.find('.precio-input').val()) || 0;
            const subtotal = cantidad * precio;
            row.find('.subtotal').text(subtotal.toFixed(2));
            actualizarTotal();
        }

        function validarStock(row) {
            const select = row.find('.producto-select');
            const cantidadInput = row.find('.cantidad-input');
            const stockDisponible = parseInt(select.find(':selected').data('stock'));
            const cantidad = parseInt(cantidadInput.val());
            
            if (cantidad > stockDisponible) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock insuficiente',
                    text: `Solo hay ${stockDisponible} unidades disponibles`
                });
                cantidadInput.val(stockDisponible);
                actualizarSubtotal(row);
            }
        }

        $('.productos-table').on('input', '.cantidad-input, .precio-input', function() {
            const row = $(this).closest('tr');
            actualizarSubtotal(row);
            if ($(this).hasClass('cantidad-input')) {
                validarStock(row);
            }
        });

        $('.productos-table').on('change', '.producto-select', function() {
            const row = $(this).closest('tr');
            const precio = $(this).find(':selected').data('precio');
            row.find('.precio-input').val(precio);
            actualizarSubtotal(row);
        });

        $('.eliminar-producto').on('click', function() {
            const row = $(this).closest('tr');
            Swal.fire({
                title: '¿Eliminar producto?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    row.remove();
                    actualizarTotal();
                }
            });
        });

        $('#agregar-producto').on('click', function() {
            const newRow = `
                <tr>
                    <td>
                        <select name="productos[]" class="producto-select" required>
                            <option value="">Seleccione un producto</option>
                            <?php foreach ($inventario as $item): ?>
                                <option value="<?= $item['id'] ?>" 
                                        data-precio="<?= $item['precio_venta'] ?>"
                                        data-stock="<?= $item['stock'] ?>">
                                    <?= htmlspecialchars($item['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="cantidades[]" value="1" min="1" 
                               class="cantidad-input" required>
                    </td>
                    <td>
                        <input type="number" name="precios[]" value="0" step="0.01" 
                               class="precio-input" required>
                    </td>
                    <td class="subtotal">0.00</td>
                    <td>
                        <button type="button" class="eliminar-producto">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('.productos-table tbody').append(newRow);
        });

        $('#edit-form').on('submit', function(e) {
            e.preventDefault();
            
            if ($('.productos-table tbody tr').length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Debe agregar al menos un producto'
                });
                return;
            }

            Swal.fire({
                title: '¿Guardar cambios?',
                text: "¿Está seguro de guardar los cambios realizados?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4caf50',
                cancelButtonColor: '#f44336',
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'guardar_edicion.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Éxito!',
                                    text: response.message,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    window.location.href = 'index.php';
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
                                text: 'Error al guardar los cambios'
                            });
                        }
                    });
                }
            });
        });
    });
    </script>
</body>
</html>
<?php
    } else {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Venta no encontrada',
                confirmButtonText: 'Volver'
            }).then(() => {
                window.location.href = 'index.php';
            });
        </script>";
    }
} else {
    header("Location: index.php");
    exit();
}
?>