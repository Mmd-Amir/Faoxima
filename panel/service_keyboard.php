<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'service_keyboard.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../botapi.php';

if (\Faoxima\Admin\Web\Pages\ServiceKeyboardRequest::handle(
        ALLOWED_STYLES: $ALLOWED_STYLES,
        ALL_DB_KEYS: $ALL_DB_KEYS,
        CONFIG_COLUMNS: $CONFIG_COLUMNS,
        CONFIG_ITEM_KEY_RE: $CONFIG_ITEM_KEY_RE,
        CONFIG_ITEM_SECTION: $CONFIG_ITEM_SECTION,
        EXPLICIT_STYLES: $EXPLICIT_STYLES,
        MAIN_TOKEN_MAP: $MAIN_TOKEN_MAP,
        MENUS: $MENUS,
        STYLE_META: $STYLE_META,
        pdo: $pdo ?? null,
        query: $query,
        tonpayFactoryStyles: $tonpayFactoryStyles,
        userStylesRaw: $userStylesRaw
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\ServiceKeyboardTextAndStyles::handle(
        ALLOWED_STYLES: $ALLOWED_STYLES ?? null,
        ALL_DB_KEYS: $ALL_DB_KEYS ?? null,
        CONFIG_ITEM_KEY_RE: $CONFIG_ITEM_KEY_RE ?? null,
        CONFIG_ITEM_SECTION: $CONFIG_ITEM_SECTION ?? null,
        EXPLICIT_STYLES: $EXPLICIT_STYLES ?? null,
        MAIN_TOKEN_MAP: $MAIN_TOKEN_MAP ?? null,
        MENUS: $MENUS ?? null,
        adminWebPage: $adminWebPage ?? null,
        button: $button,
        factoryDefaultsMap: $factoryDefaultsMap,
        key: $key,
        keyboardMain: $keyboardMain,
        kmRaw: $kmRaw,
        kmRows: $kmRows,
        payload: $payload,
        pdo: $pdo ?? null,
        row: $row,
        rxAutoStyleFor: $rxAutoStyleFor,
        rxFamilyStyle: $rxFamilyStyle,
        rxPanelKey: $rxPanelKey ?? null,
        rxPanelPairs: $rxPanelPairs ?? null,
        rxPanelSec: $rxPanelSec ?? null,
        rxPanelVal: $rxPanelVal ?? null,
        settingRow: $settingRow,
        token: $token,
        tonpayFactoryStyles: $tonpayFactoryStyles ?? null,
        tonpayStyleKey: $tonpayStyleKey ?? null,
        tonpayStyleValue: $tonpayStyleValue ?? null,
        useBuiltinDefaults: $useBuiltinDefaults,
        userStylesRaw: $userStylesRaw,
        value: $value
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\ServiceKeyboardLayout::handle(
        EXPLICIT_STYLES: $EXPLICIT_STYLES ?? null,
        MENUS: $MENUS ?? null,
        SCREENS: $SCREENS,
        adminWebPage: $adminWebPage ?? null,
        buttons: $buttons,
        catalogSamples: $catalogSamples,
        colorCatalogGroups: $colorCatalogGroups,
        keyboardMain: $keyboardMain,
        kmRaw: $kmRaw ?? null,
        kmRows: $kmRows ?? null,
        label: $label,
        mkBtn: $mkBtn,
        mkCfgHead: $mkCfgHead,
        mkIndividual: $mkIndividual,
        name: $name,
        page: $page,
        payValue: $payValue,
        pdo: $pdo ?? null,
        role: $role,
        row: $row,
        rxAutoStyleFor: $rxAutoStyleFor ?? null,
        rxFamilyStyle: $rxFamilyStyle ?? null,
        serviceSampleActions: $serviceSampleActions,
        textbotMap: $textbotMap,
        token: $token,
        useBuiltinDefaults: $useBuiltinDefaults ?? null,
        userStylesRaw: $userStylesRaw ?? null
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\ServiceKeyboardScreenRegistry::handle(
        MENUS: $MENUS ?? null,
        SCREENS: $SCREENS,
        addScreen: $addScreen,
        adminWebPage: $adminWebPage ?? null,
        catalogSamples: $catalogSamples ?? null,
        label: $label,
        mkBtn: $mkBtn ?? null,
        mkCfgHead: $mkCfgHead ?? null,
        mkIndividual: $mkIndividual ?? null,
        note: $note,
        payValue: $payValue ?? null,
        role: $role,
        serviceSampleActions: $serviceSampleActions ?? null,
        subRows: $subRows,
        textbotMap: $textbotMap ?? null
    ) !== true) {
    unset($adminWebPage);
    return;
}
if (\Faoxima\Admin\Web\Pages\ServiceKeyboardServiceScreens::handle(
        ALL_DB_KEYS: $ALL_DB_KEYS ?? null,
        CONFIG_ITEM_SECTION: $CONFIG_ITEM_SECTION ?? null,
        MENUS: $MENUS ?? null,
        SCREENS: $SCREENS ?? null,
        addScreen: $addScreen ?? null,
        adminWebPage: $adminWebPage ?? null,
        factoryDefaultsMap: $factoryDefaultsMap ?? null,
        firstScreenId: $firstScreenId,
        gateway: $gateway,
        gatewayInternalLabels: $gatewayInternalLabels,
        gatewayInternalSamples: $gatewayInternalSamples,
        key: $key,
        label: $label,
        mkBtn: $mkBtn ?? null,
        mkIndividual: $mkIndividual ?? null,
        role: $role,
        rxFactoryForJs: $rxFactoryForJs,
        rxStylesForJs: $rxStylesForJs,
        subRows: $subRows ?? null,
        userStylesRaw: $userStylesRaw ?? null
    ) !== true) {
    unset($adminWebPage);
    return;
}

unset($adminWebPage);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark" data-color="blue">
<head>
    <script>
    (function(){try{document.documentElement.setAttribute('data-theme','dark'); localStorage.setItem('faoxima_theme','dark');
    var c=localStorage.getItem('faoxima_color');
    if(c)document.documentElement.setAttribute('data-color',c);}catch(e){}})();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>رنگ‌بندی دکمه‌ها — پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=toman75">
    <script src="js/theme.js?v=dark62" defer></script>
    <style>
        body { padding-top:0 !important; --skb-top-h:58px; }
        .skb-top {
            position:fixed; top:0; left:0; right:0; z-index:1000;
            display:flex; align-items:center; gap:10px 14px;
            padding:calc(11px + env(safe-area-inset-top)) calc(18px + env(safe-area-inset-right)) 11px calc(18px + env(safe-area-inset-left));
            background:var(--surface-1); border-bottom:1px solid var(--border-soft);
            box-shadow:var(--shadow-1); flex-wrap:wrap; justify-content:space-between;
        }
        .skb-brand { display:flex; align-items:center; gap:10px; font-weight:800; color:var(--accent); font-size:15px; letter-spacing:-.2px; min-width:0; }
        .skb-brand .lm { width:30px; height:30px; display:grid; place-items:center; background:#fff; border-radius:50%; overflow:hidden; flex-shrink:0; }
        .skb-brand .lm img { width:100%; height:100%; object-fit:contain; transform:scale(1.35); display:block; }
        .skb-top-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; min-width:0; }
        .skb-top-actions > * { flex:0 0 auto; }
        .skb-chip { display:inline-flex; align-items:center; gap:6px; padding:6px 11px; border-radius:100px; background:var(--accent-soft); border:1px solid var(--accent-mid); color:var(--text-muted); font-size:12px; white-space:nowrap; }
        .skb-toggle { display:flex; align-items:center; gap:7px; padding:6px 11px; border:1px solid var(--border-soft); border-radius:100px; background:var(--surface-1); font-size:12px; color:var(--text-muted); cursor:pointer; user-select:none; }
        .skb-toggle input { accent-color:var(--accent); width:14px; height:14px; cursor:pointer; }
        .skb-badge { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); }
        .skb-badge .dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
        .skb-badge.saving { color:#f59e0b; }
        .skb-badge.saved { color:#22c55e; }
        .skb-top .btn { border-radius:100px; gap:6px; }
        .skb-shell { max-width:1420px; margin:0 auto; padding:calc(var(--skb-top-h) + 18px) 18px calc(56px + env(safe-area-inset-bottom)); }
        .skb-layout { display:grid; grid-template-columns:248px minmax(0,1fr); gap:18px; align-items:start; }
        .skb-nav {
            position:sticky; top:calc(var(--skb-top-h) + 14px);
            background:var(--surface-1); border:1px solid var(--border-soft); border-radius:var(--radius-lg);
            padding:8px; max-height:calc(100dvh - var(--skb-top-h) - 30px); overflow:auto;
        }
        .skb-nav-title { font-size:11px; font-weight:700; color:var(--text-dim); padding:8px 10px 6px; }
        .skb-nav-item {
            display:flex; align-items:center; gap:9px; width:100%; text-align:start;
            padding:10px 11px; border:0; border-radius:var(--radius-md); background:none; cursor:pointer;
            color:var(--text-muted); font-family:'Vazirmatn',sans-serif; font-size:12.5px; font-weight:600;
        }
        .skb-nav-item .svg-icon { width:16px; height:16px; flex-shrink:0; }
        .skb-nav-item:hover { background:var(--surface-2); color:var(--text-main); }
        .skb-nav-item.active { background:var(--accent-soft); color:var(--accent); box-shadow:inset 0 0 0 1px var(--accent-mid); }
        .skb-nav-item:focus-visible { outline:2px solid var(--text-main); outline-offset:1px; }
        .skb-mobile-nav { display:none; position:sticky; top:calc(var(--skb-top-h) - 1px); z-index:30; margin-bottom:12px; background:var(--surface-1); border:1px solid var(--border-soft); border-radius:var(--radius-lg); padding:8px; box-shadow:var(--shadow-1); }
        .skb-mobile-nav label { display:block; font-size:11px; color:var(--text-dim); margin:0 4px 6px; }
        .skb-mobile-nav select { width:100%; padding:10px 12px; border-radius:var(--radius-md); border:1px solid var(--border-mid); background:var(--surface-2); color:var(--text-main); font-family:'Vazirmatn',sans-serif; font-size:13px; font-weight:700; direction:rtl; }
        .skb-section-trigger { display:flex; align-items:center; gap:10px; width:100%; min-height:48px; padding:10px 12px; border:1px solid var(--border-mid); border-radius:var(--radius-md); background:var(--surface-2); color:var(--text-main); font:inherit; font-size:13px; font-weight:700; text-align:start; cursor:pointer; }
        .skb-section-trigger > span { flex:1; min-width:0; overflow-wrap:anywhere; }
        .skb-section-trigger > svg { width:18px; height:18px; flex-shrink:0; color:var(--accent); transition:transform .15s; }
        .skb-section-trigger[aria-expanded="true"] { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
        .skb-section-trigger[aria-expanded="true"] > svg { transform:rotate(180deg); }
        .skb-section-trigger:focus-visible, .skb-section-option:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
        .skb-section-menu { position:fixed; z-index:1100; display:flex; flex-direction:column; box-sizing:border-box; padding:8px; border:1px solid var(--border-mid); border-radius:var(--radius-lg); background:var(--surface-1); color:var(--text-main); box-shadow:0 14px 42px rgba(0,0,0,.22); direction:rtl; overflow:hidden; }
        .skb-section-menu[hidden], .skb-section-option[hidden], .skb-section-empty[hidden] { display:none; }
        .skb-section-search { flex-shrink:0; box-sizing:border-box; width:100%; min-height:44px; padding:10px 12px; margin-bottom:7px; border:1px solid var(--border-soft); border-radius:var(--radius-md); background:var(--surface-2); color:var(--text-main); font:inherit; font-size:12px; outline:none; }
        .skb-section-search:focus { border-color:var(--accent); }
        .skb-section-list { min-height:0; overflow-y:auto; overscroll-behavior:contain; scrollbar-width:thin; padding:2px; }
        .skb-section-option { display:flex; align-items:center; gap:10px; width:100%; min-height:46px; padding:10px; border:0; border-radius:var(--radius-sm); background:transparent; color:var(--text-main); text-align:start; font:inherit; font-size:12.5px; cursor:pointer; }
        .skb-section-option > .svg-icon { width:18px; height:18px; flex-shrink:0; color:var(--text-muted); }
        .skb-section-option > span { flex:1; }
        .skb-section-option::after { content:'✓'; opacity:0; color:var(--accent); font-weight:800; }
        .skb-section-option:hover { background:var(--surface-2); }
        .skb-section-option[aria-selected="true"] { background:var(--accent-soft); color:var(--accent); font-weight:700; }
        .skb-section-option[aria-selected="true"]::after { opacity:1; }
        .skb-section-empty { margin:10px; text-align:center; color:var(--text-muted); font-size:12px; }
        .skb-main { min-width:0; }
        .skb-screen { display:none; max-width:880px; margin:0 auto; }
        .skb-screen.active { display:block; }
        .skb-screen-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
        .skb-screen-title { margin:0; font-size:17px; font-weight:800; color:var(--text-main); display:flex; align-items:center; gap:8px; }
        .skb-screen-title .svg-icon { color:var(--accent); width:20px; height:20px; }
        .skb-screen-desc { margin:4px 0 0; font-size:12.5px; color:var(--text-muted); line-height:1.8; }
        .skb-screen-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .skb-note { margin:12px 0; padding:10px 13px; border-radius:var(--radius-md); border:1px dashed var(--border-soft); background:var(--surface-2); color:var(--text-muted); font-size:11.5px; line-height:1.9; }
        .skb-catalog-sections { display:grid; gap:18px; }
        [data-list-panel][hidden], .skb-catalog-sections[hidden], .skb-catalog-pagination[hidden] { display:none; }
        .skb-list-position { color:var(--text-muted); font-size:12px; text-align:center; }
        .skb-catalog { padding:16px; min-width:0; border:1px solid var(--border-soft); border-radius:var(--radius-lg); background:var(--surface-2); scroll-margin-top:calc(var(--skb-top-h) + 18px); }
        .skb-catalog-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
        .skb-catalog-head h2 { margin:0; font-size:14px; font-weight:800; color:var(--text-main); }
        .skb-catalog-pagination { display:flex; align-items:center; gap:8px; }
        .skb-catalog-pagination .btn { min-height:38px; }
        .skb-catalog-pagination [data-catalog-page] { min-width:72px; text-align:center; color:var(--text-muted); font-size:11.5px; font-variant-numeric:tabular-nums; }
        .skb-catalog .btn:disabled { opacity:.4; cursor:default; }
        .skb-catalog [data-catalog-status] { margin:0 0 12px; font-size:11.5px; line-height:1.9; color:var(--text-muted); }
        .skb-catalog [data-catalog-buttons]:empty, .skb-catalog [data-catalog-retry][hidden] { display:none; }
        .skb-catalog [data-catalog-retry] { margin-bottom:12px; }
        .skb-screen { scroll-margin-top:calc(var(--skb-top-h) + 18px); }
        @media (max-width:640px) {
            .skb-catalog { padding:12px; }
            .skb-catalog-head { align-items:flex-start; }
            .skb-catalog-pagination { gap:5px; }
            .skb-catalog-pagination [data-catalog-page] { min-width:55px; }
            .skb-screen-actions .btn { min-height:40px; }
        }
        .skb-kb { direction:ltr; background:var(--surface-1); border:1px solid var(--border-soft); border-radius:var(--radius-2xl); padding:14px; box-shadow:var(--shadow-1); }
        .skb-row { display:flex; gap:8px; margin-bottom:8px; direction:ltr; }
        .skb-row:last-child { margin-bottom:0; }
        .skb-key {
            direction:rtl; flex:1 1 0; min-width:0; min-height:50px; padding:10px 12px;
            display:flex; align-items:center; justify-content:center; gap:7px; text-align:center;
            border-radius:var(--radius-md); border:1px solid var(--border-mid); background:var(--surface-3);
            color:var(--text-main); font-family:'Vazirmatn',sans-serif; font-size:13px; font-weight:600;
            cursor:pointer; transition:transform .12s,border-color .12s,filter .12s; flex-wrap:wrap;
        }
        .skb-key:hover { border-color:var(--accent); filter:brightness(1.03); }
        .skb-key:focus-visible { outline:2px solid var(--text-main); outline-offset:2px; }
        .skb-key__label { min-width:0; overflow-wrap:anywhere; line-height:1.4; }
        .skb-key[data-style="primary"] { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; border-color:#2563eb; }
        .skb-key[data-style="success"] { background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; border-color:#16a34a; }
        .skb-key[data-style="danger"] { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border-color:#dc2626; }
        .skb-key--head { font-weight:800; }
        .kb-color-opt[data-color="none"] .kb-color-opt__swatch { background:var(--surface-3); border-color:color-mix(in srgb, var(--text-main) 26%, transparent); }
        .skb-key.is-static { cursor:default; opacity:.6; }
        .skb-key.is-static:hover { transform:none; border-color:var(--border-mid); filter:none; }
        .skb-key__badge { flex-shrink:0; font-size:9px; font-weight:700; padding:1px 6px; border-radius:100px; background:var(--surface-2); color:var(--text-muted); border:1px solid var(--border-soft); }
        .skb-key__badge.is-on { color:#22c55e; border-color:rgba(34,197,94,.4); background:rgba(34,197,94,.1); }
        .skb-key__badge.is-off { color:#f59e0b; border-color:rgba(245,158,11,.4); background:rgba(245,158,11,.1); }
        .skb-key[data-style="primary"] .skb-key__badge,
        .skb-key[data-style="success"] .skb-key__badge,
        .skb-key[data-style="danger"] .skb-key__badge { background:rgba(255,255,255,.16); color:#fff; border-color:rgba(255,255,255,.28); }
        .skb-key__cond { width:7px; height:7px; border-radius:50%; background:#f59e0b; flex-shrink:0; }
        .skb-key[data-style="primary"] .skb-key__cond,
        .skb-key[data-style="success"] .skb-key__cond,
        .skb-key[data-style="danger"] .skb-key__cond { background:#fff; opacity:.75; }
        .skb-kb--table .skb-kb-scroll { overflow-x:auto; overscroll-behavior-x:contain; padding-bottom:2px; }
        .skb-kb--table .skb-row { display:grid; grid-template-columns:1fr 1fr 1.15fr; min-width:520px; }
        .skb-legend { display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin:10px 2px 0; font-size:11px; color:var(--text-dim); }
        .skb-legend span.item { display:inline-flex; align-items:center; gap:6px; }
        .skb-legend .dot { width:7px; height:7px; border-radius:50%; background:#f59e0b; display:inline-block; }
        .skb-legend .chip { font-size:9px; font-weight:700; padding:1px 6px; border-radius:100px; border:1px solid var(--border-soft); background:var(--surface-2); }
        .kb-modal__box { display:flex; flex-direction:column; max-height:min(92dvh,760px); }
        .kb-modal__box > form { display:flex; flex-direction:column; min-height:0; max-height:100%; }
        .kb-modal__body { flex:1 1 auto; min-height:0; overflow-y:auto; -webkit-overflow-scrolling:touch; overscroll-behavior:contain; }
        .kb-modal__foot { position:sticky; bottom:0; padding-bottom:calc(14px + env(safe-area-inset-bottom)); }
        .skb-modal-target { font-size:13px; color:var(--text-muted); margin-bottom:6px; }
        .skb-modal-target b { color:var(--text-main); font-weight:700; }
        .skb-current { font-size:11.5px; color:var(--text-dim); margin-bottom:16px; line-height:1.8; }
        .skb-scope-picker { display:grid; gap:8px; }
        .skb-scope-opt { position:relative; display:block; cursor:pointer; }
        .skb-scope-opt input { position:absolute; opacity:0; pointer-events:none; }
        .skb-scope-opt__card { display:block; padding:10px 14px; border-radius:var(--radius-md); border:1.5px solid var(--border-soft); background:var(--surface-1); transition:border-color .15s,background .15s,box-shadow .15s; }
        .skb-scope-opt:hover .skb-scope-opt__card { border-color:var(--border-mid); }
        .skb-scope-opt input:checked + .skb-scope-opt__card { border-color:var(--accent); background:var(--accent-soft); box-shadow:0 0 0 2px var(--accent-glow); }
        .skb-scope-opt input:focus-visible + .skb-scope-opt__card { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-glow); }
        .skb-scope-opt__title { display:block; font-size:13px; font-weight:700; color:var(--text-main); }
        .skb-scope-opt__hint { display:block; font-size:10.5px; color:var(--text-muted); margin-top:2px; }
        .skb-modal-help { margin:14px 0 0; font-size:11px; color:var(--text-dim); line-height:1.9; }
        body.skb-lock { overflow:hidden; }
        @media (max-width:1024px) {
            .skb-layout { grid-template-columns:minmax(0,1fr); }
            .skb-nav { display:none; }
            .skb-mobile-nav { display:block; }
        }
        @media (max-width:640px) {
            .skb-top { padding:calc(9px + env(safe-area-inset-top)) 12px 9px; gap:7px; }
            .skb-top .btn span, .skb-toggle span, .skb-badge #skb-badge-text { display:none; }
            .skb-top .btn, .skb-toggle { padding:7px 9px; }
            .skb-chip { padding:6px 9px; font-size:11px; }
            .skb-shell { padding:calc(var(--skb-top-h) + 12px) 10px calc(44px + env(safe-area-inset-bottom)); }
            .skb-kb { padding:10px; border-radius:14px; }
            .skb-row { gap:6px; margin-bottom:6px; }
            .skb-key { min-height:46px; padding:8px; font-size:12px; }
            .skb-kb--table .skb-row { min-width:460px; }
            .skb-screen-title { font-size:15.5px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .skb-key { transition:none; }
        }
        .skb-shell .skb-nav,
        .skb-shell .skb-mobile-nav {
            background: radial-gradient(ellipse at 100% 0%, var(--accent-soft), transparent 65%), var(--surface-1);
            border-color: var(--border-soft);
        }
        body.fx-service-keyboard {
            background: radial-gradient(ellipse at 0% 35%, var(--accent-soft), transparent 65%), var(--bg-body);
            background-attachment: fixed;
        }
        .skb-shell .skb-kb,
        .skb-shell .skb-catalog {
            background: var(--surface-1);
            border-color: var(--border-soft);
        }
    </style>
</head>
<body class="fx-service-keyboard">

<script type="application/json" id="skb-data">
<?php
echo json_encode([
    'styles'          => $rxStylesForJs,
    'factoryDefaults' => $rxFactoryForJs,
    'catalogSamples'  => $catalogSamples,
    'gatewaySamples'  => $gatewayInternalSamples,
    'gatewayLabels'   => $gatewayInternalLabels,
    'configColumns'   => $CONFIG_COLUMNS,
    'mainStyles'      => empty($keyboardMain) ? new stdClass() : $keyboardMain,
    'mainTokens'      => array_keys($MAIN_TOKEN_MAP),
    'useDefaults'     => $useBuiltinDefaults,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
</script>

<div class="skb-top">
    <div class="skb-brand">
        <span class="lm"><img src="logo/faoxima.jpg" alt="faoxima" loading="lazy" width="30" height="30"></span>
        <span>رنگ‌بندی دکمه‌ها</span>
    </div>
    <div class="skb-top-actions">
        <span class="skb-chip" id="skb-count" title="تعداد دکمه‌های قابل ویرایش در این صفحه">۰ دکمه</span>
        <label class="skb-toggle" title="رنگ‌های پیش‌فرض و موروثی را فعال می‌کند. برای حذف رنگ دستی از «حذف رنگ این بخش» استفاده کنید.">
            <input type="checkbox" id="skb-use-defaults" <?php echo $useBuiltinDefaults ? 'checked' : ''; ?>>
            <span>پیش‌فرض‌ها</span>
        </label>
        <div class="skb-badge" id="skb-badge"><span class="dot"></span><span id="skb-badge-text">ذخیره خودکار</span></div>
        <button type="button" class="btn btn-outline btn-sm" id="skb-reset-screen" title="حذف رنگ همه دکمه‌های بخش نمایش‌داده‌شده">
            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('rotate-left', 'svg-icon svg-sm'); ?>
            <span>حذف رنگ این بخش</span>
        </button>
        <a class="btn btn-outline btn-sm" href="keyboard.php">
            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('keyboard', 'svg-icon svg-sm'); ?>
            <span>چیدمان کیبورد</span>
        </a>
        <a class="btn btn-outline btn-sm" href="index.php">
            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-right', 'svg-icon svg-sm'); ?>
            <span>بازگشت</span>
        </a>
    </div>
</div>

<div class="skb-shell">
    <div class="skb-layout">
        <aside class="skb-nav" aria-label="صفحه‌های کیبورد">
            <div class="skb-nav-title">صفحه‌های کیبورد کاربر</div>
            <?php foreach ($SCREENS as $sid => $screen): ?>
            <button type="button" class="skb-nav-item<?php echo $sid === $firstScreenId ? ' active' : ''; ?>" data-screen="<?php echo htmlspecialchars($sid); ?>" aria-current="<?php echo $sid === $firstScreenId ? 'page' : 'false'; ?>">
                <?php echo \Faoxima\Admin\Web\Support\Icons::icon($screen['icon'], 'svg-icon'); ?>
                <span><?php echo htmlspecialchars($screen['title']); ?></span>
            </button>
            <?php endforeach; ?>
        </aside>

        <main class="skb-main">
            <div class="skb-mobile-nav">
                <label id="skb-section-label">بخش کیبورد</label>
                <select id="skb-screen-select" hidden aria-hidden="true" tabindex="-1">
                    <?php foreach ($SCREENS as $sid => $screen): ?>
                    <option value="<?php echo htmlspecialchars($sid); ?>"><?php echo htmlspecialchars($screen['title']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="skb-section-trigger" id="skb-section-trigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="skb-section-menu" aria-labelledby="skb-section-label skb-section-current">
                    <span id="skb-section-current"><?php echo htmlspecialchars($SCREENS[$firstScreenId]['title']); ?></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="skb-section-menu" id="skb-section-menu" role="dialog" aria-label="انتخاب بخش کیبورد" hidden>
                    <input type="search" class="skb-section-search" id="skb-section-search" placeholder="جستجوی بخش" aria-label="جستجوی بخش کیبورد" autocomplete="off">
                    <div class="skb-section-list" id="skb-section-list" role="listbox" aria-label="بخش‌های کیبورد">
                        <?php foreach ($SCREENS as $sid => $screen): ?>
                        <button type="button" class="skb-section-option" role="option" data-screen-pick="<?php echo htmlspecialchars($sid); ?>" aria-selected="<?php echo $sid === $firstScreenId ? 'true' : 'false'; ?>">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon($screen['icon'], 'svg-icon'); ?>
                            <span><?php echo htmlspecialchars($screen['title']); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="skb-section-empty" id="skb-section-empty" role="status" hidden>بخشی پیدا نشد</p>
                </div>
            </div>

            <?php foreach ($SCREENS as $sid => $screen): ?>
            <section class="skb-screen<?php echo $sid === $firstScreenId ? ' active' : ''; ?>" data-screen="<?php echo htmlspecialchars($sid); ?>" id="skb-screen-<?php echo htmlspecialchars($sid); ?>" aria-labelledby="skb-title-<?php echo htmlspecialchars($sid); ?>">
                <div class="skb-screen-head">
                    <div>
                        <h1 class="skb-screen-title" id="skb-title-<?php echo htmlspecialchars($sid); ?>">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon($screen['icon'], 'svg-icon'); ?>
                            <?php echo htmlspecialchars($screen['title']); ?>
                        </h1>
                        <p class="skb-screen-desc"><?php echo htmlspecialchars($screen['desc']); ?></p>
                    </div>
                    <div class="skb-screen-actions">
                        <?php if ($sid === 'lists'): ?>
                        <button type="button" class="btn btn-outline btn-sm" data-list-step="-1" disabled>بخش قبلی</button>
                        <span class="skb-list-position" data-list-position aria-live="polite">دکمه‌های عمومی</span>
                        <button type="button" class="btn btn-outline btn-sm" data-list-step="1">بخش بعدی</button>
                        <?php elseif ($sid === 'gateway_internals'): ?>
                        <button type="button" class="btn btn-outline btn-sm" data-gateway-step="-1" disabled>درگاه قبلی</button>
                        <span class="skb-list-position" data-gateway-position aria-live="polite">ریالیت</span>
                        <button type="button" class="btn btn-outline btn-sm" data-gateway-step="1">درگاه بعدی</button>
                        <?php else: ?>
                        <button type="button" class="btn btn-outline btn-sm" data-screen-step="-1" <?php echo $sid === array_key_first($SCREENS) ? 'disabled' : ''; ?>>بخش قبلی</button>
                        <button type="button" class="btn btn-outline btn-sm" data-screen-step="1" <?php echo $sid === array_key_last($SCREENS) ? 'disabled' : ''; ?>>بخش بعدی</button>
                        <?php endif; ?>
                        <?php if (!empty($screen['link'])): ?>
                        <a class="btn btn-outline btn-sm" href="<?php echo htmlspecialchars($screen['link']); ?>">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen-to-square', 'svg-icon svg-sm'); ?>
                            <span><?php echo htmlspecialchars($screen['link_label']); ?></span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($screen['note'])): ?>
                <div class="skb-note"><?php echo htmlspecialchars($screen['note']); ?></div>
                <?php endif; ?>
                <div class="skb-kb<?php echo !empty($screen['table']) ? ' skb-kb--table' : ''; ?>" role="group" aria-label="<?php echo htmlspecialchars($screen['title']); ?>" <?php echo $sid === 'lists' ? 'data-list-panel="general" data-list-label="دکمه‌های عمومی"' : ''; ?> <?php echo $sid === 'gateway_internals' ? 'data-gateway-buttons' : ''; ?>>
                    <?php if (!empty($screen['table'])): ?><div class="skb-kb-scroll"><?php endif; ?>
                    <?php foreach ($screen['rows'] as $row): ?>
                    <div class="skb-row">
                        <?php foreach ($row as $btn): ?>
                        <?php
                        $kind = (string) ($btn['kind'] ?? 'family');
                        if ($kind === 'static') {
                            echo '<div class="skb-key is-static"><span class="skb-key__label">' . htmlspecialchars((string) ($btn['label'] ?? '')) . '</span></div>';
                            continue;
                        }
                        $attrs = [
                            'type' => 'button',
                            'class' => 'skb-key' . (!empty($btn['header']) ? ' skb-key--head' : ''),
                            'data-kind' => $kind,
                            'data-menu' => (string) ($btn['menu'] ?? ''),
                            'data-key' => (string) ($btn['key'] ?? ''),
                            'data-fallback-key' => (string) ($btn['fallback_key'] ?? ''),
                            'data-fallback-menu' => (string) ($btn['fallback_menu'] ?? ''),
                            'data-fallback-secondary' => (string) ($btn['fallback_secondary'] ?? ''),
                            'data-auto' => (string) ($btn['auto'] ?? ''),
                            'data-style' => (string) ($btn['style'] ?? 'default'),
                            'data-scopes' => implode(',', (array) ($btn['scopes'] ?? [])),
                            'data-sample' => !empty($btn['sample']) ? '1' : '',
                        ];
                        if (!empty($btn['token'])) { $attrs['data-token'] = (string) $btn['token']; }
                        if (!empty($btn['item'])) { $attrs['data-item'] = (string) $btn['item']; }
                        if (!empty($btn['role'])) { $attrs['data-role'] = (string) $btn['role']; }
                        if (!empty($btn['family_label'])) { $attrs['data-family-label'] = (string) $btn['family_label']; }
                        if (!empty($btn['cond'])) { $attrs['title'] = (string) $btn['cond']; }
                        $attrs['aria-label'] = 'ویرایش رنگ «' . (string) ($btn['label'] ?? '') . '»';
                        echo '<button';
                        foreach ($attrs as $attrName => $attrVal) {
                            echo ' ' . $attrName . '="' . htmlspecialchars((string) $attrVal, ENT_QUOTES, 'UTF-8') . '"';
                        }
                        echo '>';
                        echo '<span class="skb-key__label">' . htmlspecialchars((string) ($btn['label'] ?? '')) . '</span>';
                        if (!empty($btn['badge'])) {
                            echo '<span class="skb-key__badge ' . htmlspecialchars((string) ($btn['badge_class'] ?? '')) . '">' . htmlspecialchars((string) $btn['badge']) . '</span>';
                        }
                        if (!empty($btn['cond'])) {
                            echo '<span class="skb-key__cond" aria-hidden="true"></span>';
                        }
                        echo '</button>';
                        ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!empty($screen['table'])): ?></div><?php endif; ?>
                </div>
                <?php if ($sid === 'lists'): ?>
                <div class="skb-catalog-sections" hidden>
                    <?php foreach ($colorCatalogGroups as $catalogGroup => $catalogLabel): ?>
                    <section class="skb-catalog" data-catalog="<?php echo htmlspecialchars($catalogGroup); ?>" data-list-panel="<?php echo htmlspecialchars($catalogGroup); ?>" data-list-label="<?php echo htmlspecialchars($catalogLabel); ?>" aria-labelledby="skb-catalog-title-<?php echo htmlspecialchars($catalogGroup); ?>" aria-busy="false" hidden>
                        <div class="skb-catalog-head">
                            <h2 id="skb-catalog-title-<?php echo htmlspecialchars($catalogGroup); ?>"><?php echo htmlspecialchars($catalogLabel); ?></h2>
                            <nav class="skb-catalog-pagination" aria-label="صفحه‌بندی <?php echo htmlspecialchars($catalogLabel); ?>" <?php echo $catalogGroup !== 'products' ? 'hidden' : ''; ?>>
                                <button type="button" class="btn btn-outline btn-sm" data-catalog-prev disabled>محصول قبلی</button>
                                <span data-catalog-page aria-live="polite">صفحه ۱</span>
                                <button type="button" class="btn btn-outline btn-sm" data-catalog-next disabled>محصول بعدی</button>
                            </nav>
                        </div>
                        <p data-catalog-status role="status" aria-live="polite"></p>
                        <button type="button" class="btn btn-outline btn-sm" data-catalog-retry hidden>تلاش دوباره</button>
                        <div class="skb-kb" data-catalog-buttons></div>
                    </section>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="skb-legend">
                    <span class="item"><span class="dot"></span> دکمه شرطی</span>
                    <span class="item">برای ویرایش رنگ فقط همان دکمه روی آن بزنید.</span>
                </div>
            </section>
            <?php endforeach; ?>
        </main>
    </div>
</div>

<div id="skb-toast" class="kb-toast" role="status" aria-live="polite"></div>

<div id="skb-modal" class="kb-modal" role="dialog" aria-modal="true" aria-labelledby="skb-modal-title">
    <div class="kb-modal__box">
        <form id="skb-edit-form">
            <div class="kb-modal__head" id="skb-modal-title">
                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen-to-square'); ?>
                <span>ویرایش رنگ دکمه</span>
            </div>
            <div class="kb-modal__body">
                <div class="skb-modal-target">دکمه: <b id="skb-modal-target">—</b></div>
                <div class="skb-current" id="skb-current"></div>

                <div class="kb-field">
                    <label>رنگ دکمه</label>
                    <div class="kb-color-picker">
                        <?php foreach ($STYLE_META as $styleKey => $styleMeta): ?>
                        <label class="kb-color-opt" data-color="<?php echo htmlspecialchars($styleKey); ?>">
                            <input type="radio" name="skb-color" value="<?php echo htmlspecialchars($styleKey); ?>" <?php echo $styleKey === 'none' ? 'checked' : ''; ?>>
                            <span class="kb-color-opt__card">
                                <span class="kb-color-opt__name"><?php echo htmlspecialchars($styleMeta['label']); ?></span>
                                <span class="kb-color-opt__hint"><?php echo htmlspecialchars($styleMeta['hint']); ?></span>
                                <span class="kb-color-opt__swatch"></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="kb-field" id="skb-scope-field">
                    <label>دامنه اعمال رنگ</label>
                    <div class="skb-scope-picker" id="skb-scopes"></div>
                </div>

                <p class="skb-modal-help">«بدون رنگ» رنگ دکمه را حذف می‌کند و جلوی رنگ خودکار را می‌گیرد. تغییر رنگ فقط روی همین دکمه اثر دارد.</p>
            </div>
            <div class="kb-modal__foot">
                <button type="button" class="btn btn-outline btn-sm" id="skb-reset-button">حذف رنگ همین دکمه</button>
                <button type="button" class="btn btn-outline btn-sm" id="skb-cancel">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('xmark', 'svg-icon svg-sm'); ?>
                    <span>انصراف</span>
                </button>
                <button type="submit" class="btn btn-primary btn-sm" id="skb-save">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon svg-sm'); ?>
                    <span>ذخیره رنگ</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    'use strict';

    var dataEl = document.getElementById('skb-data');
    var data = {};
    try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) { data = {}; }

    var styles = data.styles || {};
    var factoryDefaults = data.factoryDefaults || {};
    var mainStyles = data.mainStyles || {};
    var mainTokens = Array.isArray(data.mainTokens) ? data.mainTokens : [];
    var changes = {};
    var mainChanges = {};
    var defaultsChanged = false;
    var clientId = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2) + '-' + Math.random().toString(36).slice(2);
    var revision = 0;
    var useDefaults = !!data.useDefaults;

    if (!styles.config_items || Array.isArray(styles.config_items)) styles.config_items = {};

    var COLORS = ['none', 'primary', 'success', 'danger'];
    var ROLE_HINTS = {
        download: 'همه دکمه‌های دریافت کانفیگ',
        name: 'همه خانه‌های نام کانفیگ',
        protocol: 'همه خانه‌های پروتکل'
    };
    var SOURCE_TEXT = {
        exact: 'رنگ دستی همین دکمه',
        button: 'رنگ اختصاصی همین دکمه',
        family: 'تنظیم خانواده همین دکمه',
        factory: 'رنگ پیش‌فرض کارخانه‌ای',
        auto: 'رنگ خودکار متن دکمه',
        'default': 'بدون رنگ (پیش‌فرض تلگرام)'
    };

    var modalEl = document.getElementById('skb-modal');
    var modalTargetEl = document.getElementById('skb-modal-target');
    var currentEl = document.getElementById('skb-current');
    var scopesEl = document.getElementById('skb-scopes');
    var scopeFieldEl = document.getElementById('skb-scope-field');
    var colorEls = document.querySelectorAll('input[name="skb-color"]');
    var formEl = document.getElementById('skb-edit-form');
    var toastEl = document.getElementById('skb-toast');
    var badgeEl = document.getElementById('skb-badge');
    var badgeTextEl = document.getElementById('skb-badge-text');
    var countEl = document.getElementById('skb-count');
    var navSelect = document.getElementById('skb-screen-select');
    var sectionTrigger = document.getElementById('skb-section-trigger');
    var sectionCurrent = document.getElementById('skb-section-current');
    var sectionMenu = document.getElementById('skb-section-menu');
    var sectionSearch = document.getElementById('skb-section-search');
    var sectionList = document.getElementById('skb-section-list');
    var sectionOptions = Array.from(sectionList.querySelectorAll('[data-screen-pick]'));

    var editingEl = null;
    var lastFocus = null;
    var saveTimer = null;
    var isSaving = false;
    var pendingSave = false;
    var toastTimer = null;
    var currentScreen = '<?php echo $firstScreenId; ?>';

    function validStyle(v) {
        return typeof v === 'string' && COLORS.indexOf(v) !== -1;
    }

    function familyStyle(menu, key, auto, fallbackKey, fallbackMenu, fallbackSecondary) {
        var menuStyles = styles[menu];
        if (fallbackMenu && fallbackMenu !== menu) {
            if (menuStyles && validStyle(menuStyles[key])) return menuStyles[key];
            if (!useDefaults) return 'none';
            return familyStyle(fallbackMenu, fallbackKey, auto, fallbackSecondary);
        }
        var candidates = [key];
        if (useDefaults && fallbackKey && fallbackKey !== key) candidates.push(fallbackKey);
        if (menuStyles && !Array.isArray(menuStyles)) {
            for (var i = 0; i < candidates.length; i++) {
                var saved = menuStyles[candidates[i]];
                if (validStyle(saved)) return saved;
            }
        }
        if (!useDefaults) return 'default';
        var factory = factoryDefaults[menu];
        if (factory && !Array.isArray(factory)) {
            for (var j = 0; j < candidates.length; j++) {
                var fv = factory[candidates[j]];
                if (validStyle(fv)) return fv;
            }
        }
        return validStyle(auto) ? auto : 'default';
    }

    function styleFor(el) {
        var kind = el.dataset.kind;
        if (kind === 'main') {
            var token = el.dataset.token || '';
            if (validStyle(mainStyles[token])) return mainStyles[token];
        }
        if (kind === 'config_item') {
            var itemKey = (el.dataset.item || '') + ':' + (el.dataset.role || '');
            var exact = styles.config_items[itemKey];
            if (validStyle(exact)) return exact;
        }
        return familyStyle(el.dataset.menu, el.dataset.key, el.dataset.auto, el.dataset.fallbackKey || '', el.dataset.fallbackMenu || '', el.dataset.fallbackSecondary || '');
    }

    function sourceFor(el) {
        var kind = el.dataset.kind;
        if (kind === 'main' && validStyle(mainStyles[el.dataset.token || ''])) return 'exact';
        if (kind === 'config_item' && validStyle(styles.config_items[(el.dataset.item || '') + ':' + (el.dataset.role || '')])) return 'exact';
        var menuStyles = styles[el.dataset.menu];
        var key = el.dataset.key || '';
        var fallbackKey = el.dataset.fallbackKey || '';
        var fallbackMenu = el.dataset.fallbackMenu || '';
        if (kind === 'individual') {
            if (menuStyles && validStyle(menuStyles[key])) return 'button';
            if (useDefaults && fallbackMenu && styles[fallbackMenu] && validStyle(styles[fallbackMenu][fallbackKey])) return 'family';
            return useDefaults ? 'factory' : 'default';
        }
        if (menuStyles && !Array.isArray(menuStyles)) {
            if (validStyle(menuStyles[key])) return 'button';
            if (useDefaults && fallbackKey && fallbackKey !== key && validStyle(menuStyles[fallbackKey])) return 'family';
        }
        if (useDefaults) {
            var factory = factoryDefaults[el.dataset.menu];
            if (factory && !Array.isArray(factory)) {
                if (validStyle(factory[key])) return 'factory';
                if (fallbackKey && fallbackKey !== key && validStyle(factory[fallbackKey])) return 'factory';
            }
            if (validStyle(el.dataset.auto)) return 'auto';
        }
        return 'default';
    }

    function refreshAll() {
        document.querySelectorAll('.skb-key[data-kind]').forEach(function(el) {
            el.dataset.style = styleFor(el);
        });
    }

    function setFamily(menu, key, style) {
        if (!styles[menu] || Array.isArray(styles[menu])) styles[menu] = {};
        if (style === 'default') delete styles[menu][key];
        else styles[menu][key] = style;
        if (!changes[menu]) changes[menu] = {};
        changes[menu][key] = style === 'default' ? null : style;
    }

    function setMain(token, style) {
        if (!mainStyles || Array.isArray(mainStyles)) mainStyles = {};
        if (style === 'default') {
            delete mainStyles[token];
        } else {
            mainStyles[token] = style;
        }
        mainChanges[token] = style === 'default' ? null : style;
    }

    function setExact(item, role, style) {
        var key = item + ':' + role;
        if (!styles.config_items || Array.isArray(styles.config_items)) styles.config_items = {};
        if (style === 'default') delete styles.config_items[key];
        else styles.config_items[key] = style;
        if (!changes.config_items) changes.config_items = {};
        changes.config_items[key] = style === 'default' ? null : style;
    }

    function scopeOptions(el) {
        var kind = el.dataset.kind;
        var scopes = (el.dataset.scopes || '').split(',').filter(Boolean);
        var options = [];
        scopes.forEach(function(scope) {
            if (scope === 'exact') {
                options.push({
                    id: 'exact',
                    title: kind === 'config_item' ? 'این دکمه (همین کانفیگ)' : 'این دکمه',
                    hint: kind === 'main' ? 'فقط همین دکمه در منوی اصلی' : 'فقط همین کانفیگ، بر اساس محتوای خود کانفیگ'
                });
            } else if (scope === 'button') {
                options.push({
                    id: 'button',
                    title: kind === 'config_header' ? 'این سرستون' : 'این دکمه',
                    hint: kind === 'config_header' ? 'فقط همین سرستون' : 'فقط همین دکمه'
                });
            } else if (scope === 'family') {
                var title = 'همه دکمه‌های مشابه';
                var hint = el.dataset.familyLabel || 'کل خانواده این نقش';
                if (kind === 'config_header') {
                    title = 'این سرستون';
                    hint = 'فقط همین سرستون';
                } else if (kind === 'main') {
                    hint = 'رنگ پیش‌فرض نقش؛ اگر رنگ دستی همین دکمه تنظیم شده باشد، آن اولویت دارد.';
                } else if (kind === 'config_item') {
                    hint = (el.dataset.familyLabel || 'کل خانواده این نقش') + ' — اگر رنگ اختصاصی همین کانفیگ تنظیم شده باشد، آن اولویت دارد.';
                }
                options.push({ id: 'family', title: title, hint: hint });
            } else if (scope === 'row') {
                options.push({
                    id: 'row',
                    title: kind === 'config_header' ? 'کل ردیف سرستون' : 'این ردیف',
                    hint: kind === 'config_header' ? 'هر سه سرستون با هم' : 'دانلود، پروتکل و نام همین ردیف'
                });
            }
        });
        return options;
    }

    function openEditor(el) {
        editingEl = el;
        lastFocus = el;
        modalTargetEl.textContent = (el.querySelector('.skb-key__label') || {}).textContent || '—';

        var style = styleFor(el);
        var options = scopeOptions(el);
        var defaultScope = 'family';
        if (options.some(function(o) { return o.id === 'exact'; })) defaultScope = 'exact';
        else if (options.some(function(o) { return o.id === 'button'; })) defaultScope = 'button';
        else if (options.some(function(o) { return o.id === 'family'; })) defaultScope = 'family';
        else if (options.length) defaultScope = options[0].id;

        scopesEl.innerHTML = '';
        options.forEach(function(opt) {
            var label = document.createElement('label');
            label.className = 'skb-scope-opt';
            label.innerHTML = '<input type="radio" name="skb-scope" value="' + opt.id + '"' + (opt.id === defaultScope ? ' checked' : '') + '>'
                + '<span class="skb-scope-opt__card"><span class="skb-scope-opt__title"></span><span class="skb-scope-opt__hint"></span></span>';
            label.querySelector('.skb-scope-opt__title').textContent = opt.title;
            label.querySelector('.skb-scope-opt__hint').textContent = opt.hint;
            scopesEl.appendChild(label);
        });
        scopeFieldEl.style.display = options.length > 1 ? '' : 'none';

        colorEls.forEach(function(input) {
            input.checked = (input.value === (validStyle(style) ? style : 'none'));
        });

        currentEl.textContent = 'رنگ فعلی: ' + (SOURCE_TEXT[sourceFor(el)] || SOURCE_TEXT['default']) + '.';
        modalEl.querySelector('.skb-modal-help').textContent = el.dataset.sample === '1'
            ? 'رنگ این نمونه برای همین نوع دکمه در همه آیتم‌های فعلی و جدید اعمال می‌شود. رنگ دکمه‌های دیگر مستقل است.'
            : '«بدون رنگ» رنگ دکمه را حذف می‌کند و جلوی رنگ خودکار را می‌گیرد. تغییر رنگ فقط روی همین دکمه اثر دارد.';

        modalEl.classList.add('open');
        document.body.classList.add('skb-lock');
        var checked = document.querySelector('input[name="skb-color"]:checked') || colorEls[0];
        if (checked) setTimeout(function() { checked.focus(); }, 60);
    }

    function closeEditor() {
        modalEl.classList.remove('open');
        document.body.classList.remove('skb-lock');
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
        editingEl = null;
        lastFocus = null;
    }

    function commitEdit() {
        if (!editingEl) return;
        var color = 'none';
        colorEls.forEach(function(input) { if (input.checked) color = input.value; });
        var scopeInput = document.querySelector('input[name="skb-scope"]:checked');
        var scope = scopeInput ? scopeInput.value : 'family';
        var el = editingEl;
        var kind = el.dataset.kind;
        var item = el.dataset.item || '';

        if (scope === 'button' || scope === 'family') {
            setFamily(el.dataset.menu, el.dataset.key, color);
        } else if (scope === 'exact') {
            if (kind === 'main') setMain(el.dataset.token || '', color);
            else if (kind === 'config_item') setExact(item, el.dataset.role || '', color);
        } else if (scope === 'row') {
            if (kind === 'config_item') {
                ['download', 'name', 'protocol'].forEach(function(role) {
                    setExact(item, role, color);
                });
            } else if (kind === 'config_header') {
                ['cfg_header_action', 'cfg_header_protocol', 'cfg_header_name'].forEach(function(key) {
                    setFamily('config_list', key, color);
                });
            }
        }

        refreshAll();
        closeEditor();
        scheduleSave();
        showToast('رنگ اعمال شد و در حال ذخیره است', 'success');
    }

    function deliverableStyles() {
        var out = { patch_version: 1, client: clientId, revision: ++revision, changes: changes, main_changes: mainChanges };
        if (defaultsChanged) out._use_defaults = !!useDefaults;
        return JSON.parse(JSON.stringify(out));
    }

    function hasChanges() {
        return defaultsChanged || Object.keys(mainChanges).length > 0 || Object.keys(changes).some(function(menu) { return Object.keys(changes[menu]).length > 0; });
    }

    function acknowledge(payload) {
        Object.keys(payload.changes).forEach(function(menu) {
            Object.keys(payload.changes[menu]).forEach(function(key) {
                if (changes[menu] && changes[menu][key] === payload.changes[menu][key]) delete changes[menu][key];
            });
            if (changes[menu] && !Object.keys(changes[menu]).length) delete changes[menu];
        });
        Object.keys(payload.main_changes).forEach(function(token) {
            if (mainChanges[token] === payload.main_changes[token]) delete mainChanges[token];
        });
        if (Object.prototype.hasOwnProperty.call(payload, '_use_defaults') && useDefaults === payload._use_defaults) defaultsChanged = false;
    }

    function scheduleSave() {
        setBadge('saving', 'در حال ذخیره...');
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function() {
            saveTimer = null;
            doSave();
        }, 450);
    }

    function doSave() {
        if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
        if (isSaving) { pendingSave = true; return; }
        if (!hasChanges()) return;
        var payload = deliverableStyles();
        isSaving = true;
        setBadge('saving', 'در حال ذخیره...');
        fetch('service_keyboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            keepalive: true,
            credentials: 'same-origin'
        })
        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function(d) {
            isSaving = false;
            if (d && d.ok) {
                acknowledge(payload);
                if (!hasChanges()) setBadge('saved', 'ذخیره شد ✓');
            } else {
                throw new Error((d && d.error) ? d.error : 'unknown');
            }
            if (pendingSave || hasChanges()) { pendingSave = false; doSave(); }
        })
        .catch(function() {
            isSaving = false;
            setBadge('', 'خطا در ذخیره');
            showToast('خطا در ذخیره تنظیمات', 'error');
            if (pendingSave) { pendingSave = false; doSave(); }
        });
    }

    function sendBeaconSave() {
        if (!hasChanges()) return;
        if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
        if (!navigator.sendBeacon) { doSave(); return; }
        var blob = new Blob([JSON.stringify(deliverableStyles())], { type: 'application/json' });
        if (!navigator.sendBeacon('service_keyboard.php', blob)) doSave();
    }

    function setBadge(kind, text) {
        badgeEl.className = 'skb-badge' + (kind ? ' ' + kind : '');
        badgeTextEl.textContent = text;
    }

    function showToast(message, kind) {
        toastEl.className = 'kb-toast' + (kind ? ' ' + kind : '');
        toastEl.textContent = message;
        toastEl.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function() { toastEl.classList.remove('show'); }, 1900);
    }

    var listSectionIndex = 0;
    var gatewaySectionIndex = 0;

    function activateGatewaySection(index) {
        var screen = document.getElementById('skb-screen-gateway_internals');
        var samples = data.gatewaySamples || {};
        var gateways = Object.keys(samples);
        if (!screen || !gateways.length) return;
        gatewaySectionIndex = Math.max(0, Math.min(index, gateways.length - 1));
        var gateway = gateways[gatewaySectionIndex];
        renderIndividualButtons(screen.querySelector('[data-gateway-buttons]'), samples[gateway], false);
        screen.querySelector('[data-gateway-position]').textContent = (data.gatewayLabels || {})[gateway] + ' · ' + (gatewaySectionIndex + 1).toLocaleString('fa-IR') + ' از ' + gateways.length.toLocaleString('fa-IR');
        screen.querySelector('[data-gateway-step="-1"]').disabled = gatewaySectionIndex === 0;
        screen.querySelector('[data-gateway-step="1"]').disabled = gatewaySectionIndex === gateways.length - 1;
    }

    function activateListSection(index) {
        var screen = document.getElementById('skb-screen-lists');
        if (!screen) return;
        var panels = Array.from(screen.querySelectorAll('[data-list-panel]'));
        if (!panels.length) return;
        listSectionIndex = Math.max(0, Math.min(index, panels.length - 1));
        panels.forEach(function(panel, panelIndex) { panel.hidden = panelIndex !== listSectionIndex; });
        screen.querySelector('.skb-catalog-sections').hidden = listSectionIndex === 0;
        screen.querySelector('[data-list-step="-1"]').disabled = listSectionIndex === 0;
        screen.querySelector('[data-list-step="1"]').disabled = listSectionIndex === panels.length - 1;
        var panel = panels[listSectionIndex];
        screen.querySelector('[data-list-position]').textContent = panel.dataset.listLabel + ' · ' + (listSectionIndex + 1).toLocaleString('fa-IR') + ' از ' + panels.length.toLocaleString('fa-IR');
        if (panel.hasAttribute('data-catalog') && panel.dataset.loaded !== '1') loadColorCatalog(panel, 0);
        updateCount();
    }

    function updateCount() {
        var screen = document.querySelector('.skb-screen.active');
        var count = screen ? Array.from(screen.querySelectorAll('.skb-key[data-kind]')).filter(function(button) {
            return !button.closest('[data-list-panel][hidden]');
        }).length : 0;
        countEl.textContent = count + ' دکمه';
    }

    function closeSectionMenu(restoreFocus) {
        sectionMenu.hidden = true;
        sectionTrigger.setAttribute('aria-expanded', 'false');
        if (restoreFocus) sectionTrigger.focus();
    }

    function positionSectionMenu() {
        if (sectionMenu.hidden) return;
        if (!sectionTrigger.getClientRects().length) { closeSectionMenu(false); return; }
        var rect = sectionTrigger.getBoundingClientRect();
        var viewport = window.visualViewport;
        var top = viewport ? viewport.offsetTop : 0;
        var left = viewport ? viewport.offsetLeft : 0;
        var width = viewport ? viewport.width : window.innerWidth;
        var height = viewport ? viewport.height : window.innerHeight;
        var below = top + height - rect.bottom - 12;
        var above = rect.top - top - 12;
        var down = below >= Math.min(240, height / 2) || below >= above;
        var available = Math.max(0, down ? below : above);
        var menuWidth = Math.min(rect.width, Math.max(0, width - 16));
        sectionMenu.style.width = menuWidth + 'px';
        sectionMenu.style.maxHeight = Math.min(420, available) + 'px';
        sectionMenu.style.left = Math.max(left + 8, Math.min(rect.right - menuWidth, left + width - menuWidth - 8)) + 'px';
        sectionMenu.style.top = (down ? rect.bottom + 6 : Math.max(top + 8, rect.top - sectionMenu.offsetHeight - 6)) + 'px';
    }

    function filterSections() {
        function normalize(value) { return value.replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[\u200c\s]+/g, ' ').trim(); }
        var query = normalize(sectionSearch.value);
        var count = 0;
        sectionOptions.forEach(function(option) {
            option.hidden = normalize(option.textContent).indexOf(query) === -1;
            if (!option.hidden) count++;
        });
        document.getElementById('skb-section-empty').hidden = count !== 0;
        positionSectionMenu();
    }

    function openSectionMenu(focusOption) {
        sectionSearch.value = '';
        sectionMenu.hidden = false;
        sectionTrigger.setAttribute('aria-expanded', 'true');
        filterSections();
        var selected = sectionOptions.find(function(option) { return option.getAttribute('aria-selected') === 'true'; });
        if (selected) sectionList.scrollTop = Math.max(0, selected.offsetTop - sectionList.offsetTop - sectionList.clientHeight / 2);
        if (focusOption && selected) selected.focus({ preventScroll: true });
    }

    function activateScreen(id) {
        if (!document.getElementById('skb-screen-' + id)) id = '<?php echo $firstScreenId; ?>';
        currentScreen = id;
        document.querySelectorAll('.skb-screen').forEach(function(screen) {
            screen.classList.toggle('active', screen.dataset.screen === id);
        });
        document.querySelectorAll('.skb-nav-item').forEach(function(item) {
            var active = item.dataset.screen === id;
            item.classList.toggle('active', active);
            item.setAttribute('aria-current', active ? 'page' : 'false');
        });
        if (navSelect) navSelect.value = id;
        sectionOptions.forEach(function(option) {
            var active = option.dataset.screenPick === id;
            option.setAttribute('aria-selected', active ? 'true' : 'false');
            option.tabIndex = active ? 0 : -1;
            if (active) sectionCurrent.textContent = option.querySelector('span').textContent;
        });
        closeSectionMenu(false);
        updateCount();
        if (id === 'lists') activateListSection(listSectionIndex);
        if (id === 'gateway_internals') activateGatewaySection(gatewaySectionIndex);
        try { localStorage.setItem('faoxima_skb_screen', id); } catch (e) {}
    }

    function renderIndividualButtons(container, buttons, append) {
        if (!append) container.replaceChildren();
        buttons.forEach(function(button) {
            var row = document.createElement('div');
            row.className = 'skb-row';
            var el = document.createElement('button');
            el.type = 'button';
            el.className = 'skb-key';
            ['kind', 'menu', 'key', 'auto', 'item', 'role'].forEach(function(name) {
                if (button[name] !== undefined) el.dataset[name] = button[name];
            });
            el.dataset.fallbackKey = button.fallback_key || '';
            el.dataset.fallbackMenu = button.fallback_menu || '';
            el.dataset.fallbackSecondary = button.fallback_secondary || '';
            el.dataset.scopes = (button.scopes || ['button']).join(',');
            el.dataset.sample = button.sample ? '1' : '';
            el.setAttribute('aria-label', 'ویرایش رنگ «' + button.label + '»');
            var label = document.createElement('span');
            label.className = 'skb-key__label';
            label.textContent = button.label;
            el.appendChild(label);
            row.appendChild(el);
            container.appendChild(row);
        });
        refreshAll();
        updateCount();
    }

    function loadColorCatalog(catalog, page) {
        var group = catalog.dataset.catalog;
        page = Math.max(0, Number(page) || 0);
        var request = Number(catalog.dataset.request || 0) + 1;
        catalog.dataset.request = String(request);
        var status = catalog.querySelector('[data-catalog-status]');
        var previous = catalog.querySelector('[data-catalog-prev]');
        var next = catalog.querySelector('[data-catalog-next]');
        var retry = catalog.querySelector('[data-catalog-retry]');
        var pageLabel = catalog.querySelector('[data-catalog-page]');
        var sampleButtons = (data.catalogSamples || {})[group];
        var pageSize = 12;
        function show(buttons, more, totalPages) {
            if (Number(catalog.dataset.request) !== request) return;
            renderIndividualButtons(catalog.querySelector('[data-catalog-buttons]'), buttons, false);
            catalog.dataset.page = String(page);
            catalog.dataset.loaded = '1';
            catalog.setAttribute('aria-busy', 'false');
            previous.disabled = page === 0;
            next.disabled = !more;
            retry.hidden = true;
            pageLabel.textContent = 'صفحه ' + (page + 1).toLocaleString('fa-IR') + (totalPages ? ' از ' + totalPages.toLocaleString('fa-IR') : '');
            status.textContent = buttons.length ? (group === 'products' ? 'رنگ هر خانه فقط برای همان محصول ذخیره می‌شود.' : 'رنگ هر نمونه برای همان نوع دکمه در آیتم‌های فعلی و جدید اعمال می‌شود.') : 'هنوز محصولی ثبت نشده است.';
        }
        if (Array.isArray(sampleButtons)) {
            var totalPages = Math.max(1, Math.ceil(sampleButtons.length / pageSize));
            page = Math.min(page, totalPages - 1);
            show(sampleButtons.slice(page * pageSize, (page + 1) * pageSize), page + 1 < totalPages, totalPages);
            return;
        }
        catalog.setAttribute('aria-busy', 'true');
        catalog.dataset.retryPage = String(page);
        previous.disabled = true;
        next.disabled = true;
        retry.hidden = true;
        status.textContent = 'در حال دریافت محصولات…';
        var params = new URLSearchParams({action: 'button_catalog', group: 'products', page: String(page)});
        fetch('service_keyboard.php?' + params.toString(), {credentials: 'same-origin'})
            .then(function(response) { if (!response.ok) throw new Error('catalog_failed'); return response.json(); })
            .then(function(result) {
                if (Number(catalog.dataset.request) !== request) return;
                if (!result.ok || !Array.isArray(result.buttons)) throw new Error('catalog_failed');
                show(result.buttons, !!result.more);
            })
            .catch(function() {
                if (Number(catalog.dataset.request) !== request) return;
                catalog.setAttribute('aria-busy', 'false');
                previous.disabled = Number(catalog.dataset.page || 0) === 0;
                status.textContent = 'دریافت محصولات انجام نشد.';
                retry.hidden = false;
            });
    }
    function clearButtonColor(el) {
        if (el.dataset.kind === 'main') setMain(el.dataset.token, 'none');
        else if (el.dataset.kind === 'config_item') setExact(el.dataset.item, el.dataset.role, 'none');
        else setFamily(el.dataset.menu, el.dataset.key, 'none');
    }

    function resetEditingButton() {
        if (!editingEl) return;
        var el = editingEl;
        clearButtonColor(el);
        refreshAll();
        closeEditor();
        scheduleSave();
        showToast('رنگ همین دکمه حذف شد', 'success');
    }

    function resetActiveScreen() {
        var screen = document.querySelector('.skb-screen.active');
        if (!screen) return;
        var keys = Array.from(screen.querySelectorAll('.skb-key[data-kind]')).filter(function(button) {
            return !button.closest('[data-list-panel][hidden]');
        });
        if (!keys.length) return;
        if (!confirm('رنگ همه دکمه‌های نمایش‌داده‌شده در این بخش حذف شود؟')) return;

        keys.forEach(clearButtonColor);

        refreshAll();
        scheduleSave();
        showToast('رنگ همه دکمه‌های این بخش حذف شد', 'success');
    }

    document.addEventListener('DOMContentLoaded', function() {
        styles.config_items = styles.config_items || {};
        currentEl.textContent = '';
        refreshAll();
        updateCount();
        document.getElementById('skb-reset-button').addEventListener('click', resetEditingButton);
        document.querySelectorAll('[data-gateway-step]').forEach(function(button) {
            button.addEventListener('click', function() {
                activateGatewaySection(gatewaySectionIndex + Number(button.dataset.gatewayStep));
                document.getElementById('skb-screen-gateway_internals').scrollIntoView({block: 'start', behavior: 'auto'});
            });
        });
        document.querySelectorAll('[data-list-step]').forEach(function(button) {
            button.addEventListener('click', function() {
                activateListSection(listSectionIndex + Number(button.dataset.listStep));
                document.getElementById('skb-screen-lists').scrollIntoView({block: 'start', behavior: 'auto'});
            });
        });
        document.querySelectorAll('[data-catalog]').forEach(function(catalog) {
            catalog.querySelector('[data-catalog-prev]').addEventListener('click', function() {
                loadColorCatalog(catalog, Number(catalog.dataset.page || 0) - 1);
            });
            catalog.querySelector('[data-catalog-next]').addEventListener('click', function() {
                loadColorCatalog(catalog, Number(catalog.dataset.page || 0) + 1);
            });
            catalog.querySelector('[data-catalog-retry]').addEventListener('click', function() {
                loadColorCatalog(catalog, Number(catalog.dataset.retryPage || 0));
            });
        });
        document.querySelectorAll('[data-screen-step]').forEach(function(button) {
            button.addEventListener('click', function() {
                var ids = Array.from(document.querySelectorAll('.skb-screen')).map(function(screen) { return screen.dataset.screen; });
                var target = ids.indexOf(currentScreen) + Number(button.dataset.screenStep);
                if (ids[target]) {
                    activateScreen(ids[target]);
                    document.getElementById('skb-screen-' + ids[target]).scrollIntoView({block: 'start', behavior: 'smooth'});
                }
            });
        });
        document.querySelectorAll('.skb-nav-item').forEach(function(item) {
            item.addEventListener('click', function() { activateScreen(this.dataset.screen); });
        });
        if (navSelect) navSelect.addEventListener('change', function() { activateScreen(this.value); });
        document.body.appendChild(sectionMenu);
        sectionTrigger.addEventListener('click', function() {
            if (sectionMenu.hidden) openSectionMenu(false);
            else closeSectionMenu(false);
        });
        sectionTrigger.addEventListener('keydown', function(ev) {
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                ev.preventDefault();
                openSectionMenu(true);
            } else if (ev.key === 'Escape') closeSectionMenu(true);
        });
        sectionSearch.addEventListener('input', filterSections);
        sectionOptions.forEach(function(option) {
            option.addEventListener('click', function() {
                activateScreen(option.dataset.screenPick);
                closeSectionMenu(true);
            });
        });
        sectionMenu.addEventListener('keydown', function(ev) {
            if (ev.key === 'Escape') { ev.preventDefault(); closeSectionMenu(true); return; }
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(ev.key) === -1) return;
            if (ev.target === sectionSearch && ev.key !== 'ArrowDown' && ev.key !== 'ArrowUp') return;
            var visible = sectionOptions.filter(function(option) { return !option.hidden; });
            if (!visible.length) return;
            ev.preventDefault();
            var index = visible.indexOf(document.activeElement);
            if (ev.key === 'Home') index = 0;
            else if (ev.key === 'End') index = visible.length - 1;
            else index = (index + (ev.key === 'ArrowDown' ? 1 : -1) + visible.length) % visible.length;
            visible[index].focus({ preventScroll: true });
            visible[index].scrollIntoView({ block: 'nearest' });
        });
        document.addEventListener('pointerdown', function(ev) {
            if (!sectionMenu.contains(ev.target) && !sectionTrigger.contains(ev.target)) closeSectionMenu(false);
        });
        document.addEventListener('focusin', function(ev) {
            if (!sectionMenu.hidden && !sectionMenu.contains(ev.target) && !sectionTrigger.contains(ev.target)) closeSectionMenu(false);
        });
        window.addEventListener('resize', positionSectionMenu);
        document.addEventListener('scroll', positionSectionMenu, true);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', positionSectionMenu);
            window.visualViewport.addEventListener('scroll', positionSectionMenu);
        }

        document.querySelector('.skb-main').addEventListener('click', function(ev) {
            var key = ev.target.closest('.skb-key[data-kind]');
            if (!key) return;
            openEditor(key);
        });

        document.getElementById('skb-cancel').addEventListener('click', closeEditor);
        formEl.addEventListener('submit', function(ev) { ev.preventDefault(); commitEdit(); });
        modalEl.addEventListener('click', function(ev) { if (ev.target === modalEl) closeEditor(); });
        document.addEventListener('keydown', function(ev) {
            if (ev.key === 'Escape' && modalEl.classList.contains('open')) closeEditor();
        });

        var defaultsToggle = document.getElementById('skb-use-defaults');
        defaultsToggle.checked = !!useDefaults;
        defaultsToggle.addEventListener('change', function() {
            useDefaults = !!this.checked;
            defaultsChanged = true;
            refreshAll();
            scheduleSave();
            showToast(useDefaults ? 'رنگ‌های پیش‌فرض فعال شد' : 'رنگ‌های پیش‌فرض غیرفعال شد', 'success');
        });

        document.getElementById('skb-reset-screen').addEventListener('click', resetActiveScreen);

        document.addEventListener('visibilitychange', function() {
            if (hasChanges()) doSave();
        });
        window.addEventListener('beforeunload', sendBeaconSave);

        var savedScreen = '';
        try { savedScreen = localStorage.getItem('faoxima_skb_screen') || ''; } catch (e) {}
        activateScreen(savedScreen || '<?php echo $firstScreenId; ?>');

        measureTopbar();
        window.addEventListener('load', measureTopbar);
        window.addEventListener('resize', measureTopbar);
        if (typeof ResizeObserver !== 'undefined') {
            var topbar = document.querySelector('.skb-top');
            if (topbar) new ResizeObserver(measureTopbar).observe(topbar);
        }
    });

    function measureTopbar() {
        var topbar = document.querySelector('.skb-top');
        if (!topbar) return;
        document.body.style.setProperty('--skb-top-h', topbar.offsetHeight + 'px');
    }
})();
</script>
</body>
</html>
