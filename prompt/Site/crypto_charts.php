<?php
/**
 * Cryptocurrency Chart Viewer
 * Displays charts of cryptocurrency pairs stored in MySQL database
 */

// Load environment variables from .ENV file
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

// Load environment variables
loadEnv(__DIR__ . '/.ENV');

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
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get available pairs
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

// Get chart data for selected pair with aggregation and time filter
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

// Parse selected pair and filters
$selectedPair = isset($_GET['pair']) ? $_GET['pair'] : null;
$aggregation = isset($_GET['aggregation']) ? $_GET['aggregation'] : 'hour';
$period = isset($_GET['period']) ? $_GET['period'] : '30d';
$chartData = null;

if ($selectedPair) {
    // Extract base and quote currencies from pair (e.g., SOLBRL -> SOL, BRL)
    // Assuming the pair format is always base currency (3-4 chars) + quote currency (3 chars)
    $quoteLength = 3; // Most quote currencies are 3 chars (BRL, USD, EUR, etc.)
    $baseCurrency = substr($selectedPair, 0, -$quoteLength);
    $quoteCurrency = substr($selectedPair, -$quoteLength);
    
    $chartData = getChartData($pdo, $baseCurrency, $quoteCurrency, $aggregation, $period);
}

$availablePairs = getAvailablePairs($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cryptocurrency Charts</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 30px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2em;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.95em;
        }
        
        .selector-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 600;
            font-size: 0.95em;
        }
        
        select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        select:hover {
            border-color: #667eea;
        }
        
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .chart-container {
            position: relative;
            height: 500px;
            margin-top: 30px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.85em;
            margin-bottom: 5px;
        }
        
        .stat-value {
            color: #333;
            font-size: 1.3em;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .no-data svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 1.5em;
            }
            
            .chart-container {
                height: 350px;
            }
            
            .selector-section > div[style*="grid"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📈 Cryptocurrency Charts</h1>
        <p class="subtitle">Select a cryptocurrency pair to view price history</p>
        
        <div class="selector-section">
            <label for="pairSelect">Select Currency Pair:</label>
            <select id="pairSelect" onchange="selectPair(this.value)">
                <option value="">-- Choose a pair --</option>
                <?php foreach ($availablePairs as $pair): ?>
                    <option value="<?= htmlspecialchars($pair['pair']) ?>" 
                            <?= $selectedPair === $pair['pair'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pair['pair']) ?> (<?= number_format($pair['qty']) ?> records)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if ($selectedPair): ?>
        <div class="selector-section">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label for="periodSelect">Time Period:</label>
                    <select id="periodSelect" onchange="updateFilters()">
                        <option value="24h" <?= $period === '24h' ? 'selected' : '' ?>>Last 24 Hours</option>
                        <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="90d" <?= $period === '90d' ? 'selected' : '' ?>>Last 90 Days</option>
                        <option value="1y" <?= $period === '1y' ? 'selected' : '' ?>>Last Year</option>
                        <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Time</option>
                    </select>
                </div>
                <div>
                    <label for="aggregationSelect">Data Aggregation:</label>
                    <select id="aggregationSelect" onchange="updateFilters()">
                        <option value="raw" <?= $aggregation === 'raw' ? 'selected' : '' ?>>Raw Data (All Points)</option>
                        <option value="hour" <?= $aggregation === 'hour' ? 'selected' : '' ?>>Hourly Average</option>
                        <option value="day" <?= $aggregation === 'day' ? 'selected' : '' ?>>Daily Average</option>
                    </select>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($selectedPair && $chartData): ?>
            <?php
                $dates = array_column($chartData, 'crypto_date');
                $prices = array_column($chartData, 'crypto_price');
                
                $minPrice = min($prices);
                $maxPrice = max($prices);
                $avgPrice = array_sum($prices) / count($prices);
                $totalRecords = count($chartData);
            ?>
            
            <?php if ($totalRecords > 500 && $aggregation === 'raw'): ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
                <strong>⚠️ High data density detected!</strong> You're viewing <?= number_format($totalRecords) ?> data points. 
                For better visualization, consider using "Hourly Average" or "Daily Average" aggregation.
            </div>
            <?php endif; ?>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-label">Total Records</div>
                    <div class="stat-value"><?= number_format($totalRecords) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Min Price</div>
                    <div class="stat-value"><?= number_format($minPrice, 2) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Max Price</div>
                    <div class="stat-value"><?= number_format($maxPrice, 2) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Average Price</div>
                    <div class="stat-value"><?= number_format($avgPrice, 2) ?></div>
                </div>
            </div>
            
            <div class="chart-container">
                <canvas id="priceChart"></canvas>
            </div>
            
            <script>
                const ctx = document.getElementById('priceChart').getContext('2d');
                
                // Define period label
                const periodLabels = {
                    '24h': 'Last 24 Hours',
                    '7d': 'Last 7 Days',
                    '30d': 'Last 30 Days',
                    '90d': 'Last 90 Days',
                    '1y': 'Last Year',
                    'all': 'All Time'
                };
                
                // Define aggregation label
                const aggregationLabels = {
                    'raw': 'Raw Data',
                    'hour': 'Hourly Avg',
                    'day': 'Daily Avg'
                };
                
                const period = '<?= $period ?>';
                const aggregation = '<?= $aggregation ?>';
                const periodLabel = periodLabels[period] || period;
                const aggregationLabel = aggregationLabels[aggregation] || aggregation;
                
                const chartData = {
                    labels: <?= json_encode($dates) ?>,
                    datasets: [{
                        label: '<?= htmlspecialchars($selectedPair) ?> - ' + periodLabel + ' (' + aggregationLabel + ')',
                        data: <?= json_encode($prices) ?>,
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: <?= count($dates) > 100 ? '0' : '2' ?>,
                        pointHoverRadius: 5,
                        pointBackgroundColor: 'rgb(102, 126, 234)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                };
                
                const config = {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    font: {
                                        size: 14
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: {
                                    size: 14
                                },
                                bodyFont: {
                                    size: 13
                                },
                                callbacks: {
                                    label: function(context) {
                                        return 'Price: ' + context.parsed.y.toFixed(2);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Date/Time',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    maxTicksLimit: 20
                                },
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Price',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                ticks: {
                                    callback: function(value) {
                                        return value.toFixed(2);
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            }
                        }
                    }
                };
                
                new Chart(ctx, config);
            </script>
            
        <?php elseif ($selectedPair && empty($chartData)): ?>
            <div class="no-data">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" stroke-width="2"/>
                    <line x1="12" y1="8" x2="12" y2="12" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="12" cy="16" r="1" fill="currentColor"/>
                </svg>
                <h3>No data available</h3>
                <p>No records found for the selected pair: <?= htmlspecialchars($selectedPair) ?></p>
            </div>
        <?php else: ?>
            <div class="no-data">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M3 3l18 18M3 21L21 3" stroke-width="2" stroke-linecap="round"/>
                    <path d="M9 9v6m3-6v6m3-6v6" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <h3>Please select a currency pair</h3>
                <p>Choose a pair from the dropdown above to view the chart</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function selectPair(pair) {
            if (pair) {
                window.location.href = '?pair=' + encodeURIComponent(pair) + '&period=30d&aggregation=hour';
            }
        }
        
        function updateFilters() {
            const pair = document.getElementById('pairSelect').value;
            const period = document.getElementById('periodSelect').value;
            const aggregation = document.getElementById('aggregationSelect').value;
            
            if (pair) {
                window.location.href = '?pair=' + encodeURIComponent(pair) + 
                                       '&period=' + encodeURIComponent(period) + 
                                       '&aggregation=' + encodeURIComponent(aggregation);
            }
        }
    </script>
</body>
</html>
