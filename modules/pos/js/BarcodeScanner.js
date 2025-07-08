/**
 * Módulo optimizado para integración de lectores de códigos de barras
 * Diseñado para ser rápido, fácil de usar y eficiente
 * 
 * Características:
 * - Detección automática de diferentes tipos de scanners
 * - Configuración adaptativa según el tipo de scanner
 * - Feedback visual y auditivo mejorado
 * - Diagnósticos y validación de entrada
 * - Manejo robusto de errores
 */

class BarcodeScanner {
    constructor(options = {}) {
        this.config = {
            // Configuraciones por tipo de scanner
            SCANNER_TYPES: {
                USB_HID: {
                    name: 'USB HID Scanner',
                    delayBetweenKeys: 10,      // Scanners USB muy rápidos
                    minChars: 4,
                    maxChars: 50,
                    timeout: 50,
                    prefix: '',
                    suffix: 'Enter'
                },
                WIRELESS: {
                    name: 'Wireless Scanner', 
                    delayBetweenKeys: 25,      // Scanners inalámbricos ligeramente más lentos
                    minChars: 4,
                    maxChars: 50,
                    timeout: 100,
                    prefix: '',
                    suffix: 'Enter'
                },
                WEDGE: {
                    name: 'Keyboard Wedge',
                    delayBetweenKeys: 30,      // Scanners wedge tradicionales
                    minChars: 4,
                    maxChars: 50,
                    timeout: 150,
                    prefix: '',
                    suffix: 'Enter'
                },
                MOBILE: {
                    name: 'Mobile Scanner',
                    delayBetweenKeys: 50,      // Aplicaciones móviles
                    minChars: 4,
                    maxChars: 50,
                    timeout: 200,
                    prefix: '',
                    suffix: 'Enter'
                }
            },
            
            // Configuración actual (se detecta automáticamente)
            currentType: 'USB_HID',
            
            // Configuraciones generales
            DETECTION_SAMPLES: 5,           // Muestras para detectar tipo de scanner
            AUDIO_ENABLED: true,
            VISUAL_FEEDBACK: true,
            AUTO_FOCUS: true,
            DIAGNOSTICS: true,
            
            // Callbacks
            onSuccess: null,
            onError: null,
            onDetection: null,
            
            // Elementos DOM
            inputElement: null,
            statusElement: null,
            
            ...options
        };

        // Estado interno
        this.state = {
            buffer: '',
            lastKeyTime: 0,
            timeout: null,
            isScanning: false,
            detectionData: [],
            scanCount: 0,
            successCount: 0,
            errorCount: 0,
            averageSpeed: 0
        };

        this.init();
    }

    init() {
        this.setupElements();
        this.setupEventListeners();
        this.createStatusIndicator();
        this.detectScannerType();
        
        console.log('BarcodeScanner initialized:', this.config.currentType);
    }

    setupElements() {
        // Buscar input principal o usar el proporcionado
        this.config.inputElement = this.config.inputElement || 
            document.getElementById('buscar-producto') ||
            document.querySelector('input[data-scanner-enabled]');
            
        if (!this.config.inputElement) {
            console.warn('BarcodeScanner: No input element found');
            return;
        }

        // Asegurar que el input tenga los atributos necesarios
        this.config.inputElement.setAttribute('autocomplete', 'off');
        this.config.inputElement.setAttribute('data-scanner-ready', 'true');
    }

    setupEventListeners() {
        // Listener principal para captura de teclas
        document.addEventListener('keydown', (e) => this.handleKeyInput(e), true);
        
        // Listener para detectar cuando el input pierde el foco
        if (this.config.inputElement) {
            this.config.inputElement.addEventListener('blur', () => {
                if (this.config.AUTO_FOCUS && !this.state.isScanning) {
                    setTimeout(() => this.config.inputElement.focus(), 100);
                }
            });
        }

        // Listener para entrada manual en el input
        if (this.config.inputElement) {
            let manualTimeout;
            this.config.inputElement.addEventListener('input', (e) => {
                clearTimeout(manualTimeout);
                manualTimeout = setTimeout(() => {
                    if (!this.state.isScanning && e.target.value.length > 0) {
                        this.handleManualInput(e.target.value);
                    }
                }, 300);
            });
        }
    }

    createStatusIndicator() {
        if (!this.config.VISUAL_FEEDBACK) return;

        // Crear indicador de estado si no existe
        let statusContainer = document.getElementById('scanner-status');
        if (!statusContainer) {
            statusContainer = document.createElement('div');
            statusContainer.id = 'scanner-status';
            statusContainer.className = 'scanner-status-container';
            
            // Insertar después del input de búsqueda
            if (this.config.inputElement && this.config.inputElement.parentNode) {
                this.config.inputElement.parentNode.insertBefore(
                    statusContainer, 
                    this.config.inputElement.nextSibling
                );
            }
        }

        statusContainer.innerHTML = `
            <div class="scanner-status">
                <div class="scanner-indicator" id="scanner-indicator">
                    <i class="fas fa-barcode"></i>
                    <span class="scanner-status-text">Listo para escanear</span>
                </div>
                <div class="scanner-stats" id="scanner-stats">
                    <small>Tipo: ${this.config.SCANNER_TYPES[this.config.currentType].name}</small>
                </div>
            </div>
        `;

        this.config.statusElement = statusContainer;
        this.addStatusStyles();
    }

    addStatusStyles() {
        if (document.getElementById('scanner-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'scanner-styles';
        styles.textContent = `
            .scanner-status-container {
                margin-top: 8px;
                padding: 8px 12px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                font-size: 12px;
            }
            
            .scanner-status {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .scanner-indicator {
                display: flex;
                align-items: center;
                gap: 6px;
                color: #64748b;
                transition: all 0.3s ease;
            }
            
            .scanner-indicator.scanning {
                color: #3b82f6;
                animation: pulse 1s infinite;
            }
            
            .scanner-indicator.success {
                color: #10b981;
            }
            
            .scanner-indicator.error {
                color: #ef4444;
            }
            
            .scanner-stats {
                color: #64748b;
                font-size: 10px;
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
            
            .scanner-feedback {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 16px;
                border-radius: 6px;
                color: white;
                font-weight: 500;
                z-index: 1000;
                animation: slideIn 0.3s ease;
            }
            
            .scanner-feedback.success {
                background: #10b981;
            }
            
            .scanner-feedback.error {
                background: #ef4444;
            }
            
            @keyframes slideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
        `;
        
        document.head.appendChild(styles);
    }

    handleKeyInput(e) {
        const currentTime = Date.now();
        const currentConfig = this.config.SCANNER_TYPES[this.config.currentType];
        
        // Ignorar si el foco está en un input diferente al de búsqueda
        if (e.target.tagName === 'INPUT' && e.target !== this.config.inputElement) {
            return;
        }
        
        // Si no está en el input de búsqueda, dirigir el foco
        if (e.target !== this.config.inputElement && this.config.inputElement) {
            this.config.inputElement.focus();
        }

        // Detectar si es entrada del scanner
        const timeDiff = currentTime - this.state.lastKeyTime;
        const isFromScanner = timeDiff <= currentConfig.delayBetweenKeys || this.state.buffer.length > 0;

        if (isFromScanner) {
            e.preventDefault();
            this.handleScannerInput(e, currentTime, currentConfig);
        }

        this.state.lastKeyTime = currentTime;
    }

    handleScannerInput(e, currentTime, config) {
        // Marcar como escaneando
        if (!this.state.isScanning) {
            this.state.isScanning = true;
            this.updateStatus('scanning', 'Escaneando...');
        }

        // Procesar tecla Enter (fin de escaneo)
        if (e.key === 'Enter' || e.key === config.suffix) {
            this.processScanResult();
            return;
        }

        // Agregar carácter imprimible al buffer
        if (e.key.length === 1) {
            this.state.buffer += e.key;
            
            // Limpiar timeout anterior y establecer nuevo timeout
            clearTimeout(this.state.timeout);
            this.state.timeout = setTimeout(() => {
                this.processScanResult();
            }, config.timeout);

            // Recolectar datos para detección de tipo de scanner
            this.collectDetectionData(currentTime);
        }
    }

    handleManualInput(value) {
        if (value.length >= this.config.SCANNER_TYPES[this.config.currentType].minChars) {
            this.processBarcode(value, true);
        }
    }

    processScanResult() {
        clearTimeout(this.state.timeout);
        
        const config = this.config.SCANNER_TYPES[this.config.currentType];
        const code = this.state.buffer.trim();
        
        this.state.buffer = '';
        this.state.isScanning = false;
        
        if (code.length >= config.minChars && code.length <= config.maxChars) {
            this.processBarcode(code, false);
        } else {
            this.handleError('Código de barras inválido', `Longitud: ${code.length} caracteres`);
        }
    }

    processBarcode(code, isManual = false) {
        this.state.scanCount++;
        
        if (this.config.onSuccess) {
            const result = this.config.onSuccess(code, isManual);
            if (result !== false) {
                this.state.successCount++;
                this.updateStatus('success', `${isManual ? 'Búsqueda' : 'Escaneo'} exitoso`);
                this.playFeedbackSound(true);
                this.showFeedback(`${isManual ? 'Producto encontrado' : 'Código escaneado'}: ${code}`, 'success');
                
                // Limpiar campo de búsqueda
                if (this.config.inputElement) {
                    this.config.inputElement.value = '';
                }
            } else {
                this.handleError('Producto no encontrado', code);
            }
        }
        
        // Resetear estado después de un breve delay
        setTimeout(() => {
            this.updateStatus('ready', 'Listo para escanear');
        }, 1500);
    }

    handleError(message, details = '') {
        this.state.errorCount++;
        this.updateStatus('error', message);
        this.playFeedbackSound(false);
        this.showFeedback(`${message}${details ? ': ' + details : ''}`, 'error');
        
        if (this.config.onError) {
            this.config.onError(message, details);
        }
        
        // Resetear estado después de un breve delay
        setTimeout(() => {
            this.updateStatus('ready', 'Listo para escanear');
        }, 2000);
    }

    updateStatus(type, message) {
        if (!this.config.statusElement) return;
        
        const indicator = this.config.statusElement.querySelector('.scanner-indicator');
        const text = this.config.statusElement.querySelector('.scanner-status-text');
        
        if (indicator && text) {
            indicator.className = `scanner-indicator ${type}`;
            text.textContent = message;
        }
    }

    showFeedback(message, type) {
        if (!this.config.VISUAL_FEEDBACK) return;
        
        // Remover feedback anterior
        const existing = document.querySelector('.scanner-feedback');
        if (existing) {
            existing.remove();
        }
        
        const feedback = document.createElement('div');
        feedback.className = `scanner-feedback ${type}`;
        feedback.textContent = message;
        
        document.body.appendChild(feedback);
        
        setTimeout(() => {
            feedback.remove();
        }, 3000);
    }

    playFeedbackSound(success) {
        if (!this.config.AUDIO_ENABLED) return;
        
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.type = 'sine';
            
            if (success) {
                // Tono de éxito: do-mi-sol (frecuencias más agradables)
                const frequencies = [523, 659, 784];
                let time = audioContext.currentTime;
                
                frequencies.forEach((freq, index) => {
                    const osc = audioContext.createOscillator();
                    const gain = audioContext.createGain();
                    
                    osc.connect(gain);
                    gain.connect(audioContext.destination);
                    
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, time);
                    gain.gain.setValueAtTime(0.1, time);
                    gain.gain.exponentialRampToValueAtTime(0.01, time + 0.1);
                    
                    osc.start(time);
                    osc.stop(time + 0.1);
                    
                    time += 0.1;
                });
            } else {
                // Tono de error: frecuencia baja y desagradable
                oscillator.frequency.setValueAtTime(200, audioContext.currentTime);
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
                
                oscillator.start();
                oscillator.stop(audioContext.currentTime + 0.3);
            }
        } catch (error) {
            console.warn('BarcodeScanner: Could not play audio feedback', error);
        }
    }

    collectDetectionData(currentTime) {
        const timeDiff = currentTime - this.state.lastKeyTime;
        
        this.state.detectionData.push({
            timeDiff,
            timestamp: currentTime,
            bufferLength: this.state.buffer.length
        });
        
        // Mantener solo las últimas muestras
        if (this.state.detectionData.length > this.config.DETECTION_SAMPLES * 2) {
            this.state.detectionData = this.state.detectionData.slice(-this.config.DETECTION_SAMPLES);
        }
        
        // Intentar detectar tipo de scanner cada cierto número de muestras
        if (this.state.detectionData.length >= this.config.DETECTION_SAMPLES) {
            this.detectScannerType();
        }
    }

    detectScannerType() {
        if (this.state.detectionData.length < this.config.DETECTION_SAMPLES) return;
        
        const recentData = this.state.detectionData.slice(-this.config.DETECTION_SAMPLES);
        const avgTimeDiff = recentData.reduce((sum, data) => sum + data.timeDiff, 0) / recentData.length;
        
        let detectedType = 'USB_HID';
        
        if (avgTimeDiff <= 15) {
            detectedType = 'USB_HID';
        } else if (avgTimeDiff <= 30) {
            detectedType = 'WIRELESS';
        } else if (avgTimeDiff <= 45) {
            detectedType = 'WEDGE';
        } else {
            detectedType = 'MOBILE';
        }
        
        if (detectedType !== this.config.currentType) {
            console.log(`BarcodeScanner: Detected scanner type change: ${this.config.currentType} -> ${detectedType}`);
            this.config.currentType = detectedType;
            
            // Actualizar información en el estado
            if (this.config.statusElement) {
                const stats = this.config.statusElement.querySelector('.scanner-stats small');
                if (stats) {
                    stats.textContent = `Tipo: ${this.config.SCANNER_TYPES[detectedType].name}`;
                }
            }
            
            if (this.config.onDetection) {
                this.config.onDetection(detectedType, this.config.SCANNER_TYPES[detectedType]);
            }
        }
        
        this.state.averageSpeed = avgTimeDiff;
    }

    // Métodos públicos para control
    enable() {
        this.config.AUDIO_ENABLED = true;
        this.config.VISUAL_FEEDBACK = true;
        console.log('BarcodeScanner: Enabled');
    }

    disable() {
        this.config.AUDIO_ENABLED = false;
        this.config.VISUAL_FEEDBACK = false;
        console.log('BarcodeScanner: Disabled');
    }

    getStats() {
        return {
            scanCount: this.state.scanCount,
            successCount: this.state.successCount,
            errorCount: this.state.errorCount,
            successRate: this.state.scanCount > 0 ? (this.state.successCount / this.state.scanCount * 100).toFixed(1) : 0,
            currentType: this.config.currentType,
            averageSpeed: this.state.averageSpeed.toFixed(1)
        };
    }

    reset() {
        this.state.buffer = '';
        this.state.isScanning = false;
        this.state.detectionData = [];
        clearTimeout(this.state.timeout);
        this.updateStatus('ready', 'Listo para escanear');
    }

    configure(options) {
        Object.assign(this.config, options);
        console.log('BarcodeScanner: Configuration updated', options);
    }
}

// Exportar para uso en módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BarcodeScanner;
}

// Hacer disponible globalmente
window.BarcodeScanner = BarcodeScanner;