// Auth.js - JavaScript for authentication forms
document.addEventListener('DOMContentLoaded', function() {
    
    // Password strength validation
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', checkPasswordStrength);
    }
    
    if (passwordConfirmInput) {
        passwordConfirmInput.addEventListener('input', checkPasswordMatch);
    }
    
    // Form submission enhancement
    const authForms = document.querySelectorAll('#loginForm, #registerForm');
    authForms.forEach(form => {
        form.addEventListener('submit', handleFormSubmission);
    });
});

// Password strength checker
function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const strengthDiv = document.getElementById('passwordStrength');
    
    if (!strengthDiv) return;
    
    if (password.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    let strength = 0;
    let feedback = [];
    
    // Check various criteria
    if (password.length >= 6) strength++;
    else feedback.push('Au moins 6 caractères');
    
    if (/[a-z]/.test(password)) strength++;
    else feedback.push('Une minuscule');
    
    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('Une majuscule');
    
    if (/[0-9]/.test(password)) strength++;
    else feedback.push('Un chiffre');
    
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    else feedback.push('Un caractère spécial');
    
    const levels = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
    const classes = ['strength-weak', 'strength-weak', 'strength-medium', 'strength-strong', 'strength-strong'];
    
    strengthDiv.innerHTML = `<span class="${classes[strength - 1]}">${levels[strength - 1] || 'Très faible'}</span>`;
    if (feedback.length > 0) {
        strengthDiv.innerHTML += `<br><small>Manque: ${feedback.join(', ')}</small>`;
    }
}

// Password match checker
function checkPasswordMatch() {
    const password = document.getElementById('password');
    const confirm = document.getElementById('password_confirm');
    const matchDiv = document.getElementById('passwordMatch');
    
    if (!password || !confirm || !matchDiv) return;
    
    if (confirm.value.length === 0) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (password.value === confirm.value) {
        matchDiv.innerHTML = '<span class="strength-strong"><i class="fas fa-check me-1"></i>Les mots de passe correspondent</span>';
    } else {
        matchDiv.innerHTML = '<span class="strength-weak"><i class="fas fa-times me-1"></i>Les mots de passe ne correspondent pas</span>';
    }
}

// Form submission handler
function handleFormSubmission(event) {
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (submitBtn) {
        // Add loading state
        submitBtn.classList.add('btn-loading');
        submitBtn.disabled = true;
        
        // Add original text as data attribute if not already there
        if (!submitBtn.dataset.originalText) {
            submitBtn.dataset.originalText = submitBtn.innerHTML;
        }
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement...';
        
        // If form validation fails, restore button
        setTimeout(() => {
            if (!form.checkValidity()) {
                restoreSubmitButton(submitBtn);
            }
        }, 100);
    }
}

// Restore submit button to original state
function restoreSubmitButton(btn) {
    btn.classList.remove('btn-loading');
    btn.disabled = false;
    btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
}

// Email validation enhancement
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Real-time email validation
document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const email = this.value;
            if (email && !validateEmail(email)) {
                this.classList.add('is-invalid');
                
                // Add or update error message
                let errorDiv = this.parentNode.querySelector('.invalid-feedback');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    this.parentNode.appendChild(errorDiv);
                }
                errorDiv.textContent = 'Veuillez entrer une adresse email valide.';
            } else {
                this.classList.remove('is-invalid');
                const errorDiv = this.parentNode.querySelector('.invalid-feedback');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }
        });
    }
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 500);
        }, 5000);
    });
});