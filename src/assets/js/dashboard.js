/**
 * Omnes Immobilier - Client Dashboard JavaScript
 * Handles interactivity and animations for the luxury client dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all dashboard functionality
    initStatsCardAnimations();
    initPropertyCardAnimations();
    initFormEffects();
    initNavigationEffects();
    initButtonAnimations();
});

/**
 * Animate stats cards on hover
 */
function initStatsCardAnimations() {
    const statsCards = document.querySelectorAll('.stats-card');
    
    statsCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
            this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });

        // Add click ripple effect
        card.addEventListener('click', function(e) {
            createRippleEffect(e, this);
        });
    });
}

/**
 * Property card hover animations
 */
function initPropertyCardAnimations() {
    const propertyCards = document.querySelectorAll('.property-card');
    
    propertyCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
            this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            
            // Add subtle glow effect
            this.style.boxShadow = '0 16px 32px 0 rgba(0, 0, 0, 0.12), 0 0 20px rgba(212, 175, 55, 0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });
}

/**
 * Form control focus effects
 */
function initFormEffects() {
    const formControls = document.querySelectorAll('.form-control-luxury');
    
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            const parent = this.parentElement;
            parent.style.transform = 'scale(1.02)';
            parent.style.transition = 'transform 0.2s ease';
            
            // Add gold border animation
            this.style.borderColor = '#d4af37';
            this.style.boxShadow = '0 0 0 4px rgba(212, 175, 55, 0.1)';
        });
        
        control.addEventListener('blur', function() {
            const parent = this.parentElement;
            parent.style.transform = 'scale(1)';
            
            // Reset border
            this.style.borderColor = '';
            this.style.boxShadow = '';
        });
    });
}

/**
 * Navigation link effects
 */
function initNavigationEffects() {
    const navLinks = document.querySelectorAll('.nav-link-luxury');
    
    navLinks.forEach(link => {
        // Add hover effect with icon animation
        link.addEventListener('mouseenter', function() {
            const icon = this.querySelector('i');
            if (icon) {
                icon.style.transform = 'scale(1.2) rotate(5deg)';
                icon.style.transition = 'transform 0.2s ease';
            }
        });
        
        link.addEventListener('mouseleave', function() {
            const icon = this.querySelector('i');
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0deg)';
            }
        });
    });
}

/**
 * Luxury button animations
 */
function initButtonAnimations() {
    const luxuryButtons = document.querySelectorAll('.btn-luxury-primary, .btn-luxury-secondary');
    
    luxuryButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });

        button.addEventListener('mousedown', function() {
            this.style.transform = 'translateY(0) scale(0.98)';
        });

        button.addEventListener('mouseup', function() {
            this.style.transform = 'translateY(-2px) scale(1)';
        });
    });
}

/**
 * Create ripple effect on click
 */
function createRippleEffect(event, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.style.position = 'absolute';
    ripple.style.borderRadius = '50%';
    ripple.style.background = 'rgba(212, 175, 55, 0.3)';
    ripple.style.transform = 'scale(0)';
    ripple.style.animation = 'ripple 0.6s linear';
    ripple.style.pointerEvents = 'none';
    
    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);
    
    // Remove ripple after animation
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

/**
 * Smooth scroll to section
 */
function scrollToSection(sectionId) {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

/**
 * Toggle favorite property
 */
function toggleFavorite(propertyId) {
    const button = document.querySelector(`[data-property-id="${propertyId}"]`);
    if (button) {
        const icon = button.querySelector('i');
        const isFavorite = icon.classList.contains('fas');
        
        if (isFavorite) {
            icon.classList.remove('fas');
            icon.classList.add('far');
            button.style.color = '#737373';
        } else {
            icon.classList.remove('far');
            icon.classList.add('fas');
            button.style.color = '#d4af37';
        }
        
        // Add bounce animation
        icon.style.animation = 'bounce 0.4s ease';
        setTimeout(() => {
            icon.style.animation = '';
        }, 400);
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

/**
 * Initialize tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Animate counter numbers
 */
function animateCounters() {
    const counters = document.querySelectorAll('.stats-number');
    
    counters.forEach(counter => {
        const target = parseInt(counter.textContent);
        const increment = target / 30; // 30 frames
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            counter.textContent = Math.floor(current);
            
            if (current >= target) {
                counter.textContent = target;
                clearInterval(timer);
            }
        }, 50);
    });
}

/**
 * Loading state management
 */
function showLoading(element) {
    const spinner = document.createElement('div');
    spinner.className = 'spinner-border text-warning spinner-border-sm me-2';
    spinner.setAttribute('role', 'status');
    
    element.prepend(spinner);
    element.disabled = true;
}

function hideLoading(element) {
    const spinner = element.querySelector('.spinner-border');
    if (spinner) {
        spinner.remove();
    }
    element.disabled = false;
}

// Add CSS keyframes for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes bounce {
        0%, 20%, 60%, 100% {
            transform: translateY(0);
        }
        40% {
            transform: translateY(-10px);
        }
        80% {
            transform: translateY(-5px);
        }
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translate3d(0, 40px, 0);
        }
        to {
            opacity: 1;
            transform: translate3d(0, 0, 0);
        }
    }
    
    .fade-in-up {
        animation: fadeInUp 0.6s ease-out;
    }
`;
document.head.appendChild(style);

// Initialize tooltips when DOM is ready
document.addEventListener('DOMContentLoaded', initTooltips);

// Animate counters when page loads
window.addEventListener('load', () => {
    setTimeout(animateCounters, 500);
});

// Export functions for global use
window.DashboardJS = {
    scrollToSection,
    toggleFavorite,
    showNotification,
    showLoading,
    hideLoading
};