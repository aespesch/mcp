#!/usr/bin/env python3
"""
CSV to SQL Converter for Cryptocurrency Candle Data

This script processes CSV files containing cryptocurrency candle data
and generates SQL files with stored procedure calls for database insertion.
"""

import os
import re
import csv
from pathlib import Path


def extract_coin_pair_from_filename(filename: str) -> str:
    """
    Extract the cryptocurrency pair from the CSV filename.
    
    Args:
        filename: Name of the CSV file (e.g., 'df_candles_BNBBRL.CSV')
    
    Returns:
        The coin pair string (e.g., 'BNBBRL')
    
    Raises:
        ValueError: If the filename doesn't match the expected pattern
    """
    # Pattern to match files like df_candles_BNBBRL.CSV
    pattern = r'df_candles_([A-Z]+)\.CSV'
    match = re.search(pattern, filename, re.IGNORECASE)
    
    if match:
        return match.group(1).upper()
    else:
        raise ValueError(f"Filename '{filename}' doesn't match expected pattern 'df_candles_XXXXX.CSV'")


def process_csv_file(csv_filepath: Path) -> None:
    """
    Process a single CSV file and generate the corresponding SQL file.
    
    Args:
        csv_filepath: Path object pointing to the CSV file
    """
    try:
        # Extract coin pair from filename
        coin_pair = extract_coin_pair_from_filename(csv_filepath.name)
        
        # Define output SQL filename
        sql_filepath = csv_filepath.with_suffix('.SQL')
        
        print(f"Processing {csv_filepath.name} -> {sql_filepath.name}")
        
        # Read CSV and write SQL
        with open(csv_filepath, 'r', encoding='utf-8') as csv_file, \
             open(sql_filepath, 'w', encoding='utf-8') as sql_file:
            
            csv_reader = csv.DictReader(csv_file)
            row_count = 0
            
            for row in csv_reader:
                # Extract required fields
                timestamp = row['timestamp']
                open_price = row['open_price']
                high_price = row['high_price']
                low_price = row['low_price']
                close_price = row['close_price']
                volume = row['volume']
                
                # Generate SQL statement
                sql_statement = (
                    f"call sp_insert_candle('{coin_pair}','{timestamp}',"
                    f"{open_price},{high_price},{low_price},{close_price},{volume});\n"
                )
                
                sql_file.write(sql_statement)
                row_count += 1
            
            print(f"  Generated {row_count} SQL statements for {coin_pair}")
    
    except Exception as e:
        print(f"Error processing {csv_filepath.name}: {str(e)}")


def main():
    """
    Main function to find and process all matching CSV files in the current directory.
    """
    # Get current directory
    current_dir = Path(__file__).parent.resolve()
    
    print(f"Scanning directory: {current_dir}\n")
    
    # Find all CSV files matching the pattern
    csv_files = list(current_dir.glob('df_candles_*.CSV'))
    
    # Also check for lowercase extension
    csv_files.extend(current_dir.glob('df_candles_*.csv'))
    
    if not csv_files:
        print("No CSV files matching pattern 'df_candles_*.CSV' found in the current directory.")
        return
    
    print(f"Found {len(csv_files)} CSV file(s) to process:\n")
    
    # Process each CSV file
    for csv_file in csv_files:
        process_csv_file(csv_file)
        print()
    
    print("Processing complete!")


if __name__ == "__main__":
    main()
