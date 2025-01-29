document.addEventListener('DOMContentLoaded', function() {
    // Verificar que los elementos existan antes de usarlos
    const perfilMenu = document.getElementById('perfil-menu');
    const menuOpciones = document.getElementById('menuOpciones');
    
    // Inicializar variables de estado
    let menuOpcionesVisible = false;
    let perfilMenuVisible = false;

    // Función para sincronizar datos
    window.sincronizarDatos = function(event) {
        if (!event || !event.currentTarget) return;
        
        const button = event.currentTarget;
        const originalContent = button.innerHTML;
        button.innerHTML = `<svg class="animate-spin h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>`;

        setTimeout(() => {
            button.innerHTML = originalContent;
            mostrarNotificacion('Datos sincronizados correctamente', 'success');
        }, 2000);
    };

    // Función para mostrar notificaciones
    function mostrarNotificacion(mensaje, tipo = 'info') {
        if (typeof Swal === 'undefined') return;
        
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
        if (!menuOpciones || !perfilMenu) return;
        
        menuOpcionesVisible = !menuOpcionesVisible;
        perfilMenu.classList.add('hidden');
        perfilMenuVisible = false;
        menuOpciones.classList.toggle('hidden');
    };

    // Toggle menú de perfil
    window.togglePerfilMenu = function() {
        if (!menuOpciones || !perfilMenu) return;
        
        perfilMenuVisible = !perfilMenuVisible;
        menuOpciones.classList.add('hidden');
        menuOpcionesVisible = false;
        perfilMenu.classList.toggle('hidden');
    };

    // Solo agregar event listeners si los elementos existen
    if (perfilMenu && menuOpciones) {
        // Cerrar menús al hacer clic fuera
        document.addEventListener('click', function(event) {
            const perfilButton = event.target.closest('button[onclick="togglePerfilMenu()"]');
            const menuButton = event.target.closest('button[onclick="toggleMenuOpciones()"]');
            
            if (!perfilButton && !menuButton && 
                !perfilMenu.contains(event.target) && 
                !menuOpciones.contains(event.target)) {
                
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
    }
}); 