<?php

namespace Faoxima\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $_rx_nav_styles, $connect, $from_id, $message_id, $pdo, $previous_page, $statusnote, $telegramRoute, $textbotlang, $user.
 * Inherited or exported by reference: $backuser, $data, $datain, $next_page, $page, $pages, $result, $row, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class RenewalList
{
    public static function handle(
        $_rx_nav_styles,
        &$backuser,
        $connect,
        &$data,
        &$datain,
        $from_id,
        $message_id,
        &$next_page,
        &$page,
        &$pages,
        $pdo,
        $previous_page,
        &$result,
        &$row,
        $statusnote,
        &$stmt,
        $telegramRoute,
        $textbotlang,
        $user
    ) {
        if (!in_array($telegramRoute, ['renewal_list', 'renewal_next', 'renewal_previous'], true)) {
            return true;
        }

        $firstPage = $telegramRoute === 'renewal_list';
        $eligibleStatuses = ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'];
        $statusList = "'" . implode("', '", $eligibleStatuses) . "'";
        $criteria = "WHERE id_user = :user_id AND Status IN ({$statusList})";
        $pageSize = 20;

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice {$criteria}");
            if ($stmt === false || !$stmt->execute([':user_id' => $from_id])) {
                $details = $stmt === false ? $pdo->errorInfo() : $stmt->errorInfo();
                throw new \RuntimeException('Could not count renewable services: ' . ($details[2] ?? 'unknown error'));
            }
            $count = $stmt->fetchColumn();
            $stmt->closeCursor();
            if ($count === false) {
                throw new \RuntimeException('Could not read renewable service count');
            }
            if ((int) $count === 0) {
                sendmessage($from_id, $textbotlang['users']['extend']['emptyServiceforExtend'], null, 'html');
                return;
            }

            $lastPage = intdiv((int) $count - 1, $pageSize) + 1;
            $currentPage = max(1, min($lastPage + 1, (int) ($user['pagenumber'] ?? 1)));
            $requestedPage = match ($telegramRoute) {
                'renewal_next' => $currentPage + 1,
                'renewal_previous' => $currentPage - 1,
                default => 1,
            };
            $page = max(1, min($lastPage, $requestedPage));
            $startIndex = ($page - 1) * $pageSize;

            $list = $pdo->prepare("SELECT * FROM invoice {$criteria}
                ORDER BY time_sell DESC, id_invoice DESC LIMIT :page_size OFFSET :page_offset");
            if ($list === false
                || !$list->bindValue(':user_id', (string) $from_id, \PDO::PARAM_STR)
                || !$list->bindValue(':page_size', $pageSize, \PDO::PARAM_INT)
                || !$list->bindValue(':page_offset', $startIndex, \PDO::PARAM_INT)
                || !$list->execute()) {
                $details = $list === false ? $pdo->errorInfo() : $list->errorInfo();
                throw new \RuntimeException('Could not read the renewal page: ' . ($details[2] ?? 'unknown error'));
            }
            $invoices = $list->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $error) {
            error_log('[RenewalList] ' . str_replace(["\r", "\n"], ' ', $error->getMessage()));
            sendmessage($from_id, '❌ دریافت فهرست سرویس‌های قابل تمدید ممکن نشد. لطفاً دوباره تلاش کنید.', null, 'HTML');
            return;
        }
        if ($invoices === []) {
            sendmessage($from_id, $textbotlang['users']['extend']['emptyServiceforExtend'], null, 'html');
            return;
        }

        if ($firstPage) {
            \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('extend', $from_id, $datain, $message_id, ['extendbtn']);
            $pages = 1;
        } elseif ($telegramRoute === 'renewal_next') {
            $next_page = $page;
        }

        $keyboardlists = ['inline_keyboard' => []];
        $showNote = (bool) $statusnote;
        $duplicateLabels = \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListUserDuplicateKeys($from_id, $eligibleStatuses, $showNote);
        foreach ($invoices as $row) {
            $data = $showNote ? ' | ' . ($row['note'] ?? '') : '';
            $label = \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListLabel($row, $duplicateLabels, $showNote);
            $keyboardlists['inline_keyboard'][] = [[
                'text' => "✨{$label}{$data}✨",
                'callback_data' => 'extend_' . $row['id_invoice'],
            ]];
        }

        $paginationButtons = [];
        if ($page < $lastPage) {
            $paginationButtons[] = [
                'text' => $textbotlang['users']['page']['next'],
                'callback_data' => 'next_page_extends',
                '_rxcolor' => 'subscription_list:renewal_next',
            ];
        }
        if ($page > 1) {
            $paginationButtons[] = [
                'text' => $textbotlang['users']['page']['previous'],
                'callback_data' => 'previous_page_extends',
                '_rxcolor' => 'subscription_list:renewal_previous',
            ];
        }
        if ($paginationButtons !== []) {
            $keyboardlists['inline_keyboard'][] = $paginationButtons;
        }

        $navStyles = is_array($_rx_nav_styles) ? $_rx_nav_styles : [];
        $backuser = [
            \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $navStyles),
        ];
        $keyboardlists['inline_keyboard'][] = $backuser;
        $keyboardJson = json_encode($keyboardlists);
        \Faoxima\Core\Database\Db::update('user', 'pagenumber', $page, 'id', $from_id);
        $message = $textbotlang['users']['extend']['selectOrderDirect'];
        if (!$firstPage || $datain === 'backorder') {
            Editmessagetext($from_id, $message_id, $message, $keyboardJson);
        } else {
            sendmessage($from_id, $message, $keyboardJson, 'html');
        }

        return true;
    }
}
