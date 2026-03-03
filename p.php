<?php
// p.php - Public Viewer
include 'version.php';
$dbPath = __DIR__ . '/data.db';
$slug = $_GET['s'] ?? '';
try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try { $db->exec("ALTER TABLE items ADD COLUMN created_at TEXT"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE items ADD COLUMN updated_at TEXT"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE items ADD COLUMN created_by INTEGER"); } catch (PDOException $e) {}
    try { $db->exec("ALTER TABLE items ADD COLUMN updated_by INTEGER"); } catch (PDOException $e) {}

    $stmt = $db->prepare("
        SELECT 
            i.*,
            COALESCE(uc.nickname, uc.username) AS created_by_name,
            COALESCE(uu.nickname, uu.username) AS updated_by_name
        FROM items i
        LEFT JOIN users uc ON uc.id = i.created_by
        LEFT JOIN users uu ON uu.id = i.updated_by
        WHERE i.slug = :s AND i.is_public = 1
    ");
    $stmt->execute([':s' => $slug]); $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page) die("Unauthorized.");
    $items = $db->query("SELECT id, title, type, parent_id, slug, is_public, sort_order FROM items ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { die("Offline"); }

$createdAt = $page['created_at'] ?? '';
$updatedAt = $page['updated_at'] ?? '';
$createdBy = $page['created_by'] ?? null;
$updatedBy = $page['updated_by'] ?? null;
$changedByOtherUser = !empty($createdBy) && !empty($updatedBy) && (int)$createdBy !== (int)$updatedBy;
$changedTime = !empty($createdAt) && !empty($updatedAt) && $createdAt !== $updatedAt;
$wasUpdated = $changedByOtherUser || $changedTime;
$metaLabel = $wasUpdated ? 'Updated' : 'Created';
$metaDateRaw = $wasUpdated ? $updatedAt : $createdAt;
$metaDate = '';
if (!empty($metaDateRaw)) {
    $ts = strtotime($metaDateRaw);
    $metaDate = $ts ? date('M j, Y H:i', $ts) : $metaDateRaw;
}
$metaActor = $wasUpdated
    ? ($page['updated_by_name'] ?: $page['created_by_name'] ?: 'Unknown')
    : ($page['created_by_name'] ?: $page['updated_by_name'] ?: 'Unknown');
$metaParts = [$metaLabel];
if ($metaDate !== '') $metaParts[] = $metaDate;
if (!empty($metaActor)) $metaParts[] = 'by ' . $metaActor;
$metaText = implode(' ', $metaParts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page['title']); ?> | LunarDesk</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHZpZXdCb3g9JzAgMCA1MTIgNTEyJz48cmVjdCB3aWR0aD0nNTEyJyBoZWlnaHQ9JzUxMicgZmlsbD0nIzI1NjNlYicgcng9JzExNScvPjxwYXRoIGQ9J00gMzUwIDI1NiBBIDExMCAxMTAgMCAxIDEgMjIwIDE0MCBBIDEzMCAxMzAgMCAwIDAgMzUwIDI1NiBaJyBmaWxsPScjOTNjNWZkJyBvcGFjaXR5PScwLjknLz48cGF0aCBkPSdNIDE5MCAxNzAgViAzMzAgSCAzMTAnIGZpbGw9J25vbmUnIHN0cm9rZT0nI2ZmZmZmZicgc3Ryb2tlLXdpZHRoPSc0OCcgc3Ryb2tlLWxpbmVjYXA9J3JvdW5kJyBzdHJva2UtbGluZWpvaW49J3JvdW5kJy8+PC9zdmc+">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/paragraph@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@2.8.8"></script>
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
    
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="public-page bg-slate-900 text-slate-300 flex h-screen overflow-hidden selection:bg-blue-500/30 p-6 gap-2">
    <aside id="sidebar" class="w-80 bg-slate-800/80 border border-slate-700 shadow-2xl shrink-0 h-full overflow-y-auto flex flex-col rounded-3xl">
        
        <div class="h-16 px-6 flex items-center shrink-0 border-b border-slate-700/50 bg-slate-900/60 gap-3">
            <div class="bg-blue-600 p-1.5 rounded-lg shadow-lg shadow-blue-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="w-4 h-4"><path d="M 350 256 A 110 110 0 1 1 220 140 A 130 130 0 0 0 350 256 Z" fill="#93c5fd" opacity="0.9"/><path d="M 190 170 V 330 H 310" fill="none" stroke="#ffffff" stroke-width="48" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <span class="font-black text-white uppercase tracking-widest text-xs drop-shadow-md">LunarDesk</span>
        </div>

        <div class="p-4 space-y-6">
            <?php foreach (array_filter($items, fn($i) => $i['type'] === 'space') as $space): ?>
                <?php $spages = array_filter($items, fn($i) => $i['type'] === 'page' && $i['parent_id'] == $space['id'] && $i['is_public'] == 1); ?>
                <?php if (empty($spages)) continue; ?>
                <div><h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3 ml-3"><?php echo $space['title']; ?></h3>
                    <ul class="space-y-1">
                        <?php foreach ($spages as $sp): ?>
                            <li class="nav-item">
                                <a href="?s=<?php echo $sp['slug']; ?>" class="rounded-xl transition-all flex items-center px-4 py-2 text-base <?php echo $sp['slug'] == $slug ? 'bg-blue-600/20 text-blue-400 font-bold nav-item-active shadow-inner' : 'text-slate-300 hover:bg-slate-700/50 hover:text-white'; ?>">
                                    <?php echo $sp['title']; ?>
                                </a>
                                <?php $subpages = array_filter($items, fn($i) => $i['type'] === 'subpage' && $i['parent_id'] == $sp['id'] && $i['is_public'] == 1); ?>
                                <?php if (!empty($subpages)): ?>
                                    <ul class="mt-1 ml-4 space-y-1">
                                        <?php foreach ($subpages as $subp): ?>
                                            <li class="nav-item">
                                                <a href="?s=<?php echo $subp['slug']; ?>" class="rounded-xl transition-all flex items-center pl-4 pr-2 py-1.5 text-sm <?php echo $subp['slug'] == $slug ? 'bg-blue-600/20 text-blue-400 font-bold nav-item-active shadow-inner' : 'text-slate-400 hover:bg-slate-700/50 hover:text-white'; ?>">
                                                    <?php echo $subp['title']; ?>
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
        
        <div class="mt-auto p-6 border-t border-slate-700/50 bg-slate-900/40">
            <span class="block text-[9px] text-slate-500 font-black uppercase tracking-[0.3em] mb-2">LunarDesk &bull; <?php echo $app_version; ?> <br> Timezone is <a href="https://time.is/UTC" target="_blank">UTC</a></span>
            <span class="block text-[9px] text-slate-500 font-black uppercase tracking-[0.3em]">2026 &copy; Ported by <a href="https://github.com/ByAldon" target="_blank" class="hover:text-blue-400 transition-colors">Aldon</a></span>
        </div>
    </aside>
    <main class="bg-slate-800/80 border border-slate-700 shadow-2xl rounded-3xl flex-1 h-full overflow-y-auto flex flex-col">
        <header class="h-80 shrink-0 bg-transparent relative overflow-hidden" <?php if($page['cover_image']) echo "style='background:url({$page['cover_image']}) center/cover'"; ?>>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-800 to-transparent"></div>
            <div class="absolute bottom-10 left-12">
                <h1 class="text-6xl font-black text-white drop-shadow-2xl"><?php echo htmlspecialchars($page['title']); ?></h1>
                <p class="mt-3 text-xs uppercase tracking-[0.18em] text-slate-300/90"><?php echo htmlspecialchars($metaText); ?></p>
            </div>
        </header>
        <div class="p-12 px-20 max-w-5xl mx-auto w-full pb-32"><div id="editorjs"></div></div>
    </main>
    <script>
        const rawContent = <?php echo $page['content'] ?: '{"blocks":[]}'; ?>;
        const normalizeTableBlocksForReadOnly = (doc) => {
            if (!doc || !Array.isArray(doc.blocks)) return { blocks: [] };
            const cloned = JSON.parse(JSON.stringify(doc));
            cloned.blocks = cloned.blocks.map((block) => {
                if (!block || block.type !== 'table') return block;
                const data = block.data && typeof block.data === 'object' ? block.data : {};
                const styles = data.ld_styles && typeof data.ld_styles === 'object' ? data.ld_styles : {};
                const sourceRows = Array.isArray(data.content) ? data.content : [];
                const rows = sourceRows.map((row) => {
                    if (!Array.isArray(row)) return [];
                    return row.map((cell) => (cell == null ? '' : String(cell)));
                });
                const columnWidths = styles.columnWidths && typeof styles.columnWidths === 'object' ? styles.columnWidths : {};
                const rowHeights = styles.rowHeights && typeof styles.rowHeights === 'object' ? styles.rowHeights : {};
                const maxColsFromContent = rows.reduce((m, r) => Math.max(m, r.length), 0);
                const maxColsFromStyles = Object.keys(columnWidths).reduce((m, key) => {
                    const idx = Number(key);
                    return Number.isFinite(idx) ? Math.max(m, idx + 1) : m;
                }, 0);
                const maxCols = Math.max(maxColsFromContent, maxColsFromStyles, 0);

                const maxRowsFromStyles = Object.keys(rowHeights).reduce((m, key) => {
                    const idx = Number(key);
                    return Number.isFinite(idx) ? Math.max(m, idx + 1) : m;
                }, 0);
                const targetRows = Math.max(rows.length, maxRowsFromStyles, 0);
                while (rows.length < targetRows) rows.push([]);

                if (maxCols > 0) {
                    for (let r = 0; r < rows.length; r++) {
                        while (rows[r].length < maxCols) rows[r].push('');
                    }
                }
                return { ...block, data: { ...data, content: rows } };
            });
            return cloned;
        };
        const content = normalizeTableBlocksForReadOnly(rawContent);
        const HeaderToolClass = window.Header || window.EditorjsHeader;
        const ParagraphToolClass = window.Paragraph;
        const getRows = (tableEl) => {
            const tcRows = Array.from(tableEl.querySelectorAll('.tc-row'));
            if (tcRows.length > 0) {
                return tcRows.map((row) => Array.from(row.querySelectorAll('.tc-cell')));
            }
            const trRows = Array.from(tableEl.querySelectorAll('tr'));
            return trRows.map((row) => Array.from(row.querySelectorAll('td, th')));
        };
        const applyStoredTableMerges = (tableEl, cellMerges) => {
            if (!tableEl || !cellMerges || typeof cellMerges !== 'object') return;
            const keys = Object.keys(cellMerges).sort((a, b) => {
                const [ar, ac] = a.split('-').map(Number);
                const [br, bc] = b.split('-').map(Number);
                if (ar !== br) return ar - br;
                return ac - bc;
            });
            keys.forEach((key) => {
                const merge = cellMerges[key] || {};
                const targetColspan = Math.max(1, parseInt(merge.colspan || '1', 10));
                const targetRowspan = Math.max(1, parseInt(merge.rowspan || '1', 10));
                const [rIdx, cIdx] = key.split('-').map(Number);
                if (!Number.isFinite(rIdx) || !Number.isFinite(cIdx)) return;

                const rows = getRows(tableEl);
                const origin = rows[rIdx] && rows[rIdx][cIdx];
                if (!origin) return;

                if (targetColspan > 1) {
                    for (let i = 1; i < targetColspan; i++) {
                        const freshRows = getRows(tableEl);
                        const row = freshRows[rIdx] || [];
                        const right = row[cIdx + 1];
                        if (!right) break;
                        if (right.parentNode) right.parentNode.removeChild(right);
                    }
                    origin.setAttribute('colspan', String(targetColspan));
                }
                if (targetRowspan > 1) {
                    for (let i = 1; i < targetRowspan; i++) {
                        const freshRows = getRows(tableEl);
                        const row = freshRows[rIdx + i] || [];
                        const below = row[cIdx];
                        if (!below) continue;
                        if (below.parentNode) below.parentNode.removeChild(below);
                    }
                    origin.setAttribute('rowspan', String(targetRowspan));
                }
            });
        };
        const buildFallbackTable = (block) => {
            const data = block && block.data && typeof block.data === 'object' ? block.data : {};
            const rows = Array.isArray(data.content) ? data.content.map((row) => Array.isArray(row) ? [...row] : []) : [];
            const styles = data.ld_styles && typeof data.ld_styles === 'object' ? data.ld_styles : {};
            const cellMerges = styles.cellMerges || {};
            const colors = styles.cellColors || {};
            const paddings = styles.cellPaddings || {};
            const textAlign = styles.cellTextAlign || {};
            const verticalAlign = styles.cellVerticalAlign || {};
            const borderColor = styles.cellBorderColor || {};
            const borderWidth = styles.cellBorderWidth || {};
            const columnWidths = styles.columnWidths || {};
            const rowHeights = styles.rowHeights || {};
            const tableOptions = styles.tableOptions || {};
            const maxColsFromRows = rows.reduce((m, r) => Math.max(m, Array.isArray(r) ? r.length : 0), 0);
            const maxColsFromStyles = Object.keys(columnWidths).reduce((m, key) => {
                const idx = Number(key);
                return Number.isFinite(idx) ? Math.max(m, idx + 1) : m;
            }, 0);
            const maxCols = Math.max(maxColsFromRows, maxColsFromStyles, 0);
            const maxRowsFromStyles = Object.keys(rowHeights).reduce((m, key) => {
                const idx = Number(key);
                return Number.isFinite(idx) ? Math.max(m, idx + 1) : m;
            }, 0);
            while (rows.length < maxRowsFromStyles) rows.push([]);
            rows.forEach((row) => {
                while (row.length < maxCols) row.push('');
            });
            const occupied = new Set();

            const wrap = document.createElement('div');
            wrap.style.overflowX = 'auto';
            wrap.style.margin = '8px 0';

            const table = document.createElement('table');
            table.style.width = tableOptions.width ? String(tableOptions.width) : '100%';
            table.style.tableLayout = tableOptions.tableLayout ? String(tableOptions.tableLayout) : 'fixed';
            table.style.borderCollapse = 'collapse';
            table.style.borderSpacing = '0';
            table.style.backgroundColor = 'transparent';

            for (let r = 0; r < rows.length; r++) {
                const tr = document.createElement('tr');
                const row = Array.isArray(rows[r]) ? rows[r] : [];
                for (let c = 0; c < maxCols; c++) {
                    if (occupied.has(`${r}-${c}`)) continue;
                    const td = document.createElement('td');
                    const key = `${r}-${c}`;
                    const merge = cellMerges[key] || {};
                    const colspan = Math.max(1, parseInt(merge.colspan || '1', 10));
                    const rowspan = Math.max(1, parseInt(merge.rowspan || '1', 10));
                    if (colspan > 1) td.colSpan = colspan;
                    if (rowspan > 1) td.rowSpan = rowspan;

                    for (let rr = r; rr < r + rowspan; rr++) {
                        for (let cc = c; cc < c + colspan; cc++) {
                            if (rr === r && cc === c) continue;
                            occupied.add(`${rr}-${cc}`);
                        }
                    }

                    td.innerHTML = row[c] ? String(row[c]) : '<br>';
                    td.style.borderStyle = 'solid';
                    td.style.borderColor = borderColor[key] ? String(borderColor[key]) : '#334155';
                    td.style.borderWidth = borderWidth[key] ? String(borderWidth[key]) : '1px';
                    td.style.padding = paddings[key] ? String(paddings[key]) : '8px';
                    td.style.textAlign = textAlign[key] ? String(textAlign[key]) : '';
                    td.style.verticalAlign = verticalAlign[key] ? String(verticalAlign[key]) : '';
                    if (colors[key]) td.style.backgroundColor = String(colors[key]);

                    const width = Number(columnWidths[c]);
                    if (Number.isFinite(width) && width > 0) {
                        td.style.width = `${width}px`;
                        td.style.minWidth = `${width}px`;
                    }
                    const height = Number(rowHeights[r]);
                    if (Number.isFinite(height) && height > 0) {
                        td.style.height = `${height}px`;
                        td.style.minHeight = `${height}px`;
                    }
                    tr.appendChild(td);
                }
                table.appendChild(tr);
            }
            wrap.appendChild(table);
            return wrap;
        };
        const replaceBrokenTableBlocksWithHtml = () => {
            const tableBlocks = content.blocks.filter((b) => b && b.type === 'table');
            if (tableBlocks.length === 0) return;
            const ceBlocks = Array.from(document.querySelectorAll('#editorjs .ce-block'));
            let tableIdx = 0;
            ceBlocks.forEach((blockEl) => {
                const text = (blockEl.textContent || '').toLowerCase();
                const isBrokenTable = text.includes('table') && text.includes('can not be displayed correctly');
                if (!isBrokenTable) return;
                const tableBlock = tableBlocks[tableIdx++];
                if (!tableBlock) return;
                const contentEl = blockEl.querySelector('.ce-block__content') || blockEl;
                contentEl.innerHTML = '';
                contentEl.appendChild(buildFallbackTable(tableBlock));
            });
        };
        const applyPublicTableStyles = () => {
            if (!content || !Array.isArray(content.blocks)) return;
            const tableBlocks = content.blocks.filter(b => b.type === 'table');
            const domTables = Array.from(document.querySelectorAll('#editorjs .tc-table, #editorjs table'));
            const applySizes = (rows, colWidths, rowHeights) => {
                Object.keys(colWidths).forEach((colKey) => {
                    const cIdx = Number(colKey);
                    const width = Number(colWidths[colKey]);
                    if (!Number.isFinite(cIdx) || !Number.isFinite(width)) return;
                    rows.forEach((cells) => {
                        const cell = cells[cIdx];
                        if (!cell) return;
                        cell.style.width = `${width}px`;
                        cell.style.minWidth = `${width}px`;
                        cell.style.maxWidth = '';
                    });
                });
                Object.keys(rowHeights).forEach((rowKey) => {
                    const rIdx = Number(rowKey);
                    const height = Number(rowHeights[rowKey]);
                    if (!Number.isFinite(rIdx) || !Number.isFinite(height)) return;
                    const targetRow = rows[rIdx] || [];
                    targetRow.forEach((cell) => {
                        cell.style.height = `${height}px`;
                        cell.style.minHeight = `${height}px`;
                    });
                });
            };
            const applyPaddings = (rows, paddings) => {
                Object.keys(paddings).forEach((key) => {
                    const parts = key.split('-');
                    const rIdx = Number(parts[0]);
                    const cIdx = Number(parts[1]);
                    if (!Number.isFinite(rIdx) || !Number.isFinite(cIdx)) return;
                    const cell = rows[rIdx] && rows[rIdx][cIdx];
                    if (!cell) return;
                    cell.style.padding = String(paddings[key]);
                });
            };
            const applyInputStates = (rows, inputStates) => {
                Object.keys(inputStates).forEach((key) => {
                    const parts = key.split('-');
                    const rIdx = Number(parts[0]);
                    const cIdx = Number(parts[1]);
                    if (!Number.isFinite(rIdx) || !Number.isFinite(cIdx)) return;
                    const cell = rows[rIdx] && rows[rIdx][cIdx];
                    if (!cell) return;
                    const states = Array.isArray(inputStates[key]) ? inputStates[key] : [];
                    const inputs = Array.from(cell.querySelectorAll('.ld-table-checkbox, .ld-table-radio'));
                    inputs.forEach((input, i) => {
                        input.checked = !!states[i];
                    });
                });
            };
            tableBlocks.forEach((block, tableIdx) => {
                const tableEl = domTables[tableIdx];
                if (!tableEl) return;
                const styles = (block.data && block.data.ld_styles) ? block.data.ld_styles : {};
                const colorMap = Object.keys(styles).length > 0
                    ? (styles.cellColors || {})
                    : ((block.data && block.data.cellColors) ? block.data.cellColors : {});
                const colWidths = Object.keys(styles).length > 0
                    ? (styles.columnWidths || {})
                    : ((block.data && block.data.columnWidths) ? block.data.columnWidths : {});
                const rowHeights = Object.keys(styles).length > 0
                    ? (styles.rowHeights || {})
                    : ((block.data && block.data.rowHeights) ? block.data.rowHeights : {});
                const paddings = Object.keys(styles).length > 0
                    ? (styles.cellPaddings || {})
                    : ((block.data && block.data.cellPaddings) ? block.data.cellPaddings : {});
                const inputStates = (block.data && block.data.cellInputStates) ? block.data.cellInputStates : {};
                const cellTextAlign = styles.cellTextAlign || {};
                const cellVerticalAlign = styles.cellVerticalAlign || {};
                const cellBorderColor = styles.cellBorderColor || {};
                const cellBorderWidth = styles.cellBorderWidth || {};
                const tableOptions = styles.tableOptions || {};
                const cellMerges = styles.cellMerges || {};

                if (tableOptions.tableLayout) tableEl.style.tableLayout = String(tableOptions.tableLayout);
                if (tableOptions.width) tableEl.style.width = String(tableOptions.width);

                const rows = getRows(tableEl);
                applySizes(rows, colWidths, rowHeights);
                applyPaddings(rows, paddings);
                applyInputStates(rows, inputStates);
                rows.forEach((cells, rIdx) => {
                    cells.forEach((cell, cIdx) => {
                        const key = `${rIdx}-${cIdx}`;
                        if (colorMap[key]) {
                            cell.style.backgroundColor = colorMap[key];
                        }
                        if (cellTextAlign[key]) {
                            cell.style.textAlign = String(cellTextAlign[key]);
                        }
                        if (cellVerticalAlign[key]) {
                            cell.style.verticalAlign = String(cellVerticalAlign[key]);
                        }
                        if (cellBorderColor[key]) {
                            cell.style.borderStyle = 'solid';
                            cell.style.borderColor = String(cellBorderColor[key]);
                        }
                        if (cellBorderWidth[key]) {
                            cell.style.borderStyle = 'solid';
                            cell.style.borderWidth = String(cellBorderWidth[key]);
                        }
                    });
                });
                applyStoredTableMerges(tableEl, cellMerges);
            });
        };
        new EditorJS({ 
            holder: 'editorjs', 
            data: content, 
            readOnly: true, 
            tools: { 
                paragraph: { class: ParagraphToolClass, inlineToolbar: true },
                header: {
                    class: HeaderToolClass,
                    inlineToolbar: true,
                    config: {
                        levels: [1, 2, 3, 4],
                        defaultLevel: 2
                    }
                }, 
                list: {class: EditorjsList}, 
                checklist: Checklist, 
                table: Table,
                code: CodeTool,
                quote: Quote,
                warning: Warning,
                delimiter: Delimiter,
                inlineCode: InlineCode,
                image: SimpleImage,
                embed: Embed,
                Color: window.ColorPlugin 
            },
            onReady: () => {
                applyPublicTableStyles();
                setTimeout(() => {
                    replaceBrokenTableBlocksWithHtml();
                }, 0);
            }
        });
    </script>
</body>
</html>
