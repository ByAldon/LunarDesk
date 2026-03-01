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
include 'version.php';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // --- PROFILE & USERS ---
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
            $email = $input['email'];
            $role = $input['role'];
            
            $stmt = $db->prepare("INSERT INTO users (username, email, role, reset_token, reset_expires) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$temp, $email, $role, $token, time() + 259200]);

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $inviteLink = $protocol . $domain . $path . '/reset.php?token=' . $token;

            $subject = "You have been invited to LunarDesk";
            $message = "Hello,\r\n\r\nYou have been invited to join LunarDesk as a {$role}.\r\n\r\nClick the link below to set up your account:\r\n{$inviteLink}\r\n\r\nThis link will expire in 3 days.";
            $headers = "From: noreply@" . $domain . "\r\n" .
                       "Reply-To: noreply@" . $domain . "\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            $mailSent = @mail($email, $subject, $message, $headers);

            echo json_encode(['success' => true, 'token' => $token, 'mailSent' => $mailSent, 'link' => $inviteLink]);
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

    // --- ADMIN TERMINAL ---
    if ($action === 'admin_terminal') {
        if ($method === 'POST') {
            $content = trim($input['content'] ?? '');
            if ($content !== '') {
                
                $stmt = $db->prepare("SELECT nickname, username FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $sender = !empty($user['nickname']) ? $user['nickname'] : $user['username'];

                $saveUserMessage = true;
                $sysReply = '';
                $replyColor = 'text-blue-400';
                $clearChat = false;

                // 1. Controleer of we in de "Weet je het zeker?" modus zitten
                if (isset($_SESSION['pending_terminal_delete']) && $_SESSION['pending_terminal_delete'] === true) {
                    $upperContent = strtoupper($content);
                    if ($upperContent === 'YES') {
                        $clearChat = true;
                        $sysReply = "Terminal history successfully purged by Admin.";
                        $replyColor = 'text-amber-500';
                        $_SESSION['pending_terminal_delete'] = false;
                        $saveUserMessage = false; // YES hoeft niet te blijven staan
                    } elseif ($upperContent === 'NO') {
                        $sysReply = "Terminal purge cancelled.";
                        $_SESSION['pending_terminal_delete'] = false;
                        $saveUserMessage = false; // NO hoeft niet te blijven staan
                    } else {
                        // Typt de gebruiker iets anders? Dan annuleren we stilletjes en gaan we door.
                        $_SESSION['pending_terminal_delete'] = false;
                    }
                }

                // 2. Normale commando afhandeling
                if ($sysReply === '' && strpos($content, '/') === 0) {
                    $cmd = strtolower(substr($content, 1));
                    if ($cmd === 'help') {
                        $sysReply = "Available commands: /help, /ping, /status, /version, /delete";
                    } elseif ($cmd === 'ping') {
                        $sysReply = "Pong! Connection to main server is stable.";
                    } elseif ($cmd === 'status') {
                        $sysReply = "All systems functioning nominally. No errors detected.";
                    } elseif ($cmd === 'version') {
                        $sysReply = "LunarDesk System Version: " . $app_version;
                    } elseif ($cmd === 'delete') {
                        if ($_SESSION['role'] === 'admin') {
                            $_SESSION['pending_terminal_delete'] = true;
                            $sysReply = "Are you sure you want to purge the terminal history? Type YES or NO.";
                            $replyColor = 'text-amber-500';
                            $saveUserMessage = false;
                        } else {
                            $sysReply = "Access Denied: Admin privileges required for /delete.";
                            $replyColor = 'text-red-500';
                        }
                    } else {
                        $sysReply = "Command not recognized. Type '/help' for a list of commands.";
                    }
                }

                // 3. Uitvoeren in de database
                if ($clearChat) {
                    $db->exec("DELETE FROM admin_terminal");
                } elseif ($saveUserMessage) {
                    $db->prepare("INSERT INTO admin_terminal (sender, content, colorClass) VALUES (?, ?, ?)")
                       ->execute([$sender, $content, 'text-slate-300']);
                }

                if ($sysReply !== '') {
                    $db->prepare("INSERT INTO admin_terminal (sender, content, colorClass) VALUES (?, ?, ?)")
                       ->execute(['System', $sysReply, $replyColor]);
                }

                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
        } else {
            echo json_encode($db->query("SELECT * FROM admin_terminal ORDER BY created_at ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC));
        }
        exit;
    }

    // --- CHANNELS (ROOMS & WEBHOOKS) ---
    if ($action === 'rooms') {
        if ($method === 'GET') {
            echo json_encode($db->query("SELECT * FROM rooms ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            $db->prepare("INSERT INTO rooms (title) VALUES (?)")->execute([$input['title']]);
            echo json_encode(['success' => true]);
        } elseif ($method === 'PUT') {
            $id = $_GET['id'] ?? 0;
            if (isset($_GET['revoke']) && $_GET['revoke'] == '1') {
                $db->prepare("UPDATE rooms SET webhook_key = NULL WHERE id = ?")->execute([$id]);
            } else {
                $key = bin2hex(random_bytes(16));
                $db->prepare("UPDATE rooms SET webhook_key = ? WHERE id = ?")->execute([$key, $id]);
            }
            echo json_encode(['success' => true]);
        } elseif ($method === 'DELETE') {
            $db->prepare("DELETE FROM rooms WHERE id = ?")->execute([$_GET['id']]);
            $db->prepare("DELETE FROM webhook_messages WHERE room_id = ?")->execute([$_GET['id']]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // --- WEBHOOK MESSAGES (STREAM) ---
    if ($action === 'messages') {
        $roomId = $_GET['room_id'] ?? 0;
        if ($method === 'GET') {
            $stmt = $db->prepare("SELECT * FROM webhook_messages WHERE room_id = ? ORDER BY created_at ASC");
            $stmt->execute([$roomId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'DELETE') {
            $db->prepare("DELETE FROM webhook_messages WHERE room_id = ?")->execute([$roomId]);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // --- IMAGE UPLOADS ---
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

    // --- SPACES & PAGES (DEFAULT CRUD) ---
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
} catch (PDOException $e) { 
    echo json_encode(['error' => $e->getMessage()]); 
}
?>
