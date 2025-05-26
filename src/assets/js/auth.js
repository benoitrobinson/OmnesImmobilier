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

/* ========================================
   OMNES IMMOBILIER - AUTHENTICATION JAVASCRIPT
   Sophisticated form handling and UX enhancements
   ======================================== */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all authentication features
    initializePasswordStrength();
    initializePasswordMatching();
    initializeFormValidation();
    initializeLoadingStates();
    initializeAlertHandling();
    initializePasswordToggle();
    initializeKeyboardShortcuts();
    initializeAnimations();
});

/* ========================================
   PASSWORD STRENGTH CHECKER
   ======================================== */

function initializePasswordStrength() {
    const passwordInput = document.getElementById('password');
    const strengthIndicator = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('strengthText');
    const strengthBar = document.getElementById('strengthBar');

    if (!passwordInput || !strengthIndicator) return;

    // Password strength checker function
    function checkPasswordStrength(password) {
        let strength = 0;
        let feedback = [];

        if (password.length >= 8) strength += 1;
        else feedback.push('at least 8 characters');

        if (/[a-z]/.test(password)) strength += 1;
        else feedback.push('a lowercase letter');

        if (/[A-Z]/.test(password)) strength += 1;
        else feedback.push('an uppercase letter');

        if (/[0-9]/.test(password)) strength += 1;
        else feedback.push('a number');

        if (/[^A-Za-z0-9]/.test(password)) strength += 1;

        return { strength, feedback };
    }

    passwordInput.addEventListener('input', function() {
        const password = this.value;
        
        if (password.length > 0) {
            strengthIndicator.classList.remove('d-none');
            const result = checkPasswordStrength(password);
            
            // Update progress bar
            strengthBar.style.width = (result.strength * 20) + '%';
            
            // Update styling and text based on strength
            if (result.strength <= 2) {
                strengthBar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Weak password';
                strengthIndicator.className = 'password-strength strength-weak';
            } else if (result.strength <= 3) {
                strengthBar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Fair password';
                strengthIndicator.className = 'password-strength strength-medium';
            } else {
                strengthBar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Strong password';
                strengthIndicator.className = 'password-strength strength-strong';
            }
        } else {
            strengthIndicator.classList.add('d-none');
        }
    });
}

/* ========================================
   PASSWORD CONFIRMATION MATCHING
   ======================================== */

function initializePasswordMatching() {
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');

    if (!passwordInput || !passwordConfirmInput) return;

    function validatePasswordMatch() {
        if (passwordConfirmInput.value && passwordInput.value !== passwordConfirmInput.value) {
            passwordConfirmInput.classList.add('is-invalid');
            passwordConfirmInput.classList.remove('is-valid');
        } else if (passwordConfirmInput.value && passwordInput.value === passwordConfirmInput.value) {
            passwordConfirmInput.classList.remove('is-invalid');
            passwordConfirmInput.classList.add('is-valid');
        }
    }

    // Real-time password confirmation validation
    passwordConfirmInput.addEventListener('input', validatePasswordMatch);
    passwordInput.addEventListener('input', validatePasswordMatch);
}

/* ========================================
   FORM VALIDATION ENHANCEMENTS
   ======================================== */

function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        const emailInput = form.querySelector('#email');
        const passwordInput = form.querySelector('#password');

        // Real-time email validation
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                const email = this.value.trim();
                if (email && isValidEmail(email)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });

            emailInput.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && isValidEmail(this.value)) {
                    this.classList.remove('is-invalid');
                }
            });
        }

        // Real-time password validation
        if (passwordInput) {
            passwordInput.addEventListener('blur', function() {
                if (this.value) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });

            passwordInput.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && this.value) {
                    this.classList.remove('is-invalid');
                }
            });
        }

        // Clear validation states on focus
        const formInputs = form.querySelectorAll('.form-control');
        formInputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                }
            });
        });
    });
}

/* ========================================
   FORM SUBMISSION LOADING STATES
   ======================================== */

function initializeLoadingStates() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const submitBtn = form.querySelector('#submitBtn');
        const submitSpinner = form.querySelector('#submitSpinner');
        const submitIcon = form.querySelector('#submitIcon');
        const submitText = form.querySelector('#submitText');

        if (!submitBtn) return;

        form.addEventListener('submit', function(e) {
            // Basic client-side validation for login form
            if (form.id === 'loginForm') {
                const emailInput = form.querySelector('#email');
                const passwordInput = form.querySelector('#password');
                let hasErrors = false;

                // Email validation
                if (!emailInput.value.trim()) {
                    emailInput.classList.add('is-invalid');
                    hasErrors = true;
                } else if (!isValidEmail(emailInput.value)) {
                    emailInput.classList.add('is-invalid');
                    hasErrors = true;
                } else {
                    emailInput.classList.remove('is-invalid');
                    emailInput.classList.add('is-valid');
                }

                // Password validation
                if (!passwordInput.value) {
                    passwordInput.classList.add('is-invalid');
                    hasErrors = true;
                } else {
                    passwordInput.classList.remove('is-invalid');
                    passwordInput.classList.add('is-valid');
                }

                if (hasErrors) {
                    e.preventDefault();
                    return;
                }
            }

            // Show loading state
            if (submitSpinner) submitSpinner.classList.remove('d-none');
            if (submitIcon) submitIcon.classList.add('d-none');
            if (submitText) {
                if (form.id === 'registerForm') {
                    submitText.textContent = 'Creating Account...';
                } else if (form.id === 'loginForm') {
                    submitText.textContent = 'Signing In...';
                }
            }
            submitBtn.disabled = true;
            
            // Re-enable after 10 seconds to prevent infinite loading
            setTimeout(() => {
                if (submitSpinner) submitSpinner.classList.add('d-none');
                if (submitIcon) submitIcon.classList.remove('d-none');
                if (submitText) {
                    if (form.id === 'registerForm') {
                        submitText.textContent = 'Create Professional Account';
                    } else if (form.id === 'loginForm') {
                        submitText.textContent = 'Sign In to Your Account';
                    }
                }
                submitBtn.disabled = false;
            }, 10000);
        });
    });
}

/* ========================================
   ALERT HANDLING AND AUTO-DISMISS
   ======================================== */

function initializeAlertHandling() {
    // Auto-dismiss success alerts
    const successAlerts = document.querySelectorAll('.alert-success');
    successAlerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade-out');
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 500);
        }, 6000);
    });

    // Auto-dismiss general alerts after longer time
    const alerts = document.querySelectorAll('.alert:not(.alert-success)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade-out');
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 500);
        }, 8000);
    });
}

/* ========================================
   PASSWORD VISIBILITY TOGGLE
   ======================================== */

function initializePasswordToggle() {
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    
    passwordInputs.forEach(passwordInput => {
        const passwordContainer = passwordInput.closest('.form-floating');
        if (!passwordContainer) return;

        // Create toggle button
        const togglePassword = document.createElement('button');
        togglePassword.type = 'button';
        togglePassword.className = 'btn btn-link position-absolute end-0 top-50 translate-middle-y border-0 text-muted';
        togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
        togglePassword.style.cssText = 'z-index: 10; right: 15px;';
        togglePassword.setAttribute('aria-label', 'Toggle password visibility');
        
        // Position container relatively
        passwordContainer.style.position = 'relative';
        passwordContainer.appendChild(togglePassword);

        // Toggle functionality
        togglePassword.addEventListener('click', function() {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            this.innerHTML = isPassword ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });
}

/* ========================================
   KEYBOARD SHORTCUTS AND ACCESSIBILITY
   ======================================== */

function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Alt + L to focus on email field
        if (e.altKey && e.key === 'l') {
            e.preventDefault();
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.focus();
            }
        }

        // Escape key to clear active input or blur form elements
        if (e.key === 'Escape') {
            const activeElement = document.activeElement;
            if (activeElement && activeElement.tagName === 'INPUT') {
                activeElement.blur();
            }
        }
    });
}

/* ========================================
   ANIMATIONS AND VISUAL ENHANCEMENTS
   ======================================== */

function initializeAnimations() {
    // Add subtle animation to forms on load
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        setTimeout(() => {
            form.classList.add('animate__fadeIn');
        }, 100);
    });

    // Smooth scroll behavior for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

/* ========================================
   UTILITY FUNCTIONS
   ======================================== */

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Debounce function for performance optimization
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Enhanced form data collection
function getFormData(form) {
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    return data;
}

// Validation state management
function setValidationState(input, isValid, message = '') {
    if (isValid) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    } else {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        
        // Update or create error message
        let feedback = input.parentNode.querySelector('.invalid-feedback');
        if (feedback && message) {
            feedback.textContent = message;
        }
    }
}

// Show/hide loading state for any button
function setLoadingState(button, isLoading, loadingText = 'Loading...') {
    const spinner = button.querySelector('.spinner-border');
    const icon = button.querySelector('i:not(.spinner-border)');
    const textSpan = button.querySelector('span:last-child');
    
    if (isLoading) {
        if (spinner) spinner.classList.remove('d-none');
        if (icon) icon.classList.add('d-none');
        if (textSpan) textSpan.textContent = loadingText;
        button.disabled = true;
    } else {
        if (spinner) spinner.classList.add('d-none');
        if (icon) icon.classList.remove('d-none');
        button.disabled = false;
    }
}

/* ========================================
   ENHANCED UX FEATURES
   ======================================== */

// Auto-save form data to prevent data loss (optional enhancement)
function initializeAutoSave() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input:not([type="password"]):not([type="checkbox"])');
        
        inputs.forEach(input => {
            // Load saved data
            const savedValue = localStorage.getItem(`form_${form.id}_${input.name}`);
            if (savedValue && !input.value) {
                input.value = savedValue;
            }
            
            // Save data on input
            const debouncedSave = debounce(() => {
                if (input.value) {
                    localStorage.setItem(`form_${form.id}_${input.name}`, input.value);
                }
            }, 500);
            
            input.addEventListener('input', debouncedSave);
        });
        
        // Clear saved data on successful submission
        form.addEventListener('submit', () => {
            setTimeout(() => {
                inputs.forEach(input => {
                    localStorage.removeItem(`form_${form.id}_${input.name}`);
                });
            }, 1000);
        });
    });
}

// Initialize enhanced features (optional)
// Uncomment the following line if you want auto-save functionality
// initializeAutoSave();