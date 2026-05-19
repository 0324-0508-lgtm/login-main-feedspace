<?php
// Bulletproof error handling - catches ALL PHP errors including fatal ones
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$jsonSent = false;

function sendJson($data, $code = 200) {
    global $jsonSent;
    if ($jsonSent) return;
    $jsonSent = true;
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Catch fatal errors
register_shutdown_function(function() {
    global $jsonSent;
    if ($jsonSent) return;
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        sendJson([
            'success' => false,
            'error' => 'FATAL PHP ERROR: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ], 500);
    }
});

// Catch warnings/notices too
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        sendJson([
            'success' => false,
            'error' => 'PHP ERROR [' . $errno . ']: ' . $errstr,
            'file' => basename($errfile),
            'line' => $errline
        ], 500);
    }
    return true;
});

try {
    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check auth
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        sendJson(['success' => false, 'error' => 'Not authenticated. Please log in.'], 401);
    }

    // Parse JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJson(['success' => false, 'error' => 'Invalid JSON input: ' . json_last_error_msg()], 400);
    }

    if (!isset($input['post_id'], $input['content'])) {
        sendJson(['success' => false, 'error' => 'Missing required fields (post_id, content)'], 400);
    }

    $post_id = intval($input['post_id']);
    $content = trim($input['content']);

    if (strlen($content) === 0) {
        sendJson(['success' => false, 'error' => 'Comment cannot be empty'], 400);
    }

    if (strlen($content) > 2000) {
        sendJson(['success' => false, 'error' => 'Comment too long (max 2000 characters)'], 400);
    }

    // Find and load db.php - 4 levels up from interactions/ → project root/
    $possiblePaths = [
        '../../../../config/db.php',
        __DIR__ . '/../../../../config/db.php',
        '../../../config/db.php',
        __DIR__ . '/../../../config/db.php',
    ];

    $dbLoaded = false;
    $dbError = '';
    $triedPaths = [];
    foreach ($possiblePaths as $path) {
        $triedPaths[] = $path . ' => ' . (file_exists($path) ? 'EXISTS' : 'NOT FOUND');
        if (file_exists($path)) {
            try {
                require_once $path;
                $dbLoaded = true;
                break;
            } catch (Throwable $e) {
                $dbError = $e->getMessage();
            }
        }
    }

    if (!$dbLoaded) {
        sendJson([
            'success' => false,
            'error' => 'Could not load db.php. Tried: ' . implode(' | ', $triedPaths) . ($dbError ? ' | Last error: ' . $dbError : ''),
            'cwd' => getcwd(),
            'script_dir' => __DIR__
        ], 500);
    }

    // YOUR db.php uses $conn, not $pdo!
    if (!isset($conn) || !($conn instanceof PDO)) {
        sendJson(['success' => false, 'error' => 'Database connection ($conn) not available after loading db.php'], 500);
    }

    // AI Moderation (optional - won't crash if file missing)
    $toxicity_score = null;
    $moderation_status = 'pending'; // matches your schema default
    $aiPaths = [
        '../../../../api/ai-moderator.php',
        __DIR__ . '/../../../../api/ai-moderator.php',
        '../../../api/ai-moderator.php',
    ];
    foreach ($aiPaths as $aiPath) {
        if (file_exists($aiPath)) {
            try {
                require_once $aiPath;
                if (function_exists('moderateContent')) {
                    $aiResult = moderateContent($content);
                    $toxicity_score = $aiResult['toxicity_score'] ?? null;
                    if (($aiResult['action'] ?? '') === 'reject') {
                        $moderation_status = 'removed';
                    } elseif (($aiResult['action'] ?? '') === 'flag') {
                        $moderation_status = 'flagged';
                    } elseif (($aiResult['action'] ?? '') === 'approve') {
                        $moderation_status = 'approved';
                    }
                }
            } catch (Throwable $e) {
                // AI moderation failed, continue without it
            }
            break;
        }
    }

    // Verify post exists - posts table uses post_id, not id
    $checkPost = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ?");
    $checkPost->execute([$post_id]);
    if (!$checkPost->fetch()) {
        sendJson(['success' => false, 'error' => 'Post not found'], 404);
    }

    // Insert comment - let created_at use DEFAULT current_timestamp()
    $stmt = $conn->prepare("
        INSERT INTO comments (post_id, user_id, content, toxicity_score, moderation_status) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$post_id, $user_id, $content, $toxicity_score, $moderation_status]);

    // Get updated comment count
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?");
    $countStmt->execute([$post_id]);
    $comment_count = intval($countStmt->fetchColumn());

    sendJson([
        'success' => true,
        'comment_count' => $comment_count,
        'message' => 'Comment added',
        'moderation_status' => $moderation_status
    ]);

} catch (Throwable $e) {
    sendJson([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}