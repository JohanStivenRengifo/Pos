-- Modificar el trigger after_imagenes_producto_insert
DELIMITER //

DROP TRIGGER IF EXISTS after_imagenes_producto_insert//

CREATE TRIGGER after_imagenes_producto_insert
AFTER INSERT ON imagenes_producto
FOR EACH ROW
BEGIN
    UPDATE inventario 
    SET tiene_galeria = TRUE,
        imagen_principal = CASE 
            WHEN imagen_principal IS NULL AND NEW.es_principal = 1 
            THEN NEW.ruta 
            ELSE imagen_principal 
        END
    WHERE id = NEW.producto_id;
END//

DROP TRIGGER IF EXISTS after_imagenes_producto_delete//

CREATE TRIGGER after_imagenes_producto_delete
AFTER DELETE ON imagenes_producto
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM imagenes_producto WHERE producto_id = OLD.producto_id) = 0 THEN
        UPDATE inventario 
        SET tiene_galeria = FALSE,
            imagen_principal = NULL
        WHERE id = OLD.producto_id;
    ELSEIF OLD.es_principal = 1 THEN
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
END//

DELIMITER ; 