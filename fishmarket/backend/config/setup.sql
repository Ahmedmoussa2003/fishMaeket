-- ================================================
-- Fish Market Database Setup
-- Run this in phpMyAdmin or MySQL CLI
-- ================================================

CREATE DATABASE IF NOT EXISTS fish_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fish_market;

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100) DEFAULT 'Nouakchott',
    role ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- FISH / PRODUCTS TABLE
CREATE TABLE IF NOT EXISTS fish (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(150),
    tag VARCHAR(100),
    price DECIMAL(10,2) NOT NULL,
    stock DECIMAL(10,2) DEFAULT 0,
    unit VARCHAR(20) DEFAULT 'kg',
    image_url VARCHAR(500),
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ORDERS TABLE
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','delivering','delivered','cancelled') DEFAULT 'pending',
    delivery_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ORDER ITEMS TABLE
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    fish_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (fish_id) REFERENCES fish(id)
);

-- ADMIN TABLE
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── SAMPLE DATA ──────────────────────────────

INSERT INTO fish (name, location, tag, price, stock, image_url) VALUES
('Fresh Sea Bream',   'Nouadhibou Port',  'Caught Today',  450, 50, 'https://cdn.pixabay.com/photo/2016/11/29/07/16/animal-1868916_640.jpg'),
('Atlantic Grouper',  'Nouakchott Coast', 'Caught Today',  520, 30, 'https://cdn.pixabay.com/photo/2019/09/16/16/24/fish-4481796_640.jpg'),
('Red Mullet',        'Nouadhibou Port',  'Morning Catch', 380, 40, 'https://cdn.pixabay.com/photo/2014/09/10/23/53/fish-441895_640.jpg'),
('Yellow Fin Tuna',   'Atlantic Waters',  'Caught Today',  680, 20, 'https://cdn.pixabay.com/photo/2020/01/31/07/10/fish-4807994_640.jpg');

-- Default admin (password: admin123)
INSERT INTO admins (username, password) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Sample user (password: 123456)
INSERT INTO users (name, email, phone, password, city) VALUES
('Ahmed Test', 'ahmed@test.com', '+222 12345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nouakchott');
