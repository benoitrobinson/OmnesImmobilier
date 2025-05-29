// Enhanced Navigation JavaScript - Fixed for Role-Based Navigation
// assets/js/navigation.js

document.addEventListener('DOMContentLoaded', function() {
    initializeNavigation();
    initializeTheme();
    initializeDropdowns();
    initializeScrollEffects();
    initializeRoleBasedFeatures();
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

// Role-based feature initialization
function initializeRoleBasedFeatures() {
    // Check user role from body or meta tags
    const userRole = document.body.dataset.userRole || 
                    document.querySelector('meta[name="user-role"]')?.content || 
                    'guest';
    
    // Update navigation based on role
    updateRoleBasedNavigation(userRole);
    
    // Initialize role-specific shortcuts
    initializeRoleShortcuts(userRole);
}

function updateRoleBasedNavigation(role) {
    const dashboardLinks = document.querySelectorAll('a[href*="dashboard"]');
    const accountLinks = document.querySelectorAll('a[href*="account"]');
    
    dashboardLinks.forEach(link => {
        if (role === 'agent' && !link.href.includes('/agent/')) {
            link.href = link.href.replace('/client/', '/agent/');
        } else if (role === 'admin' && !link.href.includes('/admin/')) {
            link.href = link.href.replace('/client/', '/admin/');
        }
    });
    
    accountLinks.forEach(link => {
        if (role === 'agent' && !link.href.includes('/agent/')) {
            link.href = link.href.replace('/client/', '/agent/');
        } else if (role === 'admin' && !link.href.includes('/admin/')) {
            link.href = link.href.replace('/client/', '/admin/');
        }
    });
}

function initializeRoleShortcuts(role) {
    // Add keyboard shortcuts based on role
    document.addEventListener('keydown', function(e) {
        // Alt + D for Dashboard
        if (e.altKey && e.key === 'd') {
            e.preventDefault();
            const dashboardPath = role === 'agent' ? '../agent/dashboard.php' : 
                                 role === 'admin' ? '../admin/dashboard.php' : 
                                 '../client/dashboard.php';
            window.location.href = dashboardPath;
        }
        
        // Alt + A for Account
        if (e.altKey && e.key === 'a') {
            e.preventDefault();
            const accountPath = role === 'agent' ? '../agent/account.php' : 
                               role === 'admin' ? '../admin/account.php' : 
                               '../client/account.php';
            window.location.href = accountPath;
        }
        
        // Alt + H for Home
        if (e.altKey && e.key === 'h') {
            e.preventDefault();
            window.location.href = '../pages/home.php';
        }
    });
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

// Enhanced Dropdown Management with Better Positioning
function initializeDropdowns() {
    const dropdowns = document.querySelectorAll('.account-dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu, .account-dropdown-menu');
        
        if (toggle && menu) {
            let hoverTimeout;
            
            // Enhanced hover functionality for desktop
            if (window.innerWidth > 991) {
                dropdown.addEventListener('mouseenter', () => {
                    clearTimeout(hoverTimeout);
                    showDropdown(menu);
                    positionDropdown(dropdown, menu);
                });
                
                dropdown.addEventListener('mouseleave', (e) => {
                    // Check if mouse is moving to dropdown menu
                    if (!menu.contains(e.relatedTarget)) {
                        hoverTimeout = setTimeout(() => {
                            hideDropdown(menu);
                        }, 150); // Small delay to prevent flickering
                    }
                });
                
                // Keep dropdown open when hovering over menu
                menu.addEventListener('mouseenter', () => {
                    clearTimeout(hoverTimeout);
                });
                
                menu.addEventListener('mouseleave', () => {
                    hideDropdown(menu);
                });
            }
            
            // Handle click events for mobile and accessibility
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const isVisible = menu.classList.contains('show');
                
                // Close all other dropdowns
                closeAllDropdowns();
                
                if (!isVisible) {
                    showDropdown(menu);
                    positionDropdown(dropdown, menu);
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

function positionDropdown(dropdown, menu) {
    // Ensure dropdown is positioned correctly
    const rect = dropdown.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Check if dropdown would go off-screen horizontally
    if (rect.right - menu.offsetWidth < 0) {
        menu.style.right = 'auto';
        menu.style.left = '0';
    } else if (rect.left + menu.offsetWidth > viewportWidth) {
        menu.style.left = 'auto';
        menu.style.right = '0';
    }
    
    // Check if dropdown would go off-screen vertically
    if (rect.bottom + menu.offsetHeight > viewportHeight) {
        menu.style.top = 'auto';
        menu.style.bottom = '100%';
        menu.style.marginTop = '0';
        menu.style.marginBottom = '12px';
    } else {
        menu.style.bottom = 'auto';
        menu.style.top = '100%';
        menu.style.marginBottom = '0';
        menu.style.marginTop = '12px';
    }
}

function showDropdown(menu) {
    menu.classList.add('show');
    menu.style.display = 'block';
    // Use requestAnimationFrame to ensure display change is applied before opacity
    requestAnimationFrame(() => {
        menu.style.opacity = '1';
        menu.style.transform = 'translateY(0) scale(1)';
        menu.style.pointerEvents = 'auto';
    });
}

function hideDropdown(menu) {
    menu.style.opacity = '0';
    menu.style.transform = 'translateY(-15px) scale(0.95)';
    menu.style.pointerEvents = 'none';
    menu.classList.remove('show');
    
    // Hide after transition completes
    setTimeout(() => {
        if (menu.style.opacity === '0') {
            menu.style.display = 'none';
        }
    }, 300);
}

function closeAllDropdowns() {
    const allDropdownMenus = document.querySelectorAll('.dropdown-menu, .account-dropdown-menu');
    allDropdownMenus.forEach(menu => {
        hideDropdown(menu);
    });
}

// Scroll Effects with Performance Optimization
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
        
        // Auto-hide navbar on scroll down (only on non-dashboard pages)
        if (!document.body.dataset.page || document.body.dataset.page !== 'dashboard') {
            if (scrollY > lastScrollY && scrollY > 100) {
                // Scrolling down
                navbar.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up
                navbar.style.transform = 'translateY(0)';
            }
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
    
    // Apply scroll effects based on page type
    if (!document.body.dataset.page || document.body.dataset.page !== 'dashboard') {
        window.addEventListener('scroll', requestNavbarUpdate);
    }
}

// Update active navigation based on current page
function updateActiveNavigation() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href.replace('../', ''))) {
            link.classList.add('active');
        }
    });
}

// Enhanced notification system
function showNotification(message, type = 'info', duration = 4000) {
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
        z-index: 10070;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        border: none;
        border-radius: 12px;
        backdrop-filter: blur(10px);
    `;
    
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${getNotificationIcon(type)} me-2"></i>
            <span>${message}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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

// Enhanced search functionality
function initializeSearch() {
    const searchInput = document.querySelector('#navbarSearch');
    if (!searchInput) return;
    
    let searchTimeout;
    let searchResults = null;
    
    // Create search results container
    const searchContainer = searchInput.closest('.search-container') || searchInput.parentElement;
    if (searchContainer && !searchContainer.querySelector('.search-results')) {
        searchResults = document.createElement('div');
        searchResults.className = 'search-results';
        searchResults.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            max-height: 400px;
            overflow-y: auto;
            z-index: 10050;
            display: none;
        `;
        searchContainer.appendChild(searchResults);
    }
    
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
    // Show loading state
    const searchResults = document.querySelector('.search-results');
    if (searchResults) {
        searchResults.style.display = 'block';
        searchResults.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        
        // Simulate search (replace with actual API call)
        setTimeout(() => {
            const mockResults = [
                { type: 'property', title: 'Luxury Apartment in Paris', price: '€850,000' },
                { type: 'agent', title: 'Jean-Pierre SEGADO', specialization: 'Residential Properties' }
            ];
            
            displaySearchResults(mockResults, query);
        }, 500);
    }
}

function displaySearchResults(results, query) {
    const searchResults = document.querySelector('.search-results');
    if (!searchResults) return;
    
    if (results.length === 0) {
        searchResults.innerHTML = `
            <div class="p-3 text-center text-muted">
                <i class="fas fa-search mb-2"></i><br>
                No results found for "${query}"
            </div>
        `;
        return;
    }
    
    const resultsHTML = results.map(result => `
        <div class="search-result-item p-3 border-bottom">
            <div class="fw-semibold">${result.title}</div>
            <small class="text-muted">${result.price || result.specialization}</small>
        </div>
    `).join('');
    
    searchResults.innerHTML = resultsHTML;
}

function hideSearchResults() {
    const searchResults = document.querySelector('.search-results');
    if (searchResults) {
        searchResults.style.display = 'none';
    }
}

// Responsive navigation adjustments
function handleResponsiveNavigation() {
    const isDesktop = window.innerWidth > 991;
    
    if (isDesktop) {
        // Enable hover dropdowns on desktop
        initializeDropdowns();
    } else {
        // Disable hover effects on mobile
        const dropdowns = document.querySelectorAll('.account-dropdown');
        dropdowns.forEach(dropdown => {
            dropdown.replaceWith(dropdown.cloneNode(true));
        });
        // Re-initialize for mobile
        initializeDropdowns();
    }
}

// Window resize handler with debouncing
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

// Keyboard accessibility enhancements
function initializeKeyboardNavigation() {
    // Add tab navigation support
    const focusableElements = document.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    
    // Enhanced keyboard navigation for dropdowns
    document.addEventListener('keydown', (e) => {
        const activeDropdown = document.querySelector('.account-dropdown .dropdown-menu.show');
        
        if (activeDropdown) {
            const dropdownItems = activeDropdown.querySelectorAll('.dropdown-item');
            const currentFocus = document.activeElement;
            const currentIndex = Array.from(dropdownItems).indexOf(currentFocus);
            
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    const nextIndex = currentIndex < dropdownItems.length - 1 ? currentIndex + 1 : 0;
                    dropdownItems[nextIndex].focus();
                    break;
                    
                case 'ArrowUp':
                    e.preventDefault();
                    const prevIndex = currentIndex > 0 ? currentIndex - 1 : dropdownItems.length - 1;
                    dropdownItems[prevIndex].focus();
                    break;
                    
                case 'Enter':
                case ' ':
                    if (currentFocus.classList.contains('dropdown-item')) {
                        e.preventDefault();
                        currentFocus.click();
                    }
                    break;
            }
        }
    });
}

// Initialize keyboard navigation
initializeKeyboardNavigation();

// Performance monitoring
function measureNavigationPerformance() {
    if ('performance' in window) {
        const navigationTiming = performance.getEntriesByType('navigation')[0];
        if (navigationTiming) {
            console.log('Navigation Performance:', {
                domContentLoaded: navigationTiming.domContentLoadedEventEnd - navigationTiming.domContentLoadedEventStart,
                loadComplete: navigationTiming.loadEventEnd - navigationTiming.loadEventStart
            });
        }
    }
}

// Call performance measurement after page load
window.addEventListener('load', measureNavigationPerformance);

// Export functions for external use
window.NavigationUtils = {
    showNotification,
    setLoadingState,
    toggleTheme,
    closeAllDropdowns,
    updateActiveNavigation
};