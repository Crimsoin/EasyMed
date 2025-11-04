# ⚡ EasyMed Quick Start Guide
asd
## 🚀 Super Fast Setup (5 Minutes)

### Step 1: Check XAMPP ✅
```powershell
# Open XAMPP Control Panel
Start-Process "C:\xampp\xampp-control.exe"
```
- Click **Start** next to Apache
- Apache button turns green ✅

### Step 2: Open Browser 🌐
```
http://localhost/Project_EasyMed
```

### Step 3: Login 👤
```
Admin Panel: http://localhost/Project_EasyMed/admin
Email: admin@easymed.com
Password: admin123
```

---

## 🔧 If Something's Wrong

### Problem: XAMPP not installed?
👉 Download: https://www.apachefriends.org/download.html  
👉 Install to: `C:\xampp`  
👉 Choose: Apache + PHP

### Problem: Apache won't start (Port 80 busy)?
```powershell
# Check what's using port 80
netstat -ano | findstr :80

# Use port 8080 instead:
# 1. Edit C:\xampp\apache\conf\httpd.conf
# 2. Change "Listen 80" to "Listen 8080"
# 3. Restart Apache
# 4. Access: http://localhost:8080/Project_EasyMed
```

### Problem: Project not in correct folder?
```powershell
# Move project to XAMPP
Move-Item "YourCurrentPath\Project_EasyMed" "C:\xampp\htdocs\"
```

### Problem: Blank page or errors?
```powershell
# Check Apache is green in XAMPP Control Panel
# View errors:
Get-Content "C:\xampp\apache\logs\error.log" -Tail 20

# Enable PHP errors - add to config.php:
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Problem: No admin user exists?
```powershell
# Quick create admin script
cd C:\xampp\htdocs\Project_EasyMed

# Create file
@"
<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
`$db = Database::getInstance();
`$id = `$db->insertData('users', [
    'username' => 'admin',
    'email' => 'admin@easymed.com',
    'password' => password_hash('admin123', PASSWORD_DEFAULT),
    'role' => 'admin',
    'first_name' => 'Admin',
    'last_name' => 'User',
    'is_active' => 1
]);
echo "Admin created! ID: `$id\n";
?>
"@ | Out-File create_admin.php

# Run it
C:\xampp\php\php.exe create_admin.php
```

---

## 📋 Daily Usage

```powershell
# 1. Start XAMPP
Start-Process "C:\xampp\xampp-control.exe"

# 2. Start Apache (click button)

# 3. Open browser
Start-Process "http://localhost/Project_EasyMed"

# 4. When done, stop Apache (click button)
```

---

## 🎯 Key URLs

| What | Where |
|------|-------|
| Homepage | http://localhost/Project_EasyMed |
| Admin | http://localhost/Project_EasyMed/admin |
| Doctor Portal | http://localhost/Project_EasyMed/doctor |
| Patient Portal | http://localhost/Project_EasyMed/patient |
| ERD Docs | http://localhost/Project_EasyMed/docs/EasyMed_ERD.html |

---

## 👥 Default Logins

**Admin:**
- Email: `admin@easymed.com`
- Password: `admin123`

**Check if users exist:**
```powershell
cd C:\xampp\htdocs\Project_EasyMed
C:\xampp\php\php.exe -r "require 'includes/config.php'; require 'includes/database.php'; `$db = Database::getInstance(); `$users = `$db->fetchAll('SELECT email, role FROM users'); print_r(`$users);"
```

---

## ✅ Success Checklist

- [ ] XAMPP installed
- [ ] Apache started (green in XAMPP)
- [ ] Project at: `C:\xampp\htdocs\Project_EasyMed`
- [ ] Can open: http://localhost/Project_EasyMed
- [ ] Homepage loads
- [ ] Can login to admin panel

---

## 🆘 Emergency Commands

```powershell
# View recent errors
Get-Content "C:\xampp\apache\logs\error.log" -Tail 20

# Check PHP works
C:\xampp\php\php.exe -v

# Check port 80 available
Test-NetConnection localhost -Port 80

# Fix permissions
icacls "C:\xampp\htdocs\Project_EasyMed\database" /grant Users:F
icacls "C:\xampp\htdocs\Project_EasyMed\assets\uploads" /grant Users:F

# Restart Apache (via XAMPP Control Panel)
# Click Stop, wait, then Start
```

---

## 📖 Full Documentation

👉 Read: `docs/HOW_TO_RUN.md` for complete guide  
👉 Read: `README.md` for project overview  
👉 View: `docs/EasyMed_ERD.html` for database structure

---

**Need help? Check the full guide at `docs/HOW_TO_RUN.md`**