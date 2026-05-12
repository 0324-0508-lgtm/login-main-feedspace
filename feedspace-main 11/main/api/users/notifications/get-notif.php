<?php
// get-notifications.php - FIXED VERSION (no warnings!)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Database connection
    $conn = mysqli_connect('localhost', 'root', '', 'db_feedspace');
    if (!$conn) {
        throw new Exception('DB Connection failed: ' . mysqli_connect_error());
    }
    mysqli_set_charset($conn, 'utf8mb4');
    
    // Get parameters
    $userId = $_GET['userId'] ?? null;
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    $unreadOnly = filter_var($_GET['unread'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    if (!$userId || strlen($userId) !== 9) {
        http_response_code(400);
        exit(json_encode([
            'success' => false,
            'message' => 'Valid 9-character userId required'
        ]));
    }
    
    // Build query params
    $where = "user_id = ?";
    $bindParams = [$userId];
    $types = 's';
    
    if ($unreadOnly) {
        $where .= " AND is_read = 0";
    }
    
    // Main notifications query - FIXED: Use variables only
    $query = "
        SELECT 
            notif_id,
            type,
            post_id,
            comment_id,
            community_id,
            actor_id,
            actor_username,
            message,
            is_read,
            DATE_FORMAT(created_at, '%H:%i %d %b') as time_formatted,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as timestamp
        FROM notifications 
        WHERE $where
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    $bindParams[] = $limit;
    $bindParams[] = $offset;
    $types .= 'ii'; // s + i + i
    
    mysqli_stmt_bind_param($stmt, $types, ...$bindParams);
    mysqli_stmt_execute($stmt);
    $notificationsResult = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($notificationsResult)) {
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    // Unread count
    $countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($countStmt, 's', $userId);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $unreadRow = mysqli_fetch_assoc($countResult);
    $unreadCount = $unreadRow['unread'];
    mysqli_stmt_close($countStmt);
    
    // Total count
    $totalStmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
    mysqli_stmt_bind_param($totalStmt, 's', $userId);
    mysqli_stmt_execute($totalStmt);
    $totalResult = mysqli_stmt_get_result($totalStmt);
    $totalRow = mysqli_fetch_assoc($totalResult);
    $totalCount = $totalRow['total'];
    mysqli_stmt_close($totalStmt);
    
    mysqli_close($conn);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'unreadCount' => (int)$unreadCount,
            'totalCount' => (int)$totalCount,
            'currentPage' => floor($offset / $limit) + 1,
            'hasMore' => count($notifications) === $limit,
            'userId' => $userId
        ]
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