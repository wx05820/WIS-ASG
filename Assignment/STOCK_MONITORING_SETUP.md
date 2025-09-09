# Stock Monitoring System Setup

## Overview
The stock monitoring system automatically tracks product inventory levels and sends email alerts to administrators when products are low in stock or out of stock.

## Features
- 📊 Automatic stock level monitoring
- 📧 Email alerts to all admin users
- 🎯 Configurable stock thresholds
- 📱 Web-based monitoring dashboard
- ⏰ Automated cron job support
- 📝 Detailed logging

## Files Created
- `_base.php` - Added stock monitoring functions
- `admin/stock_monitor.php` - Web dashboard and monitoring script
- `admin/stock_integration.php` - Integration helpers for product pages
- `admin/cron_stock_check.sh` - Cron job script
- `logs/` - Directory for log files

## Setup Instructions

### 1. Email Configuration
Edit the email settings in `_base.php` function `sendStockAlertEmail()`:
```php
$mail->Username = 'your-email@gmail.com'; // Your Gmail address
$mail->Password = 'your-app-password';    // Your Gmail app password
```

### 2. Gmail App Password Setup
1. Enable 2-factor authentication on your Gmail account
2. Generate an App Password:
   - Go to Google Account settings
   - Security → App passwords
   - Generate password for "Mail"
   - Use this password in the script

### 3. Manual Testing
Visit: `http://your-domain/admin/stock_monitor.php`
- Test with different thresholds
- Verify email delivery
- Check the monitoring dashboard

### 4. Automatic Monitoring (Cron Job)
Add to your server's crontab:
```bash
# Check stock every day at 9 AM
0 9 * * * /path/to/your/project/admin/cron_stock_check.sh

# Check stock every 6 hours
0 */6 * * * /path/to/your/project/admin/cron_stock_check.sh
```

### 5. Integration with Product Management
The system automatically triggers when products are updated through the admin interface.

## Usage

### Web Dashboard
- Access via Admin menu → "Stock Monitor"
- View real-time stock status
- Run manual checks with different thresholds
- See detailed reports

### Email Alerts
- Sent to all users with Admin/SuperAdmin role
- Includes low stock and out-of-stock products
- Professional HTML format with actionable information

### Thresholds
- Default: 10 items
- Configurable per check
- Out of stock: 0 items (always critical)
- Low stock: ≤ threshold items

## Customization

### Change Default Threshold
Edit the default value in function calls:
```php
runStockMonitoring(20); // Use 20 as threshold instead of 10
```

### Modify Email Template
Edit `generateStockAlertEmail()` function in `_base.php`

### Add More Alert Recipients
Modify the SQL query in `sendLowStockAlert()` to include other user roles:
```php
WHERE role IN ('Admin', 'SuperAdmin', 'Manager')
```

## Troubleshooting

### Email Not Sending
1. Check Gmail credentials
2. Verify app password is correct
3. Check server logs in `/logs/` directory
4. Test with a simple email first

### Permission Issues
1. Ensure web server can write to `/logs/` directory
2. Make cron script executable: `chmod +x cron_stock_check.sh`

### Database Errors
1. Verify product table has `qty` column
2. Check user table has admin users with email addresses
3. Review error logs

## Security Notes
- Store sensitive email credentials in environment variables (recommended)
- Limit access to stock monitor page to admin users only
- Regularly rotate email passwords
- Monitor log files for unauthorized access attempts

## Support
For issues or customization requests, contact your system administrator.
