document.addEventListener('DOMContentLoaded', function() {
    let lastScrollY = window.scrollY;
    const navbar = document.querySelector('.navbar');

    window.addEventListener('scroll', () => {
        if (window.scrollY <= 0) {
            navbar.style.top = '0';
            navbar.classList.remove('white-bg');
        } else if (window.scrollY < lastScrollY) {
            // Scrolling up
            navbar.style.top = '0';
            navbar.classList.add('white-bg');
        } else {
            // Scrolling down
            navbar.style.top = '-64px';
            navbar.classList.remove('white-bg');
        }
        lastScrollY = window.scrollY;
    });
});

/**
 * Enhanced Navigation JavaScript for Omnes Immobilier
 * Handles dropdown functionality, mobile navigation, and smooth interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    initAccountDropdown();
    initMobileNavigation();
    initNavbarEffects();
});

/**
 * Initialize account dropdown functionality
 */
function initAccountDropdown() {
    const accountDropdown = document.querySelector('.account-dropdown');
    const dropdownMenu = accountDropdown?.querySelector('.dropdown-menu');
    
    if (!accountDropdown || !dropdownMenu) return;
    
    let hoverTimeout;
    
    // Desktop hover functionality
    if (window.innerWidth > 991) {
        accountDropdown.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimeout);
            showDropdown(dropdownMenu);
        });
        
        accountDropdown.addEventListener('mouseleave', function() {
            hoverTimeout = setTimeout(() => {
                hideDropdown(dropdownMenu);
            }, 150); // Small delay to prevent flickering
        });
        
        // Keep dropdown open when hovering over menu items
        dropdownMenu.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimeout);
        });
        
        dropdownMenu.addEventListener('mouseleave', function() {
            hideDropdown(dropdownMenu);
        });
    }
    
    // Click functionality for mobile and accessibility
    const dropdownToggle = accountDropdown.querySelector('.dropdown-toggle');
    dropdownToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const isOpen = dropdownMenu.classList.contains('show');
        
        // Close all other dropdowns
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            if (menu !== dropdownMenu) {
                hideDropdown(menu);
            }
        });
        
        // Toggle current dropdown
        if (isOpen) {
            hideDropdown(dropdownMenu);
        } else {
            showDropdown(dropdownMenu);
        }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!accountDropdown.contains(e.target)) {
            hideDropdown(dropdownMenu);
        }
    });
    
    // Close dropdown on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideDropdown(dropdownMenu);
        }
    });
}

/**
 * Show dropdown with animation
 */
function showDropdown(dropdownMenu) {
    dropdownMenu.classList.add('show');
    dropdownMenu.style.display = 'block';
    
    // Trigger reflow for animation
    dropdownMenu.offsetHeight;
    
    // Add animation class
    setTimeout(() => {
        dropdownMenu.style.opacity = '1';
        dropdownMenu.style.transform = 'translateY(0)';
    }, 10);
}

/**
 * Hide dropdown with animation
 */
function hideDropdown(dropdownMenu) {
    dropdownMenu.style.opacity = '0';
    dropdownMenu.style.transform = 'translateY(-10px)';
    
    setTimeout(() => {
        dropdownMenu.classList.remove('show');
        dropdownMenu.style.display = 'none';
    }, 300);
}

/**
 * Initialize mobile navigation
 */
function initMobileNavigation() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (!navbarToggler || !navbarCollapse) return;
    
    navbarToggler.addEventListener('click', function() {
        const isExpanded = this.getAttribute('aria-expanded') === 'true';
        
        // Add animation class
        if (!isExpanded) {
            navbarCollapse.style.maxHeight = navbarCollapse.scrollHeight + 'px';
        } else {
            navbarCollapse.style.maxHeight = '0px';
        }
    });
    
    // Close mobile menu when clicking on nav links
    document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 991) {
                const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                    toggle: false
                });
                bsCollapse.hide();
            }
        });
    });
}

/**
 * Initialize navbar visual effects
 */
function initNavbarEffects() {
    const navbar = document.querySelector('.custom-navbar');
    if (!navbar) return;
    
    // Add scroll effect
    let lastScrollTop = 0;
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scrolling down
            navbar.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            navbar.style.transform = 'translateY(0)';
        }
        
        // Add background blur when scrolled
        if (scrollTop > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        lastScrollTop = scrollTop;
    });
    
    // Add hover effects to nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

/**
 * Handle window resize events
 */
window.addEventListener('resize', function() {
    const accountDropdown = document.querySelector('.account-dropdown');
    const dropdownMenu = accountDropdown?.querySelector('.dropdown-menu');
    
    if (dropdownMenu && window.innerWidth <= 991) {
        // Reset dropdown for mobile
        hideDropdown(dropdownMenu);
    }
});

/**
 * Smooth scroll to sections
 */
function smoothScrollTo(target) {
    const element = document.querySelector(target);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

/**
 * Show notification (utility function)
 */
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = `
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        border-radius: 12px;
        border: none;
    `;
    
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

// Export functions for global use
window.NavigationJS = {
    showNotification,
    smoothScrollTo
};

// Add additional CSS for scroll effects
const additionalStyles = `
    .custom-navbar {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
    }
    
    .custom-navbar.scrolled {
        background: rgba(33, 37, 41, 0.95) !important;
        box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
    }
    
    .navbar-collapse {
        transition: max-height 0.3s ease;
        overflow: hidden;
    }
    
    @media (max-width: 991.98px) {
        .custom-navbar {
            background: rgba(33, 37, 41, 0.98) !important;
        }
        
        .navbar-collapse {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            margin-top: 1rem;
            padding: 1rem;
            backdrop-filter: blur(15px);
        }
    }
`;

// Inject additional styles
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);