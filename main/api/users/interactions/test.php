<?php
// Force ALL errors to be logged AND displayed as JSON
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Custom error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'FATAL: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        exit;
    }
});

try {
    $info = [
        'success' => true,
        'php_version' => PHP_VERSION,
        'cwd' => getcwd(),
        'script_path' => __FILE__,
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
    ];

    // Check session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $info['session_status_after_start'] = session_status();
    $info['session_user_id'] = $_SESSION['user_id'] ?? 'NOT SET';

    // Check db.php path (corrected filename)
    $dbPath = '../../../config/db.php';
    $info['db_relative_path'] = $dbPath;
    $info['db_exists'] = file_exists($dbPath);
    $info['db_realpath'] = realpath($dbPath) ?: 'NOT FOUND';

    // Try to require it
    if ($info['db_exists']) {
        try {
            require_once $dbPath;
            $info['db_loaded'] = true;
            $info['pdo_exists'] = isset($pdo) && $pdo instanceof PDO;
            if ($info['pdo_exists']) {
                $info['pdo_connected'] = true;
                // Test a simple query
                $test = $pdo->query("SELECT 1");
                $info['db_query_works'] = $test !== false;
            }
        } catch (Throwable $e) {
            $info['db_loaded'] = false;
            $info['db_error'] = $e->getMessage();
            $info['db_error_file'] = $e->getFile();
            $info['db_error_line'] = $e->getLine();
        }
    }

    // Check AI moderator
    $aiPath = '../../../api/ai-moderator.php';
    $info['ai_moderator_exists'] = file_exists($aiPath);

    echo json_encode($info, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}