# 🚀 How to Run EasyMed Clinic Management System

Complete guide to get your EasyMed system up and running locally.

---

## 📋 Quick Overview

**Project Type:** PHP Web Application  
**Database:** SQLite (no server needed)  
**Web Server:** Apache (via XAMPP)  
**Framework:** Vanilla PHP with custom architecture  
**Current Location:** `C:\xampp\htdocs\Project_EasyMed`

---

## ✅ Prerequisites Checklist

Before running the project, ensure you have:

- [ ] **XAMPP** installed (includes Apache + PHP + MySQL)
- [ ] **PHP 7.4+** (PHP 8.1 recommended)
- [ ] **Web Browser** (Chrome, Firefox, Edge, Safari)
- [ ] **Git** (for version control, optional)

---

## 🛠️ Step-by-Step Setup

### Step 1: Verify XAMPP Installation

1. **Check if XAMPP is installed:**
   ```powershell
   # Check if XAMPP directory exists
   Test-Path "C:\xampp"
   ```

2. **If not installed, download from:**
   - https://www.apachefriends.org/download.html
   - Choose Windows version with PHP 8.1

3. **Install XAMPP:**
   - Run installer as Administrator
   - Install to default location: `C:\xampp`
   - Select: Apache, PHP, MySQL (optional), phpMyAdmin (optional)

---

### Step 2: Start XAMPP Services

1. **Open XAMPP Control Panel:**
   ```powershell
   # Run XAMPP Control Panel
   Start-Process "C:\xampp\xampp-control.exe"
   ```

2. **Start Apache:**
   - Click "Start" button next to Apache
   - Apache should turn green
   - Port 80 should be active

3. **Verify Apache is running:**
   - Open browser: http://localhost
   - You should see XAMPP dashboard

**Troubleshooting:**
- **Port 80 in use?** 
  - Skype or other apps may use port 80
  - Stop those apps or change Apache port in `C:\xampp\apache\conf\httpd.conf`
  - Change `Listen 80` to `Listen 8080`
  - Then access via http://localhost:8080

---

### Step 3: Verify PHP Installation

1. **Check PHP version:**
   ```powershell
   # Check PHP is working
   C:\xampp\php\php.exe -v
   ```

2. **Check required PHP extensions:**
   ```powershell
   # List all PHP modules
   C:\xampp\php\php.exe -m | Select-String "pdo|sqlite|gd|json|mbstring|curl"
   ```

   **Required extensions:**
   - ✅ PDO
   - ✅ pdo_sqlite
   - ✅ sqlite3
   - ✅ gd
   - ✅ json
   - ✅ mbstring
   - ✅ curl

3. **If extensions are missing, enable them:**
   - Edit: `C:\xampp\php\php.ini`
   - Find and uncomment (remove `;`):
     ```ini
     extension=gd
     extension=pdo_sqlite
     extension=sqlite3
     extension=mbstring
     extension=curl
     ```
   - Restart Apache

---

### Step 4: Verify Project Location

1. **Check project is in correct directory:**
   ```powershell
   # Verify project exists
   Test-Path "C:\xampp\htdocs\Project_EasyMed\index.php"
   ```

2. **If project is elsewhere, move it:**
   ```powershell
   # Example: Move from Downloads
   Move-Item "C:\Users\YourName\Downloads\Project_EasyMed" "C:\xampp\htdocs\"
   ```

3. **Navigate to project:**
   ```powershell
   cd C:\xampp\htdocs\Project_EasyMed
   ```

---

### Step 5: Configure Database

1. **Check database file exists:**
   ```powershell
   # Verify database exists
   Test-Path "C:\xampp\htdocs\Project_EasyMed\database\easymed.sqlite"
   ```

2. **Database is included** - No setup needed! The SQLite database comes pre-configured.

3. **If database is missing or corrupted:**
   - You'll need to create users manually
   - See "Creating Admin User" section below

---

### Step 6: Check Configuration File

1. **Open configuration:**
   ```powershell
   notepad "C:\xampp\htdocs\Project_EasyMed\includes\config.php"
   ```

2. **Verify these settings:**
   ```php
   // Should match your setup
   define('DB_TYPE', 'sqlite');
   define('SQLITE_PATH', __DIR__ . '/../database/easymed.sqlite');
   define('SITE_URL', 'http://localhost/Project_EasyMed');
   ```

3. **If using different port (8080):**
   ```php
   define('SITE_URL', 'http://localhost:8080/Project_EasyMed');
   ```

---

### Step 7: Set File Permissions

1. **Ensure database directory is writable:**
   ```powershell
   # Check current permissions
   Get-Acl "C:\xampp\htdocs\Project_EasyMed\database"
   
   # Make writable (if needed)
   icacls "C:\xampp\htdocs\Project_EasyMed\database" /grant Users:F
   ```

2. **Ensure uploads directory is writable:**
   ```powershell
   # Create uploads directory if missing
   New-Item -ItemType Directory -Force -Path "C:\xampp\htdocs\Project_EasyMed\assets\uploads"
   
   # Make writable
   icacls "C:\xampp\htdocs\Project_EasyMed\assets\uploads" /grant Users:F
   ```

---

### Step 8: Access the Application

1. **Open your web browser**

2. **Navigate to the application:**
   ```
   http://localhost/Project_EasyMed
   ```
   
   Or if using port 8080:
   ```
   http://localhost:8080/Project_EasyMed
   ```

3. **You should see:**
   - EasyMed home page with hero section
   - Navigation menu
   - Login/Register buttons

---

## 👤 Default User Accounts

The system may have pre-configured users. Try these credentials:

### Admin Account
```
URL: http://localhost/Project_EasyMed/admin
Email: admin@easymed.com
Password: admin123
```

### Doctor Account (if available)
```
URL: http://localhost/Project_EasyMed/doctor
Email: doctor@easymed.com
Password: doctor123
```

### Patient Account (if available)
```
Email: patient@easymed.com
Password: patient123
```

**⚠️ Important:** These are example credentials. Check your database for actual users.

---

## 🔧 Creating Admin User (If Needed)

If no users exist in the database:

### Option 1: Using SQLite Browser (Recommended)

1. **Download DB Browser for SQLite:**
   - https://sqlitebrowser.org/dl/

2. **Open database:**
   - Launch DB Browser
   - Open: `C:\xampp\htdocs\Project_EasyMed\database\easymed.sqlite`

3. **Add admin user:**
   ```sql
   -- First, insert into users table
   INSERT INTO users (username, email, password, role, first_name, last_name, is_active) 
   VALUES (
       'admin',
       'admin@easymed.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: password
       'admin',
       'Admin',
       'User',
       1
   );
   ```

4. **Write changes and close**

### Option 2: Using PowerShell Script

```powershell
# Create a quick PHP script to add admin
$phpCode = @"
<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

`$db = Database::getInstance();

// Hash password
`$password = password_hash('admin123', PASSWORD_DEFAULT);

// Insert admin user
`$userId = `$db->insertData('users', [
    'username' => 'admin',
    'email' => 'admin@easymed.com',
    'password' => `$password,
    'role' => 'admin',
    'first_name' => 'System',
    'last_name' => 'Administrator',
    'is_active' => 1,
    'created_at' => date('Y-m-d H:i:s')
]);

echo "Admin user created! ID: " . `$userId . "\n";
echo "Email: admin@easymed.com\n";
echo "Password: admin123\n";
?>
"@

# Save and run
$phpCode | Out-File -FilePath "C:\xampp\htdocs\Project_EasyMed\create_admin.php" -Encoding UTF8
C:\xampp\php\php.exe "C:\xampp\htdocs\Project_EasyMed\create_admin.php"
```

---

## 🎯 Testing the Application

### 1. Homepage Test
- ✅ Navigate to: http://localhost/Project_EasyMed
- ✅ Page loads without errors
- ✅ Navigation menu visible
- ✅ Hero section displays

### 2. Login Test
- ✅ Click "Login" button
- ✅ Modal appears
- ✅ Enter credentials
- ✅ Login successful

### 3. Admin Dashboard Test
- ✅ Login as admin
- ✅ Access: http://localhost/Project_EasyMed/admin
- ✅ Dashboard shows statistics
- ✅ Navigation works

### 4. Patient Registration Test
- ✅ Click "Register as Patient"
- ✅ Fill out form
- ✅ Submit registration
- ✅ Account created

### 5. Doctor Portal Test (if doctors exist)
- ✅ Login as doctor
- ✅ Access: http://localhost/Project_EasyMed/doctor
- ✅ View appointments
- ✅ Manage schedule

---

## 📁 Project Structure

```
Project_EasyMed/
├── admin/              # Admin dashboard and management
│   ├── Dashboard/      # Admin overview
│   ├── Doctor Management/
│   ├── Patient Management/
│   ├── Appointment/
│   └── Settings/
├── doctor/             # Doctor portal
│   ├── dashboard_doctor.php
│   ├── appointments.php
│   ├── patients.php
│   └── schedule.php
├── patient/            # Patient portal
│   ├── dashboard_patients.php
│   ├── book-appointment.php
│   └── appointments.php
├── assets/             # Static files
│   ├── css/           # Stylesheets
│   ├── js/            # JavaScript files
│   ├── images/        # Images
│   └── uploads/       # User uploads
├── database/           # SQLite database
│   └── easymed.sqlite
├── includes/           # Core files
│   ├── config.php     # Configuration
│   ├── database.php   # Database class
│   ├── functions.php  # Helper functions
│   ├── header.php     # Header template
│   └── footer.php     # Footer template
├── docs/              # Documentation
│   ├── ERD files
│   └── Deployment guides
└── index.php          # Homepage
```

---

## 🌐 Main URLs

Once running, you can access:

| Page | URL | Description |
|------|-----|-------------|
| **Homepage** | http://localhost/Project_EasyMed | Public landing page |
| **About** | http://localhost/Project_EasyMed/about.php | About clinic |
| **Doctors** | http://localhost/Project_EasyMed/doctors.php | Doctor listings |
| **Location** | http://localhost/Project_EasyMed/location.php | Clinic location |
| **Admin Panel** | http://localhost/Project_EasyMed/admin | Admin dashboard |
| **Doctor Portal** | http://localhost/Project_EasyMed/doctor | Doctor dashboard |
| **Patient Portal** | http://localhost/Project_EasyMed/patient | Patient dashboard |
| **ERD Documentation** | http://localhost/Project_EasyMed/docs/EasyMed_ERD.html | Database schema |

---

## 🔍 Troubleshooting

### Problem: Blank page or errors

**Solution:**
1. Check Apache error log:
   ```powershell
   Get-Content "C:\xampp\apache\logs\error.log" -Tail 20
   ```

2. Check PHP error log:
   ```powershell
   Get-Content "C:\xampp\php\logs\php_error_log.txt" -Tail 20
   ```

3. Enable error display in `config.php`:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

### Problem: Database connection failed

**Solution:**
1. Verify database file exists and is readable
2. Check file permissions
3. Verify PDO SQLite extension is enabled

### Problem: Images not displaying

**Solution:**
1. Check if GD extension is enabled:
   ```powershell
   C:\xampp\php\php.exe -m | Select-String "gd"
   ```

2. Check uploads directory permissions

### Problem: Port 80 already in use

**Solutions:**

**Option 1 - Change Apache Port:**
1. Edit: `C:\xampp\apache\conf\httpd.conf`
2. Change: `Listen 80` to `Listen 8080`
3. Change: `ServerName localhost:80` to `ServerName localhost:8080`
4. Restart Apache
5. Access: http://localhost:8080/Project_EasyMed

**Option 2 - Stop Conflicting Service:**
```powershell
# Find what's using port 80
netstat -ano | findstr :80

# Stop the service (example: World Wide Web Publishing Service)
Stop-Service -Name W3SVC
```

### Problem: Permission denied errors

**Solution:**
```powershell
# Grant full permissions to project directory
icacls "C:\xampp\htdocs\Project_EasyMed" /grant Users:F /T

# Specifically for database and uploads
icacls "C:\xampp\htdocs\Project_EasyMed\database" /grant Users:F
icacls "C:\xampp\htdocs\Project_EasyMed\assets\uploads" /grant Users:F
```

### Problem: Session errors

**Solution:**
1. Check if session directory is writable:
   ```powershell
   Test-Path "C:\xampp\tmp"
   ```

2. Create if missing:
   ```powershell
   New-Item -ItemType Directory -Force -Path "C:\xampp\tmp"
   ```

---

## 📊 Checking Database Contents

### Using DB Browser for SQLite:

1. Download: https://sqlitebrowser.org/
2. Open: `C:\xampp\htdocs\Project_EasyMed\database\easymed.sqlite`
3. Browse tables: users, patients, doctors, appointments

### Using PowerShell:

```powershell
# Simple check
cd C:\xampp\htdocs\Project_EasyMed

# Create quick check script
@"
<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

`$db = Database::getInstance();
`$users = `$db->fetchAll('SELECT id, username, email, role FROM users');
echo 'Users in database: ' . count(`$users) . "\n\n";
foreach (`$users as `$user) {
    echo "ID: {`$user['id']} | Email: {`$user['email']} | Role: {`$user['role']}\n";
}
?>
"@ | Out-File check_db.php -Encoding UTF8

C:\xampp\php\php.exe check_db.php
```

---

## 🚀 Development Workflow

### Daily Workflow:

1. **Start XAMPP:**
   ```powershell
   Start-Process "C:\xampp\xampp-control.exe"
   ```

2. **Start Apache** (via XAMPP Control Panel)

3. **Open project in browser:**
   ```
   http://localhost/Project_EasyMed
   ```

4. **Make changes to code**

5. **Refresh browser to see changes**

6. **Stop Apache when done** (via XAMPP Control Panel)

### Git Workflow:

```powershell
# Check status
git status

# Add changes
git add .

# Commit
git commit -m "Description of changes"

# Push to GitHub
git push origin main
```

---

## 📝 Quick Commands Reference

```powershell
# Start XAMPP Control Panel
Start-Process "C:\xampp\xampp-control.exe"

# Check PHP version
C:\xampp\php\php.exe -v

# Check PHP modules
C:\xampp\php\php.exe -m

# View Apache error log
Get-Content "C:\xampp\apache\logs\error.log" -Tail 50

# View PHP error log
Get-Content "C:\xampp\php\logs\php_error_log.txt" -Tail 50

# Test if port 80 is available
Test-NetConnection localhost -Port 80

# Navigate to project
cd C:\xampp\htdocs\Project_EasyMed

# Open project in browser
Start-Process "http://localhost/Project_EasyMed"

# Open project in VS Code
code .
```

---

## 🎓 Next Steps

Once your application is running:

1. **✅ Login as admin** and explore the dashboard
2. **✅ Add doctors** through Admin → Doctor Management
3. **✅ Add patients** through Admin → Patient Management or patient registration
4. **✅ Configure clinic settings** in Admin → Settings
5. **✅ Test appointment booking** as a patient
6. **✅ Test doctor portal** to manage appointments
7. **✅ Review the ERD** at `/docs/EasyMed_ERD.html`

---

## 💡 Tips

- **Auto-refresh pages:** Use browser extension like "Live Server" or "Auto Refresh"
- **Debug mode:** Enable in `config.php` for detailed errors
- **Database backups:** Regularly copy `database/easymed.sqlite`
- **Test on different browsers:** Chrome, Firefox, Edge for compatibility
- **Use browser DevTools:** F12 to inspect errors and network requests

---

## 📞 Getting Help

If you encounter issues:

1. ✅ Check this guide thoroughly
2. ✅ Review error logs
3. ✅ Check the README.md
4. ✅ Review the ERD documentation
5. ✅ Check GitHub issues

---

## ✅ Success Checklist

- [ ] XAMPP installed and Apache running
- [ ] PHP 7.4+ with required extensions
- [ ] Project in `C:\xampp\htdocs\Project_EasyMed`
- [ ] Database file exists and is readable
- [ ] Can access http://localhost/Project_EasyMed
- [ ] Login modal appears and works
- [ ] Can login as admin/doctor/patient
- [ ] No PHP errors on pages
- [ ] Images and CSS load properly

---

**🎉 Congratulations! Your EasyMed system should now be running!**

For deployment to production VPS, see: `docs/VPS_Deployment_Guide.md`