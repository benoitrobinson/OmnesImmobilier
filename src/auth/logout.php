<?php
session_start();
session_destroy();
header('Location: login.php?message=' . urlencode('You have been logged out successfully.'));
exit();
?>