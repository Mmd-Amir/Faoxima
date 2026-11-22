<?php

namespace Faoxima\Services\Fulfillment;

use PDO;
use Throwable;
use RuntimeException;

final class ServiceExtensions
{
    private static function prepareExtension(
        $pdo, &$ManagePanel, $nameloc, $marzban_list_get, $lookupUsername, $order_id, $rxToken,
        $rxPhase, &$rxBeforeState, &$rxTargetState, $operation, callable $targetFor
    ): array {
        $DataUserOut = $ManagePanel->DataUser($nameloc['Service_location'], $lookupUsername);
        $rxReconcilable = \Faoxima\Services\Fulfillment\Delivery::panelReconcilable($marzban_list_get);
        $rxSnap = \Faoxima\Services\Fulfillment\ProviderState::snapshotFromDatauser($DataUserOut);
        $rxApply = true;
        $result = null;
        if ($rxPhase === 'reconcile') {
            if (!$rxReconcilable || !is_array($rxBeforeState) || !is_array($rxTargetState)) {
                return ['stop' => \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, $operation . '_result_unverifiable', ['username' => (string) $nameloc['username']])];
            }
            $rxClass = \Faoxima\Services\Fulfillment\ProviderState::classify($rxSnap, $rxBeforeState, $rxTargetState);
            if ($rxClass === 'unknown') {
                return ['stop' => \Faoxima\Payments\Fulfillment\Settlement::defer($pdo, $order_id, $rxToken, $operation . '_panel_read_failed: ' . (string) ($rxSnap['error'] ?? ''))];
            }
            if ($rxClass === 'ambiguous') {
                return ['stop' => \Faoxima\Payments\Fulfillment\Settlement::manualReview($pdo, $order_id, $rxToken, $operation . '_panel_state_ambiguous', ['username' => (string) $nameloc['username']])];
            }
            if ($rxClass === 'target') {
                $rxApply = false;
                $result = ['status' => true, 'reconciled' => true];
            } elseif (!\Faoxima\Payments\Fulfillment\Settlement::markApplying($pdo, $order_id, $rxToken)) {
                return ['stop' => \Faoxima\Payments\Fulfillment\Claims::result('already_processing', 'ownership_lost')];
            }
        } else {
            $rxBeforeState = null;
            $rxTargetState = null;
            if ($rxReconcilable) {
                if (empty($rxSnap['ok'])) {
                    return ['stop' => \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, $operation . '_panel_read_failed', true)];
                }
                if (!empty($rxSnap['exists'])) {
                    $rxBeforeState = \Faoxima\Services\Fulfillment\ProviderState::limits($rxSnap);
                    $rxTargetState = $targetFor($rxBeforeState);
                }
            }
            if (!\Faoxima\Payments\Fulfillment\Settlement::prepare($pdo, $order_id, $rxToken, $rxBeforeState, $rxTargetState)) {
                return ['stop' => \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'fulfillment_prepare_failed', true)];
            }
        }
        if (is_array($rxBeforeState) && is_array($DataUserOut)) {
            $DataUserOut['data_limit'] = $rxBeforeState['data_limit'] ?? ($DataUserOut['data_limit'] ?? null);
            $DataUserOut['expire'] = $rxBeforeState['expire'] ?? ($DataUserOut['expire'] ?? null);
        }
        return ['apply' => $rxApply, 'result' => $result, 'before_data' => $DataUserOut];
    }

    private static function applyExtension(
        $pdo, &$ManagePanel, $nameloc, $panel, $lookupUsername, $order_id, $rxToken, $rxBeforeState,
        $rxTargetState, $operation, $amount, $rxApply, $result
    ): array {
        if ($rxApply) {
            try {
                $kind = $operation === 'extra_time' ? 'time' : 'volume';
                $result = Adjustments::apply($ManagePanel, $kind, $nameloc['username'], $panel['code_panel'], $amount, $rxTargetState);
            } catch (Throwable $rxPanelError) {
                return ['stop' => \Faoxima\Payments\Fulfillment\Settlement::reconcileLater($pdo, $order_id, $rxToken, $operation . '_panel_exception: ' . $rxPanelError->getMessage())];
            }
        }
        if (($result['status'] ?? false) == false && is_array($rxBeforeState) && is_array($rxTargetState)) {
            $rxClass = \Faoxima\Services\Fulfillment\ProviderState::classify(\Faoxima\Services\Fulfillment\ProviderState::panelSnapshot($ManagePanel, (string) $nameloc['Service_location'], (string) $lookupUsername), $rxBeforeState, $rxTargetState);
            if ($rxClass === 'target') {
                $result = ['status' => true, 'reconciled' => true];
            } elseif ($rxClass !== 'before') {
                return ['stop' => \Faoxima\Payments\Fulfillment\Settlement::reconcileLater($pdo, $order_id, $rxToken, $operation . '_result_unknown: ' . (is_scalar($result['msg'] ?? null) ? (string) $result['msg'] : ''))];
            }
        }
        return ['result' => $result];
    }

    public static function addVolume(array $context)
    {
        $order_id = $context['payment']['order_id'];
        $Payment_report = $context['payment']['report'];
        $steppay = $context['payment']['invoice_tag'];
        $Balance_id = $context['payment']['customer'];
        $rxCk = $context['payment']['checkout'];
        $rxToken = $context['fulfillment']['token'];
        $rxPhase = $context['fulfillment']['phase'];
        $rxBeforeState = $context['fulfillment']['before'];
        $rxTargetState = $context['fulfillment']['target'];
        $pdo =& $context['provider']['database'];
        $ManagePanel =& $context['provider']['panel'];
        $setting = $context['presentation']['settings'];
        $textbotlang =& $context['presentation']['language'];
        $format_price_cart = $context['presentation']['price_display'];
        $receipt = $context['presentation']['receipt'];
        $admin_ids = $context['reporting']['administrators'];
        $otherservice = $context['reporting']['extension'];
        $errorreport = $context['reporting']['error'];

        $steppay = explode("%", $steppay[1]);
        $volume = $steppay[1];
        $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != null) {
            $inboundid = $nameloc['inboundid'];
        }
        $ManagePanel = \Faoxima\Services\Panels\PanelSetup::rxEnsureManagePanel();
        if (!is_object($ManagePanel)) {
            if (function_exists('rx_log_event')) {
                rx_log_event('DIRECT_PAYMENT_NO_MANAGE_PANEL', 'ManagePanel unavailable; extra volume aborted without side effects', ['id_order' => $order_id]);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'manage_panel_unavailable', true);
        }
        $prepared = self::prepareExtension(
            $pdo, $ManagePanel, $nameloc, $marzban_list_get, $steppay[0], $order_id, $rxToken,
            $rxPhase, $rxBeforeState, $rxTargetState, 'extra_volume', static fn($before) => \Faoxima\Services\Fulfillment\Adjustments::extraVolumeTarget($before, $volume)
        );
        if (array_key_exists('stop', $prepared)) {
            return $prepared['stop'];
        }
        $DataUserOut = $prepared['before_data'];
        $data_for_database = json_encode(array(
            'volume_value' => $volume,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_user";
        $applied = self::applyExtension(
            $pdo, $ManagePanel, $nameloc, $marzban_list_get, $steppay[0], $order_id, $rxToken, $rxBeforeState,
            $rxTargetState, 'extra_volume', $volume, $prepared['apply'], $prepared['result']
        );
        if (array_key_exists('stop', $applied)) {
            return $applied['stop'];
        }
        $extra_volume = $applied['result'];
        if ($extra_volume['status'] == false) {
            $__refundVx = \Faoxima\Payments\Settlement\Refunds::paymentOnce($pdo, $Payment_report['id_order'], $Balance_id['id'], $Payment_report['price'], 'بازگشت وجه - خطا در خرید حجم اضافه', (string)($nameloc['id_invoice'] ?? ''));
            if ($__refundVx === 'duplicate') {
                return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'extra_volume_failed', $__refundVx);
            }
            $extra_volume['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extra_volume['msg'] ?? null, $extra_volume['detail'] ?? null);
            $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_volume['msg']}</blockquote>";
            sendmessage($nameloc['id_user'], \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_extra_volume_purchase_error', "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید"), null, 'HTML');
            if ($__refundVx === 'refunded') {
                sendmessage($nameloc['id_user'], \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_traffic_failed_refunded', ['amount' => \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price'])]), null, 'HTML');
            }
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'extra_volume_failed', $__refundVx);
        }
        $rxCompletion = \Faoxima\Payments\Fulfillment\Settlement::complete($pdo, $order_id, $rxToken, ['operation' => 'getextravolumeuser', 'panel' => $extra_volume, 'target' => $rxTargetState], \Faoxima\Orders\Checkout\Settlement::settlementStatements($rxCk, $order_id, $Balance_id['id'], -1, 'خرید حجم اضافه'));
        if ($rxCompletion !== 'completed') {
            return \Faoxima\Payments\Fulfillment\Settlement::completionOutcome($pdo, $order_id, $rxToken, $rxCompletion);
        }
        \Faoxima\Orders\Discounts\DiscountRules::logSale($pdo, [
            'id_user' => $Balance_id['id'],
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'Service_location' => $nameloc['Service_location'],
            'kind' => 'volume',
            'name_product' => $nameloc['name_product'] ?? null,
            'amount' => $Payment_report['price'],
            'source' => 'bot',
        ]);
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode($extra_volume));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price'], 0);
        $rxFmtBalanceBeforeVolume = \Faoxima\Core\Support\Formatting::rxFormatToman($Balance_id['Balance']);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_score_earned_1', "📌شما 1 امتیاز جدید کسب کردید."), null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            \Faoxima\Core\Database\Db::update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textvolume = "✅ افزایش حجم برای سرویس شما با موفقیت صورت گرفت
 
▫️نام سرویس  : {$steppay[0]}
▫️حجم اضافه : $volume گیگ

▫️مبلغ افزایش حجم : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textvolume, $keyboardextrafnished, 'HTML');
        $volumes = $volume;
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید حجم اضافه
🛍 حجم خریداری شده  : $volumes گیگ
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل ازافزایش موجودی : {$rxFmtBalanceBeforeVolume}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            ReceiptMessage::updateReceipt($receipt, $textconfrom);
        }
        \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر حجم اضافه خریده است

<blockquote>🪪 آیدی عددی : {$Balance_id['id']}</blockquote>
<blockquote>🛍 حجم خریداری شده  : $volumes گیگ</blockquote>
<blockquote>💰 مبلغ پرداختی : " . \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price']) . " تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ {$steppay[0]}</blockquote>
<blockquote>موجودی کاربر قبل خرید : {$rxFmtBalanceBeforeVolume}</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $otherservice,
                'text' => $text_report,
                'parse_mode' => "HTML"
            ]);
        }
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
            \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('volume_topup', [
                'user_id' => $Balance_id['id'],
                'amount'  => $volumes,
                'price'   => $volumesformat,
            ], $setting);
        }
        return \Faoxima\Payments\Fulfillment\Claims::result('completed');
    }

    public static function addTime(array $context)
    {
        $order_id = $context['payment']['order_id'];
        $Payment_report = $context['payment']['report'];
        $steppay = $context['payment']['invoice_tag'];
        $Balance_id = $context['payment']['customer'];
        $rxCk = $context['payment']['checkout'];
        $rxToken = $context['fulfillment']['token'];
        $rxPhase = $context['fulfillment']['phase'];
        $rxBeforeState = $context['fulfillment']['before'];
        $rxTargetState = $context['fulfillment']['target'];
        $pdo =& $context['provider']['database'];
        $ManagePanel =& $context['provider']['panel'];
        $setting = $context['presentation']['settings'];
        $textbotlang =& $context['presentation']['language'];
        $format_price_cart = $context['presentation']['price_display'];
        $receipt = $context['presentation']['receipt'];
        $admin_ids = $context['reporting']['administrators'];
        $otherservice = $context['reporting']['extension'];
        $errorreport = $context['reporting']['error'];

        $steppay = explode("%", $steppay[1]);
        $tmieextra = $steppay[1];
        $nameloc = \Faoxima\Core\Database\Db::select("invoice", "*", "username", $steppay[0], "select");
        $marzban_list_get = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $nameloc['Service_location'], "select");
        $Balance_Low_user = 0;
        $inboundid = $marzban_list_get['inboundid'];
        if ($nameloc['inboundid'] != false) {
            $inboundid = $nameloc['inboundid'];
        }
        $ManagePanel = \Faoxima\Services\Panels\PanelSetup::rxEnsureManagePanel();
        if (!is_object($ManagePanel)) {
            if (function_exists('rx_log_event')) {
                rx_log_event('DIRECT_PAYMENT_NO_MANAGE_PANEL', 'ManagePanel unavailable; extra time aborted without side effects', ['id_order' => $order_id]);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::abort($pdo, $order_id, $rxToken, 'manage_panel_unavailable', true);
        }
        $prepared = self::prepareExtension(
            $pdo, $ManagePanel, $nameloc, $marzban_list_get, $steppay[0], $order_id, $rxToken,
            $rxPhase, $rxBeforeState, $rxTargetState, 'extra_time', static fn($before) => \Faoxima\Services\Fulfillment\Adjustments::extraTimeTarget($before, $tmieextra, time())
        );
        if (array_key_exists('stop', $prepared)) {
            return $prepared['stop'];
        }
        $DataUserOut = $prepared['before_data'];
        $data_for_database = json_encode(array(
            'day' => $tmieextra,
            'old_volume' => $DataUserOut['data_limit'],
            'expire_old' => $DataUserOut['expire']
        ));
        $dateacc = date('Y/m/d H:i:s');
        $type = "extra_time_user";
        $timeservice = $DataUserOut['expire'] - time();
        $day = floor($timeservice / 86400);
        $applied = self::applyExtension(
            $pdo, $ManagePanel, $nameloc, $marzban_list_get, $steppay[0], $order_id, $rxToken, $rxBeforeState,
            $rxTargetState, 'extra_time', $tmieextra, $prepared['apply'], $prepared['result']
        );
        if (array_key_exists('stop', $applied)) {
            return $applied['stop'];
        }
        $extra_time = $applied['result'];
        if ($extra_time['status'] == false) {
            $__refundEt = \Faoxima\Payments\Settlement\Refunds::paymentOnce($pdo, $Payment_report['id_order'], $Payment_report['id_user'], $Payment_report['price'], 'بازگشت وجه - خطا در خرید زمان اضافه', (string)($nameloc['id_invoice'] ?? ''));
            if ($__refundEt === 'duplicate') {
                return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'extra_time_failed', $__refundEt);
            }
            if ($__refundEt === 'refunded') {
                sendmessage($Payment_report['id_user'], \Faoxima\Telegram\Support\MessageTemplates::get('service_extra_time_failed_refunded', ['amount' => \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price'])]), null, 'HTML');
            }
            $extra_time['msg'] = \Faoxima\Services\Panels\PanelMessages::rx_panel_error_text($extra_time['msg'] ?? null, $extra_time['detail'] ?? null);
            $textreports = "خطای خرید حجم اضافه
<blockquote>نام پنل : {$marzban_list_get['name_panel']}</blockquote>
<blockquote>نام کاربری سرویس : {$nameloc['username']}</blockquote>
<blockquote>دلیل خطا : {$extra_time['msg']}</blockquote>";
            sendmessage($context['presentation']['request_user'], \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_extra_volume_purchase_error', "❌خطایی در خرید حجم اضافه سرویس رخ داده با پشتیبانی در ارتباط باشید"), null, 'HTML');
            if (strlen($setting['Channel_Report']) > 0) {
                telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $errorreport,
                    'text' => $textreports,
                    'parse_mode' => "HTML"
                ]);
            }
            return \Faoxima\Payments\Fulfillment\Settlement::fail($pdo, $order_id, $rxToken, 'extra_time_failed', $__refundEt);
        }
        $rxCompletion = \Faoxima\Payments\Fulfillment\Settlement::complete($pdo, $order_id, $rxToken, ['operation' => 'getextratimeuser', 'panel' => $extra_time, 'target' => $rxTargetState], \Faoxima\Orders\Checkout\Settlement::settlementStatements($rxCk, $order_id, $Balance_id['id'], -1, 'خرید زمان اضافه'));
        if ($rxCompletion !== 'completed') {
            return \Faoxima\Payments\Fulfillment\Settlement::completionOutcome($pdo, $order_id, $rxToken, $rxCompletion);
        }
        \Faoxima\Orders\Discounts\DiscountRules::logSale($pdo, [
            'id_user' => $Balance_id['id'],
            'id_invoice' => $nameloc['id_invoice'] ?? null,
            'Service_location' => $nameloc['Service_location'],
            'kind' => 'time',
            'name_product' => $nameloc['name_product'] ?? null,
            'amount' => $Payment_report['price'],
            'source' => 'bot',
        ]);
        $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username,value,type,time,price,output) VALUES (:id_user,:username,:value,:type,:time,:price,:output)");
        $stmt->bindParam(':id_user', $Balance_id['id']);
        $stmt->bindParam(':username', $steppay[0]);
        $stmt->bindParam(':value', $data_for_database);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':time', $dateacc);
        $stmt->bindParam(':price', $Payment_report['price']);
        $stmt->bindParam(':output', json_encode($extra_time));
        $stmt->execute();
        $keyboardextrafnished = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $textbotlang['users']['stateus']['backservice'], 'callback_data' => "product_" . $nameloc['id_invoice']],
                ]
            ]
        ]);
        $volumesformat = number_format($Payment_report['price']);
        $rxFmtBalanceBeforeTime = \Faoxima\Core\Support\Formatting::rxFormatToman($Balance_id['Balance']);
        if (intval($setting['scorestatus']) == 1 and !in_array($Balance_id['id'], $admin_ids)) {
            sendmessage($Balance_id['id'], \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_score_earned_1', "📌شما 1 امتیاز جدید کسب کردید."), null, 'html');
            $scorenew = $Balance_id['score'] + 1;
            \Faoxima\Core\Database\Db::update("user", "score", $scorenew, "id", $Balance_id['id']);
        }
        $textextratime = "✅ افزایش زمان برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : {$steppay[0]}
▫️زمان اضافه : $tmieextra روز

▫️مبلغ افزایش زمان : $volumesformat تومان";
        sendmessage($Balance_id['id'], $textextratime, $keyboardextrafnished, 'HTML');
        if ($Payment_report['Payment_Method'] == "cart to cart" or $Payment_report['Payment_Method'] == "arze digital offline") {
            $volumes = $tmieextra;
            $textconfrom = "✅ پرداخت تایید شده
🔋 خرید زمان اضافه
🛍 زمان خریداری شده  : $volumes روز
👤 نام کاربری کانفیگ {$steppay[0]}
👤 شناسه کاربر: <code>{$Balance_id['id']}</code>
🛒 کد پیگیری پرداخت: {$Payment_report['id_order']}
⚜️ نام کاربری: @{$Balance_id['username']}
💎 موجودی قبل ازافزایش موجودی : {$rxFmtBalanceBeforeTime}
💸 مبلغ پرداختی: $format_price_cart تومان
";
            ReceiptMessage::updateReceipt($receipt, $textconfrom);
        }
        \Faoxima\Core\Database\Db::update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        $text_report = "⭕️ یک کاربر زمان اضافه خریده است

<blockquote>🪪 آیدی عددی : {$Balance_id['id']}</blockquote>
<blockquote>🛍 زمان خریداری شده  : $volumes روز</blockquote>
<blockquote>💰 مبلغ پرداختی : " . \Faoxima\Core\Support\Formatting::rxFormatToman($Payment_report['price']) . " تومان</blockquote>
<blockquote>👤 نام کاربری کانفیگ {$steppay[0]}</blockquote>";
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'parse_mode' => 'HTML',
                'message_thread_id' => $otherservice,
                'text' => $text_report,
            ]);
        }
        if (method_exists(\Faoxima\Telegram\Support\Texts::class, 'faoxima_public_purchase_log_event')) {
            \Faoxima\Telegram\Support\Texts::faoxima_public_purchase_log_event('time_extra', [
                'user_id' => $Balance_id['id'],
                'amount'  => $tmieextra,
                'price'   => $volumesformat,
            ], $setting);
        }
        return \Faoxima\Payments\Fulfillment\Claims::result('completed');
    }
}
