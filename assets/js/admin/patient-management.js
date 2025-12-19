/**
 * Patient Management JavaScript
 * Handles client-side functionality for the patient management system
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Patient Management JS loaded');
    
    // Initialize patient management functionality
    initializePatientManagement();
});

function initializePatientManagement() {
    // Add loading states to form submissions
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton && !submitButton.disabled) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                // Re-enable after 5 seconds as a failsafe
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = submitButton.getAttribute('data-original-text') || 'Submit';
                }, 5000);
            }
        });
    });
    
    // Enhanced confirmation for delete buttons
    const deleteButtons = document.querySelectorAll('button[onclick*="delete_patient"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const form = this.closest('form');
            const patientName = this.getAttribute('data-patient-name') || 'this patient';
            
            // Create custom confirmation dialog
            const confirmed = confirm(`⚠️ DANGER: PERMANENT DELETION\n\n` +
                `You are about to permanently delete ${patientName} and ALL related data:\n\n` +
                `• Patient profile and medical information\n` +
                `• All appointment history\n` +
                `• Payment records\n` +
                `• Reviews and ratings\n` +
                `• Account access credentials\n\n` +
                `This action CANNOT be undone and the data CANNOT be recovered.\n\n` +
                `Are you absolutely certain you want to proceed?`);
            
            if (confirmed) {
                // Second confirmation for critical action
                const doubleConfirmed = confirm(`FINAL CONFIRMATION\n\n` +
                    `Last chance to cancel. Delete ${patientName} permanently?`);
                
                if (doubleConfirmed) {
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    form.submit();
                }
            }
        });
    });
    
    // Add tooltips to action buttons
    const actionButtons = document.querySelectorAll('.btn-action');
    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            if (title) {
                this.setAttribute('data-original-title', title);
            }
        });
    });
    
    // Search functionality enhancements
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Could implement live search here
                console.log('Search term:', this.value);
            }, 500);
        });
    }
    
    // Table row hover effects
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(2px)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Status badge click handlers (for future expansion)
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        badge.addEventListener('click', function() {
            // Could implement quick status change here
            console.log('Status badge clicked:', this.textContent);
        });
    });
    
    // Auto-refresh functionality (optional)
    if (window.location.search.includes('auto-refresh')) {
        setInterval(() => {
            window.location.reload();
        }, 30000); // Refresh every 30 seconds
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + F for search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchInput.value = '';
                searchInput.form.submit();
            }
        }
    });
}

// Utility functions
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i>
        ${message}
    `;
    
    // Insert at top of content
    const content = document.querySelector('.admin-content');
    if (content) {
        content.insertBefore(notification, content.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

function formatTableData() {
    // Format phone numbers
    const phoneElements = document.querySelectorAll('[data-phone]');
    phoneElements.forEach(element => {
        const phone = element.getAttribute('data-phone');
        if (phone) {
            element.textContent = formatPhoneNumber(phone);
        }
    });
    
    // Format dates
    const dateElements = document.querySelectorAll('[data-date]');
    dateElements.forEach(element => {
        const date = element.getAttribute('data-date');
        if (date) {
            element.textContent = formatDate(date);
        }
    });
}

function formatPhoneNumber(phone) {
    // Basic phone number formatting
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 11) {
        return cleaned.replace(/(\d{4})(\d{3})(\d{4})/, '$1-$2-$3');
    }
    return phone;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Export functions for global access
window.PatientManagement = {
    showNotification,
    formatTableData,
    formatPhoneNumber,
    formatDate
};

console.log('Patient Management JavaScript initialized successfully');