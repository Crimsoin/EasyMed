/**
 * Patient Management JavaScript Module
 * Handles filtering, exporting, and UI interactions for patient management
 */

class PatientManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initNotificationSystem();
    }

    bindEvents() {
        // Export button event
        const exportBtn = document.querySelector('[onclick="exportPatients()"]');
        if (exportBtn) {
            exportBtn.removeAttribute('onclick');
            exportBtn.addEventListener('click', () => this.exportPatients());
        }

        // Note: Auto-filter disabled - users must click "Apply Filters" button
        // this.initAutoFilter();
    }

    initAutoFilter() {
        const filterForm = document.querySelector('.filter-form');
        if (filterForm) {
            const inputs = filterForm.querySelectorAll('select, input[type="text"]');
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    // Debounce for text inputs
                    if (input.type === 'text') {
                        clearTimeout(this.searchTimeout);
                        this.searchTimeout = setTimeout(() => {
                            filterForm.submit();
                        }, 500);
                    } else {
                        filterForm.submit();
                    }
                });
            });
        }
    }

    exportPatients() {
        try {
            // Get current filter parameters
            const params = new URLSearchParams(window.location.search);
            
            // Get patient data from the page
            const patients = this.getPatientDataFromTable();
            
            if (patients.length === 0) {
                this.showNotification('No patient data to export', 'warning');
                return;
            }

            // Create CSV content
            const csvContent = this.generateCSV(patients);
            
            // Create and download file
            this.downloadFile(csvContent, this.generateFilename());
            
            // Show success message
            this.showNotification('Patient data exported successfully!', 'success');
            
        } catch (error) {
            console.error('Export error:', error);
            this.showNotification('Error exporting data. Please try again.', 'error');
        }
    }

    getPatientDataFromTable() {
        const patients = [];
        const rows = document.querySelectorAll('.data-table tbody tr');
        
        rows.forEach(row => {
            // Skip empty state row
            if (row.querySelector('.empty-state')) return;
            
            const cells = row.querySelectorAll('td');
            if (cells.length >= 8) {
                const patient = {
                    id: cells[0].textContent.trim(),
                    name: cells[1].textContent.trim(),
                    username: cells[2].textContent.trim(),
                    email: cells[3].textContent.trim(),
                    phone: cells[4].textContent.trim(),
                    dateOfBirth: this.extractDateFromCell(cells[5]),
                    age: this.extractAgeFromCell(cells[5]),
                    status: cells[6].textContent.trim(),
                    created: cells[7].textContent.trim()
                };
                patients.push(patient);
            }
        });
        
        return patients;
    }

    extractDateFromCell(cell) {
        const text = cell.textContent.trim();
        if (text === '-') return '';
        
        // Extract date before the age part
        const lines = text.split('\n');
        return lines[0] ? lines[0].trim() : '';
    }

    extractAgeFromCell(cell) {
        const text = cell.textContent.trim();
        const ageMatch = text.match(/\((\d+) years old\)/);
        return ageMatch ? ageMatch[1] : '';
    }

    generateCSV(patients) {
        const headers = [
            'ID', 'Name', 'Username', 'Email', 'Phone', 
            'Date of Birth', 'Age', 'Status', 'Created'
        ];
        
        let csvContent = headers.join(',') + '\n';
        
        patients.forEach(patient => {
            const row = [
                patient.id,
                `"${this.escapeCsvValue(patient.name)}"`,
                `"${this.escapeCsvValue(patient.username)}"`,
                `"${this.escapeCsvValue(patient.email)}"`,
                `"${this.escapeCsvValue(patient.phone)}"`,
                `"${this.escapeCsvValue(patient.dateOfBirth)}"`,
                patient.age,
                `"${this.escapeCsvValue(patient.status)}"`,
                `"${this.escapeCsvValue(patient.created)}"`
            ];
            csvContent += row.join(',') + '\n';
        });
        
        return csvContent;
    }

    escapeCsvValue(value) {
        if (typeof value !== 'string') return value;
        return value.replace(/"/g, '""');
    }

    generateFilename() {
        const date = new Date().toISOString().split('T')[0];
        const time = new Date().toTimeString().split(' ')[0].replace(/:/g, '-');
        return `patients_export_${date}_${time}.csv`;
    }

    downloadFile(content, filename) {
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        } else {
            // Fallback for older browsers
            window.open('data:text/csv;charset=utf-8,' + encodeURIComponent(content));
        }
    }

    initNotificationSystem() {
        // Create notification container if it doesn't exist
        if (!document.querySelector('.notification-container')) {
            const container = document.createElement('div');
            container.className = 'notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }
    }

    showNotification(message, type = 'info', duration = 3000) {
        const container = document.querySelector('.notification-container');
        if (!container) return;

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.style.cssText = `
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 1rem;
            margin-bottom: 1rem;
            min-width: 300px;
            animation: slideInRight 0.3s ease-out;
            pointer-events: auto;
            position: relative;
        `;

        const icon = this.getNotificationIcon(type);
        const borderColor = this.getNotificationBorderColor(type);
        
        notification.style.borderLeft = `4px solid ${borderColor}`;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas ${icon}" style="color: ${borderColor};"></i>
                <span style="flex: 1;">${message}</span>
                <button onclick="this.closest('.notification').remove()" 
                        style="background: none; border: none; font-size: 1.2em; cursor: pointer; color: #999;">
                    &times;
                </button>
            </div>
        `;

        container.appendChild(notification);

        // Auto remove after duration
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }
        }, duration);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    getNotificationBorderColor(type) {
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8'
        };
        return colors[type] || colors.info;
    }

    // Utility method to confirm actions
    confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    // Method to handle patient status toggle
    togglePatientStatus(patientId, currentStatus) {
        const action = currentStatus ? 'deactivate' : 'activate';
        const message = `Are you sure you want to ${action} this patient?`;
        
        this.confirmAction(message, () => {
            window.location.href = `patients.php?action=${currentStatus ? 'delete' : 'activate'}&id=${patientId}`;
        });
    }

    // Method to handle password reset
    resetPatientPassword(patientId) {
        const message = "Are you sure you want to reset this patient's password to 'password123'?";
        
        this.confirmAction(message, () => {
            window.location.href = `patients.php?action=reset_password&id=${patientId}`;
        });
    }
}

// Utility function for legacy support
function exportPatients() {
    if (window.patientManager) {
        window.patientManager.exportPatients();
    }
}

// Add slide-out animation
const additionalStyles = `
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Add additional styles
    const styleSheet = document.createElement('style');
    styleSheet.textContent = additionalStyles;
    document.head.appendChild(styleSheet);
    
    // Initialize patient manager
    window.patientManager = new PatientManager();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PatientManager;
}
