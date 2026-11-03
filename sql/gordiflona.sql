
CREATE DATABASE IF NOT EXISTS gordiflona
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gordiflona;


CREATE TABLE IF NOT EXISTS usuarios (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  nombre    VARCHAR(100) NOT NULL,
  correo    VARCHAR(120) NOT NULL UNIQUE,
  password  VARCHAR(255) NOT NULL,           
  rol       ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  creado    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS productos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(30)  NOT NULL UNIQUE,
  nombre      VARCHAR(80)  NOT NULL,
  descripcion VARCHAR(255),
  precio      DECIMAL(8,2) NOT NULL,
  stock       INT NOT NULL DEFAULT 0,         
  vendidos    INT NOT NULL DEFAULT 0,        
  imagen      VARCHAR(150),
  activo      TINYINT(1) NOT NULL DEFAULT 1
);


CREATE TABLE IF NOT EXISTS pedidos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  folio       VARCHAR(20)  NOT NULL UNIQUE,
  cliente     VARCHAR(100) NOT NULL,
  telefono    VARCHAR(20)  NOT NULL,
  tipo        ENUM('aqui','llevar') NOT NULL DEFAULT 'aqui',
  comentarios VARCHAR(255),
  total       DECIMAL(8,2) NOT NULL,
  estado      ENUM('pendiente','pagado','cancelado') NOT NULL DEFAULT 'pendiente',
  paypal_id   VARCHAR(60) NULL,             
  fecha       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS detalle_pedido (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id       INT NOT NULL,
  producto_id     INT NOT NULL,
  producto        VARCHAR(80) NOT NULL,       
  guiso           VARCHAR(60),
  cantidad        INT NOT NULL,
  precio_unitario DECIMAL(8,2) NOT NULL,
  subtotal        DECIMAL(8,2) NOT NULL,
  FOREIGN KEY (pedido_id)   REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
);


INSERT IGNORE INTO productos (clave, nombre, descripcion, precio, stock, imagen) VALUES
('gordita',   'Gorditas',    'Gordita tradicional de harina o maíz, dorada al comal y rellena de tu guiso favorito.', 20.00, 100, 'imagenes/gordita-menu.jpeg'),
('tortillon', 'Tortillones', 'Tortillones hechos con masa de maíz y rellenos de tu elección.',                        60.00, 100, 'imagenes/tortillon-menu.jpeg'),
('burrito',   'Burritos',    'Burritos rellenos de tu guiso favorito con nuestras salsas especiales.',                22.00, 100, 'imagenes/burrito-menu.jpeg');

-- Administrador de prueba
--   correo:      admin@gordiflona.com
--   contraseña:  Gordiflona2026
INSERT IGNORE INTO usuarios (nombre, correo, password, rol) VALUES
('Administrador', 'admin@gordiflona.com',
 '$2y$12$CCCv5n/yxcfnKZ17luBaMOpI18G1KEVN6Zm4EYAzoJ/V7DaMMslOG',
 'admin');
