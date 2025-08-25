# EasyMed Clinic Management System

A comprehensive web-based clinic management system built with PHP, SQLite, and modern web technologies.

## 🏥 Features

### Patient Management
- Patient registration and profile management
- Appointment booking system
- Medical history tracking
- Payment processing with GCash integration

### Doctor Management
- Doctor profiles and specializations
- Schedule management (days and hours)
- Laboratory offers management
- Consultation fee setting

### Admin Dashboard
- Complete appointment management
- Patient and doctor administration
- Payment verification system
- Reports and analytics
- Settings management

### Laboratory Services
- Dynamic laboratory offers
- Doctor-specific service assignments
- Integration with appointment booking

### Payment System
- GCash QR code integration
- Payment proof upload
- Admin verification workflow
- Consultation fee management

## 🛠️ Technology Stack

- **Backend**: PHP 8.x
- **Database**: SQLite
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Styling**: CSS Grid, Flexbox, CSS Variables
- **Icons**: Font Awesome
- **Payment**: GCash QR Code Integration

## 🚀 Installation

### Prerequisites
- PHP 8.0 or higher
- Web server (Apache/Nginx)
- SQLite extension enabled

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/easymed-clinic.git
   cd easymed-clinic
   ```

2. **Configure the application**
   - Copy `includes/config.sample.php` to `includes/config.php`
   - Update database path and site URL in config.php
   - Set up your clinic information

3. **Set up the database**
   - Create SQLite database file
   - Run database migrations (if available)
   - Set proper file permissions

4. **Configure web server**
   - Point document root to project directory
   - Ensure mod_rewrite is enabled (for Apache)
   - Set proper directory permissions

## 📁 Project Structure

```
EasyMed/
├── admin/                  # Admin dashboard and management
│   ├── Dashboard/
│   ├── Patient Management/
│   ├── Doctor Management/
│   ├── Appointment/
│   └── Settings/
├── patient/               # Patient portal
├── doctor/                # Doctor dashboard
├── includes/              # Core PHP files
├── assets/                # Static assets
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/
├── database/              # Database files
└── docs/                  # Documentation
```

## 🎨 Key Features Implemented

### Responsive Design
- Mobile-first approach
- Adaptive layouts for all screen sizes
- Modern CSS Grid and Flexbox

### User Authentication
- Role-based access control (Admin, Doctor, Patient)
- Secure session management
- Password hashing and validation

### Appointment System
- Real-time availability checking
- Multiple status tracking (pending, confirmed, completed)
- Email/SMS notifications (configurable)

### Payment Integration
- GCash QR code generation
- Receipt upload and verification
- Payment status tracking

## 🔧 Configuration

### Database Configuration
Update `includes/config.php` with your database settings:

```php
define('DB_PATH', '/path/to/your/database.sqlite');
```

### Site Configuration
```php
define('SITE_URL', 'https://your-domain.com');
define('SITE_NAME', 'Your Clinic Name');
```

### Payment Configuration
- Set up GCash merchant details
- Configure QR code generation settings

## 🚦 Usage

### For Administrators
1. Access admin panel at `/admin/`
2. Manage doctors, patients, and appointments
3. Configure clinic settings and services
4. Verify payments and generate reports

### For Doctors
1. Log in to doctor portal
2. Manage schedule and availability
3. View assigned appointments
4. Update profile and services

### For Patients
1. Register for an account
2. Book appointments with preferred doctors
3. Upload payment proofs
4. View appointment history

## 🔒 Security Features

- SQL injection protection via prepared statements
- XSS prevention with input sanitization
- CSRF protection for forms
- Secure file upload handling
- Role-based access control

## 📱 Browser Support

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👥 Support

For support and questions:
- Create an issue on GitHub
- Email: your-email@example.com

## 🙏 Acknowledgments

- Font Awesome for icons
- Modern CSS techniques and best practices
- PHP community for excellent documentation

---

**Built with ❤️ for better healthcare management**
