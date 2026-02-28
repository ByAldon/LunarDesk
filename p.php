<?php
// p.php - Public Viewer for Extended Editor.js blocks
$app_version = "v1.0.0";
$dbPath = __DIR__ . '/data.db';
$slug = $_GET['s'] ?? '';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (empty($slug)) {
        die("No document specified.");
    }

    $stmt = $db->prepare("SELECT title, content, created_at FROM items WHERE slug = :slug AND is_public = 1 LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$page) {
        http_response_code(404);
        die("Document not found or this page is private.");
    }
} catch (PDOException $e) {
    die("System error: " . $e->getMessage());
}

$data = json_decode($page['content'], true);
$blocks = $data['blocks'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0f172a; color: #cbd5e1; }
        .document-container { max-width: 800px; margin: 0 auto; font-size: 1.125rem; line-height: 1.7; }
        
        .block-header { color: white; font-weight: bold; margin-top: 2em; margin-bottom: 0.5em; }
        .block-header-h1 { font-size: 2.25rem; }
        .block-header-h2 { font-size: 1.875rem; }
        .block-header-h3 { font-size: 1.5rem; }
        
        .block-paragraph { margin-bottom: 1.5em; }
        .block-paragraph a { color: #60a5fa; text-decoration: underline; }
        .block-paragraph b { color: white; font-weight: bold; }
        .block-paragraph i { font-style: italic; }
        
        /* The Color Plugins keep their style for the public page */
        mark.cdx-marker { background: rgba(245, 158, 11, 0.3); color: inherit; padding: 0 4px; border-radius: 3px; }
        code.inline-code { background: #1e293b; color: #4ade80; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 0.9em; border: 1px solid #334155; }
        
        .block-list { margin-bottom: 1.5em; padding-left: 1.5em; list-style-position: outside; }
        .block-list-unordered { list-style-type: disc; }
        .block-list-ordered { list-style-type: decimal; }
        .block-list li { margin-bottom: 0.5em; }
        
        .block-checklist { margin-bottom: 1.5em; }
        .checklist-item { display: flex; align-items: flex-start; margin-bottom: 0.5em; }
        .checklist-box { min-width: 20px; height: 20px; border: 2px solid #334155; border-radius: 4px; margin-right: 12px; margin-top: 4px; display: flex; align-items: center; justify-content: center; }
        .checklist-box.checked { background-color: #3b82f6; border-color: #3b82f6; }
        .checklist-box.checked::after { content: '✓'; color: white; font-size: 12px; }
        .checklist-text.checked { text-decoration: line-through; color: #64748b; }
        
        .block-code { background-color: #1e293b; border: 1px solid #334155; color: #4ade80; padding: 1.5rem; border-radius: 0.5rem; overflow-x: auto; font-family: monospace; font-size: 0.9rem; margin-bottom: 1.5em; }
        .block-delimiter { text-align: center; margin: 2rem 0; font-size: 1.5rem; color: #475569; letter-spacing: 0.5rem; }
        
        /* Table Display */
        .block-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .block-table th { border: 1px solid #334155; padding: 0.75rem; background-color: #1e293b; font-weight: bold; color: white; }
        .block-table td { border: 1px solid #334155; padding: 0.75rem; }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col">

    <nav class="border-b border-slate-800 p-4 mb-12">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <span class="font-bold tracking-tighter text-slate-500">DOCUMENTATION</span>
            <span class="text-xs text-slate-500 uppercase">Published: <?php echo date('d-m-Y', strtotime($page['created_at'])); ?></span>
        </div>
    </nav>

    <main class="flex-1 px-6 pb-24">
        <div class="document-container">
            <h1 class="text-4xl font-black text-white mb-12 border-b border-slate-800 pb-4">
                <?php echo htmlspecialchars($page['title']); ?>
            </h1>

            <div class="content-blocks">
                <?php 
                foreach ($blocks as $block) {
                    $type = $block['type'];
                    $data = $block['data'];

                    if ($type === 'paragraph') {
                        echo "<p class='block-paragraph'>" . $data['text'] . "</p>";
                    } 
                    elseif ($type === 'header') {
                        $level = $data['level'];
                        echo "<h{$level} class='block-header block-header-h{$level}'>" . $data['text'] . "</h{$level}>";
                    } 
                    elseif ($type === 'list') {
                        $style = $data['style'] === 'ordered' ? 'block-list-ordered' : 'block-list-unordered';
                        $tag = $data['style'] === 'ordered' ? 'ol' : 'ul';
                        echo "<{$tag} class='block-list {$style}'>";
                        foreach ($data['items'] as $item) { echo "<li>{$item}</li>"; }
                        echo "</{$tag}>";
                    } 
                    elseif ($type === 'checklist') {
                        echo "<div class='block-checklist'>";
                        foreach ($data['items'] as $item) {
                            $checkedClass = $item['checked'] ? 'checked' : '';
                            echo "<div class='checklist-item'><div class='checklist-box {$checkedClass}'></div><div class='checklist-text {$checkedClass}'>{$item['text']}</div></div>";
                        }
                        echo "</div>";
                    } 
                    elseif ($type === 'code') {
                        echo "<pre class='block-code'>" . htmlspecialchars($data['code']) . "</pre>";
                    }
                    elseif ($type === 'quote') {
                        echo "<blockquote class='border-l-4 border-blue-500 pl-4 py-2 italic text-slate-300 mb-6 bg-slate-800/50 rounded-r-lg'>";
                        echo "<p>" . $data['text'] . "</p>";
                        if (!empty($data['caption'])) { echo "<footer class='text-sm text-slate-500 mt-2'>— " . $data['caption'] . "</footer>"; }
                        echo "</blockquote>";
                    }
                    elseif ($type === 'warning') {
                        echo "<div class='border-l-4 border-amber-500 bg-slate-800 p-4 rounded-r-lg mb-6'>";
                        echo "<h4 class='text-amber-400 font-bold mb-1'>" . $data['title'] . "</h4>";
                        echo "<p class='text-slate-300 text-sm'>" . $data['message'] . "</p>";
                        echo "</div>";
                    }
                    elseif ($type === 'delimiter') {
                        echo "<div class='block-delimiter'>***</div>";
                    }
                    elseif ($type === 'table') {
                        echo "<div class='overflow-x-auto mb-6'><table class='block-table'>";
                        $isWithHeadings = $data['withHeadings'] ?? false;
                        $cellColors = $data['cellColors'] ?? [];
                        
                        foreach ($data['content'] as $rowIndex => $row) {
                            echo "<tr>";
                            foreach ($row as $colIndex => $cell) { 
                                $bgColorStyle = "";
                                // Check if a custom color was saved for this specific cell
                                if (isset($cellColors[$rowIndex][$colIndex]) && !empty($cellColors[$rowIndex][$colIndex])) {
                                    $safeColor = htmlspecialchars($cellColors[$rowIndex][$colIndex]);
                                    $bgColorStyle = " style='background-color: {$safeColor};'";
                                }

                                if ($isWithHeadings && $rowIndex === 0) {
                                    echo "<th{$bgColorStyle}>" . $cell . "</th>"; 
                                } else {
                                    echo "<td{$bgColorStyle}>" . $cell . "</td>"; 
                                }
                            }
                            echo "</tr>";
                        }
                        echo "</table></div>";
                    }
                    elseif ($type === 'image') {
                        echo "<figure class='mb-6'>";
                        echo "<img src='" . htmlspecialchars($data['url']) . "' alt='Image' class='rounded-lg border border-slate-700 max-w-full h-auto'>";
                        if (!empty($data['caption'])) { echo "<figcaption class='text-center text-sm text-slate-500 mt-2'>" . $data['caption'] . "</figcaption>"; }
                        echo "</figure>";
                    }
                    elseif ($type === 'embed') {
                        echo "<figure class='mb-6'>";
                        echo "<iframe src='" . htmlspecialchars($data['embed']) . "' width='" . $data['width'] . "' height='" . $data['height'] . "' class='w-full rounded-lg border border-slate-700 aspect-video' frameborder='0' allowfullscreen></iframe>";
                        if (!empty($data['caption'])) { echo "<figcaption class='text-center text-sm text-slate-500 mt-2'>" . $data['caption'] . "</figcaption>"; }
                        echo "</figure>";
                    }
                }
                ?>
            </div>
        </div>
    </main>

    <footer class="border-t border-slate-800 py-8 text-center text-slate-500 text-sm">
        Published via Workspace &bull; <?php echo $app_version; ?>
    </footer>

</body>
</html>