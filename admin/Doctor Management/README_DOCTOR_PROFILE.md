# Doctor Profile View Template

## Overview
The doctor profile view template provides a comprehensive view of doctor information in the admin panel. It displays detailed information about a doctor including personal details, professional information, statistics, and recent appointments.

## Files Created
1. `admin/Doctor Management/view-doctor.php` - Main profile view page
2. `assets/css/admin/view-doctor-profile.css` - Stylesheet for the profile view

## Features

### Profile Header
- Doctor avatar with fallback to initials
- Doctor name and specialty
- Status badges (Active/Inactive, Available/Unavailable)
- Rating display (if available)

### Statistics Dashboard
- Total appointments
- Completed appointments
- Pending appointments
- Cancelled appointments

### Information Sections
- **Personal Information**: Name, email, phone, gender, date of birth, member since
- **Professional Information**: Specialty, license number, experience, consultation fee, schedule, biography

### Recent Appointments
- Last 5 appointments with patient names
- Appointment dates and times
- Status indicators
- Quick link to view all appointments

### Quick Actions
- Edit profile
- Toggle doctor status (activate/deactivate)
- View appointments
- Send email

## Usage

### Navigation
The view doctor profile can be accessed from:
1. Doctor Management page → Click the eye icon (👁️) in the Actions column
2. Direct URL: `admin/Doctor Management/view-doctor.php?id={doctor_id}`

### Integration
The view profile link is already integrated into the doctors listing page at:
- Line 341 in `admin/Doctor Management/doctors.php`

### Database Requirements
The template uses the following database tables:
- `users` - Basic user information
- `doctors` - Doctor-specific information
- `appointments` - Appointment data for statistics
- `reviews` - Rating and review data

## Security
- Requires admin authentication
- Input validation for doctor ID
- XSS protection with `htmlspecialchars()`
- SQL injection protection with prepared statements

## Responsive Design
The template is fully responsive and includes:
- Mobile-friendly layout
- Adaptive grid system
- Touch-friendly buttons
- Optimized typography

## Customization
You can customize the appearance by modifying:
- `view-doctor-profile.css` for styling
- Color scheme variables in the CSS
- Layout structure in the PHP file
- Additional information fields as needed

## Status Modal
Includes a confirmation modal for status changes with:
- Smooth animations
- Backdrop blur effect
- Accessible close buttons
- Form submission handling

## Browser Support
- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11+ (with some limitations)
- Mobile browsers (iOS Safari, Chrome Mobile)
