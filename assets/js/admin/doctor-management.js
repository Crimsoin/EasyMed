/**
 * Doctor Management JavaScript
 * Enhanced functionality for doctor account management
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Enhanced delete confirmation with loading state
    const deleteButtons = document.querySelectorAll('button[type="submit"].btn-danger');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const form = this.closest('form');
            const doctorRow = this.closest('tr');
            const doctorName = doctorRow.querySelector('.doctor-info h4').textContent;
            
            // Create enhanced confirmation dialog
            const confirmDelete = confirm(
                `⚠️ PERMANENT DELETION WARNING ⚠️\n\n` +
                `Are you sure you want to permanently delete Dr. ${doctorName}?\n\n` +
                `This action will:\n` +
                `• Delete the doctor account permanently\n` +
                `• Remove all appointment history\n` +
                `• Delete all patient reviews\n` +
                `• Remove schedule information\n` +
                `• Clear all associated records\n\n` +
                `This action CANNOT be undone!\n\n` +
                `Type "DELETE" to confirm (case sensitive):`
            );
            
            if (confirmDelete !== null) {
                const userConfirmation = prompt(
                    `To confirm deletion of Dr. ${doctorName}, please type "DELETE" exactly:`
                );
                
                if (userConfirmation === 'DELETE') {
                    // Show loading state
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    this.disabled = true;
                    
                    // Add loading class to row
                    doctorRow.classList.add('deleting');
                    doctorRow.style.opacity = '0.6';
                    
                    // Submit form
                    form.submit();
                } else if (userConfirmation !== null) {
                    alert('Deletion cancelled. You must type "DELETE" exactly to confirm.');
                }
            }
        });
    });
    
    // Enhanced toggle status with loading state
    const toggleButtons = document.querySelectorAll('button[type="submit"].btn-delete, button[type="submit"].btn-toggle');
    
    toggleButtons.forEach(button => {
        if (!button.classList.contains('btn-danger')) { // Skip delete buttons
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const form = this.closest('form');
                const isDeactivating = this.querySelector('.fa-ban');
                const doctorRow = this.closest('tr');
                const doctorName = doctorRow.querySelector('.doctor-info h4').textContent;
                
                const action = isDeactivating ? 'deactivate' : 'activate';
                const confirmMessage = `Are you sure you want to ${action} Dr. ${doctorName}?`;
                
                if (confirm(confirmMessage)) {
                    // Show loading state
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    this.disabled = true;
                    
                    // Submit form
                    form.submit();
                }
            });
        }
    });
    
    // Search and filter enhancements
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Add visual feedback for search
                this.style.borderColor = '#00bcd4';
                setTimeout(() => {
                    this.style.borderColor = '';
                }, 1000);
            }, 300);
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
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
            if (searchInput && searchInput === document.activeElement) {
                searchInput.value = '';
                searchInput.blur();
            }
        }
    });
    
    // Auto-refresh for real-time updates (optional)
    let autoRefreshEnabled = false;
    let refreshInterval;
    
    // Add refresh button if not exists
    const filtersContainer = document.querySelector('.filters');
    if (filtersContainer && !document.querySelector('#auto-refresh-toggle')) {
        const refreshToggle = document.createElement('button');
        refreshToggle.id = 'auto-refresh-toggle';
        refreshToggle.type = 'button';
        refreshToggle.className = 'btn btn-outline';
        refreshToggle.innerHTML = '<i class="fas fa-sync"></i> Auto Refresh: OFF';
        refreshToggle.style.marginLeft = '1rem';
        
        refreshToggle.addEventListener('click', function() {
            autoRefreshEnabled = !autoRefreshEnabled;
            
            if (autoRefreshEnabled) {
                this.innerHTML = '<i class="fas fa-sync fa-spin"></i> Auto Refresh: ON';
                this.classList.add('active');
                
                refreshInterval = setInterval(() => {
                    window.location.reload();
                }, 30000); // Refresh every 30 seconds
            } else {
                this.innerHTML = '<i class="fas fa-sync"></i> Auto Refresh: OFF';
                this.classList.remove('active');
                clearInterval(refreshInterval);
            }
        });
        
        filtersContainer.appendChild(refreshToggle);
    }
    
    // Show success/error messages with better styling
    const messageAlert = document.querySelector('.alert');
    if (messageAlert) {
        // Auto-hide success messages after 5 seconds
        if (messageAlert.classList.contains('alert-success')) {
            setTimeout(() => {
                messageAlert.style.opacity = '0';
                setTimeout(() => {
                    messageAlert.remove();
                }, 300);
            }, 5000);
        }
        
        // Add close button to messages
        if (!messageAlert.querySelector('.close-btn')) {
            const closeBtn = document.createElement('button');
            closeBtn.className = 'close-btn';
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = `
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                opacity: 0.7;
            `;
            
            closeBtn.addEventListener('click', () => {
                messageAlert.style.opacity = '0';
                setTimeout(() => {
                    messageAlert.remove();
                }, 300);
            });
            
            messageAlert.appendChild(closeBtn);
        }
    }
    
    console.log('Doctor Management JavaScript loaded successfully');
});