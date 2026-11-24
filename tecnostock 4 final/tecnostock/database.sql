-- TecnoStock - Modelo relacional
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS tecnostock CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tecnostock;

CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  nombre     VARCHAR(100) NOT NULL,
  correo     VARCHAR(120) NOT NULL UNIQUE,
  clave      VARCHAR(255) NOT NULL COMMENT 'hash bcrypt (password_hash)',
  estado     TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categorias (
  id_categoria INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(80)  NOT NULL UNIQUE,
  descripcion  VARCHAR(255) NULL,
  estado       TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
  id_producto  INT AUTO_INCREMENT PRIMARY KEY,
  codigo       VARCHAR(30)   NOT NULL UNIQUE,
  nombre       VARCHAR(120)  NOT NULL,
  descripcion  TEXT          NULL,
  precio       DECIMAL(10,2) NOT NULL,
  stock_actual INT           NOT NULL DEFAULT 0,
  stock_minimo INT           NOT NULL DEFAULT 0,
  estado       TINYINT(1)    NOT NULL DEFAULT 1,
  id_categoria INT           NOT NULL,
  CONSTRAINT chk_precio       CHECK (precio >= 0),
  CONSTRAINT chk_stock_actual CHECK (stock_actual >= 0),
  CONSTRAINT chk_stock_minimo CHECK (stock_minimo >= 0),
  CONSTRAINT fk_productos_categoria FOREIGN KEY (id_categoria)
    REFERENCES categorias (id_categoria) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_productos_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimientos (
  id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
  fecha_hora    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tipo          ENUM('entrada','salida') NOT NULL,
  cantidad      INT          NOT NULL,
  observacion   VARCHAR(255) NULL,
  id_producto   INT          NOT NULL,
  id_usuario    INT          NOT NULL,
  CONSTRAINT chk_cantidad CHECK (cantidad > 0),
  CONSTRAINT fk_mov_producto FOREIGN KEY (id_producto)
    REFERENCES productos (id_producto) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_mov_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios (id_usuario) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_mov_fecha (fecha_hora)
) ENGINE=InnoDB;

-- Datos iniciales (usuario de prueba: admin@tecnostock.cl / admin123)
INSERT INTO usuarios (nombre, correo, clave) VALUES
('Encargado de Inventario', 'admin@tecnostock.cl', '$2y$10$K04tDlKOmOwIPuyZwwybxuObTTIVmMbuswF10h4N7VOMoC5z3e1yi');

INSERT INTO categorias (nombre, descripcion) VALUES
('Audio', 'Audífonos, parlantes y micrófonos'),
('Cables y cargadores', 'Cables, adaptadores y cargadores'),
('Almacenamiento', 'Pendrives, discos y tarjetas de memoria'),
('Periféricos', 'Mouse, teclados y accesorios de escritorio'),
('Fundas y protección', 'Fundas, láminas y estuches'),
('Celulares y accesorios', 'Soportes, trípodes y accesorios para celular'),
('Redes', 'Routers, repetidores y cables de red'),
('Computación', 'Accesorios y repuestos para notebook y PC');

INSERT INTO productos (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, id_categoria) VALUES
-- Audio (1)
('AUD-001', 'Audífonos Bluetooth', 'Audífonos inalámbricos con estuche de carga', 19990, 25, 10, 1),
('AUD-002', 'Audífonos con cable 3.5mm', 'Audífonos básicos con control de volumen', 4990, 40, 15, 1),
('AUD-003', 'Parlante Bluetooth portátil', 'Parlante resistente al agua, 10 horas de batería', 24990, 14, 8, 1),
('AUD-004', 'Audífonos gamer con micrófono', 'Diadema con sonido envolvente y micrófono plegable', 29990, 6, 8, 1),
('AUD-005', 'Micrófono de escritorio USB', 'Micrófono condensador para streaming y clases', 17990, 9, 5, 1),
-- Cables y cargadores (2)
('CAB-001', 'Cable USB-C 1m', 'Cable de carga y datos USB-C', 3990, 8, 15, 2),
('CAB-002', 'Cable Lightning 1m', 'Cable de carga rápida certificado', 5990, 22, 10, 2),
('CAB-003', 'Cable HDMI 2m', 'Cable HDMI 2.0 compatible con 4K', 6990, 18, 8, 2),
('CAB-004', 'Cargador de pared USB 20W', 'Cargador rápido con entrada USB-C', 9990, 3, 10, 2),
('CAB-005', 'Cargador portátil 10000mAh', 'Batería externa con dos puertos USB', 15990, 11, 6, 2),
('CAB-006', 'Adaptador USB-C a HDMI', 'Adaptador para conectar notebook a TV o proyector', 12990, 7, 5, 2),
-- Almacenamiento (3)
('ALM-001', 'Pendrive 64GB', 'Memoria USB 3.0', 6990, 30, 10, 3),
('ALM-002', 'Pendrive 128GB', 'Memoria USB 3.1 de alta velocidad', 11990, 16, 8, 3),
('ALM-003', 'Tarjeta microSD 128GB', 'Tarjeta clase 10 con adaptador SD', 9990, 4, 10, 3),
('ALM-004', 'Disco duro externo 1TB', 'Disco portátil USB 3.0', 49990, 10, 4, 3),
('ALM-005', 'SSD externo 500GB', 'Unidad de estado sólido portátil USB-C', 59990, 5, 3, 3),
-- Periféricos (4)
('PER-001', 'Mouse inalámbrico', 'Mouse óptico 2.4GHz', 8990, 5, 5, 4),
('PER-002', 'Teclado USB', 'Teclado en español con teclado numérico', 15990, 20, 8, 4),
('PER-003', 'Mouse gamer RGB', 'Mouse con 6 botones y sensor de 7200 DPI', 14990, 13, 6, 4),
('PER-004', 'Mousepad XL', 'Superficie extendida antideslizante 80x30 cm', 7990, 25, 10, 4),
('PER-005', 'Webcam Full HD', 'Cámara 1080p con micrófono integrado', 22990, 2, 5, 4),
('PER-006', 'Hub USB 4 puertos', 'Hub USB 3.0 con cable de 30 cm', 8490, 17, 6, 4),
-- Fundas y protección (5)
('FUN-001', 'Funda notebook 15"', 'Funda acolchada impermeable', 12990, 12, 6, 5),
('FUN-002', 'Funda para tablet 10"', 'Funda con soporte y cierre magnético', 8990, 15, 6, 5),
('FUN-003', 'Lámina protectora de vidrio', 'Vidrio templado 9H para celular', 3490, 50, 20, 5),
('FUN-004', 'Estuche para audífonos', 'Estuche rígido con mosquetón', 4490, 0, 5, 5),
-- Celulares y accesorios (6)
('CEL-001', 'Soporte para celular de auto', 'Soporte magnético para rejilla de ventilación', 6990, 19, 8, 6),
('CEL-002', 'Trípode flexible para celular', 'Trípode con patas flexibles y control remoto', 9990, 12, 5, 6),
('CEL-003', 'Aro de luz LED', 'Aro de luz de 10" con trípode regulable', 14990, 8, 4, 6),
-- Redes (7)
('RED-001', 'Router WiFi AC1200', 'Router de doble banda con 4 antenas', 29990, 9, 4, 7),
('RED-002', 'Repetidor WiFi', 'Extiende la señal inalámbrica hasta 300 Mbps', 19990, 6, 4, 7),
('RED-003', 'Cable de red Cat6 5m', 'Cable Ethernet Cat6 con conectores RJ45', 4990, 27, 10, 7),
('RED-004', 'Adaptador WiFi USB', 'Adaptador inalámbrico USB de doble banda', 9990, 14, 6, 7),
-- Productos adicionales
('AUD-006', 'Audífonos in-ear deportivos', 'Resistentes al sudor, con ganchos de sujeción', 12990, 18, 8, 1),
('CAB-007', 'Alargador eléctrico 4 enchufes', 'Alargador con protección contra sobrecarga, 1.5 m', 8990, 15, 6, 2),
('PER-007', 'Teclado mecánico compacto', 'Teclado 60% con switches rojos y retroiluminación', 34990, 6, 4, 4),
('CEL-004', 'Cargador inalámbrico 15W', 'Base de carga rápida compatible con Qi', 13990, 11, 5, 6),
('RED-005', 'Switch de red 8 puertos', 'Switch Gigabit no administrable', 17990, 5, 3, 7),
-- Computación (8)
('COM-001', 'Soporte para notebook de aluminio', 'Soporte plegable con altura regulable', 17990, 10, 4, 8),
('COM-002', 'Base refrigerante para notebook', 'Base con dos ventiladores silenciosos', 19990, 7, 4, 8),
('COM-003', 'Kit de limpieza para pantallas', 'Spray y paño de microfibra', 3990, 35, 12, 8),
('COM-004', 'Lector de tarjetas USB', 'Lector SD y microSD USB 3.0', 5990, 21, 8, 8),
('COM-005', 'Cargador universal para notebook', 'Cargador 65W con puntas intercambiables', 27990, 4, 5, 8);

-- Producto descontinuado (inactivo), para probar el filtro "Incluir inactivos"
INSERT INTO productos (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, estado, id_categoria) VALUES
('ALM-006', 'Pendrive 8GB', 'Modelo descontinuado', 2990, 3, 5, 0, 3);

-- Entrada inicial de stock (solo productos con existencias)
INSERT INTO movimientos (tipo, cantidad, observacion, id_producto, id_usuario)
SELECT 'entrada', stock_actual, 'Stock inicial', id_producto, 1 FROM productos WHERE stock_actual > 0;
