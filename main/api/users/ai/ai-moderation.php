<?php
/**
 * AI Moderation Service for FeedSpace
 * Analyzes content for toxicity, hate speech, spam, and policy violations
 */

class AIModerator {
    private $conn;

    // Toxicity thresholds
    const SAFE_THRESHOLD = 0.3;
    const REVIEW_THRESHOLD = 0.7;
    const REJECT_THRESHOLD = 0.9;

    // Local keyword-based detection
    private $toxicKeywords = [
        'hate' => ['hate', 'kill', 'die', 'death', 'murder', 'violence', 'attack', 'destroy'],
        'harassment' => ['stupid', 'idiot', 'moron', 'retard', 'loser', 'ugly', 'fat', 'dumb'],
        'profanity' => ['fuck', 'shit', 'bitch', 'asshole', 'damn', 'crap', 'hell'],
        'spam' => ['click here', 'buy now', 'free money', 'get rich', 'limited time', 'act now', '$$$', 'xxx'],
        'threats' => ['i will', 'going to', 'watch out', 'better be careful', 'regret this'],
        'discrimination' => ['racist', 'nazi', 'supremacy', 'inferior', 'superior race', 'ethnic cleansing']
    ];

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    public function analyzeContent($content, $contentType = 'post') {
        if (empty($content)) {
            return $this->createResult('safe', 0.0, 'Empty content');
        }

        return $this->localAnalysis($content);
    }

    private function localAnalysis($content) {
        $lowerContent = strtolower($content);
        $totalScore = 0.0;
        $detectedIssues = [];
        $maxCategoryScore = 0.0;

        foreach ($this->toxicKeywords as $category => $keywords) {
            $categoryScore = 0.0;
            $matches = [];

            foreach ($keywords as $keyword) {
                $count = substr_count($lowerContent, $keyword);
                if ($count > 0) {
                    $categoryScore += min($count * 0.25, 0.5);
                    $matches[] = $keyword;
                }
            }

            if ($categoryScore > 0) {
                $detectedIssues[] = [
                    'category' => $category,
                    'score' => min($categoryScore, 1.0),
                    'matches' => array_slice($matches, 0, 3)
                ];
                $maxCategoryScore = max($maxCategoryScore, $categoryScore);
            }
        }

        $totalScore = $this->calculateHeuristicScore($content, $maxCategoryScore);
        $status = $this->determineStatus($totalScore);
        $reason = $this->generateReason($detectedIssues, $totalScore);

        return $this->createResult($status, $totalScore, $reason, $detectedIssues);
    }

    private function calculateHeuristicScore($content, $baseScore) {
        $score = $baseScore;

        $capsRatio = $this->getCapsRatio($content);
        if ($capsRatio > 0.7) $score += 0.15;

        $punctuationRatio = $this->getExcessivePunctuation($content);
        if ($punctuationRatio > 0.3) $score += 0.1;

        if (preg_match('/(.){4,}/', $content)) $score += 0.15;

        $urlCount = substr_count(strtolower($content), 'http');
        if ($urlCount > 2) $score += min($urlCount * 0.1, 0.3);

        if (strlen($content) < 50 && $baseScore > 0.3) $score += 0.1;

        return min($score, 1.0);
    }

    private function getCapsRatio($text) {
        $letters = preg_replace('/[^a-zA-Z]/', '', $text);
        if (empty($letters)) return 0;
        $caps = preg_replace('/[^A-Z]/', '', $letters);
        return strlen($caps) / strlen($letters);
    }

    private function getExcessivePunctuation($text) {
        $punctuation = preg_replace('/[^!?]/', '', $text);
        return strlen($punctuation) / max(strlen($text), 1);
    }

    private function determineStatus($score) {
        if ($score >= self::REJECT_THRESHOLD) return 'rejected';
        if ($score >= self::REVIEW_THRESHOLD) return 'review';
        if ($score >= self::SAFE_THRESHOLD) return 'review';
        return 'safe';
    }

    private function generateReason($issues, $score) {
        if (empty($issues)) {
            return $score > 0 ? 'Minor concerns detected - flagged for review' : 'Content appears safe';
        }

        $categories = array_column($issues, 'category');
        $reasons = [
            'hate' => 'Potentially hateful or violent content detected',
            'harassment' => 'Harassment or bullying language detected',
            'profanity' => 'Excessive profanity detected',
            'spam' => 'Spam or promotional content detected',
            'threats' => 'Potential threats detected',
            'discrimination' => 'Discriminatory content detected'
        ];

        return $reasons[$categories[0]] ?? 'Inappropriate content detected';
    }

    private function createResult($status, $score, $reason, $details = []) {
        return [
            'status' => $status,
            'score' => round($score, 2),
            'reason' => $reason,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s'),
            'source' => 'local_ai'
        ];
    }

    public function moderatePost($postId, $content) {
        $result = $this->analyzeContent($content, 'post');

        $stmt = $this->conn->prepare("
            UPDATE posts 
            SET ai_score = ?, ai_status = ?, ai_reason = ?
            WHERE post_id = ?
        ");
        $stmt->execute([$result['score'], $result['status'], $result['reason'], $postId]);

        if ($result['status'] === 'rejected') {
            $stmt = $this->conn->prepare("UPDATE posts SET status = 'rejected' WHERE post_id = ?");
            $stmt->execute([$postId]);
        }

        return $result;
    }

    public function moderateComment($commentId, $content) {
        $result = $this->analyzeContent($content, 'comment');

        $status = 'pending';
        if ($result['status'] === 'safe') $status = 'approved';
        elseif ($result['status'] === 'rejected') $status = 'removed';
        elseif ($result['status'] === 'review') $status = 'flagged';

        $stmt = $this->conn->prepare("
            UPDATE comments 
            SET moderation_status = ?, moderation_reason = ?, toxicity_score = ?, moderated_by = 'AI', moderated_at = NOW()
            WHERE comment_id = ?
        ");
        $stmt->execute([$status, $result['reason'], $result['score'], $commentId]);

        return array_merge($result, ['moderation_status' => $status]);
    }
}