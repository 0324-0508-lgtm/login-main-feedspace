<?php
// DEBUG VERSION - Shows exact errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

try {
    $configPath = __DIR__ . '/../../../config/db.php';

    if (!file_exists($configPath)) {
        echo json_encode([
            'success' => false,
            'error' => 'Config file not found',
            'tried_path' => $configPath,
            'this_file' => __FILE__,
            'this_dir' => __DIR__
        ]);
        exit;
    }

    require_once $configPath;

    if (!isset($conn)) {
        echo json_encode([
            'success' => false,
            'error' => 'No $conn variable after loading config',
            'config_path' => $configPath
        ]);
        exit;
    }

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    $data = json_decode(file_get_contents('php://input'), true);
    $post_id = (int)($data['post_id'] ?? 0);
    $comment = trim($data['comment'] ?? '');

    if (!$post_id) {
        echo json_encode(['success' => false, 'error' => 'Post ID required']);
        exit;
    }

    // Verify post
    $check = $conn->prepare("
        SELECT post_id, content, file_url, user_id 
        FROM posts 
        WHERE post_id = ? AND is_deleted = 0 AND status = 'approved' AND visibility = 'public'
    ");
    $check->execute([$post_id]);
    $post = $check->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit;
    }

    // Record share
    $stmt = $conn->prepare("INSERT INTO shares (post_id, user_id) VALUES (?, ?)");
    $stmt->execute([$post_id, $user_id]);

    // Create shared post
    $shared_content = $comment ? $comment . "

[Shared post]" : "[Shared post]";
    $insert = $conn->prepare("
        INSERT INTO posts (user_id, content, file_url, file_type, status, visibility) 
        VALUES (?, ?, ?, 'none', 'approved', 'public')
    ");
    $insert->execute([$user_id, $shared_content, $post['file_url']]);

    echo json_encode([
        'success' => true,
        'message' => 'Post shared',
        'post_id' => $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}