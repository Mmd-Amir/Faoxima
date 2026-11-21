<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $CartManage, $NowPaymentsManage, $Startelegram, $abangateway, $adminAction, $adminExecution, $atlaspay, $backadmin, $blupal, $callback_query_id, $cmm, $cubepay, $cwd, $cwm, $doneTxt, $from_id, $iranpaykeyboard, $keyboardadmin, $keyboardzarinpal, $message_id, $nowpayment_setting_keyboard, $rxGatewayLimitRoute, $setting, $text, $textbotlang, $tonpay, $trnado, $tronnowpayments, $user, $variza.
 * Inherited or exported by reference: $cur, $label, $ok, $okMsg, $row.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CardAndCryptoSettings
{
    public static function handle(
        $CartManage,
        $NowPaymentsManage,
        $Startelegram,
        $abangateway,
        $adminAction,
        $adminExecution,
        $atlaspay,
        $backadmin,
        $blupal,
        $callback_query_id,
        $cmm,
        $cubepay,
        &$cur,
        $cwd,
        $cwm,
        $doneTxt,
        $from_id,
        $iranpaykeyboard,
        $keyboardadmin,
        $keyboardzarinpal,
        &$label,
        $message_id,
        $nowpayment_setting_keyboard,
        &$ok,
        &$okMsg,
        &$row,
        $rxGatewayLimitRoute,
        $setting,
        $text,
        $textbotlang,
        $tonpay,
        $trnado,
        $tronnowpayments,
        $user,
        $variza
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getmaincart", $from_id);
            break;
        case 2:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "minbalancecart");
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getmaxcart", $from_id);
            break;
        case 4:
            $currentMin = (string)($setting['card_verify_min_amount'] ?? '0');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداقل مبلغ فاکتور برای فعال‌شدن احراز کارت را وارد کنید (تومان).\n\nمقدار فعلی: <b>{$currentMin}</b> تومان\n\nعدد صفر = برای همه مبالغ اعمال می‌شود.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("get_cvmin_cart", $from_id);
            break;
        case 5:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداقل مبلغ احراز کارت تنظیم شد: <b>{$text}</b> تومان", $CartManage, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("setting", "card_verify_min_amount", $text);
            break;
        case 6:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $CartManage, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "maxbalancecart");
            break;
        case 7:
            if ($rxGatewayLimitRoute['is_prompt']) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxGatewayLimitRoute['prompt'], $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step($rxGatewayLimitRoute['step'], $from_id);
            } else {
                if (!ctype_digit($text)) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                    return;
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxGatewayLimitRoute['saved'], (['CartManage' => $CartManage ?? null, 'NowPaymentsManage' => $NowPaymentsManage ?? null, 'Startelegram' => $Startelegram ?? null, 'abangateway' => $abangateway ?? null, 'atlaspay' => $atlaspay ?? null, 'blupal' => $blupal ?? null, 'cubepay' => $cubepay ?? null, 'iranpaykeyboard' => $iranpaykeyboard ?? null, 'keyboardzarinpal' => $keyboardzarinpal ?? null, 'nowpayment_setting_keyboard' => $nowpayment_setting_keyboard ?? null, 'tonpay' => $tonpay ?? null, 'trnado' => $trnado ?? null, 'tronnowpayments' => $tronnowpayments ?? null, 'variza' => $variza ?? null][$rxGatewayLimitRoute['keyboard']] ?? null), 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", $rxGatewayLimitRoute['key']);
            }
            break;
        case 8:

            if (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallet')) {
                $rowTrx   = \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallet('TRX');
                $rowUsdtT = \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallet('USDT_TRC20');
                $rowTon   = \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallet('TON');
                $rowUsdtN = \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallet('USDT_TON');
                $shorten = static function ($row) {
                    if (!$row || empty($row['wallet_address'])) return '—';
                    $w = (string) $row['wallet_address'];
                    return mb_strlen($w) > 14 ? mb_substr($w, 0, 8) . '…' . mb_substr($w, -4) : $w;
                };
                $manualCurrencies = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
                $msg = "💼 <b>آدرس کیف پول هش‌چکر</b>\n\n"
                     . "شبکه‌ای که می‌خواهید آدرسش را ثبت/ویرایش کنید را انتخاب کنید:\n\n"
                     . "🟥 ترون (TRX): <code>" . $shorten($rowTrx) . "</code>\n"
                     . "🟢 تتر روی ترون: <code>" . $shorten($rowUsdtT) . "</code>\n"
                     . "🟦 تون (TON): <code>" . $shorten($rowTon) . "</code>\n"
                     . "🟢 تتر روی تون: <code>" . $shorten($rowUsdtN) . "</code>\n";
                $manualRows = [];
                foreach ($manualCurrencies as $mCur => $mInfo) {
                    $mRow = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallet') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallet($mCur) : null;
                    $msg .= "🛠 " . $mInfo['label'] . ": <code>" . $shorten($mRow) . "</code> <i>(دستی)</i>\n";
                    $manualRows[] = [
                        ['text' => '🛠 ' . $mInfo['label'], 'callback_data' => 'cryptowalletmanual_' . $mCur],
                        ['text' => '🗑', 'callback_data' => 'cryptowallet_del_' . $mCur],
                    ];
                }
                $msg .= "\nℹ️ بعد از ثبت آدرس، کاربر هنگام انتخاب «ارز آفلاین» یک هش پرداخت می‌فرستد. برای ۴ شبکه بالا ربات هر دقیقه به‌صورت خودکار از Tronscan / TonAPI تایید می‌کند؛ شبکه‌های «دستی» فقط توسط ادمین بررسی و تایید می‌شوند.";
                $networkPickerKb = json_encode([
                    'inline_keyboard' => array_merge([
                        [['text' => '🟥 ترون (TRX)',     'callback_data' => 'cryptowallet_TRX'],         ['text' => '🗑', 'callback_data' => 'cryptowallet_del_TRX']],
                        [['text' => '🟢 تتر روی ترون',  'callback_data' => 'cryptowallet_USDT_TRC20'],  ['text' => '🗑', 'callback_data' => 'cryptowallet_del_USDT_TRC20']],
                        [['text' => '🟦 تون (TON)',     'callback_data' => 'cryptowallet_TON'],         ['text' => '🗑', 'callback_data' => 'cryptowallet_del_TON']],
                        [['text' => '🟢 تتر روی تون',   'callback_data' => 'cryptowallet_USDT_TON'],    ['text' => '🗑', 'callback_data' => 'cryptowallet_del_USDT_TON']],
                    ], $manualRows, [
                        [['text' => '➕ افزودن شبکه دستی', 'callback_data' => 'cryptowallet_new']],
                        [['text' => $textbotlang['Admin']['backmenu'] ?? '▶️ بازگشت به منوی قبل', 'callback_data' => 'wallet_backmenu']],
                        [['text' => '❌ بستن',          'callback_data' => 'close_stat']],
                    ]),
                ], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $msg, $networkPickerKb, 'HTML');
            } else {

                $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
                $currentWallet = $PaySetting['ValuePay'] ?? '';
                $texttronseller = "💼 لطفاً آدرس ولت ترون (TRC20) را ارسال کنید.\n\nولت فعلی شما: " . ($currentWallet === '' ? '—' : $currentWallet);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
                \Faoxima\Telegram\Conversation\ConversationData::savedata('clear', 'walletaddress_origin', 'general');
                \Faoxima\Telegram\Navigation\Steps::step('walletaddresssiranpay', $from_id);
            }
            break;
        case 9:

            $memoAction = $cmm[1];
            $memoCur    = $cmm[2];
            if ($memoAction === 'no') {
                if (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_save_wallet_memo')) {
                    \Faoxima\Payments\Crypto\ManualCrypto::crypto_save_wallet_memo($memoCur, '');
                }
                \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
                $doneTxt = "✅ کیف پول <b>{$memoCur}</b> بدون ممو ذخیره شد.";
                if (!empty($message_id)) {
                    Editmessagetext($from_id, $message_id, $doneTxt, null);
                } else {
                    sendmessage($from_id, $doneTxt, null, 'HTML');
                }
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            } else {
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $memoCur, "id", $from_id);
                $askMemoTxt = "🏷 لطفاً <b>ممو (Memo / Comment)</b> کیف پول <b>{$memoCur}</b> را ارسال کنید.\n\n"
                            . "<i>این مقدار هنگام پرداخت توسط کاربر در فیلد Memo/Comment کیف پولش وارد می‌شود.</i>";
                if (!empty($message_id)) {
                    Editmessagetext($from_id, $message_id, $askMemoTxt, null);
                } else {
                    sendmessage($from_id, $askMemoTxt, $backadmin, 'HTML');
                }
                \Faoxima\Telegram\Navigation\Steps::step('cryptowallet_set_memo', $from_id);
            }
            break;
        case 10:

            $memoText = trim((string) $text);
            $looksLikeNav = (
                $memoText === ''
                || mb_strlen($memoText) > 200
            );
            $memoCur = trim((string) ($user['Processing_value'] ?? ''));
            if (!in_array($memoCur, ['TON', 'USDT_TON'], true)) {
                \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($looksLikeNav) {
                if ($memoText === '') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🏠 از حالت ثبت ممو خارج شدید.", $keyboardadmin, 'HTML');
                } else {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ممو معتبر نیست (طول بیش از ۲۰۰ کاراکتر).", null, 'HTML');
                    return;
                }
                \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_save_wallet_memo')) {
                \Faoxima\Payments\Crypto\ManualCrypto::crypto_save_wallet_memo($memoCur, $memoText);
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            $successMsg = "✅ ممو برای کیف پول <b>{$memoCur}</b> ذخیره شد:\n<code>" . htmlspecialchars($memoText) . "</code>";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $successMsg, $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 11:

            $cur = $cwd[1];
            $supported = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_supported_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_supported_currencies() : [];
            $manual = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
            $label = $supported[$cur]['label'] ?? $manual[$cur]['label'] ?? $cur;
            $ok = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_delete_wallet') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_delete_wallet($cur) : false;
            if ($ok) {
                $doneTxt = "🗑 کیف پول <b>{$label}</b> حذف و خالی شد.\nاز این پس برای این ارز هیچ آدرسی تنظیم نیست و کاربر نمی‌تواند پرداخت آفلاین انجام دهد.";
            } else {
                $doneTxt = "❌ حذف کیف پول <b>{$label}</b> ناموفق بود.";
            }
            if (!empty($message_id)) {
                Editmessagetext($from_id, $message_id, $doneTxt, null);
            } else {
                sendmessage($from_id, $doneTxt, null, 'HTML');
            }
            if (function_exists('telegram') && !empty($callback_query_id ?? null)) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => $ok ? '✅ حذف شد' : '❌ خطا',
                    'cache_time' => 0,
                ]);
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 12:

            $cur = $cwm[1];
            $supported = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_supported_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_supported_currencies() : [];
            $label = $supported[$cur]['label'] ?? $cur;
            $row = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallet') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallet($cur) : null;
            $cur_now = $row['wallet_address'] ?? '';
            $hintNet = $supported[$cur]['network'] ?? '';
            $hint = $hintNet === 'TRON'
                ? "ℹ️ آدرس باید با حرف <code>T</code> شروع شود و ۳۴ کاراکتر باشد (TRC20)."
                : "ℹ️ آدرس TON معمولاً با <code>EQ</code>، <code>UQ</code> یا <code>kQ</code> شروع می‌شود و ۴۸ کاراکتر است.";
            $msg = "💼 شبکه انتخاب‌شده: <b>{$label}</b>\n\n"
                 . "آدرس فعلی: <code>" . ($cur_now === '' ? '—' : htmlspecialchars($cur_now)) . "</code>\n\n"
                 . "آدرس کیف پول جدید را ارسال کنید:\n\n{$hint}";

            \Faoxima\Core\Database\Db::update("user", "Processing_value", $cur, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $msg, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('cryptowallet_set', $from_id);
            break;
        case 13:

            $trimmed = trim((string) $text);
            $looksLikeNav = (
                $trimmed === ''
                || mb_strlen($trimmed) < 30
                || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $trimmed)
                || strpos($trimmed, ' ') !== false
            );
            if ($looksLikeNav) {
                \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if ($trimmed !== '') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🏠 از حالت ثبت آدرس خارج شدید.", $keyboardadmin, 'HTML');
                }
                return;
            }
            $cur = trim((string) ($user['Processing_value'] ?? ''));
            $supported = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_supported_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_supported_currencies() : [];
            if ($cur === '' || !isset($supported[$cur])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطای داخلی: ارز نامشخص است. مجدداً از منوی آدرس ولت اقدام کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $address = trim((string) $text);
            $network = $supported[$cur]['network'];
            if (!method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_validate_address') || !\Faoxima\Payments\Crypto\ManualCrypto::crypto_validate_address($address, $network)) {
                $exHint = $network === 'TRON'
                    ? "آدرس TRC20 معتبر مثل <code>TR7N…</code>."
                    : "آدرس TON معتبر مثل <code>EQ…</code> یا <code>UQ…</code>.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آدرس وارد شده برای شبکه <b>{$network}</b> معتبر نیست.\n\n{$exHint}", null, 'HTML');
                return;
            }
            if ($cur === 'TRX' && is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'walletsCollide'])) {
                $tronadoWalletSetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
                if (\Faoxima\Payments\Gateways\Tronado\Client::walletsCollide($address, $tronadoWalletSetting['ValuePay'] ?? '')) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این آدرس همان کیف پول ترونادو است و ثبت نشد.\n\nاگر هر دو درگاه به یک کیف پول واریز شوند، یک پرداخت ترونادو می‌تواند دوباره به‌عنوان رسید پرداخت آفلاین ثبت و دو بار تایید شود. لطفاً یک آدرس جداگانه ارسال کنید.", null, 'HTML');
                    return;
                }
            }
            $ok = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_save_wallet') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_save_wallet($cur, $address) : false;
            if (!$ok) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ذخیره‌سازی با خطا مواجه شد. لاگ سرور را بررسی کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }

            $label = $supported[$cur]['label'];
            $okMsg = "✅ آدرس کیف پول <b>{$label}</b> با موفقیت ثبت شد.\n\n<code>" . htmlspecialchars($address) . "</code>";
            if ($cur === 'TRX') {
                $okMsg .= "\n\nℹ️ آدرس تتر روی ترون (USDT-TRC20) جداگانه است؛ در صورت نیاز از منوی «🟢 تتر روی ترون» آن را تنظیم کنید.";
            } elseif ($cur === 'TON') {
                $okMsg .= "\n\nℹ️ آدرس تتر روی تون (USDT-TON) جداگانه است؛ در صورت نیاز از منوی «🟢 تتر روی تون» آن را تنظیم کنید.";
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $okMsg, $keyboardadmin, 'HTML');

            if ($cur === 'TON' || $cur === 'USDT_TON') {
                \Faoxima\Core\Database\Db::update("user", "Processing_value", $cur, "id", $from_id);
                $memoAskMsg = "🏷 <b>آیا این کیف پول ممو (Memo / Comment) دارد؟</b>\n\n"
                            . "<i>اگر آدرس از یک صرافی است (مثل نوبیتکس / MEXC)، معمولاً ممو لازم دارد.\n"
                            . "آدرس‌های شخصی Tonkeeper معمولاً نیاز ندارند.</i>";
                $memoAskKb = json_encode([
                    'inline_keyboard' => [
                        [['text' => '✅ دارم — وارد می‌کنم', 'callback_data' => 'cryptomemo_yes_' . $cur]],
                        [['text' => '❌ ندارم',              'callback_data' => 'cryptomemo_no_'  . $cur]],
                    ],
                ], JSON_UNESCAPED_UNICODE);
                sendmessage($from_id, $memoAskMsg, $memoAskKb, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }

            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }

        return true;
    }
}
