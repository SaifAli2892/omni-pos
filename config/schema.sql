-- OmniPOS Database Schema
CREATE DATABASE IF NOT EXISTS `omni_pos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `omni_pos`;

-- 1. Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Users / Cashiers Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'cashier') DEFAULT 'cashier',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Products Table
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `barcode` VARCHAR(100) NULL UNIQUE,
    `sku` VARCHAR(100) NULL,
    `category_id` INT NULL,
    `cost_price` DECIMAL(10,2) DEFAULT 0.00,
    `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock_qty` INT NOT NULL DEFAULT 0,
    `alert_qty` INT NOT NULL DEFAULT 5,
    `image_url` VARCHAR(255) NULL,
    `scora_product_id` VARCHAR(100) NULL,
    `scora_variant_id` VARCHAR(100) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Customers Table (with Khata / Udhaar balance)
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) NULL UNIQUE,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `credit_balance` DECIMAL(10,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Sales Table
CREATE TABLE IF NOT EXISTS `sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT NULL,
    `cashier_id` INT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `change_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('cash', 'card', 'easypaisa', 'jazzcash', 'udhaar') DEFAULT 'cash',
    `status` ENUM('completed', 'voided') DEFAULT 'completed',
    `scora_order_id` VARCHAR(100) NULL,
    `scora_sync_status` ENUM('synced', 'pending', 'failed', 'local_only') DEFAULT 'local_only',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Sale Items Table
CREATE TABLE IF NOT EXISTS `sale_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT NOT NULL,
    `product_id` INT NULL,
    `product_title` VARCHAR(255) NOT NULL,
    `cost_price` DECIMAL(10,2) DEFAULT 0.00,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `quantity` INT NOT NULL DEFAULT 1,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `scora_product_id` VARCHAR(100) NULL,
    FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Customer Khata / Ledger Table
CREATE TABLE IF NOT EXISTS `customer_ledgers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `sale_id` INT NULL,
    `type` ENUM('credit_sale', 'payment_received') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `balance_after` DECIMAL(10,2) NOT NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Scora Sync & Activity Logs
CREATE TABLE IF NOT EXISTS `sync_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `status` ENUM('success', 'error', 'info') DEFAULT 'info',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initial Default Settings
INSERT INTO `settings` (`key`, `value`) VALUES
('store_name', 'Retail Store'),
('store_phone', ''),
('store_address', ''),
('receipt_footer', 'Thank you for shopping with us!'),
('currency_symbol', 'Rs.'),
('tax_rate', '0'),
('cloud_store_domain', ''),
('cloud_access_token', ''),
('cloud_auto_sync', '0'),
('cloud_last_sync', '')
ON DUPLICATE KEY UPDATE `key`=`key`;

-- Default Admin User (admin / admin123)
INSERT INTO `users` (`name`, `username`, `password`, `role`) VALUES
('Store Owner (Admin)', 'admin', '$2y$10$w1B.zLpW5JpBvV8F50QZ/eqGgq8Jj96q1T9rN1eQ74YdG4Nq1aHh6', 'admin'),
('Cashier Counter 1', 'cashier', '$2y$10$w1B.zLpW5JpBvV8F50QZ/eqGgq8Jj96q1T9rN1eQ74YdG4Nq1aHh6', 'cashier')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Default Categories
INSERT INTO `categories` (`id`, `name`, `slug`) VALUES
(1, 'General', 'general')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Default Customer (Walk-in Cash)
INSERT INTO `customers` (`name`, `phone`, `email`, `address`, `credit_balance`) VALUES
('Walk-in Customer (Naqad)', '0000-0000000', '', 'Cash Customer', 0.00)
ON DUPLICATE KEY UPDATE `phone`=`phone`;

