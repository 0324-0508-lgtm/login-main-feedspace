<?php
// ai-moderator.php
// Place this file at: C:\xampp\htdocs\login-main-feedspace\main\api\users\ai\ai-moderator.php

/**
 * Moderate content for inappropriate text
 * @param string $text The content to check
 * @return array ['flagged' => bool, 'reason' => string, 'cleaned_text' => string]
 */
function moderateContent($text) {
    if (empty($text)) {
        return ['flagged' => false, 'reason' => '', 'cleaned_text' => $text];
    }

    // List of words/phrases to flag (expand as needed)
    $toxicWords = [
        'badword1', 'badword2', 'spam', 'hate', 'kill', 'die',
        'stupid', 'idiot', 'moron', 'loser'
    ];

    $lower = strtolower($text);
    $flagged = false;
    $reason = '';

    foreach ($toxicWords as $word) {
        if (stripos($lower, $word) !== false) {
            $flagged = true;
            $reason = 'Contains inappropriate content: ' . $word;
            break;
        }
    }

    return [
        'flagged' => $flagged,
        'reason' => $reason,
        'cleaned_text' => $text
    ];
}

/**
 * Moderate image content (placeholder for AI image moderation)
 * @param string $imagePath Path to uploaded image
 * @return array ['flagged' => bool, 'reason' => string]
 */
function moderateImage($imagePath) {
    // Placeholder — integrate with AI vision API later
    return ['flagged' => false, 'reason' => ''];
}
?>