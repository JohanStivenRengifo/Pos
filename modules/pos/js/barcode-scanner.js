/**
 * Módulo de Escáner de Códigos de Barras Optimizado
 * Integración rápida, fácil y eficaz para el POS
 */

class BarcodeScanner {
    constructor(config = {}) {
        this.config = {
            // Configuración optimizada para diferentes tipos de escáneres
            delayBetweenKeys: config.delayBetweenKeys || 25, // Reducido para mayor velocidad
            minChars: config.minChars || 3,
            enterKey: config.enterKey || 'Enter',
            timeout: config.timeout || 80, // Reducido para mayor responsividad
            searchInputId: config.searchInputId || 'buscar-producto',
            enableVisualFeedback: config.enableVisualFeedback !== false,
            enableAudioFeedback: config.enableAudioFeedback !== false,
            debugMode: config.debugMode || false,
            ...config
        };

        this.buffer = '';
        this.lastKeyTime = 0;
        this.timeoutId = null;
        this.isScanning = false;
        this.searchInput = null;
        this.onScanCallback = null;
        this.onErrorCallback = null;

        this.init();
    }

    init() {
        this.searchInput = document.getElementById(this.config.searchInputId);
        if (!this.searchInput) {
            console.warn(`BarcodeScanner: Elemento ${this.config.searchInputId} no encontrado`);
            return;
        }

        this.setupEventListeners();
        this.setupVisualIndicators();
        
        if (this.config.debugMode) {
            console.log('BarcodeScanner inicializado con configuración:', this.config);
        }
    }

    setupEventListeners() {
        // Listener principal para capturar entrada del escáner
        document.addEventListener('keydown', (e) => this.handleKeyInput(e));
        
        // Listener para búsqueda manual en el input
        this.searchInput.addEventListener('input', (e) => this.handleManualInput(e));
        
        // Listener para Enter en búsqueda manual
        this.searchInput.addEventListener('keypress', (e) => this.handleManualEnter(e));

        // Listener para focus automático
        document.addEventListener('click', (e) => {
            // Solo enfocar si no se está haciendo clic en otro input
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'SELECT') {
                this.focusSearchInput();
            }
        });
    }

    setupVisualIndicators() {
        if (!this.config.enableVisualFeedback) return;

        // Agregar indicadores visuales al input de búsqueda
        this.addScannerIndicators();
    }

    addScannerIndicators() {
        const searchContainer = this.searchInput.parentElement;
        
        // Crear indicador de estado del escáner
        const scannerStatus = document.createElement('div');
        scannerStatus.id = 'scanner-status';
        scannerStatus.className = 'absolute top-2 right-2 text-xs px-2 py-1 rounded-full transition-all duration-200 opacity-0';
        scannerStatus.innerHTML = '<i class="fas fa-barcode mr-1"></i>Escaneando...';
        
        searchContainer.style.position = 'relative';
        searchContainer.appendChild(scannerStatus);

        // Agregar clase de feedback visual al input
        this.searchInput.classList.add('scanner-enabled');
    }

    handleKeyInput(e) {
        const currentTime = Date.now();
        
        // Ignorar si el foco está en otros inputs (excepto el de búsqueda)
        if (e.target.tagName === 'INPUT' && e.target !== this.searchInput) {
            return;
        }

        // Si no está en el input de búsqueda, enfocar automáticamente
        if (e.target !== this.searchInput) {
            this.focusSearchInput();
        }

        // Detectar si es entrada de escáner por velocidad
        const isQuickInput = currentTime - this.lastKeyTime <= this.config.delayBetweenKeys;
        
        if (isQuickInput || this.buffer.length > 0) {
            this.handleScannerInput(e, currentTime);
        }
        
        this.lastKeyTime = currentTime;
    }

    handleScannerInput(e, currentTime) {
        // Prevenir comportamiento por defecto para entrada del escáner
        e.preventDefault();
        
        if (!this.isScanning) {
            this.startScanning();
        }

        if (e.key === this.config.enterKey) {
            this.finalizeScan();
        } else if (this.isPrintableCharacter(e.key)) {
            this.buffer += e.key;
            this.updateVisualFeedback();
            this.resetTimeout();
        }
    }

    handleManualInput(e) {
        // Solo procesar si no estamos en modo escáner
        if (this.isScanning) return;

        const currentTime = Date.now();
        
        // Si parece ser entrada del escáner (muy rápida), no filtrar aún
        if (currentTime - this.lastKeyTime <= this.config.delayBetweenKeys) {
            return;
        }

        // Debounce para búsqueda manual
        clearTimeout(this.manualSearchTimeout);
        this.manualSearchTimeout = setTimeout(() => {
            this.performSearch(e.target.value);
        }, 300);
    }

    handleManualEnter(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const searchValue = this.searchInput.value.trim();
            
            if (searchValue.length >= this.config.minChars) {
                this.selectFirstMatchingProduct();
            }
        }
    }

    startScanning() {
        this.isScanning = true;
        this.showVisualFeedback('scanning');
        
        if (this.config.debugMode) {
            console.log('Iniciando escaneo...');
        }
    }

    finalizeScan() {
        if (this.buffer.length >= this.config.minChars) {
            this.processScan(this.buffer);
        }
        this.resetScanner();
    }

    processScan(code) {
        if (this.config.debugMode) {
            console.log('Código escaneado:', code);
        }

        this.showVisualFeedback('processing');
        
        // Buscar producto por código
        const product = this.findProductByCode(code);
        
        if (product) {
            this.handleSuccessfulScan(product, code);
        } else {
            this.handleFailedScan(code);
        }
        
        // Limpiar input y enfocar
        this.searchInput.value = '';
        this.performSearch(''); // Mostrar todos los productos
        this.focusSearchInput();
    }

    findProductByCode(code) {
        const products = document.querySelectorAll('.item-view');
        return Array.from(products).find(p => p.dataset.codigo === code);
    }

    handleSuccessfulScan(product, code) {
        const productData = {
            id: product.dataset.id,
            nombre: product.dataset.nombre,
            precio: parseFloat(product.dataset.precio),
            stock: parseInt(product.dataset.cantidad),
            codigo: product.dataset.codigo
        };

        // Verificar stock
        if (productData.stock <= 0) {
            this.showError('Producto sin stock disponible');
            this.playBeep(false);
            return;
        }

        // Verificar si ya existe en el carrito
        if (window.carrito && window.carrito.items) {
            const existingItem = window.carrito.items.find(item => item.id === parseInt(productData.id));
            if (existingItem && existingItem.cantidad >= productData.stock) {
                this.showError(`Solo hay ${productData.stock} unidades disponibles`);
                this.playBeep(false);
                return;
            }
        }

        // Agregar al carrito
        if (typeof window.agregarAlCarrito === 'function') {
            window.agregarAlCarrito(productData);
        } else if (typeof agregarProductoAlCarrito === 'function') {
            agregarProductoAlCarrito(productData);
        }

        this.showSuccess(`Producto agregado: ${productData.nombre}`);
        this.playBeep(true);

        // Callback personalizado
        if (this.onScanCallback) {
            this.onScanCallback(productData, code);
        }
    }

    handleFailedScan(code) {
        this.showError('Producto no encontrado');
        this.playBeep(false);

        if (this.onErrorCallback) {
            this.onErrorCallback(code, 'Producto no encontrado');
        }
    }

    resetScanner() {
        this.buffer = '';
        this.isScanning = false;
        clearTimeout(this.timeoutId);
        this.hideVisualFeedback();
    }

    resetTimeout() {
        clearTimeout(this.timeoutId);
        this.timeoutId = setTimeout(() => {
            if (this.buffer.length >= this.config.minChars) {
                this.processScan(this.buffer);
            }
            this.resetScanner();
        }, this.config.timeout);
    }

    showVisualFeedback(type) {
        if (!this.config.enableVisualFeedback) return;

        const status = document.getElementById('scanner-status');
        if (!status) return;

        status.classList.remove('opacity-0');
        status.classList.add('opacity-100');

        switch (type) {
            case 'scanning':
                status.className = status.className.replace(/bg-\w+-\d+/, '') + ' bg-blue-100 text-blue-700';
                status.innerHTML = '<i class="fas fa-barcode mr-1 animate-pulse"></i>Escaneando...';
                break;
            case 'processing':
                status.className = status.className.replace(/bg-\w+-\d+/, '') + ' bg-yellow-100 text-yellow-700';
                status.innerHTML = '<i class="fas fa-search mr-1 animate-spin"></i>Procesando...';
                break;
        }

        // Agregar efecto visual al input
        this.searchInput.classList.add('ring-2', 'ring-blue-500', 'border-blue-500');
    }

    hideVisualFeedback() {
        if (!this.config.enableVisualFeedback) return;

        const status = document.getElementById('scanner-status');
        if (status) {
            status.classList.add('opacity-0');
            status.classList.remove('opacity-100');
        }

        // Remover efecto visual del input
        this.searchInput.classList.remove('ring-2', 'ring-blue-500', 'border-blue-500');
    }

    updateVisualFeedback() {
        if (!this.config.enableVisualFeedback) return;

        const status = document.getElementById('scanner-status');
        if (status) {
            status.innerHTML = `<i class="fas fa-barcode mr-1 animate-pulse"></i>Escaneando... (${this.buffer.length})`;
        }
    }

    showSuccess(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Éxito',
                text: message,
                icon: 'success',
                timer: 1500,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        } else {
            console.log('Éxito:', message);
        }
    }

    showError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Error',
                text: message,
                icon: 'warning',
                timer: 2000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        } else {
            console.error('Error:', message);
        }
    }

    playBeep(success) {
        if (!this.config.enableAudioFeedback) return;

        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(success ? 1200 : 400, audioContext.currentTime);
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);

            oscillator.start();
            oscillator.stop(audioContext.currentTime + (success ? 0.1 : 0.3));
        } catch (error) {
            // Fallback silencioso si no se puede reproducir audio
            if (this.config.debugMode) {
                console.log('No se pudo reproducir audio:', error);
            }
        }
    }

    performSearch(query) {
        if (typeof filtrarProductos === 'function') {
            filtrarProductos(query);
        } else {
            // Implementación básica de búsqueda
            this.basicProductFilter(query);
        }
    }

    basicProductFilter(query) {
        const products = document.querySelectorAll('.item-view');
        const searchTerm = query.toLowerCase().trim();

        products.forEach(product => {
            const name = product.getAttribute('data-nombre').toLowerCase();
            const code = product.getAttribute('data-codigo').toLowerCase();
            
            if (searchTerm === '' || name.includes(searchTerm) || code.includes(searchTerm)) {
                product.style.display = '';
            } else {
                product.style.display = 'none';
            }
        });
    }

    selectFirstMatchingProduct() {
        const visibleProducts = document.querySelectorAll('.item-view:not([style*="display: none"])');
        
        if (visibleProducts.length === 1) {
            const product = visibleProducts[0];
            product.click();
            this.searchInput.value = '';
            this.performSearch('');
        } else if (visibleProducts.length === 0) {
            this.showError('No se encontraron productos que coincidan con la búsqueda');
        }
    }

    focusSearchInput() {
        if (this.searchInput) {
            this.searchInput.focus();
        }
    }

    isPrintableCharacter(key) {
        return key.length === 1 && key.match(/[a-zA-Z0-9\-_]/);
    }

    // Métodos públicos para configuración dinámica
    setOnScanCallback(callback) {
        this.onScanCallback = callback;
    }

    setOnErrorCallback(callback) {
        this.onErrorCallback = callback;
    }

    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };
    }

    // Método para testing y depuración
    simulateScan(code) {
        if (this.config.debugMode) {
            console.log('Simulando escaneo:', code);
        }
        this.processScan(code);
    }

    // Destructor para limpiar eventos
    destroy() {
        clearTimeout(this.timeoutId);
        clearTimeout(this.manualSearchTimeout);
        
        const status = document.getElementById('scanner-status');
        if (status) {
            status.remove();
        }
    }
}

// Exportar para uso global
window.BarcodeScanner = BarcodeScanner;