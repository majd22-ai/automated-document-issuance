// Citizen Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initCitizenDashboard();
    initApplicationTracking();
    initDocumentUpload();
});

// Initialize Citizen Dashboard
function initCitizenDashboard() {
    // Load user data
    loadUserProfile();
    
    // Load recent applications
    loadRecentApplications();
    
    // Load notifications
    loadNotifications();
}

// Load User Profile
function loadUserProfile() {
    // Simulate loading user data
    const userData = {
        name: 'أحمد محمد عبدالله',
        email: 'ahmed@email.com',
        phone: '+60123456789',
        passport: 'A12345678'
    };

    // Update profile info
    const profileName = document.querySelector('.profile-info h2');
    if (profileName) profileName.textContent = userData.name;

    const userInfo = document.querySelector('.user-info span');
    if (userInfo) userInfo.textContent = userData.name;

    // Update form fields if they exist
    const nameFields = document.querySelectorAll('input[placeholder*="الاسم"]');
    if (nameFields.length > 0) {
        nameFields[0].value = userData.name;
    }

    const emailFields = document.querySelectorAll('input[type="email"]');
    if (emailFields.length > 0) {
        emailFields[0].value = userData.email;
    }

    const phoneFields = document.querySelectorAll('input[type="tel"]');
    if (phoneFields.length > 0) {
        phoneFields[0].value = userData.phone;
    }
}

// Load Recent Applications
function loadRecentApplications() {
    const applications = [
        {
            id: '#APP-2024-001',
            service: 'إصدار بطاقة شخصية',
            date: '01/01/2024',
            status: 'completed'
        },
        {
            id: '#APP-2024-002',
            service: 'تجديد البطاقة',
            date: '05/01/2024',
            status: 'in-progress'
        },
        {
            id: '#APP-2024-003',
            service: 'إصدار بطاقة شخصية',
            date: '10/01/2024',
            status: 'rejected'
        }
    ];

    const tableBody = document.querySelector('.recent-applications tbody');
    if (tableBody) {
        tableBody.innerHTML = applications.map(app => `
            <tr>
                <td>${app.id}</td>
                <td>${app.service}</td>
                <td>${app.date}</td>
                <td><span class="status ${app.status}">${getStatusText(app.status)}</span></td>
                <td><a href="track-application.html" class="btn btn-sm btn-primary">عرض</a></td>
            </tr>
        `).join('');
    }
}

// Load Notifications
function loadNotifications() {
    const notificationBadge = document.querySelector('.notifications .badge');
    if (notificationBadge) {
        notificationBadge.textContent = '3';
    }
}

// Application Tracking
function initApplicationTracking() {
    const trackForm = document.querySelector('.track-form');
    if (trackForm) {
        trackForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const trackingId = this.querySelector('input')?.value;
            if (trackingId) {
                showNotification(`جاري البحث عن الطلب ${trackingId}...`, 'info');
                setTimeout(() => {
                    showNotification('تم العثور على الطلب', 'success');
                }, 1000);
            }
        });
    }
}

// Document Upload
function initDocumentUpload() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const files = this.files;
            const uploadedFiles = document.querySelector('.uploaded-files');
            
            if (uploadedFiles && files.length > 0) {
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.innerHTML = `
                        <div class="file-info">
                            <i class="fas fa-file-${file.type.includes('pdf') ? 'pdf' : 'image'}" 
                               style="color: ${file.type.includes('pdf') ? '#dc3545' : '#17a2b8'};"></i>
                            <span>${file.name}</span>
                            <small>${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                        </div>
                        <div class="file-status">
                            <span class="status completed">مرفوع</span>
                            <button class="btn btn-sm btn-danger" onclick="this.closest('.file-item').remove()">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    uploadedFiles.appendChild(fileItem);
                }
                showNotification(`تم رفع ${files.length} ملف بنجاح`, 'success');
            }
        });
    }

    // Drag and drop
    const uploadBox = document.querySelector('.upload-box');
    if (uploadBox) {
        uploadBox.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--primary-teal)';
            this.style.background = 'rgba(52, 176, 190, 0.05)';
        });

        uploadBox.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ddd';
            this.style.background = 'transparent';
        });

        uploadBox.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ddd';
            this.style.background = 'transparent';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('fileInput');
                if (fileInput) {
                    fileInput.files = files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            }
        });

        uploadBox.addEventListener('click', function() {
            const fileInput = document.getElementById('fileInput');
            if (fileInput) fileInput.click();
        });
    }
}

// Submit Application
function submitApplication() {
    showNotification('جاري إرسال الطلب...', 'info');
    setTimeout(() => {
        showNotification('تم إرسال الطلب بنجاح! سيتم مراجعته قريباً.', 'success');
        setTimeout(() => {
            window.location.href = 'dashboard.html';
        }, 2000);
    }, 1500);
}

// Cancel Application
function cancelApplication() {
    if (confirm('هل أنت متأكد من إلغاء هذا الطلب؟')) {
        showNotification('تم إلغاء الطلب', 'info');
        setTimeout(() => {
            window.location.href = 'dashboard.html';
        }, 1000);
    }
}

// Export
window.submitApplication = submitApplication;
window.cancelApplication = cancelApplication;