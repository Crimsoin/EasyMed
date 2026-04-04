// EasyMed JavaScript Functions

// Global variables
let currentModal = null;
let selectedRole = '';
let currentStep = 1;
let totalSteps = 3;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
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

    // Initialize admin sidebar toggle
    initializeAdminSidebar();
}

// Enhanced Modal Functions
function initializeModals() {
    // Close modal when clicking outside
    window.addEventListener('click', function (event) {
        if (event.target.classList.contains('modal')) {
            closeModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function (event) {
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

        // Clear any existing modal alerts
        const alertContainers = modal.querySelectorAll('[id$="ModalAlert"]');
        alertContainers.forEach(container => {
            container.style.display = 'none';
            container.innerHTML = '';
        });

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
        // Clear any modal alerts before closing
        const alertContainers = currentModal.querySelectorAll('[id$="ModalAlert"]');
        alertContainers.forEach(container => {
            container.style.display = 'none';
            container.innerHTML = '';
        });

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
    document.addEventListener('click', function (e) {
        if (e.target.closest('.password-toggle')) {
            const toggle = e.target.closest('.password-toggle');
            const inputId = toggle.getAttribute('data-target');
            if (inputId) {
                togglePassword(inputId, toggle);
            }
        }
    });
}

function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);

    if (!input) {
        console.error('Input not found:', inputId);
        return;
    }

    // Find the icon - if button is provided, look inside it, otherwise search globally
    let icon;
    if (button && typeof button === 'object') {
        icon = button.querySelector('i');
    } else {
        icon = document.querySelector(`[onclick*="${inputId}"] i`);
    }

    if (!icon) {
        console.error('Icon not found for input:', inputId);
        return;
    }

    // Toggle the password visibility
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Multi-step Form Functions
function initializeMultiStepForm() {
    currentStep = 1;
    totalSteps = 4;
}

function resetMultiStepForm() {
    currentStep = 1;
    updateFormStep();
    updateProgressIndicator();
    updateNavigationButtons();
}

function nextStep() {
    // Clear any error messages when moving to next step
    clearModalAlert('registerModalAlert');

    if (validateCurrentStep()) {
        if (currentStep < totalSteps) {
            currentStep++;
            updateFormStep();
            updateProgressIndicator();
            updateNavigationButtons();
        }
    }
}

function previousStep() {
    // Clear any error messages when going back
    clearModalAlert('registerModalAlert');

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
    const progressWraps = document.querySelectorAll('.progress-step-wrap');
    progressWraps.forEach((wrap, index) => {
        const stepNumber = index + 1;
        const stepCircle = wrap.querySelector('.progress-step');

        wrap.classList.remove('active', 'completed');
        stepCircle.classList.remove('active', 'completed');

        if (stepNumber < currentStep) {
            wrap.classList.add('completed');
            stepCircle.classList.add('completed');
            stepCircle.innerHTML = '<i class="fas fa-check"></i>';
        } else if (stepNumber === currentStep) {
            wrap.classList.add('active');
            stepCircle.classList.add('active');
            stepCircle.innerHTML = stepNumber;
        } else {
            stepCircle.innerHTML = stepNumber;
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

    if (nextBtn && submitBtn && verifyBtn) {
        if (currentStep < 3) {
            nextBtn.style.display = 'inline-flex';
            submitBtn.style.display = 'none';
            verifyBtn.style.display = 'none';
        } else if (currentStep === 3) {
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'inline-flex';
            verifyBtn.style.display = 'none';
        } else if (currentStep === 4) {
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'none';
            verifyBtn.style.display = 'inline-flex';
            if (prevBtn) prevBtn.style.display = 'none'; // Disable going back once submitted
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
        } else if (input.type === 'email') {
            if (!isValidEmail(input.value)) {
                showFieldError(input, 'Please enter a valid email address');
                isValid = false;
            } else if (input.dataset.exists === 'true') {
                showFieldError(input, 'This email address is already in use');
                isValid = false;
            }
        } else if ((input.type === 'password' || input.name === 'password' || input.name === 'confirm_password') && currentStep === 2) {
            if (input.value.length < 8) {
                showFieldError(input, 'Must be at least 8 characters');
                isValid = false;
            } else if (!/[A-Z]/.test(input.value)) {
                showFieldError(input, 'Requires 1 uppercase letter');
                isValid = false;
            } else if (!/[a-z]/.test(input.value)) {
                showFieldError(input, 'Requires 1 lowercase letter');
                isValid = false;
            } else if (!/[^A-Za-z0-9]/.test(input.value)) {
                showFieldError(input, 'Requires 1 special character');
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
    errorDiv.className = 'error-message show';
    errorDiv.style.color = '#ef4444';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '0.5rem';
    errorDiv.style.display = 'block';
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i>${message}`;

    // Get the input-group parent
    const inputGroup = input.closest('.input-group');
    if (inputGroup) {
        inputGroup.insertAdjacentElement('afterend', errorDiv);
    } else {
        input.insertAdjacentElement('afterend', errorDiv);
    }
    
    input.style.borderColor = 'var(--error)';
}

function clearFieldError(input) {
    if (!input) return;
    
    const inputGroup = input.closest('.input-group');
    const parent = inputGroup || input;
    const existingError = parent.nextElementSibling;
    
    if (existingError && existingError.classList.contains('error-message')) {
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
        mobileToggle.addEventListener('click', function () {
            navMenu.classList.toggle('active');
            this.classList.toggle('active');
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function (e) {
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
        form.addEventListener('submit', function (event) {
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

        // Clear modal alert when user starts typing in the registration form
        const registerInputs = registerForm.querySelectorAll('.form-control');
        registerInputs.forEach(input => {
            input.addEventListener('input', () => clearModalAlert('registerModalAlert'));
        });
    }

    // Add input validation
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('blur', validateInput);
        input.addEventListener('input', clearValidationError);

        // Prevent browser validation tooltips
        input.addEventListener('invalid', function (event) {
            event.preventDefault();
        });
    });
}

function handleLogin(event) {
    event.preventDefault();

    if (!selectedRole) {
        showModalAlert('loginModalAlert', 'Please select a login type', 'error');
        return;
    }

    const formData = new FormData(event.target);
    formData.append('role', selectedRole);

    showSpinner(event.target);

    // Get the base URL from the page
    const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.indexOf('/', 1));

    fetch(baseUrl + '/includes/ajax/login.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            hideSpinner(event.target);

            if (data.success) {
                showModalAlert('loginModalAlert', 'Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1000);
            } else {
                showModalAlert('loginModalAlert', data.message, 'error');
            }
        })
        .catch(error => {
            hideSpinner(event.target);
            showModalAlert('loginModalAlert', 'An error occurred. Please try again.', 'error');
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
        showModalAlert('registerModalAlert', 'Passwords do not match', 'error');
        return;
    }

    showSpinner(event.target);

    // Get the base URL from the page
    const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.indexOf('/', 1));

    fetch(baseUrl + '/includes/ajax/register.php', {
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
                // Store email for verification
                const verifiedEmail = document.getElementById('verified_email');
                if (verifiedEmail) verifiedEmail.value = data.email || '';
                
                // Show email in verification step UI
                const emailDisplay = document.getElementById('verification-email');
                if (emailDisplay) emailDisplay.textContent = data.email || 'your email';
                
                // Move to OTP step (Step 4)
                currentStep = 4;
                updateFormStep();
                updateProgressIndicator();
                updateNavigationButtons();
                
                showModalAlert('registerModalAlert', data.message, 'success');
            } else {
                showModalAlert('registerModalAlert', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Registration error:', error);
            hideSpinner(event.target);
            showModalAlert('registerModalAlert', 'An error occurred. Please try again.', 'error');
        });
}

function verifyOTP() {
    const email = document.getElementById('verified_email').value;
    const otp = document.getElementById('regOTP').value;
    const verifyBtn = document.getElementById('verifyBtn');

    if (!otp || otp.length !== 6) {
        showModalAlert('registerModalAlert', 'Please enter a valid 6-digit verification code', 'error');
        return;
    }

    const originalText = verifyBtn.innerHTML;
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

    const formData = new FormData();
    formData.append('email', email);
    formData.append('otp', otp);

    const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.indexOf('/', 1));

    fetch(baseUrl + '/includes/ajax/verify-otp.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = originalText;

            if (data.success) {
                showModalAlert('registerModalAlert', data.message, 'success');
                setTimeout(() => {
                    location.reload(); 
                }, 2000);
            } else {
                showModalAlert('registerModalAlert', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Verification error:', error);
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = originalText;
            showModalAlert('registerModalAlert', 'An error occurred during verification. Please try again.', 'error');
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

    // Phone number validation (only if value is provided)
    if (name === 'phone' && value && !value.match(/^[\+]?[0-9\-\s\(\)]{10,}$/)) {
        showValidationError(input, 'Invalid phone format. Use: +1 (555) 123-4567 or 5551234567');
        return false;
    }

    // Email validation and uniqueness check
    if (name === 'email' && value) {
        if (!isValidEmail(value)) {
            showValidationError(input, 'Please enter a valid email address');
            return false;
        } else {
            // Check if email already exists via AJAX
            const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.indexOf('/', 1));
            fetch(`${baseUrl}/includes/ajax/check-exists.php?type=email&value=${encodeURIComponent(value)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        showValidationError(input, 'This email address is already in use');
                        input.dataset.exists = 'true';
                    } else {
                        input.dataset.exists = 'false';
                    }
                })
                .catch(err => console.error('Error checking email:', err));
        }
    }

    // Email and phone are optional - skip validation for these fields
    // Only validate if they have the 'required' attribute

    if (input.closest('#registerForm') && (type === 'password' || name === 'password' || name === 'confirm_password') && value) {
        if (value.length < 8) {
            showValidationError(input, 'Must be at least 8 characters');
            return false;
        } else if (!/[A-Z]/.test(value)) {
            showValidationError(input, 'Requires 1 uppercase letter');
            return false;
        } else if (!/[a-z]/.test(value)) {
            showValidationError(input, 'Requires 1 lowercase letter');
            return false;
        } else if (!/[^A-Za-z0-9]/.test(value)) {
            showValidationError(input, 'Requires 1 special character');
            return false;
        }
    }

    return true;
}

function showValidationError(input, message) {
    clearValidationError(input);
    input.classList.add('error');

    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message show';
    errorDiv.style.color = '#ef4444';
    errorDiv.style.fontSize = '0.85rem';
    errorDiv.style.marginTop = '0.5rem';
    errorDiv.style.display = 'block';

    // Add error icon and message
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i>${message}`;

    // Insert after the input group
    const inputGroup = input.closest('.input-group');
    if (inputGroup) {
        inputGroup.insertAdjacentElement('afterend', errorDiv);
    } else {
        input.insertAdjacentElement('afterend', errorDiv);
    }
}

function clearValidationError(input) {
    if (!input) return;

    input.classList.remove('error');

    // Look for error message after the input group
    const inputGroup = input.closest('.input-group');
    const parent = inputGroup || input;
    const existingError = parent.nextElementSibling;
    
    if (existingError && existingError.classList.contains('error-message')) {
        existingError.remove();
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

    // Define styles based on type
    const styles = {
        success: { bg: '#fff', border: '#10b981', icon: 'fa-check-circle', iconColor: '#10b981', text: '#064e3b' },
        error: { bg: '#fff', border: '#ef4444', icon: 'fa-exclamation-circle', iconColor: '#ef4444', text: '#7f1d1d' },
        info: { bg: '#fff', border: '#3b82f6', icon: 'fa-info-circle', iconColor: '#3b82f6', text: '#1e3a8a' },
        warning: { bg: '#fff', border: '#f59e0b', icon: 'fa-exclamation-triangle', iconColor: '#f59e0b', text: '#78350f' }
    };
    const s = styles[type] || styles.info;

    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-message`;
    Object.assign(alertDiv.style, {
        position: 'fixed', top: '90px', right: '20px', zIndex: '3000',
        minWidth: '320px', background: s.bg, borderLeft: `5px solid ${s.border}`,
        borderRadius: '8px', boxShadow: '0 10px 25px rgba(0,0,0,0.1)',
        padding: '16px 20px', display: 'flex', alignItems: 'center', gap: '15px',
        cursor: 'pointer', animation: 'slideInRight 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)',
        overflow: 'hidden'
    });

    // Inner HTML
    alertDiv.innerHTML = `
        <i class="fas ${s.icon}" style="font-size: 1.5rem; color: ${s.iconColor};"></i>
        <div style="flex: 1; color: ${s.text}; font-weight: 500; font-size: 0.95rem; line-height: 1.4;">${message}</div>
        <i class="fas fa-times" style="color: #9ca3af; font-size: 1rem; opacity: 0.7; transition: opacity 0.2s;"></i>
    `;

    // Add progress bar at bottom
    const progress = document.createElement('div');
    Object.assign(progress.style, {
        position: 'absolute', bottom: '0', left: '0', height: '3px', background: s.border,
        width: '100%', transition: 'width 5s linear'
    });
    alertDiv.appendChild(progress);

    document.body.appendChild(alertDiv);

    // Start progress bar shrink
    setTimeout(() => { progress.style.width = '0%'; }, 50);

    // Auto remove function
    const removeAlert = () => {
        alertDiv.style.animation = 'slideOutRight 0.3s ease-in forwards';
        setTimeout(() => alertDiv.remove(), 300);
    };

    // Auto remove after 5 seconds
    const timeout = setTimeout(removeAlert, 5000);

    // Click to dismiss
    alertDiv.addEventListener('click', () => {
        clearTimeout(timeout);
        removeAlert();
    });
}

// Modal Alert Function (displays alerts inside modals)
function showModalAlert(containerId, message, type = 'info') {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Set alert styling based on type
    const colors = {
        success: { bg: '#dcfce7', border: '#c3e6cb', text: '#155724' },
        error: { bg: '#f8d7da', border: '#f5c6cb', text: '#721c24' },
        info: { bg: '#dbeafe', border: '#bee5eb', text: '#0c5460' },
        warning: { bg: '#fff3cd', border: '#ffeeba', text: '#856404' }
    };

    const color = colors[type] || colors.info;

    // Build alert HTML
    container.innerHTML = `
        <div class="alert alert-${type}" style="
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid ${color.border};
            background-color: ${color.bg};
            color: ${color.text};
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        ">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;

    container.style.display = 'block';

    // Auto hide after 5 seconds for success messages
    if (type === 'success') {
        setTimeout(() => {
            container.style.display = 'none';
            container.innerHTML = '';
        }, 5000);
    }
}

// Clear modal alert
function clearModalAlert(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.style.display = 'none';
        container.innerHTML = '';
    }
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
        link.addEventListener('click', function (e) {
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
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                menu.classList.toggle('show');
            });
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (e) {
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


// Admin Sidebar Toggle Functionality
function initializeAdminSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    
    if (sidebar && toggle) {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
            
            // Change icon based on state
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('active');
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
    }
}

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
    isValidPhone,
    verifyOTP
};
