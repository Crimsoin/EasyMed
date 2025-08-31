@echo off
echo Starting MySQL with stable configuration...
cd /d C:\xampp\mysql\bin
mysqld --defaults-file=C:\xampp\mysql\bin\my.ini --standalone --console
