<?php

namespace Faoxima\Telegram\Handlers;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $admin_ids, $connect, $datain, $datatextbot, $errorreport, $extend, $first_name, $from_id, $message_id, $otherservice, $pdo, $setting, $telegramRoute, $text_inline, $textbotlang, $user, $username, $valurcashbackextend.
 * Inherited or exported by reference: $ManagePanel, $Response, $error, $id_invoice, $marzban_list_get, $nameloc, $pricelastextend, $prodcut, $randomString, $result, $status, $stmt, $textextend, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ConfirmRenewal
{
    public static function handle(
        &$ManagePanel,
        &$Response,
        $admin_ids,
        $connect,
        $datain,
        $datatextbot,
        &$error,
        $errorreport,
        $extend,
        $first_name,
        $from_id,
        &$id_invoice,
        &$marzban_list_get,
        $message_id,
        &$nameloc,
        $otherservice,
        $pdo,
        &$pricelastextend,
        &$prodcut,
        &$randomString,
        &$result,
        $setting,
        &$status,
        &$stmt,
        $telegramRoute,
        $text_inline,
        $textbotlang,
        &$textextend,
        $user,
        $username,
        &$value,
        $valurcashbackextend
    ) {
        switch ($telegramRoute) {
        case 'renewal_confirm':
            rxRemoveMessageKeyboard($from_id, $message_id);
            $partsdic = explode("_", $user['Processing_value_four']);
            $userdata = json_decode($user['Processing_value'], true);
            $id_invoice = $userdata['id_invoice'];
            $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "id_invoice", $id_invoice, "select");
            if (!is_array($nameloc) || (string) ($nameloc['id_user'] ?? '') !== (string) $from_id) {
                sendmessage($from_id, $datatextbot['dyn_errors_renewal_failed_restart'] ?? "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
                return;
            }
            $walletRef = substr(hash('sha256', 'telegram_renewal:' . $from_id . ':' . $id_invoice . ':' . $message_id), 0, 32);
            if (\Faoxima\Services\Fulfillment\WalletExtensions::blockingOperation(
                $pdo, (string) $from_id, $nameloc['username'], 'wallet_renewal', (string) $id_invoice, $walletRef
            ) !== null) {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_already_registered'), null, 'HTML');
                return;
            }
            $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
            if ($marzban_list_get['status_extend'] == "off_extend") {
                sendmessage($from_id, $datatextbot['dyn_errors_extend_unavailable_panel'] ?? "❌ امکان تمدید در این پنل وجود ندارد", null, 'html');
                return;
            }
            $eextraprice = json_decode($marzban_list_get['pricecustomvolume'], true);
            $custompricevalue = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_volume');
            $eextraprice = json_decode($marzban_list_get['pricecustomtime'], true);
            $customtimevalueprice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($eextraprice[$user['agent']], $marzban_list_get, 'custom_time');
            $randomString = bin2hex(random_bytes(2));
            if ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه" || \Faoxima\Services\Renewal\Plans::renewalIsCustomCode($userdata['code_product'] ?? '')) {
                $prodcut['code_product'] = "custom_volume";
                $prodcut['name_product'] = $nameloc['name_product'];
                try {
                    $prodcut['price_product'] = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price($marzban_list_get, $user['agent'], $userdata['data_limit'], $userdata['time']);
                } catch (InvalidArgumentException $e) {
                    sendmessage($from_id, $e->getMessage(), json_encode(['inline_keyboard' => [[['text' => \Faoxima\Telegram\Support\MessageTemplates::plain('common_btn_back'), 'callback_data' => 'backuser']]]]), 'HTML');
                    return;
                }
                $prodcut['Service_time'] = $userdata['time'];
                $prodcut['Volume_constraint'] = $userdata['data_limit'];
                $prodcut['inbounds'] = $marzban_list_get['inboundid'];
            } else {
                $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:service_location, Location) > 0 OR Location = '/all') AND code_product = :code_product AND (FIND_IN_SET(:agent, REPLACE(agent, ' ', '')) > 0 OR agent IN ('all', 'allusers'))");
                $stmt->execute([
                    ':service_location' => $nameloc['Service_location'],
                    ':code_product' => $userdata['code_product'],
                    ':agent' => $user['agent'],
                ]);
                $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            $pricelastextend = $prodcut['price_product'];
            if ($prodcut == false || !in_array($nameloc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'])) {
                sendmessage($from_id, $datatextbot['dyn_errors_renewal_failed_restart'] ?? "❌ تمدید با خطا مواجه گردید مراحل تمدید را مجددا انجام دهید.", null, 'HTML');
                return;
            }
            $fxRenewCustom = ($nameloc['name_product'] == "🛍 حجم دلخواه" || $nameloc['name_product'] == "⚙️ سرویس دلخواه" || \Faoxima\Services\Renewal\Plans::renewalIsCustomCode($userdata['code_product'] ?? ''));
            $fxRenewKinds = $fxRenewCustom ? ['custom_volume', 'custom_time'] : 'product';
            if (!$fxRenewCustom) {
                $prodcut = \Faoxima\Orders\Pricing\ExchangePricing::fxApplyToProduct($prodcut, $marzban_list_get);
            }
            $prodcut['price_product'] = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($prodcut['price_product'], $marzban_list_get, $fxRenewKinds);
            $pricelastextend = $prodcut['price_product'];
            $fxRenewRef = (string) $nameloc['id_invoice'] . '|' . (string) ($userdata['code_product'] ?? '');
            $fxRenewDiscounted = false;
            if ($datain == "confirmserdiscount") {
                $SellDiscountlimit = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
                if ($SellDiscountlimit != false) {
                    $pricelastextend = $partsdic[2];
                    $fxRenewDiscounted = true;
                    $fxRenewDiscountKey = \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('renew_discount', $fxRenewRef);
                    if (!\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteCheck($from_id, $fxRenewDiscountKey, $pricelastextend, $marzban_list_get, $fxRenewKinds)) {
                        $fxRecheck = \Faoxima\Orders\Discounts\DiscountRules::validateSell($pdo, (string) $partsdic[1], 'extend', (string) ($userdata['code_product'] ?? ''), (string) ($marzban_list_get['code_panel'] ?? ''), (string) ($prodcut['category'] ?? ''), $user);
                        if (empty($fxRecheck['ok'])) {
                            sendmessage($from_id, (string) ($fxRecheck['reason'] ?? $textbotlang['Admin']['Discount']['invalidcodedis']), null, 'HTML');
                            return;
                        }
                        $fxNewDiscounted = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(\Faoxima\Orders\Pricing\ExchangePricing::fxApplyDiscountRule($prodcut['price_product'], (string) $fxRecheck['value_type'], (float) $fxRecheck['value']), $marzban_list_get, $fxRenewKinds);
                        \Faoxima\Core\Database\Db::update("user", "Processing_value_four", "dis_" . $partsdic[1] . "_" . $fxNewDiscounted, "id", $from_id);
                        \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, $fxRenewDiscountKey, $fxNewDiscounted, $marzban_list_get, $fxRenewKinds);
                        sendmessage($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedText($fxNewDiscounted) . ($fxRenewCustom ? \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $userdata['data_limit'], $userdata['time'], $fxNewDiscounted) : ''), json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserdiscount"]]]]), 'HTML');
                        return;
                    }
                }
            }
            if (!$fxRenewDiscounted) {
                $fxRenewCheckAmount = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount(\Faoxima\Orders\Pricing\ExchangePricing::fxApplyUserDiscount($pricelastextend, $user['pricediscount']), $marzban_list_get, $fxRenewKinds);
                $fxRenewQuoteKey = \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteContextKey('renew', $fxRenewRef);
                if (!\Faoxima\Orders\Pricing\ExchangePricing::fxQuoteCheck($from_id, $fxRenewQuoteKey, $fxRenewCheckAmount, $marzban_list_get, $fxRenewKinds)) {
                    \Faoxima\Orders\Pricing\ExchangePricing::fxQuoteStore($from_id, $fxRenewQuoteKey, $fxRenewCheckAmount, $marzban_list_get, $fxRenewKinds);
                    sendmessage($from_id, \Faoxima\Orders\Pricing\ExchangePricing::fxPriceChangedText($fxRenewCheckAmount) . ($fxRenewCustom ? \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $userdata['data_limit'], $userdata['time'], $fxRenewCheckAmount) : ''), json_encode(['inline_keyboard' => [
                        [['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivce"]],
                        [['text' => $textbotlang['users']['backbtn'], 'callback_data' => "backuser"]],
                    ]]), 'HTML');
                    return;
                }
            }
            if (intval($user['pricediscount']) != 0) {
                $result = ($pricelastextend * $user['pricediscount']) / 100;
                $pricelastextend = $pricelastextend - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $pricelastextend = \Faoxima\Orders\Pricing\ExchangePricing::fxFinalizeAmount($pricelastextend, $marzban_list_get, $fxRenewKinds);
            $fxRenewBasePrice = $fxRenewCustom ? \Faoxima\Orders\Pricing\ExchangePricing::fxCustomBasePrice($marzban_list_get, $user['agent'], $userdata['data_limit'] ?? 0, $userdata['time'] ?? 0) : ($prodcut['fx_base_price'] ?? $prodcut['price_product']);
            $fxRenewSnapshot = \Faoxima\Orders\Pricing\ExchangePricing::fxPricingSnapshot($marzban_list_get, $fxRenewKinds, $fxRenewBasePrice, $pricelastextend, (float) $prodcut['price_product'] - (float) $pricelastextend, ['product' => (string) ($prodcut['code_product'] ?? '')]);
            if ($fxRenewCustom) {
                $fxRenewSnapshot = \Faoxima\Orders\Pricing\CustomDuration::fx_custom_snapshot($marzban_list_get, $user['agent'], $userdata['data_limit'], $userdata['time'], $pricelastextend, $fxRenewSnapshot);
            }
            $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $nameloc['username']);
            $rxConfirmSnapshot = \Faoxima\Services\Fulfillment\ProviderState::snapshotFromDatauser($DataUserOut, (string) ($marzban_list_get['type'] ?? ''));
            $rxReservationSelection = \Faoxima\Services\Renewal\Reservations::rxReservationUiEnabled($marzban_list_get) ? \Faoxima\Services\Renewal\ReservationCriteria::reservationPanelSelection($marzban_list_get) : null;
            $rxConfirmPreview = \Faoxima\Services\Renewal\ProviderRenewal::renewalPreviewForService($pdo, $ManagePanel, $marzban_list_get, (string) $nameloc['username'], $prodcut['Volume_constraint'], $prodcut['Service_time'], ['reservation' => $rxReservationSelection, 'snapshot' => $rxConfirmSnapshot, 'id_invoice' => (string) $nameloc['id_invoice'], 'stage' => 'telegram_confirm']);
            if (!empty($rxConfirmPreview['blocked'])) {
                sendmessage($from_id, (string) $rxConfirmPreview['text'], null, 'HTML');
                return;
            }
            if (is_array($rxReservationSelection)) {
                $rxSelectionCheck = \Faoxima\Services\Renewal\ReservationCriteria::reservationNormalizeSelection($rxReservationSelection, !empty($rxConfirmSnapshot['ok']) && !empty($rxConfirmSnapshot['exists']) ? $rxConfirmSnapshot : null, time());
                if (empty($rxSelectionCheck['ok'])) {
                    \Faoxima\Services\Renewal\RenewalLog::rx_renewal_log('telegram_confirm_selection', (string) $rxSelectionCheck['reason'], ['panel' => $marzban_list_get, 'username' => (string) $nameloc['username'], 'invoice_id' => (string) $nameloc['id_invoice'], 'invoice_status' => $nameloc['Status'] ?? null, 'method' => (string) ($marzban_list_get['Methodextend'] ?? ''), 'volume_gb' => $prodcut['Volume_constraint'], 'days' => $prodcut['Service_time'], 'snapshot' => $rxConfirmSnapshot, 'detail' => $rxSelectionCheck['detail'] ?? null, 'mutation' => 'none']);
                    sendmessage($from_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalReasonText((string) $rxSelectionCheck['reason'], $rxSelectionCheck), null, 'HTML');
                    return;
                }
            }
            if (\Faoxima\Orders\Checkout\Checkout::availableForUser($user) < $pricelastextend && $user['agent'] != "n2" && intval($pricelastextend) != 0) {
                $marzbandirectpay = \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($nameloc['Service_location'], 'directbuy') ? "ondirectbuy" : "offdirectbuy";
                if ($marzbandirectpay == "offdirectbuy") {
                    $minbalance = json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']];
                    $maxbalance = json_decode(\Faoxima\Core\Database\Db::select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']];
                    $minbalance = number_format($minbalance);
                    $maxbalance = number_format($maxbalance);
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
                    $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?,?, ?, ?,?,?,?)");
                    $dateacc = date('Y/m/d H:i:s');
                    $value = json_encode(array(
                        "volumebuy" => $prodcut['Volume_constraint'],
                        "Service_time" => $prodcut['Service_time'],
                        "oldvolume" => $DataUserOut['data_limit'],
                        "oldtime" => $DataUserOut['expire'],
                        'code_product' => \Faoxima\Services\Renewal\Plans::renewalNormalizeProductCode($prodcut['code_product']),
                        'id_order' => $randomString,
                        'reservation' => $rxReservationSelection
                    ));
                    $value = \Faoxima\Orders\Pricing\ExchangePricing::fxValueWithSnapshot($value, $fxRenewSnapshot);
                    $value = \Faoxima\Telegram\Support\ServiceProductIdentity::linkRenewal($value, (string) $nameloc['id_invoice'], $prodcut);
                    $type = "extend_user";
                    $status = "unpaid";
                    $extend = '';
                    $stmt->bind_param("ssssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'], $extend, $status);
                    $stmt->execute();
                    $stmt->close();
                    \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "{$nameloc['username']}%$randomString", "id", $from_id);
                    \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "getextenduser", "id", $from_id);
                    \Faoxima\Orders\Checkout\CheckoutRuntime::rx_ck_bot_begin($user, [
                        'operation' => 'getextenduser',
                        'op_key' => "getextenduser|{$nameloc['username']}%$randomString",
                        'target' => (string) $nameloc['username'],
                        'final_price' => $pricelastextend,
                        'message_id' => $message_id,
                        'one' => "{$nameloc['username']}%$randomString",
                        'tow' => 'getextenduser',
                        'four' => '',
                    ]);
                    return;
                }
            }
            $rxExtendDiscountRow = false;
            if ($datain == "confirmserdiscount") {
                $rxExtendDiscountRow = \Faoxima\Core\Database\Db::select("DiscountSell", "*", "codeDiscount", $partsdic[1], "select");
                if ($rxExtendDiscountRow != false) {
                    \Faoxima\Core\Database\Db::update('invoice', 'discount_code', $partsdic[1], 'id_invoice', $nameloc['id_invoice']);
                    \Faoxima\Core\Database\Db::update('invoice', 'discount_amount', (string)((float)($prodcut['price_product'] ?? 0) - (float)$pricelastextend), 'id_invoice', $nameloc['id_invoice']);
                    \Faoxima\Core\Database\Db::update('invoice', 'price_before_discount', (string)($prodcut['price_product'] ?? 0), 'id_invoice', $nameloc['id_invoice']);
                }
            }
            if ($user['agent'] == "f") {
                $valurcashbackextend = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "chashbackextend", "select")['value'];
            } else {
                $valurcashbackextend = json_decode(\Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "chashbackextend_agent", "select")['value'], true)[$user['agent']];
            }
            $renewCashbackEligible = !is_callable([\Faoxima\Cashback\Eligibility::class, 'shopCashbackEligible'])
                || \Faoxima\Cashback\Eligibility::shopCashbackEligible($pdo, "chashbackextend", $user['register'] ?? null, "getextenduser");
            $rxCashbackDiscount = 0;
            if ($renewCashbackEligible && intval($valurcashbackextend) != 0 and intval($pricelastextend) != 0) {
                $rxCashbackDiscount = ($prodcut['price_product'] * $valurcashbackextend) / 100;
                $pricelastextend = $pricelastextend - $rxCashbackDiscount;
                if ($pricelastextend < 0) {
                    $pricelastextend = 0;
                }
            }
            if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
                if (($user['Balance'] - $pricelastextend) < intval("-" . $user['maxbuyagent'])) {
                    sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
                    return;
                }
            }
            $discountCode = $fxRenewDiscounted ? (string) $partsdic[1] : '';
            $funding = \Faoxima\Services\Renewal\WalletRenewal::reserve(
                $pdo, ['id' => $from_id] + $user, $nameloc, $walletRef, (float) $pricelastextend, $discountCode, 'تمدید سرویس'
            );
            if (empty($funding['ok'])) {
                $textRenewalError = !empty($funding['blocking_operation'])
                    ? \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_already_registered')
                    : ($datatextbot['dyn_errors_concurrency_insufficient_stock'] ?? '❌ موجودی کافی نیست.');
                sendmessage($from_id, $textRenewalError, null, 'HTML');
                return;
            }
            if (!\Faoxima\Services\Renewal\WalletRenewal::claim($pdo, $funding)) {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_registration_pending'), null, 'HTML');
                return;
            }
            $rxExtendOptions = [
                'source' => 'wallet',
                'ref' => $walletRef,
                'op_id' => $walletRef,
                'id_user' => $from_id,
                'price' => $pricelastextend,
                'reservation' => is_array($rxReservationSelection) ? array_merge($rxReservationSelection, ['payment_ref' => $walletRef]) : null,
            ];
            try {
                $extend = \Faoxima\Services\Renewal\ProviderRenewal::apply(
                    $ManagePanel, $marzban_list_get, $prodcut, (string) $nameloc['username'], null, $rxExtendOptions
                );
            } catch (Throwable $error) {
                error_log('Wallet renewal provider result uncertain for ' . $rxExtendOptions['ref'] . ': ' . $error->getMessage());
                $extend = ['status' => false, 'code' => 'provider_exception'];
            }
            $outcome = \Faoxima\Services\Renewal\ProviderRenewal::walletRenewalOutcome($extend);
            $resultSaved = \Faoxima\Services\Renewal\WalletRenewal::result($pdo, $funding, $outcome, empty($extend['queued']));
            if (!$resultSaved) {
                sendmessage($from_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_registration_pending'), null, 'HTML');
                return;
            }
            $rxExtendPending = $outcome === 'pending';
            if ($outcome === 'refundable' || $outcome === 'uncertain') {
                if ($resultSaved && $outcome === 'refundable') {
                    try {
                        \Faoxima\Services\Renewal\WalletRenewal::refund($pdo, $funding, (string) $from_id, 'بازگشت وجه تمدید سرویس');
                    } catch (Throwable $error) {
                        $extend = ['status' => false, 'code' => 'refund_requires_review'];
                    }
                }
                $extend = is_array($extend) ? $extend : ['status' => false, 'code' => 'provider_result_uncertain'];
                $rxStockEmpty = ($extend['code'] ?? '') === 'manual_stock_empty';
                $rxQueuedExists = ($extend['code'] ?? '') === 'queued_renewal_exists';
                $extend['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extend['msg'] ?? null, $extend['detail'] ?? null);
                $textreports = "خطای تمدید سرویس
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>کد خطا : " . htmlspecialchars((string) ($extend['code'] ?? ''), ENT_QUOTES, 'UTF-8') . "</blockquote>
<blockquote>دلیل خطا : {$extend['msg']}</blockquote>";
                if ($rxStockEmpty) {
                    sendmessage($from_id, $datatextbot['dyn_errors_renewal_stock_empty'] ?? "❌ موجودی انبار برای این محصول تمام شده است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
                } elseif ($rxQueuedExists) {
                    sendmessage($from_id, $datatextbot['dyn_errors_queued_renewal_exists'] ?? "❌ یک رزرو اشتراک برای این سرویس در انتظار فعال‌سازی است. مبلغی از حساب شما کسر نشد.", null, 'HTML');
                } else {
                    sendmessage($from_id, \Faoxima\Services\Renewal\RenewalText::rxRenewalFailureText($extend, $datatextbot['dyn_errors_renewal_support_error'] ?? "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید"), null, 'HTML');
                }
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
            if ($rxCashbackDiscount > 0) {
                $rxCashbackGiftTpl = $datatextbot['dyn_rewards_cashback_gift'] ?? "تبریک 🎉\n📌 به عنوان هدیه تمدید مبلغ %s تومان حساب شما شارژ گردید";
                sendmessage($from_id, sprintf($rxCashbackGiftTpl, $rxCashbackDiscount), null, 'HTML');
            }
            $stmt = $connect->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output,status) VALUES (?, ?, ?, ?,?,?,?,?)");
            $dateacc = date('Y/m/d H:i:s');
            $value = json_encode(array(
                "volumebuy" => $prodcut['Volume_constraint'],
                "Service_time" => $prodcut['Service_time'],
                "oldvolume" => $DataUserOut['data_limit'],
                "oldtime" => $DataUserOut['expire'],
                'code_product' => \Faoxima\Services\Renewal\Plans::renewalNormalizeProductCode($prodcut['code_product']),
                'id_order' => $randomString,
                'reservation' => $rxReservationSelection
            ));
            $value = \Faoxima\Orders\Pricing\ExchangePricing::fxValueWithSnapshot($value, $fxRenewSnapshot);
            $value = \Faoxima\Telegram\Support\ServiceProductIdentity::linkRenewal($value, (string) $nameloc['id_invoice'], $prodcut);
            $type = "extend_user";
            $status = "paid";
            $extend_json = json_encode($extend);
            $stmt->bind_param("ssssssss", $from_id, $nameloc['username'], $value, $type, $dateacc, $prodcut['price_product'],$extend_json, $status);
            $stmt->execute();
            $stmt->close();
            if (!$rxExtendPending && empty($extend['queued'])) {
                if ($nameloc['name_product'] == "سرویس تست") {
                    \Faoxima\Core\Database\Db::update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
                    \Faoxima\Core\Database\Db::update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
                } elseif (\Faoxima\Services\Renewal\Plans::renewalNormalizeProductCode($prodcut['code_product'] ?? '') !== 'custom_volume' && \Faoxima\Services\Renewal\ProviderRenewal::renewalIsAppliedVerified($extend)) {
                    \Faoxima\Services\Renewal\ProviderRenewal::renewalSyncInvoiceName($nameloc['id_invoice'], $prodcut['name_product'] ?? '');
                }
            }
            \Faoxima\Orders\Discounts\DiscountRules::logSale($pdo, [
                'id_user' => $from_id,
                'id_invoice' => $id_invoice,
                'Service_location' => $nameloc['Service_location'],
                'kind' => 'renewal',
                'name_product' => $prodcut['name_product'],
                'amount' => $pricelastextend,
                'source' => 'bot',
            ]);
            if (intval($setting['scorestatus']) == 1 and !in_array($from_id, $admin_ids)) {
                $rxPointsEarnedTpl = $datatextbot['dyn_rewards_points_earned'] ?? "📌شما %s امتیاز جدید کسب کردید.";
                sendmessage($from_id, sprintf($rxPointsEarnedTpl, 2), null, 'html');
                $scorenew = $user['score'] + 2;
                \Faoxima\Core\Database\Db::update("user", "score", $scorenew, "id", $from_id);
            }
            $keyboardextendfnished = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backlist'], 'callback_data' => "backorder"],
                    ],
                    [
                        ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                    ]
                ]
            ]);
            $priceproductformat = number_format($pricelastextend);
            $balanceformatsell = number_format(\Faoxima\Core\Database\Db::select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
            $balanceformatsellbefore = number_format($user['Balance'], 0);
            $rxFmtProdcutPriceExtend = \Faoxima\Core\Support\Formatting::rxFormatToman($prodcut['price_product']);
            if (!empty($extend['queued'])) {
                $rxQueuedSuccessTpl = (string) ($extend['reservation']['confirmation'] ?? \Faoxima\Services\Renewal\RenewalText::rxRenewalText('reservation_confirmed'));
                $textextend = \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_queued_summary', ['service' => $nameloc['username'], 'product' => $prodcut['name_product'], 'price' => $priceproductformat], ['confirmation' => $rxQueuedSuccessTpl]);
            } else {
                $textextend = \Faoxima\Telegram\Support\MessageTemplates::get('service_renewal_success', ['service' => $nameloc['username'], 'product' => $prodcut['name_product'], 'price' => $priceproductformat]);
                $textextend = \Faoxima\Services\Renewal\RenewalText::rxRenewalDecorateSuccess($textextend, $extend, (string) $nameloc['username'], (string) ($marzban_list_get['name_panel'] ?? ''));
            }
            if ($fxRenewCustom) {
                $textextend .= \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text($marzban_list_get, $user['agent'], $userdata['data_limit'], $userdata['time'], $pricelastextend);
            }
            sendmessage($from_id, $textextend, $keyboardextendfnished, 'HTML');
            $timejalali = jdate('Y/m/d H:i:s');
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['Admin']['ManageUser']['mangebtnuser'], 'callback_data' => 'manageuser_' . $from_id],
                    ],
                ]
            ]);
            $text_report = "📣 جزئیات تمدید اکانت در ربات شما ثبت شد .

<blockquote>▫️آیدی عددی کاربر : <code>$from_id</code></blockquote>
<blockquote>▫️نام کاربری کاربر :@$username</blockquote>
<blockquote>▫️نام کاربری کانفیگ :{$nameloc['username']}</blockquote>
<blockquote>▫️نام کاربر : $first_name</blockquote>
<blockquote>▫️موقعیت سرویس سرویس : {$nameloc['Service_location']}</blockquote>
<blockquote>▫️نام محصول : {$prodcut['name_product']}</blockquote>
<blockquote>▫️حجم محصول : {$prodcut['Volume_constraint']}</blockquote>
<blockquote>▫️زمان محصول : {$prodcut['Service_time']}</blockquote>
<blockquote>▫️مبلغ تمدید : {$rxFmtProdcutPriceExtend} تومان</blockquote>
<blockquote>▫️موجودی قبل از خرید : $balanceformatsellbefore تومان</blockquote>
<blockquote>▫️موجودی بعد از خرید : $balanceformatsell تومان</blockquote>
<blockquote>▫️زمان خرید : $timejalali</blockquote>";
            if (strlen($setting['Channel_Report'] ?? '') > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $otherservice,
                    'text' => $text_report,
                    'parse_mode' => "HTML",
                    'reply_markup' => $Response
                ]);
            }
            if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
                \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('renewal', [
                    'user_id'    => $from_id,
                    'amount'     => $prodcut['name_product'],
                    'price'      => $priceproductformat,
                    'panel_name' => $nameloc['Service_location'] ?? '',
                    'category'   => $prodcut['category'] ?? '',
                ], $setting);
            }
            break;
        }
        
        return true;
    }
}
