// Payment System JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initPaymentSystem();
    loadPaymentHistory();
});

// Initialize Payment System
function initPaymentSystem() {
    // Payment method selection
    const methodCards = document.querySelectorAll('.method-card');
    methodCards.forEach(card => {
        card.addEventListener('click', function() {
            methodCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            showNotification('تم اختيار طريقة الدفع', 'info');
        });
    });

    // Pay now button
    const payBtn = document.querySelector('.btn-pay');
    if (payBtn) {
        payBtn.addEventListener('click', function(e) {
            e.preventDefault();
            processPayment();
        });
    }
}

// Load Payment History
function loadPaymentHistory() {
    const payments = [
        {
            invoice: '#INV-2024-001',
            service: 'إصدار بطاقة شخصية',
            amount: 150.00,
            date: '01/01/2024',
            status: 'completed'
        },
        {
            invoice: '#INV-2024-002',
            service: 'تجديد البطاقة',
            amount: 100.00,
            date: '05/01/2024',
            status: 'pending'
        },
        {
            invoice: '#INV-2024-003',
            service: 'تمديد تأشيرة الإقامة',
            amount: 200.00,
            date: '10/01/2024',
            status: 'completed'
        }
    ];

    const tableBody = document.querySelector('.payment-history tbody');
    if (tableBody) {
        tableBody.innerHTML = payments.map(payment => `
            <tr>
                <td>${payment.invoice}</td>
                <td>${payment.service}</td>
                <td>RM ${payment.amount.toFixed(2)}</td>
                <td>${payment.date}</td>
                <td><span class="status ${payment.status}">${getStatusText(payment.status)}</span></td>
                <td>
                    ${payment.status === 'pending' 
                        ? `<a href="#" class="btn btn-sm btn-primary btn-pay">دفع الآن</a>` 
                        : `<a href="#" class="btn btn-sm">عرض</a>`}
                </td>
            </tr>
        `).join('');

        // Reinitialize payment buttons
        const payButtons = document.querySelectorAll('.btn-pay');
        payButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                processPayment();
            });
        });
    }
}

// Process Payment
function processPayment() {
    const selectedMethod = document.querySelector('.method-card.selected');
    
    if (!selectedMethod) {
        showNotification('الرجاء اختيار طريقة الدفع أولاً', 'error');
        return;
    }

    const amount = document.querySelector('.payment-amount')?.textContent || 'RM 100.00';
    
    showNotification(`جاري معالجة الدفع بقيمة ${amount}...`, 'info');
    
    // Simulate payment processing
    setTimeout(() => {
        showNotification('تمت عملية الدفع بنجاح!', 'success');
        
        // Update payment status
        const pendingPayments = document.querySelectorAll('.status.pending');
        pendingPayments.forEach(status => {
            const row = status.closest('tr');
            if (row) {
                const statusCell = row.querySelector('.status');
                if (statusCell) {
                    statusCell.className = 'status completed';
                    statusCell.textContent = 'مدفوع';
                }
                const actionCell = row.querySelector('td:last-child');
                if (actionCell) {
                    actionCell.innerHTML = '<a href="#" class="btn btn-sm">عرض</a>';
                }
            }
        });

        // Update summary
        updatePaymentSummary();
    }, 2000);
}

// Update Payment Summary
function updatePaymentSummary() {
    const summary = document.querySelector('.payment-summary');
    if (summary) {
        const amounts = {
            total: 450.00,
            paid: 300.00,
            pending: 150.00
        };

        // Update summary cards
        const cards = summary.querySelectorAll('.summary-card .amount');
        if (cards.length >= 3) {
            cards[0].textContent = `RM ${amounts.total.toFixed(2)}`;
            cards[1].textContent = `RM ${amounts.pending.toFixed(2)}`;
            cards[2].textContent = `RM ${amounts.paid.toFixed(2)}`;
        }
    }
}

// Export
window.processPayment = processPayment;