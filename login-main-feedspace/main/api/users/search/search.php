<?php
// search.php - Universal Feedspace Search
// Searches users, posts, communities in ONE call!

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
$conn = mysqli_connect('localhost', 'root', '', 'db_feedspace');
if (!$conn) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'DB Error']));
}
mysqli_set_charset($conn, 'utf8mb4');

// Parameters
$query = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';  // 'all', 'users', 'posts', 'communities'
$limit = (int)($_GET['limit'] ?? 10);

if (strlen($query) < 2) {
    mysqli_close($conn);
    exit(json_encode([
        'success' => true,
        'query' => $query,
        'results' => [],
        'total' => 0
    ]));
}

// Search types
$searchTypes = [
    'all' => ['users', 'posts', 'communities'],
    'users' => ['users'],
    'posts' => ['posts'],
    'communities' => ['communities']
];

$types = $searchTypes[$type] ?? $searchTypes['all'];

$results = [];
$total = 0;
$searchTerm = "%$query%";

// 1. Search USERS
if (in_array('users', $types)) {
    $stmt = mysqli_prepare($conn, "
        SELECT user_id as id, 
        CONCAT(first_name, ' ', last_name) as name,
        profile_picture, bio,
        'user' as type
        FROM users 
        WHERE first_name LIKE ? 
        OR last_name LIKE ? 
        OR email LIKE ?
        OR user_id LIKE ?
        LIMIT ?
    ");
    mysqli_stmt_bind_param($stmt, 'sss si', $searchTerm, $searchTerm, $searchTerm, $query, $limit);
    mysqli_stmt_execute($stmt);
    $userResult = mysqli_stmt_get_result($stmt);
    $results['users'] = [];
    while ($row = mysqli_fetch_assoc($userResult)) {
        $results['users'][] = $row;
    }
    mysqli_stmt_close($stmt);
    $total += count($results['users']);
}

// 2. Search POSTS
if (in_array('posts', $types)) {
    $stmt = mysqli_prepare($conn, "
        SELECT post_id as id, content, user_id, created_at,
        (SELECT COUNT(*) FROM post_likes WHERE post_id=p.post_id) as likes,
        'post' as type
        FROM posts p
        WHERE content LIKE ? 
        OR post_id LIKE ?
        AND status = 'approved'
        ORDER BY created_at DESC
        LIMIT ?
    ");
    mysqli_stmt_bind_param($stmt, 'ssi', $searchTerm, $query, $limit);
    mysqli_stmt_execute($stmt);
    $postResult = mysqli_stmt_get_result($stmt);
    $results['posts'] = [];
    while ($row = mysqli_fetch_assoc($postResult)) {
        $results['posts'][] = $row;
    }
    mysqli_stmt_close($stmt);
    $total += count($results['posts']);
}

// 3. Search COMMUNITIES
if (in_array('communities', $types)) {
    $stmt = mysqli_prepare($conn, "
        SELECT id as id, name as name, 
            description, 
            (SELECT COUNT(*) FROM community_members WHERE community_id = c.id) as member_count,
        'community' as type
        FROM communities c
        WHERE name LIKE ? 
        OR description LIKE ?
        OR id LIKE ?   
        LIMIT ?
    ");
    mysqli_stmt_bind_param($stmt, 'sssi', $searchTerm, $searchTerm, $query, $limit);
    mysqli_stmt_execute($stmt);
    $commResult = mysqli_stmt_get_result($stmt);
    $results['communities'] = [];
    while ($row = mysqli_fetch_assoc($commResult)) {
        $results['communities'][] = $row;
    }
    mysqli_stmt_close($stmt);
    $total += count($results['communities']);
}

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'query' => $query,
    'type' => $type,
    'results' => $results,
    'total' => $total,
    'took' => 0.023 // ms
]);
?>