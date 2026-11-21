<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $APIKEY, $adminWebPage, $domainhosts, $pdo, $replyKb, $rxMiniAppNote, $setting.
 * Inherited or exported by reference: $act, $f, $host, $path, $row, $st, $text.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TicketMediaAndReplies
{
    public static function handle(
        $APIKEY,
        &$act,
        $adminWebPage,
        $domainhosts,
        &$f,
        &$host,
        &$path,
        $pdo,
        $replyKb,
        &$row,
        $rxMiniAppNote,
        $setting,
        &$st,
        &$text
    ) {
        if (($adminWebPage ?? null) !== 'tickets.php') {
            return;
        }

        if (isset($_GET['media']) && $_GET['media'] !== '') {
            $mid = (int)$_GET['media'];
            $st = $pdo->prepare("SELECT text FROM support_message WHERE id = ? LIMIT 1");
            $st->execute([$mid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $m = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_media($row['text'] ?? '');
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
            $tk = \Faoxima\Admin\Telegram\Tickets::rxAdminTicketFind($T);
            if (!is_array($tk)) { header("Location: tickets.php"); exit; }
            $iduser = (string)$tk['iduser'];
            $reportTopic = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "otherservice", "select")['idreport'] ?? null;

            if ($act === 'reply') {
                $text = trim((string)($_POST['text'] ?? ''));
                $mediaTokens = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_deliver_uploads(\Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_uploads($_FILES), $iduser, $text);
                $miniappOnly = (string)($setting['miniapp_ticket_mode'] ?? '0') === '1';
                if ($miniappOnly) {
                    $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
                    $replyKb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_continue_in_miniapp_btn', '🚀 ادامه گفتگو در مینی‌اپ'), 'web_app' => ['url' => 'https://' . $host . '/app/#/tickets/' . $T]]]]], JSON_UNESCAPED_UNICODE) : null;
                    $rxMiniAppNote = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_miniapp_only_note', "\n\n⚠️ ادامه گفتگو فقط از طریق مینی‌اپ امکان‌پذیر است.");
                } else {
                    $replyKb = json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_reply_btn', '💬 پاسخ'), 'callback_data' => 'ticketreply_' . $T]]]], JSON_UNESCAPED_UNICODE);
                    $rxMiniAppNote = "";
                }
                if (count($mediaTokens) > 0) {
                    sendmessage($iduser, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_support_replied_tpl', '📩 پشتیبانی به تیکت شما (کد <code>{tracking}</code>) پاسخ داد.{miniapp_note}'), ['tracking' => $T, 'miniapp_note' => $rxMiniAppNote]), $replyKb, 'HTML');
                } else {
                    if ($text === '') { header("Location: tickets.php?t=" . urlencode($T)); exit; }
                    sendmessage($iduser, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_support_reply_prefix', '📩 پاسخ پشتیبانی:') . "\n" . $text . $rxMiniAppNote, $replyKb, 'HTML');
                }
                $body = trim($text . (count($mediaTokens) ? "\n" . implode(' ', $mediaTokens) : ''));
                $time = date('Y/m/d H:i:s');
                $replyToId = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_validate_reply_target($pdo, $T, (int)($_POST['reply_to_id'] ?? 0));
                \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_store_admin_message($pdo, $T, $tk['idsupport'], $iduser, $tk['name_departman'], $body, $time, $replyToId);
                \Faoxima\Admin\Telegram\Tickets::rxAdminTicketStatus($T, 'Answered');
                header("Location: tickets.php?t=" . urlencode($T));
                exit;
            }
            if ($act === 'close') {
                \Faoxima\Admin\Telegram\Tickets::rxAdminTicketStatus($T, 'close');
                sendmessage($iduser, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_closed_by_support_tpl', '🔒 تیکت شما (کد <code>{tracking}</code>) توسط پشتیبانی بسته شد.'), ['tracking' => $T]), null, 'HTML');
                header("Location: tickets.php?t=" . urlencode($T));
                exit;
            }
            if ($act === 'reopen') {
                \Faoxima\Admin\Telegram\Tickets::rxAdminTicketStatus($T, 'Answered');
                if ((string)($setting['miniapp_ticket_mode'] ?? '0') === '1') {
                    $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
                    $reopenKb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_continue_in_miniapp_btn', '🚀 ادامه گفتگو در مینی‌اپ'), 'web_app' => ['url' => 'https://' . $host . '/app/#/tickets/' . $T]]]]], JSON_UNESCAPED_UNICODE) : null;
                    sendmessage($iduser, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_reopened_miniapp_tpl', "🔓 تیکت شما (کد <code>{tracking}</code>) دوباره باز شد.\n\n⚠️ ادامه گفتگو فقط از طریق مینی‌اپ امکان‌پذیر است."), ['tracking' => $T]), $reopenKb, 'HTML');
                } else {
                    $reopenKb = json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_reply_btn', '💬 پاسخ'), 'callback_data' => 'ticketreply_' . $T]]]], JSON_UNESCAPED_UNICODE);
                    sendmessage($iduser, \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_reopened_tpl', '🔓 تیکت شما (کد <code>{tracking}</code>) دوباره باز شد. می‌توانید پیام ارسال کنید.'), ['tracking' => $T]), $reopenKb, 'HTML');
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

        return true;
    }
}
