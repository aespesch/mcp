-- Table: symbol
-- Stores cryptocurrency and fiat symbols (3 or 4 characters)
CREATE TABLE symbol (
    smbl_id INT PRIMARY KEY AUTO_INCREMENT,
    smbl_code VARCHAR(4) NOT NULL UNIQUE,
    smbl_name VARCHAR(100),
    smbl_is_fiat BOOLEAN NOT NULL DEFAULT FALSE,
    smbl_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    smbl_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_smbl_code (smbl_code),
    INDEX idx_smbl_is_fiat (smbl_is_fiat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: symbol_pair
-- Stores trading pairs (e.g., SOLBRL, BTCUSDT)
CREATE TABLE symbol_pair (
    smpr_id INT PRIMARY KEY AUTO_INCREMENT,
    smpr_base_symbol_id INT NOT NULL,
    smpr_quote_symbol_id INT NOT NULL,
    smpr_is_active BOOLEAN NOT NULL DEFAULT TRUE,
    smpr_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    smpr_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (smpr_base_symbol_id) REFERENCES symbol(smbl_id),
    FOREIGN KEY (smpr_quote_symbol_id) REFERENCES symbol(smbl_id),
    INDEX idx_smpr_base_quote (smpr_base_symbol_id, smpr_quote_symbol_id),
    UNIQUE KEY uk_base_quote (smpr_base_symbol_id, smpr_quote_symbol_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Insert sample data for testing
-- Insert symbols
INSERT INTO symbol (smbl_code, smbl_name, smbl_is_fiat) VALUES
    ('BTC', 'Bitcoin', FALSE),
    ('BNB', 'Binance Coin', FALSE),
    ('SOL', 'Solana', FALSE),
    ('RED', 'Red Token', FALSE),
    ('BRL', 'Brazilian Real', TRUE),
    ('USDC', 'USD Coin', TRUE),
    ('USDT', 'Tether', TRUE);

-- Insert symbol pairs
INSERT INTO symbol_pair (smpr_base_symbol_id, smpr_quote_symbol_id)
SELECT 
    base.smbl_id,
    quote.smbl_id
FROM symbol base
CROSS JOIN symbol quote
WHERE (base.smbl_code = 'BNB' AND quote.smbl_code = 'BRL')
   OR (base.smbl_code = 'BTC' AND quote.smbl_code = 'BRL')
   OR (base.smbl_code = 'BTC' AND quote.smbl_code = 'USDC')
   OR (base.smbl_code = 'BTC' AND quote.smbl_code = 'USDT')
   OR (base.smbl_code = 'RED' AND quote.smbl_code = 'USDT')
   OR (base.smbl_code = 'SOL' AND quote.smbl_code = 'BRL')
   OR (base.smbl_code = 'SOL' AND quote.smbl_code = 'USDT');

-- Table: candle_day
-- Stores unique trading days for each symbol pair
CREATE TABLE candle_day (
    cndl_id INT PRIMARY KEY AUTO_INCREMENT,
    cndl_symbol_pair_id INT NOT NULL,
    cndl_date DATE NOT NULL,
    cndl_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cndl_symbol_pair_id) REFERENCES symbol_pair(smpr_id),
    INDEX idx_cndl_date (cndl_date),
    INDEX idx_cndl_pair_date (cndl_symbol_pair_id, cndl_date),
    UNIQUE KEY uk_pair_date (cndl_symbol_pair_id, cndl_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: candle_time
-- Stores candle data for specific timestamps
CREATE TABLE candle_time (
    cntm_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cntm_candle_day_id INT NOT NULL,
    cntm_minutes SMALLINT,
    cntm_open_price DECIMAL(20, 8) NOT NULL,
    cntm_high_price DECIMAL(20, 8) NOT NULL,
    cntm_low_price DECIMAL(20, 8) NOT NULL,
    cntm_close_price DECIMAL(20, 8) NOT NULL,
    cntm_volume DECIMAL(20, 8) NOT NULL,
    FOREIGN KEY (cntm_candle_day_id) REFERENCES candle_day(cndl_id),
    UNIQUE KEY uk_candle_time (cntm_candle_day_id, cntm_minutes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Stored Procedure: sp_insert_candle
-- Inserts candle data into candle_day and candle_time tables
-- Supports symbol codes with 3 or 4 characters
-- Example usage:
-- CALL sp_insert_candle('SOLBRL', '2025-03-14 21:30:00+00:00', 769.6, 774.4, 768.3, 771.9, 22882);
-- CALL sp_insert_candle('BTCUSDT', '2025-03-14 21:30:00+00:00', 50000, 50100, 49900, 50050, 1500);
-- First call: inserts data with date='2025-03-14' and hour=18
-- Second call: ignores (does nothing) 

DELIMITER //

DROP PROCEDURE IF EXISTS sp_insert_candle//

CREATE PROCEDURE sp_insert_candle(
    IN p_symbol_pair VARCHAR(8) ,
    IN p_timestamp VARCHAR(30) , 
    IN p_open_price DECIMAL(20, 8) ,
    IN p_high_price DECIMAL(20, 8) ,
    IN p_low_price DECIMAL(20, 8) ,
    IN p_close_price DECIMAL(20, 8) ,
    IN p_volume DECIMAL(20, 8) 
)
BEGIN
    DECLARE v_pair_id INT;
    DECLARE v_day_id INT;
    DECLARE v_date DATE;
    DECLARE v_base_symbol VARCHAR(4);
    DECLARE v_quote_symbol VARCHAR(4);
    DECLARE v_local_datetime DATETIME;
    DECLARE v_exists INT DEFAULT 0;
    DECLARE v_minutes_since_midnight INT;    

    -- Convert UTC timestamp to São Paulo timezone (America/Sao_Paulo = GMT-3)
    -- Remove timezone indicator (+00:00) if present
    SET v_local_datetime = CONVERT_TZ(
        STR_TO_DATE(LEFT(p_timestamp, 19), '%Y-%m-%d %H:%i:%s'),
        '+00:00',
        'America/Sao_Paulo'
    );
    
    -- If CONVERT_TZ returns NULL (timezone tables not loaded), manually subtract 3 hours
    IF v_local_datetime IS NULL THEN
        SET v_local_datetime = DATE_SUB(
            STR_TO_DATE(LEFT(p_timestamp, 19), '%Y-%m-%d %H:%i:%s'),
            INTERVAL 3 HOUR
        );
    END IF;
    
    -- Extract date and hour from local datetime
    SET v_date = DATE(v_local_datetime);
   
    -- Calculate minutes since midnight
    SET v_minutes_since_midnight = HOUR(v_local_datetime) * 60 + MINUTE(v_local_datetime);
    
    -- Try to find the symbol pair by attempting different split positions
    -- First, try assuming quote symbol has 4 characters (e.g., BTCUSDT -> BTC + USDT)
    IF LENGTH(p_symbol_pair) >= 7 THEN
        SET v_base_symbol = LEFT(p_symbol_pair, LENGTH(p_symbol_pair) - 4);
        SET v_quote_symbol = RIGHT(p_symbol_pair, 4);
        
        SELECT sp.smpr_id INTO v_pair_id
          FROM symbol_pair sp
        INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
        INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
         WHERE base.smbl_code = v_base_symbol COLLATE utf8mb4_unicode_ci
           AND quote.smbl_code = v_quote_symbol COLLATE utf8mb4_unicode_ci;
    END IF;
    
    -- If not found and length is at least 6, try assuming quote symbol has 3 characters (e.g., SOLBRL -> SOL + BRL)
    IF v_pair_id IS NULL AND LENGTH(p_symbol_pair) >= 6 THEN
        SET v_base_symbol = LEFT(p_symbol_pair, LENGTH(p_symbol_pair) - 3);
        SET v_quote_symbol = RIGHT(p_symbol_pair, 3);
        
        SELECT sp.smpr_id INTO v_pair_id
          FROM symbol_pair sp
        INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
        INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
         WHERE base.smbl_code = v_base_symbol COLLATE utf8mb4_unicode_ci
           AND quote.smbl_code = v_quote_symbol COLLATE utf8mb4_unicode_ci;
    END IF;
    
    -- Check if symbol pair exists
    IF v_pair_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Symbol pair not found';
    END IF;
    
    -- Insert or get candle_day ID
    INSERT INTO candle_day (cndl_symbol_pair_id, cndl_date)
    VALUES (v_pair_id, v_date)
        ON DUPLICATE KEY UPDATE cndl_id = LAST_INSERT_ID(cndl_id);
    
    SET v_day_id = LAST_INSERT_ID();
    
    -- Check if this candle already exists (same day_id + hour)
    SELECT COUNT(*) INTO v_exists
      FROM candle_time
     WHERE cntm_candle_day_id = v_day_id
       AND cntm_minutes = v_minutes_since_midnight;
    
    -- Only insert if it doesn't exist
    IF v_exists = 0 THEN
        INSERT INTO candle_time (
            cntm_candle_day_id,
            cntm_minutes,
            cntm_open_price,
            cntm_high_price,
            cntm_low_price,
            cntm_close_price,
            cntm_volume
        ) VALUES (
            v_day_id,
            v_minutes_since_midnight,
            p_open_price,
            p_high_price,
            p_low_price,
            p_close_price,
            p_volume
        );
    END IF;
    -- If v_exists > 0, simply ignore (do nothing)
END//

DELIMITER ;

call sp_insert_candle('BNBBRL','2025-04-25 03:00:00+00:00',3410.0,3418.0,3409.0,3418.0,6.84);
call sp_insert_candle('BNBBRL','2025-04-25 03:15:00+00:00',3419.0,3423.0,3419.0,3423.0,0.024);
call sp_insert_candle('BNBBRL','2025-04-25 03:30:00+00:00',3421.0,3421.0,3418.0,3419.0,1.583);
call sp_insert_candle('BNBBRL','2025-04-25 03:45:00+00:00',3420.0,3423.0,3420.0,3421.0,0.301);
call sp_insert_candle('BNBBRL','2025-04-25 04:00:00+00:00',3423.0,3435.0,3422.0,3435.0,2.525);
call sp_insert_candle('BNBBRL','2025-04-25 04:15:00+00:00',3435.0,3441.0,3435.0,3441.0,4.39);
call sp_insert_candle('BNBBRL','2025-04-25 04:30:00+00:00',3435.0,3445.0,3435.0,3445.0,6.392);

SELECT
    base.smbl_code AS base_symbol,
    quote.smbl_code AS quote_symbol,
    cd.cndl_date AS date,
    TIME_FORMAT(SEC_TO_TIME(ct.cntm_minutes * 60), '%H:%i') AS time,
    ct.cntm_open_price AS open_price,
    ct.cntm_high_price AS high_price,
    ct.cntm_low_price AS low_price,
    ct.cntm_close_price AS close_price,
    ct.cntm_volume AS volume
FROM candle_time ct
INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
ORDER BY base.smbl_code, quote.smbl_code, cd.cndl_date, ct.cntm_minutes;

SELECT
    CONCAT(base.smbl_code, quote.smbl_code) AS pair,
    COUNT(*) AS qty
FROM candle_time ct
INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
GROUP BY base.smbl_code, quote.smbl_code
ORDER BY pair;