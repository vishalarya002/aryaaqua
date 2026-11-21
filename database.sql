-- Initial installation. Import once into an empty database.
CREATE DATABASE IF NOT EXISTS aryaaqua_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aryaaqua_db;
SET NAMES utf8mb4;
SET default_storage_engine=InnoDB;

CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(191) NOT NULL UNIQUE,
 phone VARCHAR(20) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
 is_verified BOOLEAN NOT NULL DEFAULT FALSE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE addresses (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 line1 VARCHAR(200) NOT NULL, line2 VARCHAR(200),
 city VARCHAR(100) NOT NULL, state VARCHAR(100) NOT NULL, pincode CHAR(6) NOT NULL,
 is_default BOOLEAN NOT NULL DEFAULT FALSE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY address_owner (id,user_id),
 FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 type ENUM('bottle','tank') NOT NULL,
 name VARCHAR(120) NOT NULL,
 size_litres DECIMAL(7,2) NOT NULL CHECK (size_litres > 0),
 price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
 stock INT UNSIGNED NOT NULL DEFAULT 0,
 image VARCHAR(255),
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY catalogue (type,is_active,size_litres)
);
CREATE TABLE coupons (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(32) NOT NULL UNIQUE,
 discount_type ENUM('fixed','percent') NOT NULL,
 value DECIMAL(10,2) NOT NULL,
 min_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
 expires_at DATETIME,
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK (value > 0 AND (discount_type <> 'percent' OR value <= 100)),
 CHECK (min_amount >= 0)
);
CREATE TABLE orders (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 order_no VARCHAR(32) NOT NULL UNIQUE,
 subtotal DECIMAL(12,2) NOT NULL, discount DECIMAL(12,2) NOT NULL DEFAULT 0,
 tax DECIMAL(12,2) NOT NULL DEFAULT 0, delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
 total DECIMAL(12,2) NOT NULL,
 payment_method ENUM('cod','upi','card') NOT NULL,
 payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
 order_status ENUM('Placed','Confirmed','Out for delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Placed',
 address_id INT UNSIGNED NOT NULL, address_snapshot TEXT NOT NULL,
 coupon_id INT UNSIGNED, delivery_date DATE, delivery_slot VARCHAR(48),
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY customer_history (user_id,created_at), KEY daily_orders (created_at), KEY deliveries (order_status,delivery_date),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (address_id,user_id) REFERENCES addresses(id,user_id),
 FOREIGN KEY (coupon_id) REFERENCES coupons(id)
);
CREATE TABLE order_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL,
 product_name VARCHAR(120) NOT NULL,
 quantity INT UNSIGNED NOT NULL CHECK (quantity > 0),
 unit_price DECIMAL(10,2) NOT NULL,
 line_total DECIMAL(12,2) NOT NULL,
 delivery_date DATE, delivery_slot VARCHAR(48), delivery_address TEXT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (order_id) REFERENCES orders(id),
 FOREIGN KEY (product_id) REFERENCES products(id)
);
CREATE TABLE password_resets (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY reset_expiry (expires_at), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE login_attempts (
 throttle_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 failures INT UNSIGNED NOT NULL DEFAULT 0, window_started_at DATETIME NOT NULL,
 locked_until DATETIME, KEY lock_expiry (locked_until)
);
START TRANSACTION;
INSERT INTO products (type,name,size_litres,price,stock) VALUES
 ('bottle','1 Litre Bottle',1,20,1200), ('bottle','500 ml Bottle',0.5,12,1200),
 ('bottle','250 ml Bottle',0.25,7,1200), ('bottle','100 ml Bottle',0.10,4,1200);
INSERT INTO products (type,name,size_litres,price,stock) VALUES
 ('tank','100 L Water Tank',100,200,10),
 ('tank','200 L Water Tank',200,400,10),
 ('tank','300 L Water Tank',300,600,10),
 ('tank','400 L Water Tank',400,800,10),
 ('tank','500 L Water Tank',500,1000,10),
 ('tank','600 L Water Tank',600,1200,10),
 ('tank','700 L Water Tank',700,1400,10),
 ('tank','800 L Water Tank',800,1600,10),
 ('tank','900 L Water Tank',900,1800,10),
 ('tank','1000 L Water Tank',1000,2000,10);
INSERT INTO coupons (code,discount_type,value,min_amount) VALUES ('WELCOME5','percent',5,200),('BULK100','fixed',100,1500);
INSERT INTO users (name,email,phone,password_hash,role,is_verified) VALUES ('Local Administrator','admin@aryaaqua.test','0000000000','$2b$12$WQXPkVqngQr8APVrH2.p3.DbMyAgYyw54nSDiFQEwhxzYWnNyBYqS','admin',1);
COMMIT;
