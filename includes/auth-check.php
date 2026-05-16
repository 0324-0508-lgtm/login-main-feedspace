<?php
// ============================================================
//  auth-check.php
//  Include in any PHP page that requires authentication.
//  Redirects to login if session is missing.
// ============================================================
require_once __DIR__ . '/../config/session.php';

if (!isLoggedIn()) {
    header('Location: /fixed-feedspace/auth/login.php');
    exit();
}
