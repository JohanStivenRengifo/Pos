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

        const fragment = document.createDocumentFragment();
        carrito.forEach(item => {
            const subtotalItem = item.precio * item.cantidad;
            subtotal += subtotalItem;
            cantidadTotal += item.cantidad;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.nombre}</td>
                <td>
                    <input type="number" class="form-control form-control-sm cantidad-producto" 
                           data-id="${item.id}" value="${item.cantidad}" min="1" max="${item.stock}" style="width: 60px;">
                </td>
                <td class="text-right">${formatearPrecioCOP(item.precio)}</td>
                <td class="text-right">${formatearPrecioCOP(subtotalItem)}</td>
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
        $('#descuento-monto').text(`-${formatearPrecioCOP(descuento)}`);
        $('#venta-total').text(formatearPrecioCOP(total));
        
        // Actualizar contador de items
        actualizarContadorItems();
        
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

    $ventaBoton.on('click', async function () {
        if (!validarCarrito()) return;

        const clienteId = $clienteSelect.val();
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

        try {
            const response = await fetch('procesar_venta.php', {
                method: 'POST',
                body: JSON.stringify(datos),
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            let data;
            const responseText = await response.text();
            
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Error al parsear respuesta:', responseText);
                throw new Error('Error al procesar la respuesta del servidor');
            }

            if (!response.ok) {
                throw new Error(data.message || `Error del servidor: ${response.status}`);
            }

            if (data.status === true) {
                const clienteEmail = await obtenerEmailCliente(clienteId);
                
                Swal.fire({
                    title: 'Venta Completada',
                    html: `
                        <p>${tipoDocumento === 'factura' ? 'Venta' : 'Cotización'} procesada correctamente.</p>
                        <p>Número de documento: ${data.numero_factura}</p>
                    `,
                    icon: 'success',
                    showDenyButton: true,
                    showCancelButton: true,
                    confirmButtonText: 'Imprimir Ticket',
                    denyButtonText: 'Enviar por Email',
                    cancelButtonText: 'Cerrar',
                    allowOutsideClick: false
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        imprimirDocumento(data.venta_id);
                    } else if (result.isDenied) {
                        // Si el cliente es consumidor final o no tiene email registrado
                        if (!clienteEmail) {
                            const { value: email } = await Swal.fire({
                                title: 'Ingrese el correo electrónico',
                                input: 'email',
                                inputLabel: 'Correo electrónico para enviar la factura',
                                inputPlaceholder: 'correo@ejemplo.com',
                                showCancelButton: true,
                                cancelButtonText: 'Cancelar',
                                confirmButtonText: 'Enviar',
                                validationMessage: 'El correo electrónico no es válido'
                            });

                            if (email) {
                                enviarFacturaEmail(data.venta_id, email);
                            }
                        } else {
                            // Si el cliente tiene email registrado
                            Swal.fire({
                                title: 'Enviar Factura',
                                html: `¿Desea enviar la factura al correo ${clienteEmail}?`,
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Sí, enviar',
                                cancelButtonText: 'No, usar otro correo'
                            }).then(async (result) => {
                                if (result.isConfirmed) {
                                    enviarFacturaEmail(data.venta_id, clienteEmail);
                                } else if (result.dismiss === Swal.DismissReason.cancel) {
                                    const { value: newEmail } = await Swal.fire({
                                        title: 'Ingrese el correo electrónico',
                                        input: 'email',
                                        inputLabel: 'Correo electrónico para enviar la factura',
                                        inputPlaceholder: 'correo@ejemplo.com',
                                        showCancelButton: true,
                                        cancelButtonText: 'Cancelar',
                                        confirmButtonText: 'Enviar',
                                        validationMessage: 'El correo electrónico no es válido'
                                    });

                                    if (newEmail) {
                                        enviarFacturaEmail(data.venta_id, newEmail);
                                    }
                                }
                            });
                        }
                    }
                    // Recargar la página después de todas las acciones
                    if (!result.isDenied) {
                        window.location.reload();
                    }
                });

                // Actualizar stock en las tarjetas de productos si es una factura
                if (tipoDocumento === 'factura' && data.productos_actualizados) {
                    data.productos_actualizados.forEach(producto => {
                        const productoCard = $(`.product-card[data-id="${producto.id}"]`);
                        if (productoCard.length) {
                            productoCard.data('cantidad', producto.nuevo_stock);
                            productoCard.find('.item-view__quantity').text(`Stock: ${producto.nuevo_stock}`);
                        }
                    });
                }
            } else {
                throw new Error(data.message || 'Error desconocido al procesar la venta');
            }
        } catch (error) {
            console.error('Error completo:', error);
            
            Swal.fire({
                title: 'Error al Procesar la Venta',
                text: error.message || 'Ocurrió un error al procesar la venta. Por favor, intente nuevamente.',
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
        } finally {
            $ventaBoton.prop('disabled', false)
                .html(`<i class="fas fa-check-circle mr-2"></i>${tipoDocumento === 'factura' ? 'Confirmar Venta' : 'Generar Cotización'}`);
        }
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

    // Configuración para el lector de códigos de barras
    let codigoBarras = '';
    let timeoutBarcode;
    const BARCODE_DELAY = 50; // Tiempo máximo entre caracteres del código de barras

    // Evento para capturar entrada del lector de códigos de barras
    document.addEventListener('keypress', function(e) {
        // Solo procesar si no estamos en un campo de entrada
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault(); // Prevenir la entrada en otros elementos

            // Reiniciar el timeout
            clearTimeout(timeoutBarcode);

            // Agregar el carácter al código
            codigoBarras += e.key;

            // Configurar nuevo timeout
            timeoutBarcode = setTimeout(() => {
                if (codigoBarras.length >= 3) { // Mínimo de caracteres para un código válido
                    procesarCodigoBarras(codigoBarras);
                }
                codigoBarras = ''; // Limpiar el código
            }, BARCODE_DELAY);
        }
    });

    // Función mejorada para mostrar notificaciones
    function showNotification(type, message, details = '') {
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

        Toast.fire({
            icon: type,
            title: message,
            text: details,
            className: `notification-${type}`
        });
    }

    // Función mejorada para mostrar errores
    function showError(title, message, suggestion = '') {
        Swal.fire({
            icon: 'error',
            title: title,
            html: `
                <div class="error-message">
                    <p class="error-description">${message}</p>
                    ${suggestion ? `
                        <div class="error-suggestion">
                            <i class="fas fa-lightbulb"></i>
                            <p>${suggestion}</p>
                        </div>
                    ` : ''}
                </div>
            `,
            confirmButtonText: 'Entendido',
            customClass: {
                container: 'error-dialog',
                popup: 'error-popup',
                title: 'error-title',
                htmlContainer: 'error-container',
                confirmButton: 'error-confirm'
            }
        });
    }

    // Mensajes de error específicos para cada caso
    const ErrorMessages = {
        STOCK: {
            title: 'Stock Insuficiente',
            message: (producto) => `No hay suficiente stock disponible para "${producto}"`,
            suggestion: 'Verifique el inventario o ajuste la cantidad solicitada.'
        },
        BARCODE: {
            title: 'Código No Encontrado',
            message: (codigo) => `No se encontró ningún producto con el código: ${codigo}`,
            suggestion: 'Verifique que el código sea correcto o busque el producto manualmente.'
        },
        CLIENTE: {
            title: 'Cliente No Seleccionado',
            message: 'Es necesario seleccionar un cliente para continuar.',
            suggestion: 'Seleccione un cliente de la lista o use "Consumidor Final".'
        },
        CARRITO_VACIO: {
            title: 'Carrito Vacío',
            message: 'No hay productos en el carrito.',
            suggestion: 'Agregue productos escaneando su código o seleccionándolos de la lista.'
        },
        VENTA: {
            title: 'Error en la Venta',
            message: 'No se pudo procesar la venta.',
            suggestion: 'Verifique la conexión e intente nuevamente. Si el problema persiste, contacte al soporte.'
        },
        EMPRESA: {
            title: 'Configuración Pendiente',
            message: 'No se puede procesar la venta porque la configuración de la empresa no está completa.',
            suggestion: 'Por favor, configure los datos de su empresa en el módulo de Configuración antes de realizar ventas.'
        },
        EMPRESA_NO_ENCONTRADA: {
            title: 'Error de Configuración',
            message: 'La empresa asociada a su cuenta no se encuentra disponible.',
            suggestion: 'Contacte al administrador del sistema o verifique la configuración de su empresa.'
        }
    };

    // Función mejorada para procesar el código de barras
    async function procesarCodigoBarras(codigo) {
        const productoEncontrado = $('.product-card').filter(function() {
            return $(this).data('codigo').toString() === codigo;
        }).first();

        if (!productoEncontrado.length) {
            playBeepSound(false);
            showError(
                ErrorMessages.BARCODE.title,
                ErrorMessages.BARCODE.message(codigo),
                ErrorMessages.BARCODE.suggestion
            );
            return;
        }

        const id = productoEncontrado.data('id');
        const nombre = productoEncontrado.data('nombre');
        const precio = parseFloat(productoEncontrado.data('precio'));
        const stock = parseInt(productoEncontrado.data('cantidad'));

        if (stock <= 0) {
            playBeepSound(false);
            showError(
                ErrorMessages.STOCK.title,
                ErrorMessages.STOCK.message(nombre),
                ErrorMessages.STOCK.suggestion
            );
            return;
        }

        // Verificar si ya está en el carrito
        const itemExistente = carrito.find(item => item.id === id);
        if (itemExistente && itemExistente.cantidad >= stock) {
            playBeepSound(false);
            showError(
                ErrorMessages.STOCK.title,
                `Ya tiene ${itemExistente.cantidad} unidades de "${nombre}" en el carrito.`,
                'La cantidad en el carrito ya alcanzó el máximo disponible.'
            );
            return;
        }

        playBeepSound(true);
        agregarProducto(id, nombre, precio, stock);
        showNotification(
            'success',
            'Producto Agregado',
            `${nombre} - Stock disponible: ${stock - (itemExistente?.cantidad || 0)}`
        );
    }

    // Función para reproducir sonidos
    function playBeepSound(success) {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(success ? 1000 : 400, audioContext.currentTime);
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);

        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.1);
    }

    // Mejorar la búsqueda manual para que no interfiera con el lector
    let searchTimeout;
    $buscarProductoInput.on('input', function() {
        clearTimeout(searchTimeout);
        const valorBuscado = $(this).val().trim();
        
        searchTimeout = setTimeout(() => {
            filtrarProductos(valorBuscado);
        }, 300); // Delay para no interferir con el lector
    });

    // Mejorar validación del carrito
    function validarCarrito() {
        if (carrito.length === 0) {
            showError(
                ErrorMessages.CARRITO_VACIO.title,
                ErrorMessages.CARRITO_VACIO.message,
                ErrorMessages.CARRITO_VACIO.suggestion
            );
            return false;
        }

        const clienteId = $clienteSelect.val();
        if (!clienteId) {
            showError(
                ErrorMessages.CLIENTE.title,
                ErrorMessages.CLIENTE.message,
                ErrorMessages.CLIENTE.suggestion
            );
            $clienteSelect.focus();
            return false;
        }

        // Validar stock disponible
        const stockInsuficiente = carrito.find(item => {
            const productoCard = $(`.product-card[data-id="${item.id}"]`);
            const stockDisponible = parseInt(productoCard.data('cantidad'));
            return item.cantidad > stockDisponible;
        });

        if (stockInsuficiente) {
            const producto = $(`.product-card[data-id="${stockInsuficiente.id}"]`).data('nombre');
            showError(
                ErrorMessages.STOCK.title,
                ErrorMessages.STOCK.message(producto),
                ErrorMessages.STOCK.suggestion
            );
            return false;
        }

        return true;
    }

    // Mejorar el manejo de errores en la búsqueda
    $buscarProductoInput.on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const valorBuscado = $(this).val().trim();
            
            if (valorBuscado.length < 3) {
                showError(
                    'Búsqueda muy corta',
                    'Ingrese al menos 3 caracteres para buscar.',
                    'Para códigos de barras, escanee directamente el producto.'
                );
                return;
            }

            if (!buscarPorCodigoBarrasExacto(valorBuscado)) {
                const productosVisibles = $('.product-card:visible');
                
                if (productosVisibles.length === 0) {
                    showError(
                        'Sin resultados',
                        `No se encontraron productos que coincidan con: "${valorBuscado}"`,
                        'Intente con otros términos o verifique el código de barras.'
                    );
                } else if (productosVisibles.length > 1) {
                    showNotification(
                        'info',
                        `Se encontraron ${productosVisibles.length} productos`,
                        'Haga clic en el producto deseado para agregarlo al carrito.'
                    );
                } else {
                    const producto = productosVisibles.first();
                    agregarProducto(
                        producto.data('id'),
                        producto.data('nombre'),
                        parseFloat(producto.data('precio')),
                        parseInt(producto.data('cantidad'))
                    );
                }
            }
            $(this).val('');
            filtrarProductos('');
        }
    });

    // Estilos CSS adicionales para los mensajes de error
    const errorStyles = `
        .error-dialog {
            font-family: 'Inter', sans-serif;
        }
        .error-popup {
            padding: 1.5rem;
        }
        .error-title {
            color: #dc2626;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        .error-description {
            color: #4b5563;
            margin-bottom: 1rem;
        }
        .error-suggestion {
            background: #fff7ed;
            border-left: 4px solid #f97316;
            padding: 1rem;
            margin-top: 1rem;
            display: flex;
            align-items: start;
            gap: 0.75rem;
        }
        .error-suggestion i {
            color: #f97316;
            font-size: 1.25rem;
        }
        .error-suggestion p {
            color: #9a3412;
            margin: 0;
            font-size: 0.875rem;
        }
        .error-confirm {
            background: #dc2626 !important;
            color: white !important;
        }
    `;

    // Agregar estilos al documento
    const style = document.createElement('style');
    style.textContent = errorStyles;
    document.head.appendChild(style);

    function handleVentaError(error) {
        if (error.includes('empresa_id') || error.includes('no se encontró la empresa')) {
            showError(
                ErrorMessages.EMPRESA.title,
                ErrorMessages.EMPRESA.message,
                ErrorMessages.EMPRESA.suggestion
            );
        } else if (error.includes('Error al procesar la venta')) {
            showError(
                ErrorMessages.VENTA.title,
                'No se pudo completar la venta en este momento.',
                'Verifique su conexión e intente nuevamente. Si el problema persiste, contacte al soporte técnico.'
            );
        } else {
            showError(
                'Error en el Proceso',
                error,
                'Si el problema persiste, contacte al soporte técnico.'
            );
        }
    }

    // Agregar esta función para actualizar el contador de items
    function actualizarContadorItems() {
        const cantidadTotal = carrito.reduce((sum, item) => sum + item.cantidad, 0);
        $('#cantidad-items').text(`${cantidadTotal} ${cantidadTotal === 1 ? 'item' : 'items'}`);
        $('#venta-lista-empty').toggle(carrito.length === 0);
    }

    // Agregar después de la inicialización de variables

    // Manejador para el botón de nuevo cliente
    $('#nuevo-cliente').on('click', function(e) {
        e.preventDefault();
        $('#nuevoClienteModal').modal({
            backdrop: 'static',
            keyboard: false,
            show: true
        });
    });

    // Manejador para guardar nuevo cliente
    $('#guardarCliente').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $form = $('#nuevoClienteForm');

        // Validar campos requeridos
        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }

        // Deshabilitar botón y mostrar spinner
        $btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...');

        // Recopilar datos del formulario
        const clienteData = {
            nombre: $('#nombre').val().trim(),
            documento: $('#documento').val().trim(),
            telefono: $('#telefono').val().trim(),
            email: $('#email').val().trim(),
            direccion: $('#direccion').val().trim()
        };

        // Enviar datos al servidor
        $.ajax({
            url: 'guardar_cliente.php',
            method: 'POST',
            data: clienteData,
            dataType: 'json',
            success: function(response) {
                if (response.status) {
                    // Agregar el nuevo cliente al select
                    const newOption = new Option(response.cliente.nombre, response.cliente.id, true, true);
                    $('#cliente-select').append(newOption).trigger('change');

                    // Mostrar mensaje de éxito
                    Swal.fire({
                        title: 'Cliente Guardado',
                        text: 'El cliente se ha registrado correctamente',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });

                    // Cerrar modal y limpiar formulario
                    $('#nuevoClienteModal').modal('hide');
                    $form[0].reset();
                } else {
                    throw new Error(response.message || 'Error al guardar el cliente');
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudo guardar el cliente. Por favor, intente nuevamente.',
                    icon: 'error'
                });
            },
            complete: function() {
                // Restaurar botón
                $btn.prop('disabled', false)
                    .html('<i class="fas fa-save mr-1"></i>Guardar Cliente');
            }
        });
    });

    // Limpiar formulario al cerrar el modal
    $('#nuevoClienteModal').on('hidden.bs.modal', function() {
        $('#nuevoClienteForm')[0].reset();
        const $btn = $('#guardarCliente');
        $btn.prop('disabled', false)
            .html('<i class="fas fa-save mr-1"></i>Guardar Cliente');
    });

    // Agregar validación en tiempo real
    $('#nuevoClienteForm input').on('input', function() {
        $(this).removeClass('is-invalid');
        if ($(this).attr('required') && !$(this).val().trim()) {
            $(this).addClass('is-invalid');
        }
    });

    // Función para obtener el email del cliente
    async function obtenerEmailCliente(clienteId) {
        try {
            const response = await fetch(`obtener_email_cliente.php?id=${clienteId}`);
            const data = await response.json();
            return data.email || null;
        } catch (error) {
            console.error('Error al obtener email del cliente:', error);
            return null;
        }
    }

    // Función para enviar la factura por email
    function enviarFacturaEmail(ventaId, email) {
        Swal.fire({
            title: 'Enviando factura...',
            text: 'Por favor espere mientras se envía la factura.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'enviar_factura_email.php',
            method: 'POST',
            data: {
                venta_id: ventaId,
                email: email
            },
            dataType: 'json',
            success: function(response) {
                if (response.status) {
                    Swal.fire({
                        title: 'Factura Enviada',
                        text: 'La factura ha sido enviada correctamente al correo electrónico.',
                        icon: 'success',
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.message || 'No se pudo enviar la factura.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error',
                    text: 'Hubo un problema al enviar la factura.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            }
        });
    }

    // Agregar después de la declaración de variables existentes
    let numeracionTipo = 'principal';

    // Agregar el listener para el cambio de numeración
    $('#numeracion').on('change', function() {
        numeracionTipo = $(this).val();
    });

    // Modificar la función de procesar venta
    async function procesarVenta() {
        if (!validarCarrito()) return;

        const clienteId = $('#cliente-select').val();
        const tipoDocumento = $('#tipo-documento').val();
        const descuento = parseFloat($('#descuento').val()) || 0;
        const metodoPago = $('#metodo-pago').val();
        const numeracionTipo = $('#numeracion').val();
        
        const ventaData = {
            tipo_documento: tipoDocumento,
            cliente_id: clienteId,
            productos: carrito.map(item => ({
                id: item.id,
                cantidad: item.cantidad,
                precio: item.precio
            })),
            total: calcularTotal(),
            descuento: descuento,
            metodo_pago: metodoPago,
            numeracion_tipo: numeracionTipo,
            numeracion: numeracionTipo
        };

        try {
            const response = await fetch('procesar_venta.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(ventaData)
            });

            let result;
            const responseText = await response.text();
            
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                console.error('Respuesta del servidor:', responseText);
                throw new Error('Error al procesar la respuesta del servidor');
            }

            if (!result.status) {
                throw new Error(result.message || 'Error desconocido al procesar la venta');
            }

            // Si es factura electrónica, enviar a Factus
            if (numeracionTipo === 'electronica') {
                try {
                    await enviarFacturaElectronica(result.venta_id);
                } catch (error) {
                    console.error('Error al enviar factura electrónica:', error);
                    // Mostrar error pero continuar con el proceso
                    Swal.fire({
                        title: 'Advertencia',
                        text: 'La venta se procesó correctamente pero hubo un error al generar la factura electrónica. Se intentará nuevamente más tarde.',
                        icon: 'warning'
                    });
                }
            }

            // Mostrar mensaje de éxito
            await Swal.fire({
                title: 'Éxito',
                text: 'Venta procesada correctamente',
                icon: 'success'
            });

            // Limpiar carrito y formulario
            limpiarVenta();
            
            return result;

        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: error.message,
                icon: 'error'
            });
            throw error;
        }
    }

    // Función para enviar factura electrónica
    async function enviarFacturaElectronica(ventaId) {
        try {
            console.log('Enviando factura electrónica para venta:', ventaId);
            
            const response = await fetch('enviar_factura_electronica.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ venta_id: ventaId })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            console.log('Respuesta de factura electrónica:', result);
            
            if (!result.success) {
                throw new Error(result.message || 'Error al enviar factura electrónica');
            }
            
            // Verificar el estado después del envío
            await verificarEstadoFactura(ventaId);
            
            return result;
        } catch (error) {
            console.error('Error al enviar factura electrónica:', error);
            throw error;
        }
    }

    async function verificarEstadoFactura(ventaId) {
        try {
            const response = await fetch(`consultar_estado_factura.php?venta_id=${ventaId}`);
            const result = await response.json();
            console.log('Estado de la factura:', result);
            return result;
        } catch (error) {
            console.error('Error al verificar estado:', error);
        }
    }
});