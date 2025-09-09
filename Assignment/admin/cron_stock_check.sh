#!/bin/bash
# Stock Monitoring Cron Job Script
# Add this to your crontab to run automatic stock checks
# 
# Example crontab entries:
# Run every day at 9 AM: 0 9 * * * /path/to/your/project/admin/cron_stock_check.sh
# Run every 6 hours: 0 */6 * * * /path/to/your/project/admin/cron_stock_check.sh

# Change to the script directory
cd "$(dirname "$0")"

# Run the stock monitoring script
php stock_monitor.php >> ../logs/stock_monitor.log 2>&1

# Optional: Clean up old log files (keep last 30 days)
find ../logs -name "stock_monitor.log.*" -mtime +30 -delete 2>/dev/null || true
