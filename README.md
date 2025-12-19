# 🏥 EasyMed - Patient Appointment Management System

Complete clinic management system for appointments, doctors, patients, and lab services.

---

## 📋 Table of Contents

1. [Quick Start (Local Development)](#-quick-start-local-development)
2. [Production Deployment (VPS + Nginx)](#-production-deployment-vps--nginx)
3. [Configuration & Credentials](#-configuration--credentials)
4. [Features](#-features)
5. [Troubleshooting](#-troubleshooting)

---

## 🚀 Quick Start (Local Development)

### Prerequisites
- XAMPP (Apache + PHP 8.1+)
- Web browser

### Installation

1. **Install XAMPP**
   - Download: https://www.apachefriends.org/download.html
   - Install to: `C:\xampp`

2. **Get the Project**
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/Crimsoin/EasyMed.git Project_EasyMed
   ```

3. **Configure**
   ```bash
   cd Project_EasyMed\includes
   copy config.example.php config.php
   ```
   
   Edit `config.php`:
   ```php
   define('SITE_URL', 'http://localhost/Project_EasyMed');
   define('ENVIRONMENT', 'development');
   ```

4. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache

5. **Access**
   - Homepage: http://localhost/Project_EasyMed
   - Admin: http://localhost/Project_EasyMed/admin
   - Login: `admin@easymed.com` / `admin123`

---

## 🌐 Production Deployment (VPS + Nginx)

### Step-by-Step VPS Deployment

#### Step 1: Server Setup

**1.1 Connect to VPS**
```bash
ssh root@your-vps-ip
```

**1.2 Update System**
```bash
sudo apt update && sudo apt upgrade -y
```

**1.3 Install Required Software**
```bash
# Install Nginx
sudo apt install nginx -y

# Install PHP 8.1 and Extensions
sudo apt install php8.1-fpm php8.1-sqlite3 php8.1-mbstring php8.1-curl php8.1-xml php8.1-gd php8.1-zip -y

# Verify installation
php -v
nginx -v
```

---

#### Step 2: Upload Project

**Option A: Using Git**
```bash
cd /var/www
sudo git clone https://github.com/Crimsoin/EasyMed.git easymed
cd easymed
```

**Option B: Using SCP (from Windows)**
```powershell
scp -r C:\xampp\htdocs\Project_EasyMed root@your-vps-ip:/var/www/easymed
```

---

#### Step 3: Set Permissions

```bash
cd /var/www/easymed

# Set ownership
sudo chown -R www-data:www-data .

# Set directory permissions
sudo find . -type d -exec chmod 755 {} \;

# Set file permissions
sudo find . -type f -exec chmod 644 {} \;

# Database directory
sudo chmod 755 database/
sudo chmod 644 database/easymed.sqlite
sudo chown www-data:www-data database/easymed.sqlite

# Uploads directory
sudo chmod -R 755 assets/uploads/
sudo chown -R www-data:www-data assets/uploads/

# Logs directory
sudo chmod 755 logs/
sudo chown www-data:www-data logs/
```

---

#### Step 4: Configure Nginx

**4.1 Create Nginx Config**
```bash
sudo nano /etc/nginx/sites-available/easymed
```

**4.2 Add Configuration**
```nginx
server {
    listen 80;
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
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Protect sensitive files
    location ~ /\. { deny all; }
    location ~* \.(sqlite|db|sql)$ { deny all; }
    location ~ /(config|database)\.php$ { deny all; }
    location ^~ /database/ { deny all; }
    location ^~ /logs/ { deny all; }
    
    client_max_body_size 10M;
}
```

**4.3 Enable Site**
```bash
sudo ln -s /etc/nginx/sites-available/easymed /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
```

---

#### Step 5: Configure PHP

**5.1 Edit PHP Configuration**
```bash
sudo nano /etc/php/8.1/fpm/php.ini
```

**5.2 Update Settings**
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
display_errors = Off
log_errors = On
expose_php = Off
session.cookie_httponly = 1
session.cookie_secure = 1
```

**5.3 Restart PHP-FPM**
```bash
sudo systemctl restart php8.1-fpm
```

---

#### Step 6: Install SSL Certificate

**6.1 Install Certbot**
```bash
sudo apt install certbot python3-certbot-nginx -y
```

**6.2 Get Certificate**
```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

Follow prompts:
- Enter email address
- Agree to terms
- Choose redirect HTTP to HTTPS (option 2)

**6.3 Test Auto-Renewal**
```bash
sudo certbot renew --dry-run
```

---

#### Step 7: Setup Firewall

```bash
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw status
```

---

## 🔐 Configuration & Credentials

### Step 1: Generate Encryption Key

**On VPS:**
```bash
openssl rand -base64 32
```
Copy the output (example: `Kx9mP2nQ5rT8wY1zA4bC6dE0fG3hJ7kL9mN2pR5sU8v=`)

---

### Step 2: Configure Application

**Edit config.php:**
```bash
sudo nano /var/www/easymed/includes/config.php
```

**Update these values:**

```php
<?php
// Database configuration
define('DB_TYPE', 'sqlite');
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'easymed');
define('SQLITE_PATH', __DIR__ . '/../database/easymed.sqlite');

// Site configuration - UPDATE THESE
define('SITE_URL', 'https://yourdomain.com');        // ← Your domain
define('SITE_NAME', 'YourClinic - EasyMed');         // ← Your clinic name
define('BASE_URL', 'https://yourdomain.com');        // ← Your domain

// Email configuration - UPDATE THESE
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');     // ← Your Gmail
define('SMTP_PASSWORD', 'xxxx xxxx xxxx xxxx');      // ← Gmail App Password
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com'); // ← Your email
define('SMTP_FROM_NAME', 'YourClinic');              // ← Your clinic name
define('SMTP_ENCRYPTION', 'tls');

// Security settings - UPDATE THESE
define('ENCRYPTION_KEY', 'paste-key-from-step-1-here'); // ← From openssl command
define('SESSION_TIMEOUT', 3600);

// Environment - SET TO PRODUCTION
define('ENVIRONMENT', 'production'); // ← Must be 'production'

// ... rest of config remains the same ...
?>
```

---

### Step 3: Setup Gmail App Password

**For Email Notifications:**

1. Go to Google Account: https://myaccount.google.com/
2. Security → 2-Step Verification (enable if not enabled)
3. App Passwords → Generate new app password
4. Select: Mail, Other (Custom name)
5. Name it: "EasyMed Clinic"
6. Copy the 16-character password (e.g., `abcd efgh ijkl mnop`)
7. Use this in `SMTP_PASSWORD` (with or without spaces)

---

### Step 4: Update URLs in Config

**Replace all instances:**

```php
// Before (development):
define('SITE_URL', 'http://localhost/Project_EasyMed');

// After (production):
define('SITE_URL', 'https://yourdomainname.com');
```

**Important URLs to update:**
- `SITE_URL` - Your domain with https://
- `BASE_URL` - Same as SITE_URL
- `SMTP_FROM_EMAIL` - Your professional email

---

### Step 5: Verify Configuration

**Test the site:**

1. Visit: `https://yourdomain.com`
2. Test registration (check email arrives)
3. Login as admin: `admin@easymed.com` / `admin123`
4. Test appointment booking
5. Test file uploads

**Check logs if issues:**
```bash
sudo tail -f /var/log/nginx/easymed_error.log
sudo tail -f /var/www/easymed/logs/php_errors.log
```

---

## ✅ Configuration Checklist

Before going live, verify:

- [ ] `SITE_URL` = your domain with https://
- [ ] `ENVIRONMENT` = 'production'
- [ ] `ENCRYPTION_KEY` = secure 32+ character random string
- [ ] `SMTP_USERNAME` = your email
- [ ] `SMTP_PASSWORD` = Gmail app password (16 chars)
- [ ] `SMTP_FROM_EMAIL` = your professional email
- [ ] SSL certificate installed and working
- [ ] Firewall enabled (UFW)
- [ ] Backups configured
- [ ] Test email sending works
- [ ] Test file uploads work
- [ ] Test appointments work

---

## 🛡️ Security Setup

### Enable Backups

**Create backup script:**
```bash
sudo nano /root/backup-easymed.sh
```

**Add script:**
```bash
#!/bin/bash
BACKUP_DIR="/root/backups/easymed"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR
cp /var/www/easymed/database/easymed.sqlite $BACKUP_DIR/easymed_$DATE.sqlite
find $BACKUP_DIR -name "*.sqlite" -mtime +7 -delete
echo "Backup completed: $DATE"
```

**Make executable and schedule:**
```bash
sudo chmod +x /root/backup-easymed.sh
sudo crontab -e
```

**Add to crontab (daily at 2 AM):**
```
0 2 * * * /root/backup-easymed.sh >> /var/log/easymed_backup.log 2>&1
```

---

### Install Fail2Ban

```bash
sudo apt install fail2ban -y
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

---

## 🎯 Features

### For Admins
- Dashboard with analytics
- User management (doctors, patients)
- Appointment management
- Lab offers management
- Payment tracking
- System reports

### For Doctors
- Personal schedule management
- Appointment management
- Patient records
- Lab offer management
- Profile management

### For Patients
- Book appointments
- View appointment history
- Upload payment receipts
- View lab offers
- Profile management

---

## 🔧 Troubleshooting

### 502 Bad Gateway
```bash
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

### Database Permission Error
```bash
cd /var/www/easymed
sudo chown www-data:www-data database/easymed.sqlite
sudo chmod 644 database/easymed.sqlite
```

### Email Not Sending
1. Verify Gmail app password is correct
2. Check SMTP settings in config.php
3. Enable "Less secure app access" if needed
4. Check logs: `sudo tail -f /var/www/easymed/logs/php_errors.log`

### File Upload Fails
```bash
sudo chown -R www-data:www-data /var/www/easymed/assets/uploads/
sudo chmod -R 755 /var/www/easymed/assets/uploads/
```

### View Logs
```bash
# Nginx errors
sudo tail -f /var/log/nginx/easymed_error.log

# Application errors  
sudo tail -f /var/www/easymed/logs/php_errors.log

# PHP-FPM errors
sudo tail -f /var/log/php8.1-fpm.log
```

---

## 📚 Documentation

- **Full VPS Guide**: `docs/DEPLOYMENT_NGINX.md`
- **Quick Start**: `QUICK_START.md`
- **Security Report**: `docs/SECURITY_SCAN_REPORT.md`
- **Deployment Checklist**: `docs/DEPLOYMENT_CHECKLIST.md`

---

## 🆘 Support

### Important Paths (VPS)
- Project: `/var/www/easymed`
- Config: `/var/www/easymed/includes/config.php`
- Database: `/var/www/easymed/database/easymed.sqlite`
- Logs: `/var/log/nginx/` and `/var/www/easymed/logs/`
- Nginx Config: `/etc/nginx/sites-available/easymed`

### Quick Commands
```bash
# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm

# Test Nginx config
sudo nginx -t

# Check service status
sudo systemctl status nginx
sudo systemctl status php8.1-fpm

# View real-time logs
sudo tail -f /var/log/nginx/easymed_error.log
```

---

## 📄 License

This project is private. Unauthorized copying or distribution is prohibited.

---

## 👤 Author

**Crimsoin**
- GitHub: [@Crimsoin](https://github.com/Crimsoin)
- Repository: [EasyMed](https://github.com/Crimsoin/EasyMed)

---

## 🎉 Deployment Success!

Once deployed, access your clinic management system at:
**https://yourdomain.com**

**Default Admin Login:**
- Email: `admin@easymed.com`
- Password: `admin123`

⚠️ **Change the admin password immediately after first login!**

---

**Need help?** Check the troubleshooting section or review logs for specific error messages.

A comprehensive web-based clinic management system built with PHP, providing complete functionality for patient management, doctor scheduling, appointment booking, and payment processing.

## 🏥 Features

- **Patient Management**: Complete patient registration, profiles, and medical history
- **Doctor Management**: Doctor profiles, specialties, schedules, and laboratory offers
- **Appointment System**: Online booking, status tracking, and calendar management
- **Payment Gateway**: GCash integration with QR code generation and receipt verification
- **Laboratory Services**: Dynamic laboratory offers per doctor
- **Admin Dashboard**: Complete administrative control and reporting
- **Responsive Design**: Mobile-friendly interface across all devices

## 📋 Prerequisites

### System Requirements

#### Required Software
- **Web Server**: Apache 2.4+ (XAMPP, WAMP, or LAMP)
- **PHP**: Version 7.4 or higher (8.0+ recommended)
- **Database**: SQLite 3.0+ (included with PHP)
- **Web Browser**: Modern browser (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+)

#### PHP Extensions (Required)
```
- PDO (PHP Data Objects)
- PDO SQLite driver
- GD Library (for image processing)
- JSON support
- Session support
- File upload support
- OpenSSL (for secure operations)
```

#### Development Tools (Optional)
- **Git**: For version control
- **Composer**: For dependency management (if extended)
- **Node.js**: For asset compilation (if customizing)

### Hardware Requirements

#### Minimum Requirements
- **RAM**: 512 MB
- **Storage**: 1 GB free space
- **Processor**: 1 GHz single-core

#### Recommended Requirements
- **RAM**: 2 GB or more
- **Storage**: 5 GB free space
- **Processor**: 2 GHz dual-core or better

## 🚀 Installation

### Step 1: Download and Install XAMPP

1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Install XAMPP with Apache and PHP enabled
3. Start Apache from the XAMPP Control Panel

### Step 2: Clone the Repository

```bash
cd C:\xampp\htdocs
git clone https://github.com/Crimsoin/EasyMed.git
cd EasyMed
```

### Step 3: Configure the Application

1. **Database Setup**:
   - The SQLite database is included in the `database/` folder
   - No additional database server setup required

2. **Configuration**:
   - Open `includes/config.php`
   - Update the `SITE_URL` if needed:
   ```php
   define('SITE_URL', 'http://localhost/EasyMed');
   ```

3. **File Permissions**:
   - Ensure the `assets/uploads/` directory is writable
   - Ensure the `database/` directory is writable

### Step 4: Verify PHP Configuration

Check if required PHP extensions are enabled:

```bash
php -m | findstr -i "pdo sqlite gd json"
```

Required output should include:
```
gd
json
PDO
pdo_sqlite
```

### Step 5: Access the Application

1. Open your web browser
2. Navigate to: `http://localhost/EasyMed`
3. The application should load successfully

## 🔧 Configuration

### Environment Setup

1. **Development Environment**:
   ```
   SITE_URL = http://localhost/EasyMed
   DEBUG_MODE = true
   ```

2. **Production Environment**:
   ```
   SITE_URL = https://yourdomain.com
   DEBUG_MODE = false
   ```

### Default Admin Account

```
Username: admin@easymed.com
Password: admin123
Role: Administrator
```

**⚠️ Important**: Change the default admin password after first login!

### Database Location

```
Database File: database/easymed.sqlite
Backup Files: database/*.bak
```

## 📱 Browser Compatibility

| Browser | Minimum Version | Recommended |
|---------|----------------|-------------|
| Chrome  | 90+            | Latest      |
| Firefox | 88+            | Latest      |
| Safari  | 14+            | Latest      |
| Edge    | 90+            | Latest      |

## 🔒 Security Requirements

### PHP Security Settings

Ensure these settings in `php.ini`:

```ini
; File Upload Security
file_uploads = On
upload_max_filesize = 5M
max_file_uploads = 10

; Session Security
session.cookie_httponly = 1
session.use_strict_mode = 1
session.cookie_secure = 1  ; For HTTPS only

; General Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
```

### File Permissions

```bash
# For Windows (XAMPP)
attrib +R database\easymed.sqlite
icacls assets\uploads /grant Users:F

# For Linux/Mac
chmod 644 database/easymed.sqlite
chmod 755 assets/uploads
```

## 🛠️ Troubleshooting

### Common Issues

1. **"PDO SQLite driver not found"**:
   - Enable `pdo_sqlite` extension in `php.ini`
   - Restart Apache

2. **"Permission denied" errors**:
   - Check file permissions on uploads directory
   - Ensure database file is writable

3. **"Site URL not found"**:
   - Verify XAMPP Apache is running
   - Check `SITE_URL` in `includes/config.php`

4. **Images not displaying**:
   - Check if GD extension is enabled
   - Verify uploads directory permissions

### Debug Mode

Enable debug mode in `includes/config.php`:

```php
define('DEBUG_MODE', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📞 Support

If you encounter issues during setup:

1. Check the troubleshooting section above
2. Verify all prerequisites are met
3. Ensure file permissions are correct
4. Check PHP error logs in XAMPP

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

---

**Built with ❤️ for better healthcare management**