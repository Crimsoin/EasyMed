# EasyMed Deployment Guide for VPS + Nginx

This guide is specifically for deploying EasyMed on a VPS with Nginx web server.

---

## 📋 Prerequisites

- VPS with root/sudo access (Ubuntu 20.04/22.04 or Debian)
- Domain name pointed to your VPS IP
- SSH access to your server

---

## Step 1: Connect to Your VPS

```bash
ssh root@your-vps-ip
# or
ssh username@your-vps-ip
```

---

## Step 2: Install Required Software

### Update System
```bash
sudo apt update
sudo apt upgrade -y
```

### Install Nginx
```bash
sudo apt install nginx -y
sudo systemctl start nginx
sudo systemctl enable nginx
```

### Install PHP 8.1+ and Extensions
```bash
sudo apt install php8.1-fpm php8.1-sqlite3 php8.1-mbstring php8.1-curl php8.1-xml php8.1-gd php8.1-zip -y

# Verify PHP installation
php -v
php -m | grep sqlite3
```

### Install Git (for easy deployment)
```bash
sudo apt install git -y
```

---

## Step 3: Upload Your Project

### Option A: Using Git (Recommended)
```bash
cd /var/www
sudo git clone https://github.com/Crimsoin/EasyMed.git easymed
cd easymed
```

### Option B: Using SCP (from your local machine)
```bash
# From your local machine (PowerShell):
scp -r C:\xampp\htdocs\Project_EasyMed root@your-vps-ip:/var/www/easymed
```

### Option C: Using SFTP
- Use FileZilla, WinSCP, or similar
- Upload to `/var/www/easymed`

---

## Step 4: Set Proper Permissions

```bash
cd /var/www/easymed

# Set owner to www-data (Nginx user)
sudo chown -R www-data:www-data .

# Set directory permissions
sudo find . -type d -exec chmod 755 {} \;

# Set file permissions
sudo find . -type f -exec chmod 644 {} \;

# Database permissions
sudo chmod 755 database/
sudo chmod 644 database/easymed.sqlite

# Make sure www-data can write to database
sudo chown www-data:www-data database/easymed.sqlite
sudo chown www-data:www-data database/

# Uploads directory
sudo chmod 755 assets/uploads/
sudo chmod 755 assets/uploads/payment_receipts/
sudo chown -R www-data:www-data assets/uploads/

# Logs directory
sudo chmod 755 logs/
sudo chown www-data:www-data logs/
```

---

## Step 5: Configure Nginx

### Create Nginx Configuration File
```bash
sudo nano /etc/nginx/sites-available/easymed
```

### Add This Configuration:
```nginx
server {
    listen 80;
    listen [::]:80;
    
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/easymed;
    index index.php index.html;
    
    # Logging
    access_log /var/log/nginx/easymed_access.log;
    error_log /var/log/nginx/easymed_error.log;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Hide Nginx version
    server_tokens off;
    
    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
    }
    
    # Protect sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Protect database files
    location ~* \.(sqlite|db|sql)$ {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Protect config files
    location ~ /(config|database)\.php$ {
        deny all;
    }
    
    # Block access to backup files
    location ~* \.(bak|backup|old|tmp|log)$ {
        deny all;
    }
    
    # Protect includes directory
    location ^~ /includes/ {
        location ~ \.php$ {
            deny all;
        }
    }
    
    # Protect database directory
    location ^~ /database/ {
        deny all;
    }
    
    # Protect logs directory
    location ^~ /logs/ {
        deny all;
    }
    
    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    # Deny access to certain file types
    location ~* \.(engine|inc|info|install|make|module|profile|test|po|sh|.*sql|theme|tpl(\.php)?|xtmpl)$|^(\..*|Entries.*|Repository|Root|Tag|Template)$|\.php_ {
        deny all;
    }
    
    # File upload size
    client_max_body_size 10M;
}
```

### Enable the Site
```bash
# Create symbolic link
sudo ln -s /etc/nginx/sites-available/easymed /etc/nginx/sites-enabled/

# Remove default site (optional)
sudo rm /etc/nginx/sites-enabled/default

# Test Nginx configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

---

## Step 6: Configure PHP-FPM

### Edit PHP-FPM Configuration
```bash
sudo nano /etc/php/8.1/fpm/php.ini
```

### Update These Settings:
```ini
# Find and change these values:
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M

# Security
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

# Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

### Create PHP Error Log Directory
```bash
sudo mkdir -p /var/log/php
sudo chown www-data:www-data /var/log/php
```

### Restart PHP-FPM
```bash
sudo systemctl restart php8.1-fpm
```

---

## Step 7: Update EasyMed Configuration

### Edit config.php
```bash
sudo nano /var/www/easymed/includes/config.php
```

### Update These Values:
```php
// Site configuration
define('SITE_URL', 'https://yourdomain.com');
define('BASE_URL', 'https://yourdomain.com');

// Email configuration
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'Your Clinic Name');

// Security - Generate new key
define('ENCRYPTION_KEY', 'paste-new-secure-key-here');

// Environment
define('ENVIRONMENT', 'production');
```

### Generate Encryption Key
```bash
openssl rand -base64 32
# Copy the output and paste it in config.php
```

---

## Step 8: Install SSL Certificate (Let's Encrypt)

### Install Certbot
```bash
sudo apt install certbot python3-certbot-nginx -y
```

### Get SSL Certificate
```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

### Follow the prompts:
1. Enter email address
2. Agree to terms
3. Choose to redirect HTTP to HTTPS (option 2)

### Verify Auto-Renewal
```bash
sudo certbot renew --dry-run
```

### After SSL is installed, update config.php:
```bash
sudo nano /var/www/easymed/includes/config.php
```

Make sure URLs use `https://`:
```php
define('SITE_URL', 'https://yourdomain.com');
define('BASE_URL', 'https://yourdomain.com');
```

---

## Step 9: Set Up Firewall (UFW)

```bash
# Enable firewall
sudo ufw enable

# Allow SSH (important!)
sudo ufw allow ssh
sudo ufw allow 22/tcp

# Allow HTTP and HTTPS
sudo ufw allow 'Nginx Full'

# Or manually:
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Check status
sudo ufw status
```

---

## Step 10: Test Your Deployment

### Test Nginx Configuration
```bash
sudo nginx -t
```

### Test PHP Processing
```bash
echo "<?php phpinfo(); ?>" | sudo tee /var/www/easymed/info.php
# Visit: https://yourdomain.com/info.php
# Then delete it: sudo rm /var/www/easymed/info.php
```

### Check Logs
```bash
# Nginx error log
sudo tail -f /var/log/nginx/easymed_error.log

# Nginx access log
sudo tail -f /var/log/nginx/easymed_access.log

# PHP-FPM log
sudo tail -f /var/log/php8.1-fpm.log

# Application logs
sudo tail -f /var/www/easymed/logs/php_errors.log
```

### Test the Site
1. Visit `https://yourdomain.com`
2. Test registration
3. Test login
4. Test appointment booking
5. Test file uploads

---

## Step 11: Set Up Automated Backups

### Create Backup Script
```bash
sudo nano /root/backup-easymed.sh
```

### Add This Script:
```bash
#!/bin/bash

# Configuration
BACKUP_DIR="/root/backups/easymed"
DATE=$(date +%Y%m%d_%H%M%S)
DB_FILE="/var/www/easymed/database/easymed.sqlite"
RETENTION_DAYS=7

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

# Backup database
cp $DB_FILE $BACKUP_DIR/easymed_$DATE.sqlite

# Compress old backups (older than 1 day)
find $BACKUP_DIR -name "*.sqlite" -mtime +1 -exec gzip {} \;

# Delete backups older than retention period
find $BACKUP_DIR -name "*.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup completed: easymed_$DATE.sqlite"
```

### Make Script Executable
```bash
sudo chmod +x /root/backup-easymed.sh
```

### Add to Crontab (Daily at 2 AM)
```bash
sudo crontab -e
```

Add this line:
```
0 2 * * * /root/backup-easymed.sh >> /var/log/easymed_backup.log 2>&1
```

### Test Backup Script
```bash
sudo /root/backup-easymed.sh
ls -lh /root/backups/easymed/
```

---

## Step 12: Performance Optimization

### Enable PHP OPcache
```bash
sudo nano /etc/php/8.1/fpm/conf.d/10-opcache.ini
```

Add/Update:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### Enable Gzip Compression in Nginx
```bash
sudo nano /etc/nginx/nginx.conf
```

Uncomment/add in `http` block:
```nginx
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;
```

### Restart Services
```bash
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

---

## Step 13: Monitoring Setup

### Install Fail2Ban (Protection against brute force)
```bash
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### Monitor Server Resources
```bash
# Check disk space
df -h

# Check memory usage
free -h

# Check running processes
htop  # or: top

# Check Nginx status
sudo systemctl status nginx

# Check PHP-FPM status
sudo systemctl status php8.1-fpm
```

---

## Troubleshooting

### Issue: 502 Bad Gateway
**Solution:**
```bash
# Check PHP-FPM is running
sudo systemctl status php8.1-fpm

# Check socket file exists
ls -la /var/run/php/php8.1-fpm.sock

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

### Issue: Permission Denied (Database)
**Solution:**
```bash
cd /var/www/easymed
sudo chown www-data:www-data database/easymed.sqlite
sudo chmod 644 database/easymed.sqlite
sudo chmod 755 database/
```

### Issue: File Upload Fails
**Solution:**
```bash
# Check permissions
sudo chown -R www-data:www-data /var/www/easymed/assets/uploads/
sudo chmod -R 755 /var/www/easymed/assets/uploads/

# Check PHP settings
sudo nano /etc/php/8.1/fpm/php.ini
# Verify: upload_max_filesize = 10M
# Verify: post_max_size = 10M

sudo systemctl restart php8.1-fpm
```

### Issue: White Screen/500 Error
**Solution:**
```bash
# Check Nginx error log
sudo tail -50 /var/log/nginx/easymed_error.log

# Check PHP error log
sudo tail -50 /var/log/php8.1-fpm.log

# Check application log
sudo tail -50 /var/www/easymed/logs/php_errors.log

# Temporarily enable errors (for debugging only)
sudo nano /var/www/easymed/includes/config.php
# Change: define('ENVIRONMENT', 'development');
# Visit site, then change back to 'production'
```

---

## Security Checklist

- [x] SSL certificate installed and working
- [x] Firewall (UFW) configured
- [x] Database file protected
- [x] Config files protected
- [x] Directory listing disabled
- [x] PHP version hidden
- [x] Nginx version hidden
- [x] Fail2Ban installed
- [x] Regular backups configured
- [x] Strong passwords used
- [x] SSH key authentication (recommended)

---

## Maintenance Commands

### Update System
```bash
sudo apt update
sudo apt upgrade -y
```

### Restart Services
```bash
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm
```

### View Logs
```bash
# Real-time Nginx access log
sudo tail -f /var/log/nginx/easymed_access.log

# Real-time Nginx error log
sudo tail -f /var/log/nginx/easymed_error.log

# Application errors
sudo tail -f /var/www/easymed/logs/php_errors.log
```

### Check Certificate Expiry
```bash
sudo certbot certificates
```

### Manual Backup
```bash
sudo /root/backup-easymed.sh
```

---

## Quick Reference

### Important Paths
- **Project root:** `/var/www/easymed`
- **Nginx config:** `/etc/nginx/sites-available/easymed`
- **PHP config:** `/etc/php/8.1/fpm/php.ini`
- **Logs:** `/var/log/nginx/` and `/var/www/easymed/logs/`
- **Backups:** `/root/backups/easymed/`

### Important Commands
```bash
# Restart Nginx
sudo systemctl restart nginx

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm

# Test Nginx config
sudo nginx -t

# Reload Nginx (no downtime)
sudo systemctl reload nginx

# Check service status
sudo systemctl status nginx
sudo systemctl status php8.1-fpm
```

---

## 🎉 Deployment Complete!

Your EasyMed system should now be running on your VPS with Nginx!

**Access your site at:** `https://yourdomain.com`

For support, check:
- Nginx error logs
- PHP-FPM logs  
- Application logs in `/var/www/easymed/logs/`
