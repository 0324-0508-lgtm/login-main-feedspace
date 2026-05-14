<?php
// get-notifications.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    session_start();
    $conn = mysqli_connect('localhost', 'root', '', 'db_feedspace');
    if (!$conn) {
        throw new Exception('DB Connection failed: ' . mysqli_connect_error());
    }
    mysqli_set_charset($conn, 'utf8mb4');

    $userId = $_GET['userId'] ?? $_GET['user_id'] ?? null;
    if (empty($userId) && !empty($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }

    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $unreadOnly = filter_var($_GET['unread'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!$userId || strlen($userId) !== 9) {
        http_response_code(400);
        exit(json_encode([
            'success' => false,
            'message' => 'Valid 9-character userId required or login is needed.'
        ]));
    }

    $where = "receiver_user_id = ? AND receiver_type = 'user'";
    if ($unreadOnly) {
        $where .= ' AND is_read = 0';
    }

    $query = "
        SELECT
            id AS notif_id,
            sender_admin_id,
            sender_user_id,
            receiver_user_id,
            title,
            message,
            type,
            is_read,
            DATE_FORMAT(created_at, '%H:%i %d %b') AS time_formatted,
            DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS timestamp
        FROM notifications
        WHERE $where
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sii', $userId, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $notificationsResult = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($notificationsResult)) {
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);

    $countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as unread FROM notifications WHERE receiver_user_id = ? AND receiver_type = 'user' AND is_read = 0");
    mysqli_stmt_bind_param($countStmt, 's', $userId);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $unreadRow = mysqli_fetch_assoc($countResult);
    $unreadCount = $unreadRow['unread'] ?? 0;
    mysqli_stmt_close($countStmt);

    $totalStmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM notifications WHERE receiver_user_id = ? AND receiver_type = 'user'");
    mysqli_stmt_bind_param($totalStmt, 's', $userId);
    mysqli_stmt_execute($totalStmt);
    $totalResult = mysqli_stmt_get_result($totalStmt);
    $totalRow = mysqli_fetch_assoc($totalResult);
    $totalCount = $totalRow['total'] ?? 0;
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