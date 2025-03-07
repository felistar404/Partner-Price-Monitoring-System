--              --- CREATE DB & TABLES -----
CREATE DATABASE IF NOT EXISTS price_monitoring_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE price_monitoring_system;

DROP TABLE IF EXISTS platforms;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS merchants;
DROP TABLE IF EXISTS platform_merchant_mappings;
DROP TABLE IF EXISTS product_url_mappings;
DROP TABLE IF EXISTS price_records;
DROP TABLE IF EXISTS crawl_logs;
DROP TABLE IF EXISTS refresh_cooldowns;

-- platforms
CREATE TABLE platforms (
    platform_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_name VARCHAR(100) NOT NULL,
    platform_url VARCHAR(255) NOT NULL COMMENT 'url prefix',
    platform_url_price VARCHAR(255) NOT NULL COMMENT 'url price suffix',
    platform_url_merchant VARCHAR(255) DEFAULT NULL COMMENT 'url merchant suffix',
    platform_status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'If platform does not need to be monitored, set to inactive, and vice versa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -- product categorization
-- CREATE TABLE product_categories (
--     category_id INT AUTO_INCREMENT PRIMARY KEY,
--     category_name VARCHAR(100) NOT NULL,
--     parent_id INT DEFAULT NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     FOREIGN KEY (parent_id) REFERENCES product_categories(category_id) ON DELETE SET NULL
-- ) ENGINE=InnoDB;

-- product details
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    -- category_id INT,
    product_name VARCHAR(255) NOT NULL,
    product_model VARCHAR(100) NOT NULL,
    reference_price DECIMAL(10, 2) NOT NULL,
    min_acceptable_price DECIMAL(10, 2) NOT NULL,
    max_acceptable_price DECIMAL(10, 2) NOT NULL,
    product_status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'If product does not need to be monitored, set to inactive, and vice versa',
    product_description TEXT,
    -- product_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- FOREIGN KEY (category_id) REFERENCES product_categories(category_id) ON DELETE SET NULL,
    UNIQUE INDEX (product_model)
) ENGINE=InnoDB;

-- merchants
CREATE TABLE merchants (
    merchant_id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    address TEXT,
    merchant_status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'If merchant does not need to be monitored, set to inactive, and vice versa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX (merchant_name)
) ENGINE=InnoDB;

-- relationship between one merchant to multiple platforms (support different declaration on merchant ids)
CREATE TABLE platform_merchant_mappings (
    mapping_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_id INT NOT NULL,
    merchant_id INT NOT NULL,
    platform_merchant_id VARCHAR(100) NOT NULL,
    platform_merchant_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
    FOREIGN KEY (merchant_id) REFERENCES merchants(merchant_id) ON DELETE CASCADE,
    UNIQUE INDEX (platform_id, platform_merchant_id)
) ENGINE=InnoDB;

-- stores product in each platforms (support different declaration on product ids)
CREATE TABLE product_url_mappings (
    mapping_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    platform_id INT NOT NULL,
    platform_product_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
    UNIQUE INDEX (product_id, platform_id)
) ENGINE=InnoDB;

-- main_record to display newest data for correct batches.
CREATE TABLE main_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    reference_key VARCHAR(255) DEFAULT NULL,
    update_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX (reference_key)
) ENGINE=InnoDB;

-- price_records
CREATE TABLE price_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    merchant_id INT NOT NULL,
    platform_id INT NOT NULL,
    parent_id VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2),
    currency VARCHAR(10) DEFAULT 'HKD',
    price_status ENUM('normal', 'overpriced', 'underpriced', 'missing') NOT NULL COMMENT 'Based on this indicator to provide status',
    reference_key VARCHAR(255),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (merchant_id) REFERENCES merchants(merchant_id) ON DELETE CASCADE,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES main_records(reference_key) ON DELETE CASCADE,
    INDEX (record_id)
) ENGINE=InnoDB;

-- crawl logs
CREATE TABLE crawl_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_id INT NOT NULL,
    crawl_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('completed', 'failed') NOT NULL,
    crawled_products INT DEFAULT 0,
    error_message TEXT,
    crawl_description TEXT
) ENGINE=InnoDB;

-- -- notification logs (To ensure not to duplicate the notification email in short period of time - Automation)
-- CREATE TABLE notification_logs (
--     notification_id INT AUTO_INCREMENT PRIMARY KEY,
--     merchant_id INT NOT NULL,
--     product_id INT NOT NULL,
--     notification_type ENUM('missing_price', 'overpriced', 'underpriced') NOT NULL,
--     message TEXT NOT NULL,
--     sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
--     error_message TEXT,
--     FOREIGN KEY (merchant_id) REFERENCES merchants(merchant_id) ON DELETE CASCADE,
--     FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
-- ) ENGINE=InnoDB;

-- cooldown references (global ban)
CREATE TABLE refresh_cooldowns (
    cooldown_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_id INT NOT NULL,
    last_refresh_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    next_available_time TIMESTAMP COMMENT 'Based on this timestamp to decide whether or not to perform refresh',
    cooldown_hours INT DEFAULT 24 COMMENT 'Default in 24 hrs',
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
    -- FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
    -- FOREIGN KEY (merchant_id) REFERENCES merchants(merchant_id) ON DELETE SET NULL,
    UNIQUE INDEX (platform_id)
) ENGINE=InnoDB;