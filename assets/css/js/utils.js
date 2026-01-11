// assets/js/utils.js

class UNEXCAUtils {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupGlobalListeners();
        this.setupAjaxCSRF();
    }
    
    setupGlobalListeners() {
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Prevent form resubmission on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    }
    
    setupAjaxCSRF() {
        // Setup CSRF token for AJAX requests
        $.ajaxSetup({
            beforeSend: function(xhr) {
                const token = document.querySelector('meta[name="csrf-token"]');
                if (token) {
                    xhr.setRequestHeader('X-CSRF-TOKEN', token.getAttribute('content'));
                }
            }
        });
    }
    
    // Form validation
    validateForm(formId, options = {}) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                this.showInputError(input, 'Este campo es requerido');
                isValid = false;
            } else if (input.type === 'email' && !this.validateEmail(input.value)) {
                this.showInputError(input, 'Por favor ingrese un email válido');
                isValid = false;
            } else if (input.type === 'number' && input.min && parseFloat(input.value) < parseFloat(input.min)) {
                this.showInputError(input, `El valor mínimo es ${input.min}`);
                isValid = false;
            } else if (input.type === 'number' && input.max && parseFloat(input.value) > parseFloat(input.max)) {
                this.showInputError(input, `El valor máximo es ${input.max}`);
                isValid = false;
            } else {
                this.clearInputError(input);
            }
        });
        
        return isValid;
    }
    
    showInputError(input, message) {
        const formGroup = input.closest('.form-group') || input.parentElement;
        let errorDiv = formGroup.querySelector('.invalid-feedback');
        
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            formGroup.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        input.classList.add('is-invalid');
    }
    
    clearInputError(input) {
        input.classList.remove('is-invalid');
        const formGroup = input.closest('.form-group') || input.parentElement;
        const errorDiv = formGroup.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Date utilities
    formatDate(date, format = 'dd/mm/yyyy') {
        if (!date) return '';
        
        const d = new Date(date);
        const day = d.getDate().toString().padStart(2, '0');
        const month = (d.getMonth() + 1).toString().padStart(2, '0');
        const year = d.getFullYear();
        
        switch (format) {
            case 'dd/mm/yyyy':
                return `${day}/${month}/${year}`;
            case 'mm/dd/yyyy':
                return `${month}/${day}/${year}`;
            case 'yyyy-mm-dd':
                return `${year}-${month}-${day}`;
            default:
                return `${day}/${month}/${year}`;
        }
    }
    
    getCurrentPeriod() {
        const now = new Date();
        const year = now.getFullYear();
        const semester = now.getMonth() < 6 ? 1 : 2;
        return `${year}-${semester}`;
    }
    
    // File utilities
    validateFile(input, options = {}) {
        const file = input.files[0];
        if (!file) return true;
        
        const { maxSize = 5, allowedTypes = [] } = options;
        const maxSizeBytes = maxSize * 1024 * 1024; // Convert MB to bytes
        
        if (file.size > maxSizeBytes) {
            this.showFileError(input, `El archivo no debe exceder ${maxSize}MB`);
            return false;
        }
        
        if (allowedTypes.length > 0 && !allowedTypes.includes(file.type)) {
            this.showFileError(input, `Tipo de archivo no permitido. Permitidos: ${allowedTypes.join(', ')}`);
            return false;
        }
        
        this.clearFileError(input);
        return true;
    }
    
    showFileError(input, message) {
        const formGroup = input.closest('.form-group') || input.parentElement;
        let errorDiv = formGroup.querySelector('.file-error');
        
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'file-error invalid-feedback';
            formGroup.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        input.classList.add('is-invalid');
    }
    
    clearFileError(input) {
        input.classList.remove('is-invalid');
        const formGroup = input.closest('.form-group') || input.parentElement;
        const errorDiv = formGroup.querySelector('.file-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    // Loading states
    showLoading(selector = 'body', text = 'Cargando...') {
        const element = document.querySelector(selector);
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'loading-overlay';
        loadingDiv.innerHTML = `
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <div class="loading-text">${text}</div>
            </div>
        `;
        element.appendChild(loadingDiv);
    }
    
    hideLoading(selector = 'body') {
        const element = document.querySelector(selector);
        const loadingDiv = element.querySelector('.loading-overlay');
        if (loadingDiv) {
            loadingDiv.remove();
        }
    }
    
    // Notification system
    showNotification(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="notification-icon fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Remove after duration
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, duration);
    }
    
    getNotificationIcon(type) {
        switch (type) {
            case 'success': return 'check-circle';
            case 'error': return 'exclamation-circle';
            case 'warning': return 'exclamation-triangle';
            case 'info': return 'info-circle';
            default: return 'info-circle';
        }
    }
    
    // Data export
    exportToCSV(data, filename = 'export.csv') {
        if (!data || data.length === 0) {
            this.showNotification('No hay datos para exportar', 'warning');
            return;
        }
        
        const headers = Object.keys(data[0]);
        const csvContent = [
            headers.join(','),
            ...data.map(row => headers.map(header => {
                const cell = row[header] === null || row[header] === undefined ? '' : row[header];
                return JSON.stringify(cell);
            }).join(','))
        ].join('\n');
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // Chart helpers
    createChart(ctx, type, data, options = {}) {
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        };
        
        const mergedOptions = { ...defaultOptions, ...options };
        
        return new Chart(ctx, {
            type: type,
            data: data,
            options: mergedOptions
        });
    }
    
    // Session management
    setSessionData(key, value) {
        try {
            sessionStorage.setItem(`unexca_${key}`, JSON.stringify(value));
        } catch (e) {
            console.error('Error saving to session storage:', e);
        }
    }
    
    getSessionData(key) {
        try {
            const data = sessionStorage.getItem(`unexca_${key}`);
            return data ? JSON.parse(data) : null;
        } catch (e) {
            console.error('Error reading from session storage:', e);
            return null;
        }
    }
    
    clearSessionData(key) {
        try {
            sessionStorage.removeItem(`unexca_${key}`);
        } catch (e) {
            console.error('Error clearing session storage:', e);
        }
    }
}

// Initialize utilities
document.addEventListener('DOMContentLoaded', function() {
    window.UNEXCA = window.UNEXCA || {};
    window.UNEXCA.Utils = new UNEXCAUtils();
});

// Notification styles
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    background: white;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transform: translateX(150%);
    transition: transform 0.3s ease;
    z-index: 9999;
    min-width: 300px;
    max-width: 400px;
}

.notification.show {
    transform: translateX(0);
}

.notification-success {
    border-left: 4px solid #28a745;
}

.notification-error {
    border-left: 4px solid #dc3545;
}

.notification-warning {
    border-left: 4px solid #ffc107;
}

.notification-info {
    border-left: 4px solid #17a2b8;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.notification-icon {
    font-size: 1.2rem;
}

.notification-success .notification-icon {
    color: #28a745;
}

.notification-error .notification-icon {
    color: #dc3545;
}

.notification-warning .notification-icon {
    color: #ffc107;
}

.notification-info .notification-icon {
    color: #17a2b8;
}

.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9998;
}

.loading-content {
    text-align: center;
}

.loading-spinner {
    display: inline-block;
    width: 3rem;
    height: 3rem;
    border: 0.25rem solid rgba(0, 86, 179, 0.2);
    border-radius: 50%;
    border-top-color: #0056b3;
    animation: spin 1s ease-in-out infinite;
    margin-bottom: 1rem;
}

.loading-text {
    color: #0056b3;
    font-weight: 500;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
`;

document.head.appendChild(notificationStyles);