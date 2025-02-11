<?php

define('PLAN_BASICO', 'basico');
define('PLAN_PROFESIONAL', 'profesional');
define('PLAN_EMPRESARIAL', 'empresarial');

$PLANES = [
    PLAN_BASICO => [
        'nombre' => 'Básico',
        'precio' => 29900,
        'limite_facturas' => 100,
        'limite_usuarios' => 1,
        'caracteristicas' => [
            'reportes_basicos' => true,
            'soporte_email' => true,
            'control_inventario' => false,
            'api_personalizada' => false,
            'soporte_24_7' => false,
            'personalizacion' => false
        ]
    ],
    PLAN_PROFESIONAL => [
        'nombre' => 'Profesional',
        'precio' => 59900,
        'limite_facturas' => 500,
        'limite_usuarios' => 3,
        'caracteristicas' => [
            'reportes_basicos' => true,
            'soporte_email' => true,
            'control_inventario' => true,
            'api_personalizada' => false,
            'soporte_24_7' => true,
            'personalizacion' => false
        ]
    ],
    PLAN_EMPRESARIAL => [
        'nombre' => 'Empresarial',
        'precio' => 119900,
        'limite_facturas' => -1, // -1 significa ilimitado
        'limite_usuarios' => -1, // -1 significa ilimitado
        'caracteristicas' => [
            'reportes_basicos' => true,
            'soporte_email' => true,
            'control_inventario' => true,
            'api_personalizada' => true,
            'soporte_24_7' => true,
            'personalizacion' => true
        ]
    ]
];

function verificarLimitePlan($empresa_id, $tipo_operacion) {
    global $pdo, $PLANES;
    
    // Obtener el plan actual de la empresa
    $stmt = $pdo->prepare("SELECT plan_suscripcion FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        throw new Exception("Empresa no encontrada");
    }
    
    $plan = $PLANES[$empresa['plan_suscripcion']] ?? null;
    if (!$plan) {
        throw new Exception("Plan no válido");
    }
    
    switch ($tipo_operacion) {
        case 'factura':
            if ($plan['limite_facturas'] === -1) return true;
            
            // Contar facturas del mes actual
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM facturas 
                                 WHERE empresa_id = ? 
                                 AND MONTH(fecha_creacion) = MONTH(CURRENT_DATE())
                                 AND YEAR(fecha_creacion) = YEAR(CURRENT_DATE())");
            $stmt->execute([$empresa_id]);
            $resultado = $stmt->fetch();
            
            return $resultado['total'] < $plan['limite_facturas'];
            
        case 'usuario':
            if ($plan['limite_usuarios'] === -1) return true;
            
            // Contar usuarios activos
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users 
                                 WHERE empresa_id = ? AND estado = 'activo'");
            $stmt->execute([$empresa_id]);
            $resultado = $stmt->fetch();
            
            return $resultado['total'] < $plan['limite_usuarios'];
            
        default:
            throw new Exception("Tipo de operación no válida");
    }
}

function validarDatosEmpresa($datos) {
    $errores = [];
    
    // Validaciones básicas
    if (empty($datos['nit'])) {
        $errores[] = "El NIT es obligatorio";
    }
    
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
?>
