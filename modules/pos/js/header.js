document.addEventListener('DOMContentLoaded', function() {
    // Variables para los menús desplegables
    const menuOpciones = document.getElementById('menuOpciones');
    const perfilMenu = document.getElementById('perfilMenu');
    let menuOpcionesVisible = false;
    let perfilMenuVisible = false;

    // Función para sincronizar datos
    window.sincronizarDatos = function() {
        // Mostrar indicador de carga
        const button = event.currentTarget;
        const originalContent = button.innerHTML;
        button.innerHTML = `<svg class="animate-spin h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>`;

        // Simular sincronización
        setTimeout(() => {
            button.innerHTML = originalContent;
            mostrarNotificacion('Datos sincronizados correctamente', 'success');
        }, 2000);
    };

    // Función para mostrar ayuda
    window.mostrarAyuda = function() {
        Swal.fire({
            title: 'Centro de Ayuda',
            html: `
                <div class="text-left">
                    <h3 class="font-medium text-lg mb-2">Accesos rápidos:</h3>
                    <ul class="space-y-2">
                        <li>• <strong>F1</strong> - Búsqueda rápida</li>
                        <li>• <strong>F2</strong> - Nuevo cliente</li>
                        <li>• <strong>F3</strong> - Procesar venta</li>
                        <li>• <strong>ESC</strong> - Cancelar venta</li>
                    </ul>
                    <p class="mt-4">Para más ayuda, visite nuestro <a href="../ayuda/index.php" class="text-indigo-600 hover:text-indigo-800">centro de ayuda</a>.</p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#4F46E5'
        });
    };

    // Función para mostrar notificaciones
    function mostrarNotificacion(mensaje, tipo = 'info') {
        Swal.fire({
            text: mensaje,
            icon: tipo,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }

    // Toggle menú de opciones
    window.toggleMenuOpciones = function() {
        menuOpcionesVisible = !menuOpcionesVisible;
        perfilMenu.classList.add('hidden'); // Ocultar menú de perfil
        perfilMenuVisible = false;
        menuOpciones.classList.toggle('hidden');
    };

    // Toggle menú de perfil
    window.togglePerfilMenu = function() {
        perfilMenuVisible = !perfilMenuVisible;
        menuOpciones.classList.add('hidden'); // Ocultar menú de opciones
        menuOpcionesVisible = false;
        perfilMenu.classList.toggle('hidden');
    };

    // Cerrar menús al hacer clic fuera
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.h-full.flex.items-center')) {
            menuOpciones.classList.add('hidden');
            perfilMenu.classList.add('hidden');
            menuOpcionesVisible = false;
            perfilMenuVisible = false;
        }
    });

    // Cerrar menús con la tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            menuOpciones.classList.add('hidden');
            perfilMenu.classList.add('hidden');
            menuOpcionesVisible = false;
            perfilMenuVisible = false;
        }
    });
}); 