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

    // Inicialización del nuevo scanner de códigos de barras optimizado
    let barcodeScanner = null;

    // Función optimizada para procesar código de barras
    function procesarCodigoBarras(codigo, isManual = false) {
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
                return false; // Indicar fallo para el scanner
            }

            // Verificar si ya existe en el carrito y validar stock
            const existente = carrito.items.find(item => item.id === parseInt(productoData.id));
            if (existente) {
                if (existente.cantidad >= productoData.stock) {
                    return false; // Indicar fallo para el scanner
                }
            }

            agregarAlCarrito(productoData);
            
            // Limpiar el campo de búsqueda
            const buscarInput = document.getElementById('buscar-producto');
            if (buscarInput) {
                buscarInput.value = '';
                buscarInput.focus();
            }
            
            return true; // Indicar éxito
        }
        
        return false; // Producto no encontrado
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

    // Inicializar el nuevo scanner optimizado
    document.addEventListener('DOMContentLoaded', function() {
        // Esperar a que el BarcodeScanner esté disponible
        if (typeof BarcodeScanner !== 'undefined') {
            barcodeScanner = new BarcodeScanner({
                onSuccess: (codigo, isManual) => {
                    return procesarCodigoBarras(codigo, isManual);
                },
                onError: (message, details) => {
                    console.warn('Scanner error:', message, details);
                    if (message.includes('no encontrado')) {
                        Swal.fire({
                            title: 'Producto no encontrado',
                            text: `No se encontró ningún producto con el código: ${details}`,
                            icon: 'warning',
                            timer: 2000,
                            showConfirmButton: false,
                            position: 'top-end',
                            toast: true
                        });
                    } else if (message.includes('Stock')) {
                        Swal.fire({
                            title: 'Stock insuficiente',
                            text: details || 'No hay suficientes existencias',
                            icon: 'warning',
                            timer: 2000,
                            showConfirmButton: false,
                            position: 'top-end',
                            toast: true
                        });
                    }
                },
                onDetection: (type, config) => {
                    console.log('Scanner detectado:', type, config.name);
                }
            });
            
            console.log('Scanner optimizado inicializado correctamente');
            
            // Configurar botón de diagnósticos
            const diagnosticsBtn = document.getElementById('scanner-diagnostics-btn');
            if (diagnosticsBtn) {
                diagnosticsBtn.addEventListener('click', mostrarDiagnosticos);
            }
        } else {
            console.warn('BarcodeScanner no está disponible. Asegúrate de incluir BarcodeScanner.js');
        }
    });

    // Función para mostrar diagnósticos del scanner
    function mostrarDiagnosticos() {
        if (!barcodeScanner) {
            Swal.fire({
                title: 'Scanner no disponible',
                text: 'El sistema de scanner no está inicializado',
                icon: 'error'
            });
            return;
        }

        const stats = barcodeScanner.getStats();
        
        Swal.fire({
            title: 'Diagnósticos del Scanner',
            html: `
                <div class="text-left space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-blue-50 p-3 rounded">
                            <h4 class="font-semibold text-blue-800">Tipo Detectado</h4>
                            <p class="text-sm text-blue-600">${stats.currentType}</p>
                        </div>
                        <div class="bg-green-50 p-3 rounded">
                            <h4 class="font-semibold text-green-800">Velocidad Promedio</h4>
                            <p class="text-sm text-green-600">${stats.averageSpeed}ms entre teclas</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="bg-gray-50 p-2 rounded">
                            <div class="text-lg font-bold text-gray-800">${stats.scanCount}</div>
                            <div class="text-xs text-gray-600">Total Escaneos</div>
                        </div>
                        <div class="bg-green-50 p-2 rounded">
                            <div class="text-lg font-bold text-green-800">${stats.successCount}</div>
                            <div class="text-xs text-green-600">Exitosos</div>
                        </div>
                        <div class="bg-red-50 p-2 rounded">
                            <div class="text-lg font-bold text-red-800">${stats.errorCount}</div>
                            <div class="text-xs text-red-600">Errores</div>
                        </div>
                    </div>
                    
                    <div class="bg-indigo-50 p-3 rounded">
                        <h4 class="font-semibold text-indigo-800">Tasa de Éxito</h4>
                        <div class="flex items-center">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full" style="width: ${stats.successRate}%"></div>
                            </div>
                            <span class="ml-2 text-sm text-indigo-600">${stats.successRate}%</span>
                        </div>
                    </div>
                    
                    <div class="text-xs text-gray-500 border-t pt-2">
                        <p><strong>Consejos:</strong></p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Para mejores resultados, use el scanner a una distancia de 5-15cm</li>
                            <li>Asegúrese de que el código esté bien iluminado y sin arrugas</li>
                            <li>El sistema detecta automáticamente el tipo de scanner</li>
                        </ul>
                    </div>
                </div>
            `,
            width: 500,
            showCancelButton: true,
            confirmButtonText: 'Probar Scanner',
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#4F46E5'
        }).then((result) => {
            if (result.isConfirmed) {
                probarScanner();
            }
        });
    }

    // Función para probar el scanner
    function probarScanner() {
        Swal.fire({
            title: 'Modo de Prueba',
            html: `
                <div class="text-left">
                    <p class="mb-4">Escanee un código de barras o ingrese manualmente un código para probar:</p>
                    <input type="text" 
                        id="test-barcode-input" 
                        class="w-full p-2 border rounded" 
                        placeholder="Escanee aquí o escriba un código..."
                        autofocus>
                    <div id="test-feedback" class="mt-2 text-sm"></div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Cerrar',
            cancelButtonText: 'Volver',
            allowOutsideClick: false,
            didOpen: () => {
                const testInput = document.getElementById('test-barcode-input');
                const feedback = document.getElementById('test-feedback');
                
                if (testInput && barcodeScanner) {
                    // Configurar scanner temporal para el modo de prueba
                    const originalConfig = { ...barcodeScanner.config };
                    
                    barcodeScanner.configure({
                        inputElement: testInput,
                        onSuccess: (codigo, isManual) => {
                            feedback.innerHTML = `
                                <div class="p-2 bg-green-100 border border-green-300 rounded text-green-800">
                                    ✅ Código detectado: <strong>${codigo}</strong> 
                                    <br><small>(${isManual ? 'Entrada manual' : 'Escaneado automáticamente'})</small>
                                </div>
                            `;
                            testInput.value = '';
                            return false; // No procesar realmente
                        },
                        onError: (message, details) => {
                            feedback.innerHTML = `
                                <div class="p-2 bg-red-100 border border-red-300 rounded text-red-800">
                                    ❌ Error: ${message}
                                    ${details ? `<br><small>${details}</small>` : ''}
                                </div>
                            `;
                        }
                    });
                    
                    // Restaurar configuración al cerrar
                    const restoreConfig = () => {
                        barcodeScanner.configure(originalConfig);
                    };
                    
                    setTimeout(() => {
                        const modal = document.querySelector('.swal2-container');
                        if (modal) {
                            modal.addEventListener('click', (e) => {
                                if (e.target.classList.contains('swal2-confirm') || 
                                    e.target.classList.contains('swal2-cancel')) {
                                    restoreConfig();
                                }
                            });
                        }
                    }, 100);
                }
            }
        });
    }

    // Event listener para búsqueda manual mejorada (manejada por el scanner optimizado)
    const buscarInputManual = document.getElementById('buscar-producto');
    if (buscarInputManual) {
        // Solo mantener el filtro en tiempo real para la visualización
        let timeoutId;
        buscarInputManual.addEventListener('input', function(e) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                filtrarProductos(this.value);
            }, 300);
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