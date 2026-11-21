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
        switch ($telegramRoute) {
        case 'renewal_list':
            $stmt = $pdo->prepare("SELECT * FROM invoice WHERE id_user = :id_user AND (status = 'active' OR status = 'end_of_time'  OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold')");
            $stmt->bindParam(':id_user', $from_id);
            $stmt->execute();
            $invoices = $stmt->rowCount();
            if ($invoices == 0) {
                sendmessage($from_id, $textbotlang['users']['extend']['emptyServiceforExtend'], null, 'html');
                return;
            }
            \Faoxima\Telegram\Media\SectionMedia::rx_section_media_enter('extend', $from_id, $datain, $message_id, ['extendbtn']);
            $pages = 1;
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $pages, "id", $from_id);
            $page = 1;
            $items_per_page = 20;
            $start_index = ($page - 1) * $items_per_page;
            $_start_index_i = (int)$start_index; $_p = (int)$items_per_page;
            $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
            $_stmt->bind_param("sii", $from_id, $_start_index_i, $_p);
            $_stmt->execute();
            $result = $_stmt->get_result();
            $_stmt->close();
            $keyboardlists = [
                'inline_keyboard' => [],
            ];
            $rxLabelDup = \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], (bool) $statusnote);
            if ($statusnote) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $data = "";
                    if ($row != null)
                        $data = " | {$row['note']}";
                    $keyboardlists['inline_keyboard'][] = [
                        [
                            'text' => "✨" . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListLabel($row, $rxLabelDup, true) . $data . "✨",
                            'callback_data' => "extend_" . $row['id_invoice']
                        ],
                    ];
                }
            } else {
                while ($row = mysqli_fetch_assoc($result)) {
                    $keyboardlists['inline_keyboard'][] = [
                        [
                            'text' => "✨" . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListLabel($row, $rxLabelDup, false) . "✨",
                            'callback_data' => "extend_" . $row['id_invoice']
                        ],
                    ];
                }
            }
            $pagination_buttons = [
                [
                    'text' => $textbotlang['users']['page']['next'],
                    'callback_data' => 'next_page_extends', '_rxcolor' => 'subscription_list:renewal_next'
                ]
            ];
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            $backuser = [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
            ];
            $keyboardlists['inline_keyboard'][] = $pagination_buttons;
            $keyboardlists['inline_keyboard'][] = $backuser;
            $keyboard_json = json_encode($keyboardlists);
            if ($datain == "backorder") {
                Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
            } else {
                sendmessage($from_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json, 'html');
            }
            break;
        case 'renewal_next':
            $numpage = \Faoxima\Core\Database\Db::select("invoice", "id_user", "id_user", $from_id, "count");
            $page = $user['pagenumber'];
            $items_per_page = 20;
            $sum = $user['pagenumber'] * $items_per_page;
            if ($sum > $numpage) {
                $next_page = 1;
            } else {
                $next_page = $page + 1;
            }
            $start_index = ($next_page - 1) * $items_per_page;
            $_si2 = (int)$start_index; $_p2 = (int)$items_per_page;
            $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
            $_stmt->bind_param("sii", $from_id, $_si2, $_p2);
            $_stmt->execute();
            $result = $_stmt->get_result();
            $_stmt->close();
            $keyboardlists = [
                'inline_keyboard' => [],
            ];
            $rxLabelDup = \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], (bool) $statusnote);
            if ($statusnote) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $data = "";
                    if ($row != null)
                        $data = " | {$row['note']}";
                    $keyboardlists['inline_keyboard'][] = [
                        [
                            'text' => "✨" . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListLabel($row, $rxLabelDup, true) . $data . "✨",
                            'callback_data' => "extend_" . $row['id_invoice']
                        ],
                    ];
                }
            } else {
                while ($row = mysqli_fetch_assoc($result)) {
                    $keyboardlists['inline_keyboard'][] = [
                        [
                            'text' => "✨" . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListLabel($row, $rxLabelDup, false) . "✨",
                            'callback_data' => "extend_" . $row['id_invoice']
                        ],
                    ];
                }
            }
            $pagination_buttons = [
                [
                    'text' => $textbotlang['users']['page']['next'],
                    'callback_data' => 'next_page_extends', '_rxcolor' => 'subscription_list:renewal_next'
                ],
                [
                    'text' => $textbotlang['users']['page']['previous'],
                    'callback_data' => 'previous_page_extends', '_rxcolor' => 'subscription_list:renewal_previous'
                ]
            ];
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            $backuser = [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
            ];
            $keyboardlists['inline_keyboard'][] = $pagination_buttons;
            $keyboardlists['inline_keyboard'][] = $backuser;
            $keyboard_json = json_encode($keyboardlists);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $next_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
            break;
        case 'renewal_previous':
            $numpage = \Faoxima\Core\Database\Db::select("invoice", "id_user", "id_user", $from_id, "count");
            $page = $user['pagenumber'];
            $items_per_page = 20;
            $sum = $user['pagenumber'] * $items_per_page;
            if ($sum > $numpage) {
                $previous_page = 1;
            } else {
                $previous_page = $page - 1;
            }
            $start_index = ($previous_page - 1) * $items_per_page;
            $_pp = (int)$previous_page; $_p = (int)$items_per_page;
            $_stmt = $connect->prepare("SELECT * FROM invoice WHERE id_user = ? AND (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR Status = 'send_on_hold') ORDER BY time_sell DESC LIMIT ?, ?");
            $_stmt->bind_param("sii", $from_id, $_pp, $_p);
            $_stmt->execute();
            $result = $_stmt->get_result();
            $_stmt->close();
            $keyboardlists = [
                'inline_keyboard' => [],
            ];
            $rxLabelDup = \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListUserDuplicateKeys($from_id, ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], (bool) $statusnote);
            if ($statusnote) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $data = "";
                    if ($row != null)
                        $data = " | {$row['note']}";
                    $keyboardlists['inline_keyboard'][] = [
                        [
                            'text' => "✨" . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListLabel($row, $rxLabelDup, true) . $data . "✨",
                            'callback_data' => "extend_" . $row['id_invoice']
                        ],
                    ];
                }
            } else {
                while ($row = mysqli_fetch_assoc($result)) {
                    $keyboardlists['inline_keyboard'][] = [
                        [
                            'text' => "✨" . \Faoxima\Services\Delivery\ServiceDelivery::rxServiceListLabel($row, $rxLabelDup, false) . "✨",
                            'callback_data' => "extend_" . $row['id_invoice']
                        ],
                    ];
                }
            }
            $pagination_buttons = [
                [
                    'text' => $textbotlang['users']['page']['next'],
                    'callback_data' => 'next_page_extends', '_rxcolor' => 'subscription_list:renewal_next'
                ],
                [
                    'text' => $textbotlang['users']['page']['previous'],
                    'callback_data' => 'previous_page_extends', '_rxcolor' => 'subscription_list:renewal_previous'
                ]
            ];
            $_rx_nav_s = (isset($_rx_nav_styles) && is_array($_rx_nav_styles)) ? $_rx_nav_styles : [];
            $backuser = [
                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'], 'backuser', $_rx_nav_s)
            ];
            $keyboardlists['inline_keyboard'][] = $pagination_buttons;
            $keyboardlists['inline_keyboard'][] = $backuser;
            $keyboard_json = json_encode($keyboardlists);
            \Faoxima\Core\Database\Db::update("user", "pagenumber", $previous_page, "id", $from_id);
            Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectOrderDirect'], $keyboard_json);
            break;
        }

        return true;
    }
}
