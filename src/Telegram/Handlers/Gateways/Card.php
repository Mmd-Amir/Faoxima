<?php

namespace Faoxima\Telegram\Handlers\Gateways;

use Faoxima\Payments\Receipts\ReceiptOrders;
use Throwable;

/**
 * Handler stage. Inherited by value: $_cc_insert_err, $_rx_payrcpt_styles, $admin_ids, $backuser, $callback_query_id, $connect, $datatextbot, $from_id, $keyboard, $pdo, $setting, $textbotlang, $user.
 * Inherited or exported by reference: $data, $invoice, $message_id, $randomString, $sendresidcart, $stmt, $textcart.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Card
{
    public static function handle(
        $_cc_insert_err,
        $_rx_payrcpt_styles,
        $admin_ids,
        $backuser,
        $callback_query_id,
        $connect,
        &$data,
        $datatextbot,
        $from_id,
        &$invoice,
        $keyboard,
        &$message_id,
        $pdo,
        &$randomString,
        &$sendresidcart,
        $setting,
        &$stmt,
        $textbotlang,
        &$textcart,
        $user
    ) {
        $from_id_sql = (string) $from_id;
        $lockName = ReceiptOrders::cardCreationLockName($from_id_sql);
        $lockAcquired = false;

        try {
                $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 5)');
                $lockStmt->execute([$lockName]);
                $lockAcquired = (int) $lockStmt->fetchColumn() === 1;
                if (!$lockAcquired) {
                    sendmessage($from_id, '⏳ درخواست پرداخت شما در حال ثبت است. لطفاً پیام پرداخت را بررسی کنید و در صورت نیاز کمی بعد دوباره تلاش کنید.', null, 'HTML');
                    return;
                }
                $_cc_keep_invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $_sbv_reuse = is_callable([\Faoxima\Payments\Receipts\ReceiptOrders::class, 'findReusable']) ? \Faoxima\Payments\Receipts\ReceiptOrders::findReusable($pdo, $from_id_sql, $_cc_keep_invoice, $user['Processing_value']) : null;
                if ($_sbv_reuse === null && method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_active_payment')) {
                    $_sbv_active = \Faoxima\Payments\Receipts\ReceiptOrders::activePayment($pdo, $from_id_sql);
                    if ($_sbv_active !== null) {
                        \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_bot_pending_notice($from_id, $message_id, $_sbv_active);
                        return;
                    }
                }
                if ($_sbv_reuse === null) {
                    $activeReport = ReceiptOrders::findActiveCardReport($pdo, $from_id_sql, $_cc_keep_invoice);
                    if ($activeReport === null) {
                        $stale_cutoff = date('Y/m/d H:i:s', time() - 15 * 60);
                        \Faoxima\Payments\Receipts\Cancellation::cancelAbandonedCardPaymentsForUser($pdo, $from_id_sql, 'stale_unpaid', [
                            'methods' => ['cart to cart'],
                            'created_before' => $stale_cutoff,
                            'preserve_invoice_key' => $_cc_keep_invoice,
                        ]);
                        \Faoxima\Payments\Receipts\Cancellation::cancelAbandonedCardPaymentsForUser($pdo, $from_id_sql, 'superseded', [
                            'methods' => ['cart to cart'],
                            'preserve_invoice_key' => $_cc_keep_invoice,
                        ]);
                        $activeReport = ReceiptOrders::findActiveCardReport($pdo, $from_id_sql);
                    }
                    if ($activeReport !== null) {
                        $orderCode = htmlspecialchars((string) $activeReport['id_order'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        sendmessage($from_id, $textbotlang['Admin']['SettingPayment']['issetpay'] . "\nکد پیگیری پرداخت فعال <code>{$orderCode}</code>", null, 'HTML');
                        return;
                    }
                }
                $mainbalance = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "minbalancecart", "select")['ValuePay'];
                $maxbalance = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "maxbalancecart", "select")['ValuePay'];
                if ($user['Processing_value'] < $mainbalance || $user['Processing_value'] > $maxbalance) {
                    $mainbalance = number_format($mainbalance);
                    $maxbalance = number_format($maxbalance);
                    sendmessage($from_id, sprintf($datatextbot['dyn_errors_min_max_deposit_amount'] ?? "❌ حداقل مبلغ واریزی این روش پرداخت باید %s و حداکثر %s تومان باشد", $mainbalance, $maxbalance), null, 'HTML');
                    return;
                }
                $_uid = intval($from_id);
                $_access = "((cn.is_active = 1 AND (NOT EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id) OR EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) OR (cn.is_active = 0 AND EXISTS (SELECT 1 FROM card_whitelist cw WHERE cw.card_id = cn.id AND cw.user_id = {$_uid}))) AND NOT EXISTS (SELECT 1 FROM user_card_block ucb WHERE ucb.card_id = cn.id AND ucb.user_id = {$_uid})";
                $_cmode_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = 'card_display_mode' LIMIT 1");
                $_cmode_row = $_cmode_res ? mysqli_fetch_assoc($_cmode_res) : null;
                $_cmode = is_array($_cmode_row) ? ($_cmode_row['ValuePay'] ?? 'random') : 'random';

                $card_info = null;
                $_card2 = null;

                if ($_cmode == 'direct') {
                    $_dq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY cn.created_at ASC LIMIT 2");
                    $_dcards = [];
                    if ($_dq) { while ($_r = mysqli_fetch_assoc($_dq)) { $_dcards[] = $_r; } }
                    $card_info = $_dcards[0] ?? null;
                    $_card2 = $_dcards[1] ?? null;
                } else {
                    $_ck = "card_cycle_{$_uid}";
                    $_ck_esc = mysqli_real_escape_string($connect, $_ck);
                    $_seen_res = mysqli_query($connect, "SELECT ValuePay FROM PaySetting WHERE NamePay = '{$_ck_esc}' LIMIT 1");
                    $_seen_row = $_seen_res ? mysqli_fetch_assoc($_seen_res) : null;
                    $_seen_str = is_array($_seen_row) ? ($_seen_row['ValuePay'] ?? '') : '';
                    $_seen = array_values(array_filter(array_map('intval', explode(',', $_seen_str))));
                    $_excl = !empty($_seen) ? "AND cn.id NOT IN (" . implode(',', $_seen) . ")" : "";

                    $_rq = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} {$_excl} ORDER BY RAND() LIMIT 1");
                    $card_info = $_rq ? mysqli_fetch_assoc($_rq) : null;

                    if (!$card_info) {
                        $_rq2 = mysqli_query($connect, "SELECT * FROM card_number cn WHERE {$_access} ORDER BY RAND() LIMIT 1");
                        $card_info = $_rq2 ? mysqli_fetch_assoc($_rq2) : null;
                        $_seen = [];
                    }

                    if ($card_info) {
                        $_new_seen = implode(',', array_unique(array_merge($_seen, [intval($card_info['id'])])));
                        $_nv_esc = mysqli_real_escape_string($connect, $_new_seen);
                        mysqli_query($connect, "UPDATE PaySetting SET ValuePay = '{$_nv_esc}' WHERE NamePay = '{$_ck_esc}'");
                        if (mysqli_affected_rows($connect) === 0) {
                            mysqli_query($connect, "INSERT INTO PaySetting (NamePay, ValuePay) VALUES ('{$_ck_esc}', '{$_nv_esc}')");
                        }
                    }
                }

                if (!$card_info || empty($card_info['cardnumber']) || empty($card_info['namecard'])) {
                    sendmessage($from_id, $datatextbot['dyn_wallet_no_active_card'] ?? "❌ کارت بانکی فعالی برای این روش پرداخت یافت نشد. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.", null, 'HTML');
                    return;
                }

                $card_number = $card_info['cardnumber'];
                $PaySettingname = $card_info['namecard'];
                $_sbv_new = false;
                if ($_sbv_reuse !== null) {
                    $randomString = (string) $_sbv_reuse['id_order'];
                } else {
                    $randomString = bin2hex(random_bytes(5));
                    if (is_callable([\Faoxima\Payments\Receipts\Settings::class, 'enabled']) && \Faoxima\Payments\Receipts\Settings::enabled($pdo)) {
                        $_sbv_prep = \Faoxima\Payments\Receipts\ReceiptOrders::prepare($pdo, $randomString, $from_id_sql, $user['Processing_value']);
                        if (empty($_sbv_prep['ok']) && ($_sbv_prep['reason'] ?? '') === 'active_exists') {
                            \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_bot_pending_notice($from_id, 0, $_sbv_prep['active']);
                            return;
                        }
                        if (empty($_sbv_prep['ok'])) {
                            $_sbv_err =($_sbv_prep['reason'] ?? '') === 'exhausted'
                                ? "❌ در حال حاضر امکان ساخت مبلغ یکتا برای این مبلغ وجود ندارد. لطفاً چند دقیقه دیگر دوباره تلاش کنید یا روش پرداخت دیگری انتخاب کنید."
                                : \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید');
                            sendmessage($from_id, $_sbv_err, $keyboard, 'HTML');
                            return;
                        }
                        $_sbv_new = true;
                    }
                }
                $_sbv_bot = (method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_bot_payment_view') && ($_sbv_new || $_sbv_reuse !== null)) ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_bot_payment_view($randomString) : null;
                $valueprice = $_sbv_bot !== null ? $_sbv_bot['valueprice'] : number_format($user['Processing_value']);
                $price_copy = $_sbv_bot !== null ? $_sbv_bot['price_copy'] : intval($user['Processing_value'] . "0");

                if ($_cmode == 'direct' && $_card2) {
                    $textcart = "💳 <b>کارت اول</b>\n🔢 <code>{$card_number}</code>\n👤 {$PaySettingname}\n\n💳 <b>کارت دوم</b>\n🔢 <code>{$_card2['cardnumber']}</code>\n👤 {$_card2['namecard']}\n\n💰 مبلغ: {$valueprice} تومان";
                } else {
                    $replacements = ['{price}' => $valueprice, '{card_number}' => $card_number, '{name_card}' => $PaySettingname];
                    $textcart = strtr($datatextbot['text_cart'], $replacements);
                }
                if ($_sbv_bot !== null) {
                    $textcart .= $_sbv_bot['block'];
                }
                $invoice = "{$user['Processing_value_tow']}|{$user['Processing_value_one']}";
                $dateacc = date('Y/m/d H:i:s');
                $payment_Status = "Unpaid";
                $Payment_Method = "cart to cart";
                $_cc_insert_ok = false;
                if ($_sbv_reuse !== null) {
                    $_cc_insert_ok = true;
                } else {
                    try {
                        $stmt = $connect->prepare("INSERT INTO Payment_report (id_user,id_order,time,price,payment_Status,Payment_Method,id_invoice) VALUES (?,?,?,?,?,?,?)");
                        if ($stmt) {
                            $stmt->bind_param("sssssss", $from_id, $randomString, $dateacc, $user['Processing_value'], $payment_Status, $Payment_Method, $invoice);
                            $_cc_insert_ok = $stmt->execute() && $stmt->affected_rows === 1;
                            $_cc_insert_err = $stmt->error;
                            $stmt->close();
                        } else {
                            $_cc_insert_err = $connect->error;
                        }
                    } catch (Throwable $_cc_insert_e) {
                        $_cc_insert_ok = false;
                        $_cc_insert_err = $_cc_insert_e->getMessage();
                    }
                }
                if (!$_cc_insert_ok && $_sbv_new) {
                    \Faoxima\Payments\Receipts\ReceiptOrders::abandon($pdo, $randomString);
                }
                if (!$_cc_insert_ok) {
                    if (function_exists('rx_log_event')) {
                        rx_log_event('CARD_PAYMENT_INSERT_FAILED', (string) ($_cc_insert_err ?? 'unknown'), [
                            'id_user' => $from_id,
                            'id_order' => $randomString,
                        ]);
                    }
                    sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
                    return;
                }
        } catch (Throwable $e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('CARD_PAYMENT_INIT_FAILED', $e->getMessage(), ['id_user' => $from_id_sql]);
            }
            sendmessage($from_id, \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_errors_purchase_or_payment_restart', '❌ خطایی رخ داده است لطفا مراحل خرید یا پرداخت  را مجدد انجام دهید'), $keyboard, 'HTML');
            return;
        } finally {
            if ($lockAcquired) {
                try {
                    $releaseStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $releaseStmt->execute([$lockName]);
                } catch (Throwable $e) {
                    if (function_exists('rx_log_event')) {
                        rx_log_event('CARD_PAYMENT_UNLOCK_FAILED', $e->getMessage(), ['id_user' => $from_id_sql]);
                    }
                }
            }
        }

                $_cv_active = !in_array($from_id, $admin_ids)
                    && ($setting['card_verify_status'] ?? 'offcardverify') === 'oncardverify'
                    && intval($user['card_verify_bypass'] ?? 0) !== 1;
                if ($_cv_active) {
                    $_cv_min = (int)($setting['card_verify_min_amount'] ?? 0);
                    if ($_cv_min > 0 && (int)$user['Processing_value'] < $_cv_min) {
                        $_cv_active = false;
                    }
                }
                if ($_cv_active) {
                    deletemessage($from_id, $message_id);
                    telegram('answerCallbackQuery', [
                        'callback_query_id' => $callback_query_id,
                        'text'              => '🔒 احراز هویت کارت‌به‌کارت فعال است',
                        'show_alert'        => true,
                    ]);
                    \Faoxima\Core\Database\Db::update("user", "Processing_value", $randomString, "id", $from_id);

                    $_vc_rows = [];
                    $_vc_result = $connect->prepare("SELECT last4 FROM verified_cards WHERE user_id = ? ORDER BY id DESC LIMIT 5");
                    $_vc_result->bind_param("s", $from_id);
                    $_vc_result->execute();
                    $_vc_res = $_vc_result->get_result();
                    while ($_vc_row = $_vc_res->fetch_assoc()) {
                        $_vc_rows[] = $_vc_row['last4'];
                    }
                    $_vc_result->close();

                    if (!empty($_vc_rows)) {
                        $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
                        $_vc_keyboard_rows = [];
                        foreach ($_vc_rows as $_vc_last4) {
                            $_vc_keyboard_rows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "💳 **** **** **** {$_vc_last4}", 'callback_data' => "cv_use_{$_vc_last4}"], 'cv_use', $_prcpt_s)];
                        }
                        $_vc_keyboard_rows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => "📷 پرداخت با کارت جدید", 'callback_data' => "cv_new"], 'cv_new', $_prcpt_s)];
                        $_sbv_cancel_row = ($_sbv_bot !== null && method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_cancel_row')) ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_cancel_row((string) $randomString) : null;
                        if ($_sbv_cancel_row !== null) {
                            $_vc_keyboard_rows[] = $_sbv_cancel_row;
                        }
                        $_vc_kb = json_encode(['inline_keyboard' => $_vc_keyboard_rows]);
                        \Faoxima\Telegram\Navigation\Steps::step('card_select_step', $from_id);
                        sendmessage($from_id, $datatextbot['dyn_wallet_select_verified_card'] ?? "💳 کارت‌های تاییدشده شما:\n\nیکی از کارت‌های زیر را انتخاب کنید یا با کارت جدید پرداخت کنید:", $_vc_kb, 'HTML');
                    } else {
                        \Faoxima\Telegram\Navigation\Steps::step('card_photo_step', $from_id);
                        sendmessage($from_id, $datatextbot['dyn_wallet_card_auth_active_notice'] ?? "⚠️ احراز هویت کارت به کارت فعال است\n\n📸 لطفا تصویر کارت فیزیکی خود را که قصد واریز با آن را دارید، ارسال کنید.\n\n🔘 نکته مهم: برای تایید تراکنش، الزامی است که ۴ رقم آخر شماره کارت و نام شما در تصویر کاملاً واضح و خوانا باشد. (سایر اطلاعات را می‌توانید بپوشانید).", ($_sbv_bot !== null && method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_cancel_keyboard') && \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_cancel_keyboard((string) $randomString) !== null) ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_cancel_keyboard((string) $randomString) : $backuser, 'HTML');
                    }
                    return;
                }

                deletemessage($from_id, $message_id);
                $_prcpt_s = (isset($_rx_payrcpt_styles) && is_array($_rx_payrcpt_styles)) ? $_rx_payrcpt_styles : [];
                $_sbv_receipt_label = $_sbv_bot !== null ? $_sbv_bot['receipt_label'] : "✅ پرداخت کردم | ارسال رسید.";
                if ($setting['statuscopycart'] == "1" || $_sbv_bot !== null) {
                    $_kb_rows = [[]];
                    if ($setting['statuscopycart'] == "1") {
                        $_kb_rows[0][] = ['text' => "کپی کارت اول", 'copy_text' => ["text" => $card_number], '_rxck' => 'card_num'];
                    }
                    $_kb_rows[0][] = ['text' => $_sbv_bot !== null ? $_sbv_bot['copy_label'] : "کپی مبلغ", 'copy_text' => ["text" => (string) $price_copy], '_rxck' => 'card_amount'];
                    if ($setting['statuscopycart'] == "1" && isset($_cmode) && $_cmode == 'direct' && !empty($_card2['cardnumber'])) {
                        $_kb_rows[] = [['text' => "کپی کارت دوم", 'copy_text' => ["text" => $_card2['cardnumber']], '_rxck' => 'card_num_2']];
                    }
                    $_kb_rows[] = [\Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $_sbv_receipt_label, 'callback_data' => "sendresidcart-" . $randomString], 'pay_sendreceipt', $_prcpt_s)];
                    $_sbv_cancel_row = ($_sbv_bot !== null && method_exists(\Faoxima\Payments\Receipts\ReceiptRuntime::class, 'rx_sbv_cancel_row')) ? \Faoxima\Payments\Receipts\ReceiptRuntime::rx_sbv_cancel_row((string) $randomString) : null;
                    if ($_sbv_cancel_row !== null) {
                        $_kb_rows[] = $_sbv_cancel_row;
                    }
                    $sendresidcart = json_encode(['inline_keyboard' => $_kb_rows]);
                } else {
                    $sendresidcart = json_encode([
                        'inline_keyboard' => [
                            [
                                \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => $_sbv_receipt_label, 'callback_data' => "sendresidcart-" . $randomString], 'pay_sendreceipt', $_prcpt_s)
                            ]
                        ]
                    ]);
                }
                $gethelp = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "helpcart", "select")['ValuePay'];
                if ($gethelp != 2) {
                    $data = json_decode($gethelp, true);
                    \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxSendPaymentGuide($from_id, $data, true);
                }
                $message_id = \Faoxima\Telegram\Media\SectionMedia::rx_send_banner_message($from_id, 'cart', $textcart, $sendresidcart, "html");
                \Faoxima\Payments\Settlement\PendingInvoices::updatePaymentMessageId($message_id, $randomString);

        return true;
    }
}
