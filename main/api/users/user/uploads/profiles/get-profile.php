<?php
// ============================================================
//  get-profile.php
//  Returns profile data + posts for a given user.
//
//  Auth: Accepts user_id via POST (stored in localStorage by
//        the frontend after OTP login). No PHP session required.
//
//  POST params:
//    user_id        – the currently logged-in user (required)
//    target_user_id – whose profile to view (optional; defaults to user_id)
//    page           – pagination page (optional; default 1)
// ============================================================

session_start();

// ── Path fix: 6 levels up to project root ─────────────────────
// File lives at: main/api/users/user/uploads/profiles/
// Root lives at: ../../../../../.. relative to this file
require_once __DIR__ . '/../../../../../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auth: accept session OR POST user_id (localStorage flow) ──
$user_id = $_SESSION['user_id'] ?? trim($_POST['user_id'] ?? '');

if (empty($user_id)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$target_user_id = trim($_POST['target_user_id'] ?? $user_id);
$page           = max(1, intval($_POST['page'] ?? 1));
$limit          = 10;
$offset         = ($page - 1) * $limit;

// ── 1. Fetch profile ──────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        user_id, first_name, last_name, email, bio, role, college,
        profile_picture, cover_photo, created_at,
        (SELECT COUNT(*) FROM posts   WHERE user_id = ? AND is_deleted = 0) AS post_count,
        (SELECT COUNT(*) FROM shares  WHERE user_id = ?)                    AS share_count,
        (SELECT COUNT(*) FROM community_members WHERE user_id = ?)          AS community_count
    FROM users
    WHERE user_id = ?
");
$stmt->bind_param('ssss', $target_user_id, $target_user_id, $target_user_id, $target_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

$profile = $result->fetch_assoc();

// ── Build full image URLs ─────────────────────────────────────
// Use a relative URL so it works regardless of domain/port.
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
// uploads folder is at: main/api/users/user/uploads/
// From web root the path is: /main/api/users/user/uploads/

$profile['profile_picture_url'] = $profile['profile_picture'] && $profile['profile_picture'] !== 'default.png'
    ? '/main/api/users/user/uploads/profiles/' . $profile['profile_picture']
    : null;   // frontend falls back to default avatar

$profile['cover_photo_url'] = $profile['cover_photo']
    ? '/main/api/users/user/uploads/covers/' . $profile['cover_photo']
    : null;

$profile['full_name']   = trim($profile['first_name'] . ' ' . $profile['last_name']);
$profile['member_since'] = date('M Y', strtotime($profile['created_at']));
$profile['is_own_profile'] = ($user_id === $target_user_id);

// ── 2. Fetch user's posts ─────────────────────────────────────
$posts_stmt = $conn->prepare("
    SELECT
        p.post_id AS id,
        p.content,
        p.file_url  AS image,
        p.file_type,
        p.created_at,
        (SELECT COUNT(*) FROM post_likes  WHERE post_id = p.post_id) AS like_count,
        (SELECT COUNT(*) FROM shares      WHERE post_id = p.post_id) AS share_count,
        (SELECT COUNT(*) FROM comments    WHERE post_id = p.post_id) AS comment_count,
        EXISTS(SELECT 1 FROM post_likes WHERE post_id = p.post_id AND user_id = ?) AS user_liked
    FROM posts p
    WHERE p.user_id = ?
      AND p.is_deleted = 0
      AND p.visibility = 'public'
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$posts_stmt->bind_param('ssii', $user_id, $target_user_id, $limit, $offset);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();

$posts = [];
while ($post = $posts_result->fetch_assoc()) {
    // Only set image URL if the file_url is a local filename (not already a full URL)
    if ($post['image']) {
        $post['image'] = preg_match('#^https?://#i', $post['image'])
            ? $post['image']
            : '/main/api/users/user/uploads/posts/' . $post['image'];
    }
    $post['created_at']  = date('M d, Y', strtotime($post['created_at']));
    $post['user_liked']  = (bool)$post['user_liked'];
    $posts[] = $post;
}

// ── 3. Total post count for pagination ───────────────────────
$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM posts
    WHERE user_id = ? AND is_deleted = 0 AND visibility = 'public'
");
$count_stmt->bind_param('s', $target_user_id);
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_assoc()['total'];

// ── Response ──────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'profile' => $profile,
    'posts'   => $posts,
    'pagination' => [
        'page'  => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => (int)ceil($total / $limit),
    ],
]);
