<?php


if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

// Panel version (read from the project root `version` file). Displayed in the
// sidebar footer on every page. Always shown with a leading "v".
$__panelVersionRaw = trim((string)@file_get_contents(__DIR__ . '/../version'));
if ($__panelVersionRaw === '') $__panelVersionRaw = '1.0.5';
$__panelVersion = (stripos($__panelVersionRaw, 'v') === 0) ? $__panelVersionRaw : ('v' . $__panelVersionRaw);

if (isset($_SESSION["user"])) {
    if (isset($pdo) && $pdo instanceof PDO) {
        $__stmt_admin_ip = $pdo->prepare("SELECT iplogin FROM admin WHERE username = :username LIMIT 1");
        $__stmt_admin_ip->bindValue(':username', $_SESSION["user"], PDO::PARAM_STR);
        $__stmt_admin_ip->execute();
        $__raw_admin_ip = $__stmt_admin_ip->fetchColumn();

        $__admin_ip_list = [];
        $__admin_ip_unlimited = false;
        if ($__raw_admin_ip !== false && $__raw_admin_ip !== null && $__raw_admin_ip !== '') {
            $__admin_ip_decoded = json_decode((string)$__raw_admin_ip, true);
            if (is_array($__admin_ip_decoded)) {
                if (in_array('*', $__admin_ip_decoded, true) || in_array('all', $__admin_ip_decoded, true) || in_array('unlimited', $__admin_ip_decoded, true)) {
                    $__admin_ip_unlimited = true;
                } else {
                    $__admin_ip_list = $__admin_ip_decoded;
                }
            } elseif ($__raw_admin_ip === '*' || $__raw_admin_ip === 'all' || $__raw_admin_ip === 'unlimited') {
                $__admin_ip_unlimited = true;
            } elseif (filter_var($__raw_admin_ip, FILTER_VALIDATE_IP)) {
                $__admin_ip_list = [$__raw_admin_ip];
            }
        } else {
            $__admin_ip_unlimited = true;
        }

        $__current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $__allowed = $__admin_ip_unlimited || (!empty($__admin_ip_list) && in_array($__current_ip, $__admin_ip_list, true));
        if (!$__allowed) {
            session_unset();
            session_destroy();
            header('Location: login.php', true, 302);
            exit;
        }
        unset($__stmt_admin_ip, $__raw_admin_ip, $__admin_ip_decoded, $__admin_ip_list, $__admin_ip_unlimited, $__current_ip, $__allowed);
    }
}


if (!function_exists('icon')) {
    $__iconsLib = __DIR__ . '/lib/icons.php';
    if (is_file($__iconsLib) && is_readable($__iconsLib)) {
        @include_once $__iconsLib;
    }
}
if (!function_exists('icon')) {
    function icon(string $name, string $class = 'svg-icon'): string {
        static $paths = [
            'bars'        => '<line x1="4" y1="6"  x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/>',
            'robot'       => '<rect x="3" y="11" width="18" height="10" rx="2" ry="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8"  y1="16" x2="8.01" y2="16"/><line x1="16" y1="16" x2="16.01" y2="16"/>',
            'chevron-down'=> '<polyline points="6 9 12 15 18 9"/>',
            'moon'        => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
            'arrow-right-from-bracket' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
            'home'        => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'dollar-sign' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
            'package'     => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
            'grid'        => '<rect x="2" y="2" width="9" height="11" rx="2" fill="currentColor" stroke="none"/><rect x="13" y="2" width="9" height="7" rx="2" fill="currentColor" stroke="none"/><rect x="2" y="15" width="9" height="7" rx="2" fill="currentColor" stroke="none"/><rect x="13" y="11" width="9" height="11" rx="2" fill="currentColor" stroke="none"/>',
            'wallet'      => '<rect x="2" y="6" width="20" height="14" rx="2"/><polyline points="22 12 18 12 18 16 22 16"/><path d="M2 10V6a2 2 0 0 1 2-2h14"/>',
            'ban'         => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
            'keyboard'    => '<rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><line x1="6"  y1="8" x2="6.01" y2="8"/><line x1="10" y1="8" x2="10.01" y2="8"/><line x1="14" y1="8" x2="14.01" y2="8"/><line x1="18" y1="8" x2="18.01" y2="8"/><line x1="7"  y1="16" x2="17" y2="16"/>',
        ];
        $p = $paths[$name] ?? '<circle cx="12" cy="12" r="3"/>';
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    }
}

$__user    = isset($_SESSION["user"]) ? htmlspecialchars($_SESSION["user"], ENT_QUOTES, 'UTF-8') : 'admin';
$__current = strtolower(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$__avatar  = function_exists('mb_substr') ? mb_substr($__user, 0, 1, 'UTF-8') : substr($__user, 0, 1);


$__schemaLib = __DIR__ . '/lib/schema.php';
if (is_file($__schemaLib) && is_readable($__schemaLib)) {
    @include_once $__schemaLib;
    if (function_exists('faoxima_schema_ready') && isset($pdo) && $pdo instanceof PDO) {
        try { faoxima_schema_ready($pdo); } catch (\Throwable $e) { error_log('[header] schema_ready failed: ' . $e->getMessage()); }
    }
}

$__hdr_users_total = null;
$__hdr_new_orders  = 0;
$__hdr_notif_latest = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $__hdr_users_total = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
        $__hdr_since = time() - 86400;
        $__hdr_q = $pdo->prepare("SELECT COUNT(*), COALESCE(MAX(time_sell + 0),0) FROM invoice WHERE time_sell >= :since AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') AND name_product != 'سرویس تست'");
        $__hdr_q->bindValue(':since', (int)$__hdr_since, PDO::PARAM_INT);
        $__hdr_q->execute();
        $__hdr_row = $__hdr_q->fetch(PDO::FETCH_NUM);
        $__hdr_new_orders   = (int)($__hdr_row[0] ?? 0);
        $__hdr_notif_latest = (int)($__hdr_row[1] ?? 0);
    } catch (\Throwable $e) {
        error_log('[header] counts failed: ' . $e->getMessage());
    }
}
$__hdr_users_label = $__hdr_users_total !== null ? number_format($__hdr_users_total) : '';
$__hdr_open_tickets = 0;
$__hdr_ticket_latest = 0;
$__hdr_ticket_seen = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $__hdr_open_tickets = (int)$pdo->query("SELECT COUNT(DISTINCT Tracking) FROM support_message WHERE status IN ('Unseen','Customerresponse')")->fetchColumn();
        $__hdr_ticket_latest = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM support_message WHERE status IN ('Unseen','Customerresponse')")->fetchColumn();
        $__seenStmt = $pdo->prepare("SELECT last_ticket_seen FROM admin WHERE username = :u LIMIT 1");
        $__seenStmt->execute([':u' => $_SESSION['user'] ?? '']);
        $__hdr_ticket_seen = (int)($__seenStmt->fetchColumn() ?: 0);
    } catch (\Throwable $e) {}
}
$__hdr_tickets_unseen = ($__hdr_ticket_latest > $__hdr_ticket_seen) ? 1 : 0;
$__hdr_notif_total = $__hdr_new_orders + $__hdr_open_tickets;
$__hdr_pending_receipts = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $__hdr_pending_receipts = (int)$pdo->query("SELECT COUNT(*) FROM Payment_report WHERE Payment_Method = 'cart to cart' AND payment_Status = 'waiting'")->fetchColumn();
    } catch (\Throwable $e) {}
}
?>

<script>
(function () {
    try {


        var color = localStorage.getItem('faoxima_color') || 'purple';
        var html = document.documentElement;
        var s = html.style;
        var PRESET = { red:'#ef4444', blue:'#3b82f6', purple:'#a855f7', yellow:'#facc15', orange:'#f97316', green:'#22c55e' };
        function fg(r,g,b){ function lin(v){ v/=255; return v<=0.03928 ? v/12.92 : Math.pow((v+0.055)/1.055,2.4); } var L=0.2126*lin(r)+0.7152*lin(g)+0.0722*lin(b); return L>0.45?'#14121d':'#ffffff'; }
        function lin(v){ v/=255; return v<=0.03928 ? v/12.92 : Math.pow((v+0.055)/1.055,2.4); }
        function relLum(r,g,b){ return 0.2126*lin(r)+0.7152*lin(g)+0.0722*lin(b); }
        function ratioOn(r,g,b,bgL){ var L=relLum(r,g,b); var hi=L>bgL?L:bgL, lo=L>bgL?bgL:L; return (hi+0.05)/(lo+0.05); }
        function surfaceLum(light){ var v=''; try { v=getComputedStyle(html).getPropertyValue('--surface-1')||''; } catch(e){ v=''; } var sm=/^#?([0-9a-f]{6})$/i.exec(String(v).trim()); if(sm){ var sn=parseInt(sm[1],16); return relLum((sn>>16)&255,(sn>>8)&255,sn&255); } return light?relLum(255,255,255):relLum(21,22,26); }
        function mixInk(r,g,b,k,light){ return light ? [Math.round(r*(1-k)),Math.round(g*(1-k)),Math.round(b*(1-k))] : [Math.round(r+(255-r)*k),Math.round(g+(255-g)*k),Math.round(b+(255-b)*k)]; }
        function hexOf(c){ function h(v){ var x=v.toString(16); return x.length<2?'0'+x:x; } return '#'+h(c[0])+h(c[1])+h(c[2]); }
        function inkFor(r,g,b,light){ var bgL=surfaceLum(light); if(ratioOn(r,g,b,bgL)>=4.5) return hexOf([r,g,b]); var lo=0,hi=1,i,mid,c; for(i=0;i<24;i++){ mid=(lo+hi)/2; c=mixInk(r,g,b,mid,light); if(ratioOn(c[0],c[1],c[2],bgL)>=4.5) hi=mid; else lo=mid; } c=mixInk(r,g,b,hi,light); var gd=0; while(ratioOn(c[0],c[1],c[2],bgL)<4.5 && gd++<255){ hi=Math.min(1,hi+0.004); c=mixInk(r,g,b,hi,light); } return hexOf(c); }

        var t = localStorage.getItem('faoxima_theme');
        var light = (t === 'light');
        html.setAttribute('data-theme', light ? 'light' : 'dark');

        var hex;
        if (PRESET[color]) { hex = PRESET[color]; html.setAttribute('data-color', color); }
        else {
            var m=/^#?([0-9a-f]{6})$/i.exec(color);
            if (m) { hex='#'+m[1].toLowerCase(); var n=parseInt(m[1],16),r=(n>>16)&255,g=(n>>8)&255,b=n&255;
                s.setProperty('--accent',hex); s.setProperty('--accent-soft','rgba('+r+','+g+','+b+',0.15)');
                s.setProperty('--accent-mid','rgba('+r+','+g+','+b+',0.35)'); s.setProperty('--accent-glow','rgba('+r+','+g+','+b+',0.5)');
                s.setProperty('--accent-ink', inkFor(r,g,b,light));
                s.setProperty('--accent-ring', 'rgba('+r+','+g+','+b+','+(light?'0.40':'0.45')+')');
                html.setAttribute('data-color','custom'); }
            else { hex=PRESET.blue; html.setAttribute('data-color','blue'); }
        }
        var pn=parseInt(hex.slice(1),16); s.setProperty('--accent-fg', fg((pn>>16)&255,(pn>>8)&255,pn&255));
    } catch (e) {  }
})();
</script>

<style>
.profile-trigger.brand-pill {
    direction: ltr;
    gap: 8px;
    padding: 0 14px 0 4px;
    background: var(--surface-2);
    border: 1px solid var(--border-soft);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-1);
    height: 38px;
}
[data-theme="dark"] .profile-trigger.brand-pill,
:root:not([data-theme="light"]) .profile-trigger.brand-pill {
    box-shadow: none;
}
.profile-trigger.brand-pill:hover,
.profile-wrap.open .profile-trigger.brand-pill {
    background: var(--surface-2);
    border-color: var(--border-mid);
}
.profile-trigger.brand-pill .logo {
    width: 30px; height: 30px;
    border-radius: var(--radius-md);
    display: grid; place-items: center;
    overflow: hidden;
    flex-shrink: 0;
    background: #fff;
    margin: 0;
    box-shadow: var(--shadow-1);
}
[data-theme="dark"] .profile-trigger.brand-pill .logo,
:root:not([data-theme="light"]) .profile-trigger.brand-pill .logo {
    box-shadow: none;
}
.profile-trigger.brand-pill .logo img {
    width: 100%; height: 100%;
    object-fit: contain; object-position: center;
    transform: scale(1.2);
    display: block;
}
.profile-trigger.brand-pill .profile-info {
    direction: rtl;
}
.version-pill {
    direction: ltr;
    display: flex; align-items: center; gap: 8px;
    width: 100%;
    padding: 0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-1);
    height: 28px;
}
[data-theme="dark"] .version-pill,
:root:not([data-theme="light"]) .version-pill { box-shadow: none; }
.version-pill .logo {
    width: 20px; height: 20px;
    border-radius: var(--radius-sm);
    display: grid; place-items: center;
    overflow: hidden;
    flex-shrink: 0;
    background: #fff;
    margin: 0;
    box-shadow: var(--shadow-1);
}
[data-theme="dark"] .version-pill .logo,
:root:not([data-theme="light"]) .version-pill .logo { box-shadow: none; }
.version-pill .logo img {
    width: 100%; height: 100%;
    object-fit: contain; object-position: center;
    transform: scale(1.2);
    display: block;
}
.version-pill__label {
    font-size: 11px; font-weight: 600; letter-spacing: 0.02em;
    color: var(--text-muted);
}
.version-pill__num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px; font-weight: 600;
    color: var(--text-dim);
    margin-inline-start: auto;
}
.profile-trigger.brand-pill .profile-info b {
    font-weight: 600; font-size: 13px; letter-spacing: -0.01em;
    color: var(--text-main);
}
.submenu-wrap { position: relative; }
.submenu-trigger {
    display: flex; align-items: center; gap: 10px;
    width: 100%;
    padding: 10px 12px;
    border-radius: 10px;
    color: var(--text-main);
    font-size: 13px;
    text-decoration: none;
    border: 0; background: transparent;
    cursor: pointer;
    transition: 0.15s;
    text-align: right;
    font-family: inherit;
}
.submenu-trigger:hover { background: var(--accent-soft); color: var(--accent); }
.submenu-trigger__badge {
    margin-inline-start: auto;
    min-width: 18px; height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: var(--color-danger);
    color: #fff;
    font-size: 10.5px; font-weight: 700;
    display: inline-flex; align-items: center; justify-content: center;
    line-height: 1;
}
.submenu-trigger__chevron { transform: rotate(90deg); transition: transform 0.18s; margin-inline-start: 2px; }
.submenu-wrap.open .submenu-trigger__chevron { transform: rotate(-90deg); }
.submenu-panel {
    position: absolute;
    top: 0;
    left: calc(100% - 26px);
    min-width: 220px;
    background: var(--surface-2);
    border: 1px solid var(--border-soft);
    border-radius: 16px;
    box-shadow: var(--shadow-2);
    padding: 8px;
    opacity: 0; transform: translateX(8px) scale(0.97);
    pointer-events: none;
    transition: 0.18s ease;
    z-index: 1101;
}
.submenu-wrap.open .submenu-panel {
    opacity: 1; transform: translateX(0) scale(1); pointer-events: auto;
}
@media (max-width: 600px) {
    .submenu-panel { right: 0; left: 0; top: calc(100% + 6px); min-width: 0; transform: translateY(-8px) scale(0.97); }
    .submenu-wrap.open .submenu-panel { transform: translateY(0) scale(1); }
}
.hdr-actions { flex: 1 1 auto; display: flex; justify-content: flex-end; }
.hdr-actions .hdr-search { flex: 0 1 520px; max-width: 520px; margin-inline-end: auto; }
@media (max-width: 900px) {
    .hdr-actions .hdr-search { flex-basis: 360px; max-width: 360px; }
}
@media (max-width: 600px) {
    .app-header { justify-content: space-between; }
    .hdr-actions { order: 1; flex: 0 0 auto; margin-inline-start: 0; }
    .app-header__left { order: 2; }
    .hdr-actions .hdr-search { margin-inline-end: 0; }
    .profile-info { display: flex; }
    #hdrBellWrap .profile-menu { right: auto; left: 8px; }
}
.hdr-search__mobtoggle { display: none; border-radius: var(--radius-md); }
.hdr-search input {
    border-radius: var(--radius-md);
    padding: 0 14px;
    box-shadow: var(--shadow-1);
}
[data-theme="dark"] .hdr-search input,
:root:not([data-theme="light"]) .hdr-search input {
    box-shadow: none;
}
@media (max-width: 600px) {
    .app-header { padding: 0 12px; gap: 8px; }
    .app-header__left { gap: 8px; }
}
.hdr-search__quick a, .hdr-search__res {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 11px; border-radius: 10px;
    color: var(--text-main); font-size: 13px; text-decoration: none;
}
.hdr-search__quick a:hover, .hdr-search__res:hover { background: var(--accent-soft); color: var(--accent); }
.hdr-search__res .svg-icon { width: 16px; height: 16px; flex-shrink: 0; }
.hdr-search__res-body { display: flex; flex-direction: column; min-width: 0; }
.hdr-search__res-title { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hdr-search__res-sub { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hdr-search__group-label { padding: 8px 10px 4px; font-size: 11px; font-weight: 700; color: var(--text-dim); }
.hdr-search__empty { padding: 16px 10px; text-align: center; color: var(--text-muted); font-size: 12.5px; }
.hdr-search__dd { max-height: min(70vh, 420px); overflow-y: auto; }
.hdr-search.has-query .hdr-search__quick { display: none; }
.hdr-search:not(.has-query) .hdr-search__results { display: none; }
.hdr-search.is-open .hdr-search__dd { opacity: 1; transform: translateY(0); pointer-events: auto; }
@media (max-width: 600px) {
    .hdr-search__mobtoggle { display: inline-flex; }
    .hdr-search { display: none; }
    .hdr-search.is-mobile-open {
        display: block; position: fixed;
        top: 64px; left: auto; right: 8px;
        width: 260px; max-width: calc(100vw - 16px);
        z-index: 1102;
    }
    .hdr-search.is-mobile-open .hdr-search__dd { max-height: min(60vh, 320px); }
}
</style>

<header class="app-header">
    <div class="hdr-actions">
        <button class="btn-icon" id="sidebar-toggle-btn" type="button" aria-label="باز/بسته کردن منو">
            <?php echo icon('bars', 'svg-icon'); ?>
        </button>

        <button class="btn-icon hdr-search__mobtoggle" id="hdrSearchMob" type="button" aria-label="جستجو">
            <?php echo icon('search', 'svg-icon'); ?>
        </button>

        <div class="hdr-search" id="hdrSearch">
            <input type="search" id="hdrSearchInput" placeholder="جستجو..." aria-label="جستجو" autocomplete="off">
            <div class="hdr-search__dd">
                <div class="hdr-search__quick">
                    <a href="users.php"><?php echo icon('users', 'svg-icon'); ?><span>کاربران</span></a>
                    <a href="invoice.php"><?php echo icon('receipt', 'svg-icon'); ?><span>سفارشات</span></a>
                    <a href="product.php"><?php echo icon('package', 'svg-icon'); ?><span>محصولات</span></a>
                    <a href="reports.php"><?php echo icon('chart-bar', 'svg-icon'); ?><span>گزارش‌ها</span></a>
                </div>
                <div class="hdr-search__results" id="hdrSearchResults"></div>
            </div>
        </div>
    </div>

    <div class="app-header__left">
        <div class="hdr-dd-wrap" id="hdrNotifWrap">
            <button class="btn-icon hdr-bell" type="button" aria-label="اعلان‌ها" data-hdr-dd-toggle>
                <?php echo icon('bell', 'svg-icon'); ?>
                <?php if ($__hdr_notif_total > 0): ?>
                    <span class="hdr-bell__badge" hidden><?php echo $__hdr_notif_total > 99 ? '۹۹+' : htmlspecialchars((string)$__hdr_notif_total, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </button>
            <div class="hdr-dd">
                <div class="hdr-dd__head">اعلان‌ها</div>
                <?php if ($__hdr_open_tickets > 0): ?>
                    <a href="tickets.php" class="hdr-dd__item">
                        <?php echo icon('message', 'svg-icon'); ?>
                        <span><?php echo htmlspecialchars((string)$__hdr_open_tickets, ENT_QUOTES, 'UTF-8'); ?> تیکت بی‌پاسخ</span>
                    </a>
                <?php endif; ?>
                <?php if ($__hdr_new_orders > 0): ?>
                    <a href="invoice.php" class="hdr-dd__item">
                        <?php echo icon('receipt', 'svg-icon'); ?>
                        <span><?php echo htmlspecialchars((string)$__hdr_new_orders, ENT_QUOTES, 'UTF-8'); ?> سفارش جدید در ۲۴ ساعت اخیر</span>
                    </a>
                <?php endif; ?>
                <?php if ($__hdr_notif_total === 0): ?>
                    <div class="hdr-dd__empty">اعلان جدیدی نیست</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-wrap" id="hdrBellWrap" data-notif-latest="<?php echo (int)$__hdr_notif_latest; ?>" data-tickets-unseen="<?php echo (int)$__hdr_tickets_unseen; ?>">
            <button class="profile-trigger brand-pill" type="button" aria-label="حساب کاربری" style="position:relative;">
                <span class="logo" title="فاکسیما">
                    <img src="logo/faoxima.jpg" alt="faoxima" loading="lazy" width="36" height="36">
                </span>
                <span class="profile-info">
                    <b>faoxima</b>
                </span>
                <?php echo icon('chevron-down', 'svg-icon svg-xs'); ?>
            </button>

            <div class="profile-menu">
                <div class="profile-menu__head">
                    <b><?php echo $__user; ?></b>
                    <small>مدیر کل</small>
                </div>

                <a href="appearance.php" class="menu-item">
                    <?php echo icon('palette', 'svg-icon svg-sm'); ?>
                    <span>تنظیمات ظاهر</span>
                </a>

                <button type="button" id="theme-toggle">
                    <span id="theme-toggle-icon"></span>
                    <span id="theme-toggle-label">حالت روز</span>
                </button>

                <hr>

                <a href="login.php" class="menu-danger">
                    <?php echo icon('arrow-right-from-bracket', 'svg-icon svg-sm'); ?>
                    <span>خروج از حساب</span>
                </a>
            </div>
        </div>
    </div>
</header>

<aside class="app-sidebar">
    <div class="sidebar-scroll">
        <div class="sidebar-section-label">منوی اصلی</div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><span class="menu-symbol"><?php echo icon('grid', 'svg-icon'); ?></span><span>داشبورد</span></a></li>
        </ul>

        <div class="nav-group">
            <button type="button" class="nav-group__btn">
                <span class="menu-symbol"><svg class="svg-icon" viewBox="0 0 60 60" aria-hidden="true"><path fill="currentColor" stroke="none" d="M59.498,12.107c0-0.154-0.032-0.3-0.089-0.433c-0.004-0.097-0.02-0.195-0.047-0.29l-4.121-8.988C54.572,0.94,53.104,0,51.501,0h-8.169h-2.033h-9.802h-2h-9.802h-2.033H9.494C7.887,0,6.427,0.935,5.755,2.394l-4.063,8.824l-0.054,0.144C1.6,11.499,1.593,11.64,1.601,11.78c-0.066,0.154-0.103,0.324-0.103,0.502V19c0,1.401,0.364,2.718,1,3.864v7.351c-0.459,0.503-0.8,1.132-0.93,1.83c-0.11,0.597-0.075,1.229,0.103,1.829c0.097,0.328,0.02,0.726-0.208,1.063c-1.047,1.548-1.26,3.707-0.528,5.372c0.11,0.252,0.248,0.489,0.41,0.708c0.231,0.311,0.313,0.724,0.22,1.104c-0.052,0.205-0.087,0.416-0.106,0.626c-0.082,0.896,0.103,1.766,0.25,2.464c0.155,0.735,0.39,1.847,1.214,2.555c0.433,0.373,0.977,0.576,1.576,0.61V52H1.583c-0.663,0-1.167,0.595-1.058,1.249L1.651,60h5.666h2.027h27.153h18h4V22.864c0.636-1.147,1-2.463,1-3.864V12.107z M8.79,24.954c-0.08-0.01-0.159-0.024-0.238-0.036c-0.15-0.024-0.298-0.052-0.444-0.087c-0.093-0.022-0.186-0.045-0.278-0.072c-0.132-0.038-0.262-0.084-0.39-0.131c-0.09-0.033-0.181-0.061-0.268-0.098c-0.189-0.08-0.373-0.17-0.552-0.268c-0.091-0.05-0.178-0.107-0.266-0.162c-0.108-0.067-0.215-0.136-0.319-0.21c-0.079-0.056-0.156-0.114-0.232-0.174c-0.133-0.105-0.262-0.215-0.386-0.332c-0.037-0.035-0.077-0.067-0.113-0.103c-0.174-0.172-0.34-0.355-0.495-0.55c-0.003-0.003-0.005-0.007-0.008-0.01c-0.156-0.198-0.301-0.408-0.434-0.628c-0.035-0.058-0.073-0.115-0.106-0.175l-0.038-0.064C3.76,21.004,3.498,20.032,3.498,19v-6h11.802h0.198v6c0,3.309-2.691,6-6,6C9.258,25,9.023,24.982,8.79,24.954z M31.498,13h12v6c0,3.309-2.691,6-6,6s-6-2.691-6-6V13z M51.501,2c0.823,0,1.578,0.483,1.922,1.231L56.996,11H45.498h-0.165l-0.826-4.541L43.696,2H51.501z M19.332,2h10.165v9H17.696l0.818-4.5L19.332,2z M3.665,44.797c-0.126-0.597-0.27-1.273-0.215-1.868c0.01-0.107,0.027-0.217,0.055-0.325c0.237-0.955,0.029-1.995-0.556-2.78c-0.07-0.096-0.135-0.206-0.184-0.318c-0.458-1.044-0.313-2.462,0.354-3.448c0.567-0.841,0.737-1.844,0.468-2.75c-0.087-0.295-0.105-0.614-0.053-0.898c0.07-0.378,0.265-0.721,0.524-0.958l0.066-0.053c0.098-0.078,0.18-0.132,0.251-0.166l0.906-0.74c0.064-0.066,0.138-0.109,0.212-0.149c0.058-0.029,0.118-0.054,0.18-0.071c0.003-0.001,0.005-0.002,0.008-0.002c0.144-0.039,0.292-0.051,0.416-0.051c0.108,0,0.217,0.008,0.322,0.023c0.362,0.051,0.692,0.173,0.971,0.346l0.22,0.182c0.105,0.087,0.19,0.17,0.263,0.252c0.072,0.088,0.145,0.176,0.197,0.275c0.154,0.297,0.202,0.7,0.127,1.053c-0.192,0.898,0.038,1.854,0.617,2.557c0.118,0.144,0.208,0.335,0.246,0.525c0.025,0.124,0.037,0.255,0.037,0.394c-0.001,0.418-0.114,0.908-0.343,1.485c-0.193,0.489-0.414,0.905-0.655,1.237c-0.49,0.675-0.667,1.558-0.517,2.441c0.05,0.294,0.136,0.589,0.26,0.876c0.118,0.271,0.186,0.581,0.204,0.893c0.018,0.313-0.016,0.629-0.102,0.914c-0.048,0.16-0.109,0.311-0.178,0.455c-0.138,0.287-0.321,0.56-0.548,0.817c-0.01,0.011-0.02,0.02-0.03,0.031c-0.102,0.113-0.213,0.224-0.332,0.331c-0.395,0.354-0.808,0.607-1.112,0.765c-0.191,0.099-0.377,0.173-0.554,0.227l-0.037,0.011c-0.177,0.051-0.343,0.08-0.488,0.08c-0.25,0-0.372-0.08-0.442-0.141C3.915,45.981,3.764,45.269,3.665,44.797z M6.498,47.93c0.056-0.026,0.111-0.054,0.166-0.082c0.411-0.213,0.791-0.455,1.137-0.721L9.209,52H6.498V47.93z M7.65,58H3.344l-0.666-4h1.819h3.819L7.65,58z M49.498,47v-3c0-0.553,0.447-1,1-1s1,0.447,1,1v3c0,0.553-0.447,1-1,1S49.498,47.553,49.498,47z M56.498,58h-2V29h-18v29H9.678l0.667-4h24.153V29H8.608c-0.548-0.377-1.198-0.637-1.907-0.738c-0.247-0.035-0.494-0.04-0.738-0.03c-0.058,0.003-0.114,0.008-0.171,0.013c-0.218,0.018-0.427,0.052-0.628,0.104c-0.019,0.005-0.039,0.008-0.058,0.013c-0.218,0.06-0.427,0.134-0.61,0.233v-3.348c0.574,0.463,1.21,0.836,1.887,1.122c0.003,0.001,0.005,0.002,0.008,0.003c0.221,0.093,0.447,0.176,0.677,0.248c0.039,0.012,0.078,0.024,0.117,0.036c0.195,0.059,0.393,0.109,0.594,0.153c0.06,0.013,0.12,0.027,0.18,0.039c0.188,0.036,0.379,0.065,0.572,0.088c0.066,0.008,0.131,0.019,0.197,0.025C8.983,26.985,9.239,27,9.498,27c0.338,0,0.669-0.028,0.996-0.069c0.094-0.012,0.187-0.028,0.28-0.043c0.247-0.04,0.489-0.091,0.728-0.153c0.083-0.021,0.166-0.04,0.248-0.064c0.307-0.09,0.607-0.195,0.898-0.32c0.045-0.019,0.086-0.043,0.13-0.063c0.247-0.112,0.487-0.236,0.721-0.372c0.081-0.047,0.159-0.095,0.238-0.145c0.212-0.133,0.416-0.275,0.614-0.427c0.055-0.042,0.113-0.081,0.167-0.125c0.243-0.196,0.474-0.406,0.692-0.629c0.049-0.05,0.094-0.104,0.142-0.156c0.169-0.182,0.329-0.371,0.481-0.567c0.057-0.074,0.113-0.148,0.167-0.224c0.157-0.219,0.303-0.445,0.437-0.679c0.019-0.033,0.043-0.063,0.062-0.096c0.019,0.033,0.043,0.063,0.062,0.096c0.134,0.234,0.281,0.46,0.437,0.679c0.054,0.076,0.11,0.15,0.167,0.224c0.152,0.197,0.312,0.386,0.481,0.567c0.048,0.052,0.092,0.106,0.142,0.156c0.218,0.223,0.449,0.433,0.692,0.629c0.054,0.044,0.112,0.083,0.167,0.125c0.198,0.152,0.402,0.294,0.614,0.427c0.079,0.049,0.157,0.098,0.238,0.145c0.233,0.135,0.473,0.26,0.721,0.372c0.044,0.02,0.086,0.044,0.13,0.063c0.29,0.125,0.591,0.23,0.898,0.32c0.082,0.024,0.165,0.042,0.248,0.064c0.239,0.062,0.481,0.113,0.728,0.153c0.093,0.015,0.186,0.031,0.28,0.043C22.829,26.972,23.16,27,23.498,27s0.669-0.028,0.996-0.069c0.094-0.012,0.187-0.028,0.28-0.043c0.247-0.04,0.489-0.091,0.728-0.153c0.083-0.021,0.166-0.04,0.248-0.064c0.307-0.09,0.607-0.195,0.898-0.32c0.045-0.019,0.086-0.043,0.13-0.063c0.247-0.112,0.487-0.236,0.721-0.372c0.081-0.047,0.159-0.095,0.238-0.145c0.212-0.133,0.416-0.275,0.614-0.427c0.055-0.042,0.113-0.081,0.167-0.125c0.243-0.196,0.474-0.406,0.692-0.629c0.049-0.05,0.094-0.104,0.142-0.156c0.169-0.182,0.329-0.371,0.481-0.567c0.057-0.074,0.113-0.148,0.167-0.224c0.157-0.219,0.303-0.445,0.437-0.679c0.019-0.033,0.043-0.063,0.062-0.096c0.019,0.033,0.043,0.063,0.062,0.096c0.134,0.234,0.281,0.46,0.437,0.679c0.054,0.076,0.11,0.15,0.167,0.224c0.152,0.197,0.312,0.386,0.481,0.567c0.048,0.052,0.092,0.106,0.142,0.156c0.218,0.223,0.449,0.433,0.692,0.629c0.054,0.044,0.112,0.083,0.167,0.125c0.198,0.152,0.402,0.294,0.614,0.427c0.079,0.049,0.157,0.098,0.238,0.145c0.233,0.135,0.473,0.26,0.721,0.372c0.044,0.02,0.086,0.044,0.13,0.063c0.29,0.125,0.591,0.23,0.898,0.32c0.082,0.024,0.165,0.042,0.248,0.064c0.239,0.062,0.481,0.113,0.728,0.153c0.093,0.015,0.186,0.031,0.28,0.043C36.829,26.972,37.16,27,37.498,27s0.669-0.028,0.996-0.069c0.094-0.012,0.187-0.028,0.28-0.043c0.247-0.04,0.489-0.091,0.728-0.153c0.083-0.021,0.166-0.04,0.248-0.064c0.307-0.09,0.607-0.195,0.898-0.32c0.045-0.019,0.086-0.043,0.13-0.063c0.247-0.112,0.487-0.236,0.721-0.372c0.081-0.047,0.159-0.095,0.238-0.145c0.212-0.133,0.416-0.275,0.614-0.427c0.055-0.042,0.113-0.081,0.167-0.125c0.243-0.196,0.474-0.406,0.692-0.629c0.049-0.05,0.094-0.104,0.142-0.156c0.169-0.182,0.329-0.371,0.481-0.567c0.057-0.074,0.113-0.148,0.167-0.224c0.157-0.219,0.303-0.445,0.437-0.679c0.019-0.033,0.043-0.063,0.062-0.096c0.019,0.033,0.043,0.063,0.062,0.096c0.134,0.234,0.281,0.46,0.437,0.679c0.054,0.076,0.11,0.15,0.167,0.224c0.152,0.197,0.312,0.386,0.481,0.567c0.048,0.052,0.092,0.106,0.142,0.156c0.218,0.223,0.449,0.433,0.692,0.629c0.054,0.044,0.112,0.083,0.167,0.125c0.198,0.152,0.402,0.294,0.614,0.427c0.079,0.049,0.157,0.098,0.238,0.145c0.233,0.135,0.473,0.26,0.721,0.372c0.044,0.02,0.086,0.044,0.13,0.063c0.29,0.125,0.591,0.23,0.898,0.32c0.082,0.024,0.165,0.042,0.248,0.064c0.239,0.062,0.481,0.113,0.728,0.153c0.093,0.015,0.186,0.031,0.28,0.043C50.829,26.972,51.16,27,51.498,27c0.259,0,0.514-0.015,0.768-0.039c0.066-0.006,0.131-0.017,0.197-0.025c0.192-0.023,0.383-0.051,0.572-0.088c0.061-0.012,0.12-0.026,0.18-0.039c0.201-0.044,0.399-0.095,0.594-0.153c0.039-0.012,0.078-0.023,0.117-0.036c0.23-0.073,0.456-0.155,0.677-0.248c0.003-0.001,0.005-0.002,0.008-0.003c0.677-0.286,1.313-0.659,1.887-1.122V58z M28.205,34.707l-3,3C25.009,37.902,24.753,38,24.498,38s-0.512-0.098-0.707-0.293c-0.391-0.391-0.391-1.023,0-1.414l3-3c0.391-0.391,1.023-0.391,1.414,0S28.595,34.316,28.205,34.707z M28.788,36.29c0.37-0.37,1.05-0.37,1.42,0c0.18,0.189,0.29,0.45,0.29,0.71s-0.11,0.52-0.29,0.71c-0.19,0.18-0.45,0.29-0.71,0.29s-0.521-0.11-0.71-0.29c-0.181-0.19-0.29-0.45-0.29-0.71S28.607,36.479,28.788,36.29z M23.205,34.707l-4,4C19.009,38.902,18.753,39,18.498,39s-0.512-0.098-0.707-0.293c-0.391-0.391-0.391-1.023,0-1.414l4-4c0.391-0.391,1.023-0.391,1.414,0S23.595,34.316,23.205,34.707z M23.498,39c0,0.26-0.11,0.52-0.29,0.71c-0.19,0.18-0.45,0.29-0.71,0.29c-0.271,0-0.521-0.11-0.71-0.3c-0.181-0.181-0.29-0.44-0.29-0.7s0.109-0.521,0.29-0.71c0.37-0.37,1.05-0.37,1.42,0C23.387,38.479,23.498,38.74,23.498,39z M17.79,42.293l2-2c0.391-0.391,1.023-0.391,1.414,0s0.391,1.023,0,1.414l-2,2C19.009,43.902,18.753,44,18.498,44s-0.512-0.098-0.707-0.293C17.4,43.316,17.4,42.684,17.79,42.293z M17.79,47.293l9-9c0.391-0.391,1.023-0.391,1.414,0s0.391,1.023,0,1.414l-9,9C19.009,48.902,18.753,49,18.498,49s-0.512-0.098-0.707-0.293C17.4,48.316,17.4,47.684,17.79,47.293z"/></svg></span>
                <span class="nav-group__label">فروشگاه</span>
                <span class="nav-group__chevron"><?php echo icon('chevron-down', 'svg-icon'); ?></span>
            </button>
            <div class="nav-group__panel">
                <ul class="sidebar-menu">
                    <li><a href="invoice.php"><span class="menu-symbol"><svg class="svg-icon" viewBox="0 0 512 512" aria-hidden="true"><path fill="currentColor" stroke="none" d="M302.793,440.856c0-1.925,0.096-3.861,0.286-5.834c0.868-8.667,3.013-16.371,6.216-23.006c2.174-4.529,4.891-8.533,7.942-12.032H35.753c-6.292,0-12.518,1.373-17.829,4.405c-5.32,3.06-9.83,7.522-13.166,14.234c-2.212,4.49-3.881,10.049-4.577,16.989C0.057,436.853,0,438.073,0,439.274c0,4.577,0.848,8.876,2.441,13.004c1.601,4.119,3.947,8.066,6.96,11.718c5.996,7.294,14.711,13.338,24.321,16.904c6.398,2.393,13.157,3.68,19.678,3.709h-0.01l269.158,0.152c-5.597-5.244-10.316-11.403-13.748-18.334C304.986,458.714,302.793,450.029,302.793,440.856z"/><path fill="currentColor" stroke="none" d="M511.276,27.22H152.364c0.162,22.815,0.668,46.764,0.668,71.82c0,43.581-1.583,90.345-9.573,139.522c-7.484,46.098-20.622,94.32-43.256,143.907h256.477v0.134c8.914,0.114,17.685,2.364,25.37,6.664c7.837,4.395,14.597,11.022,18.726,19.612c2.573,5.387,3.785,11.345,3.785,17.333c0,4.93-0.83,9.907-2.651,14.606c-1.83,4.701-4.691,9.162-8.819,12.69c-6.026,5.158-13.339,7.628-20.308,7.598c-6.092,0-11.947-1.783-16.913-4.976c-4.977-3.194-9.115-7.838-11.575-13.586l16.093-6.903c1.012,2.355,2.727,4.339,4.939,5.749c2.193,1.421,4.824,2.212,7.456,2.212c3.022-0.02,6.016-0.954,8.952-3.423c1.563-1.335,2.908-3.251,3.852-5.702c0.954-2.431,1.478-5.339,1.478-8.266c0-3.565-0.792-7.102-2.069-9.753c-2.422-5.025-6.417-9.057-11.518-11.928c-5.082-2.851-11.221-4.452-17.419-4.433c-6.14-0.01-12.29,1.526-17.639,4.681c-5.358,3.175-9.991,7.894-13.357,14.835c-2.231,4.634-3.871,10.287-4.557,17.161c-0.144,1.382-0.2,2.746-0.2,4.081c0,6.388,1.496,12.338,4.195,17.81c2.697,5.473,6.616,10.449,11.46,14.654c5.997,5.225,13.366,9.2,21.308,11.451l8.771,0.009c8.524-0.028,14.559-1.553,20.222-4.08c5.635-2.527,10.983-6.293,17.066-11.155c24.121-19.23,42.951-43.123,57.729-70.409c14.777-27.286,25.428-57.958,33.006-90.326C509.245,244.073,512.02,172.662,512,108.717C512,79.59,511.447,52.036,511.276,27.22z M205.774,83.147h136.024c0.314,31.072,0.162,63.926-2.918,98.248H202.847C205.928,147.073,206.09,114.218,205.774,83.147z M417.794,329.717H174.665v-20.431h243.129V329.717z M436.805,252.882H193.676V232.46h243.129V252.882z"/></svg></span><span>سفارشات</span></a></li>
                    <li><a href="product.php"><span class="menu-symbol"><svg class="svg-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M11.0287 2.53961C11.6327 2.20402 12.3672 2.20402 12.9713 2.5396L20.4856 6.71425C20.8031 6.89062 21 7.22524 21 7.5884V15.8232C21 16.5495 20.6062 17.2188 19.9713 17.5715L12.9713 21.4604C12.3672 21.796 11.6327 21.796 11.0287 21.4604L4.02871 17.5715C3.39378 17.2188 3 16.5495 3 15.8232V7.5884C3 7.22524 3.19689 6.89062 3.51436 6.71425L11.0287 2.53961Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.5 4.5L16.5 9.5V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 12.3281L9 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 7L12 12M12 12L21 7M12 12V21.5" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span><span>محصولات</span></a></li>
                    <li><a href="category.php"><span class="menu-symbol"><svg class="svg-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5C3 3.89543 3.89543 3 5 3H9C10.1046 3 11 3.89543 11 5V9C11 10.1046 10.1046 11 9 11H5C3.89543 11 3 10.1046 3 9V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 5C13 3.89543 13.8954 3 15 3H19C20.1046 3 21 3.89543 21 5V9C21 10.1046 20.1046 11 19 11H15C13.8954 11 13 10.1046 13 9V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 15C3 13.8954 3.89543 13 5 13H9C10.1046 13 11 13.8954 11 15V19C11 20.1046 10.1046 21 9 21H5C3.89543 21 3 20.1046 3 19V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 15C13 13.8954 13.8954 13 15 13H19C20.1046 13 21 13.8954 21 15V19C21 20.1046 20.1046 21 19 21H15C13.8954 21 13 20.1046 13 19V15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>دسته‌بندی‌ها</span></a></li>
                    <li><a href="service.php"><span class="menu-symbol"><svg class="svg-icon" viewBox="0 0 138.518 138.518" aria-hidden="true"><g fill="currentColor" stroke="none"><path d="M20.547,112.328c2.083,0,3.771,1.691,3.771,3.776c0,2.079-1.688,3.765-3.771,3.765c-2.084,0-3.773-1.686-3.773-3.765C16.774,114.02,18.463,112.328,20.547,112.328z"/><path d="M20.547,78.709c2.083,0,3.771,1.69,3.771,3.775c0,2.08-1.688,3.766-3.771,3.766c-2.084,0-3.773-1.686-3.773-3.766C16.774,80.399,18.463,78.709,20.547,78.709z"/><circle cx="20.547" cy="48.862" r="3.771"/><path d="M14.478,126.589c-2.881,0-5.244-2.354-5.244-5.248v-10.484c0-2.895,2.363-5.247,5.244-5.247h48.603c0-0.011,0-0.011,0-0.022c0-4.422,0.792-8.656,2.169-12.618H14.478c-2.881,0-5.244-2.362-5.244-5.253V77.236c0-2.893,2.363-5.246,5.244-5.246h68.345c5.56-3.126,11.962-4.923,18.779-4.923c5.976,0,11.62,1.408,16.668,3.85l-1.686-9.757c1.708-1.866,2.782-4.331,2.782-7.058V43.617c0-0.117-0.057-0.208-0.057-0.32L108.848,5.211C108.53,2.35,105.654,0,102.446,0h-81.53c-3.207,0-6.09,2.35-6.404,5.211L4.049,43.297c0,0.112-0.055,0.203-0.055,0.32v10.484c0,2.722,1.071,5.192,2.78,7.058L4.049,76.914c0,0.115-0.055,0.208-0.055,0.322v10.485c0,2.719,1.071,5.192,2.78,7.059l-2.725,15.754c0,0.114-0.055,0.207-0.055,0.322v10.484c0,5.784,4.706,10.49,10.484,10.49h58.999c-1.521-1.631-2.91-3.371-4.13-5.242H14.478L14.478,126.589z M9.239,43.617c0-2.893,2.365-5.25,5.246-5.25h94.401c2.885,0,5.248,2.358,5.248,5.25v10.484c0,2.893-2.363,5.25-5.248,5.25H14.478c-2.881,0-5.244-2.357-5.244-5.25V43.617H9.239z"/><path d="M101.603,72.679c-18.178,0-32.919,14.73-32.919,32.92c0,18.178,14.741,32.919,32.919,32.919c18.181,0,32.921-14.741,32.921-32.919C134.523,87.42,119.783,72.679,101.603,72.679z M118.064,110.534h-11.531v11.519h-9.871v-11.519H85.133v-9.883h11.529V89.133h9.871v11.519h11.531V110.534z"/></g></svg></span><span>سرویس‌ها</span></a></li>
                    <li><a href="payment.php"><span class="menu-symbol"><svg class="svg-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" stroke="none" d="M21.9883291,10.9947074 L21.9888849,16.275793 C21.9888849,17.7383249 20.8471803,18.9341973 19.4064072,19.0207742 L19.2388849,19.025793 L4.76104885,19.025793 C3.29851702,19.025793 2.10264457,17.8840884 2.01606765,16.4433154 L2.01104885,16.275793 L2.01032912,10.9947074 L21.9883291,10.9947074 Z M18.2529045,14.5 L15.7529045,14.5 L15.6511339,14.5068466 C15.2850584,14.556509 15.0029045,14.8703042 15.0029045,15.25 C15.0029045,15.6296958 15.2850584,15.943491 15.6511339,15.9931534 L15.7529045,16 L18.2529045,16 L18.3546751,15.9931534 C18.7207506,15.943491 19.0029045,15.6296958 19.0029045,15.25 C19.0029045,14.8703042 18.7207506,14.556509 18.3546751,14.5068466 L18.2529045,14.5 Z M19.2388849,5.0207074 C20.7014167,5.0207074 21.8972891,6.162412 21.9838661,7.60318507 L21.9888849,7.7707074 L21.9883291,9.4947074 L2.01032912,9.4947074 L2.01104885,7.7707074 C2.01104885,6.30817556 3.15275345,5.11230312 4.59352652,5.02572619 L4.76104885,5.0207074 L19.2388849,5.0207074 Z"/></svg></span><span>تراکنش‌ها</span></a></li>
                    <li><a href="receipts.php"><span class="menu-symbol"><?php echo icon('receipt', 'svg-icon'); ?></span><span>رسیدهای پرداخت</span><?php if ($__hdr_pending_receipts > 0): ?><span class="menu-badge"><?php echo htmlspecialchars((string)$__hdr_pending_receipts, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></a></li>
                    <li><a href="cancelService.php"><span class="menu-symbol"><svg class="svg-icon" viewBox="0 -4.5 31 31" aria-hidden="true"><g fill="currentColor" stroke="none"><g transform="translate(-206,-626)" fill="currentColor"><path d="M235,643 L216,643 C214.896,643 214,643.896 214,645 C214,646.104 214.896,647 216,647 L235,647 C236.104,647 237,646.104 237,645 C237,643.896 236.104,643 235,643 L235,643 Z M235,635 L216,635 C214.896,635 214,635.896 214,637 C214,638.104 214.896,639 216,639 L235,639 C236.104,639 237,638.104 237,637 C237,635.896 236.104,635 235,635 L235,635 Z M216,631 L235,631 C236.104,631 237,630.104 237,629 C237,627.896 236.104,627 235,627 L216,627 C214.896,627 214,627.896 214,629 C214,630.104 214.896,631 216,631 L216,631 Z M209,642 C207.343,642 206,643.343 206,645 C206,646.657 207.343,648 209,648 C210.657,648 212,646.657 212,645 C212,643.343 210.657,642 209,642 L209,642 Z M209,634 C207.343,634 206,635.343 206,637 C206,638.657 207.343,640 209,640 C210.657,640 212,638.657 212,637 C212,635.343 210.657,634 209,634 L209,634 Z M209,626 C207.343,626 206,627.343 206,629 C206,630.657 207.343,632 209,632 C210.657,632 212,630.657 212,629 C212,627.343 210.657,626 209,626 L209,626 Z"/></g></g></svg></span><span>لیست درخواست‌ها</span></a></li>
                    <li><a href="discounts.php"><span class="menu-symbol"><svg class="svg-icon" viewBox="0 0 511.998 511.998" aria-hidden="true"><g fill="currentColor" stroke="none"><path d="M179.34,262.92c-9.217,0-16.716,7.499-16.716,16.716s7.499,16.716,16.716,16.716c9.217,0,16.716-7.499,16.716-16.716C196.056,270.419,188.559,262.92,179.34,262.92z"/><path d="M379.934,262.92c-9.217,0-16.716,7.499-16.716,16.716s7.499,16.716,16.716,16.716c9.217,0,16.716-7.499,16.716-16.716C396.65,270.419,389.152,262.92,379.934,262.92z"/><path d="M474.505,354.619l22.789-22.805c19.596-19.573,19.616-51.325,0-70.919L291.456,55.058l-47.275,47.28c-6.529,6.529-17.107,6.53-23.638,0c-6.529-6.524-6.529-17.108,0-23.638l47.275-47.28l-16.716-16.717c-19.548-19.558-51.268-19.638-70.924,0.007l-22.811,22.794l-11.514-8.2C113.358,6.17,67.665,8.998,38.341,38.342C9.382,67.306,5.584,112.524,29.309,145.864l8.184,11.514l-22.789,22.805c-19.596,19.573-19.616,51.325,0,70.919l16.716,16.716l47.275-47.275c6.529-6.529,17.108-6.529,23.638,0c6.529,6.524,6.529,17.113,0,23.638l-47.275,47.275l205.839,205.839c19.548,19.559,51.268,19.639,70.924-0.006l22.811-22.8l11.525,8.228c32.14,22.971,77.86,20.592,107.501-9.06c28.959-28.965,32.758-74.183,9.032-107.523L474.505,354.619z M125.981,173.262l47.275-47.281c6.529-6.529,17.108-6.529,23.638,0c6.529,6.524,6.529,17.108,0,23.638l-47.275,47.281c-6.529,6.529-17.107,6.53-23.638,0C119.452,190.376,119.452,179.791,125.981,173.262z M179.34,329.785c-27.653,0-50.148-22.495-50.148-50.148s22.495-50.148,50.148-50.148s50.148,22.495,50.148,50.148S206.994,329.785,179.34,329.785z M296.353,396.649c0,9.234-7.488,16.716-16.716,16.716c-9.228,0-16.716-7.482-16.716-16.716V162.624c0-9.234,7.488-16.716,16.716-16.716c9.228,0,16.716,7.482,16.716,16.716V396.649z M379.934,329.785c-27.653,0-50.148-22.495-50.148-50.148s22.495-50.148,50.148-50.148s50.148,22.495,50.148,50.148S407.588,329.785,379.934,329.785z"/></g></svg></span><span>کدهای تخفیف</span></a></li>
                </ul>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li><a href="users.php"><span class="menu-symbol"><?php echo icon('users', 'svg-icon'); ?></span><span>کاربران</span><?php if ($__hdr_users_label !== ''): ?><span class="menu-badge"><?php echo htmlspecialchars($__hdr_users_label, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></a></li>
            <li><a href="tickets.php"><span class="menu-symbol"><?php echo icon('message', 'svg-icon'); ?></span><span>تیکت‌ها</span><?php if (!empty($__hdr_open_tickets)): ?><span class="menu-badge"><?php echo htmlspecialchars((string)$__hdr_open_tickets, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></a></li>
            <li><a href="reports.php"><span class="menu-symbol"><?php echo icon('chart-bar', 'svg-icon'); ?></span><span>گزارش‌ها</span></a></li>
        </ul>

        <div class="sidebar-section-label">مدیریت</div>
        <div class="nav-group">
            <button type="button" class="nav-group__btn">
                <span class="menu-symbol"><?php echo icon('sliders', 'svg-icon'); ?></span>
                <span class="nav-group__label">مدیریت پنل و انبار</span>
                <span class="nav-group__chevron"><?php echo icon('chevron-down', 'svg-icon'); ?></span>
            </button>
            <div class="nav-group__panel">
                <ul class="sidebar-menu">
                    <li><a href="stock.php"><span class="menu-symbol"><?php echo icon('package', 'svg-icon'); ?></span><span>انبار شبکه ملی</span></a></li>
                    <li><a href="manual_service.php"><span class="menu-symbol"><?php echo icon('cart-check', 'svg-icon'); ?></span><span>فروش دستی</span></a></li>
                </ul>
            </div>
        </div>

        <div class="sidebar-section-label">پیکربندی</div>
        <div class="nav-group">
            <button type="button" class="nav-group__btn">
                <span class="menu-symbol"><?php echo icon('gear', 'svg-icon'); ?></span>
                <span class="nav-group__label">تنظیمات</span>
                <span class="nav-group__chevron"><?php echo icon('chevron-down', 'svg-icon'); ?></span>
            </button>
            <div class="nav-group__panel">
                <ul class="sidebar-menu">
                    <li><a href="textbot.php"><span class="menu-symbol"><?php echo icon('text', 'svg-icon'); ?></span><span>متن‌های ربات</span></a></li>
                    <li><a href="keyboard.php"><span class="menu-symbol"><?php echo icon('keyboard', 'svg-icon'); ?></span><span>چیدمان کیبورد</span></a></li>
                    <li><a href="service_keyboard.php"><span class="menu-symbol"><?php echo icon('palette', 'svg-icon'); ?></span><span>رنگ‌بندی دکمه‌ها</span></a></li>
                    <li><a href="appearance.php"><span class="menu-symbol"><?php echo icon('sparkles', 'svg-icon'); ?></span><span>تنظیمات ظاهر</span></a></li>
                    <li><a href="miscsettings.php"><span class="menu-symbol"><?php echo icon('sliders', 'svg-icon'); ?></span><span>تنظیمات متفرقه</span></a></li>
                    <li><a href="trust_channel.php"><span class="menu-symbol"><?php echo icon('shield', 'svg-icon'); ?></span><span>تنظیم کانال اعتماد</span></a></li>
                    <li><a href="banner.php"><span class="menu-symbol"><?php echo icon('image', 'svg-icon'); ?></span><span>تنظیم بنر</span></a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="sidebar-version" style="padding:14px 16px; border-top:1px solid var(--border-soft); display:flex; align-items:center;">
        <span class="version-pill">
            <span class="logo" title="فاکسیما">
                <img src="logo/faoxima.jpg" alt="faoxima" loading="lazy" width="34" height="34">
            </span>
            <span class="version-pill__label">Version</span>
            <span class="version-pill__num"><?php echo htmlspecialchars($__panelVersionRaw, ENT_QUOTES, 'UTF-8'); ?></span>
        </span>
    </div>
</aside>

<div class="sidebar-overlay"></div>

<script>
(function () {
    var bw = document.getElementById('hdrBellWrap');
    var notifWrap = document.getElementById('hdrNotifWrap');
    if (bw && notifWrap) {
        var orderLatest = parseInt(bw.getAttribute('data-notif-latest'), 10) || 0;
        var ticketsUnseen = bw.getAttribute('data-tickets-unseen') === '1';
        var bellBadge = notifWrap.querySelector('.hdr-bell__badge');
        var orderSeen = 0;
        try { orderSeen = parseInt(localStorage.getItem('faoxima_notif_seen'), 10) || 0; } catch (e) {}
        var orderUnseen = orderLatest > 0 && orderLatest > orderSeen;
        if (bellBadge && (ticketsUnseen || orderUnseen)) {
            bellBadge.hidden = false;
        }

        var notifTrigger = notifWrap.querySelector('[data-hdr-dd-toggle]');
        if (notifTrigger) {
            notifTrigger.addEventListener('click', function (ev) {
                ev.stopPropagation();
                notifWrap.classList.toggle('open');
                try { localStorage.setItem('faoxima_notif_seen', String(orderLatest)); } catch (e) {}
                if (ticketsUnseen) {
                    ticketsUnseen = false;
                    try { fetch('tickets.php?ajax=mark_ticket_seen', { method: 'POST', credentials: 'same-origin' }); } catch (e) {}
                }
                orderSeen = orderLatest;
                orderUnseen = false;
                if (bellBadge) bellBadge.hidden = true;
            });
            document.addEventListener('click', function (ev) {
                if (!notifWrap.contains(ev.target)) notifWrap.classList.remove('open');
            });
        }
    }

    var allSubmenuWraps = document.querySelectorAll('.profile-menu .submenu-wrap');
    allSubmenuWraps.forEach(function (wrap) {
        var trigger = wrap.querySelector('[data-submenu-toggle]');
        if (!trigger) return;
        trigger.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var willOpen = !wrap.classList.contains('open');
            allSubmenuWraps.forEach(function (w) { w.classList.remove('open'); });
            if (willOpen) wrap.classList.add('open');
        });
    });
    document.addEventListener('click', function (ev) {
        allSubmenuWraps.forEach(function (w) {
            if (!w.contains(ev.target)) w.classList.remove('open');
        });
    });

    var wrap = document.getElementById('hdrSearch');
    var input = document.getElementById('hdrSearchInput');
    var results = document.getElementById('hdrSearchResults');
    var mob = document.getElementById('hdrSearchMob');
    if (wrap && input && results) {
        var timer = null;

        function render(groups) {
            results.innerHTML = '';
            var any = false;
            (groups || []).forEach(function (g) {
                if (!g.items || !g.items.length) return;
                any = true;
                var lbl = document.createElement('div');
                lbl.className = 'hdr-search__group-label';
                lbl.textContent = g.label;
                results.appendChild(lbl);
                g.items.forEach(function (it) {
                    var a = document.createElement('a');
                    a.className = 'hdr-search__res';
                    a.href = it.url;
                    var ic = document.createElement('span');
                    ic.className = 'hdr-search__res-ic';
                    ic.innerHTML = g.icon || '';
                    var body = document.createElement('span');
                    body.className = 'hdr-search__res-body';
                    var t = document.createElement('span');
                    t.className = 'hdr-search__res-title';
                    t.textContent = it.title || '';
                    var s = document.createElement('span');
                    s.className = 'hdr-search__res-sub';
                    s.textContent = it.sub || '';
                    body.appendChild(t);
                    body.appendChild(s);
                    a.appendChild(ic);
                    a.appendChild(body);
                    results.appendChild(a);
                });
            });
            if (!any) {
                var e = document.createElement('div');
                e.className = 'hdr-search__empty';
                e.textContent = 'نتیجه‌ای یافت نشد';
                results.appendChild(e);
            }
        }

        function showMsg(msg) {
            results.innerHTML = '';
            var e = document.createElement('div');
            e.className = 'hdr-search__empty';
            e.textContent = msg;
            results.appendChild(e);
        }

        function doSearch(q) {
            fetch('index.php?ajax=search&q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (j) {
                    if (input.value.trim() !== q) return;
                    if (!j || !j.ok) { showMsg('خطای دسترسی به جستجو'); return; }
                    render(j.groups);
                })
                .catch(function (err) {
                    if (input.value.trim() !== q) return;
                    showMsg('خطا در جستجو');
                    try { console.error('search error:', err); } catch (e) {}
                });
        }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            wrap.classList.add('is-open');
            if (q.length < 2) { wrap.classList.remove('has-query'); results.innerHTML = ''; return; }
            wrap.classList.add('has-query');
            if (timer) clearTimeout(timer);
            timer = setTimeout(function () { doSearch(q); }, 250);
        });
        input.addEventListener('focus', function () { wrap.classList.add('is-open'); });

        document.addEventListener('mousedown', function (ev) {
            if (wrap.contains(ev.target) || (mob && mob.contains(ev.target))) return;
            wrap.classList.remove('is-open');
            wrap.classList.remove('is-mobile-open');
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                wrap.classList.remove('is-open');
                wrap.classList.remove('is-mobile-open');
                input.blur();
            }
        });

        if (mob) {
            mob.addEventListener('click', function (ev) {
                ev.stopPropagation();
                var open = wrap.classList.toggle('is-mobile-open');
                if (open) { wrap.classList.add('is-open'); setTimeout(function () { input.focus(); }, 50); }
                else { wrap.classList.remove('is-open'); }
            });
        }
    }
})();
</script>

