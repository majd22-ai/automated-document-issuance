// Form Validation JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initFormValidation();
});

// Initialize Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });

        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            input.addEventListener('input', function() {
                if (this.dataset.validated === 'true') {
                    validateField(this);
                }
            });
        });
    });
}

// Validate Entire Form
function validateForm(form) {
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });

    return isValid;
}

// Validate Single Field
function validateField(input) {
    const value = input.value.trim();
    const type = input.type;
    const dataset = input.dataset;
    let isValid = true;
    let errorMessage = '';

    // Required validation
    if (input.hasAttribute('required') && !value) {
        isValid = false;
        errorMessage = 'هذا الحقل مطلوب';
    }

    // Email validation
    if (type === 'email' && value && !isValidEmail(value)) {
        isValid = false;
        errorMessage = 'البريد الإلكتروني غير صحيح';
    }

    // Phone validation
    if (type === 'tel' && value && !isValidPhone(value)) {
        isValid = false;
        errorMessage = 'رقم الجوال غير صحيح';
    }

    // Password validation
    if (type === 'password' && value && value.length < 8) {
        isValid = false;
        errorMessage = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
    }

    // Passport validation
    if (dataset.type === 'passport' && value && !isValidPassport(value)) {
        isValid = false;
        errorMessage = 'رقم جواز السفر غير صحيح';
    }

    // Date validation
    if (type === 'date' && value && !isValidDate(value)) {
        isValid = false;
        errorMessage = 'التاريخ غير صحيح';
    }

    // Show/hide error
    const errorElement = input.parentElement.querySelector('.field-error');
    if (errorElement) {
        errorElement.textContent = errorMessage;
        errorElement.style.display = errorMessage ? 'block' : 'none';
    }

    // Update input styling
    input.style.borderColor = isValid ? '' : '#dc3545';
    input.dataset.validated = 'true';

    return isValid;
}

// Validation Helpers
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function isValidPhone(phone) {
    const regex = /^[\+\d\s\-\(\)]{8,20}$/;
    return regex.test(phone);
}

function isValidPassport(passport) {
    const regex = /^[A-Za-z0-9]{6,9}$/;
    return regex.test(passport);
}

function isValidDate(date) {
    const d = new Date(date);
    return d instanceof Date && !isNaN(d);
}

function isValidNric(nric) {
    // Simplified NRIC validation (12 digits)
    const regex = /^\d{12}$/;
    return regex.test(nric);
}

// Confirm Password Validation
function confirmPassword(passwordInput, confirmInput) {
    if (passwordInput.value !== confirmInput.value) {
        confirmInput.style.borderColor = '#dc3545';
        const error = confirmInput.parentElement.querySelector('.field-error');
        if (error) {
            error.textContent = 'كلمات المرور غير متطابقة';
            error.style.display = 'block';
        }
        return false;
    }
    return true;
}

// Add error message container to fields
function addErrorContainer(input) {
    if (!input.parentElement.querySelector('.field-error')) {
        const error = document.createElement('span');
        error.className = 'field-error';
        error.style.cssText = `
            display: none;
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
        `;
        input.parentElement.appendChild(error);
    }
}

// Initialize validation helpers
document.querySelectorAll('input, select, textarea').forEach(input => {
    if (input.hasAttribute('required') || input.type === 'email' || input.type === 'tel') {
        addErrorContainer(input);
    }
});

// Export validation functions
window.validateForm = validateForm;
window.validateField = validateField;
window.confirmPassword = confirmPassword;
window.isValidEmail = isValidEmail;
window.isValidPhone = isValidPhone;
window.isValidPassport = isValidPassport;
window.isValidNric = isValidNric;