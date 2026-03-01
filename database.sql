-- ============================================================
-- DriveEasy Car Rentals - Database Schema & Seed Data
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- Usage: Import via phpMyAdmin or run: mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS driveeasy_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE driveeasy_db;

-- ============================================================
-- TABLE: users
-- Stores registered customers and admin accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: cars
-- Vehicle catalogue with pricing and availability
-- ============================================================
CREATE TABLE IF NOT EXISTS cars (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    brand         VARCHAR(50)   NOT NULL,
    model         VARCHAR(50)   NOT NULL,
    year          YEAR          NOT NULL,
    type          ENUM('sedan','SUV','MPV','sports') NOT NULL,
    daily_rate    DECIMAL(10,2) NOT NULL,
    status        ENUM('available','unavailable') NOT NULL DEFAULT 'available',
    image_path    VARCHAR(255)  DEFAULT 'assets/images/placeholder.jpg',
    description   TEXT,
    seats         TINYINT       NOT NULL DEFAULT 5,
    transmission  ENUM('automatic','manual') NOT NULL DEFAULT 'automatic',
    fuel_type     VARCHAR(30)   NOT NULL DEFAULT 'Petrol',
    mileage       VARCHAR(30)   DEFAULT 'Unlimited',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: bookings
-- Links users to cars with dates, cost, and status
-- ============================================================
CREATE TABLE IF NOT EXISTS bookings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT           NOT NULL,
    car_id          INT           NOT NULL,
    pickup_location VARCHAR(150)  NOT NULL,
    start_date      DATE          NOT NULL,
    end_date        DATE          NOT NULL,
    total_cost      DECIMAL(10,2) NOT NULL,
    status          ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    promo_code      VARCHAR(30)   DEFAULT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes           TEXT          DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (car_id)  REFERENCES cars(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: testimonials
-- Customer reviews linked to completed bookings
-- ============================================================
CREATE TABLE IF NOT EXISTS testimonials (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT  NOT NULL,
    booking_id INT  DEFAULT NULL,
    rating     TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    message    TEXT NOT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: contact_messages
-- Stores enquiries submitted via the contact form
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    email        VARCHAR(150) NOT NULL,
    subject      VARCHAR(200) DEFAULT NULL,
    message      TEXT         NOT NULL,
    is_read      TINYINT(1)   NOT NULL DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: promo_codes
-- Discount codes validated via AJAX on booking page
-- ============================================================
CREATE TABLE IF NOT EXISTS promo_codes (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    code             VARCHAR(30)   NOT NULL UNIQUE,
    discount_percent DECIMAL(5,2)  NOT NULL,
    expiry_date      DATE          NOT NULL,
    is_active        TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- Passwords: admin@driveeasy.com = "admin123"
--            john@example.com    = "user123"
--            sarah@example.com   = "user123"
-- NOTE: Hashes generated with PASSWORD_BCRYPT (cost 12)
--       If hashes fail, regenerate via: password_hash('...', PASSWORD_BCRYPT)
-- ============================================================

INSERT INTO users (name, email, password_hash, role) VALUES
('Admin DriveEasy',
 'admin@driveeasy.com',
 '$2b$12$s08XskvCgzK1297o6k3zWuFEF5.w25WVoHMMgMHKYqhHFpvvVQ7Ty',
 'admin'),
('John Tan',
 'john@example.com',
 '$2b$12$27tJjp.AInDpD0R/gRQ9Pu2Qb8GOwb.3Z3akqtROFjGIe.N5rRcj.',
 'user'),
('Sarah Lim',
 'sarah@example.com',
 '$2b$12$27tJjp.AInDpD0R/gRQ9Pu2Qb8GOwb.3Z3akqtROFjGIe.N5rRcj.',
 'user');

INSERT INTO cars (brand, model, year, type, daily_rate, status, image_path, description, seats, transmission, fuel_type) VALUES
('Toyota',  'Camry',      2023, 'sedan',  85.00,  'available',   'assets/images/toyota-camry.jpg',
 'The Toyota Camry offers a refined ride with excellent fuel economy. Perfect for business travellers and families alike. Equipped with Apple CarPlay, lane assist, and adaptive cruise control.',
 5, 'automatic', 'Petrol'),

('Honda',   'CR-V',       2023, 'SUV',    120.00, 'available',   'assets/images/honda-crv.jpg',
 'The Honda CR-V is a spacious, versatile SUV with all-wheel drive capability. Features a panoramic sunroof, heated seats, and a large cargo area — ideal for road trips.',
 5, 'automatic', 'Petrol'),

('Toyota',  'Alphard',    2022, 'MPV',    180.00, 'available',   'assets/images/toyota-alphard.jpg',
 'Experience luxury people-moving with the Toyota Alphard. Seats up to 7 passengers in first-class comfort, with power sliding doors, rear entertainment screens, and premium leather seating.',
 7, 'automatic', 'Petrol'),

('Porsche', '911 Carrera', 2023, 'sports', 350.00, 'available',  'assets/images/porsche-911.jpg',
 'Feel the thrill of driving a legend. The Porsche 911 Carrera delivers blistering performance with iconic styling. Sport Chrono Package and rear-wheel steering included.',
 2, 'automatic', 'Petrol'),

('Hyundai', 'Tucson',     2024, 'SUV',    105.00, 'available',   'assets/images/hyundai-tucson.jpg',
 'The Hyundai Tucson Hybrid combines efficiency with style. Enjoy its spacious interior, advanced safety features, and striking design on any journey.',
 5, 'automatic', 'Hybrid');

INSERT INTO bookings (user_id, car_id, pickup_location, start_date, end_date, total_cost, status, promo_code, discount_amount) VALUES
(2, 1, 'Orchard Road (Orchard MRT)',   '2026-01-10', '2026-01-13', 255.00, 'confirmed', NULL,    0.00),
(2, 2, 'Changi Airport Terminal 1',   '2026-02-01', '2026-02-05', 480.00, 'confirmed', 'SAVE10', 53.33),
(3, 3, 'Changi Airport Terminal 2',   '2026-01-20', '2026-01-22', 360.00, 'completed', NULL,    0.00),
(3, 5, 'Marina Bay Sands',            '2026-03-05', '2026-03-08', 315.00, 'pending',   NULL,    0.00);

-- Update booking 3 status to 'confirmed' for demo purposes (completed not in ENUM; keep confirmed)
UPDATE bookings SET status='confirmed' WHERE id=3;

INSERT INTO testimonials (user_id, booking_id, rating, message, is_active) VALUES
(2, 1, 5, 'Absolutely fantastic service! The Toyota Camry was spotless and the booking process was seamless. Will definitely rent again!', 1),
(3, 3, 4, 'The Alphard was perfect for our family outing around Singapore. Very comfortable and clean. Pick-up was a breeze.', 1),
(2, 2, 5, 'Booking online was super easy and the promo code worked perfectly. The Honda CR-V handled the mountain roads brilliantly.', 1);

INSERT INTO promo_codes (code, discount_percent, expiry_date, is_active) VALUES
('SAVE10',    10.00, '2026-12-31', 1),
('WELCOME20', 20.00, '2026-06-30', 1),
('FLASH15',   15.00, '2026-03-31', 1),
('EXPIRED5',   5.00, '2025-01-01', 0);

-- ============================================================
-- END OF SCHEMA
-- ============================================================
