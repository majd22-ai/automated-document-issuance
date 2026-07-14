// Employee Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initEmployeeDashboard();
    loadPendingApplications();
});

// Initialize Employee Dashboard
function initEmployeeDashboard() {
    // Load employee info
    const employeeName = document.querySelector('.user-info span');
    if (employeeName) {
        employeeName.textContent = 'موظف الأحوال المدنية';
    }

    // Update stats
    const stats = {
        pending: 12,
        approved: 45,
        rejected: 8,
        total: 65
    };

    const statElements = document.querySelectorAll('.stat-info p');
    if (statElements.length >= 4) {
        statElements[0].textContent = stats.pending;
        statElements[1].textContent = stats.approved;
        statElements[2].textContent = stats.rejected;
        statElements[3].textContent = stats.total;
    }
}

// Load Pending Applications
function loadPendingApplications() {
    const applications = [
        {
            id: '#APP-2024-001',
            name: 'أحمد محمد عبدالله',
            service: 'إصدار بطاقة شخصية',
            date: '01/01/2024'
        },
        {
            id: '#APP-2024-002',
            name: 'سارة علي حسين',
            service: 'تجديد البطاقة',
            date: '05/01/2024'
        },
        {
            id: '#APP-2024-003',
            name: 'محمد حسن علي',
            service: 'إصدار بطاقة شخصية',
            date: '10/01/2024'
        }
    ];

    const tableBody = document.querySelector('.recent-applications tbody');
    if (tableBody) {
        tableBody.innerHTML = applications.map(app => `
            <tr>
                <td>${app.id}</td>
                <td>${app.name}</td>
                <td>${app.service}</td>
                <td>${app.date}</td>
                <td>
                    <a href="application-details.html" class="btn btn-sm btn-primary">مراجعة</a>
                </td>
            </tr>
        `).join('');
    }
}

// Approve Application
function approveApplication(appId) {
    if (confirm(`هل أنت متأكد من اعتماد الطلب ${appId}؟`)) {
        showNotification('جاري اعتماد الطلب...', 'info');
        setTimeout(() => {
            showNotification(`تم اعتماد الطلب ${appId} بنجاح`, 'success');
        }, 1500);
    }
}

// Reject Application
function rejectApplication(appId) {
    const reason = prompt('الرجاء إدخال سبب رفض الطلب:');
    if (reason !== null) {
        showNotification('جاري رفض الطلب...', 'info');
        setTimeout(() => {
            showNotification(`تم رفض الطلب ${appId}`, 'error');
        }, 1500);
    }
}

// Request Edit
function requestEdit(appId) {
    const notes = prompt('الرجاء إدخال التعديلات المطلوبة من المواطن:');
    if (notes !== null) {
        showNotification('جاري إرسال طلب التعديل...', 'info');
        setTimeout(() => {
            showNotification(`تم إرسال طلب التعديل للطلب ${appId}`, 'success');
        }, 1500);
    }
}

// Export
window.approveApplication = approveApplication;
window.rejectApplication = rejectApplication;
window.requestEdit = requestEdit;