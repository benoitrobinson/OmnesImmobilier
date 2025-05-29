// Agent Navigation JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize mobile navigation toggle
    const mobileToggle = document.querySelector('.mobile-nav-toggle');
    const navMain = document.querySelector('.nav-main');
    
    if (mobileToggle && navMain) {
        mobileToggle.addEventListener('click', function() {
            navMain.classList.toggle('mobile-open');
            
            // Update icon
            const icon = this.querySelector('i');
            if (navMain.classList.contains('mobile-open')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        });
    }
    
    // Close mobile menu when clicking nav items
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 992) {
                navMain.classList.remove('mobile-open');
                const icon = mobileToggle.querySelector('i');
                icon.className = 'fas fa-bars';
            }
        });
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-main') && !e.target.closest('.mobile-nav-toggle')) {
            navMain.classList.remove('mobile-open');
            if (mobileToggle) {
                const icon = mobileToggle.querySelector('i');
                icon.className = 'fas fa-bars';
            }
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            navMain.classList.remove('mobile-open');
            if (mobileToggle) {
                const icon = mobileToggle.querySelector('i');
                icon.className = 'fas fa-bars';
            }
        }
    });
});