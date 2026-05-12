<?php
// Get list of users who liked a profile
session_start();
include '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$profile_user_id = $_POST['profile_user_id'] ?? '';
$page = max(1, intval($_POST['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

if (empty($profile_user_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Profile user_id required']);
    exit();
}

// Get people who liked this profile
$stmt = $conn->prepare("
    SELECT 
        ul.liked_user_id, u.first_name, u.last_name, u.profile_picture,
        ul.created_at
    FROM user_likes ul
    JOIN users u ON ul.liked_user_id = u.user_id
    WHERE ul.user_id = ?
    ORDER BY ul.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("sii", $profile_user_id, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$likers = [];
while ($liker = $result->fetch_assoc()) {
    $liker['profile_picture'] = $liker['profile_picture'] ? 
        "http://localhost/uploads/profiles/" . $liker['profile_picture'] : 
        "http://localhost/assets/default.png";
    $liker['full_name'] = trim($liker['first_name'] . ' ' . $liker['last_name']);
    $likers[] = $liker;
}

echo json_encode([
    'success' => true,
    'likers' => $likers,
    'pagination' => [
        'page' => $page,
        'limit' => $limit
    ]
]);
?>