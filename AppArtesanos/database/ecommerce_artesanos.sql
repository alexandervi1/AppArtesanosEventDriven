-- ============================================================
--  Base de datos: ecommerce_artesanos
--  Proyecto: Ecommerce Artesanos (Angular + Tailwind frontend)
--  Objetivo: Esquema MySQL listo para importar en XAMPP
--  Compatibilidad probada con MySQL 8.x / MariaDB 10.5+
-- ============================================================

DROP DATABASE IF EXISTS `ecommerce_artesanos`;
CREATE DATABASE `ecommerce_artesanos`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `ecommerce_artesanos`;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ------------------------------------------------------------
-- Tablas maestras
-- ------------------------------------------------------------

CREATE TABLE categories (
  category_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80) NOT NULL,
  slug          VARCHAR(80) NOT NULL,
  description   TEXT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_categories_name UNIQUE (name),
  CONSTRAINT uq_categories_slug UNIQUE (slug)
) ENGINE = InnoDB;

CREATE TABLE artisans (
  artisan_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  workshop_name  VARCHAR(120) NOT NULL,
  contact_name   VARCHAR(120) NULL,
  email          VARCHAR(120) NULL,
  phone          VARCHAR(40) NULL,
  region         VARCHAR(100) NULL,
  bio            TEXT NULL,
  instagram      VARCHAR(150) NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_artisans_workshop UNIQUE (workshop_name),
  CONSTRAINT uq_artisans_email UNIQUE (email)
) ENGINE = InnoDB;

CREATE TABLE products (
  product_id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku           VARCHAR(30) NOT NULL,
  name          VARCHAR(150) NOT NULL,
  category_id   INT UNSIGNED NOT NULL,
  artisan_id    INT UNSIGNED NOT NULL,
  price         DECIMAL(10,2) NOT NULL,
  stock         INT UNSIGNED NOT NULL DEFAULT 0,
  badge_label   VARCHAR(40) NULL,
  description   TEXT NULL,
  image_url     VARCHAR(255) NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_products_sku UNIQUE (sku),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id)
    REFERENCES categories (category_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_products_artisan FOREIGN KEY (artisan_id)
    REFERENCES artisans (artisan_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE INDEX idx_products_category ON products (category_id);
CREATE INDEX idx_products_artisan ON products (artisan_id);

CREATE TABLE inventory_movements (
  movement_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id      INT UNSIGNED NOT NULL,
  quantity_change INT NOT NULL,
  movement_type   ENUM('initial','adjustment','sale','return','purchase') NOT NULL DEFAULT 'adjustment',
  reference       VARCHAR(120) NULL,
  note            VARCHAR(255) NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inventory_product FOREIGN KEY (product_id)
    REFERENCES products (product_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE = InnoDB;

-- ------------------------------------------------------------
-- Clientes, carritos y pedidos
-- ------------------------------------------------------------

CREATE TABLE customers (
  customer_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name   VARCHAR(80) NOT NULL,
  last_name    VARCHAR(80) NOT NULL,
  email        VARCHAR(150) NOT NULL,
  phone        VARCHAR(40) NULL,
  province     VARCHAR(80) NULL,
  city         VARCHAR(80) NULL,
  address_line VARCHAR(180) NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_customers_email UNIQUE (email)
) ENGINE = InnoDB;

CREATE TABLE carts (
  cart_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NULL,
  status      ENUM('open','converted','abandoned') NOT NULL DEFAULT 'open',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_carts_customer FOREIGN KEY (customer_id)
    REFERENCES customers (customer_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE cart_items (
  cart_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id      INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  quantity     INT UNSIGNED NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id)
    REFERENCES carts (cart_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id)
    REFERENCES products (product_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT uq_cart_items UNIQUE (cart_id, product_id)
) ENGINE = InnoDB;

CREATE INDEX idx_cart_items_product ON cart_items (product_id);

CREATE TABLE orders (
  order_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id    INT UNSIGNED NOT NULL,
  cart_id        INT UNSIGNED NULL,
  order_number   VARCHAR(40) NOT NULL,
  status         ENUM('pending','paid','fulfilled','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  payment_status ENUM('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending',
  subtotal       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tax            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  shipping_cost  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency       CHAR(3) NOT NULL DEFAULT 'USD',
  notes          TEXT NULL,
  placed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_orders_number UNIQUE (order_number),
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id)
    REFERENCES customers (customer_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_orders_cart FOREIGN KEY (cart_id)
    REFERENCES carts (cart_id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX idx_orders_customer ON orders (customer_id);

CREATE TABLE order_items (
  order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id      INT UNSIGNED NOT NULL,
  product_id    INT UNSIGNED NOT NULL,
  quantity      INT UNSIGNED NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  line_total    DECIMAL(10,2) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id)
    REFERENCES orders (order_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id)
    REFERENCES products (product_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT uq_order_items UNIQUE (order_id, product_id)
) ENGINE = InnoDB;

CREATE INDEX idx_order_items_product ON order_items (product_id);

-- ------------------------------------------------------------
-- Datos iniciales alineados al estado del frontend
-- ------------------------------------------------------------

START TRANSACTION;

INSERT INTO categories (name, slug, description) VALUES
  ('Cerámica', 'ceramica', 'Piezas trabajadas a mano en arcilla y esmaltes tradicionales.'),
  ('Textiles', 'textiles', 'Tejidos en telar, fibras naturales y prendas artesanales.'),
  ('Joyería', 'joyeria', 'Accesorios en metales nobles y técnicas ancestrales.'),
  ('Decoración', 'decoracion', 'Objetos para el hogar con identidad cultural.'),
  ('Hogar', 'hogar', 'Artículos utilitarios hechos con maderas y fibras locales.');

INSERT INTO artisans (workshop_name, contact_name, email, phone, region, bio, instagram) VALUES
  ('Taller Ñukanchik', 'Luz Cañari', 'contacto@nukanchik.art', '+593991002001', 'Cañar', 'Colectivo de ceramistas cañaris que produce vajilla libre de plomo.', 'https://instagram.com/nukanchik'),
  ('Manos de Otavalo', 'Gustavo Chango', 'ventas@manosdeotavalo.ec', '+593983456780', 'Imbabura', 'Familia textilera que trabaja con fibras de alpaca y técnicas de telar de pedal.', 'https://instagram.com/manosdeotavalo'),
  ('Filigrana Saraguro', 'Andrea Sarango', 'contacto@filigranasaraguro.ec', '+593979998877', 'Loja', 'Orfebres especializados en filigrana de plata con simbolismos andinos.', 'https://instagram.com/filigranasaraguro'),
  ('Taller Yasuní', 'Darío Grefa', 'taller@yasuniamazonia.ec', '+593987650321', 'Orellana', 'Colectivo amazónico que trabaja fibras vegetales recolectadas de forma sostenible.', 'https://instagram.com/talleryasuni'),
  ('Bosque Vivo', 'María Coba', 'hola@bosquevivo.ec', '+593992204411', 'Pichincha', 'Taller de carpintería responsable que utiliza maderas certificadas del noroccidente.', 'https://instagram.com/bosquevivoartesanal'),
  ('Riberas del Lago', 'Nicolas Quilumba', 'ventas@riberasdellago.ec', '+593987700512', 'Imbabura', 'Artesanos que combinan totora y luz ambiental inspirada en el Lago San Pablo.', 'https://instagram.com/riberasdellago');

-- Inserción de productos respetando la información del frontend
INSERT INTO products (sku, name, category_id, artisan_id, price, stock, badge_label, description, image_url)
SELECT 'CER-001',
       'Taza de Cerámica Cañari',
       c.category_id,
       a.artisan_id,
       18.50,
       24,
       'Nuevo',
       'Taza esmaltada a mano con motivos cañaris y esmaltes libres de plomo.',
       'https://images.unsplash.com/photo-1597423494035-31d6823c5eb2?auto=format&fit=crop&w=900&q=80'
FROM categories c
JOIN artisans a ON a.workshop_name = 'Taller Ñukanchik'
WHERE c.slug = 'ceramica';

INSERT INTO products (sku, name, category_id, artisan_id, price, stock, badge_label, description, image_url)
SELECT 'TXT-104',
       'Poncho de Alpaca Otavalo',
       c.category_id,
       a.artisan_id,
       95.00,
       12,
       'Destacado',
       'Poncho tejido en telar tradicional con fibras de alpaca de comercio justo.',
       'https://images.unsplash.com/photo-1601297183305-d87bbae2bc33?auto=format&fit=crop&w=900&q=80'
FROM categories c
JOIN artisans a ON a.workshop_name = 'Manos de Otavalo'
WHERE c.slug = 'textiles';

INSERT INTO products (sku, name, category_id, artisan_id, price, stock, badge_label, description, image_url)
SELECT 'JYA-203',
       'Collar de Filigrana Saraguro',
       c.category_id,
       a.artisan_id,
       72.00,
       8,
       NULL,
       'Pieza en plata fina con detalles inspirados en la cosmovisión andina.',
       'https://images.unsplash.com/photo-1530023367847-a683933f4177?auto=format&fit=crop&w=900&q=80'
FROM categories c
JOIN artisans a ON a.workshop_name = 'Filigrana Saraguro'
WHERE c.slug = 'joyeria';

INSERT INTO products (sku, name, category_id, artisan_id, price, stock, badge_label, description, image_url)
SELECT 'DEC-045',
       'Cesta Amazónica Fibras Naturales',
       c.category_id,
       a.artisan_id,
       42.00,
       18,
       NULL,
       'Tejida a mano con fibras de chambira recolectadas de forma sostenible.',
       'https://images.unsplash.com/photo-1611080358262-8cc6e0d70094?auto=format&fit=crop&w=900&q=80'
FROM categories c
JOIN artisans a ON a.workshop_name = 'Taller Yasuní'
WHERE c.slug = 'decoracion';

INSERT INTO products (sku, name, category_id, artisan_id, price, stock, badge_label, description, image_url)
SELECT 'HOG-310',
       'Cuenco Tallado en Madera de Laurel',
       c.category_id,
       a.artisan_id,
       38.00,
       4,
       NULL,
       'Tallado artesanal con acabado natural en aceites vegetales, apto para alimentos.',
       'https://images.unsplash.com/photo-1615485925215-573fae07b6f5?auto=format&fit=crop&w=900&q=80'
FROM categories c
JOIN artisans a ON a.workshop_name = 'Bosque Vivo'
WHERE c.slug = 'hogar';

INSERT INTO products (sku, name, category_id, artisan_id, price, stock, badge_label, description, image_url)
SELECT 'MOD-220',
       'Lámpara de Totora Tejida',
       c.category_id,
       a.artisan_id,
       120.00,
       6,
       NULL,
       'Estructura tejida en totora del Lago San Pablo con luz cálida regulable.',
       'https://images.unsplash.com/photo-1473181488821-2d23949a045a?auto=format&fit=crop&w=900&q=80'
FROM categories c
JOIN artisans a ON a.workshop_name = 'Riberas del Lago'
WHERE c.slug = 'decoracion';

-- Movimientos iniciales de inventario que reflejan las existencias actuales
INSERT INTO inventory_movements (product_id, quantity_change, movement_type, reference, note)
SELECT product_id, stock, 'initial', 'seed-data', 'Carga inicial de catálogo'
FROM products;

-- Clientes de ejemplo
INSERT INTO customers (first_name, last_name, email, phone, province, city, address_line) VALUES
  ('María', 'Quishpe', 'maria.quishpe@example.com', '+593991112233', 'Pichincha', 'Quito', 'Av. 12 de Octubre N24-123'),
  ('Diego', 'Andino', 'diego.andino@example.com', '+593984567890', 'Azuay', 'Cuenca', 'Calle Larga 10-55'),
  ('Elena', 'Sisa', 'elena.sisa@example.com', '+593987001122', 'Imbabura', 'Otavalo', 'Comunidad Peguche s/n');

-- Carritos y sus ítems (compatibles con la estructura del frontend)
INSERT INTO carts (customer_id, status) VALUES
  ((SELECT customer_id FROM customers WHERE email = 'maria.quishpe@example.com'), 'converted'),
  ((SELECT customer_id FROM customers WHERE email = 'diego.andino@example.com'), 'open');

INSERT INTO cart_items (cart_id, product_id, quantity)
VALUES
  ((SELECT cart_id FROM carts c JOIN customers cu ON cu.customer_id = c.customer_id WHERE cu.email = 'maria.quishpe@example.com' ORDER BY c.created_at LIMIT 1),
   (SELECT product_id FROM products WHERE sku = 'CER-001'), 2),
  ((SELECT cart_id FROM carts c JOIN customers cu ON cu.customer_id = c.customer_id WHERE cu.email = 'maria.quishpe@example.com' ORDER BY c.created_at LIMIT 1),
   (SELECT product_id FROM products WHERE sku = 'TXT-104'), 1),
  ((SELECT cart_id FROM carts c JOIN customers cu ON cu.customer_id = c.customer_id WHERE cu.email = 'diego.andino@example.com' ORDER BY c.created_at LIMIT 1),
   (SELECT product_id FROM products WHERE sku = 'HOG-310'), 1);

-- Pedido de ejemplo generado a partir del carrito convertido
INSERT INTO orders (customer_id, cart_id, order_number, status, payment_status, subtotal, tax, shipping_cost, total, currency, notes, placed_at)
VALUES (
  (SELECT customer_id FROM customers WHERE email = 'maria.quishpe@example.com'),
  (SELECT cart_id FROM carts c JOIN customers cu ON cu.customer_id = c.customer_id WHERE cu.email = 'maria.quishpe@example.com' ORDER BY c.created_at LIMIT 1),
  'AE-20251001-001',
  'paid',
  'paid',
  132.00,
  15.84,
  5.00,
  152.84,
  'USD',
  'Pedido creado desde el ecommerce artesanal.',
  '2025-10-01 15:20:00'
);

INSERT INTO order_items (order_id, product_id, quantity, unit_price, line_total)
VALUES
  ((SELECT order_id FROM orders WHERE order_number = 'AE-20251001-001'),
   (SELECT product_id FROM products WHERE sku = 'CER-001'), 2, 18.50, 37.00),
  ((SELECT order_id FROM orders WHERE order_number = 'AE-20251001-001'),
   (SELECT product_id FROM products WHERE sku = 'TXT-104'), 1, 95.00, 95.00);

COMMIT;

-- ------------------------------------------------------------
-- Vistas de consulta rápida para el dashboard del frontend
-- ------------------------------------------------------------

CREATE OR REPLACE VIEW vw_inventory_overview AS
SELECT
  COUNT(*) AS total_products,
  SUM(stock) AS total_units,
  ROUND(SUM(stock * price), 2) AS total_value_usd
FROM products
WHERE is_active = 1;

CREATE OR REPLACE VIEW vw_low_stock_products AS
SELECT
  p.product_id,
  p.sku,
  p.name,
  a.workshop_name AS artisan,
  p.stock,
  p.price
FROM products p
JOIN artisans a ON a.artisan_id = p.artisan_id
WHERE p.stock <= 5
ORDER BY p.stock ASC;

CREATE OR REPLACE VIEW vw_product_catalog AS
SELECT
  p.product_id,
  p.sku,
  p.name,
  c.name AS category,
  a.workshop_name AS artisan,
  p.price,
  p.stock,
  IFNULL(p.badge_label, '') AS badge_label,
  p.description,
  p.image_url
FROM products p
JOIN categories c ON c.category_id = p.category_id
JOIN artisans a ON a.artisan_id = p.artisan_id
WHERE p.is_active = 1
ORDER BY c.name, p.name;

CREATE OR REPLACE VIEW vw_category_totals AS
SELECT
  c.name AS category,
  COUNT(p.product_id) AS product_count,
  SUM(p.stock) AS units_available,
  ROUND(SUM(p.price * p.stock), 2) AS inventory_value
FROM categories c
LEFT JOIN products p ON p.category_id = c.category_id AND p.is_active = 1
GROUP BY c.category_id, c.name
ORDER BY c.name;

-- ------------------------------------------------------------
-- Procedimiento opcional para registrar productos desde el panel
-- ------------------------------------------------------------

DELIMITER $$
CREATE OR REPLACE PROCEDURE sp_register_product (
  IN p_sku VARCHAR(30),
  IN p_name VARCHAR(150),
  IN p_category_slug VARCHAR(80),
  IN p_artisan_workshop VARCHAR(120),
  IN p_price DECIMAL(10,2),
  IN p_stock INT,
  IN p_badge_label VARCHAR(40),
  IN p_description TEXT,
  IN p_image_url VARCHAR(255)
)
BEGIN
  DECLARE v_category_id INT UNSIGNED;
  DECLARE v_artisan_id INT UNSIGNED;

  SELECT category_id INTO v_category_id
  FROM categories
  WHERE slug = p_category_slug
  LIMIT 1;

  IF v_category_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'La categoría indicada no existe.';
  END IF;

  SELECT artisan_id INTO v_artisan_id
  FROM artisans
  WHERE workshop_name = p_artisan_workshop
  LIMIT 1;

  IF v_artisan_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El taller/artesano indicado no existe.';
  END IF;

  INSERT INTO products (sku, name, category_id, artisan_id, price, stock, badge_label, description, image_url)
  VALUES (UPPER(p_sku), p_name, v_category_id, v_artisan_id, p_price, p_stock, p_badge_label, p_description, p_image_url);

  INSERT INTO inventory_movements (product_id, quantity_change, movement_type, reference, note)
  VALUES (LAST_INSERT_ID(), p_stock, 'initial', 'sp_register_product', 'Alta inicial desde el panel de administración');
END$$
DELIMITER ;

-- ------------------------------------------------------------
-- Fin del script
-- ------------------------------------------------------------
