<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $_rx_nav_styles, $backuser, $caption, $dataget, $datatextbot, $domainhosts, $from_id, $keyboard, $list_departman, $message_id, $pdo, $photo, $photoid, $setting, $supportoption, $telegramRoute, $text, $textbotlang, $tkCancelKb, $update, $username, $video, $videoid.
 * Inherited or exported by reference: $datain, $mrow, $randomString, $stmt, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Tickets
{
    public static function handle(
        $_rx_nav_styles,
        $backuser,
        $caption,
        $dataget,
        &$datain,
        $datatextbot,
        $domainhosts,
        $from_id,
        $keyboard,
        $list_departman,
        $message_id,
        &$mrow,
        $pdo,
        $photo,
        $photoid,
        &$randomString,
        $setting,
        &$stmt,
        $supportoption,
        $telegramRoute,
        $text,
        $textbotlang,
        $tkCancelKb,
        $update,
        &$user,
        $username,
        $video,
        $videoid
    ) {
        $showTicketMiniApp = static function ($chat, $message, $firstName, $host, $suggestion): void {
            $text = trim((string) $suggestion);
            \Faoxima\Telegram\Conversation\MiniAppRedirect::show(
                $chat,
                $message,
                $firstName,
                $host,
                $text !== '' ? $text : '🎫 ارسال و پیگیری تیکت‌های پشتیبانی فقط از طریق مینی‌اپ امکان‌پذیر است.',
                '/app/#/tickets',
                'Editmessagetext',
                'sendmessage',
            );
        };

        switch ($telegramRoute) {
        case 'support_menu':
            if (!\Faoxima\Telegram\Support\Transport::check_active_btn($setting['keyboardmain'], "text_support")) {
                sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
                return;
            }
            \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('support', $from_id, $datain, $message_id, ['supportbtns'], ['supportbtns']);
            if ($datain == "supportbtns") {
                Editmessagetext($from_id, $message_id, $textbotlang['users']['support']['btnsupport'], $supportoption);
            } else {
                sendmessage($from_id, $textbotlang['users']['support']['btnsupport'], $supportoption, 'HTML');
            }
            break;
        case 'ticket_list':
            if (\Faoxima\Telegram\Conversation\MiniAppRedirect::tickets($setting)) {
                $showTicketMiniApp($from_id, $message_id, $user['first_name'] ?? '', $domainhosts ?? null, $datatextbot['miniapp_ticket_suggest'] ?? '');
                return;
            }
            $myt = $pdo->prepare("SELECT Tracking, name_departman, MAX(id) lid, MAX(time) lt, MAX(status) st FROM support_message WHERE iduser = ? GROUP BY Tracking, name_departman ORDER BY lid DESC LIMIT 20");
            $myt->execute([$from_id]);
            $ticketmenu = ['inline_keyboard' => []];
            foreach ($myt->fetchAll(PDO::FETCH_ASSOC) as $rowt) {
                $stt = $rowt['st'] == 'close' ? '🔒' : '🟢';
                $ticketmenu['inline_keyboard'][] = [['text' => $stt . ' ' . $rowt['name_departman'] . ' | #' . $rowt['Tracking'], 'callback_data' => 'ticketopen_' . $rowt['Tracking']]];
            }
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            $ticketmenu['inline_keyboard'][] = [['text' => '➕ تیکت جدید', 'callback_data' => 'ticketnew']];
            $ticketmenu['inline_keyboard'][] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)];
            $ticketmenu = json_encode($ticketmenu, JSON_UNESCAPED_UNICODE);
            $tickethead = "🎫 تیکت‌های پشتیبانی شما\n\nیک تیکت را برای ادامه انتخاب کنید یا تیکت جدید بسازید.";
            if (!empty($datain) && intval($message_id) > 0) {
                Editmessagetext($from_id, $message_id, $tickethead, $ticketmenu);
            } else {
                sendmessage($from_id, $tickethead, $ticketmenu, 'HTML');
            }
            break;
        case 'ticket_new':
            if (\Faoxima\Telegram\Conversation\MiniAppRedirect::tickets($setting)) {
                $showTicketMiniApp($from_id, $message_id, $user['first_name'] ?? '', $domainhosts ?? null, $datatextbot['miniapp_ticket_suggest'] ?? '');
                return;
            }
            Editmessagetext($from_id, $message_id, $datatextbot['dyn_tickets_select_department'] ?? "📌 بخش پشتیبانی که میخواهید پیام دهید را انتخاب نمایید.", $list_departman, 'HTML');
            break;
        case 'ticket_department':
            $iddeparteman = $dataget[1];
            \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, "clear", "iddeparteman", $iddeparteman);
            deletemessage($from_id, $message_id);
            sendmessage($from_id, $datatextbot['dyn_tickets_ask_subject'] ?? "📝 موضوع تیکت را وارد نمایید (مثلاً: مشکل در اتصال):", $backuser, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getticketsubj", $from_id);
            break;
        case 'ticket_cancel':
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            $rxTkCanceledTxt = $datatextbot['dyn_tickets_operation_canceled'] ?? "❌ عملیات لغو شد.";
            if (intval($message_id) > 0) {
                $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
                Editmessagetext($from_id, $message_id, $rxTkCanceledTxt, json_encode(['inline_keyboard' => [[\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'supporttickets', '_rxcolor' => 'navigation:ticket_back'], 'supporttickets', $_rx_nav_s)]]], JSON_UNESCAPED_UNICODE));
            } else {
                sendmessage($from_id, $rxTkCanceledTxt, $keyboard, 'HTML');
            }
            break;
        case 'ticket_subject':
            if (!isset($update['message']) && empty($text)) { return; }
            \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, "save", "subject", mb_substr(trim((string)$text), 0, 120, 'UTF-8'));
            \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, "save", "ctx", "create");
            \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, "save", "media", []);
            \Faoxima\Telegram\Navigation\Steps::step("tk_media_choice", $from_id);
            sendmessage($from_id, $datatextbot['dyn_tickets_ask_media_new'] ?? "📎 می‌خواهید همراه تیکت عکس/ویدیو هم بفرستید؟", \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxTicketMediaChoiceKeyboard($datatextbot), 'HTML');
            break;
        case 'ticket_subject_invalid':
            if (!isset($update['message']) && empty($text)) { return; }
            sendmessage($from_id, $datatextbot['dyn_tickets_invalid_subject_text'] ?? "❌ لطفاً موضوع تیکت را به‌صورت متن وارد کنید.", null, 'HTML');
            return;
        case 'ticket_text_only':
            if (!isset($update['callback_query'])) { return; }
            \Faoxima\Telegram\Navigation\Steps::step("tk_desc", $from_id);
            Editmessagetext($from_id, $message_id, $datatextbot['dyn_tickets_ask_body_text_short'] ?? "✍️ متن پیام خود را بنویسید:", $tkCancelKb);
            break;
        case 'ticket_media_choice':
            if (!isset($update['callback_query'])) { return; }
            \Faoxima\Telegram\Navigation\Steps::step("tk_media_count", $from_id);
            $rxTkCancel = $datatextbot['dyn_tickets_cancel'] ?? "🔙 انصراف";
            Editmessagetext($from_id, $message_id, $datatextbot['dyn_tickets_ask_media_count'] ?? "🔢 چند رسانه می‌خواهید بفرستید؟ (۱ تا ۵)", json_encode(['inline_keyboard' => [[
                ['text' => '۱', 'callback_data' => 'tk_cnt_1'],
                ['text' => '۲', 'callback_data' => 'tk_cnt_2'],
                ['text' => '۳', 'callback_data' => 'tk_cnt_3'],
                ['text' => '۴', 'callback_data' => 'tk_cnt_4'],
                ['text' => '۵', 'callback_data' => 'tk_cnt_5'],
            ], [['text' => $rxTkCancel, 'callback_data' => 'tk_cancel']]]], JSON_UNESCAPED_UNICODE));
            break;
        case 'ticket_media_count':
            if (!isset($update['callback_query'])) { return; }
            \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, "save", "target", (int)$dataget[1]);
            \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, "save", "media", []);
            \Faoxima\Telegram\Navigation\Steps::step("tk_collect", $from_id);
            $rxTkUploadTpl = $datatextbot['dyn_tickets_ask_media_upload'] ?? "📤 رسانهٔ ۱ از %s را بفرستید (فقط عکس یا ویدیو).";
            Editmessagetext($from_id, $message_id, sprintf($rxTkUploadTpl, $dataget[1]), $tkCancelKb);
            break;
        case 'ticket_media_invalid':
            if (!isset($update['message']) && empty($text)) { return; }
            sendmessage($from_id, $datatextbot['dyn_tickets_invalid_media_choice'] ?? "❌ لطفاً از دکمه‌های بالا انتخاب کنید یا «انصراف» بزنید.", $tkCancelKb, 'HTML');
            return;
        case 'ticket_collect':
            $mm = $update['message'] ?? [];
            if (!\Faoxima\Telegram\Conversation\TicketMedia::accepts($mm, $photo, $video)) {
                sendmessage($from_id, $datatextbot['dyn_tickets_invalid_media_type'] ?? "❌ فقط عکس یا ویدیوی معمولی مجاز است (گیف/استیکر/فوروارد/فایل/ویس رد می‌شود). دوباره بفرستید یا «انصراف» بزنید.", $tkCancelKb, 'HTML');
                return;
            }
            $ud = json_decode($user['Processing_value'], true);
            if (!is_array($ud)) $ud = [];
            $media = is_array($ud['media'] ?? null) ? $ud['media'] : [];
            $media[] = $photo ? ['type' => 'photo', 'file_id' => $photoid] : ['type' => 'video', 'file_id' => $videoid];
            \Faoxima\Telegram\Conversation\ConversationData::rx_write_conversation_data($from_id, $user, "save", "media", $media);
            $target = (int)($ud['target'] ?? 1);
            if (count($media) < $target) {
                $nx = count($media) + 1;
                $rxTkReceivedTpl = $datatextbot['dyn_tickets_media_received'] ?? "✅ دریافت شد (%s از %s). رسانهٔ بعدی را بفرستید.";
                sendmessage($from_id, sprintf($rxTkReceivedTpl, $nx, $target), $tkCancelKb, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step("tk_desc", $from_id);
            sendmessage($from_id, $datatextbot['dyn_tickets_ask_body_text'] ?? "✍️ حالا متن پیام خود را بنویسید:", $tkCancelKb, 'HTML');
            break;
        case 'ticket_submit':
            if (!isset($update['message']) && empty($text)) { return; }
            $ud = json_decode($user['Processing_value'], true);
            if (!is_array($ud)) $ud = [];
            $ctx = (string)($ud['ctx'] ?? 'create');
            $media = is_array($ud['media'] ?? null) ? $ud['media'] : [];
            $desc = (string)$text !== '' ? (string)$text : (string)$caption;
            $mediaTokens = '';
            foreach ($media as $mone) { $mediaTokens .= "\n[[{$mone['type']}:{$mone['file_id']}]]"; }
            $time = date('Y/m/d H:i:s');
            $timejalali = jdate('Y/m/d H:i:s');
            $reportTopic = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "otherservice", "select")['idreport'] ?? null;
            $channel = (string)($setting['Channel_Report'] ?? '');
            $mainadmin = trim((string)($GLOBALS['adminnumber'] ?? ''));
            if ($ctx === 'reply') {
                $idtraking = (string)($ud['t'] ?? '');
                $tkdetail = \Faoxima\Core\Database\Db::select("support_message", "*", "Tracking", $idtraking);
                if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
                    sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
                    \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
                    \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                    return;
                }
                $body = trim($desc . $mediaTokens);
                $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$idtraking, $tkdetail['idsupport'], $from_id, $tkdetail['name_departman'], $body, 'user', $time, 'Customerresponse']);
                \Faoxima\Core\Database\Db::update("support_message", "status", "Customerresponse", "Tracking", $idtraking);
                $textreport = "💬 پاسخ جدید کاربر در تیکت\n\nکاربر : <a href=\"tg://user?id=$from_id\">$from_id</a>\nیوزرنیم : @$username\nدپارتمان : {$tkdetail['name_departman']}\nکد پیگیری : <code>$idtraking</code>\nزمان : $timejalali\n\nمتن : $desc";
                $adminReplyKb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ به تیکت', 'callback_data' => 'ticketadminreply_' . $idtraking]]]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxDeliverTicketReport($tkdetail['idsupport'] ?? '', $mainadmin, $channel, $reportTopic, $textreport, $adminReplyKb, $media);
                sendmessage($from_id, $datatextbot['dyn_tickets_reply_recorded'] ?? "✅ پیام شما ثبت شد و پس از بررسی پاسخ داده می‌شود.", $keyboard, 'HTML');
            } else {
                $departeman = \Faoxima\Core\Database\Db::select("departman", "*", "id", $ud['iddeparteman'] ?? 0, "select");
                if (!is_array($departeman)) $departeman = \Faoxima\Core\Database\Db::select("departman", "*", null, null, "select");
                $subject = trim((string)($ud['subject'] ?? ''));
                $randomString = bin2hex(random_bytes(4));
                $subjToken = $subject !== '' ? "[[subj:$subject]]\n" : "";
                $body = trim($subjToken . $desc . $mediaTokens);
                $stmt = $pdo->prepare("INSERT IGNORE INTO support_message (Tracking,idsupport,iduser,name_departman,text,result,time,status) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$randomString, $departeman['idsupport'], $from_id, $departeman['name_departman'], $body, 'user', $time, 'Unseen']);
                $textreport = "🎫 تیکت جدید پشتیبانی\n\nکاربر : <a href=\"tg://user?id=$from_id\">$from_id</a>\nیوزرنیم : @$username\nدپارتمان : {$departeman['name_departman']}\nموضوع : " . ($subject !== '' ? $subject : '—') . "\nکد پیگیری : <code>$randomString</code>\nزمان : $timejalali\n\nمتن : $desc";
                $adminReplyKb = json_encode(['inline_keyboard' => [[['text' => '💬 پاسخ به تیکت', 'callback_data' => 'ticketadminreply_' . $randomString]]]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxDeliverTicketReport($departeman['idsupport'] ?? '', $mainadmin, $channel, $reportTopic, $textreport, $adminReplyKb, $media);
                $rxTkCreatedTpl = $datatextbot['dyn_tickets_created'] ?? "✅ تیکت شما با کد پیگیری <code>%s</code> ثبت شد و پس از بررسی پاسخ داده می‌شود.";
                sendmessage($from_id, sprintf($rxTkCreatedTpl, $randomString), $keyboard, 'HTML');
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 'ticket_body_invalid':
            if (!isset($update['message']) && empty($text)) { return; }
            sendmessage($from_id, $datatextbot['dyn_tickets_invalid_body_text'] ?? "❌ لطفاً متن پیام را وارد کنید یا «انصراف» بزنید.", $tkCancelKb, 'HTML');
            return;
        case 'ticket_open':
            if (\Faoxima\Telegram\Conversation\MiniAppRedirect::tickets($setting)) {
                $showTicketMiniApp($from_id, $message_id, $user['first_name'] ?? '', $domainhosts ?? null, $datatextbot['miniapp_ticket_suggest'] ?? '');
                return;
            }
            $idtraking = $dataget[1];
            $tkdetail = \Faoxima\Core\Database\Db::select("support_message", "*", "Tracking", $idtraking);
            if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
                return;
            }
            $msgs = $pdo->prepare("SELECT * FROM support_message WHERE Tracking = ? ORDER BY id ASC");
            $msgs->execute([$idtraking]);
            $allmsgs = $msgs->fetchAll(PDO::FETCH_ASSOC);
            $subject = '';
            if (isset($allmsgs[0]['text']) && preg_match('/\[\[subj:([^\]]*)\]\]/u', (string)$allmsgs[0]['text'], $sm)) {
                $subject = trim($sm[1]);
            }
            $lines = '';
            $hasMedia = false;
            foreach ($allmsgs as $mrow) {
                $who = ($mrow['result'] === 'admin') ? '👨‍💻 پشتیبانی' : '👤 شما';
                if (preg_match('/\[\[(photo|video):[^\]]+\]\]/', (string)$mrow['text'])) $hasMedia = true;
                $bodyline = trim(preg_replace(['/\[\[(photo|video):[^\]]+\]\]/', '/\[\[subj:[^\]]*\]\]/u'], ['🖼 رسانه', ''], (string)$mrow['text']));
                if ($bodyline === '') { $bodyline = '—'; }
                $lines .= "$who:\n$bodyline\n— — —\n";
            }
            $isclosed = $tkdetail['status'] == 'close';
            $statelabel = $isclosed ? '🔒 بسته' : '🟢 باز';
            $tickethead = "🎫 تیکت <code>$idtraking</code>\nدپارتمان : {$tkdetail['name_departman']}\nموضوع : " . ($subject !== '' ? $subject : '—') . "\nوضعیت : $statelabel\n\n$lines";
            $kbrows = [];
            if ($hasMedia) {
                $kbrows[] = [['text' => '📎 مشاهده رسانه‌ها', 'callback_data' => 'tk_album_' . $idtraking]];
            }
            if (!$isclosed) {
                $kbrows[] = [['text' => '✍️ ارسال پیام', 'callback_data' => 'ticketmsg_' . $idtraking]];
                $kbrows[] = [['text' => '🔒 بستن تیکت', 'callback_data' => 'ticketclose_' . $idtraking]];
            }
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            $kbrows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'supporttickets', '_rxcolor' => 'navigation:ticket_back'], 'supporttickets', $_rx_nav_s)];
            Editmessagetext($from_id, $message_id, $tickethead, json_encode(['inline_keyboard' => $kbrows], JSON_UNESCAPED_UNICODE));
            break;
        case 'ticket_reply':
            if (\Faoxima\Telegram\Conversation\MiniAppRedirect::tickets($setting)) {
                $showTicketMiniApp($from_id, $message_id, $user['first_name'] ?? '', $domainhosts ?? null, $datatextbot['miniapp_ticket_suggest'] ?? '');
                return;
            }
            $idtraking = $dataget[1];
            $tkdetail = \Faoxima\Core\Database\Db::select("support_message", "*", "Tracking", $idtraking);
            if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
                return;
            }
            if ($tkdetail['status'] == 'close') {
                sendmessage($from_id, $datatextbot['dyn_tickets_closed_locked'] ?? "🔒 این تیکت بسته است؛ تا زمانی که پشتیبانی پاسخ ندهد یا آن را باز نکند نمی‌توانید پیام ارسال کنید.", null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", json_encode(['ctx' => 'reply', 't' => $idtraking, 'media' => []], JSON_UNESCAPED_UNICODE), "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step("tk_media_choice", $from_id);
            sendmessage($from_id, $datatextbot['dyn_tickets_ask_media_reply'] ?? "📎 می‌خواهید همراه پاسخ عکس/ویدیو هم بفرستید؟", \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxTicketMediaChoiceKeyboard($datatextbot), 'HTML');
            break;
        case 'ticket_album':
            $idtraking = $dataget[1];
            $tkd = \Faoxima\Core\Database\Db::select("support_message", "*", "Tracking", $idtraking);
            if (!is_array($tkd) || (string)($tkd['iduser'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
                return;
            }
            $rws = $pdo->prepare("SELECT text FROM support_message WHERE Tracking = ? ORDER BY id ASC");
            $rws->execute([$idtraking]);
            $allMedia = [];
            foreach ($rws->fetchAll(PDO::FETCH_ASSOC) as $rr) {
                if (preg_match_all('/\[\[(photo|video):([^\]]+)\]\]/', (string)$rr['text'], $mmm, PREG_SET_ORDER)) {
                    foreach ($mmm as $one) { $allMedia[] = ['type' => $one[1], 'file_id' => $one[2]]; }
                }
            }
            if (count($allMedia) === 0) {
                sendmessage($from_id, $datatextbot['dyn_tickets_no_media'] ?? "🖼 رسانه‌ای در این تیکت نیست.", null, 'HTML');
                return;
            }
            foreach (array_chunk($allMedia, 10) as $chunk) { \Faoxima\Telegram\Conversation\InitializeHelpers::tk_album_send($from_id, null, $chunk); }
            break;
        case 'ticket_close':
            if (\Faoxima\Telegram\Conversation\MiniAppRedirect::tickets($setting)) {
                $showTicketMiniApp($from_id, $message_id, $user['first_name'] ?? '', $domainhosts ?? null, $datatextbot['miniapp_ticket_suggest'] ?? '');
                return;
            }
            $idtraking = $dataget[1];
            $tkdetail = \Faoxima\Core\Database\Db::select("support_message", "*", "Tracking", $idtraking);
            if (!is_array($tkdetail) || (string)($tkdetail['iduser'] ?? '') !== (string)$from_id) {
                sendmessage($from_id, $datatextbot['dyn_tickets_not_available'] ?? "❌ این تیکت در دسترس نیست.", null, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("support_message", "status", "close", "Tracking", $idtraking);
            $reportTopic = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "otherservice", "select")['idreport'] ?? null;
            $closetext = "🔒 کاربر <a href=\"tg://user?id=$from_id\">$from_id</a> تیکت <code>$idtraking</code> را بست.";
            $adminpv = (string)($tkdetail['idsupport'] ?? '');
            $mainadmin = trim((string)($GLOBALS['adminnumber'] ?? ''));
            foreach (\Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxTicketReportTargets($adminpv, $mainadmin) as $tg) {
                sendmessage($tg, $closetext, null, 'HTML');
            }
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', ['chat_id' => $setting['Channel_Report'], 'message_thread_id' => $reportTopic, 'text' => $closetext, 'parse_mode' => 'HTML']);
            }
            $rxTkClosedTpl = $datatextbot['dyn_tickets_closed_confirm'] ?? "🔒 تیکت <code>%s</code> بسته شد. برای ارسال پیام جدید باید پشتیبانی پاسخ دهد یا آن را باز کند.";
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            Editmessagetext($from_id, $message_id, sprintf($rxTkClosedTpl, $idtraking), json_encode(['inline_keyboard' => [[\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'supporttickets', '_rxcolor' => 'navigation:ticket_back'], 'supporttickets', $_rx_nav_s)]]], JSON_UNESCAPED_UNICODE));
            break;
        }

        return true;
    }
}
