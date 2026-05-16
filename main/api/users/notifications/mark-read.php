<?php
// mark-read.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $userId = $input['userId'] ?? $input['user_id'] ?? $_GET['userId'] ?? $_GET['user_id'] ?? null;
    if (empty($userId) && !empty($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }

    $notifIds = $input['notifIds'] ?? [];
    $notifId = $input['notifId'] ?? null;
    $markAll = filter_var($input['markAll'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!$userId || strlen($userId) !== 9) {
        http_response_code(400);
        exit(json_encode([
            'success' => false,
            'message' => 'Valid 9-character userId required or login is needed.'
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
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read=1 WHERE receiver_user_id = ? AND receiver_type = 'user' AND is_read = 0");
        mysqli_stmt_bind_param($stmt, 's', $userId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
    } elseif (!empty($notifIds)) {
        mysqli_query($conn, "CREATE TEMPORARY TABLE temp_notif_ids (id INT PRIMARY KEY)");
        foreach ($notifIds as $id) {
            $insertStmt = mysqli_prepare($conn, "INSERT IGNORE INTO temp_notif_ids (id) VALUES (?)");
            mysqli_stmt_bind_param($insertStmt, 'i', $id);
            mysqli_stmt_execute($insertStmt);
            mysqli_stmt_close($insertStmt);
        }
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read=1 WHERE receiver_user_id = ? AND receiver_type = 'user' AND id IN (SELECT id FROM temp_notif_ids)");
        mysqli_stmt_bind_param($stmt, 's', $userId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        mysqli_query($conn, "DROP TEMPORARY TABLE temp_notif_ids");
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read=1 WHERE receiver_user_id = ? AND receiver_type = 'user' AND id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $userId, $notifId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
    }

    $countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as unread FROM notifications WHERE receiver_user_id = ? AND receiver_type = 'user' AND is_read = 0");
    mysqli_stmt_bind_param($countStmt, 's', $userId);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    $countRow = mysqli_fetch_assoc($countResult);
    $unreadCount = $countRow['unread'] ?? 0;
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