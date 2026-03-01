<?php
// p.php - Public Viewer
$app_version = "v1.5.5-beta";
$dbPath = __DIR__ . '/data.db';
$slug = $_GET['s'] ?? '';
try {
    $db = new PDO("sqlite:$dbPath");
    $stmt = $db->prepare("SELECT * FROM items WHERE slug = :s AND is_public = 1");
    $stmt->execute([':s' => $slug]); $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page) die("Unauthorized.");
    $items = $db->query("SELECT id, title, type, parent_id, slug, is_public FROM items")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { die("Offline"); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page['title']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/checklist@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/table@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/editorjs-text-color-plugin@2.0.4/dist/bundle.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-slate-950 text-slate-300 flex h-screen overflow-hidden">
    <aside id="sidebar" class="w-80 bg-slate-900 border-r border-slate-950 shrink-0 h-full overflow-y-auto flex flex-col">
        <div class="h-16 px-6 flex items-center shrink-0 border-b border-slate-950"><span class="font-black text-white uppercase text-xs">Public Docs</span></div>
        <div class="p-4 space-y-6">
            <?php foreach (array_filter($items, fn($i) => $i['type'] === 'space') as $space): ?>
                <?php $spages = array_filter($items, fn($i) => $i['type'] === 'page' && $i['parent_id'] == $space['id'] && $i['is_public'] == 1); ?>
                <?php if (empty($spages)) continue; ?>
                <div><h3 class="text-[10px] font-black text-slate-700 uppercase tracking-widest mb-3 ml-3"><?php echo $space['title']; ?></h3>
                    <ul class="space-y-1">
                        <?php foreach ($spages as $sp): ?>
                            <li class="nav-item">
                                <a href="?s=<?php echo $sp['slug']; ?>" class="flex items-center px-4 py-2 text-sm <?php echo $sp['slug'] == $slug ? 'bg-slate-950 text-white font-bold nav-item-active shadow-inner' : 'text-slate-600 hover:text-white'; ?>">
                                    <div class="nav-indicator"></div><?php echo $sp['title']; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>
    <main class="flex-1 h-full overflow-y-auto flex flex-col">
        <header class="h-80 bg-slate-900 relative" <?php if($page['cover_image']) echo "style='background:url({$page['cover_image']}) center/cover'"; ?>><div class="absolute inset-0 bg-slate-950/40"></div><h1 class="absolute bottom-10 left-12 text-6xl font-black text-white drop-shadow-2xl"><?php echo $page['title']; ?></h1></header>
        <div class="p-12 max-w-4xl mx-auto w-full"><div id="editorjs"></div></div>
    </main>
    <script>
        const content = <?php echo $page['content'] ?: '{"blocks":[]}'; ?>;
        new EditorJS({ holder: 'editorjs', data: content, readOnly: true, tools: { header: Header, list: {class: EditorjsList}, checklist: Checklist, table: Table, Color: window.ColorPlugin } });
    </script>
</body>
</html>