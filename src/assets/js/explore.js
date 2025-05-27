document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.property-image-slider').forEach(function(slider) {
        const images = JSON.parse(slider.getAttribute('data-images'));
        const mainImg = slider.querySelector('.property-main-img');
        const leftArrow = slider.querySelector('.left-arrow');
        const rightArrow = slider.querySelector('.right-arrow');
        let currentIndex = 0;

        // Show arrows on hover
        slider.addEventListener('mouseenter', function() {
            leftArrow.style.display = 'block';
            rightArrow.style.display = 'block';
        });
        slider.addEventListener('mouseleave', function() {
            leftArrow.style.display = 'none';
            rightArrow.style.display = 'none';
        });

        // Arrow click events
        leftArrow.addEventListener('click', function(e) {
            e.stopPropagation();
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            mainImg.src = images[currentIndex];
        });
        rightArrow.addEventListener('click', function(e) {
            e.stopPropagation();
            currentIndex = (currentIndex + 1) % images.length;
            mainImg.src = images[currentIndex];
        });
    });
});
