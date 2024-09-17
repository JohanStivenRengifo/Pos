-- Crear la base de datos solo si no existe
CREATE DATABASE IF NOT EXISTS `$dbName` 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos recién creada
USE `$dbName`;

-- Tabla `users`
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla `bodegas`
CREATE TABLE IF NOT EXISTS `bodegas` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `ubicacion` VARCHAR(255) NOT NULL,
    `capacidad` INT NOT NULL,
    `descripcion` TEXT,
    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_bodegas_user_id` (`user_id`),
    CONSTRAINT `bodegas_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla `configuraciones`
CREATE TABLE IF NOT EXISTS `configuraciones` (
    `user_id` INT NOT NULL,
    `zona_horaria` VARCHAR(100) DEFAULT NULL,
    `moneda` VARCHAR(10) DEFAULT NULL,
    `idioma` VARCHAR(10) DEFAULT NULL,
    `frecuencia_backup` VARCHAR(50) DEFAULT NULL,
    `tipo_backup` VARCHAR(50) DEFAULT NULL,
    `ubicacion_backup` VARCHAR(255) DEFAULT NULL,
    `modulos_activos` JSON DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `configuraciones_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla `empresa`
CREATE TABLE IF NOT EXISTS `empresa` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(255) NOT NULL,
    `direccion` VARCHAR(255) NOT NULL,
    `telefono` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    `user_id` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_empresa_user_id` (`user_id`),
    CONSTRAINT `empresa_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla `productos`
CREATE TABLE IF NOT EXISTS `productos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `codigo_bodega` INT NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `precio_costo` DECIMAL(10,2) NOT NULL,
    `impuestos` DECIMAL(10,2) NOT NULL,
    `precio_venta` DECIMAL(10,2) NOT NULL,
    `cantidad` INT NOT NULL,
    `codigo_barras` VARCHAR(50) NOT NULL,
    `descripcion` TEXT,
    PRIMARY KEY (`id`),
    KEY `fk_productos_bodega_id` (`codigo_bodega`),
    KEY `fk_productos_user_id` (`user_id`),
    CONSTRAINT `productos_fk_bodega` FOREIGN KEY (`codigo_bodega`) REFERENCES `bodegas`(`id`) ON DELETE CASCADE,
    CONSTRAINT `productos_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla `puntos_de_venta`
CREATE TABLE IF NOT EXISTS `puntos_de_venta` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `nombre` VARCHAR(100) NOT NULL,
    `ubicacion` VARCHAR(255) NOT NULL,
    `descripcion` TEXT,
    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_puntos_user_id` (`user_id`),
    CONSTRAINT `puntos_de_venta_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;