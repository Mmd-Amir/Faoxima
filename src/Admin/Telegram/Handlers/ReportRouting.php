<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminChannelMenu, $adminExecution, $backadmin, $from_id, $json_list_marzban_panel, $keyboard_shop_manage, $keyboardadmin, $keyboardtimereset, $name_product, $pdo, $setting, $shopkeyboard, $textbotlang.
 * Inherited or exported by reference: $panel, $text, $user.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ReportRouting
{
    public static function handle(
        $adminAction,
        $adminChannelMenu,
        $adminExecution,
        $backadmin,
        $from_id,
        $json_list_marzban_panel,
        $keyboard_shop_manage,
        $keyboardadmin,
        $keyboardtimereset,
        $name_product,
        &$panel,
        $pdo,
        $setting,
        $shopkeyboard,
        &$text,
        $textbotlang,
        &$user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $textreports = "📣 تنظیم گروه گزارشات ربات

در این بخش می‌توانید آیدی عددی گروه موردنظر را برای ارسال اعلان‌ها و گزارشات ربات ثبت نمایید.

آموزش تنظیم گروه:
1 ـ ابتدا یک گروه جدید ایجاد کنید یا گروه موردنظر خود را انتخاب نمایید.
2 ـ از تنظیمات گروه، قابلیت تاپیک را فعال کنید.
3 ـ ربات خود را به گروه اضافه کرده و دسترسی مدیریت موردنیاز را به آن بدهید.
4 ـ سپس داخل همان گروه، یکی از عبارت‌های «آیدی» یا «ایدی» را ارسال کنید.
5 ـ ربات، آیدی عددی گروه را برای شما نمایش می‌دهد. آیدی نمایش‌داده‌شده را کپی کرده و در این بخش برای ربات ارسال نمایید.

⚠️ توجه داشته باشید که قبل از درخواست آیدی، تاپیک گروه باید فعال شده باشد.

آیدی عددی فعلی شما: {$setting['Channel_Report']}";
            \Faoxima\Telegram\Navigation\Steps::step('addchannelid', $from_id);
            if (isset($user) && is_array($user)) {
                $user['step'] = 'addchannelid';
            }
            if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'addchannelid');
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textreports, $backadmin, 'HTML');
            break;
        case 2:
            $text = trim((string) $text);
            if (!preg_match('/^-\d+$/', $text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ آیدی گروه معتبر نیست\n\nلطفاً آیدی عددی نمایش‌داده‌شده توسط ربات در گروه را بدون تغییر ارسال نمایید.", null, 'HTML');
                return;
            }
            $outputcheck = sendmessage($text, $textbotlang['Admin']['Channel']['TestChannel'], null, 'HTML');
            if (empty($outputcheck['ok'])) {
                $errorDescription = 'نامشخص';
                if (is_array($outputcheck) && isset($outputcheck['description'])) {
                    $errorDescription = $outputcheck['description'];
                } elseif (is_string($outputcheck) && $outputcheck !== '') {
                    $errorDescription = $outputcheck;
                }
                $texterror = "❌ اتصال به گروه با موفقیت انجام نشد

خطای دریافتی :  {$errorDescription}";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $texterror, null, 'HTML');
                return;
            }
            if ($outputcheck['result']['chat']['is_forum'] == false) {
                $texterror = "❌ گروه انتخاب شده درحالت انجمن نیست ابتدا قابلیت تاپیک گروه را روشن کرده سپس آیدی عددی گروه را مجددا تنظیم نمایید";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $texterror, null, 'HTML');
                return;
            }
            @ignore_user_abort(true);
            @set_time_limit(120);
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⏳ در حال ساخت تاپیک‌های گزارش... این کار تا حدود یک دقیقه ممکن است طول بکشد، لطفاً صبر کنید.", null, 'HTML');
            $reportTopics = [
                'buyreport'     => "🛍 گزارش های خرید",
                'otherservice'  => "📌 گزارش خرید خدمات",
                'reporttest'    => "🔑 گزارش اکانت تست",
                'otherreport'   => "⚙️ سایر گزارشات",
                'errorreport'   => "❌ گزارش خطا ها",
                'paymentreport' => "💰 گزارش مالی",
                'receiptreport' => "🧾 گزارش رسید",
            ];

            $extraReportKeys = ['porsantreport', 'reportnight', 'reportcron', 'backupfile'];

            foreach ($reportTopics as $reportKey => $reportName) {
                \Faoxima\Core\Database\Db::update("topicid", "idreport", "0", "report", $reportKey);
            }
            foreach ($extraReportKeys as $extraKey) {
                \Faoxima\Core\Database\Db::update("topicid", "idreport", "0", "report", $extraKey);
            }
            \Faoxima\Core\Database\Db::update("setting", "Channel_Report", $text);

            $createdCount = 0;
            foreach ($reportTopics as $reportKey => $reportName) {
                $topicResult = null;
                for ($try = 1; $try <= 3; $try++) {
                    $topicResult = telegram('createForumTopic', [
                        'chat_id' => $text,
                        'name' => $reportName
                    ]);
                    if (!empty($topicResult['ok'])) {
                        break;
                    }
                    $retryAfter = isset($topicResult['parameters']['retry_after']) ? (int)$topicResult['parameters']['retry_after'] : 2;
                    if ($retryAfter < 1) $retryAfter = 1;
                    if ($retryAfter > 10) $retryAfter = 10;
                    sleep($retryAfter);
                }
                if (!empty($topicResult['ok']) && isset($topicResult['result']['message_thread_id'])) {
                    \Faoxima\Core\Database\Db::update("topicid", "idreport", $topicResult['result']['message_thread_id'], "report", $reportKey);
                    $createdCount++;
                    usleep(400000);
                }
            }

            $totalTopics = count($reportTopics);
            if ($createdCount >= $totalTopics) {
                if (method_exists(\Faoxima\Core\Cron\Crontab::class, 'rxEnsureCronRegistered')) {
                    \Faoxima\Core\Cron\Crontab::rxEnsureCronRegistered('report_group_success');
                }
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Channel']['SetChannelReport'], $adminChannelMenu ?? $keyboardadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('channelhub', $from_id);
                if (isset($user) && is_array($user)) {
                    $user['step'] = 'channelhub';
                }
                if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
                    \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'channelhub');
                }
            } elseif ($createdCount == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ هیچ تاپیکی ساخته نشد. مطمئن شوید ربات در گروه ادمین است و دسترسی «مدیریت تاپیک‌ها» (Manage Topics) دارد، سپس دوباره تلاش کنید.", null, 'HTML');
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ ساخت تاپیک‌ها کامل نشد ({$createdCount} از {$totalTopics} ساخته شد). علت معمولاً کندی یا محدودیت موقت تلگرام است. چند لحظه صبر کنید و دوباره همین گزینه («📣 گزارشات ربات») را بزنید.", null, 'HTML');
            }
            break;
        case 3:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['selectoption'], $shopkeyboard, 'HTML');
            break;
        case 4:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ افزودن محصول از طریق ربات غیرفعال است.\n\nبرای ساخت محصول جدید و دسترسی به تمام تنظیمات محصول، لطفاً از پنل مدیریت وب استفاده کنید.", $keyboard_shop_manage, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            if (strlen($text) > 150) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
                return;
            }
            if (in_array($text, $name_product)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("clear", "name_product", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['setagentproduct'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_agent', $from_id);
            break;
        case 6:
            $agent = ["n", "f", "n2"];
            $text = \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_resolveAgentGroupFromReplyButton($text, $agent);
            if ($text === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], \Faoxima\Admin\Telegram\AgentCashbackKeyboards::rx_agentGroupKeyboard(false), 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "agent", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Service_location'], $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_location', $from_id);
            break;
        case 7:
            $panel = ($text === '/all') ? null : (method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $text, "select"));
            if ($text !== '/all' && (!is_array($panel) || empty($panel))) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخابی اشتباه است", null, 'HTML');
                return;
            }
            $locCanonical = ($text === '/all') ? '/all' : $panel['name_panel'];
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "Location", $locCanonical);
            if ($text !== '/all' && \Faoxima\Services\Panels\PanelFeatures::panel_feature_enabled($locCanonical, 'categorygeneral')) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 نام دسته بندی خود را ارسال نمایید.", \Faoxima\Telegram\Keyboard\AdminCategoryFunctions::KeyboardCategoryadmin(), 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step("getcategory", $from_id);
                return;
            }
            if ($text !== '/all' && !is_array($panel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ساخت محصول فروش دستی از ربات غیرفعال است. لطفاً از بخش مدیریت وب اقدام کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_time', $from_id);
            break;
        case 8:
            $category = \Faoxima\Core\Database\Db::select("category", "*", "remark", $text, "count");
            if ($category == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", \Faoxima\Telegram\Keyboard\AdminCategoryFunctions::KeyboardCategoryadmin(), 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "category", $text);
            $userdata = json_decode($user['Processing_value'], true);
            $panel = $userdata['Location'] === '/all' ? null : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
            if ($userdata['Location'] !== '/all' && !is_array($panel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            if (is_array($panel) && ($panel['type'] ?? '') == "Manualsale") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ساخت محصول فروش دستی از ربات غیرفعال است. لطفاً از بخش مدیریت وب اقدام کنید.", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_time', $from_id);
            break;
        case 9:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidvolume'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "Volume_constraint", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GettIime'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('get_price', $from_id);
            break;
        case 10:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Invalidtime'] ?? '❌ زمان نامعتبر است', $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "Service_time", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('gettimereset', $from_id);
            break;
        case 11:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "price_product", $text);
            $userdata = json_decode($user['Processing_value'], true);
            $panel = $userdata['Location'] === '/all' ? null : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $userdata['Location'], "select");
            if ($userdata['Location'] !== '/all' && !is_array($panel)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ پنل انتخابی در دسترس نیست", $backadmin, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
                return;
            }
            $panelType = is_array($panel) ? ($panel['type'] ?? '') : '';
            if ($panelType == "marzban") {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['gettimereset'], $keyboardtimereset, 'HTML');
                \Faoxima\Telegram\Navigation\Steps::step('getnote', $from_id);
                return;
            }
            \Faoxima\Telegram\Conversation\ConversationData::savedata("save", "data_limit_reset", "no_reset");
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, " 🗒 یادداشت را برای محصول ارسال کنید. این یادداشت در پیش فاکتور کاربر نشان داده می شود.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('endstep', $from_id);
            break;
        }

        return true;
    }
}
