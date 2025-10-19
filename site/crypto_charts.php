<?php
/**
 * Cryptocurrency Chart Viewer with Technical Indicators
 * Displays charts with Bollinger Bands, RSI, and MACD indicators
 */

// Include functions library
require_once __DIR__ . '/crypto_functions.php';

// Initialize database connection
$pdo = initDatabase();

// Get request parameters
$selectedPair = $_GET['pair'] ?? null;
$period = $_GET['period'] ?? '30d';
$aggregation = $_GET['aggregation'] ?? 'hour';
$chartMode = $_GET['mode'] ?? 'basic'; // basic, bollinger, complete

// Parse currency pair
list($baseCurrency, $quoteCurrency) = parseCurrencyPair($pdo, $selectedPair);

// Get data
$availablePairs = getAvailablePairs($pdo);
$chartData = [];
$bollingerBands = null;
$rsiData = null;
$macdData = null;

if ($baseCurrency && $quoteCurrency) {
    $chartData = getChartData($pdo, $baseCurrency, $quoteCurrency, $aggregation, $period);
    
    // Calculate technical indicators if chart mode requires them
    if (!empty($chartData) && ($chartMode === 'bollinger' || $chartMode === 'complete')) {
        $prices = array_map(function($row) {
            return (float)$row['crypto_price'];
        }, $chartData);
        
        // Calculate Bollinger Bands
        $bollingerBands = calculateBollingerBands($prices);
        
        // Calculate RSI and MACD for complete mode
        if ($chartMode === 'complete') {
            $rsiData = calculateRSI($prices);
            $macdData = calculateMACD($prices);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Charts - Technical Analysis</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .controls {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .control-group {
            display: flex;
            flex-direction: column;
        }
        
        .control-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #495057;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .control-group select {
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1em;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .control-group select:hover {
            border-color: #667eea;
        }
        
        .control-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .chart-container {
            padding: 30px;
            position: relative;
            height: 600px;
        }
        
        .chart-container.with-indicator {
            height: 900px;
        }
        
        canvas {
            max-height: 100%;
        }
        
        .no-data {
            text-align: center;
            padding: 100px 30px;
            color: #6c757d;
        }
        
        .no-data svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .no-data h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 2px solid #e9ecef;
        }
        
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        
        .stat-label {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 1.3em;
            font-weight: 700;
            color: #667eea;
        }
        
        .mode-info {
            padding: 15px 30px;
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            margin: 20px 30px;
            border-radius: 5px;
        }
        
        .mode-info h4 {
            margin-bottom: 5px;
            color: #1976D2;
        }
        
        .mode-info p {
            color: #555;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📈 Cryptocurrency Technical Analysis</h1>
            <p>Real-time charts with Bollinger Bands, RSI, and MACD indicators</p>
        </div>
        
        <div class="controls">
            <div class="control-group">
                <label for="pairSelect">Currency Pair</label>
                <select id="pairSelect" onchange="selectPair(this.value)">
                    <option value="">-- Select Pair --</option>
                    <?php foreach ($availablePairs as $pair): ?>
                        <option value="<?= htmlspecialchars($pair['pair']) ?>" 
                                <?= $pair['pair'] === $selectedPair ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pair['pair']) ?> (<?= number_format($pair['qty']) ?> records)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="control-group">
                <label for="periodSelect">Time Period</label>
                <select id="periodSelect" onchange="updateFilters()">
                    <option value="24h" <?= $period === '24h' ? 'selected' : '' ?>>Last 24 Hours</option>
                    <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="90d" <?= $period === '90d' ? 'selected' : '' ?>>Last 90 Days</option>
                    <option value="1y" <?= $period === '1y' ? 'selected' : '' ?>>Last Year</option>
                    <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Time</option>
                </select>
            </div>
            
            <div class="control-group">
                <label for="aggregationSelect">Aggregation</label>
                <select id="aggregationSelect" onchange="updateFilters()">
                    <option value="raw" <?= $aggregation === 'raw' ? 'selected' : '' ?>>Raw Data (All Points)</option>
                    <option value="hour" <?= $aggregation === 'hour' ? 'selected' : '' ?>>Hourly Average</option>
                    <option value="day" <?= $aggregation === 'day' ? 'selected' : '' ?>>Daily Average</option>
                </select>
            </div>
            
            <div class="control-group">
                <label for="modeSelect">Chart Mode</label>
                <select id="modeSelect" onchange="updateFilters()">
                    <option value="basic" <?= $chartMode === 'basic' ? 'selected' : '' ?>>Basic (Price Only)</option>
                    <option value="bollinger" <?= $chartMode === 'bollinger' ? 'selected' : '' ?>>Bollinger Bands</option>
                    <option value="complete" <?= $chartMode === 'complete' ? 'selected' : '' ?>>Complete (BB + RSI + MACD)</option>
                </select>
            </div>
        </div>
        
        <?php if ($chartMode === 'bollinger'): ?>
        <div class="mode-info">
            <h4>📊 Bollinger Bands Mode</h4>
            <p>Shows price with upper, middle, and lower Bollinger Bands (20-period SMA ± 2 standard deviations)</p>
        </div>
        <?php elseif ($chartMode === 'complete'): ?>
        <div class="mode-info">
            <h4>📊 Complete Analysis Mode</h4>
            <p>Shows price with Bollinger Bands, RSI (Relative Strength Index), and MACD (Moving Average Convergence Divergence)</p>
        </div>
        <?php endif; ?>
        
        <?php if ($selectedPair && !empty($chartData)): ?>
            <?php
            // Calculate statistics
            $prices = array_map(function($row) { return (float)$row['crypto_price']; }, $chartData);
            $minPrice = min($prices);
            $maxPrice = max($prices);
            $avgPrice = array_sum($prices) / count($prices);
            $currentPrice = end($prices);
            $firstPrice = reset($prices);
            $priceChange = $currentPrice - $firstPrice;
            $priceChangePercent = ($priceChange / $firstPrice) * 100;
            ?>
            
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-label">Current Price</div>
                    <div class="stat-value"><?= number_format($currentPrice, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Change</div>
                    <div class="stat-value" style="color: <?= $priceChange >= 0 ? '#10b981' : '#ef4444' ?>">
                        <?= $priceChange >= 0 ? '+' : '' ?><?= number_format($priceChangePercent, 2) ?>%
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Average</div>
                    <div class="stat-value"><?= number_format($avgPrice, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">High</div>
                    <div class="stat-value"><?= number_format($maxPrice, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Low</div>
                    <div class="stat-value"><?= number_format($minPrice, 2) ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Data Points</div>
                    <div class="stat-value"><?= count($chartData) ?></div>
                </div>
            </div>
            
            <div class="chart-container <?= $chartMode === 'complete' ? 'with-indicator' : '' ?>">
                <canvas id="cryptoChart"></canvas>
            </div>
            
            <script>
                const ctx = document.getElementById('cryptoChart').getContext('2d');
                
                // Prepare data
                const labels = <?= json_encode(array_column($chartData, 'crypto_date')) ?>;
                const prices = <?= json_encode($prices) ?>;
                
                // Build datasets based on chart mode
                const datasets = [];
                
                // Always add price data
                datasets.push({
                    label: 'Price',
                    data: prices,
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    yAxisID: 'y'
                });
                
                <?php if ($chartMode === 'bollinger' || $chartMode === 'complete'): ?>
                // Add Bollinger Bands
                const upperBand = <?= json_encode($bollingerBands['upper']) ?>;
                const middleBand = <?= json_encode($bollingerBands['middle']) ?>;
                const lowerBand = <?= json_encode($bollingerBands['lower']) ?>;
                
                datasets.push({
                    label: 'Upper Band',
                    data: upperBand,
                    borderColor: 'rgba(239, 68, 68, 0.5)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 1,
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0,
                    yAxisID: 'y'
                });
                
                datasets.push({
                    label: 'Middle Band (SMA)',
                    data: middleBand,
                    borderColor: 'rgba(251, 191, 36, 0.7)',
                    backgroundColor: 'rgba(251, 191, 36, 0.1)',
                    borderWidth: 1,
                    fill: false,
                    pointRadius: 0,
                    yAxisID: 'y'
                });
                
                datasets.push({
                    label: 'Lower Band',
                    data: lowerBand,
                    borderColor: 'rgba(16, 185, 129, 0.5)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 1,
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0,
                    yAxisID: 'y'
                });
                <?php endif; ?>
                
                <?php if ($chartMode === 'complete'): ?>
                // Add RSI
                const rsiData = <?= json_encode($rsiData) ?>;
                datasets.push({
                    label: 'RSI',
                    data: rsiData,
                    borderColor: 'rgb(168, 85, 247)',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1,
                    pointRadius: 0,
                    yAxisID: 'y1'
                });
                
                // Add MACD
                const macdLine = <?= json_encode($macdData['macd']) ?>;
                const signalLine = <?= json_encode($macdData['signal']) ?>;
                const histogram = <?= json_encode($macdData['histogram']) ?>;
                
                datasets.push({
                    label: 'MACD',
                    data: macdLine,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1,
                    pointRadius: 0,
                    yAxisID: 'y2'
                });
                
                datasets.push({
                    label: 'Signal',
                    data: signalLine,
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1,
                    pointRadius: 0,
                    yAxisID: 'y2'
                });
                
                datasets.push({
                    label: 'Histogram',
                    data: histogram,
                    backgroundColor: histogram.map(v => v >= 0 ? 'rgba(16, 185, 129, 0.5)' : 'rgba(239, 68, 68, 0.5)'),
                    borderColor: histogram.map(v => v >= 0 ? 'rgb(16, 185, 129)' : 'rgb(239, 68, 68)'),
                    borderWidth: 1,
                    type: 'bar',
                    yAxisID: 'y2'
                });
                <?php endif; ?>
                
                // Configure scales
                const scales = {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date/Time',
                            font: { size: 14, weight: 'bold' }
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            maxTicksLimit: 20
                        },
                        grid: { display: false }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Price',
                            font: { size: 14, weight: 'bold' }
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(2);
                            }
                        },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    }
                };
                
                <?php if ($chartMode === 'complete'): ?>
                // Add RSI scale
                scales.y1 = {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'RSI',
                        font: { size: 12, weight: 'bold' }
                    },
                    min: 0,
                    max: 100,
                    grid: {
                        drawOnChartArea: false
                    }
                };
                
                // Add MACD scale
                scales.y2 = {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'MACD',
                        font: { size: 12, weight: 'bold' }
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                };
                <?php endif; ?>
                
                const config = {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: '<?= htmlspecialchars($selectedPair) ?> - <?= ucfirst($chartMode) ?> Chart',
                                font: { size: 18, weight: 'bold' },
                                padding: { top: 10, bottom: 20 }
                            },
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15
                                }
                            },
                            tooltip: {
                                enabled: true,
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += context.parsed.y.toFixed(2);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: scales
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
                const period = document.getElementById('periodSelect').value;
                const aggregation = document.getElementById('aggregationSelect').value;
                const mode = document.getElementById('modeSelect').value;
                window.location.href = '?pair=' + encodeURIComponent(pair) + 
                                       '&period=' + encodeURIComponent(period) + 
                                       '&aggregation=' + encodeURIComponent(aggregation) +
                                       '&mode=' + encodeURIComponent(mode);
            }
        }
        
        function updateFilters() {
            const pair = document.getElementById('pairSelect').value;
            const period = document.getElementById('periodSelect').value;
            const aggregation = document.getElementById('aggregationSelect').value;
            const mode = document.getElementById('modeSelect').value;
            
            if (pair) {
                window.location.href = '?pair=' + encodeURIComponent(pair) + 
                                       '&period=' + encodeURIComponent(period) + 
                                       '&aggregation=' + encodeURIComponent(aggregation) +
                                       '&mode=' + encodeURIComponent(mode);
            }
        }
    </script>
</body>
</html>
