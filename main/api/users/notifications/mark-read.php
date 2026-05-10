<?php
// mark-read.php - Feedspace Production API
// Marks single or multiple notifications as read

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Database
    $conn = mysqli_connect('localhost', 'root', '', 'db_feedspace');
    if (!$conn) {
        throw new Exception('DB Connection failed: ' . mysqli_connect_error());
    }
    mysqli_set_charset($conn, 'utf8mb4');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['userId'] ?? $_GET['userId'] ?? null;
    $notifIds = $input['notifIds'] ?? []; // Array of IDs
    $notifId = $input['notifId'] ?? null;  // Single ID
    $markAll = filter_var($input['markAll'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    if (!$userId || strlen($userId) !== 9) {
        http_response_code(400);
        exit(json_encode([
            'success' => false,
            'message' => 'Valid 9-character userId required'
        ]));
    }
    
    if (!$notifId && empty($notifIds) && !$markAll) {
        http_response_code(400);
        exit(json_encode([
            'success' => false,
            'message' => 'Specify notifId, notifIds array, or markAll=true'
        ]));
    }
    
    $affected = 0;
    
    if ($markAll) {
        // Mark ALL unread as read
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0");
        mysqli_stmt_bind_param($stmt, 's', $userId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
    } elseif (!empty($notifIds)) {
        // Mark multiple by IDs - using temporary table approach for safety
        // Create temp table for IDs
        mysqli_query($conn, "CREATE TEMPORARY TABLE temp_notif_ids (id INT PRIMARY KEY)");
        
        $placeholders = [];
        foreach ($notifIds as $id) {
            $placeholders[] = "(?)";
            $insertStmt = mysqli_prepare($conn, "INSERT IGNORE INTO temp_notif_ids (id) VALUES (?)");
            mysqli_stmt_bind_param($insertStmt, 'i', $id);
            mysqli_stmt_execute($insertStmt);
            mysqli_stmt_close($insertStmt);
        }
        
        $stmt = mysqli_prepare($conn, "
            UPDATE notifications 
            SET is_read=1 
            WHERE user_id=? AND notif_id IN (SELECT id FROM temp_notif_ids)
        ");
        mysqli_stmt_bind_param($stmt, 's', $userId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        
        // Clean up temp table
        mysqli_query($conn, "DROP TEMPORARY TABLE temp_notif_ids");
    } else {
        // Mark single
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read=1 WHERE user_id=? AND notif_id=?");
        mysqli_stmt_bind_param($stmt, 'si', $userId, $notifId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // Get updated unread count
    $countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as unread FROM notifications WHERE user_id=? AND is_read=0");
    mysqli_stmt_bind_param($countStmt, 's', $userId);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countRow = mysqli_fetch_assoc($countResult);
    $unreadCount = $countRow['unread'];
    mysqli_stmt_close($countStmt);
    
    mysqli_close($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Notifications marked as read',
        'affectedRows' => $affected,
        'unreadCount' => (int)$unreadCount,
        'userId' => $userId
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) mysqli_close($conn);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}
?>