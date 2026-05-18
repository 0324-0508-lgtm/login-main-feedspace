<?php
// ============================================================
//  config/session.php
//  Central session helpers for FeedSpace.
//  - Starts the PHP session
//  - Provides isLoggedIn() / currentUserId()
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    // Ensure the session cookie is consistently sent across normal app routes
    // (including /main/api/*).
    // Note: on Windows/XAMPP with default HTTP, 'secure' must be false.
    $cookieParams = session_get_cookie_params();

    // If the app is served under a subdirectory, keep the cookie path broad enough.
    // In your case the app root is document root; using '/' is safest.
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'] ?? 0,
        'path' => '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}


function currentUserId(): string {
    $id = $_SESSION['user_id'] ?? '';
    return is_string($id) ? trim($id) : '';
}

function isLoggedIn(): bool {
    return currentUserId() !== '';
}

