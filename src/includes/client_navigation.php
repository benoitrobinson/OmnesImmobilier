<?php
// Remove session_start() since it's already started in the main file
?>

<!-- Navigation HTML -->
<nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
    <div class="container">
        <a class="navbar-brand" href="../client/index.php">
            <img src="../assets/images/logo.png" alt="Logo" class="d-inline-block align-text-top" style="height: 40px;">
            Omnes Immobilier
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../client/index.php">
                        <i class="fas fa-home me-2"></i>Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../client/properties.php">
                        <i class="fas fa-list me-2"></i>Properties
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../client/appointments.php">
                        <i class="fas fa-calendar-check me-2"></i>Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../client/messages.php">
                        <i class="fas fa-envelope me-2"></i>Messages
                        <?php if (isset($_SESSION['client_unread_messages']) && $_SESSION['client_unread_messages'] > 0): ?>
                            <span class="badge bg-warning"><?= $_SESSION['client_unread_messages'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../client/profile.php">
                        <i class="fas fa-user me-2"></i>Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
    /* Navigation custom styles */
    .navbar {
        padding: 0.75rem 1rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c5aa0;
    }

    .navbar-nav .nav-link {
        font-size: 0.9rem;
        color: #333;
        padding: 0.5rem 1rem;
        transition: color 0.3s;
    }

    .navbar-nav .nav-link:hover {
        color: #2c5aa0;
    }

    .navbar-nav .nav-link i {
        min-width: 1.5rem;
        text-align: center;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.2rem 0.6rem;
        border-radius: 1rem;
    }
</style>