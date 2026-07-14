// Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initDashboardStats();
    initCharts();
    initSidebarToggle();
});

// Initialize Dashboard Stats
function initDashboardStats() {
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-info p');
    statNumbers.forEach(stat => {
        const target = parseInt(stat.textContent);
        if (target > 0) {
            animateNumber(stat, target);
        }
    });
}

// Animate Number
function animateNumber(element, target) {
    let current = 0;
    const increment = target / 50;
    const duration = 1000;
    const step = duration / 50;

    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, step);
}

// Initialize Charts
function initCharts() {
    // Bar Chart
    const barChart = document.querySelector('.bar-chart');
    if (barChart) {
        const bars = barChart.querySelectorAll('.bar');
        bars.forEach(bar => {
            const height = bar.style.height;
            bar.style.height = '0';
            setTimeout(() => {
                bar.style.height = height;
            }, 300);
        });
    }

    // Line Chart
    const lineChart = document.querySelector('.line-chart');
    if (lineChart) {
        const points = lineChart.querySelectorAll('.line-point');
        points.forEach(point => {
            const height = point.style.height;
            point.style.height = '0';
            setTimeout(() => {
                point.style.height = height;
            }, 300);
        });
    }
}

// Sidebar Toggle for Mobile
function initSidebarToggle() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }

    // Close sidebar on window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('show');
        }
    });
}

// Refresh Data
function refreshData() {
    showNotification('جاري تحديث البيانات...', 'info');
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Export
window.refreshData = refreshData;