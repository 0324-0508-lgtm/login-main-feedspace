<?php
session_start();
include '../../../../config/db.php';

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // Show reset form
    $token = $_GET['token'] ?? '';
    $user_id = $_GET['user_id'] ?? '';
    ?>
    <!DOCTYPE html>
    <html>
    <form method="POST" action="">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
        <input type="password" name="new_password" placeholder="New Password" required minlength="8">
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="submit">Reset Password</button>
    </form>
    <?php
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /login.php");
    exit();
}

$user_id = $_POST['user_id'] ?? '';
$token = $_POST['token'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate
if (empty($user_id) || empty($token) || empty($new_password)) {
    die("Invalid request");
}

if ($new_password !== $confirm_password) {
    die("Passwords don't match");
}

if (strlen($new_password) < 8) {
    die("Password must be 8+ characters");
}

// Verify token
$stmt = $conn->prepare("SELECT id FROM password_reset WHERE user_id = ? AND token = ? AND expires_at > NOW()");
$stmt->bind_param("ss", $user_id, $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid or expired token");
}

// Update password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
$stmt->bind_param("ss", $hashed_password, $user_id);
$stmt->execute();

// Cleanup
$conn->query("DELETE FROM password_reset WHERE user_id = " . $conn->real_escape_string($user_id));

$_SESSION['user_id'] = $user_id; // Auto-login
header("Location: /dashboard.php?reset=success");
exit("Password reset successful!");
?>