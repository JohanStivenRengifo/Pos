// Funciones auxiliares globales
function convertirMetodoPago(metodoPago) {
    const conversion = {
        'efectivo': 'CASH',
        'tarjeta': 'CREDIT_CARD',
        'transferencia': 'BANK_TRANSFER'
    };
    return conversion[metodoPago] || 'CASH';
}

async function obtenerDatosCliente(clienteId) {
    const response = await fetch(`api/clientes/obtener.php?id=${clienteId}`);
    if (!response.ok) {
        throw new Error('Error al obtener datos del cliente');
    }
    return await response.json();
}

function generarBotonesEfectivo(total) {
    const denominaciones = [
        1000, 2000, 5000, 10000, 20000, 50000, 100000
    ];

    const denominacionesRelevantes = denominaciones
        .filter(d => d >= total)
        .slice(0, 4);

    if (denominacionesRelevantes.length === 0) {
        denominacionesRelevantes.push(Math.ceil(total/1000)*1000);
    }

    return denominacionesRelevantes
        .map(valor => `
            <button type="button"
                class="btn-efectivo px-3 py-2 border rounded-lg hover:bg-gray-50 text-sm"
                data-valor="${valor}">
                $${valor.toLocaleString()}
            </button>
        `)
        .join('');
}

// Variables y funciones globales
let carrito = {
    items: [],
    descuento: 0
};

// Definir las funciones en el objeto window para hacerlas globalmente accesibles
window.modificarCantidad = function(id, delta) {
    const item = carrito.items.find(i => i.id === id);
    if (item) {
        const nuevaCantidad = item.cantidad + delta;
        
        // Verificar límites de stock
        if (nuevaCantidad <= 0) {
            // Si la cantidad llega a 0, eliminar el item
            carrito.items = carrito.items.filter(i => i.id !== id);
        } else if (nuevaCantidad <= item.stock) {
            // Solo actualizar si no excede el stock
            item.cantidad = nuevaCantidad;
        } else {
            // Mostrar mensaje de error si excede el stock
            Swal.fire({
                title: 'Stock insuficiente',
                text: `Solo hay ${item.stock} unidades disponibles`,
                icon: 'warning',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }
        
        actualizarCarritoUI();
    }
};

window.eliminarItem = function(id) {
    carrito.items = carrito.items.filter(i => i.id !== id);
    actualizarCarritoUI();
};

window.editarPrecio = function(id) {
    const item = carrito.items.find(i => i.id === id);
    if (item) {
        Swal.fire({
            title: 'Editar precio',
            html: `
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nuevo precio para ${item.nombre}
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                        <input type="number" 
                            id="nuevo-precio" 
                            class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" 
                            value="${item.precio}"
                            min="0"
                            step="100">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Actualizar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#4F46E5',
            preConfirm: () => {
                const nuevoPrecio = parseFloat(document.getElementById('nuevo-precio').value);
                if (isNaN(nuevoPrecio) || nuevoPrecio < 0) {
                    Swal.showValidationMessage('Por favor ingrese un precio válido');
                    return false;
                }
                return nuevoPrecio;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                item.precio = result.value;
                actualizarCarritoUI();
            }
        });
    }
};

// Función global para actualizar la UI del carrito
function actualizarCarritoUI() {
    const listaCarrito = document.getElementById('venta-lista');
    const listaVacia = document.getElementById('venta-lista-empty');
    const cantidadItems = document.getElementById('cantidad-items');
    const subtotalElement = document.getElementById('subtotal');
    const descuentoMontoElement = document.getElementById('descuento-monto');
    const totalElement = document.getElementById('venta-total');
    const btnProcesar = document.getElementById('procesar-venta');

    // Verificar stock antes de actualizar UI
    carrito.items.forEach(item => {
        if (item.cantidad > item.stock) {
            item.cantidad = item.stock;
        }
    });

    if (!carrito.items.length) {
        listaCarrito.innerHTML = '';
        listaVacia.style.display = 'block';
        btnProcesar.disabled = true;
        cantidadItems.textContent = '0';
        return;
    }

    listaVacia.style.display = 'none';
    btnProcesar.disabled = false;
    
    let subtotal = 0;
    listaCarrito.innerHTML = carrito.items.map(item => {
        const total = item.precio * item.cantidad;
        subtotal += total;

        // Determinar el estado del stock
        const stockBajo = item.cantidad >= item.stock;
        const stockClass = stockBajo ? 'text-red-500' : 'text-gray-400';
        const stockText = stockBajo ? 'Stock máximo alcanzado' : `Stock: ${item.stock}`;

        return `
            <tr class="border-b border-gray-100">
                <td class="py-2 px-2">
                    <p class="text-sm font-medium text-gray-900">${item.nombre}</p>
                </td>
                <td class="text-center">
                    <div class="flex items-center justify-center gap-1">
                        <button onclick="window.modificarCantidad(${item.id}, -1)" 
                            class="text-gray-500 hover:text-red-500 p-1 rounded-full hover:bg-red-50 transition-colors">
                            <i class="fas fa-minus"></i>
                        </button>
                        <span class="text-sm font-medium min-w-[1.5rem] text-center">${item.cantidad}</span>
                        <button onclick="window.modificarCantidad(${item.id}, 1)" 
                            class="text-gray-500 hover:text-green-500 p-1 rounded-full hover:bg-green-50 transition-colors ${item.cantidad >= item.stock ? 'opacity-50 cursor-not-allowed' : ''}"
                            ${item.cantidad >= item.stock ? 'disabled' : ''}>
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="text-xs ${stockClass} mt-1">
                        ${stockText}
                    </div>
                </td>
                <td class="text-right">
                    <span class="text-sm text-gray-600">$${item.precio.toLocaleString()}</span>
                </td>
                <td class="text-right">
                    <span class="text-sm font-medium text-gray-900">$${total.toLocaleString()}</span>
                </td>
                <td class="text-center">
                    <button onclick="window.eliminarItem(${item.id})" 
                        class="text-red-500 hover:text-red-700 p-1 rounded-full hover:bg-red-50 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    cantidadItems.textContent = carrito.items.length;
    
    const descuento = (subtotal * carrito.descuento) / 100;
    const total = subtotal - descuento;

    subtotalElement.textContent = `$${subtotal.toLocaleString()}`;
    descuentoMontoElement.textContent = `-$${descuento.toLocaleString()}`;
    totalElement.textContent = `$${total.toLocaleString()}`;
}

// Función para filtrar productos
function filtrarProductos(busqueda) {
    const productos = document.querySelectorAll('.item-view');
    const busquedaLower = busqueda.toLowerCase().trim();

    productos.forEach(producto => {
        const nombre = producto.getAttribute('data-nombre').toLowerCase();
        const codigo = producto.getAttribute('data-codigo').toLowerCase();
        
        // Mostrar producto si coincide con el nombre o código de barras
        if (nombre.includes(busquedaLower) || codigo.includes(busquedaLower)) {
            producto.style.display = '';
        } else {
            producto.style.display = 'none';
        }
    });

    // Mostrar mensaje si no hay resultados
    const productosVisibles = document.querySelectorAll('.item-view[style="display: "]').length;
    const gridContainer = document.querySelector('#products-grid .grid');
    let mensajeNoResultados = document.getElementById('no-resultados');

    if (productosVisibles === 0) {
        if (!mensajeNoResultados) {
            mensajeNoResultados = document.createElement('div');
            mensajeNoResultados.id = 'no-resultados';
            mensajeNoResultados.className = 'col-span-full flex flex-col items-center justify-center py-12 text-gray-400';
            mensajeNoResultados.innerHTML = `
                <i class="fas fa-search text-4xl mb-4"></i>
                <p class="text-lg font-medium">No se encontraron productos</p>
                <p class="text-sm text-gray-400 mt-2">Intenta con otra búsqueda</p>
            `;
            gridContainer.appendChild(mensajeNoResultados);
        }
    } else if (mensajeNoResultados) {
        mensajeNoResultados.remove();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Función para calcular el total
    function calcularTotal() {
        const subtotal = carrito.items.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        const descuento = (subtotal * carrito.descuento) / 100;
        return subtotal - descuento;
    }

    // Función para agregar producto al carrito
    function agregarAlCarrito(producto) {
        // Verificar stock antes de cualquier operación
        if (!producto.stock || producto.stock <= 0) {
            Swal.fire({
                title: 'Producto agotado',
                text: 'Este producto no tiene existencias disponibles',
                icon: 'error',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        const existente = carrito.items.find(item => item.id === parseInt(producto.id));
        
        if (existente) {
            // Verificar que la nueva cantidad no exceda el stock
            const nuevaCantidad = existente.cantidad + 1;
            if (nuevaCantidad > producto.stock) {
                Swal.fire({
                    title: 'Stock insuficiente',
                    text: `Solo hay ${producto.stock} unidades disponibles`,
                    icon: 'warning',
                    timer: 1500,
                    showConfirmButton: false
                });
                return;
            }
            existente.cantidad = nuevaCantidad;
        } else {
            carrito.items.push({
                id: parseInt(producto.id),
                nombre: producto.nombre,
                precio: parseFloat(producto.precio),
                cantidad: 1,
                stock: parseInt(producto.stock)
            });
        }

        // Verificación final de stock
        const itemActual = carrito.items.find(item => item.id === parseInt(producto.id));
        if (itemActual && itemActual.cantidad > itemActual.stock) {
            itemActual.cantidad = itemActual.stock;
            Swal.fire({
                title: 'Ajuste de stock',
                text: `La cantidad se ha ajustado al máximo disponible: ${itemActual.stock} unidades`,
                icon: 'info',
                timer: 1500,
                showConfirmButton: false
            });
        }

        actualizarCarritoUI();
    }

    // Función para procesar la venta o cotización
    async function procesarVenta() {
        if (!carrito.items.length || !document.getElementById('cliente-select').value) {
            Swal.fire({
                title: 'Carrito vacío o cliente no seleccionado',
                text: 'Agregue productos al carrito y seleccione un cliente antes de continuar',
                icon: 'warning'
            });
            return;
        }

        // Verificar stock antes de procesar
        let stockExcedido = false;
        let mensajeError = '';

        for (const item of carrito.items) {
            if (item.cantidad > item.stock) {
                stockExcedido = true;
                mensajeError += `${item.nombre}: ${item.cantidad} (pedido) > ${item.stock} (disponible)\n`;
            }
        }

        if (stockExcedido) {
            Swal.fire({
                title: 'Stock insuficiente',
                text: 'Algunos productos exceden el stock disponible:\n' + mensajeError,
                icon: 'error'
            });
            return;
        }

        const tipoDocumento = document.getElementById('tipo-documento').value;
        const clienteId = document.getElementById('cliente-select').value;
        const metodoPago = document.getElementById('metodo-pago').value;
        const numeracion = document.getElementById('numeracion').value;
        const descuentoPorcentaje = parseInt(document.getElementById('descuento').value) || 0;

        // Calcular totales
        let subtotal = 0;
        carrito.items.forEach(item => {
            subtotal += item.precio * item.cantidad;
        });
        
        const descuentoMonto = (subtotal * descuentoPorcentaje) / 100;
        const total = subtotal - descuentoMonto;

        try {
            const response = await fetch('api/ventas/procesar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    items: carrito.items,
                    cliente_id: clienteId,
                    tipo_documento: tipoDocumento,
                    numeracion: numeracion,
                    metodo_pago: metodoPago,
                    descuento: descuentoPorcentaje,
                    subtotal: subtotal,
                    total: total
                })
            });

            const data = await response.json();
            
            if (data.success) {
                // Primero mostrar el mensaje de éxito
                await Swal.fire({
                    icon: 'success',
                    title: '¡Venta realizada!',
                    text: 'La venta se ha procesado correctamente',
                    confirmButtonText: 'Continuar'
                });

                // Preguntar por la remisión
                const { isConfirmed: imprimirRemision } = await Swal.fire({
                    title: '¿Generar remisión?',
                    text: 'La remisión es el documento para despacho de bodega',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, generar remisión',
                    cancelButtonText: 'No, solo ticket',
                    confirmButtonColor: '#4F46E5',
                    cancelButtonColor: '#9CA3AF'
                });

                if (imprimirRemision) {
                    window.open(`controllers/imprimir_remision.php?id=${data.venta_id}`, '_blank');
                }

                // Preguntar por la impresión del ticket
                const { isConfirmed: imprimirTicket } = await Swal.fire({
                    title: 'Imprimir ticket',
                    text: '¿Desea imprimir el ticket de venta?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, imprimir',
                    cancelButtonText: 'No, finalizar',
                    confirmButtonColor: '#4F46E5',
                    cancelButtonColor: '#6B7280'
                });

                if (imprimirTicket) {
                    window.open(`controllers/imprimir_factura.php?id=${data.venta_id}`, '_blank');
                }

                // Limpiar carrito y recargar
                carrito.items = [];
                actualizarCarritoUI();
                location.reload();
            } else {
                throw new Error(data.message || 'Error al procesar la venta');
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
        }
    }

    // Event Listeners
    document.querySelectorAll('.item-view').forEach(item => {
        item.addEventListener('click', function() {
            const producto = {
                id: this.dataset.id,
                nombre: this.dataset.nombre,
                precio: parseFloat(this.dataset.precio),
                stock: parseInt(this.dataset.cantidad)
            };
            agregarAlCarrito(producto);
        });
    });

    document.getElementById('descuento').addEventListener('change', function() {
        carrito.descuento = parseFloat(this.value) || 0;
        actualizarCarritoUI();
    });

    document.getElementById('procesar-venta').addEventListener('click', procesarVenta);

    // Evento para el campo de búsqueda
    const buscarInput = document.getElementById('buscar-producto');
    
    if (buscarInput) {
        // Búsqueda al escribir
        buscarInput.addEventListener('input', function(e) {
            filtrarProductos(e.target.value);
        });

        // Búsqueda al presionar Enter
        buscarInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                filtrarProductos(e.target.value);
            }
        });

        // Limpiar búsqueda cuando el campo esté vacío
        buscarInput.addEventListener('change', function(e) {
            if (e.target.value === '') {
                filtrarProductos('');
            }
        });
    }

    // Enfoque automático en el campo de búsqueda al cargar la página
    buscarInput?.focus();

    // Configuración del scanner de código de barras
    const SCANNER_CONFIG = {
        DELAY_BETWEEN_KEYS: 30, // Tiempo máximo entre teclas para considerar entrada del scanner (ms)
        MIN_CHARS: 3, // Longitud mínima del código de barras
        ENTER_KEY: 'Enter', // Tecla que indica fin de escaneo
        TIMEOUT: 100 // Tiempo de espera después del último carácter antes de procesar
    };

    let scannerBuffer = '';
    let lastKeyTime = 0;
    let scannerTimeout = null;

    // Función para procesar código de barras
    function procesarCodigoBarras(codigo) {
        const productos = document.querySelectorAll('.item-view');
        const producto = Array.from(productos).find(p => p.dataset.codigo === codigo);

        if (producto) {
            const productoData = {
                id: producto.dataset.id,
                nombre: producto.dataset.nombre,
                precio: parseFloat(producto.dataset.precio),
                stock: parseInt(producto.dataset.cantidad),
                codigo: producto.dataset.codigo
            };

            // Verificar stock antes de agregar
            if (productoData.stock <= 0) {
                reproducirBeep(false);
                Swal.fire({
                    title: 'Producto sin stock',
                    text: 'Este producto no tiene existencias disponibles',
                    icon: 'warning',
                    timer: 1500,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true
                });
                return;
            }

            // Verificar si ya existe en el carrito y validar stock
            const existente = carrito.items.find(item => item.id === parseInt(productoData.id));
            if (existente) {
                if (existente.cantidad >= productoData.stock) {
                    reproducirBeep(false);
                    Swal.fire({
                        title: 'Stock insuficiente',
                        text: `Solo hay ${productoData.stock} unidades disponibles`,
                        icon: 'warning',
                        timer: 1500,
                        showConfirmButton: false,
                        position: 'top-end',
                        toast: true
                    });
                    return;
                }
            }

            agregarAlCarrito(productoData);
            reproducirBeep(true);
            // Limpiar el campo de búsqueda
            const buscarInput = document.getElementById('buscar-producto');
            if (buscarInput) {
                buscarInput.value = '';
                buscarInput.focus();
            }
        } else {
            reproducirBeep(false);
            Swal.fire({
                title: 'Producto no encontrado',
                text: 'No se encontró ningún producto con ese código de barras',
                icon: 'warning',
                timer: 1500,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
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

    // Event listener para el scanner
    document.addEventListener('keydown', function(e) {
        const buscarInput = document.getElementById('buscar-producto');
        const currentTime = new Date().getTime();
        
        // Si el foco está en un input que no es el de búsqueda, ignorar
        if (e.target.tagName === 'INPUT' && e.target !== buscarInput) {
            return;
        }

        // Si no está en el input de búsqueda, poner el foco
        if (e.target !== buscarInput) {
            buscarInput.focus();
        }

        // Procesar entrada del scanner
        if (currentTime - lastKeyTime <= SCANNER_CONFIG.DELAY_BETWEEN_KEYS || scannerBuffer.length > 0) {
            // Prevenir el comportamiento por defecto solo si parece ser entrada del scanner
            e.preventDefault();

            if (e.key === SCANNER_CONFIG.ENTER_KEY) {
                if (scannerBuffer.length >= SCANNER_CONFIG.MIN_CHARS) {
                    procesarCodigoBarras(scannerBuffer);
                }
                scannerBuffer = '';
                clearTimeout(scannerTimeout);
            } else if (e.key.length === 1) { // Solo agregar caracteres imprimibles
                scannerBuffer += e.key;
                clearTimeout(scannerTimeout);
                scannerTimeout = setTimeout(() => {
                    if (scannerBuffer.length >= SCANNER_CONFIG.MIN_CHARS) {
                        procesarCodigoBarras(scannerBuffer);
                    }
                    scannerBuffer = '';
                }, SCANNER_CONFIG.TIMEOUT);
            }
        }
        
        lastKeyTime = currentTime;
    });

    // Event listener para búsqueda manual
    const buscarInputManual = document.getElementById('buscar-producto');
    if (buscarInputManual) {
        let timeoutId;

        buscarInputManual.addEventListener('input', function(e) {
            const currentTime = new Date().getTime();
            
            // Si parece ser entrada del scanner (rápida), no filtrar aún
            if (currentTime - lastKeyTime <= SCANNER_CONFIG.DELAY_BETWEEN_KEYS) {
                return;
            }

            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                filtrarProductos(this.value);
            }, 300);
        });

        buscarInputManual.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const valor = this.value.trim();

                if (valor.length >= SCANNER_CONFIG.MIN_CHARS) {
                    const productos = document.querySelectorAll('.item-view:not([style*="display: none"])');
                    if (productos.length === 1) {
                        const producto = productos[0];
                        const productoData = {
                            id: producto.dataset.id,
                            nombre: producto.dataset.nombre,
                            precio: parseFloat(producto.dataset.precio),
                            cantidad: parseInt(producto.dataset.cantidad),
                            codigo: producto.dataset.codigo
                        };
                        agregarAlCarrito(productoData);
                        this.value = '';
                        filtrarProductos('');
                    }
                }
            }
        });
    }
});

function enviarFacturaPorCorreo(email) {
    // Mostrar indicador de carga
    Swal.fire({
        title: 'Enviando factura...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Realizar la petición
    fetch('api/alegra/enviar_factura_email.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email: email,
            facturaId: window.ultimaFacturaId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Correo enviado!',
                text: 'La factura ha sido enviada correctamente',
                confirmButtonColor: '#4F46E5'
            });
        } else {
            throw new Error(data.error || 'Error al enviar el correo');
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message,
            confirmButtonColor: '#EF4444'
        });
    });
} 