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

    if (!isset($input['post_id'])) {
        sendJson(['success' => false, 'error' => 'Missing post_id'], 400);
    }

    $post_id = intval($input['post_id']);
    $page = isset($input['page']) ? intval($input['page']) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;

    // Find and load db.php
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

    if (!isset($conn) || !($conn instanceof PDO)) {
        sendJson(['success' => false, 'error' => 'Database connection ($conn) not available after loading db.php'], 500);
    }

    // Get comments - users table has first_name and last_name, not full_name
    $stmt = $conn->prepare("
        SELECT 
            c.comment_id,
            c.content,
            c.created_at,
            c.toxicity_score,
            c.moderation_status,
            u.user_id,
            CONCAT(u.first_name, ' ', u.last_name) AS author,
            COALESCE(u.profile_picture, '/login-main-feedspace/assets/default.jpg') AS avatar
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
        LIMIT ? OFFSET ?
    ");

    // Bind LIMIT and OFFSET as PDO::PARAM_INT to avoid MariaDB syntax error
    $stmt->bindValue(1, $post_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJson([
        'success' => true,
        'comments' => $comments,
        'page' => $page,
        'count' => count($comments)
    ]);

} catch (Throwable $e) {
    sendJson([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}