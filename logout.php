<?php
// Start the session
session_start();

session_unset();


// Destroy the session
session_destroy();

// Redirect to login page or home page
header("Location: login.php"); // Change 'login.php' to your login page if needed
exit();
?>
