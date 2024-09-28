let ventaProductos = [];
let totalVenta = 0;

// Función para agregar productos al carrito
$(".product-card").click(function () {
    const clienteId = $("#cliente-select").val();
    if (!clienteId) {
        mostrarAlerta("Por favor, selecciona un cliente antes de agregar productos.", "warning");
        return;
    }

    const productoId = $(this).data("id");
    const nombre = $(this).data("nombre");
    const precio = parseFloat($(this).data("precio"));
    const cantidadDisponible = parseInt($(this).data("cantidad"));

    if (cantidadDisponible <= 0) {
        mostrarAlerta('Este producto está agotado.', "danger");
        return;
    }

    // Verificar si el producto ya está en la lista
    const productoExistente = ventaProductos.find(p => p.id === productoId);
    if (productoExistente) {
        if (productoExistente.cantidad + 1 > cantidadDisponible) {
            mostrarAlerta('No hay suficiente stock para este producto.', "danger");
            return;
        }
        productoExistente.cantidad += 1;
    } else {
        ventaProductos.push({
            id: productoId,
            nombre: nombre,
            cantidad: 1,
            precio: precio
        });
    }

    actualizarListaVenta();
});

// Función para actualizar la lista de productos en el carrito
function actualizarListaVenta() {
    totalVenta = 0;
    $("#venta-lista").empty();
    
    ventaProductos.forEach((producto, index) => {
        const subtotal = producto.precio * producto.cantidad;
        totalVenta += subtotal;
        
        $("#venta-lista").append(`
            <div class="venta-producto">
                <div>${producto.nombre} (x${producto.cantidad})</div>
                <div>$${subtotal.toFixed(2)}</div>
                <div class="venta-producto-actions">
                    <button class="btn btn-sm btn-danger" onclick="eliminarProducto(${index})">Eliminar</button>
                    <button class="btn btn-sm btn-primary" onclick="modificarPrecio(${index})">Modificar Precio</button>
                    <button class="btn btn-sm btn-secondary" onclick="modificarCantidad(${index}, 1)">+</button>
                    <button class="btn btn-sm btn-secondary" onclick="modificarCantidad(${index}, -1)">-</button>
                </div>
            </div>
        `);
    });
    
    $("#venta-total").html(`<strong>Total: $${totalVenta.toFixed(2)}</strong>`);
}

// Funciones de manejo de carrito
function eliminarProducto(index) {
    ventaProductos.splice(index, 1);
    actualizarListaVenta();
}

function modificarPrecio(index) {
    const nuevoPrecio = parseFloat(prompt("Ingrese el nuevo precio:", ventaProductos[index].precio));
    if (!isNaN(nuevoPrecio) && nuevoPrecio > 0) {
        ventaProductos[index].precio = nuevoPrecio;
        actualizarListaVenta();
    } else {
        mostrarAlerta("Precio no válido. Intente nuevamente.", "danger");
    }
}

function modificarCantidad(index, cantidad) {
    const nuevaCantidad = ventaProductos[index].cantidad + cantidad;
    if (nuevaCantidad <= 0) {
        eliminarProducto(index);
    } else if (nuevaCantidad > 0) {
        ventaProductos[index].cantidad = nuevaCantidad;
        actualizarListaVenta();
    } else {
        mostrarAlerta("No se puede reducir la cantidad a menos de 1.", "danger");
    }
}

// Mostrar alerta
function mostrarAlerta(mensaje, tipo) {
    $("#alerta").removeClass("alert-warning alert-danger").addClass(`alert-${tipo}`).text(mensaje).fadeIn();
    setTimeout(() => {
        $("#alerta").fadeOut();
    }, 5000);
}

// Filtrar productos al buscar
document.getElementById('buscar-producto').addEventListener('input', debounce(function () {
    let query = this.value.toLowerCase();
    let productos = document.querySelectorAll('.product-card');

    productos.forEach(function (producto) {
        let nombre = producto.getAttribute('data-nombre').toLowerCase();
        let codigo = producto.getAttribute('data-codigo').toLowerCase();
        producto.style.display = (nombre.includes(query) || codigo.includes(query)) ? 'block' : 'none';
    });
}, 300));

// Función para procesar la venta
$("#venta-boton").click(function () {
    if (ventaProductos.length === 0) {
        mostrarAlerta("No hay productos en el carrito para vender.", "warning");
        return;
    }

    const clienteId = $("#cliente-select").val();

    $.ajax({
        url: './procesar_venta.php',
        type: 'POST',
        data: {
            cliente_id: clienteId,
            productos: JSON.stringify(ventaProductos) // Asegúrate de enviar los productos como JSON
        },
        dataType: 'json', // Esperar un JSON de respuesta
        success: function (result) {
            if (result.status === 'success') {
                mostrarAlerta("Venta procesada con éxito. ID de venta: " + result.venta_id, "success");
                ventaProductos = []; // Limpiar el carrito
                actualizarListaVenta();
            } else {
                mostrarAlerta(result.message || "Error desconocido", "danger");
            }
        },
        error: function (jqXHR) {
            // Manejo de errores más específico
            const errorMessage = jqXHR.responseJSON?.message || "Error al procesar la venta. Intenta de nuevo.";
            mostrarAlerta(errorMessage, "danger");
        }
    });
});

// Función de debounce
function debounce(func, delay) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}