<?php
// ============================================================
//  FeedSpace Shared Functions
// ============================================================

/**
 * timeAgo(string $datetime): string
 * Returns a human-readable "time ago" string.
 */
function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return $diff . ' seconds ago';
    if ($diff < 3600)   return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d', strtotime($datetime));
}

/**
 * sanitize(string $input): string
 * Trims and strips tags from user input.
 */
function sanitize(string $input): string
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * jsonError(int $code, string $message): void
 * Sends a JSON error response and exits.
 */
function jsonError(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit();
}

/**
 * jsonSuccess(array $data = []): void
 * Sends a JSON success response and exits.
 */
function jsonSuccess(array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}
