<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: ../../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $venta_id = (int)$_GET['id'];

    // Obtener los detalles de la venta
    $stmt = $pdo->prepare("SELECT v.*, c.nombre AS cliente_nombre FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.id WHERE v.id = ? AND v.user_id = ?");
    $stmt->execute([$venta_id, $user_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($venta) {
        // Obtener los productos de la venta
        $stmt = $pdo->prepare("SELECT vd.*, i.nombre AS producto_nombre FROM venta_detalles vd JOIN inventario i ON vd.producto_id = i.id WHERE vd.venta_id = ?");
        $stmt->execute([$venta_id]);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener todos los productos del inventario
        $stmt = $pdo->prepare("SELECT id, nombre, precio_venta FROM inventario WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Modificar Venta</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
            <style>
                body {
                    font-family: 'Arial', sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f4f4f4;
                }
                h1 {
                    color: #2c3e50;
                    text-align: center;
                    margin-bottom: 30px;
                }
                form {
                    background-color: #fff;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                label {
                    display: block;
                    margin-bottom: 5px;
                    color: #2c3e50;
                }
                input[type="text"], input[type="number"], select {
                    width: 100%;
                    padding: 8px;
                    margin-bottom: 20px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th, td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                th {
                    background-color: #f2f2f2;
                    color: #333;
                }
                button {
                    background-color: #3498db;
                    color: white;
                    padding: 10px 15px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                }
                button:hover {
                    background-color: #2980b9;
                }
                .eliminar-producto {
                    background-color: #e74c3c;
                }
                .eliminar-producto:hover {
                    background-color: #c0392b;
                }
                #agregar-producto {
                    margin-bottom: 20px;
                }
                .subtotal {
                    font-weight: bold;
                }
                #total {
                    font-size: 18px;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <h1>Modificar Venta</h1>
            <form id="edit-form">
                <input type="hidden" name="id" value="<?= htmlspecialchars($venta['id']); ?>">
                
                <label for="cliente"><i class="fas fa-user"></i> Cliente:</label>
                <input type="text" id="cliente" value="<?= htmlspecialchars($venta['cliente_nombre']); ?>" readonly>
                
                <label for="fecha"><i class="fas fa-calendar-alt"></i> Fecha:</label>
                <input type="text" id="fecha" value="<?= htmlspecialchars($venta['fecha']); ?>" readonly>
                
                <h2><i class="fas fa-shopping-cart"></i> Productos</h2>
                <table id="productos-tabla">
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
                                <select name="productos[]" required>
                                    <?php foreach ($inventario as $item): ?>
                                        <option value="<?= $item['id']; ?>" <?= $item['id'] == $producto['producto_id'] ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($item['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="cantidades[]" value="<?= $producto['cantidad']; ?>" min="1" required></td>
                            <td><input type="number" name="precios[]" value="<?= $producto['precio_unitario']; ?>" step="0.01" required></td>
                            <td class="subtotal"><?= $producto['cantidad'] * $producto['precio_unitario']; ?></td>
                            <td><button type="button" class="eliminar-producto"><i class="fas fa-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" id="agregar-producto"><i class="fas fa-plus"></i> Agregar Producto</button>
                
                <label for="total"><i class="fas fa-dollar-sign"></i> Total:</label>
                <input type="number" name="total" id="total" value="<?= htmlspecialchars($venta['total']); ?>" step="0.01" readonly required>
                
                <button type="submit"><i class="fas fa-save"></i> Guardar Cambios</button>
            </form>

            <script>
            $(document).ready(function() {
                function actualizarTotal() {
                    var total = 0;
                    $('.subtotal').each(function() {
                        total += parseFloat($(this).text());
                    });
                    $('#total').val(total.toFixed(2));
                }

                function actualizarSubtotal(row) {
                    var cantidad = parseFloat(row.find('input[name="cantidades[]"]').val());
                    var precio = parseFloat(row.find('input[name="precios[]"]').val());
                    var subtotal = cantidad * precio;
                    row.find('.subtotal').text(subtotal.toFixed(2));
                    actualizarTotal();
                }

                $('#productos-tabla').on('input', 'input', function() {
                    actualizarSubtotal($(this).closest('tr'));
                });

                $('.eliminar-producto').on('click', function() {
                    $(this).closest('tr').remove();
                    actualizarTotal();
                });

                $('#agregar-producto').on('click', function() {
                    var newRow = `
                        <tr>
                            <td>
                                <select name="productos[]" required>
                                    <?php foreach ($inventario as $item): ?>
                                        <option value="<?= $item['id']; ?>"><?= htmlspecialchars($item['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="cantidades[]" value="1" min="1" required></td>
                            <td><input type="number" name="precios[]" value="0" step="0.01" required></td>
                            <td class="subtotal">0</td>
                            <td><button type="button" class="eliminar-producto"><i class="fas fa-trash"></i></button></td>
                        </tr>
                    `;
                    $('#productos-tabla tbody').append(newRow);
                    actualizarSubtotal($('#productos-tabla tbody tr:last'));
                });

                $('#edit-form').on('submit', function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: 'guardar_edicion.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: '¡Éxito!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href = 'index.php';
                                    }
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                title: 'Error',
                                text: 'Error al guardar la edición.',
                                icon: 'error',
                                confirmButtonText: 'OK'
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
        echo "<p>No se encontró la venta.</p>";
    }
} else {
    echo "<p>ID de venta no especificado.</p>";
}
?>