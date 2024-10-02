<?php
session_start();
require_once '../../config/db.php'; // Asegúrate de que este archivo define y establece la variable $pdo

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$clientes = obtenerClientes($pdo, $user_id);
$productos = obtenerProductos($pdo, $user_id);

function obtenerClientes($pdo, $userId)
{
    $clientes_stmt = $pdo->prepare("SELECT id, nombre FROM clientes WHERE user_id = ?");
    $clientes_stmt->execute([$userId]);
    return $clientes_stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerProductos($pdo, $userId)
{
    $productos_stmt = $pdo->prepare("SELECT id, nombre, precio_venta AS precio, stock AS cantidad, codigo_barras FROM inventario WHERE user_id = ?");
    $productos_stmt->execute([$userId]);
    return $productos_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Realizar Venta</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

    <style>
        /* Estilos generales */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f2f4f8;
            color: #333;
        }

        h1,
        h2,
        h3 {
            color: #4A4A4A;
        }

        .container {
            margin-top: 20px;
        }

        .action-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }

        .action-buttons button {
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .action-buttons button:hover {
            transform: translateY(-2px);
        }

        .btn-create-client {
            background-color: #007bff;
            border: none;
            color: white;
        }

        .btn-create-product {
            background-color: #28a745;
            border: none;
            color: white;
        }

        .btn-print-ticket {
            background-color: #ff9800;
            border: none;
            color: white;
        }

        .venta-producto {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.3s ease;
        }

        .venta-producto:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .venta-producto-actions button {
            margin-left: 10px;
            background-color: #e9ecef;
            border: none;
            border-radius: 5px;
            padding: 8px;
            transition: background-color 0.2s ease;
        }

        .venta-producto-actions button:hover {
            background-color: #dee2e6;
        }

        .venta-producto p {
            margin: 0;
            font-size: 14px;
        }

        /* Responsividad */
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }

            .action-buttons button {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <h3 class="text-center">Realizar Venta</h3>
        <div class="alert alert-warning" id="alerta" style="display: none;" role="alert"></div>

        <!-- Selección de cliente -->
        <div class="form-group">
            <label for="cliente-select">Selecciona un cliente</label>
            <select id="cliente-select" class="form-control">
                <option value="">Selecciona un cliente</option>
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?= $cliente['id']; ?>" <?= ($cliente['nombre'] === 'Consumidor Final') ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($cliente['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Campo de búsqueda de productos -->
        <div class="form-group">
            <input type="text" id="buscar-producto" class="form-control" placeholder="Buscar producto por nombre o código de barras...">
        </div>

        <!-- Grid de productos -->
        <div class="row" id="products-grid">
            <?php foreach ($productos as $producto): ?>
                <div class="col-md-3 col-sm-6 col-xs-12 mb-4 product-card"
                    data-id="<?= $producto['id']; ?>"
                    data-nombre="<?= htmlspecialchars($producto['nombre']); ?>"
                    data-precio="<?= $producto['precio']; ?>"
                    data-cantidad="<?= $producto['cantidad']; ?>"
                    data-codigo="<?= htmlspecialchars($producto['codigo_barras']); ?>">
                    <div class="card h-100 shadow-sm position-relative" style="border-radius: 12px; border: 1px solid #e0e0e0;">
                        <p class="position-absolute text-secondary bg-light rounded p-1" style="top: 10px; left: 10px;"><?= htmlspecialchars($producto['codigo_barras']); ?></p>
                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <div class="text-center">
                                <div class="item-image-zone d-flex align-items-center justify-content-center mb-3">
                                    <!-- SVG ícono de producto -->
                                </div>
                                <p class="item-view__name text-center text-truncate font-weight-bold mb-1"><?= htmlspecialchars($producto['nombre']); ?></p>
                                <p class="item-view__quantity text-center text-muted">Inventario: <?= $producto['cantidad']; ?></p>
                            </div>
                            <p class="item-view__price text-center text-primary font-weight-bold">$<?= number_format($producto['precio'], 2); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Detalles de la venta -->
        <div class="venta-sidebar">
            <h4>Factura de venta</h4>
            <div id="venta-lista" class="mb-3"></div>
            <div class="venta-total" id="venta-total"><strong>Total: $0.00</strong></div>
            <button class="venta-boton" id="venta-boton" aria-label="Realizar venta">Confirmar Venta</button>
        </div>

        <!-- Modal de confirmación de venta -->
        <div id="confirmacionVentaModal" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirmar Venta</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>¿Estás seguro de que deseas procesar esta venta?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" id="confirmarVenta" class="btn btn-primary">Confirmar Venta</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para imprimir ticket -->
        <div id="imprimirTicketModal" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Imprimir Ticket</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>La venta se ha procesado correctamente. ¿Quieres imprimir el ticket?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">No Imprimir</button>
                        <button type="button" id="imprimirTicket" class="btn btn-primary">Imprimir Ticket</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <script src="./script.js"></script>
    <script>
$(document).ready(function() {
    // Al hacer clic en un producto, se añade a la lista de venta
    $('.product-card').click(function() {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const precio = $(this).data('precio');
        const cantidad = $(this).data('cantidad');
        agregarProducto(id, nombre, precio, cantidad);
    });

    // Función para agregar producto a la venta
    function agregarProducto(id, nombre, precio, cantidad) {
        const listaVenta = $('#venta-lista');
        const totalVenta = parseFloat($('#venta-total strong').text().replace('$', '').replace(',', ''));

        // Verifica si el producto ya está en la lista
        if (listaVenta.find(`.producto-${id}`).length > 0) {
            alert('Este producto ya está en la lista.');
            return;
        }

        const nuevoProducto = $(`
            <div class="venta-producto producto-${id}">
                <p>${nombre} - $${precio.toFixed(2)}</p>
                <button class="btn btn-danger btn-sm eliminar-producto" data-id="${id}">Eliminar</button>
            </div>
        `);

        listaVenta.append(nuevoProducto);
        // Actualiza el total
        $('#venta-total strong').text(`Total: $${(totalVenta + precio).toFixed(2)}`);
        agregarEliminarProducto(nuevoProducto);
    }

    // Función para manejar la eliminación de productos
    function agregarEliminarProducto(element) {
        element.find('.eliminar-producto').click(function() {
            const precio = parseFloat(element.text().split('- $')[1]);
            const totalVenta = parseFloat($('#venta-total strong').text().replace('$', '').replace(',', ''));
            $('#venta-total strong').text(`Total: $${(totalVenta - precio).toFixed(2)}`);
            element.remove();
        });
    }

    // Confirmar venta al hacer clic en el botón
    $('#venta-boton').click(function() {
        $('#confirmacionVentaModal').modal('show');
    });

    // Al confirmar la venta, procesar la venta
    $('#confirmarVenta').click(function() {
        $('#confirmacionVentaModal').modal('hide');
        $('#imprimirTicketModal').modal('show');
    });

    // Generar el HTML del ticket
    function generarTicketHTML() {
        const listaVenta = $('#venta-lista').html();
        const total = $('#venta-total strong').text();
        return `
            <html>
                <head>
                    <title>Ticket de Venta</title>
                    <style>
                        /* Aquí puedes añadir estilos para el ticket */
                        body { font-family: Arial, sans-serif; }
                        .ticket { width: 300px; margin: 0 auto; }
                        .producto { margin-bottom: 10px; }
                        .total { font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class="ticket">
                        <h2>Ticket de Venta</h2>
                        <div>${listaVenta}</div>
                        <p class="total">${total}</p>
                    </div>
                </body>
            </html>
        `;
    }

    // Imprimir el ticket
    function imprimirTicket() {
        const ticketHTML = generarTicketHTML();
        const ventanaImpresion = window.open('', '', 'width=600,height=400');
        ventanaImpresion.document.write(ticketHTML);
        ventanaImpresion.document.close();
        ventanaImpresion.print();
        ventanaImpresion.close();
    }

    // Al hacer clic en "Imprimir Ticket"
    $('#imprimirTicket').click(function() {
        imprimirTicket();
        $('#imprimirTicketModal').modal('hide');
        vaciarCarrito();
    });

    // Vaciar el carrito después de imprimir
    function vaciarCarrito() {
        $('#venta-lista').empty();
        $('#venta-total strong').text('Total: $0.00');
    }

    // Búsqueda de productos
    $('#buscar-producto').on('input', function() {
        const valorBuscado = $(this).val().toLowerCase();
        $('.product-card').each(function() {
            const nombreProducto = $(this).data('nombre').toLowerCase();
            const codigoBarras = $(this).data('codigo').toLowerCase();
            if (nombreProducto.includes(valorBuscado) || codigoBarras.includes(valorBuscado)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Capturar Ctrl + P para imprimir el ticket
    $(document).keydown(function(e) {
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault(); // Evita la acción por defecto de imprimir
            imprimirTicket(); // Llama a la función para imprimir el ticket
        }
    });
});
</script>


</body>

</html>
