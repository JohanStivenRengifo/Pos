$(document).ready(function () {
    // Cachear elementos DOM frecuentemente utilizados
    const $listaVenta = $('#venta-lista tbody');
    const $totalVentaElement = $('#venta-total');
    const $buscarProductoInput = $('#buscar-producto');
    const $clienteSelect = $('#cliente-select');
    const $descuentoInput = $('#descuento');
    const $metodoPagoSelect = $('#metodo-pago');
    const $ventaBoton = $('#venta-boton');
    const $productsGrid = $('#products-grid');
    const $tipoDocumentoSelect = $('#tipo-documento');
    const $tituloDocumento = $('#titulo-documento');
    const $cantidadTotalElement = $('#cantidad-total');
    const $camposCredito = $('#campos-credito');
    const $ordenarProductos = $('#ordenar-productos');

    let carrito = [];
    let productosCache = {};

    // Función para mostrar alertas mejorada
    function mostrarAlerta(icon, title, text) {
        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }

    // Función para formatear precios en COP
    const formatearPrecioCOP = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format;

    // Función para actualizar el carrito optimizada
    function actualizarCarrito() {
        $listaVenta.empty();
        let subtotal = 0;
        let cantidadTotal = 0;

        $('#venta-lista-empty').toggle(carrito.length === 0);

        const fragment = document.createDocumentFragment();
        carrito.forEach(item => {
            const subtotalItem = item.precio * item.cantidad;
            subtotal += subtotalItem;
            cantidadTotal += item.cantidad;

            const tr = document.createElement('tr');
            tr.className = `producto-${item.id}`;
            tr.innerHTML = `
                <td>${item.nombre}</td>
                <td>
                    <input type="number" class="form-control form-control-sm cantidad-producto" 
                           data-id="${item.id}" value="${item.cantidad}" min="1" max="${item.stock}" style="width: 60px;">
                </td>
                <td>${formatearPrecioCOP(item.precio)}</td>
                <td>${formatearPrecioCOP(subtotalItem)}</td>
                <td>
                    <button class="btn btn-danger btn-sm eliminar-producto" data-id="${item.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            fragment.appendChild(tr);
        });
        $listaVenta.append(fragment);

        const descuentoPorcentaje = parseFloat($descuentoInput.val()) || 0;
        const descuento = subtotal * (descuentoPorcentaje / 100);
        const total = subtotal - descuento;

        $('#subtotal').text(formatearPrecioCOP(subtotal));
        $('#descuento-monto').text(formatearPrecioCOP(descuento));
        $totalVentaElement.text(formatearPrecioCOP(total));
        $cantidadTotalElement.text(cantidadTotal);

        $ventaBoton.prop('disabled', carrito.length === 0);
    }

    // Función para agregar producto al carrito mejorada
    function agregarProducto(id, nombre, precio, stock, cantidad = 1) {
        if (stock <= 0) {
            mostrarAlerta('error', 'Sin stock', `No hay stock disponible para ${nombre}`);
            return;
        }

        const productoExistente = carrito.find(item => item.id === id);

        if (productoExistente) {
            if (productoExistente.cantidad + cantidad > stock) {
                mostrarAlerta('warning', 'Stock insuficiente', `No hay suficiente stock para ${nombre}`);
                return;
            }
            productoExistente.cantidad += cantidad;
        } else {
            carrito.push({ id, nombre, precio, cantidad, stock });
        }

        actualizarCarrito();
        mostrarAlerta('success', 'Producto agregado', `${nombre} se ha agregado al carrito`);
    }

    // Función de debounce optimizada
    const debounce = (func, wait) => {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    };

    // Función para filtrar productos
    function filtrarProductos(valorBuscado) {
        valorBuscado = valorBuscado.toLowerCase().trim();
        $('.product-card').each(function () {
            const $this = $(this);
            const nombreProducto = $this.data('nombre').toLowerCase();
            const codigoBarras = $this.data('codigo').toString().toLowerCase();
            const coincide = nombreProducto.includes(valorBuscado) || codigoBarras.includes(valorBuscado);
            $this.toggle(coincide);
        });
    }

    // Función para buscar producto por código de barras exacto
    function buscarPorCodigoBarrasExacto(codigo) {
        const productoEncontrado = $('.product-card').filter(function() {
            return $(this).data('codigo').toString().toLowerCase() === codigo.toLowerCase();
        }).first();

        if (productoEncontrado.length) {
            const id = productoEncontrado.data('id');
            const nombre = productoEncontrado.data('nombre');
            const precio = parseFloat(productoEncontrado.data('precio'));
            const stock = parseInt(productoEncontrado.data('cantidad'));
            agregarProducto(id, nombre, precio, stock);
            $buscarProductoInput.val('');
            return true;
        }
        return false;
    }

    // Evento para buscar y filtrar productos
    $buscarProductoInput.on('input', function() {
        const valorBuscado = $(this).val().trim();
        filtrarProductos(valorBuscado);

        // Si el valor tiene 8 o más caracteres, intentamos buscar por código de barras exacto
        if (valorBuscado.length >= 8) {
            buscarPorCodigoBarrasExacto(valorBuscado);
        }
    });

    // Evento para manejar la tecla Enter en la búsqueda
    $buscarProductoInput.on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            const valorBuscado = $(this).val().trim();
            
            if (!buscarPorCodigoBarrasExacto(valorBuscado)) {
                const productoEncontrado = $('.product-card:visible').first();
                
                if (productoEncontrado.length) {
                    const id = productoEncontrado.data('id');
                    const nombre = productoEncontrado.data('nombre');
                    const precio = parseFloat(productoEncontrado.data('precio'));
                    const stock = parseInt(productoEncontrado.data('cantidad'));
                    agregarProducto(id, nombre, precio, stock);
                    $(this).val('');
                } else {
                    mostrarAlerta('error', 'Producto no encontrado', 'No se encontró ningún producto con ese nombre o código de barras.');
                }
            }
            filtrarProductos('');
        }
    });

    // Función para ordenar productos
    function ordenarProductos(criterio) {
        const $productos = $('.product-card').get();
        $productos.sort((a, b) => {
            const $a = $(a);
            const $b = $(b);
            switch (criterio) {
                case 'nombre':
                    return $a.data('nombre').localeCompare($b.data('nombre'));
                case 'precio_asc':
                    return $a.data('precio') - $b.data('precio');
                case 'precio_desc':
                    return $b.data('precio') - $a.data('precio');
                case 'stock':
                    return $b.data('cantidad') - $a.data('cantidad');
                default:
                    return 0;
            }
        });
        $productsGrid.append($productos);
    }

    // Eventos
    $tipoDocumentoSelect.on('change', function() {
        const esFactura = $(this).val() === 'factura';
        $tituloDocumento.text(esFactura ? 'Factura de venta' : 'Cotización');
        $ventaBoton.text(esFactura ? 'Confirmar Venta' : 'Generar Cotización');
        $metodoPagoSelect.prop('disabled', !esFactura);
    });

    $metodoPagoSelect.on('change', function() {
        $camposCredito.toggle($(this).val() === 'credito');
    });

    $ordenarProductos.on('change', function() {
        ordenarProductos($(this).val());
    });

    $productsGrid.on('click', '.product-card', function () {
        const { id, nombre, precio, cantidad: stock } = $(this).data();
        agregarProducto(id, nombre, parseFloat(precio), parseInt(stock));
    });

    $listaVenta
        .on('click', '.eliminar-producto', function () {
            const id = $(this).data('id');
            carrito = carrito.filter(item => item.id !== id);
            actualizarCarrito();
        })
        .on('change', '.cantidad-producto', function () {
            const id = $(this).data('id');
            const nuevaCantidad = parseInt($(this).val());
            const producto = carrito.find(item => item.id === id);
            if (producto) {
                if (nuevaCantidad > producto.stock) {
                    mostrarAlerta('error', 'Error', `No hay suficiente stock para ${producto.nombre}`);
                    $(this).val(producto.cantidad);
                    return;
                }
                producto.cantidad = nuevaCantidad;
                actualizarCarrito();
            }
        });

    $descuentoInput.on('input', actualizarCarrito);

    $ventaBoton.on('click', function () {
        if (carrito.length === 0) {
            mostrarAlerta('error', 'Carrito vacío', 'No hay productos en el carrito.');
            return;
        }

        const clienteId = $clienteSelect.val();
        if (!clienteId) {
            mostrarAlerta('warning', 'Cliente no seleccionado', 'Por favor, seleccione un cliente.');
            return;
        }

        const tipoDocumento = $tipoDocumentoSelect.val();
        const descuento = parseFloat($descuentoInput.val()) || 0;
        const metodoPago = $metodoPagoSelect.val();
        const subtotal = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        const total = subtotal * (1 - descuento / 100);

        const datos = {
            tipo_documento: tipoDocumento,
            cliente_id: clienteId,
            productos: carrito.map(({ id, cantidad, precio }) => ({ id, cantidad, precio })),
            total,
            descuento,
            metodo_pago: metodoPago
        };

        if (metodoPago === 'credito') {
            datos.credito = {
                plazo: $('#plazo-credito').val(),
                interes: $('#interes-credito').val()
            };
        }

        $ventaBoton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

        $.ajax({
            url: 'procesar_venta.php',
            method: 'POST',
            data: { venta: JSON.stringify(datos) },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Venta Completada',
                        html: `
                            <p>${tipoDocumento === 'factura' ? 'Venta' : 'Cotización'} procesada correctamente.</p>
                            <p>Número de documento: ${response.numero_factura}</p>
                        `,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonText: 'Imprimir Ticket',
                        cancelButtonText: 'Cerrar',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            imprimirDocumento(response.venta_id);
                        }
                        
                        // Recargar la página después de cerrar la alerta
                        location.reload();
                    });
                    
                    if (tipoDocumento === 'factura') {
                        response.productos_actualizados.forEach(producto => {
                            const productoCard = $(`.product-card[data-id="${producto.id}"]`);
                            productoCard.data('cantidad', producto.nuevo_stock)
                                .find('small:contains("Stock:")').text(`Stock: ${producto.nuevo_stock}`);
                        });
                    }
                    
                    // Reiniciar el formulario
                    carrito = [];
                    actualizarCarrito();
                    $descuentoInput.val(0);
                    $clienteSelect.val('');
                    $metodoPagoSelect.val('efectivo').trigger('change');
                    $buscarProductoInput.val('').focus();
                } else {
                    mostrarAlerta('error', 'Error', `Error al procesar el documento: ${response.message}`);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error en la solicitud AJAX:', status, error);
                mostrarAlerta('error', 'Error', 'Error en la comunicación con el servidor.');
            },
            complete: function() {
                $ventaBoton.prop('disabled', false).html(tipoDocumento === 'factura' ? 'Confirmar Venta' : 'Generar Cotización');
            }
        });
    });

    // Evento para cancelar la venta
    $('#cancelar-venta').on('click', function() {
        Swal.fire({
            title: '¿Cancelar venta?',
            text: "¿Estás seguro de que deseas cancelar esta venta? Se perderán todos los datos ingresados.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, cancelar venta',
            cancelButtonText: 'No, continuar con la venta'
        }).then((result) => {
            if (result.isConfirmed) {
                carrito = [];
                actualizarCarrito();
                $clienteSelect.val('');
                $descuentoInput.val(0);
                $metodoPagoSelect.val('efectivo');
                Swal.fire(
                    'Venta cancelada',
                    'La venta ha sido cancelada y los datos han sido borrados.',
                    'info'
                );
            }
        });
    });

    // Inicialización
    $buscarProductoInput.focus();
    actualizarCarrito();

    // Imprimir ticket anterior
    $('#imprimir-ticket-anterior').on('click', function() {
        $.ajax({
            url: 'obtener_ventas.php',
            method: 'GET',
            dataType: 'json',
            success: function(ventas) {
                if (ventas.length > 0) {
                    mostrarModalVentas(ventas);
                } else {
                    mostrarAlerta('info', 'Sin ventas', 'No se encontraron ventas anteriores.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al obtener las ventas:', error);
                mostrarAlerta('error', 'Error', 'No se pudieron cargar las ventas anteriores.');
            }
        });
    });

    function mostrarModalVentas(ventas) {
        let ventasHtml = '<div class="list-group" style="max-height: 300px; overflow-y: auto;">';
        ventas.forEach(venta => {
            ventasHtml += `
                <button type="button" class="list-group-item list-group-item-action imprimir-venta" data-venta-id="${venta.id}">
                    Factura: ${venta.numero_factura} - Fecha: ${venta.fecha}
                </button>`;
        });
        ventasHtml += '</div>';

        Swal.fire({
            title: 'Seleccionar Venta para Imprimir',
            html: ventasHtml,
            showCloseButton: true,
            showConfirmButton: false,
            width: '600px',
            didOpen: () => {
                $('.imprimir-venta').on('click', function() {
                    imprimirDocumento($(this).data('venta-id'));
                    Swal.close();
                });
            }
        });
    }

    function imprimirDocumento(ventaId) {
        const url = `imprimir_ticket.php?id=${ventaId}`;
        const windowName = 'ImprimirTicket';
        const windowSize = 'width=800,height=600';
        
        const printWindow = window.open(url, windowName, windowSize);
        
        if (!printWindow) {
            mostrarAlerta('error', 'Error', 'El navegador bloqueó la ventana emergente. Por favor, permita las ventanas emergentes para este sitio.');
        } else {
            printWindow.onload = function() {
                printWindow.print();
            };
        }
    }
});
