# 🗄️ Database Management Guide

This guide provides multiple methods to manually clear data from your EasyMed SQLite database.

## 📍 Database Location
- **File:** `database/easymed.sqlite`
- **Full Path:** `c:\xampp\htdocs\Project_EasyMed\database\easymed.sqlite`

## 🛠️ Available Methods

### Method 1: Web Interface (Recommended) ⭐
Use the built-in database management tools:

1. **Full Database Manager:** [database_manager.php](../database_manager.php)
   - View table statistics
   - Clear individual tables
   - Clear all data with confirmation
   - View table contents

2. **Quick Clear Tool:** [quick_clear.php](../quick_clear.php)
   - Predefined clearing options
   - Selective data removal
   - Preserves important data where needed

### Method 2: Delete Database File 🗑️
Complete database reset:

```powershell
# Navigate to project directory
cd "c:\xampp\htdocs\Project_EasyMed"

# Delete the database file
Remove-Item "database\easymed.sqlite"

# Recreate the database
# Visit: http://localhost:8080/setup_sqlite_database.php
```

### Method 3: SQLite Command Line 💻
For advanced users with SQLite CLI installed:

```bash
# Open database
sqlite3 "database/easymed.sqlite"

# Clear specific table
DELETE FROM appointments;
DELETE FROM patients;
DELETE FROM users WHERE role = 'patient';

# View table structure
.schema table_name

# Exit
.quit
```

### Method 4: PowerShell Script 🔧
Create a batch clearing script:

```powershell
# Create clear_database.ps1
$dbPath = "database\easymed.sqlite"

if (Test-Path $dbPath) {
    Remove-Item $dbPath
    Write-Host "Database cleared successfully"
    Write-Host "Run setup_sqlite_database.php to recreate"
} else {
    Write-Host "Database file not found"
}
```

## 📊 Database Tables Overview

| Table | Purpose | Dependencies |
|-------|---------|--------------|
| `users` | User accounts | Parent table |
| `patients` | Patient profiles | → users |
| `doctors` | Doctor profiles | → users |
| `appointments` | Appointments | → patients, doctors |
| `doctor_schedules` | Doctor schedules | → doctors |
| `doctor_breaks` | Doctor breaks | → doctors |
| `doctor_unavailable` | Doctor unavailable times | → doctors |

## ⚠️ Important Clearing Order

When manually clearing tables, follow this order to avoid foreign key conflicts:

1. `appointments` (child table)
2. `patients` (depends on users)
3. `doctor_breaks` (depends on doctors)
4. `doctor_unavailable` (depends on doctors)
5. `doctor_schedules` (depends on doctors)
6. `doctors` (depends on users)
7. `users` (parent table)

## 🚨 Safety Guidelines

### Before Clearing:
- ✅ **Backup first** if you have important data
- ✅ **Stop any running processes** accessing the database
- ✅ **Confirm you want to delete** the specified data
- ✅ **Test on development** before production

### Data Recovery:
- ❌ **No built-in undo** - data is permanently deleted
- ❌ **No automatic backups** - create your own
- ✅ **Can recreate structure** using setup scripts
- ✅ **Sample data available** via test scripts

## 🎯 Common Scenarios

### Testing & Development
```
Recommendation: Use quick_clear.php
Options: Clear appointments only or clear all test data
```

### Fresh Start
```
Recommendation: Delete database file + recreate
Steps: Remove-Item → setup_sqlite_database.php
```

### Production Maintenance
```
Recommendation: Use database_manager.php
Features: Selective clearing with confirmations
```

### Selective Clearing
```
Recommendation: Web interface with table view
Process: View data → Select tables → Clear with confirmation
```

## 🔧 Troubleshooting

### Foreign Key Errors
```sql
-- Disable foreign keys temporarily
PRAGMA foreign_keys = OFF;
DELETE FROM table_name;
PRAGMA foreign_keys = ON;
```

### Database Locked
- Stop PHP development server
- Close any SQLite browser connections
- Wait 30 seconds and retry

### Permission Issues
- Run PowerShell as Administrator
- Check file permissions on database folder
- Ensure antivirus isn't blocking file access

## 📝 Quick Reference Commands

| Action | Command/URL |
|--------|-------------|
| Web Manager | `http://localhost:8080/database_manager.php` |
| Quick Clear | `http://localhost:8080/quick_clear.php` |
| Delete File | `Remove-Item "database\easymed.sqlite"` |
| Recreate DB | `http://localhost:8080/setup_sqlite_database.php` |
| View Tables | SQLite browser or web interface |

## 🔗 Related Files

- `database_manager.php` - Full database management interface
- `quick_clear.php` - Quick clearing options
- `setup_sqlite_database.php` - Database creation script
- `includes/database.php` - Database connection class
- `includes/config.php` - Database configuration

---
*Last updated: After project cleanup - all database management tools ready for use*
