-- Tabla de Usuarios
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    rol ENUM('admin', 'vendedor', 'inventario') NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Categorías
CREATE TABLE categorias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Departamentos
CREATE TABLE departamentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Inventario (Productos)
CREATE TABLE inventario (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    codigo_barras VARCHAR(13) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    stock INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 0,
    unidad_medida VARCHAR(10) NOT NULL,
    precio_costo DECIMAL(10,2) NOT NULL,
    margen_ganancia DECIMAL(5,2) NOT NULL,
    impuesto DECIMAL(5,2) NOT NULL,
    precio_venta DECIMAL(10,2) NOT NULL,
    categoria_id INT NOT NULL,
    departamento_id INT NOT NULL,
    imagen_principal VARCHAR(255) DEFAULT NULL,
    tiene_galeria BOOLEAN DEFAULT FALSE,
    fecha_ingreso DATETIME NOT NULL,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    
    INDEX idx_codigo_barras (codigo_barras),
    INDEX idx_nombre (nombre),
    INDEX idx_categoria (categoria_id),
    INDEX idx_departamento (departamento_id),
    INDEX idx_tiene_galeria (tiene_galeria),
    INDEX idx_imagen_principal (imagen_principal),
    
    FOREIGN KEY (user_id) 
        REFERENCES usuarios(id) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
        
    FOREIGN KEY (categoria_id) 
        REFERENCES categorias(id) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
        
    FOREIGN KEY (departamento_id) 
        REFERENCES departamentos(id) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Imágenes de Productos
CREATE TABLE imagenes_producto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    producto_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    es_principal BOOLEAN DEFAULT FALSE,
    orden INT DEFAULT 0,
    tamano INT NOT NULL COMMENT 'Tamaño en bytes',
    tipo_mime VARCHAR(50) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (producto_id) 
        REFERENCES inventario(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
        
    INDEX idx_producto (producto_id),
    INDEX idx_es_principal (es_principal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Historial de Cambios de Productos
CREATE TABLE historial_productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    producto_id INT NOT NULL,
    user_id INT NOT NULL,
    tipo_cambio ENUM('creacion', 'modificacion', 'eliminacion', 'imagen_agregada', 'imagen_eliminada') NOT NULL,
    detalle JSON NOT NULL,
    fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (producto_id) 
        REFERENCES inventario(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
        
    FOREIGN KEY (user_id) 
        REFERENCES usuarios(id) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
        
    INDEX idx_producto_fecha (producto_id, fecha_cambio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggers para Inventario
DELIMITER //

CREATE TRIGGER before_inventario_insert 
BEFORE INSERT ON inventario
FOR EACH ROW 
BEGIN
    IF NEW.stock < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El stock no puede ser negativo';
    END IF;
    
    IF NEW.precio_costo <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El precio de costo debe ser mayor a 0';
    END IF;
    
    IF NEW.precio_venta <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El precio de venta debe ser mayor a 0';
    END IF;
END//

CREATE TRIGGER after_inventario_insert
AFTER INSERT ON inventario
FOR EACH ROW
BEGIN
    INSERT INTO historial_productos (producto_id, user_id, tipo_cambio, detalle)
    VALUES (NEW.id, NEW.user_id, 'creacion', JSON_OBJECT(
        'codigo_barras', NEW.codigo_barras,
        'nombre', NEW.nombre,
        'stock', NEW.stock,
        'precio_costo', NEW.precio_costo,
        'precio_venta', NEW.precio_venta
    ));
END//

-- Triggers para Imágenes
CREATE TRIGGER after_imagenes_producto_insert
AFTER INSERT ON imagenes_producto
FOR EACH ROW
BEGIN
    UPDATE inventario 
    SET tiene_galeria = TRUE,
        imagen_principal = CASE 
            WHEN imagen_principal IS NULL AND NEW.es_principal = TRUE 
            THEN NEW.ruta 
            ELSE imagen_principal 
        END
    WHERE id = NEW.producto_id;
    
    INSERT INTO historial_productos (producto_id, user_id, tipo_cambio, detalle)
    SELECT 
        NEW.producto_id,
        i.user_id,
        'imagen_agregada',
        JSON_OBJECT(
            'imagen_id', NEW.id,
            'nombre_archivo', NEW.nombre_archivo,
            'es_principal', NEW.es_principal
        )
    FROM inventario i
    WHERE i.id = NEW.producto_id;
END//

CREATE TRIGGER after_imagenes_producto_delete
AFTER DELETE ON imagenes_producto
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM imagenes_producto WHERE producto_id = OLD.producto_id) = 0 THEN
        UPDATE inventario 
        SET tiene_galeria = FALSE,
            imagen_principal = NULL
        WHERE id = OLD.producto_id;
    ELSEIF OLD.es_principal = TRUE THEN
        UPDATE inventario 
        SET imagen_principal = (
            SELECT ruta 
            FROM imagenes_producto 
            WHERE producto_id = OLD.producto_id 
            ORDER BY orden ASC, id ASC 
            LIMIT 1
        )
        WHERE id = OLD.producto_id;
    END IF;
    
    INSERT INTO historial_productos (producto_id, user_id, tipo_cambio, detalle)
    SELECT 
        OLD.producto_id,
        i.user_id,
        'imagen_eliminada',
        JSON_OBJECT(
            'imagen_id', OLD.id,
            'nombre_archivo', OLD.nombre_archivo,
            'es_principal', OLD.es_principal
        )
    FROM inventario i
    WHERE i.id = OLD.producto_id;
END//

DELIMITER ;

-- Vista para Productos con Imágenes
CREATE OR REPLACE VIEW v_productos_imagenes AS
SELECT 
    i.*,
    GROUP_CONCAT(
        DISTINCT 
        CASE 
            WHEN ip.es_principal = 1 THEN ip.ruta
        END
    ) as imagen_principal,
    GROUP_CONCAT(
        DISTINCT 
        CASE 
            WHEN ip.es_principal = 0 THEN ip.ruta
        END
        ORDER BY ip.orden ASC
        SEPARATOR '|'
    ) as imagenes_galeria,
    COUNT(DISTINCT ip.id) as total_imagenes
FROM 
    inventario i
LEFT JOIN 
    imagenes_producto ip ON i.id = ip.producto_id
GROUP BY 
    i.id;

-- Procedimiento para Gestionar Imagen Principal
DELIMITER //

CREATE PROCEDURE actualizar_imagen_principal(IN p_producto_id INT, IN p_imagen_id INT)
BEGIN
    START TRANSACTION;
    
    -- Quitamos la marca de principal a todas las imágenes del producto
    UPDATE imagenes_producto 
    SET es_principal = FALSE 
    WHERE producto_id = p_producto_id;
    
    -- Marcamos la nueva imagen principal
    UPDATE imagenes_producto 
    SET es_principal = TRUE,
        orden = 0
    WHERE id = p_imagen_id 
    AND producto_id = p_producto_id;
    
    -- Actualizamos la referencia en la tabla de inventario
    UPDATE inventario 
    SET imagen_principal = (
        SELECT ruta 
        FROM imagenes_producto 
        WHERE id = p_imagen_id
    )
    WHERE id = p_producto_id;
    
    COMMIT;
END//

DELIMITER ;

-- Permisos necesarios
GRANT EXECUTE ON PROCEDURE actualizar_imagen_principal TO 'usuario'@'localhost';
GRANT SELECT ON v_productos_imagenes TO 'usuario'@'localhost';