<?php
date_default_timezone_set('Asia/Tehran');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/status_filter.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../jdf.php';

function tk_jdate($raw)
{
    $s = trim((string)$raw);
    if ($s === '') return '';
    $ts = strtotime($s);
    if ($ts === false) return $s;
    return jdate('Y/m/d H:i', $ts, '', 'Asia/Tehran', 'fa');
}

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$admin = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$admin) {
    header('Location: login.php');
    return;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$_csrf = $_SESSION['csrf_token'];

$setting = select("setting", "*", null, null);

function tk_media($text)
{
    $t = (string)$text;
    $media = [];
    if (preg_match_all('/\[\[(photo|video):([^\]]+)\]\]/', $t, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            if (count($media) >= 5) break;
            $media[] = ['type' => $m[1], 'file_id' => $m[2]];
            $t = str_replace($m[0], '', $t);
        }
    }
    $t = preg_replace('/\[\[subj:[^\]]*\]\]/u', '', $t);
    return ['media' => $media, 'body' => trim((string)$t)];
}
function tk_subject($text)
{
    if (preg_match('/\[\[subj:([^\]]*)\]\]/u', (string)$text, $m)) {
        return trim($m[1]);
    }
    return '';
}

function tk_status_badge($s)
{
    if ($s === 'close') return ['بسته', 'badge-block'];
    if ($s === 'Unseen' || $s === 'Customerresponse') return ['پاسخ‌نداده', 'badge-warning'];
    if ($s === 'Answered') return ['پاسخ‌داده', 'badge-active'];
    return ['باز', 'badge-gray'];
}

function tk_delete_trackings(PDO $pdo, array $trackings): int
{
    $deleted = 0;
    $trackings = array_values(array_unique(array_map('strval', $trackings)));
    foreach (array_chunk($trackings, 500) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '?'));
        $idStmt = $pdo->prepare("SELECT id, Tracking FROM support_message WHERE Tracking IN ($ph)");
        $idStmt->execute($chunk);
        $rows = $idStmt->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map(function ($r) { return (int)$r['id']; }, $rows);
        if ($ids) {
            $idPh = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM support_message_reaction WHERE message_id IN ($idPh)")->execute($ids);
        }
        $del = $pdo->prepare("DELETE FROM support_message WHERE Tracking IN ($ph)");
        $del->execute($chunk);
        $deleted += count(array_unique(array_column($rows, 'Tracking')));
    }
    return $deleted;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'mark_ticket_seen') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM support_message")->fetchColumn();
        $st = $pdo->prepare("UPDATE admin SET last_ticket_seen = ? WHERE username = ?");
        $st->execute([$maxId, $_SESSION['user'] ?? '']);
        echo json_encode(['ok' => true]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false]);
    }
    exit;
}

$TK_REACTION_EMOJI = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'react') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['_csrf'] ?? ''))) {
            echo json_encode(['ok' => false, 'error' => 'csrf']); exit;
        }
        $mid = (int)($_POST['message_id'] ?? 0);
        $emoji = trim((string)($_POST['emoji'] ?? ''));
        if ($mid <= 0 || ($emoji !== '' && !in_array($emoji, $TK_REACTION_EMOJI, true))) {
            echo json_encode(['ok' => false, 'error' => 'invalid']); exit;
        }
        $msgRow = $pdo->prepare("SELECT result FROM support_message WHERE id = ?");
        $msgRow->execute([$mid]);
        $msgResult = $msgRow->fetchColumn();
        if ($msgResult === false || (string)$msgResult === 'admin') {
            echo json_encode(['ok' => false, 'error' => 'invalid']); exit;
        }
        $actorId = 'admin:' . (string)($_SESSION['user'] ?? '');
        if ($emoji === '') {
            $pdo->prepare("DELETE FROM support_message_reaction WHERE message_id = ? AND actor_id = ?")->execute([$mid, $actorId]);
        } else {
            $pdo->prepare("INSERT INTO support_message_reaction (message_id, actor, actor_id, emoji, time)
                            VALUES (?, 'admin', ?, ?, ?)
                            ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), time = VALUES(time)")
                ->execute([$mid, $actorId, $emoji, date('Y/m/d H:i:s')]);
        }
        $rows = $pdo->prepare("SELECT actor, actor_id, emoji FROM support_message_reaction WHERE message_id = ?");
        $rows->execute([$mid]);
        $out = [];
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[] = ['emoji' => $r['emoji'], 'actor' => $r['actor'], 'mine' => $r['actor_id'] === $actorId];
        }
        echo json_encode(['ok' => true, 'reactions' => $out]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'server']);
    }
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'bulk_delete') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['_csrf'] ?? ''))) {
            echo json_encode(['ok' => false, 'error' => 'csrf']); exit;
        }
        $raw = json_decode((string)($_POST['trackings'] ?? '[]'), true);
        if (!is_array($raw)) { echo json_encode(['ok' => false, 'error' => 'invalid']); exit; }
        $trackings = [];
        foreach ($raw as $t) {
            $t = (string)$t;
            if ($t !== '' && preg_match('/^[a-f0-9]+$/i', $t)) $trackings[] = $t;
        }
        $trackings = array_values(array_unique($trackings));
        if (count($trackings) === 0) { echo json_encode(['ok' => false, 'error' => 'invalid']); exit; }
        if (count($trackings) > 500) $trackings = array_slice($trackings, 0, 500);

        tk_delete_trackings($pdo, $trackings);
        echo json_encode(['ok' => true, 'deleted' => count($trackings)]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'server']);
    }
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'poll_state') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $T = trim((string)($_GET['t'] ?? ''));
        if ($T === '') { echo json_encode(['ok' => false]); exit; }
        $rows = $pdo->prepare("SELECT id, result, seen_by_user FROM support_message WHERE Tracking = ?");
        $rows->execute([$T]);
        $thread = $rows->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map(function ($r) { return (int)$r['id']; }, $thread);
        $reactionsByMsg = [];
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $rq = $pdo->prepare("SELECT message_id, actor, actor_id, emoji FROM support_message_reaction WHERE message_id IN ($ph)");
            $rq->execute($ids);
            $adminActorId = 'admin:' . (string)($_SESSION['user'] ?? '');
            foreach ($rq->fetchAll(PDO::FETCH_ASSOC) as $rr) {
                $mid = (int)$rr['message_id'];
                if (!isset($reactionsByMsg[$mid])) $reactionsByMsg[$mid] = [];
                $reactionsByMsg[$mid][] = ['emoji' => $rr['emoji'], 'actor' => $rr['actor'], 'mine' => $rr['actor_id'] === $adminActorId];
            }
        }
        $out = [];
        foreach ($thread as $r) {
            $mid = (int)$r['id'];
            $side = ($r['result'] === 'admin') ? 'admin' : 'user';
            $out[] = [
                'id'        => $mid,
                'seen'      => ($side === 'admin') ? ((int)($r['seen_by_user'] ?? 0) === 1) : true,
                'reactions' => $reactionsByMsg[$mid] ?? [],
            ];
        }
        echo json_encode(['ok' => true, 'messages' => $out]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false]);
    }
    exit;
}

if (isset($_GET['media']) && $_GET['media'] !== '') {
    $mid = (int)$_GET['media'];
    $st = $pdo->prepare("SELECT text FROM support_message WHERE id = ? LIMIT 1");
    $st->execute([$mid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $m = tk_media($row['text'] ?? '');
    $idx = isset($_GET['i']) ? max(0, (int)$_GET['i']) : 0;
    $item = $m['media'][$idx] ?? null;
    if (!$item) { http_response_code(404); exit; }
    $f = telegram('getFile', ['file_id' => $item['file_id']]);
    $path = $f['result']['file_path'] ?? '';
    if ($path === '') { http_response_code(404); exit; }
    $bin = @file_get_contents("https://api.telegram.org/file/bot{$APIKEY}/{$path}");
    if ($bin === false) { http_response_code(502); exit; }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $ct = $item['type'] === 'video' ? 'video/mp4' : ($ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg'));
    header('Content-Type: ' . $ct);
    header('Cache-Control: private, max-age=3600');
    echo $bin;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] !== 'admin_create') {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['_csrf'] ?? ''))) {
        header("Location: tickets.php");
        exit;
    }
    $act = (string)$_POST['_action'];
    $T = trim((string)($_POST['t'] ?? ''));
    if ($T === '') { header("Location: tickets.php"); exit; }
    $tk = select("support_message", "*", "Tracking", $T);
    if (!is_array($tk)) { header("Location: tickets.php"); exit; }
    $iduser = (string)$tk['iduser'];
    $reportTopic = select("topicid", "idreport", "report", "otherservice", "select")['idreport'] ?? null;

    if ($act === 'reply') {
        $text = trim((string)($_POST['text'] ?? ''));
        $uploads = [];
        if (isset($_FILES['media']) && is_array($_FILES['media']['tmp_name'] ?? null)) {
            $F = $_FILES['media'];
            $n = count($F['tmp_name']);
            for ($i = 0; $i < $n && count($uploads) < 5; $i++) {
                if ((int)($F['error'][$i] ?? 1) !== 0) continue;
                $tmp = (string)($F['tmp_name'][$i] ?? '');
                if ($tmp === '' || !is_uploaded_file($tmp)) continue;
                $mime = function_exists('mime_content_type') ? (string)(mime_content_type($tmp) ?: '') : (string)($F['type'][$i] ?? '');
                $uploads[] = ['tmp' => $tmp, 'mime' => $mime, 'name' => (string)($F['name'][$i] ?? 'file'), 'video' => stripos($mime, 'video') === 0];
            }
        }
        $mediaTokens = [];
        if (count($uploads) === 1) {
            $u = $uploads[0];
            $cf = new CURLFile($u['tmp'], $u['mime'] !== '' ? $u['mime'] : ($u['video'] ? 'video/mp4' : 'image/jpeg'), $u['name']);
            if ($u['video']) {
                $res = telegram('sendVideo', ['chat_id' => $iduser, 'video' => $cf, 'caption' => $text, 'parse_mode' => 'HTML']);
                $fid = $res['result']['video']['file_id'] ?? '';
                if ($fid !== '') $mediaTokens[] = "[[video:$fid]]";
            } else {
                $res = telegram('sendPhoto', ['chat_id' => $iduser, 'photo' => $cf, 'caption' => $text, 'parse_mode' => 'HTML']);
                $ph = $res['result']['photo'] ?? [];
                $fid = (is_array($ph) && $ph) ? (end($ph)['file_id'] ?? '') : '';
                if ($fid !== '') $mediaTokens[] = "[[photo:$fid]]";
            }
        } elseif (count($uploads) >= 2) {
            $mediaArr = [];
            $filesPayload = [];
            foreach ($uploads as $i => $u) {
                $key = 'tkf' . $i;
                $type = $u['video'] ? 'video' : 'photo';
                $entry = ['type' => $type, 'media' => 'attach://' . $key];
                if ($i === 0 && $text !== '') { $entry['caption'] = $text; $entry['parse_mode'] = 'HTML'; }
                $mediaArr[] = $entry;
                $filesPayload[$key] = new CURLFile($u['tmp'], $u['mime'] !== '' ? $u['mime'] : ($u['video'] ? 'video/mp4' : 'image/jpeg'), $u['name']);
            }
            $res = telegram('sendMediaGroup', array_merge(['chat_id' => $iduser, 'media' => $mediaArr], $filesPayload));
            foreach (($res['result'] ?? []) as $r) {
                if (isset($r['photo']) && is_array($r['photo'])) {
                    $fid = end($r['photo'])['file_id'] ?? '';
                    if ($fid !== '') $mediaTokens[] = "[[photo:$fid]]";
                } elseif (isset($r['video']['file_id'])) {
                    $mediaTokens[] = "[[video:" . $r['video']['file_id'] . "]]";
                }
            }
        }
        $miniappOnly = (string)($setting['miniapp_ticket_mode'] ?? '0') === '1';
        if ($miniappOnly) {
            $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
            $replyKb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => faoxima_textbot_get('dyn_panel_tickets_continue_in_miniapp_btn', '🚀 ادامه گفتگو در مینی‌اپ'), 'web_app' => ['url' => 'https://' . $host . '/app/#/tickets/' . $T]]]]], JSON_UNESCAPED_UNICODE) : null;
            $rxMiniAppNote = faoxima_textbot_get('dyn_panel_tickets_miniapp_only_note', "\n\n⚠️ ادامه گفتگو فقط از طریق مینی‌اپ امکان‌پذیر است.");
        } else {
            $replyKb = json_encode(['inline_keyboard' => [[['text' => faoxima_textbot_get('dyn_panel_tickets_reply_btn', '💬 پاسخ'), 'callback_data' => 'ticketreply_' . $T]]]], JSON_UNESCAPED_UNICODE);
            $rxMiniAppNote = "";
        }
        if (count($mediaTokens) > 0) {
            sendmessage($iduser, faoxima_render_text(faoxima_textbot_get('dyn_panel_tickets_support_replied_tpl', '📩 پشتیبانی به تیکت شما (کد <code>{tracking}</code>) پاسخ داد.{miniapp_note}'), ['tracking' => $T, 'miniapp_note' => $rxMiniAppNote]), $replyKb, 'HTML');
        } else {
            if ($text === '') { header("Location: tickets.php?t=" . urlencode($T)); exit; }
            sendmessage($iduser, faoxima_textbot_get('dyn_panel_tickets_support_reply_prefix', '📩 پاسخ پشتیبانی:') . "\n" . $text . $rxMiniAppNote, $replyKb, 'HTML');
        }
        $body = trim($text . (count($mediaTokens) ? "\n" . implode(' ', $mediaTokens) : ''));
        $time = date('Y/m/d H:i:s');
        $replyToId = (int)($_POST['reply_to_id'] ?? 0);
        if ($replyToId > 0) {
            $rtChk = $pdo->prepare("SELECT id FROM support_message WHERE id = ? AND Tracking = ?");
            $rtChk->execute([$replyToId, $T]);
            if (!$rtChk->fetchColumn()) $replyToId = 0;
        }
        $ins = $pdo->prepare("INSERT INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status,reply_to_id) VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->execute([$T, $tk['idsupport'], $iduser, $tk['name_departman'], $body, 'admin', $time, 'Answered', $replyToId > 0 ? $replyToId : null]);
        update("support_message", "status", "Answered", "Tracking", $T);
        header("Location: tickets.php?t=" . urlencode($T));
        exit;
    }
    if ($act === 'close') {
        update("support_message", "status", "close", "Tracking", $T);
        sendmessage($iduser, faoxima_render_text(faoxima_textbot_get('dyn_panel_tickets_closed_by_support_tpl', '🔒 تیکت شما (کد <code>{tracking}</code>) توسط پشتیبانی بسته شد.'), ['tracking' => $T]), null, 'HTML');
        header("Location: tickets.php?t=" . urlencode($T));
        exit;
    }
    if ($act === 'reopen') {
        update("support_message", "status", "Answered", "Tracking", $T);
        if ((string)($setting['miniapp_ticket_mode'] ?? '0') === '1') {
            $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
            $reopenKb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => faoxima_textbot_get('dyn_panel_tickets_continue_in_miniapp_btn', '🚀 ادامه گفتگو در مینی‌اپ'), 'web_app' => ['url' => 'https://' . $host . '/app/#/tickets/' . $T]]]]], JSON_UNESCAPED_UNICODE) : null;
            sendmessage($iduser, faoxima_render_text(faoxima_textbot_get('dyn_panel_tickets_reopened_miniapp_tpl', "🔓 تیکت شما (کد <code>{tracking}</code>) دوباره باز شد.\n\n⚠️ ادامه گفتگو فقط از طریق مینی‌اپ امکان‌پذیر است."), ['tracking' => $T]), $reopenKb, 'HTML');
        } else {
            $reopenKb = json_encode(['inline_keyboard' => [[['text' => faoxima_textbot_get('dyn_panel_tickets_reply_btn', '💬 پاسخ'), 'callback_data' => 'ticketreply_' . $T]]]], JSON_UNESCAPED_UNICODE);
            sendmessage($iduser, faoxima_render_text(faoxima_textbot_get('dyn_panel_tickets_reopened_tpl', '🔓 تیکت شما (کد <code>{tracking}</code>) دوباره باز شد. می‌توانید پیام ارسال کنید.'), ['tracking' => $T]), $reopenKb, 'HTML');
        }
        header("Location: tickets.php?t=" . urlencode($T));
        exit;
    }
    if ($act === 'delete') {
        $idStmt = $pdo->prepare("SELECT id FROM support_message WHERE Tracking = ?");
        $idStmt->execute([$T]);
        $ids = array_map(function ($r) { return (int)$r['id']; }, $idStmt->fetchAll(PDO::FETCH_ASSOC));
        if ($ids) {
            $idPh = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM support_message_reaction WHERE message_id IN ($idPh)")->execute($ids);
        }
        $pdo->prepare("DELETE FROM support_message WHERE Tracking = ?")->execute([$T]);
        header("Location: tickets.php");
        exit;
    }
    header("Location: tickets.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'admin_create') {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)($_POST['_csrf'] ?? ''))) {
        header("Location: tickets.php");
        exit;
    }
    $targetUserId = trim((string)($_POST['target_user_id'] ?? ''));
    $department = trim((string)($_POST['department'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $text = trim((string)($_POST['text'] ?? ''));

    if ($targetUserId === '' || !ctype_digit($targetUserId)) {
        header("Location: tickets.php?admin_create_error=" . urlencode('شناسه کاربر نامعتبر است.'));
        exit;
    }
    $targetUser = select("user", "*", "id", $targetUserId);
    if (!is_array($targetUser)) {
        header("Location: tickets.php?admin_create_error=" . urlencode('کاربری با این شناسه یافت نشد.'));
        exit;
    }
    $deptRow = select("departman", "*", "name_departman", $department);
    if (!is_array($deptRow)) {
        header("Location: tickets.php?admin_create_error=" . urlencode('دپارتمان انتخاب‌شده نامعتبر است.'));
        exit;
    }
    if ($subject === '') {
        header("Location: tickets.php?admin_create_error=" . urlencode('موضوع تیکت را وارد کنید.'));
        exit;
    }

    $uploads = [];
    if (isset($_FILES['media']) && is_array($_FILES['media']['tmp_name'] ?? null)) {
        $F = $_FILES['media'];
        $n = count($F['tmp_name']);
        for ($i = 0; $i < $n && count($uploads) < 5; $i++) {
            if ((int)($F['error'][$i] ?? 1) !== 0) continue;
            $tmp = (string)($F['tmp_name'][$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) continue;
            $mime = function_exists('mime_content_type') ? (string)(mime_content_type($tmp) ?: '') : (string)($F['type'][$i] ?? '');
            $uploads[] = ['tmp' => $tmp, 'mime' => $mime, 'name' => (string)($F['name'][$i] ?? 'file'), 'video' => stripos($mime, 'video') === 0];
        }
    }
    $mediaTokens = [];
    if (count($uploads) === 1) {
        $u = $uploads[0];
        $cf = new CURLFile($u['tmp'], $u['mime'] !== '' ? $u['mime'] : ($u['video'] ? 'video/mp4' : 'image/jpeg'), $u['name']);
        if ($u['video']) {
            $res = telegram('sendVideo', ['chat_id' => $targetUserId, 'video' => $cf, 'caption' => $text, 'parse_mode' => 'HTML']);
            $fid = $res['result']['video']['file_id'] ?? '';
            if ($fid !== '') $mediaTokens[] = "[[video:$fid]]";
        } else {
            $res = telegram('sendPhoto', ['chat_id' => $targetUserId, 'photo' => $cf, 'caption' => $text, 'parse_mode' => 'HTML']);
            $ph = $res['result']['photo'] ?? [];
            $fid = (is_array($ph) && $ph) ? (end($ph)['file_id'] ?? '') : '';
            if ($fid !== '') $mediaTokens[] = "[[photo:$fid]]";
        }
    } elseif (count($uploads) >= 2) {
        $mediaArr = [];
        $filesPayload = [];
        foreach ($uploads as $i => $u) {
            $key = 'tkf' . $i;
            $type = $u['video'] ? 'video' : 'photo';
            $entry = ['type' => $type, 'media' => 'attach://' . $key];
            if ($i === 0 && $text !== '') { $entry['caption'] = $text; $entry['parse_mode'] = 'HTML'; }
            $mediaArr[] = $entry;
            $filesPayload[$key] = new CURLFile($u['tmp'], $u['mime'] !== '' ? $u['mime'] : ($u['video'] ? 'video/mp4' : 'image/jpeg'), $u['name']);
        }
        $res = telegram('sendMediaGroup', array_merge(['chat_id' => $targetUserId, 'media' => $mediaArr], $filesPayload));
        foreach (($res['result'] ?? []) as $r) {
            if (isset($r['photo']) && is_array($r['photo'])) {
                $fid = end($r['photo'])['file_id'] ?? '';
                if ($fid !== '') $mediaTokens[] = "[[photo:$fid]]";
            } elseif (isset($r['video']['file_id'])) {
                $mediaTokens[] = "[[video:" . $r['video']['file_id'] . "]]";
            }
        }
    }

    if ($text === '' && count($mediaTokens) === 0) {
        header("Location: tickets.php?admin_create_error=" . urlencode('متن پیام یا تصویر را وارد کنید.'));
        exit;
    }

    $T = bin2hex(random_bytes(4));
    $mediaBlock = count($mediaTokens) ? "\n" . implode(' ', $mediaTokens) : '';
    $body = trim("[[subj:$subject]]" . $mediaBlock . ($text !== '' ? "\n" . $text : ''));
    $time = date('Y/m/d H:i:s');
    $ins = $pdo->prepare("INSERT INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status,reply_to_id) VALUES (?,?,?,?,?,?,?,?,?)");
    $ins->execute([$T, (string)$deptRow['idsupport'], $targetUserId, (string)$deptRow['name_departman'], $body, 'admin', $time, 'Answered', null]);

    if ((string)($setting['miniapp_ticket_mode'] ?? '0') === '1') {
        $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
        $kb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => faoxima_textbot_get('dyn_panel_tickets_continue_in_miniapp_btn', '🚀 ادامه گفتگو در مینی‌اپ'), 'web_app' => ['url' => 'https://' . $host . '/app/#/tickets/' . $T]]]]], JSON_UNESCAPED_UNICODE) : null;
        $note = faoxima_textbot_get('dyn_panel_tickets_miniapp_only_note', "\n\n⚠️ ادامه گفتگو فقط از طریق مینی‌اپ امکان‌پذیر است.");
    } else {
        $kb = json_encode(['inline_keyboard' => [[['text' => faoxima_textbot_get('dyn_panel_tickets_my_tickets_btn', '🎫 تیکت‌های من'), 'callback_data' => 'supporttickets']]]], JSON_UNESCAPED_UNICODE);
        $note = "";
    }
    $notifyBase = faoxima_render_text(faoxima_textbot_get('dyn_panel_tickets_new_ticket_created_tpl', '🎫 یک تیکت جدید توسط پشتیبانی برای شما ثبت شد (کد <code>{tracking}</code>).'), ['tracking' => $T]);
    if ($text !== '' && count($mediaTokens) === 0) { $notifyBase .= "\n" . $text; }
    sendmessage($targetUserId, $notifyBase . $note, $kb, 'HTML');

    header("Location: tickets.php?t=" . urlencode($T));
    exit;
}

$viewT = trim((string)($_GET['t'] ?? ''));
$thread = [];
$ticketRow = null;
$tkReactionsByMsg = [];
$tkBodyById = [];
if ($viewT !== '') {
    $st = $pdo->prepare("SELECT * FROM support_message WHERE Tracking = ? ORDER BY id ASC");
    $st->execute([$viewT]);
    $thread = $st->fetchAll(PDO::FETCH_ASSOC);
    $ticketRow = $thread[0] ?? null;
    if (!$ticketRow) { $viewT = ''; }
}
if ($viewT !== '' && $thread) {
    $pdo->prepare("UPDATE support_message SET seen_by_admin = 1 WHERE Tracking = ? AND result = 'user' AND seen_by_admin = 0")->execute([$viewT]);
    $ids = array_map(function ($r) { return (int)$r['id']; }, $thread);
    foreach ($thread as $r) { $tkBodyById[(int)$r['id']] = tk_media((string)$r['text'])['body']; }
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rq = $pdo->prepare("SELECT message_id, actor, actor_id, emoji FROM support_message_reaction WHERE message_id IN ($ph)");
        $rq->execute($ids);
        $adminActorId = 'admin:' . (string)($_SESSION['user'] ?? '');
        foreach ($rq->fetchAll(PDO::FETCH_ASSOC) as $rr) {
            $mid = (int)$rr['message_id'];
            if (!isset($tkReactionsByMsg[$mid])) $tkReactionsByMsg[$mid] = [];
            $tkReactionsByMsg[$mid][] = ['emoji' => $rr['emoji'], 'actor' => $rr['actor'], 'mine' => $rr['actor_id'] === $adminActorId];
        }
    }
}
$ticketSubject = $ticketRow ? tk_subject((string)$ticketRow['text']) : '';
$ticketSubjects = [];
$departments = [];
$adminCreateError = trim((string)($_GET['admin_create_error'] ?? ''));
if ($viewT === '') {
    $departments = $pdo->query("SELECT id, name_departman FROM departman ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    try {
        $__mark = $pdo->prepare("UPDATE admin SET last_ticket_seen = (SELECT COALESCE(MAX(id),0) FROM support_message) WHERE username = ?");
        $__mark->execute([$_SESSION['user'] ?? '']);
    } catch (\Throwable $e) {}
    $tkQ = fx_search_current();
    $tkWhereSql = '1=1';
    $tkParams = [];
    if ($tkQ !== '') {
        $tkLike = '%' . $tkQ . '%';
        $tkWhereSql .= ' AND Tracking IN (SELECT DISTINCT Tracking FROM support_message WHERE Tracking LIKE :tq1 OR iduser LIKE :tq2 OR text LIKE :tq3)';
        $tkParams[':tq1'] = $tkLike;
        $tkParams[':tq2'] = $tkLike;
        $tkParams[':tq3'] = $tkLike;
    }
    $tkDf = fx_date_filter_resolve();
    if ($tkDf['active']) {
        $tkDateSql = fx_date_filter_sql_mixed_named('tk_last', $tkDf['from'], $tkDf['to'], $tkParams, 'tkd');
        $tkWhereSql .= ' AND Tracking IN (SELECT Tracking FROM (SELECT Tracking, MAX(time) tk_last FROM support_message GROUP BY Tracking) tkl WHERE 1=1' . $tkDateSql . ')';
    }
    $tkStatusOptions = [
        'unanswered' => 'پاسخ‌نداده',
        'answered'   => 'پاسخ‌داده',
        'open'       => 'باز',
        'close'      => 'بسته',
    ];
    $tkStatusSql = [
        'unanswered' => "IN ('Unseen','Customerresponse')",
        'answered'   => "= 'Answered'",
        'open'       => "NOT IN ('close','Unseen','Customerresponse','Answered')",
        'close'      => "= 'close'",
    ];
    $tkStatus = fx_status_filter_current();
    $tkStatusActive = $tkStatus !== '' && isset($tkStatusOptions[$tkStatus]);
    if ($tkStatusActive) {
        $tkWhereSql .= ' AND Tracking IN (SELECT Tracking FROM support_message GROUP BY Tracking HAVING MAX(status) ' . $tkStatusSql[$tkStatus] . ')';
    }

    $tkFilterActive = $tkQ !== '' || $tkDf['active'] || $tkStatusActive;
    $tkDateKeep = fx_filter_delete_date_params('', $tkDf['active']);
    $tkFilterParams = array_merge(['q' => $tkQ !== '' ? $tkQ : null, 'status' => $tkStatusActive ? $tkStatus : null], $tkDateKeep);
    $tkFilterCriteria = fx_filter_delete_criteria($tkStatusActive ? $tkStatusOptions[$tkStatus] : '', $tkDf, $tkQ);
    if (fx_filter_delete_requested()) {
        $fdMatched = 0;
        $fdDeleted = 0;
        if ($tkFilterActive) {
            $fdTrackings = fx_filter_delete_collect($pdo, 'support_message', 'Tracking', $tkWhereSql, $tkParams);
            $fdMatched = count($fdTrackings);
            try {
                $fdDeleted = tk_delete_trackings($pdo, $fdTrackings);
            } catch (\Throwable $e) {
                $fdDeleted = 0;
            }
        }
        fx_filter_delete_redirect('tickets.php', $tkFilterParams, $fdMatched, $fdDeleted);
    }

    $tkPg = fx_paginate($pdo, "SELECT COUNT(*) FROM (SELECT 1 FROM support_message WHERE $tkWhereSql GROUP BY Tracking, iduser, name_departman) t", $tkParams, 5);
    $tkStmt = $pdo->prepare("SELECT Tracking, iduser, name_departman, MAX(id) last_id, MAX(time) last_time, MAX(status) status, COUNT(*) cnt FROM support_message WHERE $tkWhereSql GROUP BY Tracking, iduser, name_departman ORDER BY last_id DESC LIMIT :perPage OFFSET :offset");
    foreach ($tkParams as $k => $v) $tkStmt->bindValue($k, $v, PDO::PARAM_STR);
    $tkStmt->bindValue(':perPage', $tkPg['perPage'], PDO::PARAM_INT);
    $tkStmt->bindValue(':offset', $tkPg['offset'], PDO::PARAM_INT);
    $tkStmt->execute();
    $tickets = $tkStmt->fetchAll(PDO::FETCH_ASSOC);

    $tkTrackings = array_column($tickets, 'Tracking');
    if ($tkTrackings) {
        $tkPh = implode(',', array_fill(0, count($tkTrackings), '?'));
        $tkSubjStmt = $pdo->prepare("SELECT s.Tracking, s.text FROM support_message s INNER JOIN (SELECT Tracking, MIN(id) mid FROM support_message WHERE Tracking IN ($tkPh) GROUP BY Tracking) f ON s.id = f.mid");
        $tkSubjStmt->execute($tkTrackings);
        foreach ($tkSubjStmt->fetchAll(PDO::FETCH_ASSOC) as $fr) {
            $ticketSubjects[$fr['Tracking']] = tk_subject((string)$fr['text']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>تیکت‌ها | ربات فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat50">
    <script src="js/theme.js?v=flat50" defer></script>
    <style>
        .tk-chat { display:flex; flex-direction:column; gap:12px; max-height:60vh; overflow-y:auto; padding:6px 2px; }
        .tk-row { display:flex; }
        .tk-row.admin { justify-content:flex-start; }
        .tk-row.user { justify-content:flex-end; }
        .tk-bubble { max-width:100%; box-sizing:border-box; border-radius:14px; padding:10px 13px; font-size:13.5px; line-height:1.9; word-break:break-word; border:1px solid var(--border-mid); }
        .tk-row.admin .tk-bubble { background:var(--accent-soft); border-color:var(--accent-mid); border-top-left-radius:4px; }
        .tk-row.user .tk-bubble { background:var(--surface-1); border-top-right-radius:4px; }
        .tk-who { font-size:11.5px; font-weight:700; opacity:.8; margin-bottom:4px; }
        .tk-row.admin .tk-who { color:var(--accent); }
        .tk-time { font-size:10.5px; color:var(--text-muted); margin-top:6px; direction:ltr; }
        .tk-media-grid { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; max-width:100%; }
        .tk-thumb { width:92px; height:92px; min-width:92px; max-width:92px; min-height:92px; max-height:92px; border-radius:10px; overflow:hidden; cursor:pointer; border:1px solid var(--border-mid); background:var(--surface-2); position:relative; flex:0 0 92px; box-sizing:border-box; transition:transform .12s; }
        .tk-thumb:hover { transform:scale(1.04); }
        .tk-thumb img, .tk-thumb video { width:100%; height:100%; object-fit:cover; display:block; }
        .tk-thumb .tk-play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:24px; color:#fff; background:rgba(0,0,0,.30); }
        .tk-reply { display:flex; flex-direction:column; gap:10px; margin-top:16px; }
        .tk-reply textarea { width:100%; min-height:74px; resize:vertical; }
        .tk-actions { display:flex; gap:8px; flex-wrap:wrap; }

        .tk-compose-bar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:12px; }
        .tk-compose-hint { display:flex; align-items:center; gap:7px; color:var(--text-muted); font-size:12px; line-height:1.5; }
        .tk-compose-hint .svg-icon { width:15px; height:15px; flex-shrink:0; opacity:.75; }
        .tk-compose-btns { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-inline-start:auto; }

        .tk-danger-zone { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:18px; padding:14px 16px; border:1px solid var(--border-soft); border-radius:var(--radius); background:var(--surface-2); }
        .tk-danger-zone__label { display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:12.5px; font-weight:700; }
        .tk-danger-zone__label .svg-icon { width:16px; height:16px; flex-shrink:0; opacity:.8; }
        .tk-danger-zone__btns { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-inline-start:auto; }
        .tk-danger-zone__btns form { display:contents; }
        .tk-btn-delete { opacity:.85; }
        .tk-btn-delete:hover { opacity:1; }

        @media (max-width:600px) {
            .tk-compose-bar, .tk-danger-zone { flex-direction:column; align-items:stretch; }
            .tk-compose-btns, .tk-danger-zone__btns { margin-inline-start:0; }
            .tk-compose-btns .btn, .tk-danger-zone__btns .btn { flex:1 1 auto; justify-content:center; }
        }
        .tkup-wrap { display:flex; flex-direction:column; gap:8px; }
        .tkup-slot { display:flex; flex-direction:column; gap:8px; }
        .tkup-slot [hidden] { display:none !important; }
        .tkup-input { position:absolute; width:1px; height:1px; opacity:0; overflow:hidden; }
        .tkup-drop { display:flex; align-items:center; justify-content:center; gap:10px; min-height:64px; padding:14px; border:1.5px dashed var(--border-mid); border-radius:var(--radius); background:var(--surface-2); color:var(--text-muted); cursor:pointer; font-size:13px; font-weight:600; transition:.18s; user-select:none; }
        .tkup-dropwrap { display:flex; align-items:stretch; gap:8px; }
        .tkup-dropwrap .tkup-drop { flex:1 1 auto; min-width:0; }
        .tkup-slot-rm { flex:0 0 auto; display:inline-flex; align-items:center; justify-content:center; width:44px; border:1.5px dashed var(--border-mid); border-radius:var(--radius); background:var(--surface-2); color:var(--text-muted); cursor:pointer; transition:.18s; }
        .tkup-slot-rm:hover { border-color:var(--color-danger); color:var(--color-danger); background:var(--color-danger-soft); border-style:solid; }
        .tkup-slot-rm .svg-icon { width:17px; height:17px; }
        .tkup-slot-rm[hidden] { display:none !important; }
        .tkup-drop .svg-icon { width:20px; height:20px; margin-inline-start:0; }
        .tkup-drop .svg-icon + * { margin-inline-start:0; }
        .tkup-drop:hover { border-color:var(--accent); color:var(--accent); background:var(--accent-soft); }
        .tkup-drop:focus-within { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
        .tkup-drop:active { transform:scale(.99); }
        .tkup-chip { display:flex; align-items:center; gap:10px; padding:8px 10px; background:var(--surface-2); border:1px solid var(--border-soft); border-radius:var(--radius); }
        .tkup-thumb { display:block; width:44px; height:44px; min-width:44px; border-radius:10px; overflow:hidden; border:1px solid var(--border-mid); background:var(--surface-3); }
        .tkup-thumb img, .tkup-thumb video { width:100%; height:100%; object-fit:cover; display:block; }
        .tkup-meta { flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
        .tkup-name { font-size:12.5px; direction:ltr; unicode-bidi:isolate; text-align:right; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .tkup-size { font-size:11px; color:var(--text-dim); direction:ltr; text-align:right; }
        .tkup-rm { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; min-width:28px; padding:0; border:none; border-radius:999px; background:transparent; color:var(--text-muted); cursor:pointer; transition:.14s; }
        .tkup-rm .svg-icon { width:14px; height:14px; margin-inline-start:0; }
        .tkup-rm:hover { color:var(--color-danger); background:var(--color-danger-soft); }
        .tkup-error { font-size:11.5px; color:var(--color-danger); background:var(--color-danger-soft); border-radius:8px; padding:6px 10px; }
        .tkup-progress { height:4px; background:var(--surface-3); border-radius:999px; overflow:hidden; margin-top:8px; }
        .tkup-progress span { display:block; height:100%; width:40%; background:var(--accent); border-radius:999px; animation:tkup-slide 1.1s ease-in-out infinite; }
        @keyframes tkup-slide { 0% { transform:translateX(260%); } 100% { transform:translateX(-260%); } }
        @media (prefers-reduced-motion: reduce) { .tkup-progress span { animation:none; width:100%; } }
        @media (max-width:600px){ .tkup-drop { min-height:56px; } }
        .tk-lightbox { position:fixed; inset:0; background:rgba(0,0,0,.88); display:none; align-items:center; justify-content:center; z-index:9999; padding:18px; }
        .tk-lightbox.open { display:flex; }
        .tk-lightbox img, .tk-lightbox video { max-width:96vw; max-height:92vh; border-radius:10px; }
        .tk-lightbox__close { position:absolute; top:12px; inset-inline-end:16px; color:#fff; font-size:32px; cursor:pointer; line-height:1; }
        @media (max-width:600px){ .tk-bubble{ max-width:90%; } .tk-thumb{ width:78px; height:78px; } }

        .tk-bubble-wrap { position:relative; max-width:78%; }
        .tk-quote { border-inline-start:3px solid var(--accent-mid, #9b8cff); background:rgba(127,127,127,.10); border-radius:6px; padding:5px 8px; font-size:11.5px; color:var(--text-muted); margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .tk-receipt { display:inline-flex; align-items:center; margin-inline-start:4px; color:var(--text-muted); vertical-align:middle; }
        .tk-receipt .svg-icon { width:12px; height:12px; }
        .tk-receipt .tk-receipt-2 { margin-inline-start:-6px; }
        .tk-receipt.is-seen { color:var(--accent, #6c5ce7); }
        .tk-reactions { display:flex; flex-wrap:wrap; gap:4px; margin-top:4px; }
        .tk-reaction-chip { display:inline-flex; align-items:center; font-size:12px; background:var(--surface-2); border:1px solid var(--border-mid); border-radius:999px; padding:1px 7px; line-height:1.6; }
        .tk-reaction-chip.is-mine { border-color:var(--accent-mid,#9b8cff); background:var(--accent-soft); }
        .tk-msg-actions { display:flex; gap:4px; margin-top:4px; opacity:0; pointer-events:none; transition:opacity .14s ease; }
        .tk-row:hover .tk-msg-actions, .tk-msg-actions:focus-within { opacity:1; pointer-events:auto; }
        .tk-msg-action-btn { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:999px; border:1px solid var(--border-mid); background:var(--surface-2); color:var(--text-muted); font-size:13px; cursor:pointer; transition:all .14s ease; }
        .tk-msg-action-btn .svg-icon { width:13px; height:13px; }
        .tk-msg-action-btn:hover { background:var(--accent-soft); color:var(--accent); border-color:var(--accent-mid,#9b8cff); }
        .tk-reaction-picker { position:absolute; top:-38px; inset-inline-start:0; display:flex; gap:4px; background:var(--surface-1); border:1px solid var(--border-mid); border-radius:999px; padding:5px 7px; box-shadow:0 8px 20px rgba(0,0,0,.25); z-index:20; }
        .tk-reaction-picker-floating { position:fixed; top:auto; left:auto; inset-inline-start:auto; z-index:9998; }
        .tk-reaction-opt { background:transparent; border:none; font-size:17px; line-height:1; cursor:pointer; padding:2px; border-radius:6px; transition:transform .12s ease, background .12s ease; }
        .tk-reaction-opt:hover { background:var(--surface-2); transform:scale(1.15); }
        .tk-reply-preview-bar { display:flex; align-items:center; justify-content:space-between; gap:8px; border-inline-start:3px solid var(--accent-mid,#9b8cff); background:var(--surface-2); border-radius:8px; padding:7px 10px; margin-bottom:8px; }
        .tk-reply-preview-who { font-size:11px; font-weight:700; color:var(--accent,#6c5ce7); }
        .tk-reply-preview-text { font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:320px; }
        .tk-reply-cancel { flex:0 0 auto; cursor:pointer; color:var(--text-muted); font-size:20px; line-height:1; padding:0 4px; }
        .tk-reply-cancel:hover { color:var(--danger,#ef4444); }

        .tk-bulk-bar { display:flex; align-items:center; justify-content:flex-end; gap:10px; margin-bottom:12px; }
    </style>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-tickets<?php echo $viewT === '' ? ' fx-page-list' : ''; ?>">

            <?php if ($viewT === ''): ?>

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('message', 'svg-icon svg-lg'); ?>
                        تیکت‌ها
                    </div>
                    <div class="page-head__sub">مدیریت تیکت‌های پشتیبانی کاربران از ربات و مینی‌اپ</div>
                </div>
                <div class="chip-row">
                    <button type="button" class="btn btn-soft-success" onclick="openModal('modal-open-ticket')"><?php echo icon('plus', 'svg-icon'); ?> باز کردن تیکت</button>
                </div>
            </div>

            <?php if ($adminCreateError !== ''): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($adminCreateError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php echo fx_filter_delete_flash_html(); ?>

            <?php echo fx_search_ui('tickets.php', $tkQ, array_merge(['status' => $tkStatus !== '' ? $tkStatus : null], $tkDateKeep), 'جستجو در کد پیگیری، آیدی کاربر یا متن پیام…'); ?>

            <?php echo fx_status_filter_ui('tickets.php', $tkStatusOptions, $tkStatus, array_merge(['q' => $tkQ !== '' ? $tkQ : null], $tkDateKeep)); ?>

            <?php $fxFd = fx_filter_delete_parts('tickets.php', $tkFilterParams, $tkFilterActive ? (int)$tkPg['total'] : 0, $tkFilterCriteria); ?>
            <?php echo fx_date_filter_ui('tickets.php', '', ['q' => $tkQ !== '' ? $tkQ : null, 'status' => $tkStatus !== '' ? $tkStatus : null], '', $fxFd['button'], $fxFd['form']); ?>

            <div class="card">
                <div class="tk-bulk-bar" id="tk-bulk-bar">
                    <button type="button" class="btn btn-sm btn-soft-danger js-bulk-delete-btn" style="display:none;" id="tk-bulk-delete-btn"><?php echo icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها</button>
                </div>
                <div class="table-wrap">
                    <table id="ticketsTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th data-no-sort="1"><input type="checkbox" id="tk-select-all"></th>
                                <th>کد پیگیری</th>
                                <th>کاربر</th>
                                <th>دپارتمان</th>
                                <th>موضوع</th>
                                <th>وضعیت</th>
                                <th>آخرین به‌روزرسانی</th>
                                <th>پیام‌ها</th>
                                <th data-no-sort="1">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tickets as $tt):
                            [$slabel, $sclass] = tk_status_badge((string)$tt['status']); ?>
                            <tr data-detail-row data-detail-title="تیکت <?php echo htmlspecialchars((string)$tt['Tracking'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td><input type="checkbox" class="tk-row-check" value="<?php echo htmlspecialchars((string)$tt['Tracking'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                                <td data-label="کد پیگیری" style="direction:ltr; text-align:right;" data-summary="1"><?php echo htmlspecialchars((string)$tt['Tracking'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="کاربر">
                                    <a href="user.php?id=<?php echo urlencode((string)$tt['iduser']); ?>" class="text-link" style="direction:ltr; display:inline-block;"><?php echo htmlspecialchars((string)$tt['iduser'], ENT_QUOTES, 'UTF-8'); ?></a>
                                </td>
                                <td data-label="دپارتمان" data-filter-value="<?php echo htmlspecialchars((string)$tt['name_departman'], ENT_QUOTES, 'UTF-8'); ?>" data-summary="1"><?php echo htmlspecialchars((string)$tt['name_departman'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php $tsubj = $ticketSubjects[$tt['Tracking']] ?? ''; ?>
                                <td data-label="موضوع"><?php echo $tsubj !== '' ? htmlspecialchars($tsubj, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                                <td data-label="وضعیت" data-filter-value="<?php echo htmlspecialchars($slabel, ENT_QUOTES, 'UTF-8'); ?>" data-summary="1"><span class="badge <?php echo $sclass; ?>"><?php echo $slabel; ?></span></td>
                                <td data-label="آخرین به‌روزرسانی" style="direction:ltr; text-align:right; font-size:11.5px;"><?php echo htmlspecialchars(tk_jdate($tt['last_time']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="پیام‌ها"><?php echo (int)$tt['cnt']; ?></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <a href="tickets.php?t=<?php echo urlencode((string)$tt['Tracking']); ?>" class="btn btn-sm btn-soft-purple"><?php echo icon('message', 'svg-icon'); ?> مشاهده</a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('کل این تیکت حذف شود؟');">
                                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="_action" value="delete">
                                        <input type="hidden" name="t" value="<?php echo htmlspecialchars((string)$tt['Tracking'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo icon('trash', 'svg-icon'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php echo fx_pager_html($tkPg['page'], $tkPg['pages'], $tkPg['total'], count($tickets), 'tickets.php', array_merge(['q' => $tkQ !== '' ? $tkQ : null, 'status' => $tkStatus !== '' ? $tkStatus : null], $tkDateKeep)); ?>
                </div>
            </div>

            <div id="modal-open-ticket" class="modal-overlay<?php echo $adminCreateError !== '' ? ' active' : ''; ?>">
                <div class="modal-box" style="max-width:560px;">
                    <div class="modal-head">
                        <span class="modal-head__title">باز کردن تیکت جدید</span>
                        <button type="button" class="modal-close" onclick="closeModal('modal-open-ticket')">&times;</button>
                    </div>
                    <form method="POST" action="tickets.php" enctype="multipart/form-data">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="_action" value="admin_create">
                        <div class="form-group">
                            <label class="form-label">دپارتمان</label>
                            <select name="department" class="form-control" required>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo htmlspecialchars((string)$d['name_departman'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$d['name_departman'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">موضوع</label>
                            <input type="text" name="subject" class="form-control" placeholder="موضوع تیکت" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">شناسه عددی کاربر</label>
                            <input type="number" name="target_user_id" class="form-control" placeholder="مثلاً 123456789" required min="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">پیام</label>
                            <textarea name="text" class="form-control" placeholder="متن پیام..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">تصاویر / ویدیو (اختیاری)</label>
                            <div id="ac-media-wrap" class="tkup-wrap">
                                <div class="tkup-slot">
                                                                        <div class="tkup-dropwrap">
                                        <label class="tkup-drop">
                                            <input type="file" name="media[]" accept="image/*,video/*" class="tkup-input ac-media-input">
                                            <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                            <span>انتخاب تصویر / ویدیو</span>
                                        </label>
                                        <button type="button" class="tkup-slot-rm" aria-label="حذف این کادر" title="حذف این کادر"><svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                                    </div>
                                    <div class="tkup-chip" hidden>
                                        <span class="tkup-thumb"></span>
                                        <span class="tkup-meta">
                                            <span class="tkup-name" dir="ltr"></span>
                                            <span class="tkup-size" dir="ltr"></span>
                                        </span>
                                        <button type="button" class="tkup-rm" aria-label="حذف فایل"><?php echo icon('xmark', 'svg-icon'); ?></button>
                                    </div>
                                    <div class="tkup-error" role="alert" hidden></div>
                                </div>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:6px;">
                                <small style="opacity:.7; font-size:11.5px;">می‌توانید تا ۵ عکس/ویدیو اضافه کنید.</small>
                                <button type="button" id="ac-add-media" class="btn btn-sm btn-soft-purple"><?php echo icon('plus', 'svg-icon'); ?> افزودن فایل</button>
                            </div>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-open-ticket')">انصراف</button>
                            <button type="submit" class="btn btn-primary btn-sm"><?php echo icon('plus', 'svg-icon'); ?> ایجاد تیکت</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php else:
                [$slabel, $sclass] = tk_status_badge((string)$ticketRow['status']);
                $isClosed = $ticketRow['status'] === 'close'; ?>

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo icon('message', 'svg-icon svg-lg'); ?>
                        تیکت <span style="direction:ltr; font-family:monospace;"><?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="page-head__sub">
                        کاربر: <a href="user.php?id=<?php echo urlencode((string)$ticketRow['iduser']); ?>" class="text-link" style="direction:ltr; display:inline-block;"><?php echo htmlspecialchars((string)$ticketRow['iduser'], ENT_QUOTES, 'UTF-8'); ?></a>
                        — دپارتمان: <?php echo htmlspecialchars((string)$ticketRow['name_departman'], ENT_QUOTES, 'UTF-8'); ?>
                        — موضوع: <?php echo htmlspecialchars($ticketSubject !== '' ? $ticketSubject : '—', ENT_QUOTES, 'UTF-8'); ?>
                        — <span class="badge <?php echo $sclass; ?>"><?php echo $slabel; ?></span>
                    </div>
                </div>
                <div class="chip-row">
                    <a href="tickets.php" class="btn btn-outline btn-sm"><?php echo icon('arrow-right', 'svg-icon'); ?> بازگشت به لیست</a>
                </div>
            </div>

            <div class="card">
                <div class="tk-chat">
                    <?php foreach ($thread as $mrow):
                        $m = tk_media((string)$mrow['text']);
                        $mid = (int)$mrow['id'];
                        $side = ($mrow['result'] === 'admin') ? 'admin' : 'user';
                        $who = ($mrow['result'] === 'admin') ? '👨‍💻 پشتیبانی' : '👤 کاربر';
                        $replyToId = isset($mrow['reply_to_id']) ? (int)$mrow['reply_to_id'] : 0;
                        $seen = ($side === 'admin') ? ((int)($mrow['seen_by_user'] ?? 0) === 1) : true;
                        $reactions = $tkReactionsByMsg[$mid] ?? []; ?>
                        <div class="tk-row <?php echo $side; ?>" data-msg-id="<?php echo $mid; ?>" data-msg-body="<?php echo htmlspecialchars($m['body'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="tk-bubble-wrap">
                            <div class="tk-bubble">
                                <div class="tk-who"><?php echo $who; ?></div>
                                <?php if ($replyToId > 0 && isset($tkBodyById[$replyToId])): ?>
                                    <div class="tk-quote"><?php echo htmlspecialchars(mb_substr($tkBodyById[$replyToId], 0, 120) ?: 'پیوست', ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <?php if ($m['body'] !== ''): ?><div><?php echo nl2br(htmlspecialchars($m['body'], ENT_QUOTES, 'UTF-8')); ?></div><?php endif; ?>
                                <?php if (count($m['media'])): ?>
                                <div class="tk-media-grid">
                                    <?php foreach ($m['media'] as $mi => $mItem):
                                        $murl = 'tickets.php?media=' . (int)$mrow['id'] . '&i=' . (int)$mi; ?>
                                        <div class="tk-thumb" data-full="<?php echo htmlspecialchars($murl, ENT_QUOTES, 'UTF-8'); ?>" data-type="<?php echo $mItem['type'] === 'video' ? 'video' : 'photo'; ?>">
                                            <?php if ($mItem['type'] === 'video'): ?>
                                                <video src="<?php echo htmlspecialchars($murl, ENT_QUOTES, 'UTF-8'); ?>" preload="metadata" muted></video>
                                                <span class="tk-play">▶</span>
                                            <?php else: ?>
                                                <img src="<?php echo htmlspecialchars($murl, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" alt="">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <div class="tk-time">
                                    <?php echo htmlspecialchars(tk_jdate($mrow['time']), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($side === 'admin'): ?>
                                        <span class="tk-receipt <?php echo $seen ? 'is-seen' : ''; ?>"><?php echo icon('check', 'svg-icon'); ?><?php if ($seen) echo icon('check', 'svg-icon tk-receipt-2'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (count($reactions)): ?>
                            <div class="tk-reactions">
                                <?php foreach ($reactions as $r): ?>
                                    <span class="tk-reaction-chip<?php echo $r['mine'] ? ' is-mine' : ''; ?>"><?php echo htmlspecialchars($r['emoji'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="tk-msg-actions">
                                <?php if ($side === 'user'): ?>
                                <button type="button" class="tk-msg-action-btn" data-act="react" title="واکنش">🙂</button>
                                <?php endif; ?>
                                <button type="button" class="tk-msg-action-btn" data-act="reply" title="پاسخ"><?php echo icon('arrow-right-arrow-left', 'svg-icon'); ?></button>
                                <button type="button" class="tk-msg-action-btn" data-act="copy" title="کپی"><?php echo icon('copy', 'svg-icon'); ?></button>
                            </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form class="tk-reply" method="POST" action="tickets.php?t=<?php echo urlencode($viewT); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="_action" value="reply">
                    <input type="hidden" name="t" value="<?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="reply_to_id" id="tk-reply-to-id" value="">
                    <div id="tk-reply-preview" class="tk-reply-preview" style="display:none">
                        <div class="tk-reply-preview-bar">
                            <div class="tk-reply-preview-body">
                                <div class="tk-reply-preview-who" id="tk-reply-preview-who"></div>
                                <div class="tk-reply-preview-text" id="tk-reply-preview-text"></div>
                            </div>
                            <span class="tk-reply-cancel" id="tk-reply-cancel">&times;</span>
                        </div>
                    </div>
                    <textarea name="text" class="form-control" placeholder="پاسخ خود را بنویسید..."></textarea>
                    <div id="tk-media-wrap" class="tkup-wrap">
                        <div class="tkup-slot">
                                                        <div class="tkup-dropwrap">
                                <label class="tkup-drop">
                                    <input type="file" name="media[]" accept="image/*,video/*" class="tkup-input tk-media-input">
                                    <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    <span>انتخاب تصویر / ویدیو</span>
                                </label>
                                <button type="button" class="tkup-slot-rm" aria-label="حذف این کادر" title="حذف این کادر"><svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                            </div>
                            <div class="tkup-chip" hidden>
                                <span class="tkup-thumb"></span>
                                <span class="tkup-meta">
                                    <span class="tkup-name" dir="ltr"></span>
                                    <span class="tkup-size" dir="ltr"></span>
                                </span>
                                <button type="button" class="tkup-rm" aria-label="حذف فایل"><?php echo icon('xmark', 'svg-icon'); ?></button>
                            </div>
                            <div class="tkup-error" role="alert" hidden></div>
                        </div>
                    </div>
                    <div class="tk-compose-bar">
                        <div class="tk-compose-hint">
                            <?php echo icon('circle-info', 'svg-icon'); ?>
                            <span>می‌توانید تا ۵ عکس/ویدیو اضافه کنید.</span>
                        </div>
                        <div class="tk-compose-btns">
                            <button type="button" id="tk-add-media" class="btn btn-sm btn-soft-purple"><?php echo icon('plus', 'svg-icon'); ?> افزودن فایل</button>
                            <button type="submit" class="btn btn-primary btn-sm"><?php echo icon('paper-plane', 'svg-icon'); ?> ارسال پاسخ</button>
                        </div>
                    </div>
                </form>

                <div class="tk-danger-zone">
                    <div class="tk-danger-zone__label">
                        <?php echo icon('sliders', 'svg-icon'); ?>
                        <span>مدیریت تیکت</span>
                    </div>
                    <div class="tk-danger-zone__btns">
                        <?php if ($isClosed): ?>
                            <form method="POST" action="tickets.php">
                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="_action" value="reopen">
                                <input type="hidden" name="t" value="<?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-soft-success btn-sm"><?php echo icon('check', 'svg-icon'); ?> باز کردن مجدد تیکت</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="tickets.php" onsubmit="return confirm('این تیکت بسته شود؟');">
                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="_action" value="close">
                                <input type="hidden" name="t" value="<?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-soft-warning btn-sm"><?php echo icon('ban', 'svg-icon'); ?> بستن تیکت</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="tickets.php" onsubmit="return confirm('کل این تیکت حذف شود؟ این کار قابل بازگشت نیست.');">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="_action" value="delete">
                            <input type="hidden" name="t" value="<?php echo htmlspecialchars($viewT, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-soft-danger btn-sm tk-btn-delete"><?php echo icon('trash', 'svg-icon'); ?> حذف کامل تیکت</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tk-lightbox" id="tk-lightbox"><span class="tk-lightbox__close" id="tk-lightbox-close">&times;</span><div id="tk-lightbox-body"></div></div>

            <script>
            (function () {
                var chat = document.querySelector('.tk-chat');
                if (chat) chat.scrollTop = chat.scrollHeight;

                function tkupInit(wrapId, addBtnId) {
                    var wrap = document.getElementById(wrapId);
                    var add = document.getElementById(addBtnId);
                    if (!wrap || !add) return;
                    var IMG_ICON = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
                    var X_ICON = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                    function buildSlot() {
                        var slot = document.createElement('div');
                        slot.className = 'tkup-slot';
                        slot.innerHTML = '<div class="tkup-dropwrap"><label class="tkup-drop"><input type="file" name="media[]" accept="image/*,video/*" class="tkup-input">' + IMG_ICON + '<span>انتخاب تصویر / ویدیو</span></label><button type="button" class="tkup-slot-rm" aria-label="حذف این کادر" title="حذف این کادر">' + X_ICON + '</button></div>' +
                            '<div class="tkup-chip" hidden><span class="tkup-thumb"></span><span class="tkup-meta"><span class="tkup-name" dir="ltr"></span><span class="tkup-size" dir="ltr"></span></span><button type="button" class="tkup-rm" aria-label="حذف فایل">' + X_ICON + '</button></div>' +
                            '<div class="tkup-error" role="alert" hidden></div>';
                        return slot;
                    }
                    function refreshAdd() {
                        var slots = wrap.querySelectorAll('.tkup-slot');
                        add.style.display = slots.length >= 5 ? 'none' : '';
                        for (var i = 0; i < slots.length; i++) {
                            var b = slots[i].querySelector('.tkup-slot-rm');
                            if (b) b.hidden = slots.length <= 1;
                        }
                    }
                    function fmtSize(b) {
                        if (b < 1048576) return (b / 1024).toLocaleString('fa-IR', { maximumFractionDigits: 0 }) + ' کیلوبایت';
                        return (b / 1048576).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' مگابایت';
                    }
                    wrap.addEventListener('change', function (e) {
                        var inp = e.target;
                        if (!inp.matches('input[type="file"]')) return;
                        var slot = inp.closest('.tkup-slot');
                        if (!slot) return;
                        var file = inp.files && inp.files[0];
                        if (!file) return;
                        var err = slot.querySelector('.tkup-error');
                        if (file.type.indexOf('image/') !== 0 && file.type.indexOf('video/') !== 0) {
                            inp.value = '';
                            err.textContent = 'نوع فایل مجاز نیست؛ فقط تصویر یا ویدیو انتخاب کنید.';
                            err.hidden = false;
                            return;
                        }
                        err.hidden = true;
                        err.textContent = '';
                        var url = URL.createObjectURL(file);
                        slot.querySelector('.tkup-thumb').innerHTML = file.type.indexOf('video/') === 0
                            ? '<video src="' + url + '" muted playsinline preload="metadata"></video>'
                            : '<img src="' + url + '" alt="">';
                        slot.querySelector('.tkup-name').textContent = file.name;
                        slot.querySelector('.tkup-size').textContent = fmtSize(file.size);
                        slot.querySelector('.tkup-drop').hidden = true;
                        slot.querySelector('.tkup-chip').hidden = false;
                    });
                    wrap.addEventListener('click', function (e) {
                        var rm = e.target.closest('.tkup-rm, .tkup-slot-rm');
                        if (!rm) return;
                        var slot = rm.closest('.tkup-slot');
                        if (!slot) return;
                        if (rm.classList.contains('tkup-slot-rm') && wrap.querySelectorAll('.tkup-slot').length <= 1) return;
                        var th = slot.querySelector('.tkup-thumb');
                        var m = th && th.firstChild;
                        if (m && m.src) URL.revokeObjectURL(m.src);
                        slot.remove();
                        refreshAdd();
                    });
                    add.addEventListener('click', function () {
                        if (wrap.querySelectorAll('.tkup-slot').length >= 5) return;
                        wrap.appendChild(buildSlot());
                        refreshAdd();
                    });
                    refreshAdd();
                }

                function tkupBindSubmit(form) {
                    if (!form) return;
                    var btn = form.querySelector('button[type="submit"]');
                    if (!btn) return;
                    btn.dataset.tkupLabel = btn.innerHTML;
                    form.addEventListener('submit', function (e) {
                        if (form.classList.contains('tkup-busy')) {
                            e.preventDefault();
                            return;
                        }
                        form.classList.add('tkup-busy');
                        form.querySelectorAll('.tkup-slot').forEach(function (s) {
                            var i = s.querySelector('input[type="file"]');
                            if (i && (!i.files || !i.files.length)) s.remove();
                        });
                        form.querySelectorAll('.tkup-thumb').forEach(function (t) {
                            var m = t.firstChild;
                            if (m && m.src) URL.revokeObjectURL(m.src);
                        });
                        btn.disabled = true;
                        btn.setAttribute('aria-busy', 'true');
                        btn.innerHTML = '<svg class="svg-icon spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg> در حال ارسال…';
                        var bar = document.createElement('div');
                        bar.className = 'tkup-progress';
                        bar.innerHTML = '<span></span>';
                        var anchor = form.querySelector('.tk-compose-bar') || form.querySelector('.tk-actions') || form.querySelector('.modal-foot');
                        if (anchor) anchor.insertAdjacentElement('afterend', bar);
                    });
                    window.addEventListener('pageshow', function () {
                        if (!form.classList.contains('tkup-busy')) return;
                        form.classList.remove('tkup-busy');
                        btn.disabled = false;
                        btn.removeAttribute('aria-busy');
                        btn.innerHTML = btn.dataset.tkupLabel;
                        var bar = form.querySelector('.tkup-progress');
                        if (bar) bar.remove();
                    });
                }

                tkupInit('tk-media-wrap', 'tk-add-media');
                tkupBindSubmit(document.querySelector('.tk-reply'));

                var lb = document.getElementById('tk-lightbox');
                var lbBody = document.getElementById('tk-lightbox-body');
                var lbClose = document.getElementById('tk-lightbox-close');
                function closeLb() { lb.classList.remove('open'); lbBody.innerHTML = ''; }
                document.querySelectorAll('.tk-thumb').forEach(function (th) {
                    th.addEventListener('click', function () {
                        var url = th.getAttribute('data-full');
                        var type = th.getAttribute('data-type');
                        lbBody.innerHTML = type === 'video'
                            ? '<video src="' + url + '" controls autoplay playsinline></video>'
                            : '<img src="' + url + '" alt="">';
                        lb.classList.add('open');
                    });
                });
                if (lbClose) lbClose.addEventListener('click', closeLb);
                if (lb) lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLb(); });

                var CSRF = <?php echo json_encode($_csrf); ?>;
                var REACTION_EMOJI = <?php echo json_encode($TK_REACTION_EMOJI, JSON_UNESCAPED_UNICODE); ?>;
                var chatEl = document.querySelector('.tk-chat');
                var replyIdInput = document.getElementById('tk-reply-to-id');
                var replyPreview = document.getElementById('tk-reply-preview');
                var replyPreviewWho = document.getElementById('tk-reply-preview-who');
                var replyPreviewText = document.getElementById('tk-reply-preview-text');
                var replyCancel = document.getElementById('tk-reply-cancel');
                var replyTextarea = document.querySelector('.tk-reply textarea[name="text"]');

                function closePickers() {
                    document.querySelectorAll('.tk-reaction-picker').forEach(function (p) { p.remove(); });
                }

                function positionFloatingPicker(picker, anchorBtn) {
                    var r = anchorBtn.getBoundingClientRect();
                    var top = r.top - 44;
                    if (top < 8) top = r.bottom + 6;
                    picker.style.top = top + 'px';
                    var left = r.left;
                    var maxLeft = window.innerWidth - picker.offsetWidth - 8;
                    if (left > maxLeft) left = Math.max(8, maxLeft);
                    picker.style.left = left + 'px';
                }
                window.addEventListener('scroll', closePickers, true);
                window.addEventListener('resize', closePickers);

                function clearReply() {
                    if (replyIdInput) replyIdInput.value = '';
                    if (replyPreview) replyPreview.style.display = 'none';
                }
                if (replyCancel) replyCancel.addEventListener('click', clearReply);

                if (chatEl) {
                    chatEl.addEventListener('click', function (e) {
                        var btn = e.target.closest ? e.target.closest('.tk-msg-action-btn') : null;
                        if (!btn) {
                            if (!e.target.closest('.tk-reaction-picker')) closePickers();
                            return;
                        }
                        var row = btn.closest('.tk-row');
                        if (!row) return;
                        var mid = row.getAttribute('data-msg-id');
                        var body = row.getAttribute('data-msg-body') || '';
                        var act = btn.getAttribute('data-act');
                        var isAdmin = row.classList.contains('admin');

                        if (act === 'copy') {
                            var doCopy = function (text) {
                                if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
                                var ta = document.createElement('textarea');
                                ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                                document.body.appendChild(ta); ta.select();
                                try { document.execCommand('copy'); } catch (_) {}
                                ta.remove();
                                return Promise.resolve();
                            };
                            doCopy(body);
                            return;
                        }

                        if (act === 'reply') {
                            if (replyIdInput) replyIdInput.value = mid;
                            if (replyPreviewWho) replyPreviewWho.textContent = isAdmin ? 'پشتیبانی' : 'کاربر';
                            if (replyPreviewText) replyPreviewText.textContent = body.slice(0, 140) || 'پیوست';
                            if (replyPreview) replyPreview.style.display = '';
                            if (replyTextarea) replyTextarea.focus();
                            return;
                        }

                        if (act === 'react') {
                            closePickers();
                            var picker = document.createElement('div');
                            picker.className = 'tk-reaction-picker tk-reaction-picker-floating';
                            REACTION_EMOJI.forEach(function (em) {
                                var opt = document.createElement('button');
                                opt.type = 'button';
                                opt.className = 'tk-reaction-opt';
                                opt.textContent = em;
                                opt.addEventListener('click', function () {
                                    var existingMine = row.querySelector('.tk-reaction-chip.is-mine');
                                    var current = existingMine ? existingMine.textContent : '';
                                    var toSend = current === em ? '' : em;
                                    closePickers();
                                    var fd = new FormData();
                                    fd.append('_csrf', CSRF);
                                    fd.append('message_id', mid);
                                    fd.append('emoji', toSend);
                                    fetch('tickets.php?ajax=react', { method: 'POST', body: fd })
                                        .then(function (r) { return r.json(); })
                                        .then(function (res) {
                                            if (!res || !res.ok) return;
                                            var wrap = row.querySelector('.tk-bubble-wrap');
                                            var old = wrap.querySelector('.tk-reactions');
                                            if (old) old.remove();
                                            if (res.reactions && res.reactions.length) {
                                                var box = document.createElement('div');
                                                box.className = 'tk-reactions';
                                                res.reactions.forEach(function (rr) {
                                                    var chip = document.createElement('span');
                                                    chip.className = 'tk-reaction-chip' + (rr.mine ? ' is-mine' : '');
                                                    chip.textContent = rr.emoji;
                                                    box.appendChild(chip);
                                                });
                                                wrap.querySelector('.tk-bubble').insertAdjacentElement('afterend', box);
                                            }
                                        });
                                });
                                picker.appendChild(opt);
                            });
                            document.body.appendChild(picker);
                            positionFloatingPicker(picker, btn);
                            return;
                        }
                    });
                }

                var TRACKING = <?php echo json_encode($viewT); ?>;
                var RECEIPT_CHECK_2_HTML = <?php echo json_encode(icon('check', 'svg-icon tk-receipt-2')); ?>;
                function pollTicketState() {
                    if (!TRACKING) return;
                    fetch('tickets.php?ajax=poll_state&t=' + encodeURIComponent(TRACKING))
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (!res || !res.ok || !Array.isArray(res.messages)) return;
                            res.messages.forEach(function (m) {
                                var row = document.querySelector('.tk-row[data-msg-id="' + m.id + '"]');
                                if (!row) return;
                                var receipt = row.querySelector('.tk-receipt');
                                if (receipt) {
                                    receipt.classList.toggle('is-seen', !!m.seen);
                                    var second = receipt.querySelector('.tk-receipt-2');
                                    if (m.seen && !second) {
                                        receipt.insertAdjacentHTML('beforeend', RECEIPT_CHECK_2_HTML);
                                    } else if (!m.seen && second) {
                                        second.remove();
                                    }
                                }
                                var wrap = row.querySelector('.tk-bubble-wrap');
                                if (!wrap) return;
                                var old = wrap.querySelector('.tk-reactions');
                                var oldEmoji = old ? Array.prototype.map.call(old.children, function (c) { return c.textContent; }).join(',') : '';
                                var newEmoji = (m.reactions || []).map(function (r) { return r.emoji; }).join(',');
                                if (oldEmoji === newEmoji) return;
                                if (old) old.remove();
                                if (m.reactions && m.reactions.length) {
                                    var box = document.createElement('div');
                                    box.className = 'tk-reactions';
                                    m.reactions.forEach(function (rr) {
                                        var chip = document.createElement('span');
                                        chip.className = 'tk-reaction-chip' + (rr.mine ? ' is-mine' : '');
                                        chip.textContent = rr.emoji;
                                        box.appendChild(chip);
                                    });
                                    wrap.querySelector('.tk-bubble').insertAdjacentElement('afterend', box);
                                }
                            });
                        })
                        .catch(function () {});
                }
                if (TRACKING) setInterval(pollTicketState, 4000);
            })();
            </script>

            <?php endif; ?>

        </div>
    </section>
</section>

<?php if ($viewT === ''): ?>
<script src="js/bulk-select.js?v=fx2"></script>
<script>
  function openModal(id) { var m = document.getElementById(id); if (!m) return; if (typeof window.closeDetailSheet === 'function') window.closeDetailSheet(); m.classList.add('active'); }
  function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

  document.addEventListener("DOMContentLoaded", function () {

    function tkupInit(wrapId, addBtnId) {
      var wrap = document.getElementById(wrapId);
      var add = document.getElementById(addBtnId);
      if (!wrap || !add) return;
      var IMG_ICON = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
      var X_ICON = '<svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
      function buildSlot() {
        var slot = document.createElement('div');
        slot.className = 'tkup-slot';
        slot.innerHTML = '<div class="tkup-dropwrap"><label class="tkup-drop"><input type="file" name="media[]" accept="image/*,video/*" class="tkup-input">' + IMG_ICON + '<span>انتخاب تصویر / ویدیو</span></label><button type="button" class="tkup-slot-rm" aria-label="حذف این کادر" title="حذف این کادر">' + X_ICON + '</button></div>' +
          '<div class="tkup-chip" hidden><span class="tkup-thumb"></span><span class="tkup-meta"><span class="tkup-name" dir="ltr"></span><span class="tkup-size" dir="ltr"></span></span><button type="button" class="tkup-rm" aria-label="حذف فایل">' + X_ICON + '</button></div>' +
          '<div class="tkup-error" role="alert" hidden></div>';
        return slot;
      }
      function refreshAdd() {
        var slots = wrap.querySelectorAll('.tkup-slot');
        add.style.display = slots.length >= 5 ? 'none' : '';
        for (var i = 0; i < slots.length; i++) {
          var b = slots[i].querySelector('.tkup-slot-rm');
          if (b) b.hidden = slots.length <= 1;
        }
      }
      function fmtSize(b) {
        if (b < 1048576) return (b / 1024).toLocaleString('fa-IR', { maximumFractionDigits: 0 }) + ' کیلوبایت';
        return (b / 1048576).toLocaleString('fa-IR', { maximumFractionDigits: 1 }) + ' مگابایت';
      }
      wrap.addEventListener('change', function (e) {
        var inp = e.target;
        if (!inp.matches('input[type="file"]')) return;
        var slot = inp.closest('.tkup-slot');
        if (!slot) return;
        var file = inp.files && inp.files[0];
        if (!file) return;
        var err = slot.querySelector('.tkup-error');
        if (file.type.indexOf('image/') !== 0 && file.type.indexOf('video/') !== 0) {
          inp.value = '';
          err.textContent = 'نوع فایل مجاز نیست؛ فقط تصویر یا ویدیو انتخاب کنید.';
          err.hidden = false;
          return;
        }
        err.hidden = true;
        err.textContent = '';
        var url = URL.createObjectURL(file);
        slot.querySelector('.tkup-thumb').innerHTML = file.type.indexOf('video/') === 0
          ? '<video src="' + url + '" muted playsinline preload="metadata"></video>'
          : '<img src="' + url + '" alt="">';
        slot.querySelector('.tkup-name').textContent = file.name;
        slot.querySelector('.tkup-size').textContent = fmtSize(file.size);
        slot.querySelector('.tkup-drop').hidden = true;
        slot.querySelector('.tkup-chip').hidden = false;
      });
      wrap.addEventListener('click', function (e) {
        var rm = e.target.closest('.tkup-rm, .tkup-slot-rm');
        if (!rm) return;
        var slot = rm.closest('.tkup-slot');
        if (!slot) return;
        if (rm.classList.contains('tkup-slot-rm') && wrap.querySelectorAll('.tkup-slot').length <= 1) return;
        var th = slot.querySelector('.tkup-thumb');
        var m = th && th.firstChild;
        if (m && m.src) URL.revokeObjectURL(m.src);
        slot.remove();
        refreshAdd();
      });
      add.addEventListener('click', function () {
        if (wrap.querySelectorAll('.tkup-slot').length >= 5) return;
        wrap.appendChild(buildSlot());
        refreshAdd();
      });
      refreshAdd();
    }

    function tkupBindSubmit(form) {
      if (!form) return;
      var btn = form.querySelector('button[type="submit"]');
      if (!btn) return;
      btn.dataset.tkupLabel = btn.innerHTML;
      form.addEventListener('submit', function (e) {
        if (form.classList.contains('tkup-busy')) {
          e.preventDefault();
          return;
        }
        form.classList.add('tkup-busy');
        form.querySelectorAll('.tkup-slot').forEach(function (s) {
          var i = s.querySelector('input[type="file"]');
          if (i && (!i.files || !i.files.length)) s.remove();
        });
        form.querySelectorAll('.tkup-thumb').forEach(function (t) {
          var m = t.firstChild;
          if (m && m.src) URL.revokeObjectURL(m.src);
        });
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.innerHTML = '<svg class="svg-icon spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg> در حال ارسال…';
        var bar = document.createElement('div');
        bar.className = 'tkup-progress';
        bar.innerHTML = '<span></span>';
        var anchor = form.querySelector('.tk-actions') || form.querySelector('.modal-foot');
        if (anchor) anchor.insertAdjacentElement('afterend', bar);
      });
      window.addEventListener('pageshow', function () {
        if (!form.classList.contains('tkup-busy')) return;
        form.classList.remove('tkup-busy');
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
        btn.innerHTML = btn.dataset.tkupLabel;
        var bar = form.querySelector('.tkup-progress');
        if (bar) bar.remove();
      });
    }

    tkupInit('ac-media-wrap', 'ac-add-media');
    tkupBindSubmit(document.querySelector('#modal-open-ticket form'));

    var CSRF = <?php echo json_encode($_csrf); ?>;
    var table = document.getElementById('ticketsTable');
    var selectAll = document.getElementById('tk-select-all');
    var bulkDeleteBtn = document.getElementById('tk-bulk-delete-btn');

    var fxTicketsHandle = FxBulkSelect.init({
      scope: 'default',
      checkboxSelector: '.tk-row-check',
      scopeRoot: table || document,
      formEl: null,
      deleteButtonSelector: '.js-bulk-delete-btn',
      clearOnQueryFlags: []
    });

    function syncSelectAll() {
      if (!selectAll || !table) return;
      var visible = Array.prototype.slice.call(table.querySelectorAll('tbody .tk-row-check'));
      var persisted = FxBulkSelect.getPersistedIds(fxTicketsHandle.key);
      selectAll.checked = visible.length > 0 && visible.every(function (cb) { return persisted.indexOf(cb.value) !== -1; });
    }
    syncSelectAll();

    if (table) {
      table.addEventListener('change', function (e) {
        var cb = e.target;
        if (!cb.classList || !cb.classList.contains('tk-row-check')) return;
        syncSelectAll();
      });
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        var visible = Array.prototype.slice.call(table.querySelectorAll('tbody .tk-row-check'));
        visible.forEach(function (cb) {
          cb.checked = selectAll.checked;
          if (selectAll.checked) fxTicketsHandle.add(cb.value); else fxTicketsHandle.remove(cb.value);
        });
        syncSelectAll();
      });
    }

    if (bulkDeleteBtn) {
      bulkDeleteBtn.addEventListener('click', function () {
        var ids = FxBulkSelect.getPersistedIds(fxTicketsHandle.key);
        if (ids.length === 0) return;
        if (!confirm('همه تیکت‌های انتخاب‌شده (' + ids.length + ' مورد) حذف شوند؟ این کار قابل بازگشت نیست.')) return;
        var fd = new FormData();
        fd.append('_csrf', CSRF);
        fd.append('trackings', JSON.stringify(ids));
        bulkDeleteBtn.disabled = true;
        fetch('tickets.php?ajax=bulk_delete', { method: 'POST', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res && res.ok) {
              fxTicketsHandle.clear();
              location.reload();
            } else {
              bulkDeleteBtn.disabled = false;
              alert('حذف با خطا مواجه شد.');
            }
          })
          .catch(function () {
            bulkDeleteBtn.disabled = false;
            alert('حذف با خطا مواجه شد.');
          });
      });
    }
  });
</script>
<?php endif; ?>
</body>
</html>
