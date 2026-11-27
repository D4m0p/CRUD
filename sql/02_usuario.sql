-- ============================================================
-- 02_usuario.sql
-- Crea el usuario de la aplicación con permisos mínimos.
-- Solo puede consultar, insertar y actualizar productos (sin DELETE),
-- porque la aplicación únicamente guarda, edita y busca.
-- Ejecutar después de 01_esquema.sql con un usuario administrador.
-- ============================================================

CREATE USER IF NOT EXISTS 'crud_app'@'localhost' IDENTIFIED BY 'CrudProd#2026';

GRANT SELECT, INSERT, UPDATE ON crud_productos.productos TO 'crud_app'@'localhost';

FLUSH PRIVILEGES;

-- Verificación de los permisos otorgados
SHOW GRANTS FOR 'crud_app'@'localhost';
