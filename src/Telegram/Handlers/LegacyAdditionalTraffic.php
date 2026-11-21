<?php

namespace Faoxima\Telegram\Handlers;

use Throwable;

/**
 * Handler stage. Inherited by value: $ManagePanel, $backuser, $dataget, $datatextbot, $errorreport, $from_id, $message_id, $otherservice, $pdo, $setting, $step_payment, $telegramRoute, $text, $textbotlang, $update, $user.
 * Inherited or exported by reference: $back, $before, $error, $location, $marzban_list_get, $result.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class LegacyAdditionalTraffic
{
    public static function handle(
        $ManagePanel,
        &$back,
        $backuser,
        &$before,
        $dataget,
        $datatextbot,
        &$error,
        $errorreport,
        $from_id,
        &$location,
        &$marzban_list_get,
        $message_id,
        $otherservice,
        $pdo,
        &$result,
        $setting,
        $step_payment,
        $telegramRoute,
        $text,
        $textbotlang,
        $update,
        $user
    ) {
        switch ($telegramRoute) {
        case 'legacy_traffic_start':
            $usernamepanel = $dataget[1];
            $locations = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $dataget[2], "select");
            $location = $locations['name_panel'];
            $eextraprice = json_decode($locations['priceextravolume'], true);
            $extrapricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $locations, 'extra_volume');
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $usernamepanel, "id", $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $location, "id", $from_id);

            $textextra = sprintf($textbotlang['users']['Extra_volume']['enterextravolume'], $extrapricevalue);
            sendmessage($from_id, $textextra, $backuser, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getvolumeextras', $from_id);
            break;
        case 'legacy_traffic_amount':
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                sendmessage($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
                return;
            }
            if ($text < 1) {
                sendmessage($from_id, $textbotlang['users']['Extra_volume']['invalidprice'], $backuser, 'HTML');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
            $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
            $extrapricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
            $priceextra = $extrapricevalue * $text;
            $priceextra = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($priceextra, $marzban_list_get, 'extra_volume');
            \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStoreServer($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_volume_external', (string) $user['Processing_value'] . '|' . (string) $user['Processing_value_one']), $priceextra, $marzban_list_get, 'extra_volume', ['qty' => (int) $text]);
            $keyboardsetting = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextras_' . $priceextra],
                    ]
                ]
            ]);
            $priceextra = number_format($priceextra, 0);
            $extrapricevalues = number_format($extrapricevalue, 0);
            $textextra = sprintf($textbotlang['users']['Extra_volume']['extravolumeinvoice'], $extrapricevalues, $priceextra, $text);
            sendmessage($from_id, $textextra, $keyboardsetting, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 'legacy_traffic_confirm':
            $volume = $dataget[1];
            $fxExternalPanel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
            if (!is_array($fxExternalPanel)) {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            $fxExternalUnit = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman(json_decode((string) $fxExternalPanel['priceextravolume'], true)[$user['agent']] ?? null, $fxExternalPanel, 'extra_volume');
            $fxExternalVerify = \Faoxima\Orders\Pricing\ExchangePricing::fxExtraQuoteVerify($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('extra_volume_external', (string) $user['Processing_value'] . '|' . (string) $user['Processing_value_one']), $fxExternalUnit, $volume, $fxExternalPanel, 'extra_volume');
            if ($fxExternalVerify['status'] === 'missing') {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            if ($fxExternalVerify['status'] === 'changed') {
                sendmessage($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedText($fxExternalVerify['amount']), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['Extra_volume']['extracheck'], 'callback_data' => 'confirmaextras_' . $fxExternalVerify['amount']]]]]), 'HTML');
                return;
            }
            $fxExternalQty = $fxExternalVerify['qty'];
            $targetUsername = (string) $user['Processing_value'];
            $ownership = $pdo->prepare('SELECT id_user FROM invoice WHERE username = ? AND Service_location = ? AND id_user <> ? LIMIT 1');
            $ownership->execute([$targetUsername, $fxExternalPanel['name_panel'], $from_id]);
            if ($ownership->fetchColumn() !== false) {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'HTML');
                return;
            }
            if (\Faoxima\Orders\Checkout\Checkout::availableForUser($user) < $volume && $user['agent'] != "n2") {
                $marzbandirectpay = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($user['Processing_value_one'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
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
                        $result = ($volume * $user['pricediscount']) / 100;
                        $volume = $volume - $result;
                        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
                    }
                    $volume = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($volume, $fxExternalPanel, 'extra_volume');
                    $Balance_prim = $volume - \Faoxima\Orders\Checkout\Checkout::availableForUser($user);
                    \Faoxima\Core\Database\Db::update("user", "Processing_value", $Balance_prim, "id", $from_id);
                    \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                    sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
                    \Faoxima\Telegram\Navigation\Steps::step('get_step_payment', $from_id);
                    return;
                }
            }
            if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
                if (($user['Balance'] - $volume) < intval("-" . $user['maxbuyagent'])) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
                    return;
                }
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value_one'], "select");
            if ($marzban_list_get == false) {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'html');
                return;
            }
            $eextraprice = json_decode($marzban_list_get['priceextravolume'], true);
            $extrapricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'extra_volume');
            deletemessage($from_id, $message_id);
            if (intval($user['pricediscount']) != 0) {
                $result = ($volume * $user['pricediscount']) / 100;
                $volume = $volume - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $volume = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($volume, $marzban_list_get, 'extra_volume');

            $before = \Faoxima\Services\Fulfillment\ProviderState::panelSnapshot(
                $ManagePanel, (string) $marzban_list_get['name_panel'], $targetUsername, (string) ($marzban_list_get['type'] ?? '')
            );
            $data_limit_new = $fxExternalQty ?? (intval($volume) / intval($extrapricevalue));
            $order = [
                'operation_id' => 'tgextra_' . substr(hash('sha256', $from_id . ':' . $message_id . ':legacy_volume'), 0, 48),
                'invoice_id' => 'external:' . hash('sha256', $marzban_list_get['code_panel'] . ':' . $targetUsername),
                'ledger_invoice' => $targetUsername,
                'panel' => (string) $marzban_list_get['name_panel'],
                'kind' => 'volume',
                'amount' => $data_limit_new,
                'discount_code' => '',
                'before' => empty($before['exists']) ? null : \Faoxima\Services\Fulfillment\ProviderState::limits($before),
                'target' => !empty($before['exists']) ? \Faoxima\Services\Fulfillment\Adjustments::extraVolumeTarget($before, $data_limit_new) : null,
                'wallet_state' => 'charged',
            ];
            $reservation = \Faoxima\Services\Fulfillment\WalletExtensions::reserve(
                $pdo, ['id' => $from_id] + $user, $targetUsername, $order, 'extra_not_user', (float) $volume, (string) $user['agent'], 'خرید حجم اضافه'
            );
            if (empty($reservation['ok'])) {
                if (!empty($reservation['blocking_operation'])) {
                    try {
                        \Faoxima\Services\Fulfillment\WalletExtensions::reconcileUncertain(
                            $pdo, (string) $from_id, $reservation['blocking_operation'], $ManagePanel, $marzban_list_get
                        );
                    } catch (Throwable $error) {
                        error_log('Extension read-back could not be recorded: ' . $reservation['blocking_operation']);
                    }
                }
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'HTML');
                return;
            }
            $Balance_Low_user = $reservation['new_balance'];
            $execution = \Faoxima\Services\Fulfillment\WalletExtensions::applyReserved(
                $pdo, $reservation['id'], (string) $from_id, $order, $ManagePanel, $marzban_list_get, $targetUsername, $data_limit_new
            );
            $extra_volume = is_array($execution['result']) ? $execution['result'] : ['status' => false];
            $order = $execution['order'];
            if ($execution['outcome'] !== 'applied') {
                if ($execution['outcome'] === 'refundable') {
                    \Faoxima\Services\Fulfillment\WalletExtensions::refund(
                        $pdo, $reservation['id'], ['id' => $from_id], $order, (float) $volume, $extra_volume, 'بازگشت وجه خرید حجم اضافه'
                    );
                }
                $extra_volume['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extra_volume['msg'] ?? null, $extra_volume['detail'] ?? null);
                $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$user['Processing_value_one']}</blockquote>
<blockquote>نام کاربری سرویس : {$user['Processing_value']}</blockquote>
<blockquote>دلیل خطا : {$extra_volume['msg']}</blockquote>";
                sendmessage($from_id, $datatextbot['dyn_errors_extra_volume_purchase_error'] ?? "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
                if (strlen($setting['Channel_Report'] ?? '') > 0) {
                    telegram('sendmessage', [
                        'chat_id' => $setting['Channel_Report'],
                        'message_thread_id' => $errorreport,
                        'text' => $textreports,
                        'parse_mode' => "HTML"
                    ]);
                }
                return;
            }
            if (!\Faoxima\Services\Fulfillment\WalletExtensions::complete(
                $pdo, $reservation['id'], (string) $from_id, $order, [], $extra_volume
            )) {
                sendmessage($from_id, $textbotlang['users']['stateus']['error'], null, 'HTML');
                return;
            }
            $back = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['backbtn'], 'callback_data' => 'backuser'],
                    ]
                ]
            ]);
            sendmessage($from_id, $textbotlang['users']['extend']['thanks'], $back, 'HTML');
            $volumes = $fxExternalQty ?? $volume / $extrapricevalue;
            $volumes = number_format($volumes, 0);
            $text_report = sprintf($textbotlang['Admin']['reportgroup']['volumepurchase'], $from_id, $volumes, $volume, $user['Balance'], $user['Processing_value']);
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $text_report,
                    'parse_mode' => "HTML"
                ]);
            }
            if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
                \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('volume_topup', [
                    'user_id' => $from_id,
                    'amount'  => $volumes,
                    'price'   => number_format((float)$volume),
                ], $setting);
            }
            break;
        }

        return true;
    }
}
