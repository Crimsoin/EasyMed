# EasyMed Deployment Checklist

Before deploying EasyMed to production, complete this checklist:

## ✅ Pre-Deployment Checklist

### 1. Configuration Files
- [ ] Copy `includes/config.example.php` to `includes/config.php`
- [ ] Update `SITE_URL` to your production domain
- [ ] Update `BASE_URL` to your production domain
- [ ] Change `SMTP_USERNAME` to your email
- [ ] Change `SMTP_PASSWORD` to your app password
- [ ] Change `SMTP_FROM_EMAIL` to your domain email
- [ ] Generate secure `ENCRYPTION_KEY` (use: `openssl rand -base64 32`)
- [ ] Set `ENVIRONMENT` to `'production'`
- [ ] Update timezone in `date_default_timezone_set()`

### 2. Security
- [ ] Review and update `.htaccess` rules
- [ ] Ensure database file permissions are correct (644 for .sqlite, 755 for database folder)
- [ ] Protect sensitive directories (database, includes, logs)
- [ ] Remove any test/debug files
- [ ] Enable HTTPS and force SSL in `.htaccess`
- [ ] Set secure session cookies
- [ ] Review file upload permissions

### 3. Database
- [ ] Backup current database
- [ ] Create fresh production database or migrate existing
- [ ] Set up database backup automation
- [ ] Verify all tables and schema are correct
- [ ] Create admin account

### 4. File Permissions
```bash
# Recommended permissions:
# Directories: 755
find . -type d -exec chmod 755 {} \;

# PHP files: 644
find . -type f -name "*.php" -exec chmod 644 {} \;

# Database: 644
chmod 644 database/easymed.sqlite
chmod 755 database/

# Uploads: 755 (directory), 644 (files)
chmod 755 assets/uploads/
chmod 755 assets/uploads/payment_receipts/

# Logs: 755
chmod 755 logs/
```

### 5. Environment Variables (Optional but Recommended)
Consider moving sensitive data to environment variables:
- Database credentials
- SMTP credentials
- Encryption keys
- API keys

### 6. Testing
- [ ] Test user registration
- [ ] Test user login (all roles: admin, doctor, patient)
- [ ] Test appointment booking
- [ ] Test file uploads
- [ ] Test email notifications
- [ ] Test payment receipt uploads
- [ ] Test doctor profile management
- [ ] Test admin panel functionality
- [ ] Test on different browsers
- [ ] Test on mobile devices

### 7. Performance
- [ ] Enable OPcache in PHP
- [ ] Enable Gzip compression
- [ ] Set up browser caching
- [ ] Optimize images
- [ ] Minify CSS/JS (optional)

### 8. Monitoring & Maintenance
- [ ] Set up error logging
- [ ] Set up uptime monitoring
- [ ] Set up automated backups
- [ ] Configure email alerts for critical errors
- [ ] Set up analytics (optional)

### 9. Documentation
- [ ] Update README.md with production info
- [ ] Document deployment process
- [ ] Document backup procedures
- [ ] Document troubleshooting steps

### 10. DNS & SSL
- [ ] Point domain to hosting server
- [ ] Install SSL certificate
- [ ] Configure DNS records
- [ ] Test HTTPS access
- [ ] Force HTTPS in .htaccess

## 🚫 Files to Exclude from Deployment

Do NOT upload these files/folders to production:
- `.git/` (version control)
- `.vscode/` (IDE settings)
- `*.md` files (documentation - optional)
- `test*.php` files
- `debug*.php` files
- `composer.lock` (if using Composer)
- `node_modules/` (if using npm)

## 🔐 Security Reminders

### IMPORTANT: Change These in Production
1. **ENCRYPTION_KEY** - Generate new random key
2. **SMTP_PASSWORD** - Use app-specific password
3. **Database credentials** - If using MySQL
4. **Session settings** - Enable secure cookies

### Files That MUST Be Protected
- `includes/config.php` - Contains sensitive credentials
- `database/easymed.sqlite` - Contains all data
- `logs/` - May contain sensitive info

## 📋 Post-Deployment

After deployment:
1. [ ] Test all functionality
2. [ ] Create first admin account
3. [ ] Add initial doctors (if any)
4. [ ] Configure email templates
5. [ ] Test email delivery
6. [ ] Set up monitoring
7. [ ] Schedule first backup
8. [ ] Review error logs
9. [ ] Test performance

## 🆘 Emergency Rollback

If something goes wrong:
1. Restore database from backup
2. Restore previous code version
3. Check error logs for details
4. Review configuration changes

## 📞 Support Resources

- DEPLOYMENT_GUIDE.md - Full deployment instructions
- QUICK_START.md - Setup guide
- README.md - Project overview
- PHP error logs: `logs/php_errors.log`
- Apache error logs: Check hosting control panel

---

## Quick Commands Reference

### Generate Encryption Key
```bash
openssl rand -base64 32
```

### Check PHP Version
```bash
php -v
```

### Check SQLite Support
```bash
php -m | grep sqlite
```

### Test Database Connection
```bash
php -r "echo (new PDO('sqlite:database/easymed.sqlite')) ? 'Connected' : 'Failed';"
```

### Set Permissions (Unix/Linux)
```bash
chmod 755 database/
chmod 644 database/easymed.sqlite
chmod 755 assets/uploads/
chmod 755 logs/
```

---

**Remember:** Always test in a staging environment before deploying to production!
