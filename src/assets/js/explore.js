// Image slider functionality
function changeImage(button, direction) {
    const container = button.closest('.property-image-container');
    const img = container.querySelector('.property-main-img');
    const images = JSON.parse(container.dataset.images);
    let currentIndex = parseInt(container.dataset.current);
    
    currentIndex += direction;
    
    if (currentIndex >= images.length) currentIndex = 0;
    if (currentIndex < 0) currentIndex = images.length - 1;
    
    container.dataset.current = currentIndex;
    img.src = images[currentIndex];
}

// Toggle favorite function
function toggleFavorite(button) {
    // Check if user is an agent or admin - disable functionality
    if ((typeof isAgent !== 'undefined' && isAgent) || (typeof isAdmin !== 'undefined' && isAdmin)) {
        showNotification('Favorites are not available for agent or admin accounts.', 'error');
        return;
    }
    
    // Check if user is logged in and is a client
    if (!userLoggedIn || (typeof isClient !== 'undefined' && !isClient)) {
        showNotification('Please log in as a client to use favorites.', 'error');
        return;
    }
    
    const propertyId = button.dataset.propertyId;
    const isCurrentlyFavorited = button.classList.contains('favorited');
    const action = isCurrentlyFavorited ? 'remove' : 'add';
    
    // Disable button during request
    button.disabled = true;
    
    fetch('../ajax/favorites.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `property_id=${propertyId}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'added') {
                button.classList.remove('btn-outline-danger');
                button.classList.add('btn-danger', 'favorited');
                button.innerHTML = '<i class="fas fa-heart me-1"></i>Remove from Favorites';
            } else {
                button.classList.remove('btn-danger', 'favorited');
                button.classList.add('btn-outline-danger');
                button.innerHTML = '<i class="fas fa-heart me-1"></i>Add to Favorites';
            }
            
            // Show success message
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    })
    .finally(() => {
        button.disabled = false;
    });
}

// Show notification function
function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

// Show property details in modal
function showPropertyDetails(property, isFavorited) {
    const modalTitle = document.getElementById('propertyModalTitle');
    const modalBody = document.getElementById('propertyModalBody');
    
    modalTitle.textContent = property.title;
    
    const images = property.images ? JSON.parse(property.images) : [
        `../assets/images/property${property.id}-1.jpg`,
        `../assets/images/property${property.id}-2.jpg`,
        `../assets/images/property${property.id}-3.jpg`
    ];

    const actionsHTML = () => {
        if ((typeof isAgent !== 'undefined' && isAgent === true) || 
            (typeof isAdmin !== 'undefined' && isAdmin === true)) {
            // Agent or Admin view - no booking or favorites
            const roleText = isAdmin ? 'administrator' : 'agent';
            return `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You are viewing this property as an ${roleText}. Booking and favorites are not available for ${roleText} accounts.
                </div>
                <div class="d-grid">
                    <button class="btn btn-secondary" disabled>
                        <i class="fas fa-user-${isAdmin ? 'shield' : 'tie'} me-1"></i>${isAdmin ? 'Admin' : 'Agent'} View Only
                    </button>
                </div>
            `;
        } else if (userLoggedIn && typeof isClient !== 'undefined' && isClient === true) {
            // Client view - restore original styling
            return `
                <div class="d-grid gap-2">
                    <a href="book_appointment.php?property_id=${property.id}&agent_id=${property.agent_id}" 
                       class="btn btn-primary">
                        <i class="fas fa-calendar-alt me-1"></i>Book Appointment
                    </a>
                    <button class="btn btn-outline-danger favorite-btn ${isFavorited ? 'favorited' : ''}" 
                            data-property-id="${property.id}">
                        <i class="fas fa-heart me-1"></i>
                        ${isFavorited ? 'Remove from Favorites' : 'Add to Favorites'}
                    </button>
                </div>
            `;
        } else {
            // Not logged in - no favorites
            return `
                <div class="d-grid gap-2">
                    <a href="../auth/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-1"></i>Login to Book Appointment
                    </a>
                </div>
            `;
        }
    };
    
    // Display debugging info to console about user role
    console.log("User role debug:", { 
        isLoggedIn: userLoggedIn, 
        userRole: typeof userRole !== 'undefined' ? userRole : 'unknown',
        isAgent: typeof isAgent !== 'undefined' ? isAgent : 'unknown',
        isAdmin: typeof isAdmin !== 'undefined' ? isAdmin : 'unknown',
        isClient: typeof isClient !== 'undefined' ? isClient : 'unknown'
    });

    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div id="propertyCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        ${images.map((img, index) => `
                            <div class="carousel-item ${index === 0 ? 'active' : ''}">
                                <img src="${img}" class="d-block w-100" alt="Property Image" 
                                     style="height: 300px; object-fit: cover;"
                                     onerror="this.src='../assets/images/placeholder.jpg'">
                            </div>
                        `).join('')}
                    </div>
                    ${images.length > 1 ? `
                        <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>
                    ` : ''}
                </div>
            </div>
            <div class="col-md-6">
                <h4 class="text-primary">€${parseInt(property.price).toLocaleString()}</h4>
                <p><strong>Type:</strong> ${property.property_type.charAt(0).toUpperCase() + property.property_type.slice(1)}</p>
                <p><strong>Location:</strong> ${property.city}</p>
                <p><strong>Address:</strong> ${property.address_line1}</p>
                ${property.bedrooms ? `<p><strong>Bedrooms:</strong> ${property.bedrooms}</p>` : ''}
                ${property.living_area ? `<p><strong>Living Area:</strong> ${property.living_area} m²</p>` : ''}
                <p><strong>Description:</strong> ${property.description}</p>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                ${actionsHTML()}
            </div>
        </div>
    `;

    // Add event listener for favorite button (only for clients)
    if (typeof isClient !== 'undefined' && isClient && userLoggedIn) {
        const favoriteBtn = modalBody.querySelector('.favorite-btn');
        if (favoriteBtn) {
            favoriteBtn.addEventListener('click', function() {
                toggleFavorite(this);
            });
        }
    }
}

