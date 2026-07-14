 // Appointment System JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initAppointmentSystem();
    loadAppointments();
});

// Initialize Appointment System
function initAppointmentSystem() {
    const appointmentForm = document.querySelector('.appointment-form');
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            bookAppointment(this);
        });
    }

    // Date picker min date (today)
    const dateInput = document.querySelector('input[type="date"]');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
    }
}

// Book Appointment
function bookAppointment(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    // Validation
    if (!data.service || !data.date || !data.time) {
        showNotification('الرجاء إكمال جميع الحقول المطلوبة', 'error');
        return;
    }

    // Check if date is in the past
    const selectedDate = new Date(data.date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate < today) {
        showNotification('لا يمكن حجز موعد في تاريخ سابق', 'error');
        return;
    }

    showNotification('جاري حجز الموعد...', 'info');
    
    setTimeout(() => {
        showNotification('تم حجز الموعد بنجاح!', 'success');
        // Add to upcoming appointments
        addAppointment(data);
        
        // Reset form
        form.reset();
    }, 1500);
}

// Load Appointments
function loadAppointments() {
    const appointments = [
        {
            service: 'إصدار بطاقة شخصية',
            date: '15/01/2024',
            time: '10:00 صباحاً',
            location: 'مصلحة الأحوال المدنية والسجل المدني',
            status: 'confirmed'
        },
        {
            service: 'تجديد البطاقة الشخصية',
            date: '22/01/2024',
            time: '02:00 مساءً',
            location: 'مصلحة الأحوال المدنية والسجل المدني',
            status: 'pending'
        }
    ];

    const appointmentList = document.querySelector('.appointment-list');
    if (appointmentList) {
        appointmentList.innerHTML = appointments.map(app => `
            <div class="appointment-item">
                <div class="appointment-date">
                    <span class="day">${app.date.split('/')[0]}</span>
                    <span class="month">${getMonthName(app.date)}</span>
                </div>
                <div class="appointment-info">
                    <h4>${app.service}</h4>
                    <p><i class="fas fa-clock"></i> ${app.time}</p>
                    <p><i class="fas fa-map-marker-alt"></i> ${app.location}</p>
                </div>
                <div class="appointment-status">
                    <span class="status ${app.status}">${getStatusText(app.status)}</span>
                </div>
            </div>
        `).join('');
    }
}

// Add Appointment
function addAppointment(data) {
    const appointmentList = document.querySelector('.appointment-list');
    if (appointmentList) {
        const dateParts = data.date.split('-');
        const formattedDate = `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
        
        const app = document.createElement('div');
        app.className = 'appointment-item';
        app.innerHTML = `
            <div class="appointment-date">
                <span class="day">${dateParts[2]}</span>
                <span class="month">${getMonthName(formattedDate)}</span>
            </div>
            <div class="appointment-info">
                <h4>${data.service}</h4>
                <p><i class="fas fa-clock"></i> ${data.time}</p>
                <p><i class="fas fa-map-marker-alt"></i> مصلحة الأحوال المدنية والسجل المدني</p>
            </div>
            <div class="appointment-status">
                <span class="status pending">قيد الانتظار</span>
            </div>
        `;
        appointmentList.prepend(app);
    }
}

// Get Month Name
function getMonthName(dateStr) {
    const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 
                    'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    const parts = dateStr.split('/');
    if (parts.length === 3) {
        const monthIndex = parseInt(parts[1]) - 1;
        return months[monthIndex] || parts[1];
    }
    return '';
}

// Cancel Appointment
function cancelAppointment(appId) {
    if (confirm('هل أنت متأكد من إلغاء هذا الموعد؟')) {
        showNotification('جاري إلغاء الموعد...', 'info');
        setTimeout(() => {
            showNotification('تم إلغاء الموعد بنجاح', 'success');
        }, 1000);
    }
}

// Reschedule Appointment
function rescheduleAppointment(appId) {
    showNotification('جاري إعادة جدولة الموعد...', 'info');
    setTimeout(() => {
        showNotification('تم إعادة جدولة الموعد بنجاح', 'success');
    }, 1000);
}

// Export
window.bookAppointment = bookAppointment;
window.cancelAppointment = cancelAppointment;
window.rescheduleAppointment = rescheduleAppointment;