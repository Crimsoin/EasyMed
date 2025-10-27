// EasyMed JavaScript Functions

// Global variables
let currentModal = null;
let selectedRole = '';
let currentStep = 1;
let totalSteps = 3;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Initialize mobile menu
    initializeMobileMenu();
    
    // Initialize modals
    initializeModals();
    
    // Initialize forms
    initializeForms();
    
    // Initialize active navigation
    setActiveNavigation();
    
    // Initialize tooltips and other UI elements
    initializeUIElements();
    
    // Initialize password toggles
    initializePasswordToggles();
    
    // Initialize multi-step form
    initializeMultiStepForm();
}

// Enhanced Modal Functions
function initializeModals() {
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && currentModal) {
            closeModal();
        }
    });
    
    // Initialize close buttons
    const closeButtons = document.querySelectorAll('.close');
    closeButtons.forEach(button => {
        button.addEventListener('click', closeModal);
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        currentModal = modal;
        document.body.style.overflow = 'hidden';
        
        // Add fade-in animation
        setTimeout(() => {
            modal.style.opacity = '1';
        }, 10);
        
        // Focus on first input if exists
        const firstInput = modal.querySelector('input[type="text"], input[type="email"], input[type="password"]');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
        
        // Reset form state if it's register modal
        if (modalId === 'registerModal') {
            resetMultiStepForm();
        }
    }
}

function closeModal() {
    if (currentModal) {
        currentModal.style.opacity = '0';
        
        setTimeout(() => {
            currentModal.style.display = 'none';
            currentModal = null;
            document.body.style.overflow = 'auto';
            
            // Clear form if exists
            const form = currentModal.querySelector('form');
            if (form) {
                form.reset();
            }
            
            // Reset role selection
            selectedRole = '';
            const roleButtons = document.querySelectorAll('.role-btn');
            roleButtons.forEach(btn => btn.classList.remove('active'));
            
            // Reset login modal view
            goBackToRoleSelection();
        }, 300);
    }
}

// Password Toggle Functions
function initializePasswordToggles() {
    document.addEventListener('click', function(e) {
        if (e.target.closest('.password-toggle')) {
            const toggle = e.target.closest('.password-toggle');
            const inputId = toggle.getAttribute('onclick').match(/'([^']+)'/)[1];
            togglePassword(inputId);
        }
    });
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.querySelector(`[onclick*="${inputId}"] i`);
    
    if (input && icon) {
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
}

// Multi-step Form Functions
function initializeMultiStepForm() {
    currentStep = 1;
    totalSteps = 3;
}

function resetMultiStepForm() {
    currentStep = 1;
    updateFormStep();
    updateProgressIndicator();
    updateNavigationButtons();
}

function nextStep() {
    console.log('Next step called, current step:', currentStep);
    
    if (validateCurrentStep()) {
        if (currentStep < totalSteps) {
            currentStep++;
            console.log('Moving to step:', currentStep);
            updateFormStep();
            updateProgressIndicator();
            updateNavigationButtons();
        }
    } else {
        console.log('Current step validation failed');
    }
}

function previousStep() {
    if (currentStep > 1) {
        currentStep--;
        updateFormStep();
        updateProgressIndicator();
        updateNavigationButtons();
    }
}

function updateFormStep() {
    const steps = document.querySelectorAll('.form-step');
    steps.forEach((step, index) => {
        if (index + 1 === currentStep) {
            step.style.display = 'block';
            // Add slide-in animation
            step.style.opacity = '0';
            step.style.transform = 'translateX(20px)';
            setTimeout(() => {
                step.style.opacity = '1';
                step.style.transform = 'translateX(0)';
                step.style.transition = 'all 0.3s ease';
            }, 10);
        } else {
            step.style.display = 'none';
        }
    });
}

function updateProgressIndicator() {
    const progressSteps = document.querySelectorAll('.progress-step');
    progressSteps.forEach((step, index) => {
        const stepNumber = index + 1;
        step.classList.remove('active', 'completed');
        
        if (stepNumber < currentStep) {
            step.classList.add('completed');
            step.innerHTML = '<i class="fas fa-check"></i>';
        } else if (stepNumber === currentStep) {
            step.classList.add('active');
            step.innerHTML = stepNumber;
        } else {
            step.innerHTML = stepNumber;
        }
    });
}

function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    
    if (prevBtn) {
        prevBtn.style.display = currentStep > 1 ? 'inline-flex' : 'none';
    }
    
    if (nextBtn && submitBtn) {
        if (currentStep === totalSteps) {
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'inline-flex';
        } else {
            nextBtn.style.display = 'inline-flex';
            submitBtn.style.display = 'none';
        }
    }
}

function validateCurrentStep() {
    console.log('Validating step:', currentStep);
    
    const currentStepElement = document.querySelector(`#step${currentStep}`);
    if (!currentStepElement) {
        console.log('Step element not found:', `#step${currentStep}`);
        return true;
    }
    
    const requiredInputs = currentStepElement.querySelectorAll('input[required], select[required]');
    console.log('Required inputs found:', requiredInputs.length);
    
    let isValid = true;
    
    requiredInputs.forEach(input => {
        console.log('Validating input:', input.name, 'value:', input.value);
        
        if (!input.value.trim()) {
            showFieldError(input, 'This field is required');
            isValid = false;
        } else if (input.type === 'email' && !isValidEmail(input.value)) {
            showFieldError(input, 'Please enter a valid email address');
            isValid = false;
        } else if (input.type === 'password' && currentStep === 2) {
            if (input.value.length < 8) {
                showFieldError(input, 'Password must be at least 8 characters long');
                isValid = false;
            }
        }
    });
    
    // Validate password confirmation
    if (currentStep === 2) {
        const password = document.getElementById('regPassword');
        const confirmPassword = document.getElementById('regConfirmPassword');
        
        if (password && confirmPassword && password.value !== confirmPassword.value) {
            showFieldError(confirmPassword, 'Passwords do not match');
            isValid = false;
        }
    }
    
    console.log('Step validation result:', isValid);
    return isValid;
}

function showFieldError(input, message) {
    clearFieldError(input);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'form-error show';
    errorDiv.textContent = message;
    
    input.parentNode.insertBefore(errorDiv, input.nextSibling);
    input.style.borderColor = 'var(--error)';
}

function clearFieldError(input) {
    const existingError = input.parentNode.querySelector('.form-error');
    if (existingError) {
        existingError.remove();
    }
    input.style.borderColor = '';
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Mobile Menu Functions
function initializeMobileMenu() {
    const mobileToggle = document.querySelector('.mobile-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (mobileToggle && navMenu) {
        mobileToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            this.classList.toggle('active');
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileToggle.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('active');
                mobileToggle.classList.remove('active');
            }
        });
    }
}

// Role Selection Functions
function selectRole(role) {
    selectedRole = role;
    
    // Update UI
    const roleButtons = document.querySelectorAll('.role-btn');
    roleButtons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-role') === role) {
            btn.classList.add('active');
        }
    });
    
    // Show login form
    const roleSelection = document.getElementById('roleSelection');
    const loginForm = document.getElementById('loginForm');
    const modalTitle = document.querySelector('#loginModal .modal-header h2');
    
    if (roleSelection && loginForm) {
        roleSelection.style.display = 'none';
        loginForm.style.display = 'block';
        
        if (modalTitle) {
            modalTitle.innerHTML = `<i class="fas fa-sign-in-alt"></i> ${capitalizeFirst(role)} Login`;
        }
    }
}

function goBackToRoleSelection() {
    const roleSelection = document.getElementById('roleSelection');
    const loginForm = document.getElementById('loginForm');
    const modalTitle = document.querySelector('#loginModal .modal-header h2');
    const modalSubtitle = document.querySelector('#loginModal .modal-subtitle');
    
    if (roleSelection && loginForm) {
        roleSelection.style.display = 'block';
        loginForm.style.display = 'none';
        
        if (modalTitle) {
            modalTitle.innerHTML = '<i class="fas fa-sign-in-alt"></i> Welcome Back';
        }
        if (modalSubtitle) {
            modalSubtitle.textContent = 'Choose your role to access your account';
        }
    }
    
    selectedRole = '';
    const roleButtons = document.querySelectorAll('.role-btn');
    roleButtons.forEach(btn => btn.classList.remove('active'));
}

// Form Functions
function initializeForms() {
    // Suppress browser validation for all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.setAttribute('novalidate', 'true');
        
        // Handle form submission
        form.addEventListener('submit', function(event) {
            // Custom validation logic can be added here
            // For now, let the form submit normally after preventing browser validation
        });
    });
    
    // Login form
    const loginFormElement = document.getElementById('loginFormElement');
    if (loginFormElement) {
        loginFormElement.addEventListener('submit', handleLogin);
    }
    
    // Registration form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegistration);
    }
    
    // Add input validation
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('blur', validateInput);
        input.addEventListener('input', clearValidationError);
        
        // Prevent browser validation tooltips
        input.addEventListener('invalid', function(event) {
            event.preventDefault();
        });
    });
}

function handleLogin(event) {
    event.preventDefault();
    
    if (!selectedRole) {
        showAlert('Please select a login type', 'error');
        return;
    }
    
    const formData = new FormData(event.target);
    formData.append('role', selectedRole);
    
    showSpinner(event.target);
    
    fetch('includes/ajax/login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideSpinner(event.target);
        
        if (data.success) {
            showAlert('Login successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        hideSpinner(event.target);
        showAlert('An error occurred. Please try again.', 'error');
        console.error('Login error:', error);
    });
}

function handleRegistration(event) {
    event.preventDefault();
    
    console.log('Registration form submitted');
    
    const formData = new FormData(event.target);
    
    // Log form data for debugging
    for (let [key, value] of formData.entries()) {
        console.log(key, value);
    }
    
    // Validate password confirmation
    const password = formData.get('password');
    const confirmPassword = formData.get('confirm_password');
    
    if (password !== confirmPassword) {
        showAlert('Passwords do not match', 'error');
        return;
    }
    
    showSpinner(event.target);
    
    fetch('includes/ajax/register.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers.get('content-type'));
        
        // Get the response as text first to see what we're getting
        return response.text().then(text => {
            console.log('Raw response text:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response was:', text.substring(0, 500));
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        });
    })
    .then(data => {
        console.log('Parsed response data:', data);
        hideSpinner(event.target);
        
        if (data.success) {
            // Close registration modal immediately
            closeModal();
            // Show success message
            showAlert('Registration successful! Please login.', 'success');
            // Open login modal after a brief delay
            setTimeout(() => {
                openModal('loginModal');
            }, 500);
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Registration error:', error);
        hideSpinner(event.target);
        showAlert('An error occurred. Please try again.', 'error');
    });
}

// Validation Functions
function validateInput(event) {
    const input = event.target;
    const value = input.value.trim();
    const type = input.type;
    const name = input.name;
    
    clearValidationError(input);
    
    if (input.hasAttribute('required') && !value) {
        showValidationError(input, 'This field is required');
        return false;
    }
    
    if (type === 'email' && value && !isValidEmail(value)) {
        showValidationError(input, 'Please enter a valid email address');
        return false;
    }
    
    if (name === 'phone' && value && !isValidPhone(value)) {
        showValidationError(input, 'Please enter a valid phone number');
        return false;
    }
    
    if (type === 'password' && value && value.length < 6) {
        showValidationError(input, 'Password must be at least 6 characters');
        return false;
    }
    
    return true;
}

function showValidationError(input, message) {
    input.classList.add('error');
    
    // Remove existing error message
    const existingError = input.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }
    
    // Add new error message below the input
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.color = '#ef4444';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '0.5rem';
    errorDiv.style.display = 'block';
    
    // Add error icon and message
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i>${message}`;
    
    // Insert after the input group
    input.parentNode.insertAdjacentElement('afterend', errorDiv);
}

function clearValidationError(input) {
    if (!input || !input.parentNode) return;
    
    input.classList.remove('error');
    
    // Look for error message after the input group
    const errorMessage = input.parentNode.parentNode?.querySelector('.error-message');
    if (errorMessage) {
        errorMessage.remove();
    }
    
    // Also check for error message within the input group (fallback)
    const inlineError = input.parentNode.querySelector('.error-message');
    if (inlineError) {
        inlineError.remove();
    }
}

// Utility Functions
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPhone(phone) {
    const phoneRegex = /^[\+]?[0-9\-\s\(\)]{10,}$/;
    return phoneRegex.test(phone);
}

function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function formatDate(date) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(date).toLocaleDateString('en-US', options);
}

function formatTime(time) {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

// Alert Functions
function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert-message');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-message`;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '90px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '3000';
    alertDiv.style.minWidth = '300px';
    alertDiv.style.animation = 'slideInRight 0.3s ease-out';
    alertDiv.textContent = message;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 300);
        }
    }, 5000);
    
    // Add click to dismiss
    alertDiv.addEventListener('click', () => {
        alertDiv.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 300);
    });
}

// Spinner Functions
function showSpinner(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<div class="spinner" style="width: 20px; height: 20px; margin: 0 auto;"></div>';
    }
}

function hideSpinner(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtn.getAttribute('data-original-text') || 'Submit';
    }
}

// Navigation Functions
function setActiveNavigation() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navLinks = document.querySelectorAll('.nav-menu a');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPage)) {
            link.classList.add('active');
        }
    });
}

// UI Enhancement Functions
function initializeUIElements() {
    // Smooth scrolling for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href !== '#' && href.length > 1) {
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    
    // Initialize tooltips
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
    
    // Initialize dropdowns
    const dropdowns = document.querySelectorAll('.dropdown, .dropdown-nav');
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                menu.classList.toggle('show');
            });
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        const dropdowns = document.querySelectorAll('.dropdown-menu.show');
        dropdowns.forEach(menu => {
            if (!menu.parentNode.contains(e.target)) {
                menu.classList.remove('show');
            }
        });
    });
}

function showTooltip(event) {
    const element = event.target;
    const tooltipText = element.getAttribute('data-tooltip');
    
    if (tooltipText) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = tooltipText;
        tooltip.style.position = 'absolute';
        tooltip.style.backgroundColor = 'var(--text-dark)';
        tooltip.style.color = 'white';
        tooltip.style.padding = '0.5rem';
        tooltip.style.borderRadius = '4px';
        tooltip.style.fontSize = '0.8rem';
        tooltip.style.zIndex = '4000';
        tooltip.style.pointerEvents = 'none';
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
        
        element._tooltip = tooltip;
    }
}

function hideTooltip(event) {
    const element = event.target;
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
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
    
    .dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        background: white;
        border: 1px solid var(--medium-gray);
        border-radius: 8px;
        box-shadow: 0 4px 20px var(--shadow);
        min-width: 200px;
        z-index: 1000;
    }
    
    .dropdown-menu.show {
        display: block;
    }
    
    .dropdown-menu a {
        display: block;
        padding: 0.75rem 1rem;
        color: var(--text-dark);
        text-decoration: none;
        border-bottom: 1px solid var(--light-gray);
    }
    
    .dropdown-menu a:hover {
        background-color: var(--light-gray);
        color: var(--primary-cyan);
    }
    
    .dropdown-menu a:last-child {
        border-bottom: none;
    }
`;
document.head.appendChild(style);

// Export functions for use in other scripts
window.EasyMed = {
    openModal,
    closeModal,
    selectRole,
    goBackToRoleSelection,
    nextStep,
    previousStep,
    togglePassword,
    showAlert,
    formatDate,
    formatTime,
    isValidEmail,
    isValidPhone
};
