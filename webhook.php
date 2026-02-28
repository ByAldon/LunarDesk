<?php
// webhook.php

$dbPath = __DIR__ . '/data.db';

// 1. Check if key is provided
$key = $_GET['key'] ?? '';
if (empty($key)) {
    http_response_code(401);
    die("Unauthorized: Missing key");
}

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Validate key and get room
    $stmt = $db->prepare("SELECT id, title FROM rooms WHERE webhook_key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        http_response_code(403);
        die("Forbidden: Invalid key");
    }

    // 3. Process incoming payload
    $content = '';
    $sender = 'Webhook';
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        $jsonInput = json_decode($rawInput, true);

        if ($jsonInput) {
            // Format JSON nicely if it is a JSON payload
            $content = json_encode($jsonInput, JSON_PRETTY_PRINT);
            
            // Basic heuristic to find a sender if provided
            if (isset($jsonInput['sender'])) {
                $sender = $jsonInput['sender'];
            } elseif (isset($_SERVER['HTTP_USER_AGENT'])) {
                $sender = substr($_SERVER['HTTP_USER_AGENT'], 0, 50);
            }
        } else {
            // Fallback to raw text or standard form POST array
            if (!empty($_POST)) {
                $content = print_r($_POST, true);
            } else {
                $content = $rawInput;
            }
        }
        
        // Limit content length to prevent database bloat from massive payloads
        if (strlen($content) > 5000) {
            $content = substr($content, 0, 5000) . "\n\n[... Truncated due to size ...]";
        }
        
        if (empty(trim($content))) {
            $content = "Empty payload received.";
        }

        // 4. Insert into database
        $insertStmt = $db->prepare("INSERT INTO webhook_messages (room_id, sender, content) VALUES (:room_id, :sender, :content)");
        $insertStmt->execute([
            ':room_id' => $room['id'],
            ':sender' => $sender,
            ':content' => $content
        ]);

        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Webhook logged to room: " . $room['title']]);

    } else {
        http_response_code(405);
        die("Method Not Allowed. Please POST data.");
    }

} catch (PDOException $e) {
    http_response_code(500);
    die("Database error: " . $e->getMessage());
}
?>