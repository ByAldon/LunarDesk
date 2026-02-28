<?php
// webhook.php - Entry point for external data
$dbPath = __DIR__ . '/data.db';
$key = $_GET['key'] ?? '';

if (empty($key)) {
    http_response_code(401);
    die("Unauthorized: Missing key");
}

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Zoek het kanaal dat bij deze sleutel hoort
    $stmt = $db->prepare("SELECT id, title FROM rooms WHERE webhook_key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        http_response_code(403);
        die("Forbidden: Invalid key");
    }

    $content = '';
    $sender = 'Webhook';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $jsonInput = json_decode($rawInput, true);

        if ($jsonInput) {
            $content = json_encode($jsonInput, JSON_PRETTY_PRINT);
            if (isset($jsonInput['sender'])) $sender = $jsonInput['sender'];
        } else {
            $content = !empty($_POST) ? print_r($_POST, true) : $rawInput;
        }
        
        if (strlen($content) > 5000) $content = substr($content, 0, 5000) . "\n[Truncated]";
        if (empty(trim($content))) $content = "Empty payload received.";

        $insertStmt = $db->prepare("INSERT INTO webhook_messages (room_id, sender, content) VALUES (:room_id, :sender, :content)");
        $insertStmt->execute([':room_id' => $room['id'], ':sender' => $sender, ':content' => $content]);

        echo json_encode(["status" => "success"]);
    } else {
        http_response_code(405);
        die("Method Not Allowed");
    }
} catch (PDOException $e) {
    http_response_code(500);
    die("Error");
}