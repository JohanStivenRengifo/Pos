<?php
session_start();
require_once '../../config/db.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Obtener clientes
$clientes = $pdo->prepare("SELECT id, nombre FROM clientes WHERE user_id = ?");
$clientes->execute([$user_id]);
$clientes = $clientes->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos del inventario
$productos = $pdo->prepare("SELECT id, nombre, precio, cantidad FROM inventario WHERE user_id = ?");
$productos->execute([$user_id]);
$productos = $productos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Realizar Venta</title>
    <link rel="stylesheet" href="../../css/modulos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            display: flex;
            height: 100vh;
            background-color: #f7f9fc;
        }
        .sidebar {
            width: 60px;
            background-color: #007bff;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
        }
        .sidebar h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            width: 100%;
        }
        .sidebar ul li {
            margin: 20px 0;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            text-align: center;
            font-size: 20px;
        }
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }
        .cliente-seleccion {
            margin-bottom: 20px;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .product-card {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            transition: background-color 0.3s, transform 0.3s;
        }
        .product-card:hover {
            background-color: #e9ecef;
            transform: scale(1.05);
        }
        .product-card h4 {
            margin: 10px 0;
            font-size: 16px;
        }
        .product-card .price {
            font-weight: bold;
            margin-top: 10px;
        }
        .venta-sidebar {
            width: 300px;
            border-left: 1px solid #ddd;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .venta-sidebar h3 {
            margin-bottom: 20px;
        }
        .venta-producto {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .venta-total {
            font-size: 20px;
            font-weight: bold;
            margin-top: 20px;
            text-align: right;
        }
        .venta-boton {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .venta-boton:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>Menú</h2>
    <ul>
        <li><a href="../../welcome.php"><i class="fas fa-home"></i></a></li>
        <li>
            <form method="POST" action="">
                <button type="submit" name="logout" class="logout-button" style="background: none; border: none; color: white; cursor: pointer;">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </li>
    </ul>
</div>

<div class="main">
    <!-- Seleccionar Cliente -->
    <div class="cliente-seleccion">
        <h3>Selecciona un cliente</h3>
        <select id="cliente-select">
            <option value="">Selecciona un cliente</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?= $cliente['id']; ?>"><?= $cliente['nombre']; ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Grid de productos -->
    <div class="products-grid" id="products-grid">
        <?php foreach ($productos as $producto): ?>
            <div class="product-card" data-id="<?= $producto['id']; ?>" data-nombre="<?= $producto['nombre']; ?>" data-precio="<?= $producto['precio']; ?>" data-cantidad="<?= $producto['cantidad']; ?>">
                <h4><?= $producto['nombre']; ?></h4>
                <div class="price">$<?= number_format($producto['precio'], 2); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Detalles de venta -->
<div class="venta-sidebar">
    <h3>Factura de venta</h3>
    <div id="venta-lista"></div>
    <div class="venta-total" id="venta-total">$0.00</div>
    <button class="venta-boton" id="venta-boton">Vender</button>
</div>

<script>
    let ventaProductos = [];
    let totalVenta = 0;

    $("#cliente-select").change(function() {
        let clienteId = $(this).val();
        if (clienteId === "") {
            alert("Por favor, selecciona un cliente antes de agregar productos.");
        }
    });

    $(".product-card").click(function() {
        let clienteId = $("#cliente-select").val();

        if (clienteId === "") {
            alert("Por favor, selecciona un cliente antes de agregar productos.");
            return;
        }

        let productoId = $(this).data("id");
        let nombre = $(this).data("nombre");
        let precio = parseFloat($(this).data("precio"));
        let cantidadDisponible = parseInt($(this).data("cantidad"));

        if (cantidadDisponible <= 0) {
            alert('Este producto está agotado.');
            return;
        }

        let producto = ventaProductos.find(p => p.id === productoId);

        if (producto) {
            if (producto.cantidad + 1 > cantidadDisponible) {
                alert('No hay suficiente stock para este producto.');
                return;
            }
            producto.cantidad += 1;
        } else {
            ventaProductos.push({id: productoId, nombre: nombre, cantidad: 1, precio: precio});
        }

        actualizarListaVenta();
    });

    function actualizarListaVenta() {
        totalVenta = 0;
        $("#venta-lista").empty();
        ventaProductos.forEach(producto => {
            totalVenta += producto.precio * producto.cantidad;
            $("#venta-lista").append(
                `<div class="venta-producto">
                    <div>${producto.nombre} (x${producto.cantidad})</div>
                    <div>$${(producto.precio * producto.cantidad).toFixed(2)}</div>
                </div>`
            );
        });
        $("#venta-total").text(`$${totalVenta.toFixed(2)}`);
    }

    $("#venta-boton").click(function() {
        let clienteId = $("#cliente-select").val();
        if (clienteId === "") {
            alert("Por favor, selecciona un cliente antes de realizar la venta.");
            return;
        }

        if (ventaProductos.length === 0) {
            alert("No hay productos en la venta.");
            return;
        }

        $.ajax({
            url: "procesar_venta.php",
            method: "POST",
            data: {
                productos: ventaProductos,
                cliente_id: clienteId
            },
            success: function(response) {
                alert(response);
                ventaProductos = [];
                actualizarListaVenta();
            },
            error: function() {
                alert("Hubo un error al procesar la venta.");
            }
        });
    });
</script>

</body>
</html>