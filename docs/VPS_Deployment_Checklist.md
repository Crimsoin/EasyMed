# 🚀 EasyMed VPS Deployment Checklist

Quick reference for deploying EasyMed to production VPS.

## Pre-Deployment ✅

- [ ] VPS with Ubuntu 20.04/22.04 LTS
- [ ] Domain name pointed to VPS IP
- [ ] SSH access configured
- [ ] GitHub repository ready

## Server Setup ⚙️

```bash
# System update
sudo apt update && sudo apt upgrade -y

# Install essentials
sudo apt install -y nginx php8.1-fpm php8.1-sqlite3 php8.1-curl php8.1-mbstring sqlite3 git
```

## Project Deployment 📁

```bash
# Clone project
cd /var/www
sudo git clone https://github.com/Crimsoin/EasyMed.git
sudo chown -R www-data:www-data EasyMed
sudo chmod -R 755 EasyMed
sudo chmod -R 775 EasyMed/database EasyMed/assets/uploads
```

## Nginx Configuration 🌐

Create `/etc/nginx/sites-available/easymed`:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/EasyMed;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/easymed /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl restart nginx
```

## SSL Certificate 🔒

```bash
# Install Let's Encrypt
sudo apt install -y certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d your-domain.com

# Test renewal
sudo certbot renew --dry-run
```

## Configuration Update 📝

Edit `/var/www/EasyMed/includes/config.php`:

```php
define('SITE_URL', 'https://your-domain.com');
define('ENCRYPTION_KEY', 'your-new-secret-key');
// Update SMTP settings for production
```

## Security & Firewall 🛡️

```bash
# Setup firewall
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
```

## Backup Setup 💾

```bash
# Create backup script
sudo nano /usr/local/bin/easymed-backup.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/easymed-backup.sh
```

## Testing 🧪

- [ ] Website loads: `https://your-domain.com`
- [ ] Admin login works: `https://your-domain.com/admin`
- [ ] Database operations work
- [ ] File uploads work
- [ ] Email notifications work

## Monitoring 📊

```bash
# Check services
sudo systemctl status nginx php8.1-fpm

# Check logs
sudo tail -f /var/log/nginx/error.log

# Monitor resources
htop
df -h
```

## Deployment Script 🔄

```bash
#!/bin/bash
# /usr/local/bin/easymed-deploy.sh
cd /var/www/EasyMed
cp database/easymed.sqlite database/easymed.sqlite.backup
git pull origin main
chown -R www-data:www-data /var/www/EasyMed
systemctl restart php8.1-fpm nginx
echo "Deployed!"
```

## Quick Commands 🔧

```bash
# Deploy updates
sudo /usr/local/bin/easymed-deploy.sh

# Check status
sudo systemctl status nginx php8.1-fpm

# View logs
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/php8.1-fpm.log

# Manual backup
cp /var/www/EasyMed/database/easymed.sqlite ~/easymed-backup-$(date +%Y%m%d).sqlite
```

## Troubleshooting 🆘

| Issue | Solution |
|-------|----------|
| 502 Bad Gateway | `sudo systemctl restart php8.1-fpm` |
| Permission denied | `sudo chown -R www-data:www-data /var/www/EasyMed` |
| Database locked | Check file permissions on `database/easymed.sqlite` |
| SSL issues | `sudo certbot renew` |

---

**Production URLs:**
- Main site: `https://your-domain.com`
- Admin: `https://your-domain.com/admin`
- ERD: `https://your-domain.com/docs/EasyMed_ERD.html`