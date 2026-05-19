<?php
// DEBUG VERSION
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

try {
    // Try multiple config paths
    $paths = [
        __DIR__ . '/../../../config/db.php',
        __DIR__ . '/../../../../config/db.php',
    ];

    require_once __DIR__ . '/../../users/ai/ai-moderator.php';

    $configFound = false;
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $configFound = true;
            break;
        }
    }

    if (!$configFound) {
        echo json_encode(['success' => false, 'error' => 'Config not found', 'tried' => $paths]);
        exit;
    }

    // Try to load AI moderator
    $aiPath = __DIR__ . '/../../users/ai/ai-moderator.php';
    if (!file_exists($aiPath)) {
        $aiPath = __DIR__ . '/../../../users/ai/ai-moderator.php';
    }

    if (file_exists($aiPath)) {
        require_once $aiPath;
        $aiLoaded = true;
    } else {
        $aiLoaded = false;
    }

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $content = trim($_POST['content'] ?? '');

    if (empty($content) && empty($_FILES['image']['tmp_name'])) {
        echo json_encode(['success' => false, 'error' => 'Content or image required']);
        exit;
    }

// AI Moderation
$moderator = new AIModerator($conn);
$aiResult = $moderator->analyzeContent($content);

$status = 'pending';
if ($aiResult['status'] === 'safe') $status = 'approved';
elseif ($aiResult['status'] === 'rejected') $status = 'rejected';

    // AI Moderation (if available)
    $ai_score = 0;
    $ai_status = 'safe';
    $ai_reason = null;

    if ($aiLoaded && class_exists('AIModerator')) {
        $moderator = new AIModerator($conn);
        $aiResult = $moderator->analyzeContent($content);
        $ai_score = $aiResult['score'];
        $ai_status = $aiResult['status'];
        $ai_reason = $aiResult['reason'];
    }

    $status = ($ai_status === 'safe') ? 'approved' : 'pending';

    $stmt = $conn->prepare("
    INSERT INTO posts (user_id, community_id, content, file_url, file_type, visibility, status, ai_score, ai_status, ai_reason)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $user_id, $community_id, $content, $file_url, $file_type, 
    $visibility, $status, $aiResult['score'], $aiResult['status'], $aiResult['reason']
]);
    $post_id = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'post_id' => $post_id,
        'ai_status' => $ai_status,
        'ai_score' => $ai_score,
        'ai_loaded' => $aiLoaded
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}