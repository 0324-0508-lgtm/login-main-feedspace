<?php
// Wrapper to set up test session for development
session_start();

// If no session, create a test user
if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'test_user_001';
    $_SESSION['first_name'] = 'Kim';
    $_SESSION['last_name'] = 'Ballebar';
}

// Now include the HTML file
include 'main-feed.html';
?>
