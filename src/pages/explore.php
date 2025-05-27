<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Explore Properties - Omnes Immobilier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/explore.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>
<!-- Hero Section (like home) -->
<div class="explore-hero-bg">
    <div class="explore-hero-overlay"></div>
    <div class="explore-hero-content">
        <h1>Welcome to our Explore Section</h1>
        <p>Browse all the properties you are looking for and discover your next dream home or investment.</p>
    </div>
</div>

<div class="container mt-5 pt-5">

    <!-- Search Menu -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <form class="d-flex justify-content-center align-items-center gap-3 bg-light p-4 rounded shadow-sm" style="flex-wrap: wrap;">
                <!-- Location Dropdown -->
                <div>
                    <label for="location" class="form-label mb-1">Location</label>
                    <select class="form-select" id="location" name="location">
                        <option value="">Try "Paris"</option>
                        <option value="paris">Paris</option>
                        <option value="saint-tropez">Saint Tropez</option>
                        <option value="nice">Nice</option>
                        <option value="courchevel">Courchevel</option>
                        <option value="lyon">Lyon</option>
                        <option value="bordeaux">Bordeaux</option>
                    </select>
                </div>
                <!-- Listing Type Dropdown -->
                <div>
                    <label for="listingType" class="form-label mb-1">Listing Type</label>
                    <select class="form-select" id="listingType" name="listingType">
                        <option value="">Residential, Commercial</option>
                        <option value="residential">Residential Real Estate</option>
                        <option value="commercial">Commercial Real Estate</option>
                        <option value="land">Land</option>
                        <option value="apartment-rent">Apartment for Rent</option>
                        <option value="auction">Auction Properties</option>
                    </select>
                </div>
                <!-- Budget Input -->
                <div>
                    <label for="budget" class="form-label mb-1">Budget (€)</label>
                    <input type="number" class="form-control" id="budget" name="budget" min="0" placeholder="Enter budget" pattern="\d*">
                </div>
                <!-- Search Button -->
                <div class="align-self-end">
                    <button type="submit" class="btn btn-primary mt-2">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Featured Properties -->
    <div class="mb-5 featured-properties">
        <h3>Featured Properties</h3>
        <div class="row">
            <!-- All properties use property1.jpg -->
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 1" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Modern Apartment</h5>
                        <p class="card-text">2 bed · 1 bath · 70m² · Paris 15th</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 2" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Family House</h5>
                        <p class="card-text">4 bed · 3 bath · 150m² · Paris 16th</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 3" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Cozy Studio</h5>
                        <p class="card-text">1 bed · 1 bath · 35m² · Paris 7th</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 4" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Luxury Villa</h5>
                        <p class="card-text">5 bed · 4 bath · 300m² · Saint Tropez</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 5" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Beachfront Condo</h5>
                        <p class="card-text">3 bed · 2 bath · 120m² · Nice</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 6" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Mountain Chalet</h5>
                        <p class="card-text">4 bed · 2 bath · 180m² · Courchevel</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 7" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">City Loft</h5>
                        <p class="card-text">2 bed · 2 bath · 90m² · Lyon</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 8" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Historic Mansion</h5>
                        <p class="card-text">6 bed · 5 bath · 400m² · Bordeaux</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 9" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Countryside Cottage</h5>
                        <p class="card-text">3 bed · 2 bath · 110m² · Lyon</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 10" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Modern Office Space</h5>
                        <p class="card-text">Open space · 250m² · Paris 8th</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 11" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Retail Storefront</h5>
                        <p class="card-text">Ground floor · 80m² · Bordeaux</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card property-card position-relative">
                    <div class="property-image-slider" data-images='["../assets/images/property1.jpg","../assets/images/property2.jpg","../assets/images/property3.jpg","../assets/images/property4.jpg","../assets/images/property5.jpg"]' style="position:relative;">
                        <img src="../assets/images/property1.jpg" class="card-img-top property-main-img" alt="Property 12" data-index="0">
                        <button class="property-arrow left-arrow" style="display:none;position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8592;
                        </button>
                        <button class="property-arrow right-arrow" style="display:none;position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);border:none;border-radius:50%;width:32px;height:32px;color:#fff;z-index:2;">
                            &#8594;
                        </button>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Auction Apartment</h5>
                        <p class="card-text">2 bed · 1 bath · 60m² · Paris 10th</p>
                        <a href="#" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/explore.js"></script>
</body>
</html>