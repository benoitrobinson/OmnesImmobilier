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
function toggleFavorite(button, propertyId) {
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
                button.classList.remove('btn-outline-success');
                button.classList.add('btn-danger', 'favorited');
                button.innerHTML = '<i class="fas fa-heart me-1"></i>Remove from Favorites';
            } else {
                button.classList.remove('btn-danger', 'favorited');
                button.classList.add('btn-outline-success');
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
function showPropertyDetails(property, isFavorited = false) {
    document.getElementById('propertyModalTitle').textContent = property.title;
    
    // Get images
    let images = [];
    if (property.images) {
        try {
            images = JSON.parse(property.images);
        } catch(e) {
            images = [`../assets/images/property${property.id}-1.jpg`];
        }
    } else {
        images = [`../assets/images/property${property.id}-1.jpg`];
    }
    
    // Check if user is logged in
    const isLoggedIn = typeof userLoggedIn !== 'undefined' && userLoggedIn;
    
    const modalBody = document.getElementById('propertyModalBody');
    
    // First, display the modal with basic info
    modalBody.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div class="property-image-container mb-3" style="height: 250px;" data-images='${JSON.stringify(images)}' data-current="0">
                    <img src="${images[0]}" class="property-main-img" style="border-radius: 8px;" onerror="this.src='../assets/images/placeholder.jpg'">
                    ${images.length > 1 ? `
                        <button class="property-arrow left-arrow" onclick="changeImage(this, -1)">&#8592;</button>
                        <button class="property-arrow right-arrow" onclick="changeImage(this, 1)">&#8594;</button>
                    ` : ''}
                </div>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-map-marker-alt me-2"></i>Location</h6>
                <p>${property.address_line1}, ${property.city}</p>
                
                <h6><i class="fas fa-info-circle me-2"></i>Property Type</h6>
                <p class="text-capitalize">${property.property_type}</p>
                
                <h6><i class="fas fa-euro-sign me-2"></i>Price</h6>
                <p class="h4 text-primary">€${new Intl.NumberFormat().format(property.price)}</p>
                
                <h6><i class="fas fa-calendar me-2"></i>Listed</h6>
                <p>${new Date(property.created_at).toLocaleDateString()}</p>
            </div>
        </div>
        <hr>
        <h6><i class="fas fa-file-alt me-2"></i>Description</h6>
        <p>${property.description}</p>
        
        <!-- Referent agent section with loading state -->
        <div class="referent-agent-line mb-3 p-2 bg-light rounded" id="agentInfo">
            <i class="fas fa-user-tie me-2" style="color: #d4af37;"></i>
            <strong>Referent Agent:</strong> 
            <span class="ms-1">Loading...</span>
        </div>
        
        <div class="d-grid">
            ${isLoggedIn ? `
                <button type="button" 
                        class="btn favorite-btn ${isFavorited ? 'btn-danger favorited' : 'btn-outline-success'}"
                        onclick="toggleFavorite(this, ${property.id})">
                    <i class="fas fa-heart me-1"></i>${isFavorited ? 'Remove from Favorites' : 'Add to Favorites'}
                </button>
            ` : `
                <a href="../auth/login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-1"></i>Login to Add Favorites
                </a>
            `}
        </div>
    `;
    
    // Fetch agent name if agent_id exists
    if (property.agent_id) {
        fetch(`../ajax/get_agent.php?agent_id=${property.agent_id}`)
            .then(response => response.json())
            .then(data => {
                const agentInfoElement = document.getElementById('agentInfo');
                if (data.success && data.agent) {
                    agentInfoElement.innerHTML = `
                        <i class="fas fa-user-tie me-2" style="color: #d4af37;"></i>
                        <strong>Referent Agent:</strong> 
                        <span class="ms-1">${data.agent.first_name} ${data.agent.last_name}</span>
                    `;
                } else {
                    agentInfoElement.innerHTML = `
                        <i class="fas fa-user-tie me-2" style="color: #d4af37;"></i>
                        <strong>Referent Agent:</strong> 
                        <span class="ms-1">Not assigned</span>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching agent:', error);
                const agentInfoElement = document.getElementById('agentInfo');
                agentInfoElement.innerHTML = `
                    <i class="fas fa-user-tie me-2" style="color: #d4af37;"></i>
                    <strong>Referent Agent:</strong> 
                    <span class="ms-1">Error loading agent info</span>
                `;
            });
    } else {
        // No agent assigned
        const agentInfoElement = document.getElementById('agentInfo');
        agentInfoElement.innerHTML = `
            <i class="fas fa-user-tie me-2" style="color: #d4af37;"></i>
            <strong>Referent Agent:</strong> 
            <span class="ms-1">Not assigned</span>
        `;
    }
}

