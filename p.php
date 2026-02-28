<?php
// p.php - Public Viewer
$app_version = "v1.2.5";
$dbPath = __DIR__ . '/data.db';
$slug = $_GET['s'] ?? '';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (empty($slug)) die("No document specified.");
    $stmt = $db->prepare("SELECT title, content, created_at FROM items WHERE slug = :slug AND is_public = 1 LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page) { http_response_code(404); die("Document not found."); }
} catch (PDOException $e) { die("System error"); }

$data = json_decode($page['content'], true);
$blocks = $data['blocks'] ?? [];
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title><?php echo htmlspecialchars($page['title']); ?> | LunarDesk</title><script src="https://cdn.tailwindcss.com"></script>
<style>body { background-color: #0f172a; color: #cbd5e1; } .document-container { max-width: 800px; margin: 40px auto; line-height: 1.7; }</style></head>
<body class="p-6"><div class="document-container"><h1 class="text-4xl font-bold text-white mb-8"><?php echo htmlspecialchars($page['title']); ?></h1><div class="space-y-6">
<?php foreach ($blocks as $block) {
    if ($block['type'] === 'paragraph') echo "<p>" . $block['data']['text'] . "</p>";
    elseif ($block['type'] === 'header') echo "<h" . $block['data']['level'] . " class='text-2xl font-bold text-white'>" . $block['data']['text'] . "</h" . $block['data']['level'] . ">";
    // ... Voeg overige block-parsers toe indien nodig ...
} ?>
</div><footer class="mt-20 border-t border-slate-800 pt-8 text-slate-500 text-xs text-center">Version <?php echo $app_version; ?></footer></div></body></html>