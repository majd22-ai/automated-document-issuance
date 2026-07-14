// Notification System JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initNotificationSystem();
    loadNotifications();
});

// Initialize Notification System
function initNotificationSystem() {
    // Mark all as read
    const markAllBtn = document.querySelector('.btn-mark-all-read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            markAllNotificationsAsRead();
        });
    }

    // Individual notification click
    const notifications = document.querySelectorAll('.notification-item');
    notifications.forEach(notif => {
        notif.addEventListener('click', function(e) {
            // Don't trigger on action buttons
            if (e.target.closest('.notification-action') || e.target.closest('.close-btn')) {
                return;
            }
            this.classList.remove('unread');
        });

        // Close button
        const closeBtn = notif.querySelector('.notification-action, .close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const item = this.closest('.notification-item');
                item.style.transition = 'all 0.3s';
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                setTimeout(() => item.remove(), 300);
            });
        }
    });

    // Notification filters
    const filterBtns = document.querySelectorAll('.notification-filters .btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.textContent.trim();
            filterNotifications(filter);
        });
    });
}

// Load Notifications
function loadNotifications() {
    // Simulate loading notifications
    const notifications = [
        {
            id: 1,
            icon: 'fas fa-check-circle',
            color: '#28a745',
            title: 'تمت الموافقة على طلبك',
            message: 'تمت الموافقة على طلب إصدار البطاقة الشخصية رقم #APP-2024-001',
            time: 'منذ 5 دقائق',
            unread: true
        },
        {
            id: 2,
            icon: 'fas fa-credit-card',
            color: '#007bff',
            title: 'تذكير بالدفع',
            message: 'يرجى دفع رسوم تجديد البطاقة رقم #APP-2024-002 قبل 15/01/2024',
            time: 'منذ 2 ساعة',
            unread: true
        },
        {
            id: 3,
            icon: 'fas fa-calendar-check',
            color: '#17a2b8',
            title: 'تأكيد حجز الموعد',
            message: 'تم تأكيد حجز موعدك يوم 15 يناير 2024 الساعة 10:00 صباحاً',
            time: 'منذ يوم',
            unread: false
        }
    ];

    const notificationList = document.querySelector('.notification-list');
    if (notificationList) {
        notificationList.innerHTML = notifications.map(notif => `
            <div class="notification-item ${notif.unread ? 'unread' : ''}">
                <div class="notification-icon">
                    <i class="${notif.icon}" style="color: ${notif.color};"></i>
                </div>
                <div class="notification-content">
                    <h4>${notif.title}</h4>
                    <p>${notif.message}</p>
                    <span class="notification-time">${notif.time}</span>
                </div>
                <button class="notification-action"><i class="fas fa-times"></i></button>
            </div>
        `).join('');

        // Reinitialize event listeners
        initNotificationSystem();
    }
}

// Mark All Notifications as Read
function markAllNotificationsAsRead() {
    const notifications = document.querySelectorAll('.notification-item');
    notifications.forEach(notif => {
        notif.classList.remove('unread');
    });
    showNotification('تم تحديد جميع الإشعارات كمقروءة', 'success');

    // Update badge
    updateNotificationBadge(0);
}

// Filter Notifications
function filterNotifications(filter) {
    const items = document.querySelectorAll('.notification-item');
    items.forEach(item => {
        switch(filter) {
            case 'غير مقروءة':
                item.style.display = item.classList.contains('unread') ? 'flex' : 'none';
                break;
            case 'مقروءة':
                item.style.display = !item.classList.contains('unread') ? 'flex' : 'none';
                break;
            default:
                item.style.display = 'flex';
        }
    });
}

// Update Notification Badge
function updateNotificationBadge(count) {
    const badge = document.querySelector('.notifications .badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Send Notification
function sendNotification(title, message, type = 'info') {
    showNotification('جاري إرسال الإشعار...', 'info');
    setTimeout(() => {
        showNotification('تم إرسال الإشعار بنجاح', 'success');
        
        // Add to notification list
        const notificationList = document.querySelector('.notification-list');
        if (notificationList) {
            const notif = document.createElement('div');
            notif.className = 'notification-item unread';
            notif.innerHTML = `
                <div class="notification-icon">
                    <i class="fas fa-bullhorn" style="color: #007bff;"></i>
                </div>
                <div class="notification-content">
                    <h4>${title}</h4>
                    <p>${message}</p>
                    <span class="notification-time">الآن</span>
                </div>
                <button class="notification-action"><i class="fas fa-times"></i></button>
            `;
            notificationList.prepend(notif);
            
            // Update badge
            const badge = document.querySelector('.notifications .badge');
            if (badge) {
                const currentCount = parseInt(badge.textContent) || 0;
                badge.textContent = currentCount + 1;
            }
        }
    }, 1000);
}

// Export
window.markAllNotificationsAsRead = markAllNotificationsAsRead;
window.sendNotification = sendNotification;