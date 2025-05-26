<?php
?>
<link href="../assets/css/navigation.css" rel="stylesheet">
<nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
  <div class="container-fluid" style="padding:0;">
    <a class="navbar-brand" href="../pages/home.php">
      <img src="../assets/images/logo1.png" alt="Logo" style="height:60px; max-height:60px; object-fit:contain;">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) == 'home.php') echo ' active'; ?>" href="../pages/home.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?php if(basename($_SERVER['PHP_SELF']) == 'explore.php') echo ' active'; ?>" href="../pages/explore.php">Explore</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">Appointments</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#">My account</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<script src="../assets/js/navigation.js"></script>