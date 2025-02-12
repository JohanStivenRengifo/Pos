<?php

define('PLAN_BASICO', 'basico');
define('PLAN_PROFESIONAL', 'profesional');
define('PLAN_EMPRESARIAL', 'empresarial');

$PLANES = [
    PLAN_BASICO => [
        'nombre' => 'Básico',
        'precio' => 19900,
        'limite_facturas' => -1, // ilimitado
        'limite_usuarios' => 1,
        'limite_productos' => 100,
        'limite_clientes' => 50,
        'limite_proveedores' => 10,
        'limite_ingresos_mensual' => 10000000, // 10M
        'caracteristicas' => [
            'reportes_basicos' => true,
            'soporte_email' => true,
            'control_inventario' => false,
            'api_personalizada' => false,
            'soporte_24_7' => false,
            'personalizacion' => false,
            'modulos' => [
                'pos' => true,
                'ventas' => true,
                'cotizaciones' => false,
                'creditos' => false,
                'ingresos' => true,
                'egresos' => true,
                'turnos' => false,
                'inventario' => true,
                'departamentos' => false,
                'bodegas' => false,
                'prestamos' => false,
                'reportes_avanzados' => false
            ]
        ]
    ],
    PLAN_PROFESIONAL => [
        'nombre' => 'Profesional',
        'precio' => 39900,
        'limite_facturas' => -1, // ilimitado
        'limite_usuarios' => 3,
        'limite_productos' => 500,
        'limite_clientes' => 200,
        'limite_proveedores' => 50,
        'limite_ingresos_mensual' => 40000000, // 40M
        'caracteristicas' => [
            'reportes_basicos' => true,
            'soporte_email' => true,
            'control_inventario' => true,
            'api_personalizada' => false,
            'soporte_24_7' => true,
            'personalizacion' => false,
            'modulos' => [
                'pos' => true,
                'ventas' => true,
                'cotizaciones' => true,
                'creditos' => true,
                'ingresos' => true,
                'egresos' => true,
                'turnos' => true,
                'inventario' => true,
                'departamentos' => true,
                'bodegas' => true,
                'prestamos' => false,
                'reportes_avanzados' => true
            ]
        ]
    ],
    PLAN_EMPRESARIAL => [
        'nombre' => 'Empresarial',
        'precio' => 69900,
        'limite_facturas' => -1, // ilimitado
        'limite_usuarios' => -1, // ilimitado
        'limite_productos' => -1, // ilimitado
        'limite_clientes' => -1, // ilimitado
        'limite_proveedores' => -1, // ilimitado
        'limite_ingresos_mensual' => 180000000, // 180M
        'caracteristicas' => [
            'reportes_basicos' => true,
            'soporte_email' => true,
            'control_inventario' => true,
            'api_personalizada' => true,
            'soporte_24_7' => true,
            'personalizacion' => true,
            'modulos' => [
                'pos' => true,
                'ventas' => true,
                'cotizaciones' => true,
                'creditos' => true,
                'ingresos' => true,
                'egresos' => true,
                'turnos' => true,
                'inventario' => true,
                'departamentos' => true,
                'bodegas' => true,
                'prestamos' => true,
                'reportes_avanzados' => true
            ]
        ]
    ]
];

// Función para verificar límites
function verificarLimite($pdo, $empresa_id, $tipo_limite) {
    $stmt = $pdo->prepare("
        SELECT e.plan_suscripcion, 
               (SELECT COUNT(*) FROM $tipo_limite WHERE empresa_id = ?) as total_actual
        FROM empresas e 
        WHERE e.id = ?
    ");
    $stmt->execute([$empresa_id, $empresa_id]);
    $resultado = $stmt->fetch();

    if (!$resultado) {
        return false;
    }

    global $PLANES;
    $plan = $PLANES[$resultado['plan_suscripcion']];
    $limite = $plan["limite_" . $tipo_limite];

    // -1 significa ilimitado
    if ($limite === -1) {
        return true;
    }

    return $resultado['total_actual'] < $limite;
}

// Función para verificar acceso a módulo
function tieneAccesoModulo($pdo, $empresa_id, $modulo) {
    // POS siempre disponible en todos los planes
    if ($modulo === 'pos') {
        return true;
    }
    
    $stmt = $pdo->prepare("SELECT plan_suscripcion FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $plan_actual = $stmt->fetchColumn();

    global $PLANES;
    return $PLANES[$plan_actual]['caracteristicas']['modulos'][$modulo] ?? false;
}

// Función para obtener mensaje de límite alcanzado
function getMensajeLimite($tipo_limite, $plan_siguiente) {
    return "Has alcanzado el límite de $tipo_limite en tu plan actual. " .
           "Actualiza al plan $plan_siguiente para obtener más capacidad.";
}

// Función para verificar y notificar límite
function verificarYNotificarLimite($pdo, $empresa_id, $tipo_limite) {
    if (!verificarLimite($pdo, $empresa_id, $tipo_limite)) {
        $stmt = $pdo->prepare("SELECT plan_suscripcion FROM empresas WHERE id = ?");
        $stmt->execute([$empresa_id]);
        $plan_actual = $stmt->fetchColumn();

        $plan_siguiente = $plan_actual === PLAN_BASICO ? 'Profesional' : 'Empresarial';
        
        $_SESSION['error_message'] = getMensajeLimite($tipo_limite, $plan_siguiente);
        return false;
    }
    return true;
}

// Función para verificar límite de ingresos mensuales
function verificarLimiteIngresosMensuales($pdo, $empresa_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.plan_suscripcion,
                   (SELECT COALESCE(SUM(monto), 0)
                    FROM ventas v 
                    WHERE v.empresa_id = e.id 
                    AND MONTH(v.fecha) = MONTH(CURRENT_DATE)
                    AND YEAR(v.fecha) = YEAR(CURRENT_DATE)) as ingresos_mes_actual
            FROM empresas e
            WHERE e.id = ?
        ");
        
        $stmt->execute([$empresa_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resultado) {
            throw new Exception("No se encontró la empresa");
        }

        global $PLANES;
        $limite_ingresos = $PLANES[$resultado['plan_suscripcion']]['limite_ingresos_mensual'];
        $ingresos_actuales = (float)$resultado['ingresos_mes_actual'];

        return [
            'puede_facturar' => $ingresos_actuales < $limite_ingresos,
            'ingresos_disponibles' => $limite_ingresos - $ingresos_actuales,
            'ingresos_actuales' => $ingresos_actuales,
            'limite_ingresos' => $limite_ingresos
        ];
    } catch (Exception $e) {
        error_log("Error en verificarLimiteIngresosMensuales: " . $e->getMessage());
        return [
            'puede_facturar' => true,
            'ingresos_disponibles' => -1,
            'ingresos_actuales' => 0,
            'limite_ingresos' => -1
        ];
    }
}

// Función para verificar si necesita actualizar plan (actualizada)
function necesitaActualizarPlan($pdo, $empresa_id) {
    $limites = ['facturas', 'usuarios', 'productos', 'clientes', 'proveedores'];
    $necesita_actualizar = false;
    
    // Verificar límites básicos
    foreach ($limites as $limite) {
        if (!verificarLimite($pdo, $empresa_id, $limite)) {
            $necesita_actualizar = true;
            break;
        }
    }
    
    // Verificar límite de ingresos mensuales
    if (!$necesita_actualizar) {
        $resultado_ingresos = verificarLimiteIngresosMensuales($pdo, $empresa_id);
        if (!$resultado_ingresos['puede_facturar']) {
            $necesita_actualizar = true;
        }
    }
    
    if ($necesita_actualizar) {
        $stmt = $pdo->prepare("SELECT plan_suscripcion FROM empresas WHERE id = ?");
        $stmt->execute([$empresa_id]);
        $plan_actual = $stmt->fetchColumn();
        
        if ($plan_actual !== PLAN_EMPRESARIAL) {
            $plan_siguiente = $plan_actual === PLAN_BASICO ? 'Profesional' : 'Empresarial';
            $_SESSION['warning_message'] = "Tu plan actual está llegando a sus límites. " .
                                         "Considera actualizar al plan $plan_siguiente para evitar interrupciones.";
        }
    }
    
    return $necesita_actualizar;
}

// Validar datos de empresa
function validarDatosEmpresa($datos) {
    $errores = [];
    
    // Validaciones específicas según el tipo de persona
    if ($datos['tipo_persona'] === 'natural') {
        if (empty($datos['primer_nombre'])) {
            $errores[] = "El primer nombre es obligatorio para personas naturales";
        }
        if (empty($datos['apellidos'])) {
            $errores[] = "Los apellidos son obligatorios para personas naturales";
        }
    } else {
        if (empty($datos['nombre_empresa'])) {
            $errores[] = "El nombre de la empresa es obligatorio para personas jurídicas";
        }
    }
    
    // Validar formato de teléfono
    if (!empty($datos['telefono']) && !preg_match('/^\+57\s\d{10}$/', $datos['telefono'])) {
        $errores[] = "El formato del teléfono debe ser: +57 seguido de 10 dígitos";
    }
    
    // Validar correo
    if (!empty($datos['correo_contacto']) && !filter_var($datos['correo_contacto'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no tiene un formato válido";
    }
    
    // Validar sitio web
    if (!empty($datos['sitio_web']) && !filter_var($datos['sitio_web'], FILTER_VALIDATE_URL)) {
        $errores[] = "El sitio web no tiene un formato válido";
    }
    
    return [
        'valido' => empty($errores),
        'errores' => $errores
    ];
}

// Añadir o modificar la función verificarLimiteFacturasMensuales
function verificarLimiteFacturasMensuales($pdo, $empresa_id) {
    try {
        // Obtener el plan actual y sus límites
        $stmt = $pdo->prepare("
            SELECT e.plan_suscripcion,
                   (SELECT COUNT(*) 
                    FROM ventas v 
                    WHERE v.empresa_id = e.id 
                    AND MONTH(v.fecha) = MONTH(CURRENT_DATE)
                    AND YEAR(v.fecha) = YEAR(CURRENT_DATE)) as facturas_mes_actual
            FROM empresas e
            WHERE e.id = ?
        ");
        
        $stmt->execute([$empresa_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resultado) {
            throw new Exception("No se encontró la empresa");
        }

        global $PLANES;
        $limite_facturas = $PLANES[$resultado['plan_suscripcion']]['limite_facturas'];
        $facturas_actuales = (int)$resultado['facturas_mes_actual'];

        // Si el límite es -1, significa ilimitado
        if ($limite_facturas === -1) {
            return [
                'puede_facturar' => true,
                'facturas_disponibles' => -1,
                'facturas_actuales' => $facturas_actuales,
                'limite_facturas' => $limite_facturas
            ];
        }

        return [
            'puede_facturar' => $facturas_actuales < $limite_facturas,
            'facturas_disponibles' => $limite_facturas - $facturas_actuales,
            'facturas_actuales' => $facturas_actuales,
            'limite_facturas' => $limite_facturas
        ];
    } catch (Exception $e) {
        error_log("Error en verificarLimiteFacturasMensuales: " . $e->getMessage());
        // Retornar un valor por defecto que permita continuar
        return [
            'puede_facturar' => true,
            'facturas_disponibles' => -1,
            'facturas_actuales' => 0,
            'limite_facturas' => -1
        ];
    }
}
?>
