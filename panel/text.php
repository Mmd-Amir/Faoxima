<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if(!isset($_SESSION["user"]) || !$result){
    header('Location: login.php');
    return;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'panel/text.php is now read-only. Edit texts from panel/textbot.php.';
    exit;
}

function faoxima_text_php_live_overlay(): array {
    $jsonFile = __DIR__ . '/../text.json';
    $decoded = is_file($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : null;
    $base = is_array($decoded['fa'] ?? null) ? $decoded['fa'] : [];

    global $pdo;
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->query('SELECT id_text, text FROM textbot');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $id = (string) ($row['id_text'] ?? '');
                    if (strpos($id, 'jsontext.') !== 0) continue;
                    $path = substr($id, strlen('jsontext.'));
                    if ($path === '') continue;
                    $segments = explode('.', $path);
                    $cursor = &$base;
                    foreach ($segments as $i => $seg) {
                        if ($seg === '') continue 2;
                        if ($i === count($segments) - 1) {
                            $cursor[$seg] = (string) ($row['text'] ?? '');
                        } else {
                            if (!isset($cursor[$seg]) || !is_array($cursor[$seg])) $cursor[$seg] = [];
                            $cursor = &$cursor[$seg];
                        }
                    }
                    unset($cursor);
                }
            }
        } catch (\Throwable $e) {
        }
    }
    return $base;
}

$faoximaLiveText = faoxima_text_php_live_overlay();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-color="blue">
<head>
    <script>
    (function(){try{var t=localStorage.getItem('faoxima_theme');
    if(t!=='light'&&t!=='dark')t='dark';
    document.documentElement.setAttribute('data-theme',t);
    var c=localStorage.getItem('faoxima_color');
    if(c)document.documentElement.setAttribute('data-color',c);}catch(e){}})();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>ویرایش متن — پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="js/theme.js?v=flat50" defer>

</script>
    <style>
      .text-editor-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
      }
      .text-editor-row {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 14px;
        align-items: center;
        padding: 12px 14px;
        background: var(--surface-2);
        border: 1px solid var(--border-soft);
        border-radius: 10px;
        transition: border-color .15s ease;
      }
      .text-editor-row:hover { border-color: var(--border-mid); }
      .text-editor-row label {
        font-family: 'Vazirmatn', system-ui, monospace;
        font-size: 12px;
        color: var(--text-muted);
        word-break: break-all;
        padding: 0;
        margin: 0;
      }
      .text-editor-row label::before {
        content: "$ ";
        color: var(--accent);
        font-weight: 700;
      }
      .text-editor-row input[type="text"] {
        width: 100%;
        padding: 9px 12px;
        background: var(--surface-1);
        border: 1px solid var(--border-mid);
        border-radius: 8px;
        color: var(--text-main);
        font-family: 'Vazirmatn', system-ui, sans-serif;
        font-size: 13px;
        transition: border-color .15s ease;
        direction: rtl;
      }
      .text-editor-row input[type="text"]:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow);
      }
      .text-editor-actions {
        position: sticky;
        bottom: 0;
        margin-top: 18px;
        padding-top: 14px;
        background: linear-gradient(to top, var(--bg-body) 60%, transparent);
        display: flex;
        gap: 10px;
        justify-content: flex-end;
      }
      @media (max-width: 720px) {
        .text-editor-row { grid-template-columns: 1fr; gap: 6px; }
      }
      .text-loading {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted);
        font-size: 14px;
      }
      .text-loading i { color: var(--accent); margin-left: 8px; }
      .text-search-bar {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 16px;
      }
      .text-search-bar input[type="search"] {
        flex: 1 1 240px;
        min-width: 200px;
        padding: 10px 14px;
        background: var(--surface-2);
        border: 1px solid var(--border-mid);
        border-radius: 10px;
        color: var(--text-main);
        font-family: 'Vazirmatn', system-ui, sans-serif;
        font-size: 13.5px;
      }
      .text-search-bar input[type="search"]:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow);
      }
      .text-group { margin-bottom: 18px; }
      .text-group__head {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        padding: 10px 4px;
        font-family: 'Vazirmatn', system-ui, monospace;
        font-size: 13px;
        font-weight: 700;
        color: var(--text-main);
        border-bottom: 1px solid var(--border-soft);
        margin-bottom: 10px;
        user-select: none;
      }
      .text-group__head .chip { margin-inline-start: auto; }
      .text-group__caret {
        transition: transform .15s ease;
        color: var(--text-muted);
      }
      .text-group.collapsed .text-group__caret { transform: rotate(-90deg); }
      .text-group.collapsed .text-group__body { display: none; }
      .text-editor-row.hidden { display: none; }
    </style>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>
    <section id="main-content">
        <section class="wrapper">
            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <span class="symbol">~</span>
                        نمایش متن ربات (فقط خواندنی)
                    </div>
                    <div class="page-head__sub">نمایش زنده متن‌های text.json؛ برای ویرایش از «متن‌های ربات» استفاده کنید</div>
                </div>
            </div>

            <div class="alert" style="background:var(--color-warning-soft); border:1px solid var(--color-warning); color:var(--color-warning); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                این صفحه دیگر قابل ویرایش نیست. برای تغییر این متن‌ها به صفحه «متن‌های ربات» مراجعه کنید.
            </div>

            <div class="card">
                <div class="card__head">
                    <div class="card__title"><span class="symbol">$</span> فایل text.json (زنده)</div>
                    <span class="chip" id="keyCountChip">Read-only</span>
                </div>
                <div class="text-search-bar">
                    <input type="search" id="textSearchBox" placeholder="جستجو در کلید یا متن…">
                </div>
                <form id="jsonForm" class="text-editor-grid"></form>
            </div>
        </section>
    </section>
</section>

<script>
    function collectLeaves(data, parentKey, groupKey, out) {
        Object.keys(data).forEach(key => {
            const fullKey = parentKey ? `${parentKey}.${key}` : key;
            const currentGroup = groupKey || key;
            if (typeof data[key] === 'object' && data[key] !== null) {
                collectLeaves(data[key], fullKey, currentGroup, out);
            } else {
                if (!out[currentGroup]) out[currentGroup] = [];
                out[currentGroup].push({ key: fullKey, value: data[key] });
            }
        });
    }

    function createForm(data) {
        const form = document.getElementById('jsonForm');
        const groups = {};
        collectLeaves(data, '', '', groups);

        Object.keys(groups).forEach(groupName => {
            const groupWrap = document.createElement('div');
            groupWrap.className = 'text-group';
            groupWrap.dataset.group = groupName;

            const head = document.createElement('div');
            head.className = 'text-group__head';
            head.innerHTML = '<i class="fa-solid fa-chevron-down text-group__caret"></i> <span>' + groupName + '</span>';
            const countChip = document.createElement('span');
            countChip.className = 'chip';
            countChip.innerText = groups[groupName].length;
            head.appendChild(countChip);
            head.addEventListener('click', () => groupWrap.classList.toggle('collapsed'));

            const body = document.createElement('div');
            body.className = 'text-group__body';

            groups[groupName].forEach(item => {
                const row = document.createElement('div');
                row.className = 'text-editor-row';
                row.dataset.search = (item.key + ' ' + String(item.value)).toLowerCase();
                const label = document.createElement('label');
                label.innerText = item.key;
                const input = document.createElement('input');
                input.type = 'text';
                input.value = item.value;
                input.name = item.key;
                input.readOnly = true;
                row.appendChild(label);
                row.appendChild(input);
                body.appendChild(row);
            });

            groupWrap.appendChild(head);
            groupWrap.appendChild(body);
            form.appendChild(groupWrap);
        });

        const totalChip = document.getElementById('keyCountChip');
        if (totalChip) {
            const total = Object.values(groups).reduce((n, arr) => n + arr.length, 0);
            totalChip.innerText = total + ' کلید';
        }
    }


    const faoximaLiveTextData = <?php echo json_encode($faoximaLiveText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;

    createForm(faoximaLiveTextData);

    const search = document.getElementById('textSearchBox');
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        document.querySelectorAll('.text-group').forEach(group => {
            let anyVisible = false;
            group.querySelectorAll('.text-editor-row').forEach(row => {
                const match = !q || row.dataset.search.indexOf(q) !== -1;
                row.classList.toggle('hidden', !match);
                if (match) anyVisible = true;
            });
            group.style.display = anyVisible ? '' : 'none';
            if (q && anyVisible) group.classList.remove('collapsed');
        });
    });
</script>

</body>
</html>


