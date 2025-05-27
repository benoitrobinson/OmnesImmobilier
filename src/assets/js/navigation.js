// Enhanced Navigation JavaScript
// assets/js/navigation.js

document.addEventListener('DOMContentLoaded', function() {
    initializeNavigation();
    initializeTheme();
    initializeDropdowns();
    initializeScrollEffects();
});

// Initialize all navigation functionality
function initializeNavigation() {
    // Handle mobile menu toggle
    const mobileToggle = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (mobileToggle && navbarCollapse) {
        mobileToggle.addEventListener('click', function() {
            navbarCollapse.classList.toggle('show');
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.navbar') && navbarCollapse.classList.contains('show')) {
                navbarCollapse.classList.remove('show');
            }
        });
    }
    
    // Handle active navigation states
    updateActiveNavigation();
}

// Theme Management
function initializeTheme() {
    // Get saved theme or default to light
    const savedTheme = localStorage.getItem('userTheme') || 'light';
    applyTheme(savedTheme);
    updateThemeIcon(savedTheme);
    updateThemeStatus(savedTheme);
    
    // Initialize theme toggle buttons
    const themeToggles = document.querySelectorAll('.theme-toggle');
    themeToggles.forEach(toggle => {
        toggle.addEventListener('click', toggleTheme);
    });
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    applyTheme(newTheme);
    updateThemeIcon(newTheme);
    updateThemeStatus(newTheme);
    
    // Save to localStorage
    localStorage.setItem('userTheme', newTheme);
    
    // Save to session via AJAX (only if user is logged in)
    if (document.body.dataset.userLoggedIn === 'true') {
        saveThemeToServer(newTheme);
    }
    
    // Add smooth transition effect
    document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
    setTimeout(() => {
        document.body.style.transition = '';
    }, 300);
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    
    // Update body class for additional styling
    document.body.classList.remove('theme-light', 'theme-dark');
    document.body.classList.add(`theme-${theme}`);
    
    // Update navbar classes for theme-specific styling
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        navbar.classList.remove('navbar-light', 'navbar-dark');
        navbar.classList.add(theme === 'dark' ? 'navbar-dark' : 'navbar-light');
    }
}

function updateThemeIcon(theme) {
    const icons = document.querySelectorAll('#themeIcon');
    icons.forEach(icon => {
        if (icon) {
            icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }
    });
}

function updateThemeStatus(theme) {
    const statusElements = document.querySelectorAll('#themeStatus');
    statusElements.forEach(status => {
        if (status) {
            status.textContent = `Current: ${theme === 'light' ? 'Light' : 'Dark'} Mode`;
        }
    });
}

function saveThemeToServer(theme) {
    fetch('../ajax/save_theme.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ theme: theme })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Theme saved successfully');
        } else {
            console.error('Theme save error:', data.error);
        }
    })
    .catch(error => {
        console.error('Theme save request failed:', error);
    });
}

// Enhanced Dropdown Management
function initializeDropdowns() {
    const dropdowns = document.querySelectorAll('.account-dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            // Handle mouse events for desktop
            if (window.innerWidth > 991) {
                dropdown.addEventListener('mouseenter', () => {
                    showDropdown(menu);
                });
                
                dropdown.addEventListener('mouseleave', () => {
                    hideDropdown(menu);
                });
            }
            
            // Handle click events for mobile and accessibility
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                const isVisible = menu.classList.contains('show');
                
                // Close all other dropdowns
                closeAllDropdowns();
                
                if (!isVisible) {
                    showDropdown(menu);
                }
            });
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.account-dropdown')) {
            closeAllDropdowns();
        }
    });
    
    // Close dropdowns on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllDropdowns();
        }
    });
}

function showDropdown(menu) {
    menu.classList.add('show');
    menu.style.opacity = '1';
    menu.style.transform = 'translateY(0) scale(1)';
    menu.style.pointerEvents = 'auto';
}

function hideDropdown(menu) {
    menu.classList.remove('show');
    menu.style.opacity = '0';
    menu.style.transform = 'translateY(-10px) scale(0.95)';
    menu.style.pointerEvents = 'none';
}

function closeAllDropdowns() {
    const allDropdownMenus = document.querySelectorAll('.dropdown-menu');
    allDropdownMenus.forEach(menu => {
        hideDropdown(menu);
    });
}

// Scroll Effects
function initializeScrollEffects() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    let lastScrollY = window.scrollY;
    let ticking = false;
    
    function updateNavbar() {
        const scrollY = window.scrollY;
        
        // Add scrolled class for backdrop effect
        if (scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        // Hide/show navbar on scroll (optional)
        if (scrollY > lastScrollY && scrollY > 100) {
            // Scrolling down
            navbar.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            navbar.style.transform = 'translateY(0)';
        }
        
        lastScrollY = scrollY;
        ticking = false;
    }
    
    function requestNavbarUpdate() {
        if (!ticking) {
            requestAnimationFrame(updateNavbar);
            ticking = true;
        }
    }
    
    // Only apply scroll effects on non-dashboard pages
    if (!document.body.dataset.page || document.body.dataset.page !== 'dashboard') {
        window.addEventListener('scroll', requestNavbarUpdate);
    }
}

// Update active navigation based on current page
function updateActiveNavigation() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href.replace('../', ''))) {
            link.classList.add('active');
        }
    });
}

// Notification system for user feedback
function showNotification(message, type = 'info', duration = 3000) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.nav-notification');
    existingNotifications.forEach(notif => notif.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `nav-notification alert alert-${type} alert-dismissible fade show`;
    notification.style.cssText = `
        position: fixed;
        top: 90px;
        right: 20px;
        z-index: 10060;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        border: none;
        border-radius: 12px;
    `;
    
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${getNotificationIcon(type)} me-2"></i>
            <span>${message}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after duration
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'danger': 'exclamation-triangle',
        'warning': 'exclamation-circle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// Enhanced search functionality (if search bar exists)
function initializeSearch() {
    const searchInput = document.querySelector('#navbarSearch');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        } else {
            hideSearchResults();
        }
    });
    
    // Close search results when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-container')) {
            hideSearchResults();
        }
    });
}

function performSearch(query) {
    // Implement search functionality
    console.log('Searching for:', query);
    // You can make an AJAX call here to search properties/agents
}

function hideSearchResults() {
    const searchResults = document.querySelector('.search-results');
    if (searchResults) {
        searchResults.style.display = 'none';
    }
}

// User avatar initials generator
function generateAvatarInitials(firstName, lastName) {
    const firstInitial = firstName ? firstName.charAt(0).toUpperCase() : '';
    const lastInitial = lastName ? lastName.charAt(0).toUpperCase() : '';
    return firstInitial + lastInitial;
}

// Responsive navigation adjustments
function handleResponsiveNavigation() {
    const navbar = document.querySelector('.navbar');
    const isDesktop = window.innerWidth > 991;
    
    if (isDesktop) {
        // Enable hover dropdowns on desktop
        initializeDropdowns();
    } else {
        // Disable hover effects on mobile
        const dropdowns = document.querySelectorAll('.account-dropdown');
        dropdowns.forEach(dropdown => {
            dropdown.removeEventListener('mouseenter', showDropdown);
            dropdown.removeEventListener('mouseleave', hideDropdown);
        });
    }
}

// Window resize handler
window.addEventListener('resize', debounce(handleResponsiveNavigation, 250));

// Utility function for debouncing
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

// Loading state management
function setLoadingState(element, isLoading) {
    if (isLoading) {
        element.classList.add('loading');
        element.disabled = true;
        const originalText = element.textContent;
        element.dataset.originalText = originalText;
        element.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
    } else {
        element.classList.remove('loading');
        element.disabled = false;
        if (element.dataset.originalText) {
            element.textContent = element.dataset.originalText;
            delete element.dataset.originalText;
        }
    }
}

// Keyboard accessibility
function initializeKeyboardNavigation() {
    const focusableElements = document.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    
    // Add keyboard navigation for dropdowns
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            // Handle tab navigation
            handleTabNavigation(e, focusableElements);
        }
    });
}

function handleTabNavigation(e, focusableElements) {
    const currentIndex = Array.from(focusableElements).indexOf(document.activeElement);
    
    if (e.shiftKey) {
        // Shift + Tab (backward)
        if (currentIndex <= 0) {
            e.preventDefault();
            focusableElements[focusableElements.length - 1].focus();
        }
    } else {
        // Tab (forward)
        if (currentIndex >= focusableElements.length - 1) {
            e.preventDefault();
            focusableElements[0].focus();
        }
    }
}

// Initialize keyboard navigation
document.addEventListener('DOMContentLoaded', initializeKeyboardNavigation);

// Export functions for use in other scripts
window.NavigationUtils = {
    toggleTheme,
    applyTheme,
    showNotification,
    setLoadingState,
    generateAvatarInitials
};

console.log('Enhanced Navigation JavaScript loaded successfully');