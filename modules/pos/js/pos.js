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
let carrito;

// Definir las funciones en el objeto window para hacerlas globalmente accesibles
window.modificarCantidad = function(id, delta) {
    const item = carrito.items.find(i => i.id === id);
    if (item) {
        const nuevaCantidad = item.cantidad + delta;
        if (nuevaCantidad > 0 && nuevaCantidad <= item.stock) {
            item.cantidad = nuevaCantidad;
            actualizarCarritoUI();
        }
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

    if (carrito.items.length === 0) {
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
        return `
            <tr class="border-b border-gray-100">
                <td class="py-2 px-2">
                    <p class="text-sm font-medium text-gray-900">${item.nombre}</p>
                </td>
                <td class="text-center">
                    <div class="flex items-center justify-center gap-1">
                        <button onclick="window.modificarCantidad(${item.id}, -1)" class="text-gray-500 hover:text-red-500">
                            <i class="fas fa-minus-circle"></i>
                        </button>
                        <span class="text-sm">${item.cantidad}</span>
                        <button onclick="window.modificarCantidad(${item.id}, 1)" class="text-gray-500 hover:text-green-500">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                    </div>
                </td>
                <td class="text-right">
                    <div class="flex items-center justify-end gap-2">
                        <span>$${item.precio.toLocaleString()}</span>
                        <button onclick="window.editarPrecio(${item.id})" class="text-gray-500 hover:text-blue-500">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
                <td class="text-right">$${total.toLocaleString()}</td>
                <td class="text-center">
                    <button onclick="window.eliminarItem(${item.id})" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
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
    // Inicializar carrito
    carrito = {
        items: [],
        cliente: null,
        tipo_documento: 'factura',
        numeracion: 'principal',
        descuento: 0
    };

    // Función para calcular el total
    function calcularTotal() {
        const subtotal = carrito.items.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
        const descuento = (subtotal * carrito.descuento) / 100;
        return subtotal - descuento;
    }

    // Función para agregar producto al carrito
    function agregarAlCarrito(producto) {
        const existente = carrito.items.find(item => item.id === producto.id);
        
        if (producto.stock <= 0) {
            Swal.fire({
                title: 'Producto agotado',
                text: 'Este producto no tiene existencias disponibles',
                icon: 'error'
            });
            return;
        }

        if (existente) {
            if (existente.cantidad >= producto.stock) {
                Swal.fire({
                    title: 'Stock insuficiente',
                    text: 'No hay más unidades disponibles de este producto',
                    icon: 'warning'
                });
                return;
            }
            existente.cantidad++;
        } else {
            carrito.items.push({
                id: producto.id,
                nombre: producto.nombre,
                precio: parseFloat(producto.precio),
                cantidad: 1,
                stock: producto.stock
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

        const tipoDocumento = document.getElementById('tipo-documento').value;
        const clienteId = document.getElementById('cliente-select').value;
        const metodoPago = document.getElementById('metodo-pago').value;
        const total = calcularTotal();

        try {
            // Primero mostrar ventana de pago si es efectivo
            if (metodoPago === 'efectivo') {
                const { isConfirmed, value: pagoData } = await Swal.fire({
                    title: 'Pago en Efectivo',
                    html: `
                        <div class="space-y-4">
                            <div class="flex justify-between text-lg font-bold">
                                <span>Total:</span>
                                <span class="text-indigo-600">$${total.toLocaleString()}</span>
                            </div>
                            <div class="flex justify-between text-lg">
                                <span>Por pagar:</span>
                                <span id="swal-por-pagar" class="text-red-600">$${total.toLocaleString()}</span>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Efectivo recibido</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">$</span>
                                    <input type="number" id="swal-efectivo" class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" 
                                        value="${total}" min="${total}">
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2 mt-4">
                                ${generarBotonesEfectivo(total)}
                            </div>
                            <div class="mt-4 text-lg font-bold flex justify-between">
                                <span>Cambio:</span>
                                <span id="swal-cambio" class="text-green-600">$0</span>
                            </div>
                        </div>
                    `,
                    confirmButtonText: 'Confirmar Pago',
                    showCancelButton: true,
                    cancelButtonText: 'Cancelar',
                    didOpen: () => {
                        const efectivoInput = document.getElementById('swal-efectivo');
                        const cambioSpan = document.getElementById('swal-cambio');
                        const porPagarSpan = document.getElementById('swal-por-pagar');

                        // Manejar cambios en el input
                        efectivoInput.addEventListener('input', () => {
                            const efectivo = parseFloat(efectivoInput.value) || 0;
                            const cambio = Math.max(0, efectivo - total);
                            const porPagar = Math.max(0, total - efectivo);
                            
                            cambioSpan.textContent = `$${cambio.toLocaleString()}`;
                            porPagarSpan.textContent = `$${porPagar.toLocaleString()}`;
                        });

                        // Manejar clicks en botones de efectivo
                        document.querySelectorAll('.btn-efectivo').forEach(btn => {
                            btn.addEventListener('click', () => {
                                efectivoInput.value = btn.dataset.valor;
                                efectivoInput.dispatchEvent(new Event('input'));
                            });
                        });
                    }
                });

                if (!isConfirmed) return;
            } else {
                // Para otros métodos de pago, mostrar confirmación simple
                const { isConfirmed } = await Swal.fire({
                    title: `Pago con ${metodoPago}`,
                    html: `
                        <div class="text-lg font-bold mb-4">
                            <div class="flex justify-between">
                                <span>Total a pagar:</span>
                                <span class="text-indigo-600">$${total.toLocaleString()}</span>
                            </div>
                        </div>
                    `,
                    confirmButtonText: 'Confirmar Pago',
                    showCancelButton: true,
                    cancelButtonText: 'Cancelar'
                });

                if (!isConfirmed) return;
            }

            // Continuar con el proceso de venta normal
            // Si es factura electrónica, primero enviamos a Alegra
            if (tipoDocumento === 'factura_electronica') {
                const cliente = await obtenerDatosCliente(clienteId);
                
                const facturaAlegra = {
                    date: new Date().toISOString().split('T')[0],
                    dueDate: new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0],
                    client: {
                        name: cliente.nombre_completo || '',
                        identification: cliente.identificacion || '',
                        email: cliente.email || '',
                        phonePrimary: cliente.telefono || cliente.celular || '',
                        address: {
                            address: cliente.direccion || '',
                            city: cliente.ciudad || ''
                        }
                    },
                    items: carrito.items.map(item => ({
                        name: item.nombre || '',
                        description: item.descripcion || item.nombre || '',
                        price: parseFloat(item.precio) || 0,
                        quantity: parseInt(item.cantidad) || 0,
                        tax: [{
                            id: parseInt(item.impuesto_id) || 6,
                            name: "IVA",
                            percentage: parseFloat(item.porcentaje_impuesto) || 19
                        }]
                    })),
                    paymentForm: {
                        paymentMethod: convertirMetodoPago(metodoPago),
                        paymentDueDate: new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0]
                    },
                    numberTemplate: {
                        id: 1
                    }
                };

                // Validar que el objeto sea serializable
                try {
                    JSON.stringify(facturaAlegra);
                } catch (e) {
                    console.error('Error al serializar datos:', e);
                    throw new Error('Error al preparar los datos de la factura');
                }

                // Enviar a Alegra
                const responseAlegra = await fetch('api/alegra/facturar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(facturaAlegra)
                });

                if (!responseAlegra.ok) {
                    throw new Error('Error al generar factura electrónica');
                }

                const dataAlegra = await responseAlegra.json();
                
                // Agregamos el ID de Alegra a los datos de la venta
                const ventaData = {
                    items: carrito.items,
                    cliente_id: clienteId,
                    tipo_documento: tipoDocumento,
                    metodo_pago: metodoPago,
                    descuento: carrito.descuento,
                    alegra_id: dataAlegra.id
                };

                // Continuar con el proceso normal de venta
                const response = await fetch('api/ventas/procesar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(ventaData)
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Error en el servidor');
                }

                const data = await response.json();

                if (data.success) {
                    // Preguntar por la remisión solo si es una venta (no cotización)
                    if (tipoDocumento !== 'cotizacion') {
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
                            // Imprimir remisión
                            window.open(`controllers/imprimir_remision.php?id=${data.venta_id}`, '_blank');
                        }

                        // Después de la remisión o si no se quiso remisión, preguntar por el ticket
                        const { isConfirmed: imprimirTicket } = await Swal.fire({
                            title: 'Imprimir ticket',
                            text: '¿Desea imprimir el ticket de venta?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, imprimir',
                            cancelButtonText: 'No, finalizar',
                            confirmButtonColor: '#4F46E5',
                            cancelButtonColor: '#9CA3AF'
                        });

                        if (imprimirTicket) {
                            window.open(`controllers/imprimir_factura.php?id=${data.venta_id}`, '_blank');
                        }
                    } else {
                        // Si es cotización, mantener el comportamiento actual
                        let imprimirUrl = `controllers/imprimir_cotizacion.php?id=${data.cotizacion_id}`;
                        window.open(imprimirUrl, '_blank');
                    }

                    // Reiniciar carrito
                    carrito.items = [];
                    actualizarCarritoUI();
                    // Recargar productos
                    location.reload();
                } else {
                    throw new Error(data.message);
                }
            } else {
                // Proceso normal para otros tipos de documentos
                const endpoint = tipoDocumento === 'cotizacion' ? 'api/cotizaciones/procesar.php' : 'api/ventas/procesar.php';

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        items: carrito.items,
                        cliente_id: clienteId,
                        tipo_documento: tipoDocumento,
                        numeracion: document.getElementById('numeracion').value,
                        metodo_pago: metodoPago,
                        descuento: carrito.descuento
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Error en el servidor');
                }

                const data = await response.json();

                if (data.success) {
                    // Preguntar por la remisión solo si es una venta (no cotización)
                    if (tipoDocumento !== 'cotizacion') {
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
                            // Imprimir remisión
                            window.open(`controllers/imprimir_remision.php?id=${data.venta_id}`, '_blank');
                        }

                        // Después de la remisión o si no se quiso remisión, preguntar por el ticket
                        const { isConfirmed: imprimirTicket } = await Swal.fire({
                            title: 'Imprimir ticket',
                            text: '¿Desea imprimir el ticket de venta?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, imprimir',
                            cancelButtonText: 'No, finalizar',
                            confirmButtonColor: '#4F46E5',
                            cancelButtonColor: '#9CA3AF'
                        });

                        if (imprimirTicket) {
                            window.open(`controllers/imprimir_factura.php?id=${data.venta_id}`, '_blank');
                        }
                    } else {
                        // Si es cotización, mantener el comportamiento actual
                        let imprimirUrl = `controllers/imprimir_cotizacion.php?id=${data.cotizacion_id}`;
                        window.open(imprimirUrl, '_blank');
                    }

                    // Reiniciar carrito
                    carrito.items = [];
                    actualizarCarritoUI();
                    // Recargar productos
                    location.reload();
                } else {
                    throw new Error(data.message);
                }
            }
        } catch (error) {
            console.error('Error completo:', error);
            Swal.fire({
                title: 'Error',
                text: error.message || 'Error al procesar la operación',
                icon: 'error'
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

    document.getElementById('tipo-documento').addEventListener('change', function() {
        const metodoPago = document.getElementById('metodo-pago');
        const btnProcesar = document.getElementById('procesar-venta');
        
        if (this.value === 'cotizacion') {
            metodoPago.disabled = true;
            btnProcesar.textContent = 'Generar Cotización';
        } else {
            metodoPago.disabled = false;
            btnProcesar.textContent = 'Confirmar Venta';
        }
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

    // Evento para leer código de barras
    let codigoBarras = '';
    let ultimoKeyTime = 0;
    const DELAY_ENTRE_TECLAS = 50; // Tiempo máximo entre teclas para considerar lectura de código de barras

    document.addEventListener('keypress', function(e) {
        const tiempoActual = new Date().getTime();
        
        // Si el foco está en un input, no procesar como código de barras
        if (document.activeElement.tagName === 'INPUT') return;

        // Verificar si es una entrada rápida (típica de un scanner)
        if (tiempoActual - ultimoKeyTime <= DELAY_ENTRE_TECLAS) {
            codigoBarras += e.key;
        } else {
            codigoBarras = e.key;
        }
        
        ultimoKeyTime = tiempoActual;

        // Procesar el código de barras cuando se presiona Enter
        if (e.key === 'Enter' && codigoBarras.length > 1) {
            // Eliminar el Enter del final del código
            codigoBarras = codigoBarras.slice(0, -1);
            
            // Establecer el código en el campo de búsqueda y realizar la búsqueda
            const buscarInput = document.getElementById('buscar-producto');
            buscarInput.value = codigoBarras;
            filtrarProductos(codigoBarras);
            
            // Limpiar el código almacenado
            codigoBarras = '';
        }
    });
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
            facturaId: window.ultimaFacturaId // Asegúrate de tener esta variable disponible
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