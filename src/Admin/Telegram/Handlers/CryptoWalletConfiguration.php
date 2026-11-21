<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $backuser, $cwmm, $cwnm, $from_id, $iranpaykeyboard, $keyboardadmin, $message_id, $okMsg, $text, $textbotlang, $trnado, $user.
 * Inherited or exported by reference: $cur, $label, $ok, $row.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CryptoWalletConfiguration
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $backuser,
        &$cur,
        $cwmm,
        $cwnm,
        $from_id,
        $iranpaykeyboard,
        $keyboardadmin,
        &$label,
        $message_id,
        &$ok,
        $okMsg,
        &$row,
        $text,
        $textbotlang,
        $trnado,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:

            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            $askNetworkMsg = "🛠 <b>افزودن شبکه پرداخت دستی جدید (تتر)</b>\n\n"
                        . "ارز این شبکه‌ها همیشه <b>تتر (USDT)</b> است — فقط نام شبکه را ارسال کنید (مثل <code>ERC20</code> یا <code>BEP20</code> یا <code>POLYGON</code>):";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $askNetworkMsg, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('cryptowallet_new_network', $from_id);
            break;
        case 2:

            $networkInput = strtoupper(trim((string) $text));
            if ($networkInput === '' || mb_strlen($networkInput) > 32) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if ($networkInput !== '') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🏠 از حالت افزودن شبکه خارج شدید.", $keyboardadmin, 'HTML');
                }
                return;
            }
            if (!preg_match('/^[A-Z0-9_]{2,20}$/', $networkInput)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام شبکه نامعتبر است. فقط حروف انگلیسی، اعداد و آندرلاین مجاز است (حداکثر ۲۰ کاراکتر).", null, 'HTML');
                return;
            }
            $codeInput = 'USDT_' . $networkInput;
            $supported = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_supported_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_supported_currencies() : [];
            $manual = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
            if (isset($supported[$codeInput]) || isset($manual[$codeInput])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ شبکه <b>{$networkInput}</b> برای تتر قبلاً ثبت شده است.", null, 'HTML');
                return;
            }
            $label = 'تتر روی شبکه ' . $networkInput . ' (USDT-' . $networkInput . ')';
            \Faoxima\Core\Database\Db::update("user", "Processing_value", json_encode(['code' => $codeInput, 'network' => $networkInput, 'label' => $label], JSON_UNESCAPED_UNICODE), "id", $from_id);
            $memoAskMsg = "🏷 <b>آیا این کیف پول ممو/تگ (Memo / Tag) دارد؟</b>\n\n"
                        . "<i>اگر آدرس از یک صرافی است، معمولاً ممو لازم دارد. اگر شخصی و مستقل است، معمولاً نیاز ندارد.</i>";
            $memoAskKb = json_encode([
                'inline_keyboard' => [
                    [['text' => '✅ دارم — وارد می‌کنم', 'callback_data' => 'cryptowalletnewmemo_yes']],
                    [['text' => '❌ ندارم',              'callback_data' => 'cryptowalletnewmemo_no']],
                ],
            ], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $memoAskMsg, $memoAskKb, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 3:

            $pending = json_decode((string) ($user['Processing_value'] ?? ''), true);
            if (!is_array($pending) || empty($pending['code']) || empty($pending['network']) || empty($pending['label'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطای داخلی: اطلاعات ناقص است. مجدداً از منوی آدرس ولت اقدام کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if ($cwnm[1] === 'no') {
                $pending['memo'] = '';
                \Faoxima\Core\Database\Db::update("user", "Processing_value", json_encode($pending, JSON_UNESCAPED_UNICODE), "id", $from_id);
                $askAddrMsg = "💼 آدرس کیف پول را برای دریافت وجه ارسال کنید:";
                if (!empty($message_id)) {
                    Editmessagetext($from_id, $message_id, $askAddrMsg, null);
                } else {
                    sendmessage($from_id, $askAddrMsg, $backadmin, 'HTML');
                }
                \Faoxima\Telegram\Navigation\Steps::step('cryptowallet_new_address', $from_id);
            } else {
                $askMemoMsg = "🏷 لطفاً <b>ممو/تگ (Memo / Tag)</b> کیف پول را ارسال کنید.";
                if (!empty($message_id)) {
                    Editmessagetext($from_id, $message_id, $askMemoMsg, null);
                } else {
                    sendmessage($from_id, $askMemoMsg, $backadmin, 'HTML');
                }
                \Faoxima\Telegram\Navigation\Steps::step('cryptowallet_new_memo', $from_id);
            }
            break;
        case 4:

            $memoInput = trim((string) $text);
            if ($memoInput === '' || mb_strlen($memoInput) > 200) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if ($memoInput !== '') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🏠 از حالت افزودن شبکه خارج شدید.", $keyboardadmin, 'HTML');
                }
                return;
            }
            $pending = json_decode((string) ($user['Processing_value'] ?? ''), true);
            if (!is_array($pending) || empty($pending['code']) || empty($pending['network']) || empty($pending['label'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطای داخلی: اطلاعات ناقص است. مجدداً از منوی آدرس ولت اقدام کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $pending['memo'] = $memoInput;
            \Faoxima\Core\Database\Db::update("user", "Processing_value", json_encode($pending, JSON_UNESCAPED_UNICODE), "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "💼 آدرس کیف پول را برای دریافت وجه ارسال کنید:", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('cryptowallet_new_address', $from_id);
            break;
        case 5:

            $addressInput = trim((string) $text);
            if ($addressInput === '' || mb_strlen($addressInput) < 10 || mb_strlen($addressInput) > 255) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if ($addressInput !== '') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🏠 از حالت افزودن شبکه خارج شدید.", $keyboardadmin, 'HTML');
                }
                return;
            }
            $pending = json_decode((string) ($user['Processing_value'] ?? ''), true);
            if (!is_array($pending) || empty($pending['code']) || empty($pending['network']) || empty($pending['label'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ خطای داخلی: اطلاعات ناقص است. مجدداً از منوی آدرس ولت اقدام کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $ok = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_create_manual_wallet')
                ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_create_manual_wallet($pending['code'], $pending['network'], $pending['label'], $addressInput)
                : false;
            if ($ok && !empty($pending['memo']) && method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_save_wallet_memo')) {
                \Faoxima\Payments\Crypto\ManualCrypto::crypto_save_wallet_memo($pending['code'], $pending['memo']);
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", "0", "id", $from_id);
            if ($ok) {
                $okMsg = "✅ شبکه دستی <b>" . htmlspecialchars($pending['label']) . "</b> با موفقیت ثبت شد.\n\n<code>" . htmlspecialchars($addressInput) . "</code>\n\n"
                       . "ℹ️ تراکنش‌های این شبکه به‌صورت خودکار تایید نمی‌شوند و باید توسط ادمین بررسی شوند.";
            } else {
                $okMsg = "❌ ذخیره‌سازی شبکه دستی با خطا مواجه شد. لاگ سرور را بررسی کنید.";
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $okMsg, $keyboardadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 6:

            $cur = $cwmm[1];
            $manual = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_manual_currencies') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_manual_currencies() : [];
            if (!isset($manual[$cur])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این شبکه دستی یافت نشد.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $label = $manual[$cur]['label'];
            $row = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallet') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallet($cur) : null;
            $cur_now = $row['wallet_address'] ?? '';
            $msg = "💼 شبکه انتخاب‌شده: <b>{$label}</b> <i>(دستی)</i>\n\n"
                 . "آدرس فعلی: <code>" . ($cur_now === '' ? '—' : htmlspecialchars($cur_now)) . "</code>\n\n"
                 . "آدرس کیف پول جدید را ارسال کنید:";
            \Faoxima\Core\Database\Db::update("user", "Processing_value", json_encode(['code' => $cur, 'network' => $manual[$cur]['network'], 'label' => $label], JSON_UNESCAPED_UNICODE), "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $msg, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('cryptowallet_new_address', $from_id);
            break;
        case 7:
            $walletInput = trim((string) $text);
            $walletProcessing = json_decode((string) ($user['Processing_value'] ?? ''), true);
            $walletReturnKeyboard = (is_array($walletProcessing) && ($walletProcessing['walletaddress_origin'] ?? '') === 'trnado')
                ? $trnado
                : $keyboardadmin;

            if ($walletInput === ''
                || mb_strlen($walletInput) < 30
                || preg_match('/[\x{0600}-\x{06FF}\x{200C}\x{200D}]/u', $walletInput)
                || strpos($walletInput, ' ') !== false
            ) {
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                if ($walletInput !== '') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🏠 از حالت ثبت آدرس ولت خارج شدید.", $walletReturnKeyboard, 'HTML');
                }
                return;
            }

            if (!is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'isValidTronAddress']) || !\Faoxima\Payments\Gateways\Tronado\Client::isValidTronAddress($walletInput)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آدرس ولت وارد شده نامعتبر است. لطفاً آدرس TRC20 معتبر را دقیقاً با همان حروف کوچک و بزرگ ارسال کنید.", $backadmin, 'HTML');
                return;
            }

            // Stored exactly as entered: Base58 is case sensitive. This used to be strtoupper(), which
            // corrupted every address saved here.
            $standardizedWallet = $walletInput;

            // Refused, not just warned: sharing the offline TRX wallet would let one payment be credited twice.
            // USDT-TRC20 is not checked - that checker needs a USDT transfer, and Tronado pays plain TRX.
            $offlineTrx = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallet') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallet('TRX') : null;
            if (is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'walletsCollide']) && is_array($offlineTrx)
                && \Faoxima\Payments\Gateways\Tronado\Client::walletsCollide($standardizedWallet, $offlineTrx['wallet_address'] ?? '')) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ این آدرس همان کیف پول «🟥 ترون (TRX)» بخش ارز آفلاین است و ثبت نشد.\n\nاگر هر دو درگاه به یک کیف پول واریز شوند، یک پرداخت ترونادو می‌تواند دوباره به‌عنوان رسید پرداخت آفلاین ثبت و دو بار تایید شود. لطفاً برای ترونادو یک آدرس جداگانه ارسال کنید.", $backadmin, 'HTML');
                return;
            }

            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $walletReturnKeyboard, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $standardizedWallet, "NamePay", "walletaddress");
            \Faoxima\Core\Database\Db::update("user", "Processing_value", '{}', "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 8:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apiiranpay", "select")['ValuePay'];
            $texttronseller = "📌 کد api خود را ارسال نمایید.

        مرچنت فعلی شما : $PaySetting";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apiiranpay', $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $iranpaykeyboard, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "apiiranpay");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداقل مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("minbalanceiranpay", $from_id);
            break;
        case 11:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداقل مبلغ واریزی تنظیم گردید.", $iranpaykeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "minbalanceiranpay");
            break;
        case 12:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداکثر مبلغ واریزی را ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("maxbalanceiranpay", $from_id);
            break;
        case 13:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ حداکثر مبلغ واریزی تنظیم گردید.", $iranpaykeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "maxbalanceiranpay");
            break;
        case 14:
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. ابتدا از «مدیریت پنل» پنل موردنظر را باز کنید، سپس دوباره این دکمه را بزنید.", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 حداقل حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('GetmaineExtra', $from_id);
            break;
        case 15:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backuser, 'HTML');
                return;
            }
            $panelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)$user['Processing_value'];
            if ($panelName === '') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخاب نشده است. لطفاً دوباره پنل را انتخاب کنید.", $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "namepanel", $panelName);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "mainvalume", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['Extra_volume']['gettypeextra'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('gettypeextramain', $from_id);
            break;
        }

        return true;
    }
}
