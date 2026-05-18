<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isUserBanned($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT id FROM user_bans 
        WHERE user_id = ? AND (expires_at > NOW() OR expires_at IS NULL)
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return !empty($result);
}
?>