<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $ManagePanel, $connect, $data_limit, $dataget, $dataoutput, $datatextbot, $errorreport, $from_id, $keyboard, $list_marzban_panel_userschange, $message_id, $otherservice, $pdo, $setting, $step_payment, $telegramRoute, $textbotlang, $user, $username.
 * Inherited or exported by reference: $configs, $day, $id_invoice, $marzban_list_get, $nameloc, $output, $prodcut, $product, $result, $stmt, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ChangeLocation
{
    public static function handle(
        $ManagePanel,
        &$configs,
        $connect,
        $data_limit,
        $dataget,
        $dataoutput,
        $datatextbot,
        &$day,
        $errorreport,
        $from_id,
        &$id_invoice,
        $keyboard,
        $list_marzban_panel_userschange,
        &$marzban_list_get,
        $message_id,
        &$nameloc,
        $otherservice,
        &$output,
        $pdo,
        &$prodcut,
        &$product,
        &$result,
        $setting,
        $step_payment,
        &$stmt,
        $telegramRoute,
        $textbotlang,
        $user,
        $username,
        &$value
    ) {
        switch ($telegramRoute) {
        case 'location_start':
            $id_invoice = $dataget[1];
            $limitchangeloc = json_decode($setting['limitnumber'], true);
            if ($user['limitchangeloc'] > $limitchangeloc['all'] and intval($setting['statuslimitchangeloc']) == 1) {
                sendmessage($from_id, $datatextbot['dyn_errors_location_change_limit_reached'] ?? "❌ محدودیت تغییر لوکیشن شما به پایان رسیده  است", null, 'html');
                return;
            }
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler changeloc on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'changeloc',
                    ]);
                }
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $nameloc['id_invoice'], "id", $from_id);
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if ($marzban_list_get['changeloc'] == "offchangeloc") {
                sendmessage($from_id, $datatextbot['dyn_errors_location_change_unavailable'] ?? "❌ این قابلیت درحال حاضر دردسترس نیست.", null, 'html');
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "Unsuccessful" || $DataUserOut['status'] == "disabled") {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            Editmessagetext($from_id, $message_id, $datatextbot['textselectlocation'], $list_marzban_panel_userschange);
            break;
        case 'location_select':
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $dataget[1], "id", $from_id);
            $limitchangeloc = json_decode($setting['limitnumber'], true);
            $userlimitlast = $limitchangeloc['all'] - $user['limitchangeloc'];
            $userlimitlastfree = $limitchangeloc['free'] - $user['limitchangeloc'];
            if ($userlimitlastfree < 0)
                $userlimitlastfree = 0;
            $Pricechange = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $dataget[1], "select")['priceChangeloc'];
            $textchange = \Faoxima\Telegram\Support\MessageTemplates::get('service_location_change_confirm', ['price' => \Faoxima\Core\Support\Formatting::rxFormatToman($Pricechange), 'limit_left' => $userlimitlast, 'free_left' => $userlimitlastfree]);
            $keyboardextend = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['change-location']['confirm'], 'callback_data' => "confirmchangeloccha_" . $user['Processing_value']],
                    ],
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $user['Processing_value']],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textchange, $keyboardextend);
            break;
        case 'location_confirm':
            $id_invoice = $dataget[1];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");

            if (!is_array($nameloc) || (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler confirmchangeloccha on non-owned invoice', [
                        'from_id' => $from_id, 'invoice' => $id_invoice, 'handler' => 'confirmchangeloccha',
                    ]);
                }
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            $marzban_list_get_new = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $limitchangeloc = json_decode($setting['limitnumber'], true);
            $limitfree = true;
            if ($user['limitchangeloc'] < $limitchangeloc['free'] and intval($setting['statuslimitchangeloc']) == 1) {
                $limitfree = false;
            }
            if ($user['limitchangeloc'] >= $limitchangeloc['all'] and intval($setting['statuslimitchangeloc']) == 1) {
                sendmessage($from_id, $datatextbot['dyn_errors_location_change_limit_reached'] ?? "❌ محدودیت تغییر لوکیشن شما به پایان رسیده  است", null, 'html');
                return;
            }
            if ($marzban_list_get_new['changeloc'] == "offchangeloc") {
                sendmessage($from_id, $datatextbot['dyn_errors_location_change_unavailable'] ?? "❌ این قابلیت درحال حاضر دردسترس نیست.", null, 'html');
                return;
            }
            if ($marzban_list_get_new == false) {
                sendmessage($from_id, $datatextbot['dyn_errors_generic_restart_process'] ?? "❌ خطایی رخ داده است لطفا مراحل مجددا انجام دهید", null, 'html');
                return;
            }
            $Pricechange = $marzban_list_get_new['priceChangeloc'];
            if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه") {
                $prodcut['code_product'] = "🛍 حجم دلخواه";
                $product['inbounds'] = null;
            } else {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND name_product = :name_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                $stmt->execute([
                    ':service_location' => $nameloc['Service_location'],
                    'name_product' => $nameloc['name_product'],
                    ':agent' => $user['agent'],
                ]);
                $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if ($product['inbounds'] != null) {
                $marzban_list_get_new['inboundid'] = $prodcut['inbounds'];
            }
            if ($marzban_list_get_new['type'] == "Manualsale" && $marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
                sendmessage($from_id, $datatextbot['dyn_errors_transfer_panel_unavailable'] ?? "❌ امکان انتقال به پنل وجود ندارد.", null, 'html');
                return;
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            if ($DataUserOut['status'] == "on_hold") {
                sendmessage($from_id, $datatextbot['dyn_errors_transfer_unused_config_only'] ?? "❌ کانفیگ شما در وضعیت استفاده نشده است و امکان انتقال موقعیت سرویس وجود ندارد.", null, 'html');
                return;
            }
            if ($DataUserOut['status'] != "active") {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            if ($limitfree == false) {
                $Pricechange = 0;
            }
            if (\Faoxima\Orders\Checkout\Checkout::availableForUser($user) < $Pricechange && $user['agent'] != "n2" && $limitfree) {
                $marzbandirectpay = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
                if ($marzbandirectpay == "offdirectbuy") {
                    $minbalance = number_format(json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
                    $maxbalance = number_format(json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
                    $bakinfos = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account", '_rxcolor' => 'navigation:account_back', '_rxsk' => 'backinfo', '_rxkeep' => true],
                            ]
                        ]
                    ]);
                    Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('getprice', $from_id);
                    return;
                } else {
                    if (intval($user['pricediscount']) != 0) {
                        $result = ($Pricechange * $user['pricediscount']) / 100;
                        $Pricechange = $Pricechange - $result;
                        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
                    }
                    if (intval($Pricechange) != 0) {
                        $Balance_prim = $Pricechange - \Faoxima\Orders\Checkout\Checkout::availableForUser($user);
                        \Faoxima\Core\Database\Db::update("user", "Processing_value", $Balance_prim, "id", $from_id);
                        \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                        Editmessagetext($from_id, $message_id, $textbotlang['users']['sell']['None-credit'], $step_payment);
                        \Faoxima\Telegram\Navigation\Steps::step('get_step_payment', $from_id);
                        return;
                    }
                }
            }
            if (intval($user['pricediscount']) != 0 and intval($Pricechange) != 0) {
                $result = ($Pricechange * $user['pricediscount']) / 100;
                $Pricechange = $Pricechange - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
                if (($user['Balance'] - $Pricechange) < intval("-" . $user['maxbuyagent'])) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
                    return;
                }
            }
            $keyboardextend = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            $value = json_encode(array(
                "old_panel" => $marzban_list_get['code_panel'],
                "new_panel" => $marzban_list_get_new['code_panel'],
                "volume" => $DataUserOut['data_limit'],
                "used_traffic" => $DataUserOut['used_traffic'],
                "expire" => $DataUserOut['expire'],
                "stateus" => $DataUserOut['status']
            ));
            $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price) VALUES (?, ?, ?, ?,?,?)");
            $dateacc = date('Y/m/d H:i:s');
            $type = "change_location";
            $stmt->bind_param("ssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product']);
            $stmt->execute();
            $stmt->close();
            if ($DataUserOut['data_limit'] == 0 || $DataUserOut['data_limit'] == null) {
                $data_limit = 0;
            } else {
                $data_limit = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
            }
            $datac = array(
                'expire' => $DataUserOut['expire'],
                'data_limit' => $data_limit,
                'from_id' => $from_id,
                'username' => $username,
                'type' => 'usertest',
                'ip_limit' => intval($nameloc['ip_limit'] ?? 0),
            );
            $expirationDate = $DataUserOut['expire']
                ? jdate('Y/m/d', $DataUserOut['expire'])
                : (($DataUserOut['status'] ?? '') === 'on_hold' ? $textbotlang['users']['stateus']['PendingFirstConnection'] : $textbotlang['users']['stateus']['Unlimited']);
            $timeDiff = $DataUserOut['expire'] - time();
            $day = $DataUserOut['expire'] ? floor($timeDiff / 86400) . $textbotlang['users']['stateus']['day'] : $textbotlang['users']['stateus']['Unlimited'];
            $output = $DataUserOut['data_limit'] - $DataUserOut['used_traffic'];
            $RemainingVolume = $DataUserOut['data_limit'] ? \Faoxima\Core\Support\Formatting::formatBytes($output) : "نامحدود";
            if ($marzban_list_get['url_panel'] == $marzban_list_get_new['url_panel']) {
                $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
                $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
            } else {
                $dataoutput = $ManagePanel->createUser($marzban_list_get_new['name_panel'], "usertest", $DataUserOut['username'], $datac);
                if ($dataoutput['username'] == null) {
                    $dataoutput['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($dataoutput['msg'] ?? null, $dataoutput['detail'] ?? null);
                    sendmessage($from_id, $textbotlang['users']['sell']['ErrorConfig'], $keyboard, 'HTML');
                    $texterros = "خطا هنگام تغییر موقعیت سرویس
<blockquote>دلیل خطا : {$dataoutput['msg']}</blockquote>
<blockquote>آیدی کابر : $from_id</blockquote>
<blockquote>نام کاربری کاربر : @$username</blockquote>
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام پنل مقصد : {$marzban_list_get_new['name_panel']}</blockquote>";
                    if (strlen($setting['Channel_Report'] ?? '') > 0) {
                        telegram('sendmessage', [
                            'chat_id' => $setting['Channel_Report'],
                            'message_thread_id' => $errorreport,
                            'text' => $texterros,
                            'parse_mode' => "HTML"
                        ]);
                    }
                    return;
                }
                $remove = $ManagePanel->RemoveUser($nameloc['Service_location'], $nameloc['username']);
            }
            $output_config_link = "";
            if ($marzban_list_get_new['sublink'] == "onsublink") {
                $output_config_link = $dataoutput['subscription_url'];
            }
            if ($marzban_list_get_new['config'] == "onconfig") {
                if (is_array($dataoutput['configs'])) {
                    $rxChangelocTextConfigs = method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'rxDeliveryTextConfigs') ? \Faoxima\Services\Panels\Drivers\JovadPanel::rxDeliveryTextConfigs($dataoutput['configs']) : $dataoutput['configs'];
                    foreach ($rxChangelocTextConfigs as $configs) {
                        $output_config_link .= "\n" . $configs;
                    }
                }
            }
            $limitnew = $user['limitchangeloc'] + 1;
            \Faoxima\Core\Database\Db::update("user", "limitchangeloc", $limitnew, "id", $from_id);
            $textchangeloc = \Faoxima\Telegram\Support\MessageTemplates::get('service_location_change_success', ['panel' => $marzban_list_get_new['name_panel'], 'username' => $nameloc['username'], 'volume' => $RemainingVolume, 'expire' => $expirationDate, 'days_left' => $day], ['link' => $output_config_link]);
            if (intval($Pricechange) != 0) {
                $__allowNegPc = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
                $__chargePc = is_callable([\Faoxima\Wallet\Balance\Balances::class, 'charge']) ? \Faoxima\Wallet\Balance\Balances::charge($pdo, $from_id, $Pricechange, $__allowNegPc) : ['ok' => false];
                if (empty($__chargePc['ok'])) {
                    sendmessage($from_id, $datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", null, 'HTML');
                    return;
                }
                $Balance_Low_user = $__chargePc['new_balance'];
                if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                    \Faoxima\Wallet\Ledger\Ledger::record($pdo, $from_id, 'debit', $Pricechange, 'service_action', 'تغییر موقعیت سرویس', null, 'invoice', (string)($nameloc['id_invoice'] ?? ''));
                }
            }
            \Faoxima\Core\Database\Db::update("invoice", "Service_location", $marzban_list_get_new['name_panel'], "username", $nameloc['username']);
            if ($marzban_list_get_new['inboundid'] != null) {
                \Faoxima\Core\Database\Db::update("invoice", "inboundid", $marzban_list_get_new['inboundid'], "username", $nameloc['username']);
            }
            Editmessagetext($from_id, $message_id, $textchangeloc, $keyboardextend);
            $balanceformatsell = number_format(\Faoxima\Core\Database\Db::select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
            $format_byte = \Faoxima\Core\Support\Formatting::formatBytes($data_limit);
            $textreport = "
تغییر موقعیت سرویس

<blockquote>🔻آیدی عددی : <code>$from_id</code></blockquote>
<blockquote>🔻نام کاربری : @$username</blockquote>
<blockquote>🔻نام پنل قدیم : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>🔻نام پنل جدید : {$marzban_list_get_new['name_panel']}</blockquote>
<blockquote>🔻 نام کاربری مشتری در پنل  :{$nameloc['username']}</blockquote>
<blockquote>🔻حجم نهایی سرویس : $format_byte</blockquote>
<blockquote>🔻موجودی کاربر : $balanceformatsell تومان</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $textreport,
                    'parse_mode' => "HTML"
                ]);
            }
            break;
        }

        return true;
    }
}
