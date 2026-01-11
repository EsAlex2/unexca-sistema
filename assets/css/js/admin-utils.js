// assets/js/admin-utils.js

class AdminUtils {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupAdminListeners();
        this.setupDataExport();
        this.setupBulkActions();
    }
    
    setupAdminListeners() {
        // Auto-refresh datos en tiempo real
        this.setupAutoRefresh();
        
        // Validación de formularios administrativos
        this.setupFormValidation();
        
        // Tooltips y popovers
        this.setupTooltips();
    }
    
    setupAutoRefresh() {
        // Auto-refresh cada 2 minutos en páginas que requieren datos en tiempo real
        if (document.querySelector('[data-auto-refresh]')) {
            setInterval(() => {
                this.refreshDashboardData();
            }, 120000);
        }
    }
    
    async refreshDashboardData() {
        try {
            const response = await fetch('ajax/refresh_dashboard.php');
            const data = await response.json();
            
            if (data.success) {
                // Actualizar widgets específicos
                this.updateWidgets(data.widgets);
                
                // Mostrar notificación si hay alertas nuevas
                if (data.alerts && data.alerts.length > 0) {
                    this.showNewAlerts(data.alerts);
                }
            }
        } catch (error) {
            console.error('Error refreshing dashboard:', error);
        }
    }
    
    updateWidgets(widgets) {
        Object.keys(widgets).forEach(widgetId => {
            const widget = document.getElementById(widgetId);
            if (widget) {
                const valueElement = widget.querySelector('.widget-value');
                if (valueElement) {
                    const oldValue = parseInt(valueElement.textContent.replace(/,/g, ''));
                    const newValue = widgets[widgetId];
                    
                    valueElement.textContent = newValue.toLocaleString();
                    
                    // Animación de cambio
                    if (newValue > oldValue) {
                        this.animateValueChange(widget, 'increase');
                    } else if (newValue < oldValue) {
                        this.animateValueChange(widget, 'decrease');
                    }
                }
            }
        });
    }
    
    animateValueChange(element, type) {
        element.classList.add(`value-${type}`);
        setTimeout(() => {
            element.classList.remove(`value-${type}`);
        }, 2000);
    }
    
    showNewAlerts(alerts) {
        alerts.forEach(alert => {
            const notification = document.createElement('div');
            notification.className = `notification notification-${alert.type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${this.getAlertIcon(alert.type)}"></i>
                    <div>
                        <strong>${alert.title}</strong>
                        <p>${alert.message}</p>
                    </div>
                </div>
            `;
            
            document.querySelector('.notification-container').appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        });
    }
    
    getAlertIcon(type) {
        const icons = {
            'success': 'check-circle',
            'warning': 'exclamation-triangle',
            'danger': 'exclamation-circle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    setupFormValidation() {
        // Validación personalizada para formularios administrativos
        const adminForms = document.querySelectorAll('form[data-admin-form]');
        adminForms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateAdminForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }
    
    validateAdminForm(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[data-required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'Este campo es requerido');
                isValid = false;
            } else {
                this.clearFieldError(field);
            }
            
            // Validaciones específicas por tipo
            if (field.type === 'email' && field.value) {
                if (!this.validateEmail(field.value)) {
                    this.showFieldError(field, 'Ingrese un email válido');
                    isValid = false;
                }
            }
            
            if (field.type === 'number' && field.value) {
                if (field.min && parseFloat(field.value) < parseFloat(field.min)) {
                    this.showFieldError(field, `El valor mínimo es ${field.min}`);
                    isValid = false;
                }
                if (field.max && parseFloat(field.value) > parseFloat(field.max)) {
                    this.showFieldError(field, `El valor máximo es ${field.max}`);
                    isValid = false;
                }
            }
        });
        
        return isValid;
    }
    
    showFieldError(field, message) {
        const formGroup = field.closest('.form-group') || field.parentElement;
        let errorDiv = formGroup.querySelector('.invalid-feedback');
        
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            formGroup.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        field.classList.add('is-invalid');
    }
    
    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const formGroup = field.closest('.form-group') || field.parentElement;
        const errorDiv = formGroup.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    setupTooltips() {
        // Inicializar tooltips de Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Inicializar popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }
    
    setupDataExport() {
        // Configurar exportación de datos
        document.querySelectorAll('[data-export]').forEach(button => {
            button.addEventListener('click', (e) => {
                const format = button.getAttribute('data-format') || 'excel';
                const tableId = button.getAttribute('data-table') || '';
                this.exportData(format, tableId);
            });
        });
    }
    
    async exportData(format, tableId) {
        try {
            UNEXCA.Utils.showLoading('body', `Exportando a ${format.toUpperCase()}...`);
            
            let url = 'ajax/export_data.php';
            let params = new URLSearchParams({
                format: format,
                table: tableId,
                filters: JSON.stringify(this.getCurrentFilters())
            });
            
            if (format === 'excel') {
                // Descargar directamente
                window.location.href = `${url}?${params.toString()}`;
            } else if (format === 'pdf') {
                // Abrir en nueva ventana
                window.open(`${url}?${params.toString()}`, '_blank');
            } else if (format === 'csv') {
                // Generar CSV del lado del cliente
                this.exportTableToCSV(tableId);
            }
            
            setTimeout(() => {
                UNEXCA.Utils.hideLoading('body');
                UNEXCA.Utils.showNotification(`Datos exportados a ${format.toUpperCase()}`, 'success');
            }, 1000);
            
        } catch (error) {
            UNEXCA.Utils.hideLoading('body');
            UNEXCA.Utils.showNotification('Error al exportar datos', 'error');
            console.error('Export error:', error);
        }
    }
    
    getCurrentFilters() {
        const filters = {};
        document.querySelectorAll('[data-filter]').forEach(input => {
            if (input.value) {
                filters[input.name] = input.value;
            }
        });
        return filters;
    }
    
    exportTableToCSV(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tr');
        const csv = [];
        
        rows.forEach(row => {
            const rowData = [];
            const cells = row.querySelectorAll('th, td');
            
            cells.forEach(cell => {
                // Excluir columnas de acciones
                if (!cell.closest('.actions-column')) {
                    let cellText = cell.textContent.trim();
                    
                    // Manejar contenido especial
                    if (cell.querySelector('.badge')) {
                        cellText = cell.querySelector('.badge').textContent.trim();
                    }
                    if (cell.querySelector('input[type="checkbox"]')) {
                        cellText = cell.querySelector('input[type="checkbox"]').checked ? 'Sí' : 'No';
                    }
                    
                    // Escapar comillas
                    cellText = cellText.replace(/"/g, '""');
                    
                    // Agregar comillas si contiene comas
                    if (cellText.includes(',')) {
                        cellText = `"${cellText}"`;
                    }
                    
                    rowData.push(cellText);
                }
            });
            
            csv.push(rowData.join(','));
        });
        
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        link.href = URL.createObjectURL(blob);
        link.download = `export_${tableId}_${new Date().toISOString().slice(0,10)}.csv`;
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    setupBulkActions() {
        // Configurar acciones masivas
        const bulkActions = document.querySelectorAll('[data-bulk-action]');
        bulkActions.forEach(action => {
            action.addEventListener('click', (e) => {
                const selectedItems = this.getSelectedItems();
                if (selectedItems.length === 0) {
                    UNEXCA.Utils.showNotification('Seleccione al menos un elemento', 'warning');
                    return;
                }
                
                const actionType = action.getAttribute('data-action');
                this.executeBulkAction(actionType, selectedItems);
            });
        });
        
        // Checkbox para seleccionar todos
        const selectAllCheckbox = document.querySelector('[data-select-all]');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                const checkboxes = document.querySelectorAll('[data-item-checkbox]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                });
            });
        }
    }
    
    getSelectedItems() {
        const selectedItems = [];
        document.querySelectorAll('[data-item-checkbox]:checked').forEach(checkbox => {
            selectedItems.push(checkbox.value);
        });
        return selectedItems;
    }
    
    async executeBulkAction(action, items) {
        try {
            UNEXCA.Utils.showLoading('body', 'Procesando acción...');
            
            const response = await fetch('ajax/bulk_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    items: items
                })
            });
            
            const data = await response.json();
            
            UNEXCA.Utils.hideLoading('body');
            
            if (data.success) {
                UNEXCA.Utils.showNotification(data.message, 'success');
                
                // Recargar la página o actualizar la tabla
                if (data.reload) {
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            } else {
                UNEXCA.Utils.showNotification(data.message, 'error');
            }
            
        } catch (error) {
            UNEXCA.Utils.hideLoading('body');
            UNEXCA.Utils.showNotification('Error al ejecutar la acción', 'error');
            console.error('Bulk action error:', error);
        }
    }
    
    // Funciones de utilidad para gráficos
    createAdvancedChart(ctx, type, data, options = {}) {
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        borderDash: [2]
                    }
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
    
    // Funciones para gestión de usuarios
    async toggleUserStatus(userId, newStatus) {
        try {
            const response = await fetch('ajax/toggle_user_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    status: newStatus
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                UNEXCA.Utils.showNotification(`Estado actualizado: ${newStatus}`, 'success');
                return true;
            } else {
                UNEXCA.Utils.showNotification(data.message, 'error');
                return false;
            }
        } catch (error) {
            UNEXCA.Utils.showNotification('Error al actualizar estado', 'error');
            console.error('Toggle status error:', error);
            return false;
        }
    }
    
    // Funciones para notificaciones del sistema
    async sendSystemNotification(title, message, type = 'info', recipients = []) {
        try {
            const response = await fetch('ajax/send_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    title: title,
                    message: message,
                    type: type,
                    recipients: recipients
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                UNEXCA.Utils.showNotification('Notificación enviada', 'success');
                return true;
            } else {
                UNEXCA.Utils.showNotification(data.message, 'error');
                return false;
            }
        } catch (error) {
            UNEXCA.Utils.showNotification('Error al enviar notificación', 'error');
            console.error('Send notification error:', error);
            return false;
        }
    }
    
    // Funciones para backup y mantenimiento
    async createBackup(type = 'database') {
        try {
            UNEXCA.Utils.showLoading('body', 'Creando backup...');
            
            const response = await fetch('ajax/create_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: type,
                    timestamp: new Date().toISOString()
                })
            });
            
            const data = await response.json();
            
            UNEXCA.Utils.hideLoading('body');
            
            if (data.success) {
                UNEXCA.Utils.showNotification(`Backup creado exitosamente: ${data.filename}`, 'success');
                
                // Opcional: Descargar el backup
                if (data.download_url) {
                    const link = document.createElement('a');
                    link.href = data.download_url;
                    link.download = data.filename;
                    link.click();
                }
                
                return true;
            } else {
                UNEXCA.Utils.showNotification(data.message, 'error');
                return false;
            }
        } catch (error) {
            UNEXCA.Utils.hideLoading('body');
            UNEXCA.Utils.showNotification('Error al crear backup', 'error');
            console.error('Backup error:', error);
            return false;
        }
    }
    
    // Funciones para limpieza del sistema
    async cleanupSystem(daysToKeep = 30) {
        try {
            const confirm = await Swal.fire({
                title: '¿Está seguro?',
                text: `Esta acción eliminará registros antiguos (más de ${daysToKeep} días). Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, limpiar',
                cancelButtonText: 'Cancelar'
            });
            
            if (confirm.isConfirmed) {
                UNEXCA.Utils.showLoading('body', 'Limpiando sistema...');
                
                const response = await fetch('ajax/cleanup_system.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        days_to_keep: daysToKeep
                    })
                });
                
                const data = await response.json();
                
                UNEXCA.Utils.hideLoading('body');
                
                if (data.success) {
                    UNEXCA.Utils.showNotification(
                        `Sistema limpiado: ${data.deleted_records} registros eliminados`,
                        'success'
                    );
                    return true;
                } else {
                    UNEXCA.Utils.showNotification(data.message, 'error');
                    return false;
                }
            }
        } catch (error) {
            UNEXCA.Utils.hideLoading('body');
            UNEXCA.Utils.showNotification('Error al limpiar sistema', 'error');
            console.error('Cleanup error:', error);
            return false;
        }
    }
}

// Inicializar utilidades administrativas
document.addEventListener('DOMContentLoaded', function() {
    window.UNEXCA = window.UNEXCA || {};
    window.UNEXCA.Admin = new AdminUtils();
});

// Estilos para notificaciones
const adminStyles = document.createElement('style');
adminStyles.textContent = `
.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
}

.notification {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    border-left: 4px solid #0056b3;
    animation: slideInRight 0.3s ease;
}

.notification-success {
    border-left-color: #28a745;
}

.notification-warning {
    border-left-color: #ffc107;
}

.notification-danger {
    border-left-color: #dc3545;
}

.notification-info {
    border-left-color: #17a2b8;
}

.notification-content {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.notification-content i {
    font-size: 1.2rem;
    margin-top: 2px;
}

.notification-content strong {
    display: block;
    margin-bottom: 5px;
}

.notification-content p {
    margin: 0;
    font-size: 0.9rem;
    color: #6c757d;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.value-increase {
    animation: pulseIncrease 1s ease;
}

.value-decrease {
    animation: pulseDecrease 1s ease;
}

@keyframes pulseIncrease {
    0%, 100% { background-color: transparent; }
    50% { background-color: rgba(40, 167, 69, 0.1); }
}

@keyframes pulseDecrease {
    0%, 100% { background-color: transparent; }
    50% { background-color: rgba(220, 53, 69, 0.1); }
}

.actions-column {
    width: 100px;
    text-align: center;
}

.bulk-actions-bar {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.bulk-actions-bar select {
    max-width: 200px;
}

.table-actions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.table-actions .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.data-table-container {
    position: relative;
}

.data-table-loading {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
`;

document.head.appendChild(adminStyles);