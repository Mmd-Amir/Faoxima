<?php

namespace Faoxima\Telegram\Support;

final class FeatureFunctionsHelpers
{
    public static function rxHelpTopicButtons($topics): array
{
    $rows = [];
    foreach ($topics as $topic) {
        $rows[] = [['text' => $topic['name_os'], 'callback_data' => "helpos_{$topic['id']}"]];
    }
    return $rows;
}

    public static function rxTicketMediaChoiceKeyboard($labels)
{
    return json_encode(['inline_keyboard' => [
        [
            ['text' => $labels['dyn_tickets_media_yes'] ?? '🖼 بله', 'callback_data' => 'tk_media_yes'],
            ['text' => $labels['dyn_tickets_media_no'] ?? '✏️ فقط متن', 'callback_data' => 'tk_media_no'],
        ],
        [['text' => $labels['dyn_tickets_cancel'] ?? '🔙 انصراف', 'callback_data' => 'tk_cancel']],
    ]], JSON_UNESCAPED_UNICODE);
}

    public static function rxTicketReportTargets($supportId, string $mainAdmin): array
{
    $targets = [];
    $supportId = (string) $supportId;
    if ($supportId !== '' && $supportId !== '0') {
        $targets[$supportId] = true;
    }
    if ($mainAdmin !== '' && $mainAdmin !== '0') {
        $targets[$mainAdmin] = true;
    }
    return array_keys($targets);
}

    public static function rxDeliverTicketReport($supportId, string $mainAdmin, string $channel, $topic, string $report, $keyboard, array $media): void
{
    foreach (self::rxTicketReportTargets($supportId, $mainAdmin) as $chatId) {
        sendmessage($chatId, $report, $keyboard, 'HTML');
        \Faoxima\Telegram\Conversation\InitializeHelpers::tk_album_send($chatId, null, $media);
    }
    if ($channel !== '') {
        telegram('sendmessage', ['chat_id' => $channel, 'message_thread_id' => $topic, 'text' => $report, 'parse_mode' => 'HTML']);
        \Faoxima\Telegram\Conversation\InitializeHelpers::tk_album_send($channel, $topic, $media);
    }
}

    public static function rxSendPurchaseDurationPrompt($fromId, $panel, $agent, $price, $min, $max, $backKeyboard): void
{
    $prompt = "⌛️ زمان سرویس خود را انتخاب نمایید
📌 تعرفه هر روز  : " . \Faoxima\Core\Support\Formatting::rxFormatToman($price) . "  تومان
⚠️ حداقل زمان $min روز  و حداکثر $max روز  می توانید تهیه کنید";
    if (\Faoxima\Orders\Pricing\CustomDuration::fx_duration_config($panel, $agent)['enabled']) {
        sendmessage($fromId, 'مدت سرویس را انتخاب کنید:', \Faoxima\Orders\Pricing\CustomDuration::fx_duration_keyboard($panel, $agent), 'HTML');
    } else {
        sendmessage($fromId, $prompt, $backKeyboard, 'html');
    }
}

    public static function rxTrialRequestedPanel($callback, $user): ?string
{
    if (preg_match('/^locationtest_(.+)$/', (string) $callback, $match)) {
        return $match[1];
    }
    return $user['step'] == 'createusertest' ? (string) $user['Processing_value_one'] : null;
}

    public static function rxPurchaseUsernameUsesRandomSuffix($method): bool
{
    return $method == 'نام کاربری دلخواه + عدد رندوم' || $method == 'متن دلخواه کاربر + رندوم';
}

    public static function rxPurchaseNeedsUsername($method, $customUsernameLabel): bool
{
    return $method == $customUsernameLabel || self::rxPurchaseUsernameUsesRandomSuffix($method);
}

    public static function rxSendPaymentGuide($fromId, $guide, bool $withMediaCaption = false): void
{
    if ($guide['type'] == 'text') {
        sendmessage($fromId, $guide['text'], null, 'HTML');
    } elseif ($guide['type'] == 'photo') {
        sendphoto($fromId, $guide['photoid'], $withMediaCaption ? $guide['text'] : null);
    } elseif ($guide['type'] == 'video') {
        sendvideo($fromId, $guide['videoid'], $withMediaCaption ? $guide['text'] : null);
    }
}

    public static function rxBotHandleTransferRecipient($from_id, $text, bool $hasMessage): bool
{
    if (!$hasMessage && empty($text)) { return false; }
    $trRecipientId = trim((string)$text);
    if (!ctype_digit($trRecipientId)) {
        sendmessage($from_id, "❌ آیدی عددی نامعتبر است. آیدی عددی تلگرام کاربر مقصد را مجددا ارسال کنید:", null, 'HTML');
        return false;
    }
    if ($trRecipientId === (string)$from_id) {
        sendmessage($from_id, "❌ نمی‌توانید به خودتان موجودی انتقال دهید. آیدی عددی دیگری ارسال کنید:", null, 'HTML');
        return false;
    }
    $trRecipientUser = \Faoxima\Core\Database\Db::select("user", "*", "id", $trRecipientId, "select");
    if (empty($trRecipientUser)) {
        sendmessage($from_id, "❌ کاربری با این آیدی یافت نشد. آیدی عددی تلگرام کاربر مقصد را مجددا ارسال کنید:", null, 'HTML');
        return false;
    }
    \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $trRecipientId, "id", $from_id);
    sendmessage($from_id, sprintf("💸 مبلغ مورد نظر برای انتقال را به تومان وارد کنید:\n✅ حداقل مبلغ %s تومان می‌باشد", number_format(RX_MIN_TRANSFER_AMOUNT)), null, 'HTML');
    \Faoxima\Telegram\Navigation\Steps::step('getTransferAmount', $from_id);
    return true;
}

    public static function rxBotHandleTransferAmount($from_id, $text, bool $hasMessage, $user, array $_rx_acc_s): bool
{
    if (!$hasMessage && empty($text)) { return false; }
    $trAmount = trim((string)$text);
    if (!ctype_digit($trAmount) || (int)$trAmount <= 0) {
        sendmessage($from_id, "❌ مبلغ نامعتبر است. مبلغ را به تومان و به صورت عدد ارسال کنید:", null, 'HTML');
        return false;
    }
    $trAmount = (int)$trAmount;
    if ($trAmount < RX_MIN_TRANSFER_AMOUNT) {
        sendmessage($from_id, sprintf("❌ مبلغ باید حداقل %s تومان باشد. مجددا ارسال کنید:", number_format(RX_MIN_TRANSFER_AMOUNT)), null, 'HTML');
        return false;
    }
    if ((float)$user['Balance'] < $trAmount) {
        sendmessage($from_id, "❌ موجودی کیف پول شما کافی نیست.", null, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        return false;
    }
    $trRecipientId = $user['Processing_value_one'];
    $trRecipientUser = \Faoxima\Core\Database\Db::select("user", "*", "id", $trRecipientId, "select");
    if (empty($trRecipientUser)) {
        sendmessage($from_id, "❌ کاربر مقصد دیگر معتبر نیست. عملیات لغو شد.", null, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        return false;
    }
    \Faoxima\Core\Database\Db::update("user", "Processing_value", $trAmount, "id", $from_id);
    $trOperationId = 'tr_' . bin2hex(random_bytes(16));
    \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", $trOperationId, "id", $from_id);
    $trRecipientLabel = trim((string)($trRecipientUser['username'] ?? '')) !== ''
        ? '@' . $trRecipientUser['username']
        : $trRecipientId;
    $trConfirmKb = json_encode(['inline_keyboard' => [
        [
            \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => '✅ تایید و انتقال', 'callback_data' => 'transferbal_confirm_' . $trOperationId], 'transferbal_confirm', $_rx_acc_s),
            \Faoxima\Telegram\Keyboard\LayoutFunctions::rx_kb_style(['text' => '❌ انصراف', 'callback_data' => 'transferbal_cancel'], 'transferbal_cancel', $_rx_acc_s),
        ],
    ]], JSON_UNESCAPED_UNICODE);
    sendmessage($from_id, sprintf("🔄 تایید انتقال موجودی\n\n👤 گیرنده: %s\n💰 مبلغ: %s تومان\n\nآیا از انجام این تراکنش اطمینان دارید؟", $trRecipientLabel, number_format($trAmount)), $trConfirmKb, 'HTML');
    \Faoxima\Telegram\Navigation\Steps::step('getTransferConfirm', $from_id);
    return true;
}

    public static function rxBotHandleTransferConfirmation($pdo, $from_id, $message_id, $datain, $user): bool
{
    $trOperationId = trim((string)($user['Processing_value_tow'] ?? ''));
    if (str_starts_with((string)$datain, 'transferbal_confirm_')) {
        if (!hash_equals($trOperationId, substr((string)$datain, strlen('transferbal_confirm_')))) {
            return false;
        }
    } elseif ($trOperationId !== '' && $trOperationId !== '0') {
        return false;
    } else {
        $trOperationId = 'tr_' . substr(hash('sha256', $from_id . ':' . $message_id), 0, 32);
    }
    $trAmount = (int)$user['Processing_value'];
    $trRecipientId = $user['Processing_value_one'];
    if ($trAmount < RX_MIN_TRANSFER_AMOUNT || $trRecipientId === '' || $trRecipientId === null) {
        Editmessagetext($from_id, $message_id, "❌ اطلاعات تراکنش نامعتبر است. مجددا تلاش کنید.", null, 'HTML');
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        return false;
    }
    $trRecipientUser = \Faoxima\Core\Database\Db::select("user", "*", "id", $trRecipientId, "select");
    if (empty($trRecipientUser)) {
        Editmessagetext($from_id, $message_id, "❌ کاربر مقصد دیگر معتبر نیست. عملیات لغو شد.", null, 'HTML');
        \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
        \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "", "id", $from_id);
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        return false;
    }
    $trCharge = \Faoxima\Wallet\Transfers\Transfers::transfer($pdo, (string)$from_id, (string)$trRecipientId, $trAmount, $trOperationId);
    if (empty($trCharge['ok'])) {
        $trError = ($trCharge['reason'] ?? '') === 'insufficient_balance'
            ? '❌ موجودی کیف پول شما کافی نیست.'
            : '❌ انتقال تکمیل نشد؛ با شناسه ' . $trOperationId . ' با پشتیبانی در ارتباط باشید.';
        Editmessagetext($from_id, $message_id, $trError, null, 'HTML');
        \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
        \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "", "id", $from_id);
        \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
        return false;
    }
    if (empty($trCharge['replayed'])) {
        \Faoxima\Payments\Settlement\SettlementRuntime::rx_wallet_transfer_report($trCharge['sender'], $trCharge['recipient'], $trAmount, $trOperationId, (float)$trCharge['new_balance'], (float)$trCharge['recipient_balance']);
    }
    \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
    \Faoxima\Core\Database\Db::update("user", "Processing_value_one", "", "id", $from_id);
    \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "0", "id", $from_id);
    Editmessagetext($from_id, $message_id, sprintf("✅ انتقال موجودی با موفقیت انجام شد.\n\n💰 مبلغ: %s تومان\n👤 گیرنده: %s\n🧾 شناسه انتقال: <code>%s</code>", number_format($trAmount), $trRecipientId, $trOperationId), null, 'HTML');
    $trSenderUsername = trim((string)($user['username'] ?? ''));
    $trSenderLines = $trSenderUsername !== '' ? "نام کاربری: @{$trSenderUsername}\n" : '';
    $trSenderLines .= "آیدی عددی: <code>{$from_id}</code>";
    $trNotifyText = sprintf("مبلغ %s تومان به کیف پول شما واریز شد.\n\n<blockquote>%s</blockquote>", number_format($trAmount), $trSenderLines);
    if (empty($trCharge['replayed'])) sendmessage($trRecipientId, $trNotifyText, null, 'HTML');
    \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
    return true;
}

    public static function rxBotReferralSummary($affiliatescommission, $Percent_porsant, $affiliateCount, $orders_count, $sum_order, $rxCommissionEarned, $rxPendingGiftCount, string $rxAffiliateInfoTpl): string
{
    $text_start = "";
    $text_porsant = "";
    if ($affiliatescommission['Discount'] == "onDiscountaffiliates") {
        $rxFmtAffiliateDiscount = \Faoxima\Core\Support\Formatting::rxFormatToman($affiliatescommission['price_Discount'] ?? 0);
        $text_start = "<b>🎁 هدیه عضویت:</b>
• 🎉 مجموع هدیه: {$rxFmtAffiliateDiscount} تومان
• 🔻 ۵۰٪ برای شما (معرف)
• 🔻 ۵۰٪ برای زیرمجموعه (کاربر جدید)
";
    }
    if ($affiliatescommission['status_commission'] == "oncommission") {
        $text_porsant = "<b>💸 پورسانت خرید:</b>
•  $Percent_porsant درصد از مبلغ خرید زیرمجموعه به شما تعلق می‌گیره";
    }
    $textaffiliates = "<b>💼 زیرمجموعه‌گیری و هدیه خوش‌آمد</b>

با دعوت دوستان از طریق <b>لینک اختصاصی</b>، بدون پرداخت حتی ۱ ریال کیف پولت شارژ میشه و از خدمات ربات استفاده می‌کنی!

$text_start
$text_porsant

<b>📊 آمار شما:</b>
• 👥 زیرمجموعه‌ها: $affiliateCount نفر
• 🛒 خریدها: $orders_count عدد
• 💵 مجموع خرید: $sum_order تومان
• 💰 مجموع پورسانت دریافتی: $rxCommissionEarned تومان
• ⏳ هدیه‌های در انتظار دریافت: $rxPendingGiftCount نفر

<b>📢 دعوت کن، هدیه بگیر، رشد کن!</b>
";
    if (trim($rxAffiliateInfoTpl) !== '') {
        try {
            $rxAffiliateInfoText = vsprintf($rxAffiliateInfoTpl, [
                \Faoxima\Core\Support\Formatting::rxFormatToman($affiliatescommission['price_Discount'] ?? 0),
                $Percent_porsant,
                $affiliateCount,
                $orders_count,
                $sum_order,
                $rxCommissionEarned,
                $rxPendingGiftCount,
            ]);
        } catch (\Throwable $rxAffiliateInfoErr) {
            $rxAffiliateInfoText = false;
        }
        if (is_string($rxAffiliateInfoText) && trim($rxAffiliateInfoText) !== '') {
            $textaffiliates = $rxAffiliateInfoText;
        }
    }
    return $textaffiliates;
}
}
