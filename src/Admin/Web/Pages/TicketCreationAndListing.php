<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $adminWebPage, $domainhosts, $note, $pdo, $setting.
 * Inherited or exported by reference: $adminCreateError, $departments, $host, $kb, $r, $st, $text, $thread, $ticketRow, $ticketSubject, $ticketSubjects, $tickets, $tkBodyById, $tkDateKeep, $tkDf, $tkFilterActive, $tkFilterCriteria, $tkFilterParams, $tkPg, $tkQ, $tkReactionsByMsg, $tkStatus, $tkStatusActive, $tkStatusOptions, $viewT.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TicketCreationAndListing
{
    public static function handle(
        &$adminCreateError,
        $adminWebPage,
        &$departments,
        $domainhosts,
        &$host,
        &$kb,
        $note,
        $pdo,
        &$r,
        $setting,
        &$st,
        &$text,
        &$thread,
        &$ticketRow,
        &$ticketSubject,
        &$ticketSubjects,
        &$tickets,
        &$tkBodyById,
        &$tkDateKeep,
        &$tkDf,
        &$tkFilterActive,
        &$tkFilterCriteria,
        &$tkFilterParams,
        &$tkPg,
        &$tkQ,
        &$tkReactionsByMsg,
        &$tkStatus,
        &$tkStatusActive,
        &$tkStatusOptions,
        &$viewT
    ) {
        if (($adminWebPage ?? null) !== 'tickets.php') {
            return;
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
            $targetUser = \Faoxima\Core\Database\Db::select("user", "*", "id", $targetUserId);
            if (!is_array($targetUser)) {
                header("Location: tickets.php?admin_create_error=" . urlencode('کاربری با این شناسه یافت نشد.'));
                exit;
            }
            $deptRow = \Faoxima\Core\Database\Db::select("departman", "*", "name_departman", $department);
            if (!is_array($deptRow)) {
                header("Location: tickets.php?admin_create_error=" . urlencode('دپارتمان انتخاب‌شده نامعتبر است.'));
                exit;
            }
            if ($subject === '') {
                header("Location: tickets.php?admin_create_error=" . urlencode('موضوع تیکت را وارد کنید.'));
                exit;
            }

            $mediaTokens = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_deliver_uploads(\Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_uploads($_FILES), $targetUserId, $text);

            if ($text === '' && count($mediaTokens) === 0) {
                header("Location: tickets.php?admin_create_error=" . urlencode('متن پیام یا تصویر را وارد کنید.'));
                exit;
            }

            $T = bin2hex(random_bytes(4));
            $mediaBlock = count($mediaTokens) ? "\n" . implode(' ', $mediaTokens) : '';
            $body = trim("[[subj:$subject]]" . $mediaBlock . ($text !== '' ? "\n" . $text : ''));
            $time = date('Y/m/d H:i:s');
            \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_store_admin_message($pdo, $T, (string)$deptRow['idsupport'], $targetUserId, (string)$deptRow['name_departman'], $body, $time, null);

            if ((string)($setting['miniapp_ticket_mode'] ?? '0') === '1') {
                $host = isset($domainhosts) ? rtrim(preg_replace('#^https?://#', '', (string)$domainhosts), '/') : '';
                $kb = ($host !== '') ? json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_continue_in_miniapp_btn', '🚀 ادامه گفتگو در مینی‌اپ'), 'web_app' => ['url' => 'https://' . $host . '/app/#/tickets/' . $T]]]]], JSON_UNESCAPED_UNICODE) : null;
                $note = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_miniapp_only_note', "\n\n⚠️ ادامه گفتگو فقط از طریق مینی‌اپ امکان‌پذیر است.");
            } else {
                $kb = json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_my_tickets_btn', '🎫 تیکت‌های من'), 'callback_data' => 'supporttickets']]]], JSON_UNESCAPED_UNICODE);
                $note = "";
            }
            $notifyBase = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_tickets_new_ticket_created_tpl', '🎫 یک تیکت جدید توسط پشتیبانی برای شما ثبت شد (کد <code>{tracking}</code>).'), ['tracking' => $T]);
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
            foreach ($thread as $r) { $tkBodyById[(int)$r['id']] = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_media((string)$r['text'])['body']; }
            $tkReactionsByMsg = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_thread_reactions($pdo, $thread, 'admin:' . (string)($_SESSION['user'] ?? ''));
        }
        $ticketSubject = $ticketRow ? \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_subject((string)$ticketRow['text']) : '';
        $ticketSubjects = [];
        $departments = [];
        $adminCreateError = trim((string)($_GET['admin_create_error'] ?? ''));
        if ($viewT === '') {
            $departments = $pdo->query("SELECT id, name_departman FROM departman ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            try {
                $__mark = $pdo->prepare("UPDATE admin SET last_ticket_seen = (SELECT COALESCE(MAX(id),0) FROM support_message) WHERE username = ?");
                $__mark->execute([$_SESSION['user'] ?? '']);
            } catch (\Throwable $e) {}
            $tkQ = \Faoxima\Admin\Web\Support\SearchFilter::fx_search_current();
            $tkWhereSql = '1=1';
            $tkParams = [];
            if ($tkQ !== '') {
                $tkLike = '%' . $tkQ . '%';
                $tkWhereSql .= ' AND Tracking IN (SELECT DISTINCT Tracking FROM support_message WHERE Tracking LIKE :tq1 OR iduser LIKE :tq2 OR text LIKE :tq3)';
                $tkParams[':tq1'] = $tkLike;
                $tkParams[':tq2'] = $tkLike;
                $tkParams[':tq3'] = $tkLike;
            }
            $tkDf = \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_resolve();
            if ($tkDf['active']) {
                $tkDateSql = \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_sql_mixed_named('tk_last', $tkDf['from'], $tkDf['to'], $tkParams, 'tkd');
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
            $tkStatus = \Faoxima\Admin\Web\Support\StatusFilter::fx_status_filter_current();
            $tkStatusActive = $tkStatus !== '' && isset($tkStatusOptions[$tkStatus]);
            if ($tkStatusActive) {
                $tkWhereSql .= ' AND Tracking IN (SELECT Tracking FROM support_message GROUP BY Tracking HAVING MAX(status) ' . $tkStatusSql[$tkStatus] . ')';
            }

            $tkFilterActive = $tkQ !== '' || $tkDf['active'] || $tkStatusActive;
            $tkDateKeep = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_date_params('', $tkDf['active']);
            $tkFilterParams = array_merge(['q' => $tkQ !== '' ? $tkQ : null, 'status' => $tkStatusActive ? $tkStatus : null], $tkDateKeep);
            $tkFilterCriteria = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_criteria($tkStatusActive ? $tkStatusOptions[$tkStatus] : '', $tkDf, $tkQ);
            if (\Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_requested()) {
                $fdMatched = 0;
                $fdDeleted = 0;
                if ($tkFilterActive) {
                    $fdTrackings = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_collect($pdo, 'support_message', 'Tracking', $tkWhereSql, $tkParams);
                    $fdMatched = count($fdTrackings);
                    try {
                        $fdDeleted = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_delete_trackings($pdo, $fdTrackings);
                    } catch (\Throwable $e) {
                        $fdDeleted = 0;
                    }
                }
                \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_redirect('tickets.php', $tkFilterParams, $fdMatched, $fdDeleted);
            }

            [$tkPg, $tickets, $ticketSubjects] = \Faoxima\Admin\Web\Pages\TicketsRequestHelpers::tk_admin_ticket_page($pdo, $tkWhereSql, $tkParams);
        }

        return true;
    }
}
