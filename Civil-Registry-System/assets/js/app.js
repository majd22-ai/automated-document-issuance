// Main Application JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initMobileMenu();
    initDropdowns();
    initNotifications();
    initFormValidation();
});

// Mobile Menu Toggle
function initMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const navList = document.querySelector('.nav-list');
    const sidebarToggle = document.querySelector('.sidebar-toggle');

    if (menuToggle && navList) {
        menuToggle.addEventListener('click', function() {
            navList.classList.toggle('show');
        });
    }

    // Close menu on outside click
    document.addEventListener('click', function(e) {
        if (navList && !navList.contains(e.target) && !menuToggle.contains(e.target)) {
            navList.classList.remove('show');
        }
    });
}

// Dropdown Menu Toggle
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');

    dropdowns.forEach(dropdown => {
        const link = dropdown.querySelector('a');
        const menu = dropdown.querySelector('.dropdown-menu');

        if (link && menu) {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href') === '#' || window.innerWidth <= 992) {
                    e.preventDefault();
                    dropdowns.forEach(d => {
                        if (d !== dropdown) {
                            d.classList.remove('open');
                            const m = d.querySelector('.dropdown-menu');
                            if (m && window.innerWidth <= 992) m.style.display = 'none';
                        }
                    });
                    dropdown.classList.toggle('open');
                    if (window.innerWidth <= 992) {
                        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                    }
                }
            });
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            dropdowns.forEach(d => {
                d.classList.remove('open');
                const m = d.querySelector('.dropdown-menu');
                if (m && window.innerWidth <= 992) m.style.display = 'none';
            });
        }
    });
}

// Notification System
function initNotifications() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Notification click handler
    const notifications = document.querySelectorAll('.notification-item');
    notifications.forEach(notif => {
        notif.addEventListener('click', function() {
            this.classList.remove('unread');
        });
    });
}

// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#dc3545';
            isValid = false;
        } else {
            input.style.borderColor = '';
        }

        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                input.style.borderColor = '#dc3545';
                isValid = false;
            }
        }

        // Password validation
        if (input.type === 'password' && input.value && input.value.length < 8) {
            input.style.borderColor = '#dc3545';
            isValid = false;
        }
    });

    return isValid;
}

// Utility Functions
function showNotification(message, type = 'info') {
    const container = document.querySelector('.notification-container') || createNotificationContainer();
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
        <button class="close-btn"><i class="fas fa-times"></i></button>
    `;
    
    container.appendChild(notification);

    setTimeout(() => {
        notification.classList.add('show');
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }, 100);

    const closeBtn = notification.querySelector('.close-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        });
    }
}

function createNotificationContainer() {
    const container = document.createElement('div');
    container.className = 'notification-container';
    container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 400px;
    `;
    document.body.appendChild(container);
    return container;
}

// Date formatting
function formatDate(date) {
    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatDateShort(date) {
    return new Date(date).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Number formatting
function formatCurrency(amount) {
    return `RM ${parseFloat(amount).toFixed(2)}`;
}

// Generate random ID
function generateId() {
    return '#' + Math.random().toString(36).substr(2, 9).toUpperCase();
}

// Track application status
const statusMap = {
    'pending': 'قيد الانتظار',
    'in-progress': 'قيد المعالجة',
    'completed': 'مكتمل',
    'approved': 'معتمد',
    'rejected': 'مرفوض',
    'confirmed': 'مؤكد',
    'active': 'نشط',
    'inactive': 'غير نشط'
};

function getStatusText(status) {
    return statusMap[status] || status;
}

// Export for use in other files
window.showNotification = showNotification;
window.formatDate = formatDate;
window.formatDateShort = formatDateShort;
window.formatCurrency = formatCurrency;
window.generateId = generateId;
window.getStatusText = getStatusText;