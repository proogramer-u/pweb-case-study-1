-- Additive: never drops or replaces existing catalog data. InnoDB required.
CREATE TABLE IF NOT EXISTS categories (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS products (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 category_id INT NOT NULL, name VARCHAR(150) NOT NULL, description TEXT NULL,
 price DECIMAL(12,2) NOT NULL, image VARCHAR(255) NULL,
 is_available TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS users (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL,
 role ENUM('owner','cashier','customer') NOT NULL DEFAULT 'customer',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS orders (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, order_number VARCHAR(40) NOT NULL UNIQUE,
 user_id INT NULL, guest_name VARCHAR(150) NULL, guest_email VARCHAR(190) NULL,
 guest_phone VARCHAR(40) NULL, cashier_id INT NULL,
 subtotal DECIMAL(12,2) NOT NULL, discount_amount DECIMAL(12,2) NOT NULL,
 total_amount DECIMAL(12,2) NOT NULL,
 status ENUM('pending','processing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
 checkout_key CHAR(64) NOT NULL UNIQUE, guest_token_hash CHAR(64) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX orders_user(user_id), INDEX orders_cashier(cashier_id),
 INDEX orders_status_date(status,created_at), INDEX orders_date(created_at),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS order_items (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL,
 product_id INT NULL, product_name VARCHAR(150) NOT NULL,
 quantity INT NOT NULL, unit_price DECIMAL(12,2) NOT NULL, line_total DECIMAL(12,2) NOT NULL,
 FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
 INDEX items_product(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reviews (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
 product_id INT NOT NULL, rating TINYINT NOT NULL, comment TEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY reviews_user_product(user_id,product_id),
 INDEX reviews_product(product_id),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
 CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
