<?php
// Secure user logout
session_start();

// Clear all session data
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        // Expire in the past to delete the cookie
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Clear session regeneration
session_write_close();

// Redirect to login (security best practice)
header("Location: /login.php");
exit("Logout successful! <a href='/login.php'>Click here if not redirected</a>");
?>