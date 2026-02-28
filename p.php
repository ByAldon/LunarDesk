<?php
// p.php - Public Viewer
$app_version = "v1.3.7-beta";
$dbPath = __DIR__ . '/data.db';
$slug = $_GET['s'] ?? '';

if (empty($slug)) {
    header("HTTP/1.0 404 Not Found");
    die("Page not found.");
}

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Huidige pagina ophalen
    $stmt = $db->prepare("SELECT * FROM items WHERE slug = :slug AND is_public = 1 LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        header("HTTP/1.0 404 Not Found");
        die("This page does not exist or is not public.");
    }

    // Alle items ophalen om het menu te bouwen
    $stmtAll = $db->query("SELECT id, title, type, parent_id, slug, is_public FROM items ORDER BY title ASC");
    $allItems = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    $spaces = [];
    $pages = [];
    $subpages = [];

    foreach ($allItems as $item) {
        if ($item['type'] === 'space') {
            $spaces[] = $item;
        } elseif ($item['type'] === 'page' && $item['is_public'] == 1) {
            $pages[] = $item;
        } elseif ($item['type'] === 'subpage' && $item['is_public'] == 1) {
            $subpages[] = $item;
        }
    }

} catch (PDOException $e) {
    die("System error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page['title']); ?> | LunarDesk <?php echo $app_version; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><rect width='512' height='512' fill='%232563eb' rx='115'/><path d='M 350 256 A 110 110 0 1 1 220 140 A 130 130 0 0 0 350 256 Z' fill='%2393c5fd' opacity='0.9'/><path d='M 190 170 V 330 H 310' fill='none' stroke='%23ffffff' stroke-width='48' stroke-linecap='round' stroke-linejoin='round'/></svg>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/checklist@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/code@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/table@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/warning@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/inline-code@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/simple-image@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/image@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/embed@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/editorjs-text-color-plugin@2.0.4/dist/bundle.js"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-slate-950 text-slate-300 flex h-screen overflow-hidden">
    
    <div id="mobile-overlay" class="fixed inset-0 bg-black/60 z-40 hidden md:hidden" onclick="toggleMenu()"></div>

    <aside id="sidebar" class="w-72 bg-slate-900 border-r border-slate-800 shrink-0 h-full overflow-y-auto flex-col z-50 fixed md:relative -translate-x-full md:translate-x-0 transition-transform duration-300 flex">
        <div class="p-4 border-b border-slate-800 shrink-0 flex items-center justify-between sticky top-0 bg-slate-900 z-10">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="w-6 h-6 rounded-md shadow-sm"><rect width="512" height="512" fill="#2563eb"/><path d="M 350 256 A 110 110 0 1 1 220 140 A 130 130 0 0 0 350 256 Z" fill="#93c5fd" opacity="0.9"/><path d="M 190 170 V 330 H 310" fill="none" stroke="#ffffff" stroke-width="48" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="font-black text-white uppercase tracking-widest text-xs">LunarDesk</span>
            </div>
            <button class="md:hidden text-slate-400 hover:text-white text-xl font-bold" onclick="toggleMenu()">✕</button>
        </div>
        <div class="p-4 flex-1 space-y-6">
            <?php foreach ($spaces as $space): ?>
                <?php 
                    $spacePages = array_filter($pages, fn($p) => $p['parent_id'] == $space['id']); 
                    if (empty($spacePages)) continue; 
                ?>
                <div>
                    <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2"><?php echo htmlspecialchars($space['title']); ?></h3>
                    <ul class="space-y-1 border-l border-slate-700 ml-1">
                        <?php foreach ($spacePages as $spage): ?>
                            <li>
                                <a href="?s=<?php echo $spage['slug']; ?>" class="block pl-3 py-1.5 text-sm rounded transition <?php echo ($spage['id'] == $page['id']) ? 'bg-slate-800 text-blue-400 font-bold' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                                    <?php echo htmlspecialchars($spage['title']); ?>
                                </a>
                                <?php 
                                    $pageSubpages = array_filter($subpages, fn($sub) => $sub['parent_id'] == $spage['id']); 
                                    if (!empty($pageSubpages)): 
                                ?>
                                    <ul class="mt-1">
                                        <?php foreach ($pageSubpages as $subpage): ?>
                                            <li>
                                                <a href="?s=<?php echo $subpage['slug']; ?>" class="block pl-6 py-1.5 text-xs rounded transition flex items-center <?php echo ($subpage['id'] == $page['id']) ? 'bg-slate-800 text-blue-400 font-bold' : 'text-slate-400 hover:bg-slate-800 hover:text-white'; ?>">
                                                    <span class="mr-2 opacity-50">↳</span> <?php echo htmlspecialchars($subpage['title']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>

    <div class="flex-1 h-full overflow-y-auto relative flex flex-col">
        <button onclick="toggleMenu()" class="md:hidden absolute top-4 left-4 z-30 bg-slate-800/80 backdrop-blur border border-slate-700 text-white w-10 h-10 rounded shadow-lg flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
        </button>
        
        <header class="relative border-b border-slate-800 flex flex-col justify-end shrink-0 transition-all duration-300 <?php echo !empty($page['cover_image']) ? 'h-64' : 'h-28 bg-slate-900'; ?>" <?php if(!empty($page['cover_image'])) echo 'style="background-image: url(\''.htmlspecialchars($page['cover_image']).'\'); background-size: cover; background-position: center;"'; ?>>
            <?php if(!empty($page['cover_image'])): ?>
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/50 to-transparent z-0"></div>
            <?php endif; ?>
            <div class="relative z-10 p-6 pb-4 w-full mt-auto max-w-4xl mx-auto pl-16 md:pl-6">
                <h1 class="text-4xl font-black text-white drop-shadow-md"><?php echo htmlspecialchars($page['title']); ?></h1>
            </div>
        </header>
        
        <main class="flex-1 p-8 w-full max-w-4xl mx-auto">
            <div id="editorjs" class="w-full"></div>
        </main>
        
        <footer class="bg-slate-900 border-t border-slate-800 p-4 text-center mt-auto shrink-0">
            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">
                Powered by LunarDesk <?php echo $app_version; ?> &bull; Made by <a href="https://github.com/ByAldon" target="_blank" class="text-blue-400 hover:text-blue-300 transition">Aldon</a>
            </span>
        </footer>
    </div>

    <script>
        function toggleMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        let contentData = { blocks: [] };
        try {
            contentData = <?php echo !empty($page['content']) ? $page['content'] : '{ blocks: [] }'; ?>;
        } catch(e) {
            console.error("Could not parse page content", e);
        }

        const editor = new EditorJS({
            holder: 'editorjs',
            data: contentData,
            readOnly: true, 
            tools: {
                header: { class: Header },
                list: { class: EditorjsList },
                checklist: { class: Checklist },
                code: CodeTool,
                table: { class: Table },
                quote: { class: Quote },
                warning: { class: Warning },
                image: { class: ImageTool },
                Color: { class: window.ColorPlugin },
                Marker: { class: window.ColorPlugin },
                delimiter: Delimiter,
                inlineCode: { class: InlineCode },
                embed: { class: Embed }
            },
            onReady: () => {
                setTimeout(() => {
                    const tableBlocks = document.querySelectorAll('.ce-block .tc-table');
                    let tableIndex = 0;
                    contentData.blocks.forEach(block => {
                        if (block.type === 'table' && block.data.cellColors) {
                            const domTable = tableBlocks[tableIndex++];
                            if (domTable) {
                                const rows = domTable.querySelectorAll('.tc-row');
                                rows.forEach((row, rIdx) => {
                                    const cells = row.querySelectorAll('.tc-cell');
                                    cells.forEach((cell, cIdx) => {
                                        if (block.data.cellColors[rIdx] && block.data.cellColors[rIdx][cIdx]) {
                                            cell.style.backgroundColor = block.data.cellColors[rIdx][cIdx];
                                        }
                                    });
                                });
                            }
                        } else if (block.type === 'table') {
                            tableIndex++;
                        }
                    });
                }, 300);
            }
        });
    </script>
</body>
</html>