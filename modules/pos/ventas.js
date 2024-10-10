$(document).ready(function () {
    let carrito = [];
    const listaVenta = $('#venta-lista');
    const totalVentaElement = $('#venta-total strong');
    const alertContainer = $('#alertContainer');
    const buscarProductoInput = $('#buscar-producto');
    const clienteSelect = $('#cliente-select');
    const descuentoInput = $('#descuento');
    const metodoPagoSelect = $('#metodo-pago');
    const ventaBoton = $('#venta-boton');
    const productsGrid = $('#products-grid');
    const tipoDocumentoSelect = $('#tipo-documento');
    const tituloDocumento = $('#titulo-documento');

    // Función para mostrar alertas de error
    function mostrarAlertaError(mensaje) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: mensaje,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true
        });
    }

    // Función para formatear precios en COP
    function formatearPrecioCOP(precio) {
        return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(precio);
    }

    // Función para actualizar el carrito
    function actualizarCarrito() {
        listaVenta.empty();
        let subtotal = 0;

        carrito.forEach(item => {
            const subtotalItem = item.precio * item.cantidad;
            subtotal += subtotalItem;

            listaVenta.append(`
                <tr class="producto-${item.id}">
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
                </tr>
            `);
        });

        const descuentoPorcentaje = parseFloat(descuentoInput.val()) || 0;
        const descuento = subtotal * (descuentoPorcentaje / 100);
        const total = subtotal - descuento;

        totalVentaElement.text(formatearPrecioCOP(total));
        $('#subtotal').text(`Subtotal: ${formatearPrecioCOP(subtotal)}`);
        $('#descuento-monto').text(`Descuento: ${formatearPrecioCOP(descuento)}`);

        // Habilitar o deshabilitar el botón de venta
        ventaBoton.prop('disabled', carrito.length === 0);
    }

    // Función para agregar producto al carrito
    function agregarProducto(id, nombre, precio, stock, cantidad = 1) {
        if (stock <= 0) {
            mostrarAlertaError(`No hay stock disponible para ${nombre}`);
            return;
        }

        const productoExistente = carrito.find(item => item.id === id);

        if (productoExistente) {
            if (productoExistente.cantidad + cantidad > stock) {
                mostrarAlertaError(`No hay suficiente stock para ${nombre}`);
                return;
            }
            productoExistente.cantidad += cantidad;
        } else {
            carrito.push({ id, nombre, precio, cantidad, stock });
        }

        actualizarCarrito();
    }

    // Función para filtrar productos
    function filtrarProductos() {
        const valorBuscado = buscarProductoInput.val().toLowerCase();
        $('.product-card').each(function () {
            const nombreProducto = $(this).data('nombre').toLowerCase();
            const codigoBarras = $(this).data('codigo').toLowerCase();
            $(this).toggle(nombreProducto.includes(valorBuscado) || codigoBarras.includes(valorBuscado));
        });
    }

    // Evento para cambiar entre factura y cotización
    tipoDocumentoSelect.on('change', function() {
        const tipoDocumento = $(this).val();
        if (tipoDocumento === 'factura') {
            tituloDocumento.text('Factura de venta');
            ventaBoton.text('Confirmar Venta');
            metodoPagoSelect.prop('disabled', false);
        } else {
            tituloDocumento.text('Cotización');
            ventaBoton.text('Generar Cotización');
            metodoPagoSelect.prop('disabled', true);
        }
    });

    // Agregar un evento para mostrar/ocultar campos adicionales para el crédito
    metodoPagoSelect.on('change', function() {
        const metodoPago = $(this).val();
        if (metodoPago === 'credito') {
            // Mostrar campos adicionales para el crédito
            $('#campos-credito').show();
        } else {
            // Ocultar campos adicionales para el crédito
            $('#campos-credito').hide();
        }
    });

    // Evento para buscar y filtrar productos
    buscarProductoInput.on('input', function() {
        filtrarProductos();
    });

    // Evento para agregar producto al presionar Enter en el buscador
    buscarProductoInput.on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            const productoEncontrado = $('.product-card:visible').first();
            
            if (productoEncontrado.length) {
                const id = productoEncontrado.data('id');
                const nombre = productoEncontrado.data('nombre');
                const precio = parseFloat(productoEncontrado.data('precio'));
                const stock = parseInt(productoEncontrado.data('cantidad'));
                agregarProducto(id, nombre, precio, stock);
                $(this).val('');
                filtrarProductos();
            } else {
                mostrarAlertaError('Producto no encontrado');
            }
        }
    });

    // Evento para agregar producto al hacer clic
    $(document).on('click', '.product-card', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const precio = parseFloat($(this).data('precio'));
        const stock = parseInt($(this).data('cantidad'));
        agregarProducto(id, nombre, precio, stock);
    });

    // Eventos para eliminar producto y cambiar cantidad
    listaVenta.on('click', '.eliminar-producto', function () {
        const id = $(this).data('id');
        carrito = carrito.filter(item => item.id !== id);
        actualizarCarrito();
    }).on('change', '.cantidad-producto', function () {
        const id = $(this).data('id');
        const nuevaCantidad = parseInt($(this).val());
        const producto = carrito.find(item => item.id === id);
        if (producto) {
            if (nuevaCantidad > producto.stock) {
                mostrarAlertaError(`No hay suficiente stock para ${producto.nombre}`);
                $(this).val(producto.cantidad);
                return;
            }
            producto.cantidad = nuevaCantidad;
            actualizarCarrito();
        }
    });

    // Agregar evento para aplicar descuento
    descuentoInput.on('input', actualizarCarrito);

    // Evento para confirmar la venta o generar cotización
    ventaBoton.on('click', function () {
        if (carrito.length === 0) {
            mostrarAlertaError('No hay productos en el carrito.');
            return;
        }

        const clienteId = clienteSelect.val();
        if (!clienteId) {
            mostrarAlertaError('Por favor, seleccione un cliente.');
            return;
        }

        const tipoDocumento = tipoDocumentoSelect.val();
        const descuento = parseFloat(descuentoInput.val()) || 0;
        const metodoPago = metodoPagoSelect.val();
        const subtotal = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        const total = subtotal * (1 - descuento / 100);

        const datos = {
            tipo_documento: tipoDocumento,
            cliente_id: clienteId,
            productos: carrito.map(item => ({
                id: item.id,
                cantidad: item.cantidad,
                precio: item.precio
            })),
            total: total,
            descuento: descuento,
            metodo_pago: metodoPago
        };

        // Agregar información de crédito si es necesario
        if (metodoPago === 'credito') {
            datos.credito = {
                plazo: $('#plazo-credito').val(),
                interes: $('#interes-credito').val()
            };
        }

        console.log('Datos a enviar:', datos); // Para depuración

        ventaBoton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');

        $.ajax({
            url: 'procesar_venta.php',
            method: 'POST',
            data: { venta: JSON.stringify(datos) },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: `${tipoDocumento === 'factura' ? 'Venta' : 'Cotización'} procesada correctamente. Número de documento: ${response.numero_factura}`,
                        showConfirmButton: false,
                        timer: 5000
                    });
                    
                    Swal.fire({
                        title: 'Imprimir documento',
                        text: `¿Desea imprimir la ${tipoDocumento === 'factura' ? 'factura' : 'cotización'}?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, imprimir',
                        cancelButtonText: 'No, gracias'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            imprimirDocumento(response.venta_id);
                        }
                    });
                    
                    if (tipoDocumento === 'factura') {
                        // Actualizar el stock en la página con la información del servidor
                        response.productos_actualizados.forEach(producto => {
                            const productoCard = $(`.product-card[data-id="${producto.id}"]`);
                            productoCard.data('cantidad', producto.nuevo_stock);
                            productoCard.find('small:contains("Stock:")').text(`Stock: ${producto.nuevo_stock}`);
                        });
                    }
                    
                    // Reiniciar el formulario
                    carrito = [];
                    actualizarCarrito();
                    descuentoInput.val(0);
                    clienteSelect.val('');
                    metodoPagoSelect.val('efectivo');
                    buscarProductoInput.val('').focus();
                } else {
                    mostrarAlertaError(`Error al procesar el documento: ${response.message}`);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error en la solicitud AJAX:', status, error);
                mostrarAlertaError('Error en la comunicación con el servidor.');
            },
            complete: function() {
                ventaBoton.prop('disabled', false).html(tipoDocumento === 'factura' ? 'Confirmar Venta' : 'Generar Cotización');
            }
        });
    });

    // Enfocar automáticamente el campo de búsqueda al cargar la página
    buscarProductoInput.focus();

    // Inicializar el carrito
    actualizarCarrito();

    // Evento para imprimir ticket anterior
    $('#imprimir-ticket-anterior').on('click', function() {
        console.log('Botón de imprimir ticket anterior clickeado'); // Para depuración
        $.ajax({
            url: 'obtener_ventas.php',
            method: 'GET',
            dataType: 'json',
            success: function(ventas) {
                console.log('Ventas obtenidas:', ventas); // Para depuración
                if (ventas.length > 0) {
                    mostrarModalVentas(ventas);
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin ventas',
                        text: 'No se encontraron ventas anteriores.',
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al obtener las ventas:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudieron cargar las ventas anteriores.',
                });
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
                    const ventaId = $(this).data('venta-id');
                    imprimirDocumento(ventaId);
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
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El navegador bloqueó la ventana emergente. Por favor, permita las ventanas emergentes para este sitio.',
            });
        } else {
            printWindow.onload = function() {
                printWindow.print();
            };
        }
    }
});