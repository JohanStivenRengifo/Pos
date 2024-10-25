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
    const clienteBusqueda = $('#cliente-busqueda');
    const clienteLista = $('#cliente-lista');
    const nuevoClienteForm = $('#nuevo-cliente-form');
    const cantidadTotalElement = $('#cantidad-total');

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
        const listaVenta = $('#venta-lista tbody');
        listaVenta.empty();
        let subtotal = 0;
        let cantidadTotal = 0;

        if (carrito.length === 0) {
            $('#venta-lista-empty').show();
        } else {
            $('#venta-lista-empty').hide();
        }

        carrito.forEach(item => {
            const subtotalItem = item.precio * item.cantidad;
            subtotal += subtotalItem;
            cantidadTotal += item.cantidad;

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

        const descuentoPorcentaje = parseFloat($('#descuento').val()) || 0;
        const descuento = subtotal * (descuentoPorcentaje / 100);
        const total = subtotal - descuento;

        $('#subtotal').text(formatearPrecioCOP(subtotal));
        $('#descuento-monto').text(formatearPrecioCOP(descuento));
        $('#venta-total').text(formatearPrecioCOP(total));
        $('#cantidad-total').text(cantidadTotal);

        $('#venta-boton').prop('disabled', carrito.length === 0);
    }

    // Función para agregar producto al carrito
    function agregarProducto(id, nombre, precio, stock, cantidad = 1) {
        if (stock <= 0) {
            mostrarAlerta('error', 'Error', `No hay stock disponible para ${nombre}`);
            return;
        }

        const productoExistente = carrito.find(item => item.id === id);

        if (productoExistente) {
            if (productoExistente.cantidad + cantidad > stock) {
                mostrarAlerta('error', 'Error', `No hay suficiente stock para ${nombre}`);
                return;
            }
            productoExistente.cantidad += cantidad;
        } else {
            carrito.push({ id, nombre, precio, cantidad, stock });
        }

        actualizarCarrito();
    }

    // Función para debounce
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Función para filtrar productos
    function filtrarProductos(valorBuscado) {
        valorBuscado = valorBuscado.toLowerCase();
        $('.product-card').each(function () {
            const nombreProducto = $(this).data('nombre').toLowerCase();
            const codigoBarras = $(this).data('codigo').toString().toLowerCase();
            $(this).toggle(nombreProducto.includes(valorBuscado) || codigoBarras.includes(valorBuscado));
        });
    }

    // Función para buscar producto por código de barras
    function buscarPorCodigoBarras(codigo) {
        const productoEncontrado = $('.product-card').filter(function() {
            return $(this).data('codigo').toString().toLowerCase() === codigo.toLowerCase();
        }).first();

        if (productoEncontrado.length) {
            const id = productoEncontrado.data('id');
            const nombre = productoEncontrado.data('nombre');
            const precio = parseFloat(productoEncontrado.data('precio'));
            const stock = parseInt(productoEncontrado.data('cantidad'));
            agregarProducto(id, nombre, precio, stock);
            buscarProductoInput.val('');
            return true;
        }
        return false;
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
    buscarProductoInput.on('input', debounce(function() {
        const valorBuscado = $(this).val().trim();
        filtrarProductos(valorBuscado);

        if (valorBuscado.length >= 8) {
            buscarPorCodigoBarras(valorBuscado);
        }
    }, 300));

    // Evento para agregar producto al presionar Enter en el buscador
    buscarProductoInput.on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            const valorBuscado = $(this).val().trim();
            
            if (!buscarPorCodigoBarras(valorBuscado)) {
                const productoEncontrado = $('.product-card:visible').first();
                
                if (productoEncontrado.length) {
                    const id = productoEncontrado.data('id');
                    const nombre = productoEncontrado.data('nombre');
                    const precio = parseFloat(productoEncontrado.data('precio'));
                    const stock = parseInt(productoEncontrado.data('cantidad'));
                    agregarProducto(id, nombre, precio, stock);
                } else {
                    mostrarAlertaError('Producto no encontrado');
                }
            }
            $(this).val('');
            filtrarProductos('');
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

    // Función para buscar clientes
    function buscarClientes(query) {
        $.ajax({
            url: 'buscar_clientes.php',
            method: 'GET',
            data: { query: query },
            success: function(response) {
                const clientes = JSON.parse(response);
                mostrarResultadosClientes(clientes);
            },
            error: function(xhr, status, error) {
                console.error("Error al buscar clientes:", error);
            }
        });
    }

    // Función para mostrar los resultados de la búsqueda de clientes
    function mostrarResultadosClientes(clientes) {
        const listaClientes = $('#cliente-lista');
        listaClientes.empty();

        if (clientes.length > 0) {
            clientes.forEach(function(cliente) {
                listaClientes.append(`
                    <a href="#" class="list-group-item list-group-item-action" data-id="${cliente.id}" data-nombre="${cliente.nombre}">
                        ${cliente.nombre} - ${cliente.documento}
                    </a>
                `);
            });
            listaClientes.show();
        } else {
            listaClientes.hide();
        }
    }

    // Evento para buscar clientes mientras se escribe
    $('#cliente-busqueda').on('input', function() {
        const query = $(this).val().trim();
        if (query.length >= 2) {
            buscarClientes(query);
        } else {
            $('#cliente-lista').hide();
        }
    });

    // Evento para seleccionar un cliente de la lista
    $('#cliente-lista').on('click', 'a', function(e) {
        e.preventDefault();
        const clienteId = $(this).data('id');
        const clienteNombre = $(this).data('nombre');
        seleccionarCliente(clienteId, clienteNombre);
    });

    // Función para seleccionar un cliente
    function seleccionarCliente(clienteId, clienteNombre) {
        $('#cliente-id').val(clienteId);
        $('#nombre-cliente-seleccionado').text(clienteNombre);
        $('#cliente-seleccionado').show();
        $('#cliente-busqueda').val('').hide();
        $('#cliente-lista').hide();
    }

    // Evento para cambiar el cliente seleccionado
    $('#cambiar-cliente').on('click', function() {
        $('#cliente-id').val('');
        $('#cliente-seleccionado').hide();
        $('#cliente-busqueda').val('').show().focus();
    });

    // Evento para mostrar formulario de nuevo cliente
    $('#nuevo-cliente').on('click', function() {
        nuevoClienteForm.show();
        clienteSelect.hide();
    });

    // Función para guardar un nuevo cliente
    function guardarNuevoCliente() {
        const nuevoCliente = {
            nombre: $('#nuevo-cliente-nombre').val(),
            documento: $('#nuevo-cliente-documento').val(),
            email: $('#nuevo-cliente-email').val()
        };

        $.ajax({
            url: 'guardar_cliente.php',
            method: 'POST',
            data: nuevoCliente,
            success: function(response) {
                const clienteGuardado = JSON.parse(response);
                // Agregar el nuevo cliente al select
                clienteSelect.append($('<option>', {
                    value: clienteGuardado.id,
                    text: clienteGuardado.nombre
                }));
                // Seleccionar el nuevo cliente
                clienteSelect.val(clienteGuardado.id);
                nuevoClienteForm.hide();
                clienteSelect.show();
                // Limpiar los campos del formulario
                $('#nuevo-cliente-nombre, #nuevo-cliente-documento, #nuevo-cliente-email').val('');
                // Mostrar mensaje de éxito
                Swal.fire({
                    icon: 'success',
                    title: 'Cliente guardado',
                    text: 'El cliente ha sido guardado y seleccionado exitosamente'
                });
            },
            error: function(xhr, status, error) {
                console.error("Error al guardar el cliente:", error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo guardar el cliente. Por favor, intente nuevamente.'
                });
            }
        });
    }

    // Evento para guardar un nuevo cliente
    $('#guardar-nuevo-cliente').on('click', function() {
        guardarNuevoCliente();
    });

    // Evento para cancelar nuevo cliente
    $('#cancelar-nuevo-cliente').on('click', function() {
        nuevoClienteForm.hide();
        clienteSelect.show();
    });
});
