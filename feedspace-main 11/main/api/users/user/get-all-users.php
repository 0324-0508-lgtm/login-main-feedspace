<?php
session_start();
include '../../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$page = max(1, intval($_POST['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$search = trim($_POST['search'] ?? '');

$where = "1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
    $types = "sss";
}

$stmt = $conn->prepare("
    SELECT 
        user_id, first_name, last_name, email, bio,
        profile_picture, cover_photo,
        (SELECT COUNT(*) FROM posts WHERE user_id = u.user_id) as post_count
    FROM users u
    WHERE $where
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($user = $result->fetch_assoc()) {
    $user['profile_picture'] = $user['profile_picture'] ? 
        "http://localhost/uploads/profiles/" . $user['profile_picture'] : 
        "http://localhost/assets/default.png";
    $user['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $users[] = $user;
}

echo json_encode([
    'success' => true,
    'users' => $users,
    'pagination' => ['page' => $page, 'limit' => $limit]
]);
?>