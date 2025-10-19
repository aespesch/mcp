<?php
/**
 * Cryptocurrency Trading Strategy Simulator
 * Implements a trading strategy and visualizes buy/sell signals
 */

// Include functions library
require_once __DIR__ . '/crypto_functions.php';

// Initialize database connection
$pdo = initDatabase();

// Get request parameters
$selectedPair = $_GET['pair'] ?? null;
$period = $_GET['period'] ?? '30d';
$aggregation = $_GET['aggregation'] ?? 'hour';
$initialFiat = floatval($_GET['initial_fiat'] ?? 10000); // Initial fiat amount
$tradeAmount = floatval($_GET['trade_amount'] ?? 1000); // Amount per trade

// Parse currency pair
list($baseCurrency, $quoteCurrency) = parseCurrencyPair($pdo, $selectedPair);

// Get data
$availablePairs = getAvailablePairs($pdo);
$chartData = [];
$trades = [];
$cryptoBalance = 0;
$fiatBalance = $initialFiat;

if ($baseCurrency && $quoteCurrency) {
    $chartData = getChartData($pdo, $baseCurrency, $quoteCurrency, $aggregation, $period);
    
    if (!empty($chartData)) {
        $prices = array_map(function($row) {
            return (float)$row['crypto_price'];
        }, $chartData);
        
        $dates = array_column($chartData, 'crypto_date');
        
        // Calculate technical indicators
        $bollingerBands = calculateBollingerBands($prices);
        $rsiData = calculateRSI($prices);
        $macdData = calculateMACD($prices);
        
        // Implement trading strategy
        $position = 'none'; // none, long
        $lastAction = null;
        
        for ($i = 20; $i < count($prices); $i++) {
            // Skip if indicators are not available
            if ($bollingerBands['lower'][$i] === null || 
                $rsiData[$i] === null || 
                $macdData['histogram'][$i] === null) {
                continue;
            }
            
            $price = $prices[$i];
            $rsi = $rsiData[$i];
            $lowerBand = $bollingerBands['lower'][$i];
            $upperBand = $bollingerBands['upper'][$i];
            $macdHist = $macdData['histogram'][$i];
            $prevMacdHist = $i > 0 ? $macdData['histogram'][$i - 1] : null;
            
            // BUY SIGNAL CONDITIONS:
            // 1. Price touches or goes below lower Bollinger Band
            // 2. RSI is oversold (< 35)
            // 3. Not already in position
            if ($position === 'none' && 
                $price <= $lowerBand * 1.005 && 
                $rsi < 35) {
                
                // Calculate how much crypto to buy
                $amountToSpend = min($tradeAmount, $fiatBalance);
                if ($amountToSpend >= 10) { // Minimum trade amount
                    $cryptoBought = $amountToSpend / $price;
                    $cryptoBalance += $cryptoBought;
                    $fiatBalance -= $amountToSpend;
                    
                    $trades[] = [
                        'type' => 'BUY',
                        'index' => $i,
                        'date' => $dates[$i],
                        'price' => $price,
                        'amount' => $cryptoBought,
                        'total' => $amountToSpend,
                        'rsi' => $rsi,
                        'cryptoBalance' => $cryptoBalance,
                        'fiatBalance' => $fiatBalance
                    ];
                    
                    $position = 'long';
                    $lastAction = 'buy';
                }
            }
            
            // SELL SIGNAL CONDITIONS:
            // 1. Price touches or goes above upper Bollinger Band
            // 2. RSI is overbought (> 65)
            // 3. Currently holding crypto
            elseif ($position === 'long' && 
                    $price >= $upperBand * 0.995 && 
                    $rsi > 65 &&
                    $cryptoBalance > 0) {
                
                // Sell all crypto
                $cryptoToSell = $cryptoBalance;
                $fiatReceived = $cryptoToSell * $price;
                $fiatBalance += $fiatReceived;
                $cryptoBalance = 0;
                
                $trades[] = [
                    'type' => 'SELL',
                    'index' => $i,
                    'date' => $dates[$i],
                    'price' => $price,
                    'amount' => $cryptoToSell,
                    'total' => $fiatReceived,
                    'rsi' => $rsi,
                    'cryptoBalance' => $cryptoBalance,
                    'fiatBalance' => $fiatBalance
                ];
                
                $position = 'none';
                $lastAction = 'sell';
            }
        }
        
        // Calculate final portfolio value
        $currentPrice = end($prices);
        $finalCryptoValue = $cryptoBalance * $currentPrice;
        $finalTotalValue = $fiatBalance + $finalCryptoValue;
        $totalReturn = $finalTotalValue - $initialFiat;
        $returnPercent = ($totalReturn / $initialFiat) * 100;
        
        // Calculate if just holding (buy and hold strategy)
        $buyHoldCrypto = $initialFiat / $prices[20]; // Buy at first possible point
        $buyHoldValue = $buyHoldCrypto * $currentPrice;
        $buyHoldReturn = (($buyHoldValue - $initialFiat) / $initialFiat) * 100;
        
        // Separate buy and sell trades
        $buyTrades = array_filter($trades, function($t) { return $t['type'] === 'BUY'; });
        $sellTrades = array_filter($trades, function($t) { return $t['type'] === 'SELL'; });
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Trading Strategy Simulator</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #7c3aed 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #1e3a8a 0%, #7c3aed 100%);
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .control-group select,
        .control-group input {
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1em;
            background: white;
            transition: all 0.3s ease;
        }
        
        .control-group select {
            cursor: pointer;
        }
        
        .control-group select:hover,
        .control-group input:focus {
            border-color: #7c3aed;
        }
        
        .control-group select:focus,
        .control-group input:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        
        .strategy-info {
            padding: 20px 30px;
            background: #e0e7ff;
            border-left: 4px solid #7c3aed;
            margin: 20px 30px;
            border-radius: 5px;
        }
        
        .strategy-info h4 {
            margin-bottom: 10px;
            color: #5b21b6;
            font-size: 1.1em;
        }
        
        .strategy-info ul {
            margin-left: 20px;
            color: #4c1d95;
        }
        
        .strategy-info li {
            margin: 5px 0;
            font-size: 0.9em;
        }
        
        .performance {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .performance-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            text-align: center;
        }
        
        .performance-card.positive {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .performance-card.negative {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .performance-label {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .performance-value {
            font-size: 1.8em;
            font-weight: 700;
            color: #1e3a8a;
        }
        
        .performance-value.positive {
            color: #10b981;
        }
        
        .performance-value.negative {
            color: #ef4444;
        }
        
        .performance-subtitle {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .chart-container {
            padding: 30px;
            position: relative;
            height: 700px;
        }
        
        .trades-table {
            padding: 30px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .trades-table h3 {
            margin-bottom: 15px;
            color: #1e3a8a;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
        }
        
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
        }
        
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e9ecef;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-buy {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-sell {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .no-data {
            text-align: center;
            padding: 100px 30px;
            color: #6c757d;
        }
        
        .no-data h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Crypto Trading Strategy Simulator</h1>
            <p>Simulação de estratégia de investimento com sinais de compra e venda</p>
        </div>
        
        <div class="controls">
            <div class="control-group">
                <label for="pairSelect">Par de Moedas</label>
                <select id="pairSelect" onchange="updateStrategy()">
                    <option value="">-- Selecione --</option>
                    <?php foreach ($availablePairs as $pair): ?>
                        <option value="<?= htmlspecialchars($pair['pair']) ?>" 
                                <?= $pair['pair'] === $selectedPair ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pair['pair']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="control-group">
                <label for="periodSelect">Período</label>
                <select id="periodSelect" onchange="updateStrategy()">
                    <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>7 Dias</option>
                    <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>30 Dias</option>
                    <option value="90d" <?= $period === '90d' ? 'selected' : '' ?>>90 Dias</option>
                    <option value="1y" <?= $period === '1y' ? 'selected' : '' ?>>1 Ano</option>
                    <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>Tudo</option>
                </select>
            </div>
            
            <div class="control-group">
                <label for="aggregationSelect">Agregação</label>
                <select id="aggregationSelect" onchange="updateStrategy()">
                    <option value="hour" <?= $aggregation === 'hour' ? 'selected' : '' ?>>Por Hora</option>
                    <option value="day" <?= $aggregation === 'day' ? 'selected' : '' ?>>Por Dia</option>
                </select>
            </div>
            
            <div class="control-group">
                <label for="initialFiat">Capital Inicial (<?= $quoteCurrency ?>)</label>
                <input type="number" id="initialFiat" value="<?= $initialFiat ?>" 
                       min="100" step="100" onchange="updateStrategy()">
            </div>
            
            <div class="control-group">
                <label for="tradeAmount">Valor por Trade (<?= $quoteCurrency ?>)</label>
                <input type="number" id="tradeAmount" value="<?= $tradeAmount ?>" 
                       min="10" step="10" onchange="updateStrategy()">
            </div>
        </div>
        
        <div class="strategy-info">
            <h4>📋 Estratégia Implementada</h4>
            <ul>
                <li><strong>Sinal de COMPRA:</strong> Preço toca banda inferior de Bollinger E RSI &lt; 35 (sobrevendido)</li>
                <li><strong>Sinal de VENDA:</strong> Preço toca banda superior de Bollinger E RSI &gt; 65 (sobrecomprado)</li>
                <li><strong>Gestão de Risco:</strong> Valor fixo por operação, saldo é simulado</li>
                <li><strong>Período Bollinger:</strong> 20 períodos, 2 desvios padrão</li>
                <li><strong>Período RSI:</strong> 14 períodos</li>
            </ul>
        </div>
        
        <?php if ($selectedPair && !empty($chartData) && !empty($trades)): ?>
            
            <div class="performance">
                <div class="performance-card <?= $totalReturn >= 0 ? 'positive' : 'negative' ?>">
                    <div class="performance-label">Retorno Total</div>
                    <div class="performance-value <?= $totalReturn >= 0 ? 'positive' : 'negative' ?>">
                        <?= $totalReturn >= 0 ? '+' : '' ?><?= number_format($returnPercent, 2) ?>%
                    </div>
                    <div class="performance-subtitle">
                        <?= $totalReturn >= 0 ? '+' : '' ?><?= number_format($totalReturn, 2) ?> <?= $quoteCurrency ?>
                    </div>
                </div>
                
                <div class="performance-card">
                    <div class="performance-label">Valor Final</div>
                    <div class="performance-value"><?= number_format($finalTotalValue, 2) ?></div>
                    <div class="performance-subtitle">de <?= number_format($initialFiat, 2) ?> <?= $quoteCurrency ?></div>
                </div>
                
                <div class="performance-card">
                    <div class="performance-label">Saldo Fiat</div>
                    <div class="performance-value"><?= number_format($fiatBalance, 2) ?></div>
                    <div class="performance-subtitle"><?= $quoteCurrency ?></div>
                </div>
                
                <div class="performance-card">
                    <div class="performance-label">Saldo Crypto</div>
                    <div class="performance-value"><?= number_format($cryptoBalance, 6) ?></div>
                    <div class="performance-subtitle">
                        ≈ <?= number_format($finalCryptoValue, 2) ?> <?= $quoteCurrency ?>
                    </div>
                </div>
                
                <div class="performance-card">
                    <div class="performance-label">Total de Trades</div>
                    <div class="performance-value"><?= count($trades) ?></div>
                    <div class="performance-subtitle">
                        <?= count($buyTrades) ?> compras, <?= count($sellTrades) ?> vendas
                    </div>
                </div>
                
                <div class="performance-card <?= $returnPercent > $buyHoldReturn ? 'positive' : 'negative' ?>">
                    <div class="performance-label">vs Buy & Hold</div>
                    <div class="performance-value">
                        <?= number_format($buyHoldReturn, 2) ?>%
                    </div>
                    <div class="performance-subtitle">
                        <?= $returnPercent > $buyHoldReturn ? 'Estratégia melhor!' : 'Hold melhor' ?>
                    </div>
                </div>
            </div>
            
            <div class="chart-container">
                <canvas id="strategyChart"></canvas>
            </div>
            
            <div class="trades-table">
                <h3>📊 Histórico de Operações (<?= count($trades) ?> trades)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tipo</th>
                            <th>Data</th>
                            <th>Preço</th>
                            <th>Quantidade</th>
                            <th>Total</th>
                            <th>RSI</th>
                            <th>Saldo Crypto</th>
                            <th>Saldo Fiat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trades as $idx => $trade): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($trade['type']) ?>">
                                    <?= $trade['type'] === 'BUY' ? '📈 COMPRA' : '📉 VENDA' ?>
                                </span>
                            </td>
                            <td><?= $trade['date'] ?></td>
                            <td><?= number_format($trade['price'], 2) ?></td>
                            <td><?= number_format($trade['amount'], 6) ?></td>
                            <td><?= number_format($trade['total'], 2) ?></td>
                            <td><?= number_format($trade['rsi'], 1) ?></td>
                            <td><?= number_format($trade['cryptoBalance'], 6) ?></td>
                            <td><?= number_format($trade['fiatBalance'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
                const ctx = document.getElementById('strategyChart').getContext('2d');
                
                // Prepare data
                const labels = <?= json_encode($dates) ?>;
                const prices = <?= json_encode($prices) ?>;
                const upperBand = <?= json_encode($bollingerBands['upper']) ?>;
                const middleBand = <?= json_encode($bollingerBands['middle']) ?>;
                const lowerBand = <?= json_encode($bollingerBands['lower']) ?>;
                const rsiData = <?= json_encode($rsiData) ?>;
                
                // Prepare buy/sell signals
                const buySignals = [];
                const sellSignals = [];
                
                <?php foreach ($trades as $trade): ?>
                    <?php if ($trade['type'] === 'BUY'): ?>
                        buySignals.push({
                            x: labels[<?= $trade['index'] ?>],
                            y: <?= $trade['price'] ?>
                        });
                    <?php else: ?>
                        sellSignals.push({
                            x: labels[<?= $trade['index'] ?>],
                            y: <?= $trade['price'] ?>
                        });
                    <?php endif; ?>
                <?php endforeach; ?>
                
                const config = {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Preço',
                                data: prices,
                                borderColor: 'rgb(30, 58, 138)',
                                backgroundColor: 'rgba(30, 58, 138, 0.1)',
                                borderWidth: 2,
                                fill: false,
                                tension: 0.1,
                                pointRadius: 0,
                                pointHoverRadius: 5,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Banda Superior',
                                data: upperBand,
                                borderColor: 'rgba(239, 68, 68, 0.5)',
                                borderWidth: 1,
                                borderDash: [5, 5],
                                fill: false,
                                pointRadius: 0,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Banda Média (SMA)',
                                data: middleBand,
                                borderColor: 'rgba(251, 191, 36, 0.7)',
                                borderWidth: 1,
                                fill: false,
                                pointRadius: 0,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Banda Inferior',
                                data: lowerBand,
                                borderColor: 'rgba(16, 185, 129, 0.5)',
                                borderWidth: 1,
                                borderDash: [5, 5],
                                fill: false,
                                pointRadius: 0,
                                yAxisID: 'y'
                            },
                            {
                                label: '🟢 COMPRA',
                                data: buySignals,
                                type: 'scatter',
                                backgroundColor: '#10b981',
                                borderColor: '#065f46',
                                borderWidth: 2,
                                pointRadius: 8,
                                pointHoverRadius: 10,
                                yAxisID: 'y'
                            },
                            {
                                label: '🔴 VENDA',
                                data: sellSignals,
                                type: 'scatter',
                                backgroundColor: '#ef4444',
                                borderColor: '#991b1b',
                                borderWidth: 2,
                                pointRadius: 8,
                                pointHoverRadius: 10,
                                yAxisID: 'y'
                            },
                            {
                                label: 'RSI',
                                data: rsiData,
                                borderColor: 'rgb(124, 58, 237)',
                                backgroundColor: 'rgba(124, 58, 237, 0.1)',
                                borderWidth: 2,
                                fill: false,
                                tension: 0.1,
                                pointRadius: 0,
                                yAxisID: 'y1'
                            }
                        ]
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
                                text: '<?= htmlspecialchars($selectedPair) ?> - Estratégia de Trading',
                                font: { size: 20, weight: 'bold' },
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
                                intersect: false
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Data/Hora',
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
                                    text: 'Preço (<?= $quoteCurrency ?>)',
                                    font: { size: 14, weight: 'bold' }
                                },
                                grid: { color: 'rgba(0, 0, 0, 0.05)' }
                            },
                            y1: {
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
                            }
                        }
                    }
                };
                
                new Chart(ctx, config);
            </script>
            
        <?php elseif ($selectedPair && !empty($chartData) && empty($trades)): ?>
            <div class="no-data">
                <h3>⚠️ Nenhuma oportunidade de trade encontrada</h3>
                <p>A estratégia não identificou sinais de compra/venda claros no período selecionado.</p>
                <p>Tente aumentar o período ou ajustar os parâmetros.</p>
            </div>
        <?php elseif ($selectedPair && empty($chartData)): ?>
            <div class="no-data">
                <h3>Sem dados disponíveis</h3>
                <p>Nenhum registro encontrado para o par selecionado: <?= htmlspecialchars($selectedPair) ?></p>
            </div>
        <?php else: ?>
            <div class="no-data">
                <h3>Selecione um par de moedas</h3>
                <p>Escolha um par no menu acima para simular a estratégia</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function updateStrategy() {
            const pair = document.getElementById('pairSelect').value;
            const period = document.getElementById('periodSelect').value;
            const aggregation = document.getElementById('aggregationSelect').value;
            const initialFiat = document.getElementById('initialFiat').value;
            const tradeAmount = document.getElementById('tradeAmount').value;
            
            if (pair) {
                window.location.href = '?pair=' + encodeURIComponent(pair) + 
                                       '&period=' + encodeURIComponent(period) + 
                                       '&aggregation=' + encodeURIComponent(aggregation) +
                                       '&initial_fiat=' + encodeURIComponent(initialFiat) +
                                       '&trade_amount=' + encodeURIComponent(tradeAmount);
            }
        }
    </script>
</body>
</html>
