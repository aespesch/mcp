# Cryptocurrency Charts - PHP Script

## Description
PHP script to display interactive charts of cryptocurrency data stored in MySQL database.

## Features
- ✅ Loads database credentials from .ENV file
- ✅ Lists all available cryptocurrency pairs with record counts
- ✅ Interactive pair selection dropdown
- ✅ Beautiful, responsive chart using Chart.js
- ✅ Statistics display (min, max, average prices)
- ✅ Mobile-friendly interface
- ✅ Smooth animations and modern UI

## Requirements
- PHP 7.4 or higher
- MySQL database
- PDO PHP extension
- Web server (Apache, Nginx, or PHP built-in server)
- Modern web browser with JavaScript enabled

## Installation

1. **Copy files to your web directory:**
   ```bash
   cp crypto_charts.php /path/to/your/webroot/
   ```

2. **Create .ENV file:**
   ```bash
   cp .ENV.example .ENV
   ```

3. **Configure database credentials in .ENV:**
   ```env
   DB_HOST=localhost
   database=your_database_name
   user=your_database_user
   pwd=your_database_password
   ```

4. **Set proper permissions:**
   ```bash
   chmod 644 crypto_charts.php
   chmod 600 .ENV  # Keep credentials secure
   ```

## Usage

### Using PHP Built-in Server (for testing):
```bash
php -S localhost:8000
```
Then open: http://localhost:8000/crypto_charts.php

### Using Apache/Nginx:
Simply navigate to: http://yourserver.com/crypto_charts.php

## How It Works

1. **Initial Load:**
   - Script reads database credentials from .ENV file
   - Connects to MySQL database
   - Queries available currency pairs with record counts
   - Displays dropdown with all available pairs

2. **Pair Selection:**
   - User selects a pair (e.g., SOLBRL)
   - Script extracts base currency (SOL) and quote currency (BRL)
   - Queries database for all price records of selected pair
   - Generates interactive chart with Chart.js

3. **Data Display:**
   - Line chart showing price evolution over time
   - Statistics cards with min, max, average prices and total records
   - Responsive design adapts to screen size
   - Hover tooltips show exact values

## Database Schema

The script expects the following tables:
- `candle_time` - Time-based candle data
- `candle_day` - Daily candle data
- `symbol_pair` - Currency pair definitions
- `symbol` - Individual symbols/currencies

## Troubleshooting

**"Database connection failed":**
- Check your .ENV file credentials
- Verify MySQL server is running
- Confirm user has proper permissions

**"No data available":**
- Verify the selected pair has records in database
- Check if the pair naming convention matches (e.g., SOL + BRL = SOLBRL)

**".ENV file not found":**
- Ensure .ENV file is in the same directory as crypto_charts.php
- Check file permissions

## Security Notes

- ✅ Uses PDO with prepared statements (prevents SQL injection)
- ✅ .ENV file keeps credentials out of source code
- ✅ HTML special chars encoding (prevents XSS)
- ⚠️ Ensure .ENV file is not accessible via web (.htaccess or nginx config)

## Customization

### Change Chart Colors:
Edit the `borderColor` and `backgroundColor` in the Chart.js config:
```javascript
borderColor: 'rgb(102, 126, 234)',  // Line color
backgroundColor: 'rgba(102, 126, 234, 0.1)',  // Fill color
```

### Adjust Chart Height:
Modify the CSS `.chart-container` height:
```css
.chart-container {
    height: 500px;  /* Change this value */
}
```

### Limit Data Points:
Add a LIMIT clause to the query in `getChartData()` function:
```sql
ORDER BY cd.cndl_date, ct.cntm_minutes
LIMIT 1000
```

## License
Free to use and modify.

## Support
For issues or questions, please check:
- PHP error logs
- MySQL error logs
- Browser console for JavaScript errors
