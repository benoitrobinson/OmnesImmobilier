<?php
// Start session to check login status
session_start();
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
</head>
<body>
    <!-- Include Header -->
    <?php include '../includes/header.php'; ?>
    
    <!-- Include Updated Navigation -->
    <?php include '../includes/navigation.php'; ?>

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
                <?php else: ?>
                    <a href="../client/dashboard.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-chart-pie me-2"></i>My Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Event of the Week Section -->
    <section class="events-section" id="event-of-the-day">
        <div class="container">
            <h2 class="section-title text-center mb-5">
                <i class="fas fa-calendar-star me-2 text-warning"></i>
                Events of the Week
            </h2>
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
                <div class="cimalpes-event-description">
                    <p>Join us for an exclusive open house featuring luxury apartments in the heart of Paris. Our expert agents will be available to answer all your questions.</p>
                </div>
                <button class="cimalpes-event-btn btn-sm" onclick="showEventDetails('open-house')">
                    <i class="fas fa-info-circle me-1"></i>See Details
                </button>
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
                <div class="cimalpes-event-description">
                    <p>Learn about real estate investment opportunities with our financial experts. Perfect for first-time investors and experienced professionals.</p>
                </div>
                <button class="cimalpes-event-btn btn-sm" onclick="showEventDetails('investment-seminar')">
                    <i class="fas fa-info-circle me-1"></i>See Details
                </button>
            </div>
        </section>
    </section>

    <!-- Quick Actions Section -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <section class="quick-actions-section container my-5">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h5>Search Properties</h5>
                        <p>Find your perfect home from our extensive collection</p>
                        <a href="../pages/explore.php" class="btn btn-outline-primary">Browse Now</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h5>Book Appointment</h5>
                        <p>Schedule a viewing with our professional agents</p>
                        <a href="../client/dashboard.php?section=appointments" class="btn btn-outline-primary">Schedule</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h5>My Favorites</h5>
                        <p>View and manage your saved properties</p>
                        <a href="../client/dashboard.php?section=favorites" class="btn btn-outline-primary">View</a>
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

    <!-- Additional styles for enhanced home page -->
    <style>
        .quick-action-card {
            text-align: center;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            background: white;
            border: 1px solid rgba(212, 175, 55, 0.1);
        }

        .quick-action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .quick-action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }

        .welcome-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .author-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.75rem;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
        }

        .contact-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .events-section {
            padding: 3rem 0;
        }

        .cimalpes-event-description {
            margin: 1rem 0;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .welcome-actions {
                flex-direction: column;
                align-items: center;
            }

            .contact-actions {
                flex-direction: column;
            }

            .quick-action-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</body>
</html>