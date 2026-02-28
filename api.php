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

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fallback voor oudere sessies die nog geen ID of Role hebben vastgelegd
    if (empty($_SESSION['user_id']) && !empty($_SESSION['username'])) {
        $stmt = $db->prepare("SELECT id, role FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
        $u = $stmt->fetch();
        if ($u) {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['role'] = $u['role'] ?: 'admin';
            if (!$u['role']) { // Maak de eerste fallback user automatisch admin
                $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$u['id']]);
            }
        }
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // --- PROFIEL INSTELLINGEN ---
    if ($action === 'profile') {
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT id, username, email, nickname, role FROM users WHERE id = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } elseif ($method === 'PUT') {
            $updatePass = !empty($input['password']);
            $sql = "UPDATE users SET nickname = :nickname, email = :email";
            $params = [':nickname' => $input['nickname'], ':email' => $input['email'], ':id' => $_SESSION['user_id']];
            
            if ($updatePass) {
                $sql .= ", password_hash = :pass";
                $params[':pass'] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = :id";
            $db->prepare($sql)->execute($params);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // --- GEBRUIKERS BEHEER (Alleen Admin) ---
    if ($action === 'users') {
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        if ($method === 'GET') {
            $stmt = $db->query("SELECT id, username, email, nickname, role FROM users ORDER BY id ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            $hash = password_hash($input['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, nickname, role) VALUES (:username, :pass, :email, :nickname, :role)");
            $stmt->execute([
                ':username' => $input['username'],
                ':pass' => $hash,
                ':email' => $input['email'],
                ':nickname' => $input['nickname'],
                ':role' => $input['role']
            ]);
            
            // Automatisch Email Sturen
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER["REQUEST_URI"], '?') . "?portal=open";
            
            $to = $input['email'];
            $subject = "Access to LunarDesk";
            $msg = "Hello " . $input['nickname'] . ",\n\nYou have been granted access to LunarDesk.\n\nUsername: " . $input['username'] . "\nPassword: " . $input['password'] . "\n\nLogin securely here: " . $baseUrl;
            $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];
            
            @mail($to, $subject, $msg, $headers);

            echo json_encode(['success' => true]);
        } elseif ($method === 'PUT') {
            $updatePass = !empty($input['password']);
            $sql = "UPDATE users SET username = :username, nickname = :nickname, email = :email, role = :role";
            $params = [
                ':username' => $input['username'],
                ':nickname' => $input['nickname'],
                ':email' => $input['email'],
                ':role' => $input['role'],
                ':id' => $input['id']
            ];
            if ($updatePass) {
                $sql .= ", password_hash = :pass";
                $params[':pass'] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = :id";
            $db->prepare($sql)->execute($params);
            echo json_encode(['success' => true]);
        } elseif ($method === 'DELETE' && isset($_GET['id'])) {
            if ($_GET['id'] == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'error' => 'Cannot delete yourself']);
                exit;
            }
            $db->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $_GET['id']]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // --- UPLOAD ROUTE ---
    if ($action === 'upload') {
        if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0755, true); }
        $file = $_FILES['image'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', strtolower($ext));
            $destination = __DIR__ . '/uploads/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                echo json_encode(['success' => 1, 'file' => ['url' => 'uploads/' . $filename]]);
            } else {
                echo json_encode(['success' => 0, 'error' => 'Kan bestand niet verplaatsen.']);
            }
        } else {
            echo json_encode(['success' => 0, 'error' => 'Geen bestand ontvangen of upload error.']);
        }
        exit;
    }

    // --- TERMINAL ROUTES ---
    if ($action === 'terminal') {
        if ($method === 'GET') {
            $stmt = $db->query("SELECT * FROM admin_terminal ORDER BY created_at ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            $stmt = $db->prepare("INSERT INTO admin_terminal (sender, content, colorClass) VALUES (:sender, :content, :colorClass)");
            $stmt->execute([':sender' => $input['sender'] ?? 'Admin', ':content' => $input['content'], ':colorClass' => $input['colorClass'] ?? 'text-purple-400']);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // --- WEBHOOK & ROOM ROUTES ---
    if ($action === 'rooms') {
        if ($method === 'GET') {
            $stmt = $db->query("SELECT * FROM rooms ORDER BY title ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            $stmt = $db->prepare("INSERT INTO rooms (title) VALUES (:title)");
            $stmt->execute([':title' => $input['title']]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        } elseif ($method === 'DELETE' && isset($_GET['id'])) {
            $db->prepare("DELETE FROM rooms WHERE id = :id")->execute([':id' => $_GET['id']]);
            $db->prepare("DELETE FROM webhook_messages WHERE room_id = :id")->execute([':id' => $_GET['id']]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($action === 'webhook_messages') {
        if ($method === 'GET' && isset($_GET['room_id'])) {
            $stmt = $db->prepare("SELECT * FROM webhook_messages WHERE room_id = :room_id ORDER BY created_at ASC");
            $stmt->execute([':room_id' => $_GET['room_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        exit;
    }

    if ($action === 'clear_messages') {
        if ($method === 'DELETE' && isset($_GET['room_id'])) {
            $stmt = $db->prepare("DELETE FROM webhook_messages WHERE room_id = :room_id");
            $stmt->execute([':room_id' => $_GET['room_id']]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    if ($action === 'webhook_key') {
        if ($method === 'PUT' && isset($input['id'])) {
            $key = bin2hex(random_bytes(16));
            $stmt = $db->prepare("UPDATE rooms SET webhook_key = :key WHERE id = :id");
            $stmt->execute([':key' => $key, ':id' => $input['id']]);
            echo json_encode(['success' => true, 'key' => $key]);
        } elseif ($method === 'DELETE' && isset($_GET['id'])) {
            $stmt = $db->prepare("UPDATE rooms SET webhook_key = NULL WHERE id = :id");
            $stmt->execute([':id' => $_GET['id']]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // --- DOCUMENTATION EDITOR ROUTES ---
    switch ($method) {
        case 'GET':
            $stmt = $db->query("SELECT * FROM items ORDER BY type DESC, title ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
        case 'POST':
            if (!empty($input['title'])) {
                $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $input['title'])));
                $slug = $baseSlug . '-' . rand(1000, 9999);
                
                $stmt = $db->prepare("INSERT INTO items (title, draft_title, content, draft_content, type, parent_id, slug, is_public, cover_image, draft_cover_image) VALUES (:title1, :title2, :content1, :content2, :type, :parent_id, :slug, :is_public, '', '')");
                $stmt->execute([
                    ':title1' => $input['title'],
                    ':title2' => $input['title'],
                    ':content1' => $input['content'] ?? '',
                    ':content2' => $input['content'] ?? '',
                    ':type' => $input['type'] ?? 'page',
                    ':parent_id' => $input['parent_id'] ?? null,
                    ':slug' => $slug,
                    ':is_public' => $input['is_public'] ?? 0
                ]);
                echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            }
            break;
        case 'PUT':
            if (isset($input['id'])) {
                $cover = $input['cover_image'] ?? '';
                
                // Splits de execute variabelen correct op basis van de actie
                if (isset($input['action']) && $input['action'] === 'publish') {
                    $stmt = $db->prepare("UPDATE items SET title = :title1, content = :content1, cover_image = :cover1, draft_title = :title2, draft_content = :content2, draft_cover_image = :cover2, has_draft = 0, is_public = :is_public WHERE id = :id");
                    $stmt->execute([
                        ':title1' => $input['title'],
                        ':title2' => $input['title'],
                        ':content1' => $input['content'] ?? '',
                        ':content2' => $input['content'] ?? '',
                        ':cover1' => $cover,
                        ':cover2' => $cover,
                        ':is_public' => $input['is_public'] ?? 0, 
                        ':id' => $input['id']
                    ]);
                } else {
                    $stmt = $db->prepare("UPDATE items SET draft_title = :title1, draft_content = :content1, draft_cover_image = :cover1, has_draft = 1, is_public = :is_public WHERE id = :id");
                    $stmt->execute([
                        ':title1' => $input['title'],
                        ':content1' => $input['content'] ?? '',
                        ':cover1' => $cover,
                        ':is_public' => $input['is_public'] ?? 0, 
                        ':id' => $input['id']
                    ]);
                }
                echo json_encode(['success' => true]);
            }
            break;
        case 'DELETE':
            if (isset($_GET['id'])) {
                $stmt = $db->prepare("DELETE FROM items WHERE id = :id OR parent_id = :id");
                $stmt->execute([':id' => $_GET['id']]);
                echo json_encode(['success' => true]);
            }
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>