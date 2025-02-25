                    ----- create db & tables -----
CREATE DATABASE IF NOT EXISTS price_monitoring_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE price_monitoring_system;

DROP TABLE IF EXISTS

-- platforms
CREATE TABLE platforms (
    platform_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_name VARCHAR(100) NOT NULL,
    platform_url VARCHAR(255) NOT NULL,
    platform_status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'If platform does not need to be monitored, set to inactive, and vice versa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- product categorization
CREATE TABLE product_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    parent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES product_categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- product details
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    product_model VARCHAR(100) NOT NULL,
    category_id INT,
    reference_price DECIMAL(10, 2) NOT NULL,
    min_acceptable_price DECIMAL(10, 2) NOT NULL,
    max_acceptable_price DECIMAL(10, 2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    product_description TEXT,
    product_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(category_id) ON DELETE SET NULL,
    UNIQUE INDEX (product_model)
) ENGINE=InnoDB;

-- merchants
CREATE TABLE merchants (
    merchant_id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    merchant_status ENUM('active', 'inactive') DEFAULT 'active',
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
    product_url VARCHAR(512) NOT NULL,
    platform_product_id VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
    UNIQUE INDEX (product_id, platform_id)
) ENGINE=InnoDB;

-- 价格记录表(存储爬取的价格数据)
CREATE TABLE price_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    merchant_id INT NOT NULL,
    platform_id INT NOT NULL,
    price DECIMAL(10, 2),
    currency VARCHAR(10) DEFAULT 'HKD',
    price_status ENUM('normal', 'overpriced', 'underpriced', 'missing') NOT NULL,
    is_available BOOLEAN DEFAULT TRUE, -- 产品是否在该商家处有售
    record_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (merchant_id) REFERENCES merchants(merchant_id) ON DELETE CASCADE,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
    INDEX (record_date)
) ENGINE=InnoDB;

-- 爬取日志表(记录每次爬取的详情)
CREATE TABLE crawl_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_id INT NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP,
    status ENUM('running', 'completed', 'failed') NOT NULL,
    crawled_products INT DEFAULT 0,
    success_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    error_message TEXT,
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 通知日志表(记录发送通知的详情)
CREATE TABLE notification_logs (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    product_id INT NOT NULL,
    notification_type ENUM('missing_price', 'overpriced', 'underpriced') NOT NULL,
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    error_message TEXT,
    FOREIGN KEY (merchant_id) REFERENCES merchants(merchant_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 刷新冷却时间表(管理刷新功能的冷却时间)
CREATE TABLE refresh_cooldowns (
    cooldown_id INT AUTO_INCREMENT PRIMARY KEY,
    platform_id INT NOT NULL,
    product_id INT DEFAULT NULL, -- NULL表示整个平台的冷却
    merchant_id INT DEFAULT NULL, -- NULL表示所有商家
    last_refresh_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    next_available_time TIMESTAMP, -- 下次可用时间
    cooldown_hours INT DEFAULT 24, -- 冷却时间(小时)
    FOREIGN KEY (platform_id) REFERENCES platforms(platform_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
    FOREIGN KEY (merchant_id) REFERENCES merchants(merchant_id) ON DELETE SET NULL,
    UNIQUE INDEX (platform_id, product_id, merchant_id)
) ENGINE=InnoDB;

-- -- users
-- CREATE TABLE users (
--     user_id INT AUTO_INCREMENT PRIMARY KEY,
--     username VARCHAR(50) NOT NULL,
--     password VARCHAR(255) NOT NULL,
--     email VARCHAR(255) NOT NULL,
--     role ENUM('admin', 'manager', 'viewer') NOT NULL DEFAULT 'viewer',
--     is_active BOOLEAN DEFAULT TRUE,
--     last_login TIMESTAMP NULL,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     UNIQUE INDEX (username),
--     UNIQUE INDEX (email)
-- ) ENGINE=InnoDB;

-- -- admin log
-- CREATE TABLE admin_action_logs (
--     log_id INT AUTO_INCREMENT PRIMARY KEY,
--     user_id INT NOT NULL,
--     action_type VARCHAR(50) NOT NULL,
--     action_description TEXT NOT NULL,
--     performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     ip_address VARCHAR(45),
--     FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
-- ) ENGINE=InnoDB;

                    ----- DATA INIT -----

-- data init for platform
INSERT INTO platforms (platform_name, platform_url) VALUES 
('Price.com.hk', 'https://www.price.com.hk/'),
('WCSLMall', 'https://www.wcslmall.com/'),
('CentralField', 'https://www.centralfield.com/');

-- -- data init for users
-- INSERT INTO users (username, password, email, role) VALUES 
-- ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin');