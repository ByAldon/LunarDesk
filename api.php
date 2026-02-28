<?php
// api.php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized Access']);
    exit;
}

$dbPath = __DIR__ . '/data.db';
$app_version = "v1.2.5";

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fallback voor sessie data
    if (empty($_SESSION['user_id']) && !empty($_SESSION['username'])) {
        $stmt = $db->prepare("SELECT id, role FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
        $u = $stmt->fetch();
        if ($u) {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['role'] = $u['role'] ?: 'admin';
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // --- PROFIEL ---
    if ($action === 'profile') {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT id, username, email, nickname, role FROM users WHERE id = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } elseif ($method === 'PUT') {
            $sql = "UPDATE users SET nickname = :nickname, email = :email";
            $params = [':nickname' => $input['nickname'], ':email' => $input['email'], ':id' => $_SESSION['user_id']];
            if (!empty($input['password'])) {
                $sql .= ", password_hash = :pass";
                $params[':pass'] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = :id";
            $db->prepare($sql)->execute($params);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // --- USERS (Admin Only) ---
    if ($action === 'users') {
        if ($_SESSION['role'] !== 'admin') { http_response_code(403); exit; }
        if ($method === 'GET') {
            echo json_encode($db->query("SELECT id, username, email, nickname, role FROM users")->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, nickname, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$input['username'], password_hash($input['password'], PASSWORD_DEFAULT), $input['email'], $input['nickname'], $input['role']]);
            echo json_encode(['success' => true]);
        } elseif ($method === 'DELETE' && isset($_GET['id'])) {
            if ($_GET['id'] != $_SESSION['user_id']) {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['id']]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Cannot delete yourself']);
            }
        }
        exit;
    }

    // --- TERMINAL ---
    if ($action === 'terminal') {
        if ($method === 'GET') {
            echo json_encode($db->query("SELECT * FROM admin_terminal ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            $stmt = $db->prepare("INSERT INTO admin_terminal (sender, content, colorClass) VALUES (?, ?, ?)");
            $stmt->execute([$input['sender'] ?? 'Admin', $input['content'], $input['colorClass'] ?? 'text-purple-400']);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // --- ROOMS & WEBHOOKS ---
    if ($action === 'rooms') {
        if ($method === 'GET') {
            echo json_encode($db->query("SELECT * FROM rooms ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            $stmt = $db->prepare("INSERT INTO rooms (title) VALUES (?)");
            $stmt->execute([$input['title']]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        } elseif ($method === 'DELETE' && isset($_GET['id'])) {
            $db->prepare("DELETE FROM rooms WHERE id = ?")->execute([$_GET['id']]);
            $db->prepare("DELETE FROM webhook_messages WHERE room_id = ?")->execute([$_GET['id']]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($action === 'webhook_messages' && isset($_GET['room_id'])) {
        $stmt = $db->prepare("SELECT * FROM webhook_messages WHERE room_id = ? ORDER BY created_at ASC");
        $stmt->execute([$_GET['room_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'webhook_key' && $method === 'PUT') {
        $key = bin2hex(random_bytes(16));
        $db->prepare("UPDATE rooms SET webhook_key = ? WHERE id = ?")->execute([$key, $input['id']]);
        echo json_encode(['success' => true, 'key' => $key]);
        exit;
    }

    // --- EDITOR / ITEMS ---
    switch ($method) {
        case 'GET':
            echo json_encode($db->query("SELECT * FROM items ORDER BY type DESC, title ASC")->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'POST':
            $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $input['title'])));
            $slug = $baseSlug . '-' . rand(1000, 9999);
            $stmt = $db->prepare("INSERT INTO items (title, draft_title, content, draft_content, type, parent_id, slug, is_public) VALUES (:t1, :t2, :c1, :c2, :type, :pid, :slug, :pub)");
            $stmt->execute([':t1'=>$input['title'], ':t2'=>$input['title'], ':c1'=>$input['content']??'', ':c2'=>$input['content']??'', ':type'=>$input['type']??'page', ':pid'=>$input['parent_id']??null, ':slug'=>$slug, ':pub'=>$input['is_public']??0]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;
        case 'PUT':
            if (isset($input['id'])) {
                $cover = $input['cover_image'] ?? '';
                if (isset($input['action']) && $input['action'] === 'publish') {
                    $stmt = $db->prepare("UPDATE items SET title = :t1, content = :c1, cover_image = :cov1, draft_title = :t2, draft_content = :c2, draft_cover_image = :cov2, has_draft = 0, is_public = :pub WHERE id = :id");
                    $stmt->execute([':t1'=>$input['title'], ':c1'=>$input['content'], ':cov1'=>$cover, ':t2'=>$input['title'], ':c2'=>$input['content'], ':cov2'=>$cover, ':pub'=>$input['is_public'], ':id'=>$input['id']]);
                } else {
                    $stmt = $db->prepare("UPDATE items SET draft_title = :t1, draft_content = :c1, draft_cover_image = :cov1, has_draft = 1, is_public = :pub WHERE id = :id");
                    $stmt->execute([':t1'=>$input['title'], ':c1'=>$input['content'], ':cov1'=>$cover, ':pub'=>$input['is_public'], ':id'=>$input['id']]);
                }
                echo json_encode(['success' => true]);
            }
            break;
        case 'DELETE':
            $db->prepare("DELETE FROM items WHERE id = ? OR parent_id = ?")->execute([$_GET['id'], $_GET['id']]);
            echo json_encode(['success' => true]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}