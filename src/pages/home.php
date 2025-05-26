<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Omnes Immobilier - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/home.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
<?php include '../includes/navigation.php'; ?>

<div class="welcome-section">
    <div class="welcome-overlay">
        <h1>Welcome to Omnes Immobilier</h1>
        <p>Serving the Omnes Community. Browse, schedule and connect with our agents easily.</p>
        <a href="#event-of-the-day" class="btn btn-primary mt-3">Learn More</a>
    </div>
</div>

<!-- Cimalpes-inspired Event 1: Images left, text right -->
<section class="cimalpes-event-section">
    <div class="cimalpes-event-images">
        <img src="../assets/images/event1.png" alt="Event 1 Main" class="cimalpes-event-img-main">
        <img src="../assets/images/event1.png" alt="Event 1 Secondary" class="cimalpes-event-img-secondary">
    </div>
    <div class="cimalpes-event-content">
        <div class="cimalpes-event-title">🏡 Open House</div>
        <div class="cimalpes-event-meta">
            <span><span class="icon">📅</span>Saturday, 10am-4pm</span>
            <span><span class="icon">📍</span>10 Rue Sextius Michel</span>
        </div>
        <button class="cimalpes-event-btn btn-sm">See Details</button>
    </div>
</section>

<!-- Cimalpes-inspired Event 2: Images right, text left -->
<section class="cimalpes-event-section" style="flex-direction: row-reverse;">
    <div class="cimalpes-event-images">
        <img src="../assets/images/event2.png" alt="Event 2 Main" class="cimalpes-event-img-main">
        <img src="../assets/images/event2.png" alt="Event 2 Secondary" class="cimalpes-event-img-secondary">
    </div>
    <div class="cimalpes-event-content">
        <div class="cimalpes-event-title">💼 Investment Seminar</div>
        <div class="cimalpes-event-meta">
            <span><span class="icon">📅</span>Sunday, 2pm</span>
            <span><span class="icon">📍</span>Main Office</span>
        </div>
        <button class="cimalpes-event-btn btn-sm">See Details</button>
    </div>
</section>

<section class="testimonials-section container mb-5">
    <h3 class="section-title text-center mb-4">What Our Clients Say</h3>
    <div class="row g-4 justify-content-center">
        <div class="col-md-5">
            <div class="testimonial-card">
                <div class="testimonial-quote">“</div>
                <p class="testimonial-text">Omnes Immobilier helped me find my dream apartment in Paris. The process was smooth and professional!</p>
                <div class="testimonial-author">— Marie D.</div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="testimonial-card">
                <div class="testimonial-quote">“</div>
                <p class="testimonial-text">Great service and friendly agents. Highly recommended for anyone looking to buy or rent.</p>
                <div class="testimonial-author">— Jean P.</div>
            </div>
        </div>
    </div>
</section>

<section class="contact-section d-flex justify-content-center">
    <div class="contact-card">
        <h5 class="mb-3 contact-title"><span class="footer-highlight">Contact us</span></h5>
        <ul class="contact-list">
            <li><strong>Email:</strong> <a href="mailto:contact@omnes-immobilier.com">contact@omnes-immobilier.com</a></li>
            <li><strong>Address:</strong> 10 Rue Sextius Michel, 75015 Paris, France</li>
            <li><strong>Phone:</strong> +33 1 23 45 67 89</li>
        </ul>
        <a href="mailto:contact@omnes-immobilier.com" class="btn btn-success mt-2">Email Us</a>
    </div>
</section>

<footer class="site-footer">
    &copy; <?php echo date('Y'); ?> <span class="footer-highlight">Omnes Immobilier</span> — All rights reserved. ECE École d'Ingénieurs 2025. Powered by <span class="footer-highlight">ING2 Groupe 13</span>.
</footer>
<script src="../assets/js/home.js"></script>
</body>
</html>