<?php
// toxic-check.php - Call Python model from PHP

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$input = json_decode(file_get_contents('php://input'), true);
$posts = $input['posts'] ?? [];
$userId = $input['userId'] ?? null;

if (empty($posts)) {
    exit(json_encode(['success'=>false, 'message'=>'posts array required']));
}

$command = escapeshellcmd('python3 toxic_detector.py');
$inputData = json_encode(['predict' => $posts]);

$process = proc_open($command, [
    0 => ['pipe', 'r'], // stdin
    1 => ['pipe', 'w'], // stdout
    2 => ['pipe', 'w']  // stderr
], $pipes);

fwrite($pipes[0], $inputData);
fclose($pipes[0]);

$output = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
proc_close($process);

if (!empty($stderr)) {
    exit(json_encode(['success'=>false, 'error'=>$stderr]));
}

$data = json_decode($output, true);
echo json_encode([
    'success' => true,
    'results' => $data ?? [],
    'toxicCount' => count(array_filter($data ?? [], fn($r) => $r['is_toxic'])),
    'userId' => $userId
]);
?>