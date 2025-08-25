# Patient Management Module Documentation

## Overview
The Patient Management system has been refactored into a modular structure with separated concerns for better maintainability and reusability.

## File Structure

```
Project_EasyMed/
├── admin/
│   └── patients.php                 # Main patient management page
├── assets/
│   ├── css/
│   │   └── patient-management.css   # Modular CSS styles
│   └── js/
│       └── patient-management.js    # JavaScript functionality
```

## CSS Module (patient-management.css)

### Components Included:
- **Filter Section**: Search and filter interface styling
- **Statistics Grid**: Dashboard cards with patient statistics
- **Data Table**: Enhanced table styling with hover effects
- **Empty State**: No-data messaging and styling
- **Patient Actions**: Button styling for CRUD operations
- **Status Badges**: Active/Inactive status indicators
- **Notifications**: Toast notification system
- **Responsive Design**: Mobile-friendly layouts

### Key Classes:
- `.filter-section` - Main filter container
- `.filter-grid` - Grid layout for filter controls
- `.stats-grid` - Statistics dashboard layout
- `.stat-card` - Individual statistic cards
- `.empty-state` - No data found styling
- `.patient-actions` - Action button containers
- `.status-badge` - Status indicators

## JavaScript Module (patient-management.js)

### PatientManager Class:
Main class that handles all patient management functionality.

#### Methods:
- `init()` - Initialize the module
- `exportPatients()` - Export filtered patient data to CSV
- `showNotification()` - Display toast notifications
- `confirmAction()` - Handle confirmation dialogs
- `togglePatientStatus()` - Handle patient activation/deactivation
- `resetPatientPassword()` - Handle password reset functionality

#### Features:
- **CSV Export**: Exports current filtered results
- **Auto-filtering**: Optional real-time filter updates
- **Notification System**: User feedback for actions
- **Data Extraction**: Reads patient data from DOM table
- **Error Handling**: Graceful error management

## Integration

### In patients.php:
```php
<!-- CSS Integration -->
<link rel="stylesheet" href="../assets/css/patient-management.css">

<!-- JavaScript Integration -->
<script src="../assets/js/patient-management.js"></script>
```

### HTML Structure Requirements:
- Filter form with class `.filter-form`
- Table with class `.data-table`
- Export button with `onclick="exportPatients()"`
- Patient action buttons with appropriate classes

## Benefits

### 1. **Modularity**
- CSS and JavaScript are separated from PHP logic
- Easy to maintain and update styling/functionality
- Reusable components across different pages

### 2. **Performance**
- CSS and JS files can be cached by browsers
- Reduced page load times
- Better resource management

### 3. **Organization**
- Clear separation of concerns
- Easier debugging and development
- Better code readability

### 4. **Scalability**
- Easy to extend with new features
- Components can be reused in other admin pages
- Consistent styling across the application

## Usage Examples

### Adding Custom Filters:
```javascript
// In patient-management.js
PatientManager.prototype.addCustomFilter = function(filterName, filterValue) {
    // Custom filter logic
};
```

### Custom Styling:
```css
/* In patient-management.css */
.custom-filter-style {
    /* Custom styles */
}
```

### Extending Functionality:
```javascript
// Add new methods to PatientManager class
PatientManager.prototype.customMethod = function() {
    // Custom functionality
};
```

## Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11+ (with polyfills if needed)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Dependencies
- FontAwesome (for icons)
- Basic CSS Grid support
- ES6 JavaScript features

## Maintenance Notes
- Update CSS variables for theme changes
- Extend JavaScript class for new functionality
- Keep documentation updated with changes
- Test responsive design on various screen sizes
