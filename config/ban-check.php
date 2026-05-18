<?php
// ban-check.php - Check if user is banned

function isUserBanned($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT id FROM user_bans 
        WHERE user_id = ? AND (expires_at > NOW() OR expires_at IS NULL)
        LIMIT 1
    ");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}
?>
