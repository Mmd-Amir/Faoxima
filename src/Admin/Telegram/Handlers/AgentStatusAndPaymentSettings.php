<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $abangateway, $adminAction, $adminExecution, $atlaspay, $backadmin, $blupal, $callback_query_id, $cubepay, $datagetr, $from_id, $message_id, $pdo, $rialit, $text, $textbotlang, $tonpay, $tonpayMode, $tonpayModeMatch, $trnado, $variza.
 * Inherited or exported by reference: $id_user, $label.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AgentStatusAndPaymentSettings
{
    public static function handle(
        $abangateway,
        $adminAction,
        $adminExecution,
        $atlaspay,
        $backadmin,
        $blupal,
        $callback_query_id,
        $cubepay,
        $datagetr,
        $from_id,
        &$id_user,
        &$label,
        $message_id,
        $pdo,
        $rialit,
        $text,
        $textbotlang,
        $tonpay,
        $tonpayMode,
        $tonpayModeMatch,
        $trnado,
        $variza
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $selectedType = $datagetr[1];
            $id_user = $datagetr[2];
            $agentTypeLabels = [
                'n' => 'نماینده عادی',
                'n2' => 'نماینده پیشرفته',
            ];
            if (!array_key_exists($selectedType, $agentTypeLabels)) {
                telegram('answerCallbackQuery', array(
                    'callback_query_id' => $callback_query_id,
                    'text' => $textbotlang['Admin']['agent']['invalidtypeagent'],
                    'show_alert' => true,
                    'cache_time' => 0,
                ));
                return;
            }
            \Faoxima\Core\Database\Db::update("user", "agent", $selectedType, "id", $id_user);
            \Faoxima\Core\Database\Db::update("Requestagent", "type", $selectedType, "id", $id_user);
            $request_agent = \Faoxima\Core\Database\Db::select("Requestagent", "*", "id", $id_user, "select");
            if ($request_agent) {
                $agentTypeButtons = [];
                foreach ($agentTypeLabels as $typeCode => $label) {
                    $buttonText = ($typeCode === $selectedType ? "✅ " : "") . $label;
                    $agentTypeButtons[] = [
                        'text' => $buttonText,
                        'callback_data' => "setagenttype_{$typeCode}_{$id_user}"
                    ];
                }
                $keyboardreject = json_encode([
                    'inline_keyboard' => [
                        [['text' => "✅درخواست تایید شده.", 'callback_data' => "accept"]],
                        $agentTypeButtons,
                        [['text' => "⏱️ زمان انقضا نمایندگی", 'callback_data' => 'expireset_' . $id_user]],
                        [['text' => "مدیریت کاربر", 'callback_data' => 'manageuser_' . $id_user]]
                    ]
                ], JSON_UNESCAPED_UNICODE);
                $textrequestagent = "📣 یک کاربر درخواست نمایندگی ثبت کرده لطفا اطلاعات را بررسی و وضعیت را مشخص کنید.\n\nآیدی عددی : $id_user\nنام کاربری : {$request_agent['username']}\nتوضیحات :  {$request_agent['Description']} ";
                $textrequestagent .= "\nوضعیت: تایید شد ({$agentTypeLabels[$selectedType]})";
                $textrequestagent .= "\nبرای تغییر نوع نماینده از دکمه‌های زیر استفاده کنید.";
                Editmessagetext($from_id, $message_id, $textrequestagent, $keyboardreject);
            }
            telegram('answerCallbackQuery', array(
                'callback_query_id' => $callback_query_id,
                'text' => "نوع نماینده به {$agentTypeLabels[$selectedType]} تغییر کرد.",
                'show_alert' => false,
                'cache_time' => 0,
            ));
            break;
        case 2:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $trnado, 'HTML');
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $tonpay, 'HTML');
            break;
        case 4:
            $rxRialitWhUrl = method_exists(\Faoxima\Payments\Gateways\Rialit\BotRuntime::class, 'rialitWebhookUrl') ? \Faoxima\Payments\Gateways\Rialit\BotRuntime::rialitWebhookUrl() : '';
            $rxRialitMenuText = (string) $textbotlang['users']['selectoption'];
            if ($rxRialitWhUrl !== '') {
                $rxRialitMenuText .= "\n\n🌐 آدرس وب‌هوک:\n<code>" . htmlspecialchars($rxRialitWhUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>";
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxRialitMenuText, $rialit, 'HTML');
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $cubepay, 'HTML');
            break;
        case 6:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $blupal, 'HTML');
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $variza, 'HTML');
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $abangateway, 'HTML');
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $atlaspay, 'HTML');
            break;
        case 10:
            $balanceData = is_callable([\Faoxima\Payments\Gateways\Atlaspay\Client::class, 'balance']) ? \Faoxima\Payments\Gateways\Atlaspay\Client::balance() : null;
            $accountData = is_callable([\Faoxima\Payments\Gateways\Atlaspay\Client::class, 'account']) ? \Faoxima\Payments\Gateways\Atlaspay\Client::account() : null;
            $availableTrx = is_array($balanceData) ? ($balanceData['data']['availableTrx'] ?? null) : null;
            $accountName = is_array($accountData) ? ($accountData['data']['name'] ?? null) : null;
            $accountStatus = is_array($accountData) ? ($accountData['data']['status'] ?? null) : null;
            $markupPct = is_array($accountData) ? ($accountData['data']['markupPct'] ?? null) : null;
            $textAtlasAccount = "📊 اطلاعات حساب اطلس‌پی\n\n";
            $textAtlasAccount .= "💰 موجودی قابل‌برداشت: " . ($availableTrx !== null ? number_format((float)$availableTrx, 4) . ' TRX' : 'دریافت نشد') . "\n";
            $textAtlasAccount .= "🏪 نام فروشگاه: " . ($accountName !== null ? htmlspecialchars((string)$accountName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'دریافت نشد') . "\n";
            $textAtlasAccount .= "📌 وضعیت حساب: " . ($accountStatus !== null ? htmlspecialchars((string)$accountStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'دریافت نشد') . "\n";
            $textAtlasAccount .= "📈 درصد مارک‌آپ: " . ($markupPct !== null ? (string)$markupPct : 'ندارد');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textAtlasAccount, $atlaspay, 'HTML');
            break;
        case 11:
            $statusternadoosql = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
            $statusternadoo = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'],
                    ],
                ]
            ]);
            $textternado = "در این بخش می توانید درگاه ترونادو را خاموش یا روشن کنید";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textternado, $statusternadoo, 'HTML');
            break;
        case 12:
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "offternado", "NamePay", "statustarnado");
            $statusternadoosql = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
            $statusternadoo = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "خاموش گردید", $statusternadoo);
            break;
        case 13:
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "onternado", "NamePay", "statustarnado");
            $statusternadoosql = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "statustarnado", "select");
            $statusternadoo = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $statusternadoosql['ValuePay'], 'callback_data' => $statusternadoosql['ValuePay']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'trnado_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "روشن گردید", $statusternadoo);
            break;
        case 14:
            sendmessage($from_id, \Faoxima\Payments\Gateways\Tronado\Runtime::tronadoAdminSetupText(), \Faoxima\Payments\Gateways\Tronado\Runtime::tronadoAdminSetupKeyboard(), 'HTML');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔑 کلید API ترونادو خود را اینجا وارد کنید.\n\nبرای حفظ امنیت، مقدار کلید فعلی نمایش داده نمی‌شود.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apiternado', $from_id);
            break;
        case 15:
            $newTronadoKey = trim((string) $text);
            if ($newTronadoKey === '' || preg_match('/\s/', $newTronadoKey)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کلید API نامعتبر است. کلید را بدون فاصله و به‌صورت یک متن ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $newTronadoKey, "NamePay", "apiternado");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'] . "\n\nℹ️ صحت کلید با ترونادو بررسی نشد؛ پس از ثبت دامنه‌ی Callback، با یک پرداخت آزمایشی کوچک از عملکرد آن مطمئن شوید.", $trnado, 'HTML');
            sendmessage($from_id, \Faoxima\Payments\Gateways\Tronado\Runtime::tronadoAdminSetupText(), \Faoxima\Payments\Gateways\Tronado\Runtime::tronadoAdminSetupKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 16:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "ipnsigningkeytronado", "select");
            $currentSigning = trim((string) ($PaySetting['ValuePay'] ?? ''));
            $signingStatus = $currentSigning === '' ? 'ثبت نشده' : 'ثبت شده (' . \Faoxima\Payments\Gateways\Tronado\Runtime::tronadoAdminMaskSecret($currentSigning) . ')';
            $texttronseller = "🔏 کلید امضای IPN (IpnSigningKey) ترونادو را اینجا وارد کنید.\n\nوضعیت فعلی: {$signingStatus}\n\nاین کلید با API Key جداست و برای تأیید امضای Callbackها استفاده می‌شود. برای حفظ امنیت، مقدار کلید نمایش داده نمی‌شود.";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('ipnsigningkeytronado', $from_id);
            break;
        case 17:
            $newSigningKey = trim((string) $text);
            if ($newSigningKey === '' || preg_match('/\s/', $newSigningKey)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ کلید امضا نامعتبر است. کلید را بدون فاصله و به‌صورت یک متن ارسال کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $newSigningKey, "NamePay", "ipnsigningkeytronado");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 18:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "walletaddress", "select");
            $currentWallet = trim((string) ($PaySetting['ValuePay'] ?? ''));
            $texttronseller = "💼 آدرس کیف پول ترون (TRC20) که ترونادو مبالغ را به آن واریز می‌کند را ارسال کنید.\n\n"
                . "⚠️ آدرس را دقیقاً همان‌طور که در کیف پول نمایش داده می‌شود و با همان حروف کوچک و بزرگ ارسال کنید.\n\n"
                . "آدرس فعلی: " . ($currentWallet === '' ? 'ثبت نشده' : "<code>" . htmlspecialchars($currentWallet) . "</code>");
            if ($currentWallet !== '' && is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'isValidTronAddress']) && !\Faoxima\Payments\Gateways\Tronado\Client::isValidTronAddress($currentWallet)) {
                $texttronseller .= "\n\n❌ آدرس فعلی معتبر نیست و ترونادو آن را رد می‌کند؛ لطفاً آدرس صحیح را دوباره ثبت کنید.";
            }
            $offlineTrx = method_exists(\Faoxima\Payments\Crypto\ManualCrypto::class, 'crypto_active_wallet') ? \Faoxima\Payments\Crypto\ManualCrypto::crypto_active_wallet('TRX') : null;
            if (is_callable([\Faoxima\Payments\Gateways\Tronado\Client::class, 'walletsCollide']) && is_array($offlineTrx)
                && \Faoxima\Payments\Gateways\Tronado\Client::walletsCollide($currentWallet, $offlineTrx['wallet_address'] ?? '')) {
                $texttronseller .= "\n\n⚠️ این آدرس با کیف پول «🟥 ترون (TRX)» بخش ارز آفلاین یکی است؛ برای جلوگیری از تایید دوباره‌ی یک پرداخت، یک آدرس جداگانه برای ترونادو ثبت کنید.";
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
            \Faoxima\Telegram\Conversation\ConversationData::savedata('clear', 'walletaddress_origin', 'trnado');
            \Faoxima\Telegram\Navigation\Steps::step('walletaddresssiranpay', $from_id);
            break;
        case 19:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "wageFromBusinessPercentageTronado", "select");
            $currentValue = $PaySetting['ValuePay'] ?? '0';
            $texttronseller = "⚖️ درصد کارمزد ترونادو که کسب‌وکار شما پرداخت می‌کند\n\n"
                . "کارمزد پیش‌فرض ترونادو ۲۰٪ است. این عدد مشخص می‌کند این کارمزد بین شما و کاربر چطور تقسیم شود:\n\n"
                . "🔹 ۰ (پیش‌فرض): کل کارمزد را کاربر پرداخت می‌کند. شما دقیقاً مبلغ فاکتور خودتان را دریافت می‌کنید، اما کاربر مبلغی بیشتر از قیمت واقعی پرداخت می‌کند.\n\n"
                . "🔹 ۱۰۰: کل کارمزد را شما پرداخت می‌کنید. کاربر تقریباً همان مبلغ فاکتور را پرداخت می‌کند، اما مقدار ترونی که به کیف پول شما واریز می‌شود کمتر از درخواستی است.\n\n"
                . "🔹 بین ۰ تا ۱۰۰: کارمزد بین شما و کاربر به‌نسبت تقسیم می‌شود.\n\n"
                . "⚠️ توجه: تغییر این عدد فقط نحوه تقسیم کارمزد را مشخص می‌کند و باعث تغییر در فاکتور شما نمی‌شود؛ اگر مطمئن نیستید، آن را روی ۰ نگه دارید.\n\n"
                . "ℹ️ این تنظیم «کش‌بک» نیست؛ کش‌بک کاربران از دکمه «💰 کش بک ترونادو» تنظیم می‌شود. تغییر این عدد فقط برای فاکتورهای جدید اعمال می‌شود.\n\n"
                . "لطفاً عددی بین ۰ تا ۱۰۰ ارسال کنید.\n\nمقدار فعلی: {$currentValue}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $texttronseller, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('wageFromBusinessPercentageTronado', $from_id);
            break;
        case 20:
            if (!ctype_digit($text) || (int) $text < 0 || (int) $text > 100) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مقدار نامعتبر است. عددی بین 0 تا 100 ارسال کنید.", $trnado, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $trnado, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "wageFromBusinessPercentageTronado");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 21:
            $PaySetting = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "apitonpay", "select");
            $currentKey = is_callable([\Faoxima\Payments\Gateways\Tonpay\Client::class, 'maskSecret']) ? \Faoxima\Payments\Gateways\Tonpay\Client::maskSecret($PaySetting['ValuePay'] ?? '') : '';
            if ($currentKey === '') {
                $currentKey = 'ثبت نشده';
            }
            $currentKey = htmlspecialchars($currentKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $texttonpay = "🔑 کلید API تون‌پی خود را اینجا وارد کنید.\n\nکلید فعلی شما: {$currentKey}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $texttonpay, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apitonpay', $from_id);
            break;
        case 22:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $tonpay, 'HTML');
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $text, "NamePay", "apitonpay");
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 23:
            $currentCustomKey = is_callable([\Faoxima\Payments\Gateways\Tonpay\Client::class, 'maskSecret']) && is_callable([\Faoxima\Payments\Gateways\Tonpay\CustomClient::class, 'apiKey']) ? \Faoxima\Payments\Gateways\Tonpay\Client::maskSecret(\Faoxima\Payments\Gateways\Tonpay\CustomClient::apiKey()) : '';
            if ($currentCustomKey === '') {
                $currentCustomKey = 'ثبت نشده';
            }
            $currentCustomKey = htmlspecialchars($currentCustomKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔐 کلید API درگاه کاستوم تلگرام تون‌پی را اینجا وارد کنید.\n\nاین کلید با کلید درگاه معمولی متفاوت است و فقط برای حالت پرداخت داخلی (کاستوم) استفاده می‌شود.\n\nکلید فعلی شما: {$currentCustomKey}", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('apitonpaycustom', $from_id);
            break;
        case 24:
            if (!is_callable([\Faoxima\Payments\Gateways\Tonpay\CustomClient::class, 'saveApiKey']) || !\Faoxima\Payments\Gateways\Tonpay\CustomClient::saveApiKey($pdo, $text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ذخیره کلید ناموفق بود. دوباره تلاش کنید.", $tonpay, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingnowPayment']['Savaapi'], $tonpay, 'HTML');
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 25:
            if (!empty($tonpayModeMatch[1])) {
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $tonpayModeMatch[1], "NamePay", "tonpay_payment_mode");
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '✅ مقصد پرداخت تون‌پی ذخیره شد', 'show_alert' => false]);
                $tonpayMode = $tonpayModeMatch[1];
            } else {
                $tonpayMode = is_callable([\Faoxima\Payments\Gateways\Tonpay\Client::class, 'paymentMode']) ? \Faoxima\Payments\Gateways\Tonpay\Client::paymentMode() : 'bot';
            }
            $tonpayBotMark = $tonpayMode === 'bot' ? '✅ ' : '';
            $tonpayWebMark = $tonpayMode === 'web' ? '✅ ' : '';
            $tonpayCustomMark = $tonpayMode === 'custom' ? '✅ ' : '';
            $tonpayModeKb = json_encode(['inline_keyboard' => [
                [['text' => "{$tonpayBotMark}🤖 پرداخت از طریق ربات تلگرام", 'callback_data' => 'tonpay_paymode_bot']],
                [['text' => "{$tonpayWebMark}🌐 پرداخت از طریق وب", 'callback_data' => 'tonpay_paymode_web']],
                [['text' => "{$tonpayCustomMark}💳 درگاه کاستوم (پرداخت داخل ربات و مینی‌اپ)", 'callback_data' => 'tonpay_paymode_custom']],
                [['text' => "🔙 بازگشت", 'callback_data' => 'tonpaysetting']],
            ]], JSON_UNESCAPED_UNICODE);
            $tonpayModeLabels = [
                'bot' => '🤖 پرداخت از طریق ربات تلگرام',
                'web' => '🌐 پرداخت از طریق وب',
                'custom' => '💳 درگاه کاستوم (پرداخت داخل ربات و مینی‌اپ)',
            ];
            $tonpayModeLabel = $tonpayModeLabels[$tonpayMode] ?? $tonpayModeLabels['bot'];
            $tonpayCustomWarning = '';
            if ($tonpayMode === 'custom' && (!is_callable([\Faoxima\Payments\Gateways\Tonpay\CustomClient::class, 'apiKey']) || \Faoxima\Payments\Gateways\Tonpay\CustomClient::apiKey() === '')) {
                $tonpayCustomWarning = "\n\n⚠️ کلید API درگاه کاستوم هنوز ثبت نشده است؛ تا زمان ثبت کلید، ساخت فاکتور جدید با خطا مواجه می‌شود.";
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🔀 مقصد پرداخت تون‌پی\n\nلینک پرداختی که برای کاربر ارسال می‌شود را انتخاب کنید. اگر لینک وب از سمت تون‌پی دریافت نشود، لینک ربات تلگرام ارسال خواهد شد.\n\nدر حالت کاستوم، کاربر تمام مراحل پرداخت (کارت، رسید و پیگیری) را داخل ربات یا مینی‌اپ انجام می‌دهد. فاکتورهای قبلی با همان روشی که ساخته شده‌اند ادامه پیدا می‌کنند.\n\nحالت فعلی: <b>{$tonpayModeLabel}</b>{$tonpayCustomWarning}", $tonpayModeKb, 'HTML');
            break;
        }

        return true;
    }
}
