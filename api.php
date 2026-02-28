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

    // Automatische schema updates voor oudere versies
    try { $db->exec("ALTER TABLE items ADD COLUMN cover_image TEXT DEFAULT ''"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE items ADD COLUMN draft_cover_image TEXT DEFAULT ''"); } catch(Exception $e) {}

    // Volledige tabel aanmaken als deze nog niet bestaat
    $db->exec("CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        parent_id INTEGER DEFAULT NULL,
        title TEXT NOT NULL,
        content TEXT DEFAULT '',
        cover_image TEXT DEFAULT '',
        draft_title TEXT,
        draft_content TEXT,
        draft_cover_image TEXT DEFAULT '',
        has_draft INTEGER DEFAULT 0,
        type TEXT DEFAULT 'page',
        is_public INTEGER DEFAULT 0,
        slug TEXT UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, webhook_key TEXT UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS webhook_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL, sender TEXT NOT NULL, content TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS admin_terminal (id INTEGER PRIMARY KEY AUTOINCREMENT, sender TEXT NOT NULL, content TEXT NOT NULL, colorClass TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);

    // --- UPLOAD ROUTE (Voor banners EN editor plaatjes) ---
    if ($action === 'upload') {
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0755, true);
        }
        
        $file = $_FILES['image'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            // Genereer een veilige, unieke bestandsnaam
            $filename = uniqid() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', strtolower($ext));
            $destination = __DIR__ . '/uploads/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Editor.js verwacht deze specifieke JSON output
                echo json_encode([
                    'success' => 1,
                    'file' => ['url' => 'uploads/' . $filename]
                ]);
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
                $stmt = $db->prepare("INSERT INTO items (title, draft_title, content, draft_content, type, parent_id, slug, is_public, cover_image, draft_cover_image) VALUES (:title, :title, :content, :content, :type, :parent_id, :slug, :is_public, '', '')");
                $stmt->execute([
                    ':title' => $input['title'], ':content' => $input['content'] ?? '', ':type' => $input['type'] ?? 'page',
                    ':parent_id' => $input['parent_id'] ?? null, ':slug' => $slug, ':is_public' => $input['is_public'] ?? 0
                ]);
                echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            }
            break;
        case 'PUT':
            if (isset($input['id'])) {
                $cover = $input['cover_image'] ?? '';
                if (isset($input['action']) && $input['action'] === 'publish') {
                    $stmt = $db->prepare("UPDATE items SET title = :title, content = :content, cover_image = :cover, draft_title = :title, draft_content = :content, draft_cover_image = :cover, has_draft = 0, is_public = :is_public WHERE id = :id");
                } else {
                    $stmt = $db->prepare("UPDATE items SET draft_title = :title, draft_content = :content, draft_cover_image = :cover, has_draft = 1, is_public = :is_public WHERE id = :id");
                }
                $stmt->execute([
                    ':title' => $input['title'], ':content' => $input['content'] ?? '', ':cover' => $cover, 
                    ':is_public' => $input['is_public'] ?? 0, ':id' => $input['id']
                ]);
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