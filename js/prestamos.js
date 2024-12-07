// Variables globales
let prestamos = [];
let productoSeleccionado = null;
let productos = [];

// Función para verificar si estamos en una página específica
function isPage(pageName) {
    return window.location.pathname.includes(pageName);
}

// Inicializar la página
document.addEventListener('DOMContentLoaded', () => {
    if (isPage('index.php')) {
        cargarPrestamos();
    }
    
    if (isPage('crear.php')) {
        // Event listeners para la página de crear
        document.getElementById('producto_busqueda').addEventListener('input', (e) => {
            buscarProductos(e.target.value);
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#sugerencias_productos') && !e.target.closest('#producto_busqueda')) {
                document.getElementById('sugerencias_productos').classList.add('hidden');
            }
        });

        // Validación del formulario
        document.getElementById('prestamoForm').addEventListener('submit', function(e) {
            if (productos.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos un producto al préstamo');
            }
        });

        // Inicializar foto del cliente si hay uno seleccionado
        const clienteSelect = document.getElementById('cliente_id');
        if (clienteSelect && clienteSelect.value) {
            mostrarFotoCliente(clienteSelect.value);
        }
    }
});

// Cargar préstamos existentes
async function cargarPrestamos() {
    try {
        const response = await fetch('../../ajax/prestamos/obtener_prestamos.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Error al cargar préstamos');
        }
        
        prestamos = result.data;
        const tabla = document.getElementById('tablaPrestamos');
        if (!tabla) return; // Si no existe la tabla, salimos de la función

        tabla.innerHTML = '';
        
        if (prestamos.length === 0) {
            tabla.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                        No hay préstamos registrados
                    </td>
                </tr>
            `;
            return;
        }
        
        prestamos.forEach(prestamo => {
            tabla.innerHTML += `
                <tr>
                    <td class="px-6 py-4">${prestamo.id}</td>
                    <td class="px-6 py-4">${prestamo.cliente_nombre}</td>
                    <td class="px-6 py-4">${prestamo.fecha_prestamo}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-sm ${getEstadoClass(prestamo.estado)}">
                            ${prestamo.estado}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <button onclick="verDetallePrestamo(${prestamo.id})" class="text-blue-500 hover:text-blue-700 mr-2">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${prestamo.estado === 'activo' ? `
                            <button onclick="devolverPrestamo(${prestamo.id})" class="text-green-500 hover:text-green-700">
                                <i class="fas fa-undo"></i>
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
    } catch (error) {
        console.error('Error al cargar préstamos:', error);
        const tabla = document.getElementById('tablaPrestamos');
        if (tabla) {
            tabla.innerHTML = `
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-red-500">
                        Error al cargar la lista de préstamos: ${error.message}
                    </td>
                </tr>
            `;
        }
    }
}

// Función para buscar productos
async function buscarProductos(busqueda) {
    if (busqueda.length < 2) {
        document.getElementById('sugerencias_productos').classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`../../ajax/prestamos/buscar_productos.php?q=${encodeURIComponent(busqueda)}`);
        const data = await response.json();
        
        const sugerencias = document.getElementById('sugerencias_productos');
        sugerencias.innerHTML = '';
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(producto => {
                const div = document.createElement('div');
                div.className = 'cursor-pointer p-2 hover:bg-gray-100';
                div.innerHTML = `
                    <div class="flex justify-between">
                        <span>${producto.nombre}</span>
                        <span class="text-gray-600">Stock: ${producto.stock}</span>
                    </div>
                    <div class="text-sm text-gray-500">Código: ${producto.codigo_barras}</div>
                `;
                div.onclick = () => seleccionarProducto(producto);
                sugerencias.appendChild(div);
            });
            
            sugerencias.classList.remove('hidden');
        } else {
            sugerencias.innerHTML = '<div class="p-2 text-gray-500">No se encontraron productos</div>';
            sugerencias.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Función auxiliar para obtener la clase CSS según el estado
function getEstadoClass(estado) {
    switch (estado) {
        case 'activo':
            return 'bg-blue-100 text-blue-800';
        case 'devuelto':
            return 'bg-green-100 text-green-800';
        case 'parcial':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Ver detalle del préstamo
async function verDetallePrestamo(prestamoId) {
    const prestamo = prestamos.find(p => p.id === prestamoId);
    if (!prestamo) return;
    
    // Crear modal de detalle
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Detalle del Préstamo #${prestamo.id}</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-600">Cliente:</p>
                        <p class="font-medium">${prestamo.cliente_nombre}</p>
                    </div>
                    <div>
                        <p class="text-gray-600">Identificación:</p>
                        <p class="font-medium">${prestamo.cliente_identificacion || 'No disponible'}</p>
                    </div>
                    <div>
                        <p class="text-gray-600">Fecha:</p>
                        <p class="font-medium">${prestamo.fecha_prestamo}</p>
                    </div>
                    <div>
                        <p class="text-gray-600">Estado:</p>
                        <span class="px-2 py-1 rounded text-sm ${getEstadoClass(prestamo.estado)}">
                            ${prestamo.estado}
                        </span>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h4 class="font-bold mb-2">Productos:</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Código</th>
                                    <th class="px-4 py-2 text-left">Producto</th>
                                    <th class="px-4 py-2 text-left">Cantidad</th>
                                    <th class="px-4 py-2 text-left">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                ${prestamo.productos.map(p => `
                                    <tr>
                                        <td class="px-4 py-2">${p.codigo_barras || 'N/A'}</td>
                                        <td class="px-4 py-2">${p.producto_nombre}</td>
                                        <td class="px-4 py-2">${p.cantidad}</td>
                                        <td class="px-4 py-2">
                                            <span class="px-2 py-1 rounded text-sm ${getEstadoProductoClass(p.estado)}">
                                                ${p.estado}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Devolver préstamo
async function devolverPrestamo(prestamoId) {
    const prestamo = prestamos.find(p => p.id === prestamoId);
    if (!prestamo) return;

    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Devolución de Préstamo #${prestamo.id}</h3>
                <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="formDevolucion" class="space-y-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Producto</th>
                                <th class="px-4 py-2 text-left">Cantidad</th>
                                <th class="px-4 py-2 text-left">Estado</th>
                                <th class="px-4 py-2 text-left">Precio Venta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            ${prestamo.productos.filter(p => p.estado === 'prestado').map((p) => `
                                <tr>
                                    <td class="px-4 py-2">${p.producto_nombre}</td>
                                    <td class="px-4 py-2">${p.cantidad}</td>
                                    <td class="px-4 py-2">
                                        <select name="estado_${p.producto_id}" 
                                                class="estado-select border rounded px-2 py-1"
                                                data-producto-id="${p.producto_id}"
                                                onchange="togglePrecioVenta(this)">
                                            <option value="devuelto">Devuelto</option>
                                            <option value="vendido">Vendido</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" 
                                               name="precio_${p.producto_id}"
                                               class="precio-venta border rounded px-2 py-1 w-24"
                                               value="${p.precio_venta || 0}"
                                               min="${p.precio_costo || 0}"
                                               max="999999"
                                               step="0.01"
                                               disabled
                                               required>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end space-x-3 mt-4">
                    <button type="button" 
                            onclick="this.closest('.fixed').remove()" 
                            class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        Confirmar Devolución
                    </button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);

    // Función para habilitar/deshabilitar campo de precio
    window.togglePrecioVenta = function(selectElement) {
        const productoId = selectElement.dataset.productoId;
        const precioInput = document.querySelector(`input[name="precio_${productoId}"]`);
        precioInput.disabled = selectElement.value !== 'vendido';
    };

    // Manejar el envío del formulario
    document.getElementById('formDevolucion').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const devoluciones = prestamo.productos
            .filter(p => p.estado === 'prestado')
            .map(p => {
                const estado = formData.get(`estado_${p.producto_id}`);
                return {
                    producto_id: parseInt(p.producto_id),
                    estado: estado,
                    precio_venta: estado === 'vendido' ? parseFloat(formData.get(`precio_${p.producto_id}`)) : 0
                };
            });

        try {
            const response = await fetch('../../ajax/prestamos/devolver_prestamo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    prestamo_id: prestamoId,
                    cliente_id: prestamo.cliente_id,
                    devoluciones: devoluciones
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Devolución registrada exitosamente');
                modal.remove();
                cargarPrestamos();
            } else {
                alert('Error al registrar la devolución: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        }
    });
}

// Función auxiliar para obtener la clase CSS según el estado del producto
function getEstadoProductoClass(estado) {
    switch (estado) {
        case 'prestado':
            return 'bg-yellow-100 text-yellow-800';
        case 'devuelto':
            return 'bg-green-100 text-green-800';
        case 'vendido':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Función para seleccionar un producto
function seleccionarProducto(producto) {
    productoSeleccionado = producto;
    document.getElementById('producto_busqueda').value = producto.nombre;
    document.getElementById('sugerencias_productos').classList.add('hidden');
}

// Función para agregar un producto
function agregarProducto() {
    if (!productoSeleccionado) {
        alert('Por favor, seleccione un producto primero');
        return;
    }

    const cantidad = parseInt(document.getElementById('cantidad_producto').value);
    if (isNaN(cantidad) || cantidad <= 0) {
        alert('Por favor, ingrese una cantidad válida');
        return;
    }

    if (cantidad > productoSeleccionado.stock) {
        alert('La cantidad solicitada excede el stock disponible');
        return;
    }

    // Verificar si el producto ya está en la lista
    if (productos.some(p => p.id === productoSeleccionado.id)) {
        alert('Este producto ya está en la lista');
        return;
    }

    // Agregar a la lista de productos
    productos.push({
        id: productoSeleccionado.id,
        nombre: productoSeleccionado.nombre,
        codigo_barras: productoSeleccionado.codigo_barras,
        cantidad: cantidad,
        stock: productoSeleccionado.stock
    });

    actualizarTablaProductos();

    // Limpiar selección
    productoSeleccionado = null;
    document.getElementById('producto_busqueda').value = '';
    document.getElementById('cantidad_producto').value = '1';
}

// Función para actualizar la tabla de productos
function actualizarTablaProductos() {
    const tbody = document.getElementById('productos_container');
    tbody.innerHTML = '';
    
    productos.forEach((producto, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="px-6 py-4">${producto.codigo_barras || 'N/A'}</td>
            <td class="px-6 py-4">${producto.nombre}</td>
            <td class="px-6 py-4">
                <input type="number" 
                       name="productos[${index}][cantidad]" 
                       value="${producto.cantidad}"
                       min="1"
                       max="${producto.stock}"
                       class="w-20 px-2 py-1 border border-gray-300 rounded-md"
                       onchange="actualizarCantidad(${index}, this.value)">
                <input type="hidden" name="productos[${index}][id]" value="${producto.id}">
            </td>
            <td class="px-6 py-4">${producto.stock}</td>
            <td class="px-6 py-4">
                <button type="button" onclick="eliminarProducto(${index})" class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// Función para actualizar cantidad
function actualizarCantidad(index, nuevaCantidad) {
    nuevaCantidad = parseInt(nuevaCantidad);
    if (nuevaCantidad <= 0) {
        alert('La cantidad debe ser mayor a 0');
        actualizarTablaProductos();
        return;
    }
    if (nuevaCantidad > productos[index].stock) {
        alert('La cantidad no puede exceder el stock disponible');
        actualizarTablaProductos();
        return;
    }
    productos[index].cantidad = nuevaCantidad;
}

// Función para eliminar producto
function eliminarProducto(index) {
    productos.splice(index, 1);
    actualizarTablaProductos();
}

// Agregar esta función después de las funciones existentes
function mostrarFotoCliente(clienteId) {
    const selectCliente = document.getElementById('cliente_id');
    const opcionSeleccionada = selectCliente.options[selectCliente.selectedIndex];
    const fotoUrl = opcionSeleccionada.dataset.foto;
    const contenedorFoto = document.getElementById('foto_cliente_container');
    const imgFoto = document.getElementById('foto_cliente');

    if (fotoUrl) {
        imgFoto.src = fotoUrl;
        contenedorFoto.classList.remove('hidden');
        // Agregar animación de fade in
        imgFoto.style.opacity = '0';
        setTimeout(() => {
            imgFoto.style.transition = 'opacity 0.3s ease-in-out';
            imgFoto.style.opacity = '1';
        }, 100);
    } else {
        imgFoto.src = '../../assets/img/default-user.png'; // Imagen por defecto
        contenedorFoto.classList.remove('hidden');
    }
} 