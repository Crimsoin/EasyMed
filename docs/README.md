# EasyMed - Patient Appointment Management System

A comprehensive web-based patient appointment management system designed specifically for private clinics. Built with PHP, MySQL, and modern web technologies, featuring a responsive design with a cyan and white minimalistic theme.

## Features

### For Patients
- ✅ User registration and profile management
- ✅ Online appointment booking with doctors
- ✅ View appointment history and medical records
- ✅ GCash payment integration with receipt upload
- ✅ Submit reviews and feedback
- ✅ Email notifications for appointment updates
- ✅ Responsive dashboard

### For Doctors
- ✅ View and manage appointment schedules
- ✅ Set availability and working hours
- ✅ Access patient medical history
- ✅ Record consultation notes, diagnoses, and prescriptions
- ✅ Request and view laboratory tests
- ✅ Monitor consultation statistics
- ✅ Receive appointment notifications

### For Administrators
- ✅ Comprehensive system dashboard
- ✅ Manage doctor profiles and schedules
- ✅ Approve, reschedule, or cancel appointments
- ✅ Payment verification and management
- ✅ Generate analytics and reports
- ✅ User account management
- ✅ System activity logging
- ✅ Review patient feedback

### General Features
- ✅ Secure role-based authentication
- ✅ Responsive design (mobile-friendly)
- ✅ Modern minimalistic UI with cyan/white theme
- ✅ Email notification system
- ✅ Activity logging and audit trails
- ✅ Payment tracking with GCash QR integration
- ✅ Search and filter functionality
- ✅ Data export capabilities

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Icons**: Font Awesome 6
- **Server**: Apache (XAMPP recommended)

## Installation Instructions

### Prerequisites
1. XAMPP (Apache + MySQL + PHP)
2. Web browser (Chrome, Firefox, Safari, Edge)
3. Text editor (optional, for customization)

### Step 1: Download and Extract
1. Download the EasyMed project files
2. Extract to your XAMPP htdocs directory: `C:\xampp\htdocs\Project_EasyMed`

### Step 2: Start XAMPP Services
1. Open XAMPP Control Panel
2. Start **Apache** and **MySQL** services
3. Ensure both services are running (green status)

### Step 3: Create Database
1. Open your web browser
2. Navigate to: `http://localhost/Project_EasyMed/setup_database.php`
3. Wait for the setup to complete
4. You should see "Database setup completed successfully!"

### Step 4: Access the System
1. Navigate to: `http://localhost/Project_EasyMed/`
2. The homepage should load with the EasyMed interface

## Default Login Credentials

### Administrator
- **Username**: `admin`
- **Password**: `admin123`
- **Access**: Full system management

### Sample Doctors
1. **Dr. John Smith (General Medicine)**
   - **Username**: `dr_smith`
   - **Password**: `doctor123`

2. **Dr. Sarah Johnson (Pediatrics)**
   - **Username**: `dr_johnson`
   - **Password**: `doctor123`

3. **Dr. Michael Brown (Cardiology)**
   - **Username**: `dr_brown`
   - **Password**: `doctor123`

### Patients
Patients need to register through the registration form on the website.

## Configuration

### Database Settings
Edit `includes/config.php` to modify database connection:
```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'easymed_db');
```

### Site Settings
Modify site URL and other settings in `includes/config.php`:
```php
define('SITE_URL', 'http://localhost/Project_EasyMed');
define('SITE_NAME', 'EasyMed - Patient Appointment Management System');
```

### Email Configuration
For email notifications, configure SMTP settings in `includes/config.php`:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

## File Structure

```
Project_EasyMed/
├── admin/                 # Admin dashboard and management
│   ├── dashboard.php
│   └── ...
├── doctor/               # Doctor portal
│   ├── dashboard_doctor.php
│   └── ...
├── patient/              # Patient portal
│   ├── dashboard.php
│   └── ...
├── assets/               # Static assets
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── script.js
│   └── images/
├── includes/             # Core system files
│   ├── config.php        # Configuration settings
│   ├── database.php      # Database connection
│   ├── functions.php     # Utility functions
│   ├── header.php        # Common header
│   ├── footer.php        # Common footer
│   └── ajax/            # AJAX handlers
├── index.php            # Homepage
├── about.php            # About page
├── doctors.php          # Find doctors page
├── location.php         # Location and contact
├── payment.php          # Payment information
├── reviews.php          # Patient reviews
└── setup_database.php   # Database setup script
```

## Key Pages and URLs

- **Homepage**: `http://localhost/Project_EasyMed/`
- **Admin Dashboard**: `http://localhost/Project_EasyMed/admin/Dashboard/dashboard.php`
- **Doctor Dashboard**: `http://localhost/Project_EasyMed/doctor/dashboard_doctor.php`
- **Patient Dashboard**: `http://localhost/Project_EasyMed/patient/dashboard_patients.php`
- **Database Setup**: `http://localhost/Project_EasyMed/setup_database.php`

## Usage Guide

### For New Patients
1. Visit the homepage
2. Click "Register" to create an account
3. Fill in personal information
4. Login with your credentials
5. Book appointments through "Find Doctors" or patient dashboard

### For Doctors
1. Login with provided doctor credentials
2. Access your dashboard to view appointments
3. Update your availability and schedule
4. Manage patient consultations and records

### For Administrators
1. Login with admin credentials
2. Access comprehensive dashboard
3. Manage all users, appointments, and system settings
4. Generate reports and monitor system activity

## Payment Integration

The system uses GCash QR code payment:
1. Patients scan the QR code with GCash app
2. Make payment for consultation fee
3. Upload payment receipt proof
4. Admin verifies payment and confirms appointment

## Security Features

- Password hashing with PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- Session management with timeout
- Role-based access control
- Activity logging for audit trails

## Customization

### Theme Colors
Modify CSS variables in `assets/css/style.css`:
```css
:root {
    --primary-cyan: #00bcd4;
    --light-cyan: #4dd0e1;
    --dark-cyan: #0097a7;
    --white: #ffffff;
    /* ... other colors */
}
```

### Clinic Information
Update clinic details in the admin settings or directly in `clinic_settings` table.

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure MySQL is running in XAMPP
   - Check database credentials in `config.php`
   - Verify database exists by running setup script

2. **Page Not Found (404)**
   - Check XAMPP Apache service is running
   - Verify file paths and folder structure
   - Ensure project is in correct htdocs directory

3. **Login Issues**
   - Verify database setup completed successfully
   - Check default credentials
   - Clear browser cache and cookies

4. **Email Not Working**
   - Configure SMTP settings in `config.php`
   - Enable "Less secure app access" for Gmail
   - Use app-specific passwords for Gmail

### Support

For technical support or questions:
- Check the troubleshooting section above
- Review error logs in XAMPP
- Ensure all prerequisites are met

## License

This project is created for educational and private clinic use. Please ensure compliance with local healthcare data protection regulations.

---

**EasyMed** - Making healthcare appointments easy and efficient! 🏥💙
