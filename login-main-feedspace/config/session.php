<?php
// ============================================================
//  config/session.php
//  Central session helpers for FeedSpace.
//  - Starts the PHP session
//  - Provides isLoggedIn() / currentUserId()
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentUserId(): string {
    $id = $_SESSION['user_id'] ?? '';
    return is_string($id) ? trim($id) : '';
}

function isLoggedIn(): bool {
    return currentUserId() !== '';
}

