// Document Upload JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initFileUpload();
    initOCR();
});

// Initialize File Upload
function initFileUpload() {
    const fileInput = document.getElementById('fileInput');
    const uploadBox = document.querySelector('.upload-box');
    const fileList = document.querySelector('.uploaded-files');

    if (fileInput) {
        // File selection
        fileInput.addEventListener('change', function(e) {
            handleFiles(this.files);
        });

        // Drag and drop
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
                    fileInput.files = files;
                    handleFiles(files);
                }
            });

            uploadBox.addEventListener('click', function() {
                fileInput.click();
            });
        }
    }
}

// Handle Files
function handleFiles(files) {
    const validTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    const maxSize = 5 * 1024 * 1024; // 5MB
    const uploadedFiles = document.querySelector('.uploaded-files');

    if (!uploadedFiles) return;

    for (let file of files) {
        // Validate file type
        if (!validTypes.includes(file.type)) {
            showNotification(`الملف ${file.name} غير مدعوم`, 'error');
            continue;
        }

        // Validate file size
        if (file.size > maxSize) {
            showNotification(`الملف ${file.name} يتجاوز الحد المسموح (5MB)`, 'error');
            continue;
        }

        // Add file to list
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        
        const fileIcon = file.type.includes('pdf') ? 'fa-file-pdf' : 'fa-file-image';
        const iconColor = file.type.includes('pdf') ? '#dc3545' : '#17a2b8';
        const fileSize = (file.size / 1024 / 1024).toFixed(2);

        fileItem.innerHTML = `
            <div class="file-info">
                <i class="fas ${fileIcon}" style="color: ${iconColor};"></i>
                <span>${file.name}</span>
                <small>${fileSize} MB</small>
            </div>
            <div class="file-status">
                <span class="status pending">جاري الرفع...</span>
                <button class="btn btn-sm btn-danger remove-file"><i class="fas fa-trash"></i></button>
            </div>
        `;

        uploadedFiles.appendChild(fileItem);

        // Simulate upload
        simulateUpload(fileItem);
    }
}

// Simulate Upload
function simulateUpload(fileItem) {
    const status = fileItem.querySelector('.status');
    let progress = 0;

    const interval = setInterval(() => {
        progress += Math.random() * 20;
        if (progress >= 100) {
            progress = 100;
            clearInterval(interval);
            status.className = 'status completed';
            status.textContent = 'مرفوع';
            
            // Perform OCR on image files
            const fileInfo = fileItem.querySelector('.file-info');
            if (fileInfo) {
                const fileName = fileInfo.querySelector('span')?.textContent || '';
                if (fileName.match(/\.(jpg|jpeg|png)$/i)) {
                    performOCR(fileName);
                }
            }

            showNotification('تم رفع الملف بنجاح', 'success');
        }
        status.textContent = `جاري الرفع ${Math.round(progress)}%`;
    }, 300);

    // Remove file button
    const removeBtn = fileItem.querySelector('.remove-file');
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            clearInterval(interval);
            fileItem.remove();
            showNotification('تم إلغاء رفع الملف', 'info');
        });
    }
}

// OCR Simulation
function initOCR() {
    // OCR is initialized on file upload
}

function performOCR(fileName) {
    // Simulate OCR processing
    showNotification(`جاري استخراج البيانات من ${fileName}...`, 'info');
    
    setTimeout(() => {
        // Simulate extracted data
        const extractedData = {
            name: 'أحمد محمد عبدالله',
            passport: 'A12345678',
            dob: '15/05/1990',
            nationality: 'يمني'
        };

        // Fill form fields with extracted data
        const form = document.querySelector('.service-form');
        if (form) {
            const nameInput = form.querySelector('input[placeholder*="الاسم"]');
            if (nameInput) nameInput.value = extractedData.name;

            const passportInput = form.querySelector('input[placeholder*="جواز"]');
            if (passportInput) passportInput.value = extractedData.passport;

            showNotification('تم استخراج البيانات بنجاح! يرجى مراجعة الحقول.', 'success');
        }
    }, 2000);
}

// Validate Documents Before Submit
function validateDocuments() {
    const fileItems = document.querySelectorAll('.file-item');
    const uploadedFiles = document.querySelectorAll('.file-item .status.completed');

    if (fileItems.length === 0) {
        showNotification('الرجاء رفع المستندات المطلوبة', 'error');
        return false;
    }

    if (uploadedFiles.length < fileItems.length) {
        showNotification('الرجاء الانتظار حتى اكتمال رفع جميع الملفات', 'error');
        return false;
    }

    return true;
}

// Export
window.validateDocuments = validateDocuments;
window.performOCR = performOCR;