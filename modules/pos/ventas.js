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

    // Función para mostrar alertas
    function mostrarAlerta(mensaje, tipo = 'warning') {
        const alertElement = $(`<div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
            ${mensaje}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>`);
        
        alertContainer.append(alertElement);
        
        // Desaparecer la alerta después de 5 segundos
        setTimeout(() => {
            alertElement.alert('close');
        }, 5000);
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
            mostrarAlerta(`No hay stock disponible para ${nombre}`, 'danger');
            return;
        }

        const productoExistente = carrito.find(item => item.id === id);

        if (productoExistente) {
            if (productoExistente.cantidad + cantidad > stock) {
                mostrarAlerta(`No hay suficiente stock para ${nombre}`, 'warning');
                return;
            }
            productoExistente.cantidad += cantidad;
        } else {
            carrito.push({ id, nombre, precio, cantidad, stock });
        }

        actualizarCarrito();
        mostrarAlerta(`${nombre} agregado al carrito`, 'success');
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
    buscarProductoInput.on('input', filtrarProductos);

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
                mostrarAlerta('Producto no encontrado', 'danger');
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
                mostrarAlerta(`No hay suficiente stock para ${producto.nombre}`, 'warning');
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
            mostrarAlerta('No hay productos en el carrito.', 'warning');
            return;
        }

        const clienteId = clienteSelect.val();
        if (!clienteId) {
            mostrarAlerta('Por favor, seleccione un cliente.', 'warning');
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
            url: 'procesar_documento.php',
            method: 'POST',
            data: { datos: JSON.stringify(datos) },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    mostrarAlerta(`<i class="fas fa-check-circle"></i> ${tipoDocumento === 'factura' ? 'Venta' : 'Cotización'} procesada correctamente. Número de documento: ${response.numero_documento}`, 'success');
                    
                    if (confirm(`¿Desea imprimir la ${tipoDocumento === 'factura' ? 'factura' : 'cotización'}?`)) {
                        imprimirDocumento(response.datos_impresion);
                    }
                    
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
                    mostrarAlerta(`<i class="fas fa-exclamation-triangle"></i> Error al procesar el documento: ${response.message}`, 'danger');
                }
            },
            error: function (xhr, status, error) {
                console.error('Error en la solicitud AJAX:', status, error);
                mostrarAlerta('<i class="fas fa-exclamation-circle"></i> Error en la comunicación con el servidor.', 'danger');
            },
            complete: function() {
                ventaBoton.prop('disabled', false).html(tipoDocumento === 'factura' ? 'Confirmar Venta' : 'Generar Cotización');
            }
        });
    });

    function imprimirDocumento(datos) {
        const ventanaImpresion = window.open('', '', 'width=300,height=600');
        
        const estiloCSS = `
            <style>
                @media print {
                    @page { margin: 0; }
                }
                body { 
                    font-family: 'Arial', sans-serif; 
                    font-size: 12px; 
                    width: 80mm; 
                    margin: 0; 
                    padding: 10px; 
                }
                .header { text-align: center; margin-bottom: 10px; }
                .header h1 { font-size: 16px; margin: 0; }
                .header p { font-size: 12px; margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                th, td { text-align: left; padding: 5px; }
                th { border-bottom: 1px solid #ddd; }
                .totals { margin-top: 10px; }
                .totals p { margin: 5px 0; text-align: right; }
                .total { font-weight: bold; font-size: 14px; }
                .footer { margin-top: 20px; text-align: center; font-size: 10px; }
            </style>
        `;
        
        let contenidoHTML = `
            ${estiloCSS}
            <div class="header">
                <h1>${datos.tipo_documento === 'factura' ? 'Factura de Venta' : 'Cotización'}</h1>
                <p><strong>Número:</strong> ${datos.numero_documento}</p>
                <p><strong>Fecha:</strong> ${datos.fecha}</p>
                <p><strong>Cliente:</strong> ${datos.cliente}</p>
            </div>
            <table>
                <tr>
                    <th>Producto</th>
                    <th>Cant.</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                </tr>
        `;
        
        datos.productos.forEach(producto => {
            contenidoHTML += `
                <tr>
                    <td>${producto.nombre}</td>
                    <td>${producto.cantidad}</td>
                    <td>${formatearPrecioCOP(producto.precio)}</td>
                    <td>${formatearPrecioCOP(producto.cantidad * producto.precio)}</td>
                </tr>
            `;
        });
        
        contenidoHTML += `
            </table>
            <div class="totals">
                <p><strong>Subtotal:</strong> ${formatearPrecioCOP(datos.subtotal)}</p>
                <p><strong>Descuento:</strong> ${formatearPrecioCOP(datos.descuento)}</p>
                <p class="total"><strong>Total:</strong> ${formatearPrecioCOP(datos.total)}</p>
                <p><strong>Método de Pago:</strong> ${datos.metodo_pago}</p>
            </div>
            <div class="footer">
                <p>Gracias por su compra</p>
            </div>
        `;
        
        ventanaImpresion.document.write(contenidoHTML);
        ventanaImpresion.document.close();
        ventanaImpresion.print();
        ventanaImpresion.close();
    }

    // Enfocar automáticamente el campo de búsqueda al cargar la página
    buscarProductoInput.focus();

    // Inicializar el carrito
    actualizarCarrito();
});