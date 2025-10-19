<?php
/**
 * Cryptocurrency Functions Library
 * Contains database access and technical indicator calculations
 */

/**
 * Load environment variables from .ENV file
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        die("Error: .ENV file not found at: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Initialize database connection
 */
function initDatabase($envPath = null) {
    if ($envPath === null) {
        $envPath = __DIR__ . '/.ENV';
    }
    
    // Load environment variables
    loadEnv($envPath);
    
    // Database configuration from .ENV
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_name = getenv('database') ?: getenv('DB_NAME');
    $db_user = getenv('user') ?: getenv('DB_USER');
    $db_pass = getenv('pwd') ?: getenv('DB_PASS');
    
    // Validate database credentials
    if (empty($db_name) || empty($db_user)) {
        die("Error: Database credentials not found in .ENV file. Required: database, user, pwd");
    }
    
    // Database connection
    try {
        $pdo = new PDO(
            "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Calculate Bollinger Bands
 * @param array $prices Array of prices
 * @param int $period Moving average period (default: 20)
 * @param float $stdDev Standard deviation multiplier (default: 2)
 * @return array Array with upper, middle, and lower bands
 */
function calculateBollingerBands($prices, $period = 20, $stdDev = 2.0) {
    $result = [
        'upper' => [],
        'middle' => [],
        'lower' => []
    ];
    
    for ($i = 0; $i < count($prices); $i++) {
        if ($i < $period - 1) {
            // Not enough data for calculation
            $result['upper'][] = null;
            $result['middle'][] = null;
            $result['lower'][] = null;
            continue;
        }
        
        // Get the slice of prices for calculation
        $slice = array_slice($prices, $i - $period + 1, $period);
        
        // Calculate SMA (Simple Moving Average)
        $sma = array_sum($slice) / $period;
        
        // Calculate Standard Deviation
        $variance = 0;
        foreach ($slice as $value) {
            $variance += pow($value - $sma, 2);
        }
        $std = sqrt($variance / $period);
        
        // Calculate bands
        $result['middle'][] = $sma;
        $result['upper'][] = $sma + ($stdDev * $std);
        $result['lower'][] = $sma - ($stdDev * $std);
    }
    
    return $result;
}

/**
 * Calculate RSI (Relative Strength Index)
 * @param array $prices Array of prices
 * @param int $period RSI period (default: 14)
 * @return array Array of RSI values
 */
function calculateRSI($prices, $period = 14) {
    $rsi = [];
    
    if (count($prices) < $period + 1) {
        return array_fill(0, count($prices), null);
    }
    
    // Calculate price changes
    $changes = [];
    for ($i = 1; $i < count($prices); $i++) {
        $changes[] = $prices[$i] - $prices[$i - 1];
    }
    
    // Initial average gain and loss
    $avgGain = 0;
    $avgLoss = 0;
    
    for ($i = 0; $i < $period; $i++) {
        if ($changes[$i] > 0) {
            $avgGain += $changes[$i];
        } else {
            $avgLoss += abs($changes[$i]);
        }
    }
    
    $avgGain /= $period;
    $avgLoss /= $period;
    
    // Calculate RSI for each point
    for ($i = 0; $i < count($prices); $i++) {
        if ($i < $period) {
            $rsi[] = null;
            continue;
        }
        
        // Calculate RS and RSI
        if ($avgLoss == 0) {
            $rsi[] = 100;
        } else {
            $rs = $avgGain / $avgLoss;
            $rsi[] = 100 - (100 / (1 + $rs));
        }
        
        // Update average gain/loss for next iteration
        if ($i < count($changes)) {
            $change = $changes[$i];
            $gain = $change > 0 ? $change : 0;
            $loss = $change < 0 ? abs($change) : 0;
            
            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }
    }
    
    return $rsi;
}

/**
 * Calculate MACD (Moving Average Convergence Divergence)
 * @param array $prices Array of prices
 * @param int $fastPeriod Fast EMA period (default: 12)
 * @param int $slowPeriod Slow EMA period (default: 26)
 * @param int $signalPeriod Signal line period (default: 9)
 * @return array Array with MACD line, signal line, and histogram
 */
function calculateMACD($prices, $fastPeriod = 12, $slowPeriod = 26, $signalPeriod = 9) {
    $result = [
        'macd' => [],
        'signal' => [],
        'histogram' => []
    ];
    
    // Calculate EMA
    $emaFast = calculateEMA($prices, $fastPeriod);
    $emaSlow = calculateEMA($prices, $slowPeriod);
    
    // Calculate MACD line
    $macdLine = [];
    for ($i = 0; $i < count($prices); $i++) {
        if ($emaFast[$i] === null || $emaSlow[$i] === null) {
            $macdLine[] = null;
        } else {
            $macdLine[] = $emaFast[$i] - $emaSlow[$i];
        }
    }
    
    // Calculate signal line (EMA of MACD)
    $signalLine = calculateEMA($macdLine, $signalPeriod);
    
    // Calculate histogram
    for ($i = 0; $i < count($prices); $i++) {
        $result['macd'][] = $macdLine[$i];
        $result['signal'][] = $signalLine[$i];
        
        if ($macdLine[$i] === null || $signalLine[$i] === null) {
            $result['histogram'][] = null;
        } else {
            $result['histogram'][] = $macdLine[$i] - $signalLine[$i];
        }
    }
    
    return $result;
}

/**
 * Calculate EMA (Exponential Moving Average)
 * @param array $data Array of values
 * @param int $period EMA period
 * @return array Array of EMA values
 */
function calculateEMA($data, $period) {
    $ema = [];
    $multiplier = 2 / ($period + 1);
    
    // Initialize with nulls until we have enough data
    for ($i = 0; $i < $period - 1; $i++) {
        $ema[] = null;
        if ($data[$i] === null) {
            continue;
        }
    }
    
    // Calculate first EMA as SMA
    $sum = 0;
    $count = 0;
    for ($i = 0; $i < $period; $i++) {
        if ($data[$i] !== null) {
            $sum += $data[$i];
            $count++;
        }
    }
    
    if ($count > 0) {
        $ema[] = $sum / $count;
    } else {
        $ema[] = null;
    }
    
    // Calculate subsequent EMAs
    for ($i = $period; $i < count($data); $i++) {
        if ($data[$i] === null) {
            $ema[] = $ema[$i - 1];
        } else {
            $ema[] = (($data[$i] - $ema[$i - 1]) * $multiplier) + $ema[$i - 1];
        }
    }
    
    return $ema;
}

/**
 * Get available currency pairs from database
 */
function getAvailablePairs($pdo) {
    $query = "
        SELECT
            CONCAT(base.smbl_code, quote.smbl_code) AS pair,
            COUNT(*) AS qty
        FROM candle_time ct
        INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
        INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
        INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
        INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
        GROUP BY base.smbl_code, quote.smbl_code
        ORDER BY pair
    ";
    
    $stmt = $pdo->query($query);
    return $stmt->fetchAll();
}

/**
 * Get chart data for selected pair with aggregation and time filter
 */
function getChartData($pdo, $baseCurrency, $quoteCurrency, $aggregation = 'hour', $period = 'all') {
    // Determine date filter
    $dateFilter = '';
    $params = [':base' => $baseCurrency, ':quote' => $quoteCurrency];
    
    switch ($period) {
        case '24h':
            $dateFilter = 'AND TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)) >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
            break;
        case '7d':
            $dateFilter = 'AND TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)) >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case '30d':
            $dateFilter = 'AND TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)) >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
        case '90d':
            $dateFilter = 'AND TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)) >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
            break;
        case '1y':
            $dateFilter = 'AND TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)) >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
            break;
    }
    
    // Build query based on aggregation level
    switch ($aggregation) {
        case 'raw':
            // Original query - all data points
            $query = "
                SELECT
                    DATE_FORMAT(
                        TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)), 
                        '%d/%m/%y %H:%i'
                    ) AS crypto_date,
                    (ct.cntm_open_price + ct.cntm_close_price) / 2 AS crypto_price
                FROM candle_time ct
                INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
                INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
                INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
                INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
                WHERE base.smbl_code = :base AND quote.smbl_code = :quote
                $dateFilter
                ORDER BY cd.cndl_date, ct.cntm_minutes
            ";
            break;
            
        case 'hour':
            // Aggregate by hour
            $query = "
                SELECT
                    DATE_FORMAT(
                        DATE_FORMAT(
                            TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)), 
                            '%Y-%m-%d %H:00:00'
                        ), 
                        '%d/%m/%y %H:00'
                    ) AS crypto_date,
                    AVG((ct.cntm_open_price + ct.cntm_close_price) / 2) AS crypto_price
                FROM candle_time ct
                INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
                INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
                INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
                INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
                WHERE base.smbl_code = :base AND quote.smbl_code = :quote
                $dateFilter
                GROUP BY DATE_FORMAT(TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)), '%Y-%m-%d %H')
                ORDER BY DATE_FORMAT(TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)), '%Y-%m-%d %H')
            ";
            break;
            
        case 'day':
            // Aggregate by day
            $query = "
                SELECT
                    DATE_FORMAT(cd.cndl_date, '%d/%m/%y') AS crypto_date,
                    AVG((ct.cntm_open_price + ct.cntm_close_price) / 2) AS crypto_price
                FROM candle_time ct
                INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
                INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
                INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
                INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
                WHERE base.smbl_code = :base AND quote.smbl_code = :quote
                $dateFilter
                GROUP BY cd.cndl_date
                ORDER BY cd.cndl_date
            ";
            break;
            
        default:
            $query = "
                SELECT
                    DATE_FORMAT(
                        TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60)), 
                        '%d/%m/%y %H:%i'
                    ) AS crypto_date,
                    (ct.cntm_open_price + ct.cntm_close_price) / 2 AS crypto_price
                FROM candle_time ct
                INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
                INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
                INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
                INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
                WHERE base.smbl_code = :base AND quote.smbl_code = :quote
                $dateFilter
                ORDER BY cd.cndl_date, ct.cntm_minutes
            ";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Parse currency pair into base and quote currencies
 */
function parseCurrencyPair($pdo, $selectedPair) {
    $baseCurrency = null;
    $quoteCurrency = null;
    
    if (!$selectedPair) {
        return [null, null];
    }
    
    // Try to split common pairs (BTCUSDT, ETHBTC, etc.)
    // Order matters: longer codes first to avoid incorrect matches
    $commonQuotes = [
        'USDT', 'USDC', 'BUSD', 'TUSD', 'FDUSD',  // Stablecoins (4-5 chars)
        'BRL', 'USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'CNY', 'KRW', 'INR', 'RUB', 'TRY', 'ZAR', // Fiat currencies (3 chars)
        'BTC', 'ETH', 'BNB', 'SOL', 'XRP', 'ADA', 'DOT', 'DOGE', 'MATIC', 'AVAX'  // Crypto quote currencies (3-5 chars)
    ];
    
    foreach ($commonQuotes as $quote) {
        if (substr($selectedPair, -strlen($quote)) === $quote) {
            $baseCurrency = substr($selectedPair, 0, -strlen($quote));
            $quoteCurrency = $quote;
            break;
        }
    }
    
    // If parsing failed, try to get from database
    if (!$baseCurrency || !$quoteCurrency) {
        $parseQuery = "
            SELECT base.smbl_code AS base, quote.smbl_code AS quote
            FROM symbol_pair sp
            INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
            INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
            WHERE CONCAT(base.smbl_code, quote.smbl_code) = :pair
            LIMIT 1
        ";
        $parseStmt = $pdo->prepare($parseQuery);
        $parseStmt->execute([':pair' => $selectedPair]);
        $pairData = $parseStmt->fetch();
        
        if ($pairData) {
            $baseCurrency = $pairData['base'];
            $quoteCurrency = $pairData['quote'];
        }
    }
    
    return [$baseCurrency, $quoteCurrency];
}
