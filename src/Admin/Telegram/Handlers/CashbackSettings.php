<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $CartManage, $NowPaymentsManage, $abangateway, $adminAction, $adminExecution, $backadmin, $blupal, $callback_query_id, $cubepay, $dataget, $from_id, $keyboardzarinpal, $message_id, $nowpayment_setting_keyboard, $rialit, $text, $textbotlang, $tonpay, $trnado, $user.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class CashbackSettings
{
    public static function handle(
        $CartManage,
        $NowPaymentsManage,
        $abangateway,
        $adminAction,
        $adminExecution,
        $backadmin,
        $blupal,
        $callback_query_id,
        $cubepay,
        $dataget,
        $from_id,
        $keyboardzarinpal,
        $message_id,
        $nowpayment_setting_keyboard,
        $rialit,
        $text,
        $textbotlang,
        $tonpay,
        $trnado,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashcart", $from_id);
            break;
        case 2:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackcart");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "CartManage");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashiranpay2", $from_id);
            break;
        case 4:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackiranpay2");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "trnado");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtonpay", $from_id);
            break;
        case 6:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbacktonpay");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "tonpay");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashblupal", $from_id);
            break;
        case 8:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackblupal");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "blupal");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashvariza", $from_id);
            break;
        case 10:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackvariza");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "variza");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن عدد صفر ارسال کنید )", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashabangateway", $from_id);
            break;
        case 12:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackabangateway");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "abangateway");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 13:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashatlaspay", $from_id);
            break;
        case 14:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackatlaspay");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "atlaspay");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 15:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashcubepay", $from_id);
            break;
        case 16:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackcubepay");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "cubepay");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 17:
            $currentfeecubepay = \Faoxima\Core\Database\Db::getPaySettingValue('feecubepay', '0');
            $textfeecubepay = "⚖️ کارمزد کیوب‌پی را چه کسی بپردازد؟\n\n"
                . "کیوب‌پی بابت هر تراکنش کارمزدی از کیف پول شما کم می‌کند. اینجا می‌توانید آن هزینه را روی فاکتور بگذارید تا کاربر پرداختش کند:\n\n"
                . "🔹 صفر (پیش‌فرض): غیرفعال. کارمزد را خودتان می‌پردازید.\n"
                . "🔹 عدد ۱ تا ۱۰۰: همان درصد به مبلغ فاکتور اضافه می‌شود (اعشار مجاز است، مثلاً 9.9).\n"
                . "🔹 عدد بالای ۱۰۰: همان مبلغ به تومان به فاکتور اضافه می‌شود.\n\n"
                . "⚠️ فقط مبلغ پرداختی بزرگ‌تر می‌شود؛ اعتباری که به کاربر داده می‌شود همان مبلغ درخواستی خودش است.\n\n"
                . "📌 مقدار فعلی: <code>" . htmlspecialchars((string) $currentfeecubepay, ENT_QUOTES, 'UTF-8') . "</code>";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textfeecubepay, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getfeecubepay", $from_id);
            break;
        case 18:
            $feevalue = str_replace([',', '،'], '', trim($text));
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $feevalue)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ کارمزد کیوب‌پی تنظیم گردید.", $cubepay, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $feevalue, "NamePay", "feecubepay");
            break;
        case 19:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashplisio", $from_id);
            break;
        case 20:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackplisio");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "NowPaymentsManage");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 21:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashnowpayment", $from_id);
            break;
        case 22:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "cashbacknowpayment");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "nowpayment_setting_keyboard");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 23:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 در این بخش می توانید تعیین کنید کاربر پس از پرداخت چه درصدی به عنوان هدیه به حسابش واریز شود. ( برای غیرفعال کردن این قابلیت عدد صفر ارسال کنید)", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashzarinpal", $from_id);
            break;
        case 24:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "cashback_percent", $text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_key", "chashbackzarinpal");
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_menu", "keyboardzarinpal");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 جامعه هدف این کش‌بک را انتخاب نمایید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashtarget", $from_id);
            break;
        case 25:
            $resolvedTarget = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveCashbackTargetFromReplyButton($text);
            if ($resolvedTarget === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ گزینه نامعتبر است، لطفا از دکمه های زیر انتخاب کنید", \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackTargetKeyboard(), 'HTML');
                return;
            }
            if ($resolvedTarget === 'custom') {
                \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_target", $resolvedTarget);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 تعداد روز مورد نظر را به صورت عدد وارد کنید (بین 1 تا 365)", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("getcashtargetdays", $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_target", $resolvedTarget);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_scope", \Faoxima\Cashback\Eligibility::cashbackScopeDefault());
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 این کش‌بک روی کدام بخش‌ها اعمال شود؟", json_encode(['inline_keyboard' => \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackScopeKeyboardRows('cbscope_', \Faoxima\Cashback\Eligibility::cashbackScopeDefault())], JSON_UNESCAPED_UNICODE), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashscope", $from_id);
            break;
        case 26:
            if (!ctype_digit($text) || (int)$text < 1 || (int)$text > 365) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ لطفا یک عدد صحیح بین 1 تا 365 ارسال کنید", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_target", "custom:" . (int)$text);
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_scope", \Faoxima\Cashback\Eligibility::cashbackScopeDefault());
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 این کش‌بک روی کدام بخش‌ها اعمال شود؟", json_encode(['inline_keyboard' => \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackScopeKeyboardRows('cbscope_', \Faoxima\Cashback\Eligibility::cashbackScopeDefault())], JSON_UNESCAPED_UNICODE), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getcashscope", $from_id);
            break;
        case 27:
            $scopeKey = $dataget[1];
            $userdata = json_decode($user['Processing_value'], true);
            $selected = is_array($userdata['cashback_scope'] ?? null) ? $userdata['cashback_scope'] : \Faoxima\Cashback\Eligibility::cashbackScopeDefault();
            if (in_array($scopeKey, $selected, true)) {
                $selected = array_values(array_diff($selected, [$scopeKey]));
            } else {
                $selected[] = $scopeKey;
                $selected = array_values($selected);
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_scope", $selected);
            Editmessagetext($from_id, $message_id, "📌 این کش‌بک روی کدام بخش‌ها اعمال شود؟", json_encode(['inline_keyboard' => \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackScopeKeyboardRows('cbscope_', $selected)], JSON_UNESCAPED_UNICODE), 'HTML');
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            break;
        case 28:
            $userdata = json_decode($user['Processing_value'], true);
            $selected = is_array($userdata['cashback_scope'] ?? null) ? $userdata['cashback_scope'] : [];
            $allDefault = \Faoxima\Cashback\Eligibility::cashbackScopeDefault();
            $isAllSelected = count(array_diff($allDefault, $selected)) === 0 && count($selected) === count($allDefault);
            $selected = $isAllSelected ? [] : $allDefault;
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "cashback_scope", $selected);
            Editmessagetext($from_id, $message_id, "📌 این کش‌بک روی کدام بخش‌ها اعمال شود؟", json_encode(['inline_keyboard' => \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_cashbackScopeKeyboardRows('cbscope_', $selected)], JSON_UNESCAPED_UNICODE), 'HTML');
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            break;
        case 29:
            $cashbackMenus = [
                'CartManage' => $CartManage,
                'trnado' => $trnado,
                'tonpay' => $tonpay,
                'rialit' => $rialit,
                'blupal' => $blupal,
                'abangateway' => $abangateway,
                'cubepay' => $cubepay,
                'NowPaymentsManage' => $NowPaymentsManage,
                'nowpayment_setting_keyboard' => $nowpayment_setting_keyboard,
                'keyboardzarinpal' => $keyboardzarinpal,
            ];
            $userdata = json_decode($user['Processing_value'], true);
            $cashbackKey = $userdata['cashback_key'] ?? '';
            $cashbackPercent = $userdata['cashback_percent'] ?? '0';
            $cashbackMenuKey = $userdata['cashback_menu'] ?? '';
            $cashbackTarget = $userdata['cashback_target'] ?? 'all';
            $selectedScope = is_array($userdata['cashback_scope'] ?? null) ? $userdata['cashback_scope'] : \Faoxima\Cashback\Eligibility::cashbackScopeDefault();
            if ($cashbackKey === '') {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $textbotlang['Admin']['agent']['invalidvlue'], 'show_alert' => true]);
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
                return;
            }
            if (empty($selectedScope)) {
                telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => '❌ حداقل یک بخش را انتخاب کنید یا گزینه «همه موارد» را بزنید.', 'show_alert' => true]);
                return;
            }
            $allDefault = \Faoxima\Cashback\Eligibility::cashbackScopeDefault();
            $isAllSelected = count(array_diff($allDefault, $selectedScope)) === 0 && count($selectedScope) === count($allDefault);
            $scopeToSave = $isAllSelected ? 'all' : implode(',', $selectedScope);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $cashbackPercent, "NamePay", $cashbackKey);
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $cashbackTarget, "NamePay", $cashbackKey . "_target");
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $scopeToSave, "NamePay", $cashbackKey . "_scope");
            $backKeyboard = $cashbackMenus[$cashbackMenuKey] ?? $backadmin;
            if (!empty($message_id)) {
                deletemessage($from_id, $message_id);
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ مبلغ، جامعه هدف و بخش‌های اعمال با موفقیت ذخیره گردید.", $backKeyboard, 'HTML');
            telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'cache_time' => 0]);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        }

        return true;
    }
}
