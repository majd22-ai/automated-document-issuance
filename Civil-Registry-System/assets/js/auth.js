// Authentication JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initLoginForm();
    initRegisterForm();
    initLogout();
    checkAuthStatus();
});

// Login Form Handler
function initLoginForm() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username')?.value;
            const password = document.getElementById('password')?.value;

            if (!username || !password) {
                showNotification('يرجى إدخال اسم المستخدم وكلمة المرور', 'error');
                return;
            }

            // Simulate login
            loginUser(username, password);
        });
    }
}

// Register Form Handler
function initRegisterForm() {
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            // Basic validation
            if (data.password !== data['confirm-password']) {
                showNotification('كلمة المرور وتأكيدها غير متطابقين', 'error');
                return;
            }

            if (data.password.length < 8) {
                showNotification('كلمة المرور يجب أن تكون 8 أحرف على الأقل', 'error');
                return;
            }

            // Simulate registration
            registerUser(data);
        });
    }
}

// Login Function
function loginUser(username, password) {
    // Simulate API call
    showNotification('جاري تسجيل الدخول...', 'info');
    
    setTimeout(() => {
        // For demo purposes - check if user type
        if (username === 'admin' && password === 'admin') {
            showNotification('تم تسجيل الدخول بنجاح كمدير', 'success');
            window.location.href = 'admin/dashboard.html';
        } else if (username === 'employee' && password === 'employee') {
            showNotification('تم تسجيل الدخول بنجاح كموظف', 'success');
            window.location.href = 'employee/dashboard.html';
        } else if (username && password) {
            showNotification('تم تسجيل الدخول بنجاح', 'success');
            // Check if on auth page, redirect to citizen dashboard
            if (window.location.pathname.includes('login.html') || 
                window.location.pathname.includes('register.html')) {
                window.location.href = 'citizen/dashboard.html';
            }
        } else {
            showNotification('بيانات الدخول غير صحيحة', 'error');
        }
    }, 1500);
}

// Register Function
function registerUser(data) {
    showNotification('جاري إنشاء الحساب...', 'info');
    
    setTimeout(() => {
        showNotification('تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.', 'success');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 2000);
    }, 1500);
}

// Logout Function
function initLogout() {
    const logoutLinks = document.querySelectorAll('a[href*="logout"]');
    logoutLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });
    });
}

function logout() {
    // Clear session storage
    sessionStorage.removeItem('user');
    sessionStorage.removeItem('token');
    
    showNotification('تم تسجيل الخروج بنجاح', 'info');
    setTimeout(() => {
        window.location.href = '../index.html';
    }, 1000);
}

// Check Auth Status
function checkAuthStatus() {
    const token = sessionStorage.getItem('token');
    const currentPage = window.location.pathname;
    
    // If on protected page and no token, redirect to login
    const protectedPaths = ['/citizen/', '/employee/', '/admin/'];
    const isProtected = protectedPaths.some(path => currentPage.includes(path));
    
    if (isProtected && !token) {
        // Don't redirect if already on login page
        if (!currentPage.includes('login.html')) {
            showNotification('يرجى تسجيل الدخول أولاً', 'error');
            setTimeout(() => {
                window.location.href = '../login.html';
            }, 1500);
        }
    }
}

// Password Toggle
function togglePassword() {
    const passwordInput = document.querySelector('.auth-form input[type="password"]');
    const toggleIcon = document.querySelector('.toggle-password i');
    
    if (passwordInput) {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            if (toggleIcon) toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            if (toggleIcon) toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
}

// Export
window.togglePassword = togglePassword;
window.loginUser = loginUser;
window.logout = logout;