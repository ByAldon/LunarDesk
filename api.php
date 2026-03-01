<?php
// api.php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dbPath = __DIR__ . '/data.db';
$app_version = "v1.5.8-beta";

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'profile') {
        if ($method === 'PUT') {
            $sql = "UPDATE users SET username = :un, nickname = :nn, email = :em";
            $params = [':un' => $input['username'], ':nn' => $input['nickname'], ':em' => $input['email'], ':id' => $_SESSION['user_id']];
            if (!empty($input['password'])) {
                $sql .= ", password_hash = :pass";
                $params[':pass'] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = :id";
            $db->prepare($sql)->execute($params);
            $_SESSION['username'] = $input['username'];
            echo json_encode(['success' => true]);
        } else {
            $stmt = $db->prepare("SELECT id, username, email, nickname, role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        }
        exit;
    }

    if ($action === 'users') {
        if ($_SESSION['role'] !== 'admin') { http_response_code(403); exit; }
        if ($method === 'GET') {
            echo json_encode($db->query("SELECT id, username, email, nickname, role FROM users")->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            $token = bin2hex(random_bytes(32));
            $temp = 'pending_' . bin2hex(random_bytes(4));
            $stmt = $db->prepare("INSERT INTO users (username, email, role, reset_token, reset_expires) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$temp, $input['email'], $input['role'], $token, time() + 259200]);
            echo json_encode(['success' => true]);
        } elseif ($method === 'PUT') {
            $db->prepare("UPDATE users SET username=?, nickname=?, email=?, role=? WHERE id=?")
               ->execute([$input['username'], $input['nickname'], $input['email'], $input['role'], $input['id']]);
            echo json_encode(['success' => true]);
        } elseif ($method === 'DELETE') {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['id']]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($action === 'terminal') {
        if ($method === 'POST') {
            $db->prepare("INSERT INTO admin_terminal (sender, content, colorClass) VALUES (?, ?, ?)")
               ->execute([$input['sender'], $input['content'], $input['colorClass'] ?? 'text-slate-400']);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode($db->query("SELECT * FROM admin_terminal ORDER BY created_at ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC));
        }
        exit;
    }

    if ($action === 'rooms') {
        if ($method === 'GET') echo json_encode($db->query("SELECT * FROM rooms ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC));
        elseif ($method === 'POST') {
            $db->prepare("INSERT INTO rooms (title) VALUES (?)")->execute([$input['title']]);
            echo json_encode(['success' => true]);
        } elseif ($method === 'DELETE') {
            $db->prepare("DELETE FROM rooms WHERE id = ?")->execute([$_GET['id']]);
            $db->prepare("DELETE FROM webhook_messages WHERE room_id = ?")->execute([$_GET['id']]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($action === 'webhook_key' && $method === 'PUT') {
        $key = bin2hex(random_bytes(16));
        $db->prepare("UPDATE rooms SET webhook_key = ? WHERE id = ?")->execute([$key, $input['id']]);
        echo json_encode(['success' => true, 'key' => $key]);
        exit;
    }

    if ($action === 'clear_messages' && $method === 'DELETE') {
        $db->prepare("DELETE FROM webhook_messages WHERE room_id = ?")->execute([$_GET['room_id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'webhook_messages') {
        $stmt = $db->prepare("SELECT * FROM webhook_messages WHERE room_id = ? ORDER BY created_at ASC");
        $stmt->execute([$_GET['room_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'upload') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => 0, 'error' => 'Upload error.']);
            exit;
        }
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $newName = uniqid('img_') . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newName)) {
            echo json_encode(['success' => 1, 'file' => ['url' => 'uploads/' . $newName]]);
        }
        exit;
    }

    switch ($method) {
        case 'GET':
            echo json_encode($db->query("SELECT * FROM items ORDER BY type DESC, title ASC")->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'POST':
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $input['title']))) . '-' . rand(100, 999);
            $db->prepare("INSERT INTO items (title, draft_title, type, parent_id, slug) VALUES (?, ?, ?, ?, ?)")
               ->execute([$input['title'], $input['title'], $input['type'], $input['parent_id'], $slug]);
            echo json_encode(['success' => true]);
            break;
        case 'PUT':
            if ($input['action'] === 'publish') {
                $stmt = $db->prepare("UPDATE items SET title = :t, content = :c, cover_image = :cov, has_draft = 0, is_public = :p WHERE id = :id");
            } else {
                $stmt = $db->prepare("UPDATE items SET draft_title = :t, draft_content = :c, draft_cover_image = :cov, has_draft = 1, is_public = :p WHERE id = :id");
            }
            $stmt->execute([':t'=>$input['title'], ':c'=>$input['content'], ':p'=>$input['is_public'], ':id'=>$input['id'], ':cov'=>$input['cover_image'] ?? '']);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            $db->prepare("DELETE FROM items WHERE id = ? OR parent_id = ?")->execute([$_GET['id'], $_GET['id']]);
            echo json_encode(['success' => true]);
            break;
    }
} catch (PDOException $e) { echo json_encode(['error' => $e->getMessage()]); }