<?php
// Start session to check login status
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Get user role for conditional functionality
$user_role = $_SESSION['role'] ?? null;
$is_client = ($user_role === 'client');
$is_agent = ($user_role === 'agent');
$is_admin = ($user_role === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Omnes Immobilier - Home</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/home.css" rel="stylesheet">
    <link href="../assets/css/navigation.css" rel="stylesheet">
    <style>
      /* nav sits flat above hero, no blur */
      .navbar {
        position: absolute !important;
        top: 0; left: 0; right: 0;
        background-color: transparent !important;
        box-shadow: none !important;
        backdrop-filter: none !important;
        z-index: 1000;
      }
      /* strip any corner radius off the hero */
      .welcome-section {
        border-radius: 0 !important;
        overflow: hidden;
      }
      /* ensure nav links stay visible */
      .navbar .navbar-brand,
      .navbar .nav-link {
        color: #fff !important;
      }
      .navbar .nav-link:hover {
        color: #ddd !important;
      }

      /* remove left‐gold border on the Events container */
      .events-section > .container {
        border-left: none !important;
        box-shadow: none !important;
      }

      /* Carousel styles */
      .events-carousel-container {
        position: relative;
        margin: 0 auto;
        max-width: 800px;
      }
      .event-slide {
        display: none;
      }
      .event-slide.active {
        display: block;
      }
      .carousel-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background-color: rgba(255, 255, 255, 0.8);
        border: none;
        padding: 10px;
        cursor: pointer;
        z-index: 10;
      }
      .carousel-prev {
        left: 10px;
      }
      .carousel-next {
        right: 10px;
      }
      .carousel-indicators {
        text-align: center;
        margin-top: 10px;
      }
      .indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        margin: 0 5px;
        background-color: #ccc;
        border-radius: 50%;
        cursor: pointer;
      }
      .indicator.active {
        background-color: #007bff;
      }
    </style>
</head>
<body>
    <!-- Include Header -->
    <?php include '../includes/header.php'; ?>
    
    <!-- Include Updated Navigation -->
    <?php include '../includes/navigation.php'; ?>

    <main>
        <!-- Error Message Display -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="container mt-4">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="welcome-section">
                <div class="welcome-overlay">
                    <h1>Welcome to Omnes Immobilier</h1>
                    <p>Serving the Omnes Community. Browse, schedule and connect with our agents easily.</p>
                    <div class="welcome-actions mt-4">
                        <a href="#event-of-the-day" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-calendar-alt me-2"></i>Learn More
                        </a>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="../auth/register.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Join Us Today
                            </a>
                        <?php elseif ($is_agent): ?>
                            <a href="../agent/dashboard.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-chart-pie me-2"></i>My Dashboard
                            </a>
                        <?php elseif ($is_admin): ?>
                            <a href="../admin/dashboard.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-chart-line me-2"></i>Admin Dashboard
                            </a>
                        <?php else: ?>
                            <a href="../client/dashboard.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-chart-pie me-2"></i>My Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Event of the Week Section -->
        <section class="events-section" id="event-of-the-day">
            <div class="container">
                <h2 class="section-title text-center mb-5">
                    <i class="fas fa-calendar-star me-2 text-warning"></i>
                    Events of the Week
                </h2>
            </div>

            <!-- Events Carousel -->
            <div class="events-carousel-container">
                <div class="events-carousel">
                    <!-- Event 1 -->
                    <div class="event-slide active">
                        <section class="cimalpes-event-section">
                            <div class="cimalpes-event-images">
                                <img src="../assets/images/event1.png" alt="Event 1 Main" class="cimalpes-event-img-main">
                            </div>
                            <div class="cimalpes-event-content">
                                <div class="cimalpes-event-title">🏡 Open House</div>
                                <div class="cimalpes-event-meta">
                                    <span><span class="icon">📅</span>Saturday, 10am-4pm</span>
                                    <span><span class="icon">📍</span>10 Rue Sextius Michel</span>
                                </div>
                                <div class="cimalpes-event-description">
                                    <p>Join us for an exclusive open house featuring luxury apartments in the heart of Paris. Our expert agents will be available to answer all your questions.</p>
                                </div>
                                <button class="cimalpes-event-btn btn-sm" onclick="showEventDetails('open-house')">
                                    <i class="fas fa-info-circle me-1"></i>See Details
                                </button>
                            </div>
                        </section>
                    </div>

                    <!-- Event 2 -->
                    <div class="event-slide">
                        <section class="cimalpes-event-section">
                            <div class="cimalpes-event-images">
                                <img src="../assets/images/event2.png" alt="Event 2 Main" class="cimalpes-event-img-main">
                            </div>
                            <div class="cimalpes-event-content">
                                <div class="cimalpes-event-title">💼 Investment Seminar</div>
                                <div class="cimalpes-event-meta">
                                    <span><span class="icon">📅</span>Sunday, 2pm</span>
                                    <span><span class="icon">📍</span>Main Office</span>
                                </div>
                                <div class="cimalpes-event-description">
                                    <p>Learn about real estate investment opportunities with our financial experts. Perfect for first-time investors and experienced professionals.</p>
                                </div>
                                <button class="cimalpes-event-btn btn-sm" onclick="showEventDetails('investment-seminar')">
                                    <i class="fas fa-info-circle me-1"></i>See Details
                                </button>
                            </div>
                        </section>
                    </div>

                    <!-- Event 3 -->
                    <div class="event-slide">
                        <section class="cimalpes-event-section">
                            <div class="cimalpes-event-images">
                                <img src="../assets/images/event3.png" alt="Event 3 Main" class="cimalpes-event-img-main">
                            </div>
                            <div class="cimalpes-event-content">
                                <div class="cimalpes-event-title">🏢 Commercial Property Tour</div>
                                <div class="cimalpes-event-meta">
                                    <span><span class="icon">📅</span>Friday, 3pm-6pm</span>
                                    <span><span class="icon">📍</span>Business District</span>
                                </div>
                                <div class="cimalpes-event-description">
                                    <p>Discover the best commercial properties available for your business. Guided tours and expert advice from our commercial team.</p>
                                </div>
                                <button class="cimalpes-event-btn btn-sm" onclick="showEventDetails('commercial-tour')">
                                    <i class="fas fa-info-circle me-1"></i>See Details
                                </button>
                            </div>
                        </section>
                    </div>

                </div>

                <!-- Navigation Arrows -->
                <button class="carousel-arrow carousel-prev" onclick="changeSlide(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-arrow carousel-next" onclick="changeSlide(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>

                <!-- Carousel Indicators -->
                <div class="carousel-indicators">
                    <button class="indicator active" onclick="goToSlide(0)"></button>
                    <button class="indicator" onclick="goToSlide(1)"></button>
                    <button class="indicator" onclick="goToSlide(2)"></button>
                </div>
            </div>
        </section>

        <!-- Quick Actions Section -->
        <?php if (isset($_SESSION['user_id']) && $is_client): ?>
            <section class="quick-actions-section container my-5">
                <div class="row g-4 justify-content-center align-items-stretch"><!-- added align-items-stretch -->
                    <div class="col-md-4 d-flex"><!-- added d-flex -->
                        <div class="quick-action-card h-100 d-flex flex-column"><!-- added h-100 d-flex flex-column -->
                            <div class="quick-action-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h5>Search Properties</h5>
                            <p>Find your perfect home from our extensive collection</p>
                            <a href="../pages/explore.php" class="btn btn-outline-primary mt-auto"><!-- added mt-auto -->
                                Browse Now
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex">
                        <div class="quick-action-card h-100 d-flex flex-column">
                            <div class="quick-action-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h5>Book Appointment</h5>
                            <p>Schedule a viewing with our professional agents</p>
                            <a href="../client/dashboard.php?section=appointments" class="btn btn-outline-primary mt-auto">
                                Schedule
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex">
                        <div class="quick-action-card h-100 d-flex flex-column">
                            <div class="quick-action-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h5>My Favorites</h5>
                            <p>View and manage your saved properties</p>
                            <a href="../client/dashboard.php?section=favorites" class="btn btn-outline-primary mt-auto">
                                View
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        <?php elseif (isset($_SESSION['user_id']) && $is_agent): ?>
            <section class="quick-actions-section container my-5">
                <div class="row g-4 justify-content-center align-items-stretch">
                    <div class="col-md-4 d-flex">
                        <div class="quick-action-card h-100 d-flex flex-column">
                            <div class="quick-action-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h5>Explore Properties</h5>
                            <p>Browse all available properties in the market</p>
                            <a href="../pages/explore.php" class="btn btn-outline-primary mt-auto">
                                Browse Now
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex">
                        <div class="quick-action-card h-100 d-flex flex-column">
                            <div class="quick-action-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <h5>My Properties</h5>
                            <p>Manage your listed properties and add new ones</p>
                            <a href="../agent/manage_properties.php" class="btn btn-outline-primary mt-auto">
                                Manage
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex">
                        <div class="quick-action-card h-100 d-flex flex-column">
                            <div class="quick-action-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h5>Appointments</h5>
                            <p>View and manage your property appointments</p>
                            <a href="../agent/manage_appointments.php" class="btn btn-outline-primary mt-auto">
                                View
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Testimonials Section -->
        <section class="testimonials-section container mb-5">
            <h3 class="section-title text-center mb-4">
                <i class="fas fa-quote-left me-2 text-warning"></i>
                What Our Clients Say
            </h3>
            <div class="row g-4 justify-content-center">
                <div class="col-md-5">
                    <div class="testimonial-card">
                        <div class="testimonial-quote">"</div>
                        <p class="testimonial-text">Omnes Immobilier helped me find my dream apartment in Paris. The process was smooth and professional!</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">M</div>
                            <div>
                                <div class="fw-semibold">Marie D.</div>
                                <small class="text-muted">Client since 2023</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="testimonial-card">
                        <div class="testimonial-quote">"</div>
                        <p class="testimonial-text">Great service and friendly agents. Highly recommended for anyone looking to buy or rent.</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">J</div>
                            <div>
                                <div class="fw-semibold">Jean P.</div>
                                <small class="text-muted">Client since 2022</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section class="contact-section d-flex justify-content-center">
            <div class="contact-card">
                <h5 class="mb-3 contact-title">
                    <i class="fas fa-envelope me-2"></i>
                    <span class="footer-highlight">Contact us</span>
                </h5>
                <ul class="contact-list">
                    <li>
                        <i class="fas fa-envelope me-2"></i>
                        <strong>Email:</strong> 
                        <a href="mailto:contact@omnes-immobilier.com">contact@omnes-immobilier.com</a>
                    </li>
                    <li>
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <strong>Address:</strong> 10 Rue Sextius Michel, 75015 Paris, France
                    </li>
                    <li>
                        <i class="fas fa-phone me-2"></i>
                        <strong>Phone:</strong> +33 1 23 45 67 89
                    </li>
                </ul>
                <div class="contact-actions mt-3">
                    <a href="mailto:contact@omnes-immobilier.com" class="btn btn-success me-2">
                        <i class="fas fa-envelope me-1"></i>Email Us
                    </a>
                    <a href="tel:+33123456789" class="btn btn-outline-success">
                        <i class="fas fa-phone me-1"></i>Call Us
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="site-footer">
            <div class="container">
                <div class="row">
                    <div class="col-md-8">
                        <p>&copy; <?php echo date('Y'); ?> <span class="footer-highlight">Omnes Immobilier</span> — All rights reserved. ECE École d'Ingénieurs 2025.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <p>Powered by <span class="footer-highlight">ING2 Groupe 13</span>.</p>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../assets/js/home.js"></script>
        <script src="../assets/js/navigation.js"></script>

        <script>
            // Enhanced event details functionality
            function showEventDetails(eventType) {
                const eventDetails = {
                    'open-house': {
                        title: 'Open House Event',
                        description: 'Join us for an exclusive viewing of luxury apartments in central Paris. Our experienced agents will guide you through premium properties and answer all your questions.',
                        features: ['Free consultation', 'Property tours', 'Refreshments provided', 'No appointment needed'],
                        contact: 'For more info: contact@omnes-immobilier.com'
                    },
                    'investment-seminar': {
                        title: 'Real Estate Investment Seminar',
                        description: 'Learn the fundamentals of real estate investment with our expert financial advisors. Perfect for both beginners and experienced investors.',
                        features: ['Expert speakers', 'Investment strategies', 'Market analysis', 'Q&A session'],
                        contact: 'Register: seminars@omnes-immobilier.com'
                    },
                    'commercial-tour': {
                        title: 'Commercial Property Tour',
                        description: 'Discover the best commercial properties available for your business. Guided tours and expert advice from our commercial team.',
                        features: ['Business locations', 'Expert guidance', 'Networking opportunities', 'On-site Q&A'],
                        contact: 'Contact: business@omnes-immobilier.com'
                    }
                };

                const event = eventDetails[eventType];
                if (event) {
                    // Show modal or navigate to detail page
                    if (window.NavigationJS) {
                        window.NavigationJS.showNotification(`${event.title} - ${event.contact}`, 'info');
                    } else {
                        alert(`${event.title}\n\n${event.description}\n\n${event.contact}`);
                    }
                }
            }

            // Events Carousel Functionality
            let currentSlide = 0;
            const slides = document.querySelectorAll('.event-slide');
            const indicators = document.querySelectorAll('.indicator');
            const totalSlides = slides.length;

            function showSlide(index) {
                // Hide all slides
                slides.forEach(slide => slide.classList.remove('active'));
                indicators.forEach(indicator => indicator.classList.remove('active'));

                // Show current slide
                slides[index].classList.add('active');
                indicators[index].classList.add('active');
            }

            function changeSlide(direction) {
                currentSlide += direction;
                
                if (currentSlide >= totalSlides) {
                    currentSlide = 0;
                } else if (currentSlide < 0) {
                    currentSlide = totalSlides - 1;
                }
                
                showSlide(currentSlide);
            }

            function goToSlide(index) {
                currentSlide = index;
                showSlide(currentSlide);
            }

            // Auto-advance carousel every 5 seconds
            setInterval(() => {
                changeSlide(1);
            }, 5000);

            // Add smooth scrolling to anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        </script>
    </main>
</body>
</html>