# Troubleshooting Guide - Cryptocurrency Charts

## Problem: "No data available" message

If you're seeing "No data available" when selecting a currency pair, follow these steps to diagnose and fix the issue.

## Quick Fixes

### 1. Try "All Time" Period First
If you selected a specific time period (24h, 7d, 30d), try changing to "All Time":
- This removes the time filter and shows if ANY data exists for the pair
- If data appears with "All Time", your data might be older than the selected period

### 2. Check Different Pairs
- Try selecting different currency pairs from the dropdown
- If other pairs work, the issue is specific to one pair

### 3. Use Debug Mode
Add `&debug=1` to your URL or click "Enable Debug Mode" at the bottom of the page.

Example:
```
https://yoursite.com/crypto_charts.php?pair=BNBBRL&period=24h&aggregation=raw&debug=1
```

## Debug Mode Information

When debug mode is enabled, you'll see:
- **Selected Pair**: The pair you chose (e.g., BNBBRL)
- **Base Currency**: Detected base (e.g., BNB)
- **Quote Currency**: Detected quote (e.g., BRL)
- **Period & Aggregation**: Your filters
- **Records Returned**: How many records the query found
- **SQL Query**: The actual database query being executed

## Common Issues and Solutions

### Issue 1: Wrong Currency Detection
**Symptoms:**
- Debug shows wrong base/quote split
- Example: BNBBRL detected as "BNB" + "BRL" but database has "BNBR" + "BL"

**Solution:**
The script now queries the database first to get the correct split. If this fails, check your database:

```sql
SELECT 
    CONCAT(base.smbl_code, quote.smbl_code) AS pair,
    base.smbl_code as base,
    quote.smbl_code as quote
FROM symbol_pair sp
INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
WHERE CONCAT(base.smbl_code, quote.smbl_code) = 'BNBBRL';
```

### Issue 2: No Data in Time Period
**Symptoms:**
- Debug shows: "Database contains X records for this pair, but none match the selected time period"
- Works with "All Time" but not with 24h/7d/30d

**Possible Causes:**
1. **Old Data**: Your database only has historical data
2. **Time Zone Issue**: Database time doesn't match server time
3. **Date Format Issue**: Dates in database are in unexpected format

**Solutions:**

**A) Check Latest Data Date:**
```sql
SELECT 
    MAX(TIMESTAMP(cd.cndl_date, SEC_TO_TIME(ct.cntm_minutes * 60))) as latest_date
FROM candle_time ct
INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
WHERE base.smbl_code = 'BNB' AND quote.smbl_code = 'BRL';
```

**B) Check Server Time:**
```sql
SELECT NOW() as server_time;
```

**C) If data is old, use appropriate period:**
- If latest data is from last week, use "Last 30 Days" or "All Time"
- The script now shows a suggestion to try "All Time" automatically

### Issue 3: No Records in Database
**Symptoms:**
- Debug shows: "No records found in database"
- Red warning message appears

**Solutions:**

**A) Verify the pair exists:**
```sql
SELECT 
    CONCAT(base.smbl_code, quote.smbl_code) AS pair,
    COUNT(*) AS record_count
FROM candle_time ct
INNER JOIN candle_day cd ON ct.cntm_candle_day_id = cd.cndl_id
INNER JOIN symbol_pair sp ON cd.cndl_symbol_pair_id = sp.smpr_id
INNER JOIN symbol base ON sp.smpr_base_symbol_id = base.smbl_id
INNER JOIN symbol quote ON sp.smpr_quote_symbol_id = quote.smbl_id
WHERE base.smbl_code = 'BNB' AND quote.smbl_code = 'BRL'
GROUP BY pair;
```

**B) List all available pairs:**
```sql
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
```

### Issue 4: Aggregation Problems
**Symptoms:**
- Raw data works but aggregated data doesn't
- Some periods work, others don't

**Solutions:**
- Try different aggregation levels (raw, hour, day)
- Check debug SQL query for syntax errors
- Verify MySQL version supports the date functions used

## Step-by-Step Diagnostic Process

1. **Enable debug mode** (add `&debug=1` to URL)

2. **Check the debug panel:**
   - Are base/quote currencies correct?
   - How many records were returned?

3. **Try "All Time" period:**
   - If it works → data exists but is outside your time range
   - If it doesn't work → check database for the pair

4. **Run SQL queries manually:**
   - Copy the SQL from debug panel
   - Run it in phpMyAdmin or MySQL Workbench
   - Replace `:base` with actual base currency (e.g., 'BNB')
   - Replace `:quote` with actual quote currency (e.g., 'BRL')

5. **Check data freshness:**
   - Run the "Check Latest Data Date" query above
   - Compare with current server time

## New Features in This Version

### Improved Currency Pair Detection
The script now:
1. First queries the database to get exact base/quote split
2. Falls back to common quote currencies (BRL, USD, EUR, BTC, ETH, USDT, BUSD)
3. Finally tries 3-character split as last resort

### Smart Diagnostics
- Shows if data exists but doesn't match time filter
- Provides total record count for the pair
- Suggests trying "All Time" when appropriate
- Includes debug mode with SQL query display

## Getting Help

If issues persist after following this guide:

1. **Collect debug information:**
   - Enable debug mode
   - Take screenshot of debug panel
   - Note which pairs work and which don't

2. **Check your data:**
   - Run the diagnostic SQL queries
   - Document your findings

3. **Environment details:**
   - PHP version
   - MySQL version
   - Time zone settings

## Example: Successful Debug Session

```
URL: crypto_charts.php?pair=BNBBRL&period=24h&aggregation=raw&debug=1

Debug Output:
- Selected Pair: BNBBRL
- Base Currency: BNB
- Quote Currency: BRL
- Period: 24h
- Aggregation: raw
- Records Returned: 0

Additional Info:
"Database contains 1,000 records for this pair, but none match 
the selected time period (24h)."

Action Taken:
Clicked "Try viewing All Time data instead"

Result:
✓ Chart displayed with 1,000 data points
✓ Data range: Oct 1 - Oct 15, 2025
✓ Conclusion: Data exists but is not from last 24 hours
```

## Prevention

To avoid these issues in the future:

1. **Keep data current**: Ensure your data import/update process is running
2. **Monitor data quality**: Regularly check latest data dates
3. **Use appropriate periods**: Match period to your data availability
4. **Start broad**: Always start with "All Time" to see full picture

---

**Version**: 3.0
**Last Updated**: October 2025
