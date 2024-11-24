// Definir las funciones globalmente primero
function abrirModalCliente() {
    document.getElementById('modal-cliente').classList.remove('hidden');
    limpiarEstilosError();
    // Enfocar el primer campo
    document.querySelector('[name="tipo_identificacion"]').focus();
}

function cerrarModalCliente() {
    document.getElementById('modal-cliente').classList.add('hidden');
    document.getElementById('form-nuevo-cliente').reset();
    limpiarEstilosError();
}

function guardarCliente() {
    const form = document.getElementById('form-nuevo-cliente');
    const formData = new FormData(form);

    // Validar campos requeridos
    const camposRequeridos = ['tipo_identificacion', 'identificacion', 'primer_nombre', 'correo'];
    let faltanCampos = false;
    
    camposRequeridos.forEach(campo => {
        if (!formData.get(campo)) {
            faltanCampos = true;
            document.querySelector(`[name="${campo}"]`).classList.add('border-red-500');
        } else {
            document.querySelector(`[name="${campo}"]`).classList.remove('border-red-500');
        }
    });

    if (faltanCampos) {
        Swal.fire({
            icon: 'error',
            title: 'Campos requeridos',
            text: 'Por favor complete todos los campos marcados con *'
        });
        return;
    }

    // Corregir la ruta del controlador
    fetch('./controllers/crear_cliente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar el select de clientes
            const select = document.getElementById('cliente-select');
            const option = document.createElement('option');
            option.value = data.cliente.id;
            // Construir el nombre completo del cliente
            const nombreCompleto = [
                formData.get('primer_nombre'),
                formData.get('segundo_nombre'),
                formData.get('apellidos')
            ].filter(Boolean).join(' ');
            option.textContent = nombreCompleto;
            select.appendChild(option);
            select.value = data.cliente.id;

            // Cerrar modal y mostrar mensaje de éxito
            cerrarModalCliente();
            Swal.fire({
                icon: 'success',
                title: '¡Cliente guardado!',
                text: 'El cliente ha sido registrado exitosamente',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Hubo un error al guardar el cliente'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Hubo un error al procesar la solicitud'
        });
    });
}

// Agregar función para limpiar los estilos de error
function limpiarEstilosError() {
    const inputs = document.querySelectorAll('#form-nuevo-cliente input, #form-nuevo-cliente select');
    inputs.forEach(input => {
        input.classList.remove('border-red-500');
    });
}

// Asignar las funciones al objeto window
window.abrirModalCliente = abrirModalCliente;
window.cerrarModalCliente = cerrarModalCliente;
window.guardarCliente = guardarCliente;
// El resto del código dentro del DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // Función para formatear moneda COP
    function formatCOP(number) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number).replace('COP', '$');
    }

    // Función para agregar producto al carrito
    function agregarProductoAlCarrito(producto) {
        const ventaLista = document.getElementById('venta-lista');
        const productoExistente = ventaLista.querySelector(`tr[data-id="${producto.id}"]`);

        if (productoExistente) {
            const cantidadInput = productoExistente.querySelector('.cantidad-input');
            const cantidadActual = parseInt(cantidadInput.value);
            if (cantidadActual < producto.cantidad) {
                cantidadInput.value = cantidadActual + 1;
                actualizarTotalProducto(productoExistente);
            } else {
                Swal.fire({
                    title: 'Stock insuficiente',
                    text: 'No hay más unidades disponibles de este producto',
                    icon: 'warning',
                    confirmButtonText: 'Aceptar'
                });
            }
        } else {
            const tr = document.createElement('tr');
            tr.dataset.id = producto.id;
            tr.dataset.precio = producto.precio;
            tr.innerHTML = `
                <td class="py-1 text-xs">
                    <div class="font-medium text-gray-900">${producto.nombre}</div>
                    <div class="text-gray-500">${producto.codigo}</div>
                </td>
                <td class="text-center">
                    <input type="number" 
                           class="cantidad-input w-12 text-center border border-gray-300 rounded-md text-xs py-0.5" 
                           value="1" 
                           min="1" 
                           max="${producto.cantidad}">
                </td>
                <td class="text-right text-xs">${formatCOP(producto.precio)}</td>
                <td class="text-right text-xs producto-total">${formatCOP(producto.precio)}</td>
                <td class="text-center">
                    <button class="eliminar-producto text-red-600 hover:text-red-800">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;

            ventaLista.appendChild(tr);
            document.getElementById('venta-lista-empty').style.display = 'none';

            // Agregar eventos a los controles del producto
            const cantidadInput = tr.querySelector('.cantidad-input');
            cantidadInput.addEventListener('change', function() {
                const cantidad = parseInt(this.value);
                const maxCantidad = parseInt(this.max);
                if (cantidad > maxCantidad) {
                    this.value = maxCantidad;
                    Swal.fire({
                        title: 'Stock insuficiente',
                        text: 'Has alcanzado el máximo de unidades disponibles',
                        icon: 'warning',
                        confirmButtonText: 'Aceptar'
                    });
                }
                actualizarTotalProducto(tr);
            });

            tr.querySelector('.eliminar-producto').addEventListener('click', function() {
                tr.remove();
                actualizarTotales();
                if (ventaLista.children.length === 0) {
                    document.getElementById('venta-lista-empty').style.display = 'block';
                }
            });
        }

        actualizarTotales();
    }

    // Función para actualizar el total de un producto
    function actualizarTotalProducto(tr) {
        const cantidad = parseInt(tr.querySelector('.cantidad-input').value);
        const precio = parseFloat(tr.dataset.precio);
        const total = precio * cantidad;
        tr.querySelector('.producto-total').textContent = formatCOP(total);
        actualizarTotales();
    }

    // Función para actualizar los totales generales
    function actualizarTotales() {
        const productos = document.querySelectorAll('#venta-lista tr');
        let subtotal = 0;
        let cantidadItems = 0;

        productos.forEach(tr => {
            const cantidad = parseInt(tr.querySelector('.cantidad-input').value);
            const precio = parseFloat(tr.dataset.precio);
            subtotal += precio * cantidad;
            cantidadItems += cantidad;
        });

        // Actualizar subtotal
        document.getElementById('subtotal').textContent = formatCOP(subtotal);
        
        // Actualizar cantidad de items
        document.getElementById('cantidad-items').textContent = cantidadItems;

        // Calcular y actualizar descuento
        const descuentoPorcentaje = parseInt(document.getElementById('descuento').value) || 0;
        const descuentoMonto = (subtotal * descuentoPorcentaje) / 100;
        document.getElementById('descuento-monto').textContent = `-${formatCOP(descuentoMonto)}`;

        // Actualizar total
        const total = subtotal - descuentoMonto;
        document.getElementById('venta-total').textContent = formatCOP(total);

        // Habilitar/deshabilitar método de pago y botón de venta
        const metodoPago = document.getElementById('metodo-pago');
        const procesarVentaBtn = document.getElementById('procesar-venta');
        
        if (cantidadItems > 0) {
            metodoPago.disabled = false;
            procesarVentaBtn.disabled = false;
            procesarVentaBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            metodoPago.disabled = true;
            procesarVentaBtn.disabled = true;
            procesarVentaBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }
    }

    // Manejar clics en productos
    
    document.querySelectorAll('.item-view').forEach(producto => {
        producto.addEventListener('click', function() {
            const productoData = {
                id: this.dataset.id,
                nombre: this.dataset.nombre,
                precio: parseFloat(this.dataset.precio),
                cantidad: parseInt(this.dataset.cantidad),
                codigo: this.dataset.codigo
            };
            agregarProductoAlCarrito(productoData);
        });
    });

    // Evento para el input de descuento
    document.getElementById('descuento').addEventListener('input', actualizarTotales);

    // Función para procesar la venta
    function procesarVenta() {
        const productos = Array.from(document.querySelectorAll('#venta-lista tr')).map(tr => ({
            id: tr.dataset.id,
            cantidad: parseInt(tr.querySelector('.cantidad-input').value),
            precio: parseFloat(tr.dataset.precio),
            subtotal: parseFloat(tr.dataset.precio) * parseInt(tr.querySelector('.cantidad-input').value)
        }));

        const clienteId = document.getElementById('cliente-select').value;
        const tipoDocumento = document.getElementById('tipo-documento').value;
        const numeracion = document.getElementById('numeracion').value;
        const descuento = parseInt(document.getElementById('descuento').value) || 0;
        const metodoPago = document.getElementById('metodo-pago').value;

        // Validaciones
        if (productos.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Carrito vacío',
                text: 'Agrega productos antes de confirmar la venta'
            });
            return;
        }

        if (!clienteId) {
            Swal.fire({
                icon: 'warning',
                title: 'Cliente requerido',
                text: 'Selecciona un cliente para continuar'
            });
            return;
        }

        // Calcular totales
        const subtotal = productos.reduce((sum, prod) => sum + prod.subtotal, 0);
        const descuentoMonto = (subtotal * descuento) / 100;
        const total = subtotal - descuentoMonto;

        // Confirmar venta
        Swal.fire({
            title: '¿Confirmar venta?',
            html: `
                <div class="text-left">
                    <p class="mb-2"><strong>Cliente:</strong> ${document.getElementById('cliente-select').selectedOptions[0].text}</p>
                    <p class="mb-2"><strong>Total:</strong> ${formatCOP(total)}</p>
                    <p class="mb-2"><strong>Método de pago:</strong> ${metodoPago}</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                procesarVentaEnServidor({
                    cliente_id: clienteId,
                    tipo_documento: tipoDocumento,
                    numeracion: numeracion,
                    productos: productos,
                    descuento: descuento,
                    metodo_pago: metodoPago,
                    subtotal: subtotal,
                    descuento_monto: descuentoMonto,
                    total: total,
                    turno_id: document.getElementById('hora-apertura').dataset.turnoId
                });
            }
        });
    }

    // Función para procesar la venta en el servidor
    function procesarVentaEnServidor(ventaData) {
        Swal.fire({
            title: 'Procesando venta...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('./controllers/procesar_venta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(ventaData)
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Error parsing JSON:', text);
                throw new Error('Error en la respuesta del servidor');
            }
        })
        .then(data => {
            if (data.success) {
                localStorage.setItem('ultima_venta_id', data.venta_id);
                
                Swal.fire({
                    title: '¡Venta exitosa!',
                    text: data.message,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Imprimir ticket',
                    cancelButtonText: 'Cerrar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        imprimirTicket(data.venta_id);
                    }
                    limpiarCarrito();
                });
            } else {
                throw new Error(data.message || 'Error al procesar la venta');
            }
        })
        .catch(error => {
            console.error('Error procesando venta:', error);
            Swal.fire({
                title: 'Error',
                text: error.message,
                icon: 'error',
                confirmButtonText: 'Aceptar'
            });
        });
    }

    // Agregar el event listener al botón de procesar venta
    document.getElementById('procesar-venta').addEventListener('click', procesarVenta);

    // Función para imprimir ticket
    function imprimirTicket(ventaId) {
        localStorage.setItem('ultima_venta_id', ventaId);
        window.open(`./controllers/imprimir_ticket.php?venta_id=${ventaId}`, '_blank');
    }

    // Botón imprimir ticket
    document.getElementById('imprimir-ticket').addEventListener('click', function() {
        const ultimaVentaId = localStorage.getItem('ultima_venta_id');
        if (ultimaVentaId) {
            imprimirTicket(ultimaVentaId);
        } else {
            Swal.fire({
                title: 'No hay venta reciente',
                text: 'No hay un ticket disponible para imprimir',
                icon: 'info',
                confirmButtonText: 'Aceptar'
            });
        }
    });

    // Función para limpiar el carrito
    function limpiarCarrito() {
        const ventaLista = document.getElementById('venta-lista');
        ventaLista.innerHTML = '';
        
        document.getElementById('venta-lista-empty').style.display = 'block';
        document.getElementById('subtotal').textContent = formatCOP(0);
        document.getElementById('descuento').value = 0;
        document.getElementById('descuento-monto').textContent = `-${formatCOP(0)}`;
        document.getElementById('venta-total').textContent = formatCOP(0);
        document.getElementById('cantidad-items').textContent = '0';
        document.getElementById('metodo-pago').disabled = true;
        
        const procesarVentaBtn = document.getElementById('procesar-venta');
        procesarVentaBtn.disabled = true;
        procesarVentaBtn.classList.add('opacity-50', 'cursor-not-allowed');

        // Recargar la página para actualizar el stock
        window.location.reload();
    }

    // Función para actualizar el tiempo transcurrido
    function actualizarTiempoTranscurrido() {
        const horaAperturaElement = document.getElementById('hora-apertura');
        if (!horaAperturaElement) return;

        const fechaApertura = new Date(horaAperturaElement.dataset.fechaApertura);
        const ahora = new Date();
        const diferencia = ahora - fechaApertura;

        const horas = Math.floor(diferencia / (1000 * 60 * 60));
        const minutos = Math.floor((diferencia % (1000 * 60 * 60)) / (1000 * 60));

        document.getElementById('tiempo-transcurrido').textContent = 
            `${horas}h ${minutos}m`;
    }

    // Función para actualizar el total vendido
    function actualizarTotalVendido() {
        const turnoId = document.getElementById('hora-apertura').dataset.turnoId;
        
        fetch(`./controllers/obtener_total_turno.php?turno_id=${turnoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('total-vendido').textContent = 
                        formatCOP(data.total_vendido);
                }
            })
            .catch(error => {
                console.error('Error al obtener total vendido:', error);
                document.getElementById('total-vendido').textContent = 'Error al calcular';
            });
    }

    // Iniciar actualizaciones
    actualizarTiempoTranscurrido();
    actualizarTotalVendido();

    // Actualizar cada minuto
    setInterval(actualizarTiempoTranscurrido, 60000);
    // Actualizar total vendido cada 5 minutos
    setInterval(actualizarTotalVendido, 300000);

    // Actualizar total vendido después de cada venta exitosa
    const actualizarDespuesDeVenta = function() {
        actualizarTotalVendido();
    };

    // Variables para el lector de códigos de barras
    const BARCODE_DELAY = 20; // Tiempo máximo entre caracteres del código de barras (ms)
    const MIN_CHARS = 3; // Mínimo de caracteres para búsqueda manual

    // Función simple para filtrar productos
    function filtrarProductos(busqueda) {
        const productos = document.querySelectorAll('.item-view');
        busqueda = busqueda.toLowerCase().trim();

        productos.forEach(producto => {
            const nombre = producto.getAttribute('data-nombre').toLowerCase();
            const codigo = producto.getAttribute('data-codigo').toLowerCase();
            
            if (busqueda === '' || nombre.includes(busqueda) || codigo.includes(busqueda)) {
                producto.style.display = 'flex'; // Aseguramos que sea flex para mantener el layout
            } else {
                producto.style.display = 'none';
            }
        });
    }

    // Configuración del input de búsqueda
    document.addEventListener('DOMContentLoaded', function() {
        const buscarProductoInput = document.getElementById('buscar-producto');
        if (!buscarProductoInput) return;

        // Único listener para el input
        buscarProductoInput.addEventListener('input', function(e) {
            const busqueda = e.target.value;
            filtrarProductos(busqueda);
        });

        // Listener para el Enter
        buscarProductoInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const productosVisibles = Array.from(document.querySelectorAll('.item-view'))
                    .filter(p => p.style.display !== 'none');
                
                if (productosVisibles.length === 1) {
                    productosVisibles[0].click();
                    this.value = '';
                    filtrarProductos('');
                }
            }
        });

        // Limpiar y enfocar al cargar
        buscarProductoInput.value = '';
        filtrarProductos('');
        buscarProductoInput.focus();
    });

    // Función para procesar código de barras
    function procesarCodigoBarras(codigo) {
        const productos = document.querySelectorAll('.item-view');
        const producto = Array.from(productos).find(p => p.dataset.codigo === codigo);

        if (producto) {
            const productoData = {
                id: producto.dataset.id,
                nombre: producto.dataset.nombre,
                precio: parseFloat(producto.dataset.precio),
                cantidad: parseInt(producto.dataset.cantidad),
                codigo: producto.dataset.codigo
            };
            agregarProductoAlCarrito(productoData);
            reproducirBeep(true);
            document.getElementById('buscar-producto').value = '';
            filtrarProductos('');
        } else {
            reproducirBeep(false);
            Swal.fire({
                title: 'Producto no encontrado',
                text: 'No se encontró ningún producto con ese código de barras',
                icon: 'warning',
                confirmButtonText: 'Aceptar'
            });
        }
    }

    // Función para reproducir beep
    function reproducirBeep(success) {
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

    // Event listener para el input de búsqueda con debounce
    const buscarProductoInput = document.getElementById('buscar-producto');
    let timeoutId;

    buscarProductoInput.addEventListener('input', function(e) {
        const currentTime = new Date().getTime();
        
        // Si parece ser entrada del lector (rápida), no filtrar aún
        if (currentTime - ultimoKeyTime <= BARCODE_DELAY) {
            return;
        }

        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            filtrarProductos(this.value);
        }, 300);
    });

    // Event listener para capturar entrada del lector de códigos de barras
    document.addEventListener('keypress', function(e) {
        const currentTime = new Date().getTime();
        
        // Si el foco está en un input que no es el de búsqueda, ignorar
        if (e.target.tagName === 'INPUT' && e.target.id !== 'buscar-producto') {
            return;
        }

        // Si es Enter, procesar el código
        if (e.key === 'Enter') {
            if (codigoBarras.length >= MIN_CHARS) {
                procesarCodigoBarras(codigoBarras);
            }
            codigoBarras = '';
            return;
        }

        // Si el tiempo entre teclas es muy corto (lector de códigos)
        if (currentTime - ultimoKeyTime <= BARCODE_DELAY) {
            codigoBarras += e.key;
        } else {
            codigoBarras = e.key;
        }
        
        ultimoKeyTime = currentTime;
    });

    // Event listener para la tecla Enter en la búsqueda manual
    buscarProductoInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const valor = this.value.trim();

            if (valor.length >= MIN_CHARS) {
                const productos = document.querySelectorAll('.item-view:not(.hidden)');
                if (productos.length === 1) {
                    const producto = productos[0];
                    const productoData = {
                        id: producto.dataset.id,
                        nombre: producto.dataset.nombre,
                        precio: parseFloat(producto.dataset.precio),
                        cantidad: parseInt(producto.dataset.cantidad),
                        codigo: producto.dataset.codigo
                    };
                    agregarProductoAlCarrito(productoData);
                    this.value = '';
                    filtrarProductos('');
                } else if (productos.length === 0) {
                    Swal.fire({
                        title: 'Sin resultados',
                        text: 'No se encontraron productos que coincidan con la búsqueda',
                        icon: 'warning',
                        confirmButtonText: 'Aceptar'
                    });
                }
            }
        }
    });

    // Enfocar el input de búsqueda al cargar
    buscarProductoInput.focus();

    // Función para cerrar turno
    function cerrarTurno(turnoId) {
        Swal.fire({
            title: '¿Cerrar turno actual?',
            text: "Deberás hacer el conteo de caja antes de cerrar el turno",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, cerrar turno',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                procesarCierreTurno(turnoId);
            }
        });
    }

    // Función para procesar el cierre de turno
    function procesarCierreTurno(turnoId) {
        window.location.href = `./controllers/cierre_turno.php?turno_id=${turnoId}`;
    }

    // Hacer la función cerrarTurno global
    window.cerrarTurno = cerrarTurno;

});
