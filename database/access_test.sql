-- Script to create the access_test table
-- This table will store test access records with auto-incrementing numbers

CREATE TABLE IF NOT EXISTS access_test (
    acce_id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Surrogate key',
    acce_number INT NOT NULL COMMENT 'Sequential number incremented on each access',
    acce_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time when the record was inserted',
    INDEX idx_acce_number (acce_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table for testing automated access every 15 minutes';
