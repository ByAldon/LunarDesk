<?php
// webhook.php
header('Content-Type: application/json');
$dbPath = __DIR__ . '/data.db';
$key = $_GET['key'] ?? '';
if (empty($key)) die(json_encode(['error' => 'No Key']));
try {
    $db = new PDO("sqlite:$dbPath");
    $stmt = $db->prepare("SELECT id FROM rooms WHERE webhook_key = ?");
    $stmt->execute([$key]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) die(json_encode(['error' => 'Invalid']));
    $in = json_decode(file_get_contents('php://input'), true);
    $sender = $in['sender'] ?? 'Signal';
    $content = $in['content'] ?? (is_string($in) ? $in : json_encode($in));
    $db->prepare("INSERT INTO webhook_messages (room_id, sender, content) VALUES (?, ?, ?)")
       ->execute([$room['id'], $sender, $content]);
    echo json_encode(['success' => true]);
} catch (Exception $e) { echo json_encode(['error' => 'Fail']); }