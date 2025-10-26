#!/bin/bash

# EasyMed VPS Deployment Script
# Usage: bash deploy-easymed.sh your-domain.com

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOMAIN=${1:-"example.com"}
PROJECT_DIR="/var/www/EasyMed"
NGINX_SITE="/etc/nginx/sites-available/easymed"
PHP_VERSION="8.1"

# Functions
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

install_dependencies() {
    print_status "Updating system packages..."
    apt update && apt upgrade -y
    
    print_status "Installing required packages..."
    apt install -y nginx php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-curl php${PHP_VERSION}-json php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml php${PHP_VERSION}-zip php${PHP_VERSION}-gd \
        sqlite3 git unzip curl wget certbot python3-certbot-nginx ufw
    
    print_success "Dependencies installed"
}

clone_project() {
    print_status "Cloning EasyMed project..."
    
    if [ -d "$PROJECT_DIR" ]; then
        print_warning "Project directory exists. Backing up..."
        mv "$PROJECT_DIR" "${PROJECT_DIR}.backup.$(date +%Y%m%d_%H%M%S)"
    fi
    
    cd /var/www
    git clone https://github.com/Crimsoin/EasyMed.git
    
    print_success "Project cloned"
}

set_permissions() {
    print_status "Setting file permissions..."
    
    chown -R www-data:www-data "$PROJECT_DIR"
    chmod -R 755 "$PROJECT_DIR"
    chmod -R 775 "$PROJECT_DIR/database"
    chmod -R 775 "$PROJECT_DIR/assets/uploads"
    
    print_success "Permissions set"
}

configure_nginx() {
    print_status "Configuring Nginx..."
    
    cat > "$NGINX_SITE" << EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root $PROJECT_DIR;
    index index.php index.html;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";

    # Main location block
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~* \.(log|sql|sqlite|bak)\$ {
        deny all;
    }

    # Static files caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
}
EOF

    # Enable site
    ln -sf "$NGINX_SITE" /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
    
    # Test configuration
    nginx -t
    
    print_success "Nginx configured"
}

setup_ssl() {
    print_status "Setting up SSL certificate..."
    
    # Get SSL certificate
    certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email
    
    # Test automatic renewal
    certbot renew --dry-run
    
    print_success "SSL configured"
}

configure_firewall() {
    print_status "Configuring firewall..."
    
    ufw --force enable
    ufw allow ssh
    ufw allow 'Nginx Full'
    
    print_success "Firewall configured"
}

start_services() {
    print_status "Starting services..."
    
    systemctl start nginx
    systemctl enable nginx
    systemctl start php${PHP_VERSION}-fpm
    systemctl enable php${PHP_VERSION}-fpm
    
    systemctl restart nginx
    systemctl restart php${PHP_VERSION}-fpm
    
    print_success "Services started"
}

create_backup_script() {
    print_status "Creating backup script..."
    
    cat > /usr/local/bin/easymed-backup.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/easymed"
PROJECT_DIR="/var/www/EasyMed"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR/$DATE"
cp "$PROJECT_DIR/database/easymed.sqlite" "$BACKUP_DIR/$DATE/"
tar -czf "$BACKUP_DIR/$DATE/uploads.tar.gz" -C "$PROJECT_DIR" assets/uploads/
find "$BACKUP_DIR" -type d -mtime +7 -exec rm -rf {} \; 2>/dev/null || true
echo "Backup completed: $DATE"
EOF

    chmod +x /usr/local/bin/easymed-backup.sh
    
    # Add to crontab
    (crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/easymed-backup.sh") | crontab -
    
    print_success "Backup script created"
}

create_deploy_script() {
    print_status "Creating deployment script..."
    
    cat > /usr/local/bin/easymed-deploy.sh << EOF
#!/bin/bash
cd $PROJECT_DIR
cp database/easymed.sqlite database/easymed.sqlite.backup.\$(date +%Y%m%d_%H%M%S)
git pull origin main
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/database $PROJECT_DIR/assets/uploads
systemctl restart php${PHP_VERSION}-fpm nginx
echo "Deployment completed!"
EOF

    chmod +x /usr/local/bin/easymed-deploy.sh
    
    print_success "Deployment script created"
}

show_completion_message() {
    print_success "🎉 EasyMed deployment completed!"
    echo ""
    echo -e "${GREEN}Your EasyMed clinic management system is now live!${NC}"
    echo ""
    echo -e "${BLUE}Access URLs:${NC}"
    echo "  • Website: https://$DOMAIN"
    echo "  • Admin Panel: https://$DOMAIN/admin"
    echo "  • ERD Documentation: https://$DOMAIN/docs/EasyMed_ERD.html"
    echo ""
    echo -e "${BLUE}Management Commands:${NC}"
    echo "  • Deploy updates: sudo /usr/local/bin/easymed-deploy.sh"
    echo "  • Manual backup: sudo /usr/local/bin/easymed-backup.sh"
    echo "  • Check status: sudo systemctl status nginx php${PHP_VERSION}-fpm"
    echo "  • View logs: sudo tail -f /var/log/nginx/error.log"
    echo ""
    echo -e "${YELLOW}Next Steps:${NC}"
    echo "  1. Update /var/www/EasyMed/includes/config.php with production settings"
    echo "  2. Test all website functionality"
    echo "  3. Set up monitoring (optional)"
    echo ""
    echo -e "${GREEN}Happy hosting! 🚀${NC}"
}

# Main execution
main() {
    if [ -z "$1" ]; then
        print_error "Usage: $0 your-domain.com"
        exit 1
    fi
    
    print_status "Starting EasyMed VPS deployment for domain: $DOMAIN"
    
    check_root
    install_dependencies
    clone_project
    set_permissions
    configure_nginx
    start_services
    setup_ssl
    configure_firewall
    create_backup_script
    create_deploy_script
    show_completion_message
}

# Run main function
main "$@"