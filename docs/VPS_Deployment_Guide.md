# 🚀 EasyMed VPS Deployment Guide

This guide will walk you through deploying your EasyMed clinic management system to a Virtual Private Server (VPS).

## 📋 Prerequisites

- ✅ GitHub repository with your EasyMed project
- ✅ VPS with Ubuntu 20.04/22.04 LTS (recommended)
- ✅ Domain name (optional but recommended)
- ✅ SSH access to your VPS

## 🛠️ Step 1: Initial VPS Setup

### 1.1 Connect to Your VPS

```bash
# Replace with your actual VPS IP address
ssh root@your-vps-ip-address

# Or if you have a non-root user:
ssh username@your-vps-ip-address
```

### 1.2 Update System Packages

```bash
# Update package lists
sudo apt update

# Upgrade installed packages
sudo apt upgrade -y

# Install essential packages
sudo apt install -y curl wget git unzip software-properties-common
```

### 1.3 Create a New User (if using root)

```bash
# Create new user
sudo adduser easymed

# Add user to sudo group
sudo usermod -aG sudo easymed

# Switch to new user
su - easymed
```

## 🌐 Step 2: Install Web Server Stack

### 2.1 Install Nginx

```bash
# Install Nginx
sudo apt install -y nginx

# Start and enable Nginx
sudo systemctl start nginx
sudo systemctl enable nginx

# Check status
sudo systemctl status nginx
```

### 2.2 Install PHP 8.1

```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP and required extensions
sudo apt install -y php8.1-fpm php8.1-cli php8.1-mysql php8.1-sqlite3 \
    php8.1-curl php8.1-json php8.1-mbstring php8.1-xml php8.1-zip \
    php8.1-gd php8.1-intl php8.1-bcmath

# Start and enable PHP-FPM
sudo systemctl start php8.1-fpm
sudo systemctl enable php8.1-fpm
```

### 2.3 Install SQLite (Primary Database)

```bash
# Install SQLite
sudo apt install -y sqlite3

# Verify installation
sqlite3 --version
```

### 2.4 Install MySQL (Optional Backup)

```bash
# Install MySQL Server
sudo apt install -y mysql-server

# Secure MySQL installation
sudo mysql_secure_installation

# Start and enable MySQL
sudo systemctl start mysql
sudo systemctl enable mysql
```

## 📁 Step 3: Clone and Setup Project

### 3.1 Clone Repository

```bash
# Navigate to web directory
cd /var/www

# Clone your repository (replace with your actual repo)
sudo git clone https://github.com/Crimsoin/EasyMed.git

# Change ownership to web user
sudo chown -R www-data:www-data /var/www/EasyMed
sudo chmod -R 755 /var/www/EasyMed

# Set proper permissions for database and uploads
sudo chmod -R 775 /var/www/EasyMed/database
sudo chmod -R 775 /var/www/EasyMed/assets/uploads
```

### 3.2 Configure PHP Settings

```bash
# Edit PHP configuration
sudo nano /etc/php/8.1/fpm/php.ini

# Find and modify these settings:
# upload_max_filesize = 50M
# post_max_size = 50M
# max_execution_time = 300
# memory_limit = 256M
# date.timezone = Asia/Manila

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

## 🔧 Step 4: Configure Nginx

### 4.1 Create Nginx Server Block

```bash
# Create new site configuration
sudo nano /etc/nginx/sites-available/easymed
```

Add the following configuration:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;  # Replace with your domain
    root /var/www/EasyMed;
    index index.php index.html;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";

    # Main location block
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~* \.(log|sql|sqlite|bak)$ {
        deny all;
    }

    # Static files caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
}
```

### 4.2 Enable Site and Test Configuration

```bash
# Enable the site
sudo ln -s /etc/nginx/sites-available/easymed /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test Nginx configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

## 🗄️ Step 5: Database Setup

### 5.1 Initialize SQLite Database

```bash
# Navigate to project directory
cd /var/www/EasyMed

# Create database directory if not exists
sudo mkdir -p database

# Copy the existing database or create new one
# If you have existing data:
sudo cp database/easymed.sqlite database/easymed.sqlite.backup

# Set proper permissions
sudo chown www-data:www-data database/easymed.sqlite
sudo chmod 664 database/easymed.sqlite
```

### 5.2 Update Configuration

```bash
# Edit configuration file
sudo nano /var/www/EasyMed/includes/config.php
```

Update the configuration for production:

```php
<?php
// Database configuration
define('DB_TYPE', 'sqlite');
define('SQLITE_PATH', __DIR__ . '/../database/easymed.sqlite');

// Site configuration
define('SITE_URL', 'https://your-domain.com'); // Update with your domain
define('SITE_NAME', 'EasyMed - Patient Appointment Management System');

// Email configuration (update with your SMTP settings)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'EasyMed Clinic');
define('SMTP_ENCRYPTION', 'tls');

// Security settings (generate new key)
define('ENCRYPTION_KEY', 'generate-a-new-secret-key-here');
define('SESSION_TIMEOUT', 3600);

// Set timezone
date_default_timezone_set('Asia/Manila');
?>
```

## 🔒 Step 6: SSL Certificate (Let's Encrypt)

### 6.1 Install Certbot

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Test automatic renewal
sudo certbot renew --dry-run
```

### 6.2 Update Nginx Configuration (Auto-updated by Certbot)

The SSL configuration will be automatically added to your Nginx config.

## 🔥 Step 7: Configure Firewall

### 7.1 Setup UFW Firewall

```bash
# Enable UFW
sudo ufw enable

# Allow SSH
sudo ufw allow ssh

# Allow HTTP and HTTPS
sudo ufw allow 'Nginx Full'

# Check status
sudo ufw status
```

## 📊 Step 8: Monitoring and Maintenance

### 8.1 Setup Log Rotation

```bash
# Create log rotation config
sudo nano /etc/logrotate.d/easymed
```

Add:

```
/var/www/EasyMed/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

### 8.2 Create Backup Script

```bash
# Create backup directory
sudo mkdir -p /var/backups/easymed

# Create backup script
sudo nano /usr/local/bin/easymed-backup.sh
```

Add:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/easymed"
PROJECT_DIR="/var/www/EasyMed"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p "$BACKUP_DIR/$DATE"

# Backup database
cp "$PROJECT_DIR/database/easymed.sqlite" "$BACKUP_DIR/$DATE/"

# Backup uploads
tar -czf "$BACKUP_DIR/$DATE/uploads.tar.gz" -C "$PROJECT_DIR" assets/uploads/

# Remove backups older than 7 days
find "$BACKUP_DIR" -type d -mtime +7 -exec rm -rf {} \;

echo "Backup completed: $DATE"
```

Make executable and add to cron:

```bash
# Make executable
sudo chmod +x /usr/local/bin/easymed-backup.sh

# Add to crontab (daily backup at 2 AM)
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/easymed-backup.sh
```

## 🚀 Step 9: Deployment Automation

### 9.1 Create Deployment Script

```bash
# Create deployment script
sudo nano /usr/local/bin/easymed-deploy.sh
```

Add:

```bash
#!/bin/bash
cd /var/www/EasyMed

# Backup current database
cp database/easymed.sqlite database/easymed.sqlite.backup

# Pull latest changes
git pull origin main

# Set permissions
chown -R www-data:www-data /var/www/EasyMed
chmod -R 755 /var/www/EasyMed
chmod -R 775 /var/www/EasyMed/database
chmod -R 775 /var/www/EasyMed/assets/uploads

# Restart services
systemctl restart php8.1-fpm
systemctl restart nginx

echo "Deployment completed!"
```

Make executable:

```bash
sudo chmod +x /usr/local/bin/easymed-deploy.sh
```

## 🔍 Step 10: Testing and Verification

### 10.1 Test Website

1. **Open browser** and navigate to your domain
2. **Test login** with admin credentials
3. **Check all features**:
   - Patient registration
   - Doctor appointments
   - Admin dashboard
   - File uploads
   - Email notifications

### 10.2 Performance Testing

```bash
# Install Apache Bench for testing
sudo apt install -y apache2-utils

# Test website performance
ab -n 100 -c 10 https://your-domain.com/

# Monitor system resources
htop
```

## 📋 Step 11: Maintenance Checklist

### Daily:
- [ ] Check server resources (`htop`, `df -h`)
- [ ] Review error logs (`tail -f /var/log/nginx/error.log`)
- [ ] Verify backup completion

### Weekly:
- [ ] Update system packages (`sudo apt update && sudo apt upgrade`)
- [ ] Check SSL certificate expiry (`sudo certbot certificates`)
- [ ] Review application logs

### Monthly:
- [ ] Full system backup
- [ ] Security audit
- [ ] Performance optimization

## 🆘 Troubleshooting

### Common Issues:

**1. 502 Bad Gateway:**
```bash
# Check PHP-FPM status
sudo systemctl status php8.1-fpm
sudo systemctl restart php8.1-fpm
```

**2. Permission Issues:**
```bash
# Fix ownership and permissions
sudo chown -R www-data:www-data /var/www/EasyMed
sudo chmod -R 755 /var/www/EasyMed
```

**3. Database Errors:**
```bash
# Check database permissions
ls -la /var/www/EasyMed/database/
# Should show: -rw-rw-r-- www-data www-data easymed.sqlite
```

**4. Email Not Working:**
- Verify SMTP credentials in `config.php`
- Check if port 587 is open
- Test with Gmail app password

## 📞 Support

For issues:
1. Check logs: `/var/log/nginx/error.log` and `/var/log/php8.1-fpm.log`
2. Review this guide
3. Check EasyMed documentation

---

## 🎉 Congratulations!

Your EasyMed clinic management system is now live on your VPS! 

**Next Steps:**
- Set up monitoring (optional: install Netdata)
- Configure automated backups to cloud storage
- Set up staging environment for testing updates
- Consider CDN for static assets (optional)

**Access URLs:**
- Website: `https://your-domain.com`
- Admin Panel: `https://your-domain.com/admin`
- ERD Documentation: `https://your-domain.com/docs/EasyMed_ERD.html`