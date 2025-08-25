# Admin Panel Folder Structure

This document describes the reorganized admin panel structure for EasyMed.

## Folder Organization

### 📊 Dashboard/
- `dashboard.php` - Main admin dashboard with system overview and statistics

### 👥 Patient Management/
- `patients.php` - Patient listing and management
- `add-patient.php` - Add new patient form
- `edit-patient.php` - Edit patient information
- `view-patient.php` - View patient details

### 👨‍⚕️ Doctor Management/
- `doctors.php` - Doctor listing and management
- `add-doctor.php` - Add new doctor form
- `edit-doctor.php` - Edit doctor information
- `view-doctor.php` - View doctor details
- `doctors_old_layout.php` - Legacy doctor layout (kept for reference)

### 📅 Appointment/
- `appointments.php` - Appointment management and scheduling

### 📈 Report and Analytics/
- `reports.php` - Main reports dashboard
- `reports_new.php` - New reports interface
- `export-report.php` - Report export functionality

### ⚙️ Settings/
- `settings.php` - System settings and configuration
- `activation-fix.php` - User activation troubleshooting tool
- `users_new.php` - User management interface

## Navigation

- All files maintain proper navigation through relative paths
- The main admin index (`admin/index.php`) redirects to the Dashboard
- Each section maintains internal links within its folder
- Cross-section navigation uses relative paths (e.g., `../Dashboard/dashboard.php`)

## File Path Updates

All PHP files have been updated with:
- Corrected include paths: `../../includes/` instead of `../includes/`
- Updated navigation links to point to new folder structure
- Maintained internal file references within each section

## Benefits

1. **Better Organization** - Related functionality is grouped together
2. **Easier Maintenance** - Clear separation of concerns
3. **Improved Navigation** - Logical folder structure
4. **Future Scalability** - Easy to add new features to appropriate sections
5. **Code Maintainability** - Related files are located together

## Usage

Access the admin panel through:
- Main Dashboard: `/admin/Dashboard/dashboard.php`
- Direct Section Access: `/admin/[Section Name]/[file].php`
- Auto-redirect: `/admin/` (redirects to dashboard)
