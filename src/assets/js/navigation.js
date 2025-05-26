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
