-- ============================================================
-- 01_esquema.sql
-- Crea la base de datos, la tabla de productos y datos de ejemplo.
-- Ejecutar con un usuario administrador de MySQL.
-- ============================================================

CREATE DATABASE IF NOT EXISTS crud_productos
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE crud_productos;

-- Tabla de productos
CREATE TABLE IF NOT EXISTS productos (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    codigo         VARCHAR(20)   NOT NULL,
    nombre         VARCHAR(100)  NOT NULL,
    descripcion    VARCHAR(255)  NULL,
    categoria      VARCHAR(50)   NOT NULL,
    precio         DECIMAL(10,2) NOT NULL,
    cantidad       INT UNSIGNED  NOT NULL DEFAULT 0,
    creado_en      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_productos_codigo (codigo),
    CONSTRAINT chk_productos_precio CHECK (precio >= 0)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Productos de ejemplo (INSERT IGNORE evita duplicados si se ejecuta de nuevo)
INSERT IGNORE INTO productos (codigo, nombre, descripcion, categoria, precio, cantidad) VALUES
    ('PRD-001', 'Laptop 14 pulgadas',   'Procesador de 8 núcleos, 16 GB de RAM y 512 GB SSD', 'Electrónica', 749.99, 12),
    ('PRD-002', 'Silla ergonómica',     'Respaldo de malla con soporte lumbar ajustable',     'Oficina',     189.50,  8),
    ('PRD-003', 'Cafetera de goteo',    'Capacidad para 12 tazas con apagado automático',     'Hogar',        45.00, 20),
    ('PRD-004', 'Café molido 500 g',    'Tostado medio, origen Boquete',                       'Alimentos',     9.75, 60),
    ('PRD-005', 'Camisa de algodón',    NULL,                                                  'Ropa',         24.90, 35);
