-- Modificar la tabla imagenes_producto
DROP TABLE IF EXISTS imagenes_producto;

CREATE TABLE imagenes_producto (
    id INT PRIMARY KEY AUTO_INCREMENT,
    producto_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
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