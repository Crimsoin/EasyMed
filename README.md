# EasyMed Clinic Management System

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
   define('SITE_URL', 'http://0.0.0.0/EasyMed');
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
2. Navigate to: `http://0.0.0.0/EasyMed`
3. The application should load successfully

## 🔧 Configuration

### Environment Setup

1. **Development Environment**:
   ```
   SITE_URL = http://0.0.0.0/EasyMed
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