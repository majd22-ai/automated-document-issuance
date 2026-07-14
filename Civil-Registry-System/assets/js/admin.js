// Admin Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initAdminDashboard();
    loadAdminStats();
});

// Initialize Admin Dashboard
function initAdminDashboard() {
    // Load admin info
    const adminName = document.querySelector('.user-info span');
    if (adminName) {
        adminName.textContent = 'مدير النظام';
    }

    // Initialize settings tabs
    initSettingsTabs();
}

// Load Admin Stats
function loadAdminStats() {
    const stats = {
        users: 1245,
        applications: 2340,
        revenue: 45670,
        pending: 23
    };

    const statElements = document.querySelectorAll('.stat-info p');
    if (statElements.length >= 4) {
        statElements[0].textContent = stats.users.toLocaleString();
        statElements[1].textContent = stats.applications.toLocaleString();
        statElements[2].textContent = `RM ${stats.revenue.toLocaleString()}`;
        statElements[3].textContent = stats.pending;
    }
}

// Initialize Settings Tabs
function initSettingsTabs() {
    const tabs = document.querySelectorAll('.settings-sidebar ul li');
    const sections = document.querySelectorAll('.settings-section');

    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Hide all sections
            sections.forEach(s => s.classList.remove('active'));

            // Show selected section
            const targetId = this.querySelector('a')?.getAttribute('href')?.replace('#', '');
            if (targetId) {
                const targetSection = document.getElementById(targetId);
                if (targetSection) {
                    targetSection.classList.add('active');
                }
            }
        });
    });

    // Show first section by default
    const firstSection = document.querySelector('.settings-section');
    if (firstSection) firstSection.classList.add('active');
}

// System Settings Functions
function saveSettings() {
    showNotification('جاري حفظ الإعدادات...', 'info');
    setTimeout(() => {
        showNotification('تم حفظ الإعدادات بنجاح', 'success');
    }, 1500);
}

function createBackup() {
    showNotification('جاري إنشاء نسخة احتياطية...', 'info');
    setTimeout(() => {
        showNotification('تم إنشاء النسخة الاحتياطية بنجاح', 'success');
    }, 2000);
}

// User Management
function deleteUser(userId) {
    if (confirm(`هل أنت متأكد من حذف المستخدم ${userId}؟`)) {
        showNotification('جاري حذف المستخدم...', 'info');
        setTimeout(() => {
            showNotification('تم حذف المستخدم بنجاح', 'success');
        }, 1500);
    }
}

function toggleUserStatus(userId) {
    showNotification('جاري تغيير حالة المستخدم...', 'info');
    setTimeout(() => {
        showNotification('تم تغيير حالة المستخدم بنجاح', 'success');
    }, 1000);
}

// Service Management
function toggleService(serviceId) {
    showNotification('جاري تغيير حالة الخدمة...', 'info');
    setTimeout(() => {
        showNotification('تم تغيير حالة الخدمة بنجاح', 'success');
    }, 1000);
}

// Export
window.saveSettings = saveSettings;
window.createBackup = createBackup;
window.deleteUser = deleteUser;
window.toggleUserStatus = toggleUserStatus;
window.toggleService = toggleService;