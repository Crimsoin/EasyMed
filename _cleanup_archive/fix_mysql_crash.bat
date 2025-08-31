@echo off
echo === XAMPP MySQL Fix Script ===
echo.

echo Step 1: Stopping any running MySQL processes...
taskkill /f /im mysqld.exe 2>nul
timeout /t 2 /nobreak >nul

echo Step 2: Backing up current data directory...
if exist "C:\xampp\mysql\data_backup" (
    echo Previous backup found, skipping...
) else (
    xcopy "C:\xampp\mysql\data" "C:\xampp\mysql\data_backup\" /E /I /Q
    echo Data backed up successfully.
)

echo Step 3: Resetting MySQL data directory...
if exist "C:\xampp\mysql\data_original" (
    echo Restoring from original data...
    rmdir /s /q "C:\xampp\mysql\data"
    xcopy "C:\xampp\mysql\data_original" "C:\xampp\mysql\data\" /E /I /Q
) else (
    echo Creating fresh data directory...
    rmdir /s /q "C:\xampp\mysql\data"
    mkdir "C:\xampp\mysql\data"
    mkdir "C:\xampp\mysql\data\mysql"
    mkdir "C:\xampp\mysql\data\performance_schema"
    mkdir "C:\xampp\mysql\data\phpmyadmin"
)

echo Step 4: Starting MySQL with recovery mode...
echo Please wait while MySQL initializes...
cd /d "C:\xampp\mysql\bin"
mysql_install_db.exe --datadir="C:\xampp\mysql\data" --default-table-type=myisam

echo.
echo === Fix completed! ===
echo Now try starting MySQL from XAMPP Control Panel.
pause
