// assets/js/login.js

document.addEventListener('DOMContentLoaded', function() {
    // Efecto de entrada para el formulario
    const loginCard = document.querySelector('.login-card');
    if (loginCard) {
        loginCard.style.opacity = '0';
        loginCard.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            loginCard.style.transition = 'all 0.5s ease';
            loginCard.style.opacity = '1';
            loginCard.style.transform = 'translateY(0)';
        }, 100);
    }
    
    // Validación de formulario
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            let valid = true;
            
            if (!username.value.trim()) {
                showError(username, 'Por favor ingrese su usuario');
                valid = false;
            }
            
            if (!password.value.trim()) {
                showError(password, 'Por favor ingrese su contraseña');
                valid = false;
            }
            
            if (!valid) {
                e.preventDefault();
            }
        });
    }
    
    function showError(input, message) {
        const formGroup = input.parentElement;
        let errorDiv = formGroup.querySelector('.error-message');
        
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'error-message text-danger mt-1 small';
            formGroup.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        input.classList.add('is-invalid');
        
        setTimeout(() => {
            errorDiv.remove();
            input.classList.remove('is-invalid');
        }, 3000);
    }
});