# 📁 Estrutura do Projeto

## 🏗️ Arquitetura do Sistema

```
┌─────────────────────────────────────────────────────────────┐
│                    CRYPTO ANALYSIS SYSTEM                   │
└─────────────────────────────────────────────────────────────┘
                              │
                ┌─────────────┴─────────────┐
                │                           │
        ┌───────▼────────┐         ┌───────▼────────┐
        │ crypto_chart   │         │ crypto_strategy│
        │     .php       │         │     .php       │
        │                │         │                │
        │  📊 Gráficos  │         │  🎯 Trading   │
        │  Indicadores   │         │  Simulação     │
        └───────┬────────┘         └───────┬────────┘
                │                           │
                └─────────────┬─────────────┘
                              │
                    ┌─────────▼─────────┐
                    │ crypto_functions  │
                    │      .php         │
                    │                   │
                    │  🔧 Biblioteca   │
                    │  de Funções       │
                    └─────────┬─────────┘
                              │
                    ┌─────────▼─────────┐
                    │    MySQL DB       │
                    │   🗄️ Dados       │
                    └───────────────────┘
```

## 📦 Componentes

### 1. crypto_functions.php (Core Library)
```
┌─────────────────────────────────────────┐
│      crypto_functions.php               │
├─────────────────────────────────────────┤
│                                         │
│  📂 Database Functions                  │
│  ├─ initDatabase()                      │
│  ├─ getAvailablePairs()                 │
│  ├─ getChartData()                      │
│  └─ parseCurrencyPair()                 │
│                                         │
│  📊 Technical Indicators                │
│  ├─ calculateBollingerBands()           │
│  ├─ calculateRSI()                      │
│  ├─ calculateMACD()                     │
│  └─ calculateEMA()                      │
│                                         │
│  ⚙️ Utilities                           │
│  └─ loadEnv()                           │
│                                         │
└─────────────────────────────────────────┘
```

### 2. crypto_chart.php (Visualization)
```
┌─────────────────────────────────────────┐
│        crypto_chart.php                 │
├─────────────────────────────────────────┤
│                                         │
│  🎨 UI Components                       │
│  ├─ Header & Navigation                 │
│  ├─ Control Panel (filters)             │
│  ├─ Statistics Cards                    │
│  └─ Interactive Chart                   │
│                                         │
│  📊 Chart Modes                         │
│  ├─ Basic (price only)                  │
│  ├─ Bollinger Bands                     │
│  └─ Complete (BB + RSI + MACD)          │
│                                         │
│  🔄 Data Flow                           │
│  ├─ Include crypto_functions.php        │
│  ├─ Get user parameters                 │
│  ├─ Fetch data from DB                  │
│  ├─ Calculate indicators                │
│  └─ Render chart with Chart.js          │
│                                         │
└─────────────────────────────────────────┘
```

### 3. crypto_strategy.php (Trading Simulator)
```
┌─────────────────────────────────────────┐
│      crypto_strategy.php                │
├─────────────────────────────────────────┤
│                                         │
│  🎯 Trading Strategy                    │
│  ├─ Buy Signal Detection                │
│  │  ├─ Price vs Lower Band              │
│  │  └─ RSI < 35                         │
│  │                                      │
│  └─ Sell Signal Detection               │
│     ├─ Price vs Upper Band              │
│     └─ RSI > 65                         │
│                                         │
│  💰 Portfolio Management                │
│  ├─ Track Crypto Balance                │
│  ├─ Track Fiat Balance                  │
│  └─ Execute Trades                      │
│                                         │
│  📈 Performance Metrics                 │
│  ├─ Total Return                        │
│  ├─ Win Rate                            │
│  ├─ Number of Trades                    │
│  └─ Buy & Hold Comparison               │
│                                         │
│  🎨 Visualization                       │
│  ├─ Chart with Buy/Sell markers         │
│  ├─ Performance Dashboard               │
│  └─ Trade History Table                 │
│                                         │
└─────────────────────────────────────────┘
```

## 🔄 Fluxo de Dados

### crypto_chart.php Flow
```
User Input
    │
    ├─ Select Pair (BTCUSDT)
    ├─ Select Period (30d)
    ├─ Select Aggregation (hour)
    └─ Select Mode (complete)
    │
    ▼
Parse Parameters
    │
    ▼
Query Database
    │
    ├─ Join: candle_time + candle_day
    ├─ Join: symbol_pair + symbol
    ├─ Filter: date range
    └─ Group: by time period
    │
    ▼
Calculate Indicators
    │
    ├─ Bollinger Bands (20, 2.0)
    ├─ RSI (14)
    └─ MACD (12, 26, 9)
    │
    ▼
Render Chart
    │
    ├─ Price Line
    ├─ Bollinger Lines
    ├─ RSI Line (secondary axis)
    └─ MACD Lines (tertiary axis)
    │
    ▼
Display Statistics
    └─ Current, High, Low, Average
```

### crypto_strategy.php Flow
```
User Input
    │
    ├─ Select Pair
    ├─ Initial Capital ($10,000)
    └─ Trade Amount ($1,000)
    │
    ▼
Fetch Historical Data
    │
    ▼
Calculate Indicators
    │
    ├─ Bollinger Bands
    ├─ RSI
    └─ MACD
    │
    ▼
Simulate Trading
    │
    ├─ Loop through each candle
    │   │
    │   ├─ Check BUY conditions
    │   │   ├─ Price ≤ Lower Band
    │   │   ├─ RSI < 35
    │   │   └─ Has Fiat Balance
    │   │       │
    │   │       ├─ YES → Execute BUY
    │   │       │   ├─ Calculate crypto amount
    │   │       │   ├─ Update balances
    │   │       │   └─ Record trade
    │   │       │
    │   │       └─ NO → Continue
    │   │
    │   └─ Check SELL conditions
    │       ├─ Price ≥ Upper Band
    │       ├─ RSI > 65
    │       └─ Has Crypto Balance
    │           │
    │           ├─ YES → Execute SELL
    │           │   ├─ Calculate fiat amount
    │           │   ├─ Update balances
    │           │   └─ Record trade
    │           │
    │           └─ NO → Continue
    │
    ▼
Calculate Performance
    │
    ├─ Final Portfolio Value
    ├─ Total Return %
    ├─ Buy & Hold Return
    └─ Win Rate
    │
    ▼
Render Results
    │
    ├─ Performance Dashboard
    ├─ Chart with Signals
    └─ Trade History Table
```

## 🗄️ Estrutura do Banco de Dados

```
┌─────────────────────────────────────────────┐
│              Database Schema                │
└─────────────────────────────────────────────┘

symbol
├─ smbl_id (PK)
├─ smbl_code (BTC, ETH, USDT, etc.)
└─ smbl_name

symbol_pair
├─ smpr_id (PK)
├─ smpr_base_symbol_id (FK → symbol)
└─ smpr_quote_symbol_id (FK → symbol)

candle_day
├─ cndl_id (PK)
├─ cndl_date
└─ cndl_symbol_pair_id (FK → symbol_pair)

candle_time
├─ cntm_id (PK)
├─ cntm_candle_day_id (FK → candle_day)
├─ cntm_minutes (0-1439)
├─ cntm_open_price
├─ cntm_close_price
├─ cntm_high_price
├─ cntm_low_price
└─ cntm_volume

Relationships:
symbol ──┬─< symbol_pair
         │
symbol ──┘
         
symbol_pair ──< candle_day ──< candle_time
```

## 🔧 Configuração

```
┌─────────────────────────────────────┐
│         .ENV Configuration          │
├─────────────────────────────────────┤
│                                     │
│  DB_HOST=localhost                  │
│  database=crypto_db                 │
│  user=crypto_user                   │
│  pwd=secure_password                │
│                                     │
└─────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│      loadEnv() Function             │
├─────────────────────────────────────┤
│                                     │
│  1. Read .ENV file                  │
│  2. Parse KEY=VALUE pairs           │
│  3. Set environment variables       │
│  4. Validate required fields        │
│                                     │
└─────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────┐
│      PDO Connection                 │
├─────────────────────────────────────┤
│                                     │
│  new PDO(                           │
│    "mysql:host=...",                │
│    $user,                           │
│    $password,                       │
│    [                                │
│      ERRMODE => EXCEPTION           │
│      FETCH_MODE => ASSOC            │
│    ]                                │
│  )                                  │
│                                     │
└─────────────────────────────────────┘
```

## 📊 Indicadores Técnicos

### Bollinger Bands Calculation
```
Period = 20 candles

For each point i:
    ┌────────────────────────────────┐
    │  Get 20 previous prices        │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  Calculate SMA                 │
    │  SMA = Σ(prices) / 20          │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  Calculate Standard Deviation  │
    │  σ = sqrt(Σ(x - SMA)² / 20)   │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  Calculate Bands               │
    │  Upper = SMA + (2 × σ)         │
    │  Middle = SMA                  │
    │  Lower = SMA - (2 × σ)         │
    └────────────────────────────────┘
```

### RSI Calculation
```
Period = 14 candles

    ┌────────────────────────────────┐
    │  Calculate Price Changes       │
    │  Δ = Price[i] - Price[i-1]    │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  Separate Gains and Losses     │
    │  Gain = Δ if Δ > 0            │
    │  Loss = |Δ| if Δ < 0          │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  Calculate Averages            │
    │  AvgGain = Σ(gains) / 14      │
    │  AvgLoss = Σ(losses) / 14     │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  Calculate RS and RSI          │
    │  RS = AvgGain / AvgLoss       │
    │  RSI = 100 - (100 / (1 + RS)) │
    └────────────────────────────────┘

Output Range: 0-100
  < 30  → Oversold (Buy Signal)
  > 70  → Overbought (Sell Signal)
```

### MACD Calculation
```
Fast EMA = 12 periods
Slow EMA = 26 periods
Signal = 9 periods

    ┌────────────────────────────────┐
    │  Calculate Fast EMA (12)       │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  Calculate Slow EMA (26)       │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  MACD Line = Fast - Slow       │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  Signal = EMA of MACD (9)      │
    └───────────┬────────────────────┘
                │
                ▼
    ┌────────────────────────────────┐
    │  Histogram = MACD - Signal     │
    └────────────────────────────────┘

Crossovers indicate trend changes
```

## 🎨 UI Components

### crypto_chart.php
```
┌──────────────────────────────────────────────┐
│  Header (Purple Gradient)                    │
│  📈 Cryptocurrency Technical Analysis        │
└──────────────────────────────────────────────┘
┌──────────────────────────────────────────────┐
│  Control Panel (Gray Background)             │
│  [Pair ▼] [Period ▼] [Agg ▼] [Mode ▼]      │
└──────────────────────────────────────────────┘
┌──────────────────────────────────────────────┐
│  Info Box (Blue, conditional)                │
│  📊 Shows mode description                   │
└──────────────────────────────────────────────┘
┌──────────────────────────────────────────────┐
│  Statistics Grid                             │
│  [Current] [Change] [Avg] [High] [Low] [Pts]│
└──────────────────────────────────────────────┘
┌──────────────────────────────────────────────┐
│  Chart Area (Height: 600-900px)              │
│  [Interactive Line Chart with Chart.js]      │
└──────────────────────────────────────────────┘
```

### crypto_strategy.php
```
┌──────────────────────────────────────────────┐
│  Header (Blue Gradient)                      │
│  🎯 Crypto Trading Strategy Simulator        │
└──────────────────────────────────────────────┘
┌──────────────────────────────────────────────┐
│  Control Panel                               │
│  [Pair▼] [Period▼] [Agg▼] [Capital] [Trade] │
└──────────────────────────────────────────────┘
┌──────────────────────────────────────────────┐
│  Strategy Info (Purple Box)                  │
│  📋 Explains buy/sell conditions             │
└──────────────────────────────────────────────┘
┌──────────────────────────────────────────────┐
│  Performance Dashboard (Grid)                │
│  [Return] [Final] [Fiat] [Crypto] [Trades]  │
│  [vs Hold]                                   │
└──────────────────────────────────────────────┘
┌──────────────────────────────────────────────┐
│  Chart with Trade Signals (700px)            │
│  [Price + Bollinger + RSI + 🟢🔴 markers]   │
└──────────────────────────────────────────────┘
┌──────────────────────────────────────────────┐
│  Trade History Table (Scrollable)            │
│  [# | Type | Date | Price | Amount | ...]    │
└──────────────────────────────────────────────┘
```

## 🔐 Security Features

```
✅ PDO with Prepared Statements
   └─ Prevents SQL Injection

✅ Environment Variables (.ENV)
   └─ Keeps credentials secure

✅ Input Validation
   └─ Sanitizes user input

✅ HTTPS Recommended
   └─ Encrypts data in transit

⚠️ NEVER commit .ENV to git
   └─ Add to .gitignore
```

## 📈 Performance Considerations

```
Database Queries:
├─ Use indexes on:
│  ├─ candle_day.cndl_date
│  ├─ candle_time.cntm_candle_day_id
│  └─ symbol_pair IDs
│
Data Aggregation:
├─ Hour aggregation: ~10x fewer points
├─ Day aggregation: ~240x fewer points
└─ Faster rendering & calculation
│
Caching Opportunities:
├─ Cache available pairs
├─ Cache technical indicators
└─ Use browser localStorage
```

---

**📚 Esta estrutura foi projetada para ser modular, extensível e fácil de manter!**
