<?php
// auto-moderate.php - Check new posts

header('Content-Type: application/json');
$pdo = new PDO('mysql:host=localhost;dbname=db_feedspace', 'root', '');

$stmt = $pdo->query("
    SELECT post_id, content, user_id 
    FROM posts 
    WHERE status='pending' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$pendingPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$input = ['posts' => array_column($pendingPosts, 'content')];
$jsonInput = json_encode($input);

$command = 'python3 toxic_detector.py';
$process = proc_open($command, [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
fwrite($pipes[0], $jsonInput);
fclose($pipes[0]);

$result = json_decode(stream_get_contents($pipes[1]), true);
proc_close($process);

foreach ($result['results'] as $i => $detection) {
    $postId = $pendingPosts[$i]['post_id'];
    $action = $detection['is_toxic'] && $detection['toxicity_score'] > 0.8 
        ? 'rejected' : 'approved';
    
    $pdo->prepare("UPDATE posts SET status=? WHERE post_id=?")
        ->execute([$action, $postId]);
}

echo json_encode([
    'success' => true,
    'checked' => count($pendingPosts),
    'autoRejected' => count(array_filter($result['results'], fn($r)=>$r['is_toxic'])),
    'model' => 'ToxicDetector v1.0'
]);
?>