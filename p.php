<?php
// p.php - Public Viewer
$app_version = "v1.2.9-beta";
$dbPath = __DIR__ . '/data.db';
$slug = $_GET['s'] ?? '';

if (empty($slug)) {
    header("HTTP/1.0 404 Not Found");
    die("Page not found.");
}

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->prepare("SELECT * FROM items WHERE slug = :slug AND is_public = 1 LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$page) {
        header("HTTP/1.0 404 Not Found");
        die("This page does not exist or is not public.");
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
<body class="bg-slate-950 text-slate-300 min-h-screen flex flex-col">
    <header class="relative border-b border-slate-800 flex flex-col justify-end shrink-0 transition-all duration-300 <?php echo !empty($page['cover_image']) ? 'h-64' : 'h-28 bg-slate-900'; ?>" <?php if(!empty($page['cover_image'])) echo 'style="background-image: url(\''.htmlspecialchars($page['cover_image']).'\'); background-size: cover; background-position: center;"'; ?>>
        <?php if(!empty($page['cover_image'])): ?>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/50 to-transparent z-0"></div>
        <?php endif; ?>
        <div class="relative z-10 p-6 pb-4 w-full mt-auto max-w-4xl mx-auto">
            <h1 class="text-4xl font-black text-white drop-shadow-md"><?php echo htmlspecialchars($page['title']); ?></h1>
        </div>
    </header>
    
    <main class="flex-1 p-8 w-full max-w-4xl mx-auto">
        <div id="editorjs" class="w-full"></div>
    </main>
    
    <footer class="bg-slate-900 border-t border-slate-800 p-4 text-center mt-auto shrink-0">
        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Powered by LunarDesk <?php echo $app_version; ?></span>
    </footer>

    <script>
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
                // Herstel de opgeslagen cel-kleuren in de read-only weergave
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