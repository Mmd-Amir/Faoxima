<?php

namespace Faoxima\Admin\Telegram\Handlers;

use Faoxima\Services\Panels\Remnawave\RemnawaveManager;

/**
 * Handler stage. Inherited by value: $activeUsers, $active_users, $adminAction, $adminExecution, $from_id, $json_list_marzban_panel, $optionGuard, $optionJovad, $optionManualsale, $optionMarzban, $optionPasarGuard, $optionRebecca, $optionX_ui_single, $option_remnawave, $optionwg, $pasarguardCredsMissing, $pasarguardTestRes, $pdo, $rebeccaServiceLine, $serverStatusBlock, $textbotlang.
 * Inherited or exported by reference: $marzban_list_get, $text, $totalUsers.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ProviderAdministrationCompletion
{
    public static function handle(
        $activeUsers,
        $active_users,
        $adminAction,
        $adminExecution,
        $from_id,
        $json_list_marzban_panel,
        &$marzban_list_get,
        $optionGuard,
        $optionJovad,
        $optionManualsale,
        $optionMarzban,
        $optionPasarGuard,
        $optionRebecca,
        $optionX_ui_single,
        $option_remnawave,
        $optionwg,
        $pasarguardCredsMissing,
        $pasarguardTestRes,
        $pdo,
        $rebeccaServiceLine,
        $serverStatusBlock,
        &$text,
        $textbotlang,
        &$totalUsers
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $marzban_list_get = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $text, "select");
            if (!is_array($marzban_list_get) || empty($marzban_list_get)) {
                $notFoundMessage = $textbotlang['Admin']['managepanel']['nullpanel'] ?? "❌ پنل مورد نظر یافت نشد.";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $notFoundMessage, $json_list_marzban_panel, 'HTML');
                return;
            }
            $panelCanonicalName = $marzban_list_get['name_panel'];
            $text = $panelCanonicalName;
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $panelCanonicalName, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            if ($marzban_list_get['type'] == "marzban") {
                $Check_token = \Faoxima\Services\Panels\Drivers\Marzban::token_panel($marzban_list_get['code_panel']);
                if (isset($Check_token['access_token'])) {
                    $System_Stats = \Faoxima\Services\Panels\Drivers\Marzban::Get_System_Stats($panelCanonicalName);
                    if ((string)($marzban_list_get['version_panel'] ?? '0') === '1') {
                        $active_users = $System_Stats['active_users']
                            ?? $System_Stats['users_active']
                            ?? $System_Stats['online_users']
                            ?? 0;
                    } else {
                        $active_users = $System_Stats['users_active']
                            ?? $System_Stats['active_users']
                            ?? $System_Stats['online_users']
                            ?? 0;
                    }
                    $total_user = $System_Stats['total_user'];
                    $mem_total = \Faoxima\Core\Support\Formatting::formatBytes($System_Stats['mem_total']);
                    $mem_used = \Faoxima\Core\Support\Formatting::formatBytes($System_Stats['mem_used']);
                    $bandwidth = \Faoxima\Core\Support\Formatting::formatBytes($System_Stats['outgoing_bandwidth'] + $System_Stats['incoming_bandwidth']);
                    $rx_sales_metrics = \Faoxima\Admin\Reporting\PanelSalesMetrics::rx_panel_sales_metrics_block($marzban_list_get['name_panel']);

                    $Condition_marzban = "";
                    $text_marzban = "
آمار پنل شما👇:

🖥 وضعیت اتصال پنل مرزبان: ✅ پنل متصل است

👥  تعداد کل کاربران: $total_user

👤 تعداد کاربران فعال: $active_users

📡 نسخه پنل مرزبان :  {$System_Stats['version']}

💻 رم  کل سرور  : $mem_total

💻 مصرف رم پنل مرزبان  : $mem_used

🌐 ترافیک کل مصرف شده  ( آپلود / دانلود) : $bandwidth

$rx_sales_metrics

⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
                } elseif (($Check_token['error_code'] ?? null) === 'auth_busy') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⏳ " . $Check_token['error'], $optionMarzban, 'HTML');
                } elseif (isset($Check_token['detail']) && $Check_token['detail'] == "Incorrect username or password") {
                    $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
                } else {
                    $errorDetails = json_encode($Check_token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
                    if (!empty($errorDetails) && $errorDetails !== 'null') {
                        $text_marzban .= PHP_EOL . "علت خطا: {$errorDetails}";
                    }
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_marzban, $optionMarzban, 'HTML');
                }
            } elseif ($marzban_list_get['type'] == "guard") {
                $guardConfig = \Faoxima\Services\Panels\Drivers\Guard::getGuardPanelConfig($marzban_list_get['name_panel']);
                if ($guardConfig['status'] === false) {
                    $errorMsg = $guardConfig['msg'] ?? $textbotlang['Admin']['managepanel']['guard']['connection_error'];
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ {$errorMsg}", $optionGuard, 'HTML');
                } else {
                    $testResult = \Faoxima\Services\Panels\Drivers\Guard::guardTestConnection($guardConfig['panel']['url_panel'], $guardConfig['api_key'], $guardConfig['panel']['guard_version'] ?? 'v1');
                    $text_marzban = !empty($testResult['status']) ? "✅ اتصال به گارد برقرار است" : \Faoxima\Admin\Telegram\Support\GuardFunctionsHelpers::guardFormatConnectionResult($testResult);

                    $guardAdminsStats = \Faoxima\Services\Panels\Drivers\Guard::guardGetAdminsStats($guardConfig);
                    $guardSubsStats = \Faoxima\Services\Panels\Drivers\Guard::guardGetSubscriptionsStats($guardConfig);
                    if (!empty($guardAdminsStats['status']) || !empty($guardSubsStats['status'])) {
                        $text_marzban .= "\n\n‏──────────────\n👥 آمار کاربران و اتصال\n‏──────────────";
                        if (!empty($guardSubsStats['status'])) {
                            $s = $guardSubsStats['stats'];
                            if (isset($s['total'])) {
                                $text_marzban .= "\n\n👤 کل کاربران: {$s['total']}";
                            }
                            if (isset($s['online'])) {
                                $text_marzban .= "\n\n🟢 آنلاین: {$s['online']}";
                            }
                            if (isset($s['active'])) {
                                $text_marzban .= "\n\n✅ فعال: {$s['active']}";
                            }
                            if (isset($s['expired'])) {
                                $text_marzban .= "\n\n⏳ منقضی‌شده: {$s['expired']}";
                            }
                            if (isset($s['disabled'])) {
                                $text_marzban .= "\n\n⛔ غیرفعال: {$s['disabled']}";
                            }
                            if (isset($s['limited'])) {
                                $text_marzban .= "\n\n📉 محدودشده: {$s['limited']}";
                            }
                            if (isset($s['on_hold'])) {
                                $text_marzban .= "\n\n⏸ معلق: {$s['on_hold']}";
                            }
                        }
                        if (!empty($guardAdminsStats['status'])) {
                            $a = $guardAdminsStats['stats'];
                            if (isset($a['total'])) {
                                $text_marzban .= "\n\n🛡 کل ادمین‌ها: {$a['total']}";
                            }
                            if (isset($a['online'])) {
                                $text_marzban .= "\n\n🟢 ادمین آنلاین: {$a['online']}";
                            }
                        }
                    }

                    $rx_sales_metrics = \Faoxima\Admin\Reporting\PanelSalesMetrics::rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                    $text_marzban .= "\n\n{$rx_sales_metrics}"
                        . "\n\n⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_marzban, $optionGuard, 'HTML');
                }
            } elseif ($marzban_list_get['type'] == "x-ui_single") {
                if (\Faoxima\Services\Panels\Drivers\XuiSingle::xui_panel_uses_token($marzban_list_get)) {
                    $xuiTokenCheck = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_api_token_request($marzban_list_get, 'GET', '/panel/api/inbounds/list', null, 6, 'panel_menu_check');
                    $xuiTokenOk = (($xuiTokenCheck['status'] ?? 0) >= 200 && ($xuiTokenCheck['status'] ?? 0) < 300 && empty($xuiTokenCheck['error']));
                    if ($xuiTokenOk) {
                        $xuiPanelInfoText = $textbotlang['Admin']['managepanel']['connectx-ui'];
                        $xuiStatus = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_server_status($marzban_list_get);
                        if (!empty($xuiStatus['status'])) {
                            $xuiData = $xuiStatus['data'];
                            $xuiCpu = isset($xuiData['cpu']) ? round(floatval($xuiData['cpu']), 1) : null;
                            $xuiMemCurrent = $xuiData['mem']['current'] ?? null;
                            $xuiMemTotal = $xuiData['mem']['total'] ?? null;
                            $xuiDiskCurrent = $xuiData['disk']['current'] ?? null;
                            $xuiDiskTotal = $xuiData['disk']['total'] ?? null;
                            $xuiUptimeDays = isset($xuiData['uptime']) ? round(intval($xuiData['uptime']) / 86400, 1) : null;
                            $xuiXrayState = $xuiData['xray']['state'] ?? null;
                            $xuiXrayVersion = $xuiData['xray']['version'] ?? null;
                            $xuiPanelVersion = $xuiData['panelVersion'] ?? null;
                            $xuiPanelInfoText .= "\n\n📊 وضعیت سرور";
                            if ($xuiPanelVersion !== null && $xuiPanelVersion !== '') {
                                $xuiPanelInfoText .= "\n\n🧩 نسخه پنل: {$xuiPanelVersion}";
                            }
                            if ($xuiCpu !== null) {
                                $xuiPanelInfoText .= "\n\n🖥 پردازنده: {$xuiCpu}%";
                            }
                            if ($xuiMemCurrent !== null && $xuiMemTotal !== null && $xuiMemTotal > 0) {
                                $xuiPanelInfoText .= "\n\n🧠 حافظه: " . round($xuiMemCurrent / 1073741824, 2) . " از " . round($xuiMemTotal / 1073741824, 2) . " گیگابایت";
                            }
                            if ($xuiDiskCurrent !== null && $xuiDiskTotal !== null && $xuiDiskTotal > 0) {
                                $xuiPanelInfoText .= "\n\n💾 فضای دیسک: " . round($xuiDiskCurrent / 1073741824, 2) . " از " . round($xuiDiskTotal / 1073741824, 2) . " گیگابایت";
                            }
                            if ($xuiUptimeDays !== null) {
                                $xuiPanelInfoText .= "\n\n⏱ مدت روشن‌بودن: {$xuiUptimeDays} روز";
                            }
                            if ($xuiXrayState !== null) {
                                $xuiXrayLabel = $xuiXrayState === 'running' ? 'فعال ✅' : 'متوقف ❌';
                                if ($xuiXrayVersion !== null && $xuiXrayVersion !== '') {
                                    $xuiXrayLabel .= " (نسخه {$xuiXrayVersion})";
                                }
                                $xuiPanelInfoText .= "\n\n⚙️ وضعیت Xray: {$xuiXrayLabel}";
                            }
                        }
                        $xuiSummary = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_clients_summary($marzban_list_get);
                        if (!empty($xuiSummary['status'])) {
                            $xuiOnlines = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_clients_onlines($marzban_list_get);
                            $xuiOnlineCount = !empty($xuiOnlines['status']) ? count($xuiOnlines['emails']) : null;
                            $xuiPanelInfoText .= "\n\n‏──────────────\n👥 وضعیت کاربران پنل\n‏──────────────";
                            if ($xuiOnlineCount !== null) {
                                $xuiPanelInfoText .= "\n\n🟢 آنلاین: {$xuiOnlineCount}";
                            }
                            $xuiPanelInfoText .= "\n\n👤 کل کاربران: {$xuiSummary['total']}";
                            $xuiPanelInfoText .= "\n\n⏳ منقضی‌شده: {$xuiSummary['expired']}";
                            $xuiPanelInfoText .= "\n\n📉 حجم‌تمام‌شده: {$xuiSummary['depleted']}";
                            $xuiPanelInfoText .= "\n\n⛔ غیرفعال: {$xuiSummary['disabled']}";
                        }
                        $rx_sales_metrics = \Faoxima\Admin\Reporting\PanelSalesMetrics::rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                        $xuiPanelInfoText .= "\n\n" . $rx_sales_metrics;
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $xuiPanelInfoText, $optionX_ui_single, 'HTML');
                    } else {
                        $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
                        if (!empty($xuiTokenCheck['error'])) {
                            $text_marzban .= PHP_EOL . "علت خطا: {$xuiTokenCheck['error']}";
                        }
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
                    }
                } else {
                    $x_ui_check_connect = \Faoxima\Services\Panels\Drivers\XuiSingle::login($marzban_list_get['code_panel'], false);
                    if ($x_ui_check_connect['success']) {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['connectx-ui'], $optionX_ui_single, 'HTML');
                    } elseif (!empty($x_ui_check_connect['msg']) && $x_ui_check_connect['msg'] == "Invalid username or password.") {
                        $text_marzban = "❌ نام کاربری یا رمز عبور پنل اشتباه است";
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
                    } else {
                        $text_marzban = $textbotlang['Admin']['managepanel']['errorstateuspanel'];
                        if (!empty($x_ui_check_connect['errror'])) {
                            $text_marzban .= PHP_EOL . "علت خطا: {$x_ui_check_connect['errror']}";
                        }
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_marzban, $optionX_ui_single, 'HTML');
                    }
                }
            } elseif ($marzban_list_get['type'] == "Manualsale") {
                $rx_sales_metrics = \Faoxima\Admin\Reporting\PanelSalesMetrics::rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                $text_manualsale = "یک گزینه را انتخاب نمایید

{$rx_sales_metrics}";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_manualsale, $optionManualsale, 'HTML');
            } elseif ($marzban_list_get['type'] == "WGDashboard") {
                $rx_sales_metrics = \Faoxima\Admin\Reporting\PanelSalesMetrics::rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                $text_wg = "آمار پنل شما👇:

{$rx_sales_metrics}

⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_wg, $optionwg, 'HTML');
            } elseif ($marzban_list_get['type'] == "remnawave") {
                $mgr = new RemnawaveManager($marzban_list_get);
                $testRes = $mgr->testConnection($pdo);
                if (!empty($testRes['ok'])) {
                    $stats = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_system_stats($marzban_list_get['name_panel']);
                    $rx_sales_metrics = \Faoxima\Admin\Reporting\PanelSalesMetrics::rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                    $remnaVersion = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_panel_version($marzban_list_get['name_panel']);
                    $versionLine = $remnaVersion !== null ? "\n📡 نسخه پنل رمن‌ویو: {$remnaVersion}\n" : '';
                    $statsUnavailableNote = '';
                    if (!empty($stats['ok'])) {
                        $totalUsers = $stats['total_users'];
                        $activeUsers = $stats['active_users'];
                        $serverStatusBlock = "
‏──────────────
📊 «وضعیت سرور»
‏──────────────

🧮 هسته‌های پردازنده: {$stats['cpu_cores']}

🧠 حافظه: " . \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_format_bytes($stats['mem_used']) . " از " . \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_format_bytes($stats['mem_total']) . "

🌐 ترافیک کل مصرف‌شده نودها: " . ($stats['total_traffic'] > 0 ? \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_format_bytes($stats['total_traffic']) : 'نامشخص') . "

🕐 مدت روشن‌بودن: " . \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_format_uptime($stats['uptime_seconds']) . "

‏──────────────
👥 «وضعیت کاربران پنل»
‏──────────────

🟢 آنلاین: {$stats['online_now']}

👤 کل کاربران: {$stats['total_users']}

✅ فعال: {$stats['active_users']}

⏳ منقضی‌شده: {$stats['expired_users']}

📉 محدودشده: {$stats['limited_users']}

⛔️ غیرفعال: {$stats['disabled_users']}
";
                    } else {
                        $remnaSub = \Faoxima\Services\Panels\Drivers\Remnawave::remnawave_user_data_count($marzban_list_get['name_panel']);
                        $totalUsers = "نامشخص (تخمین لوکال: {$remnaSub})";
                        $activeUsers = "نامشخص (تخمین لوکال: {$remnaSub})";
                        $statsUnavailableNote = "\n⚠️ دریافت آمار زنده از پنل ناموفق بود؛ عدد بالا فقط برآورد از دیتابیس محلی است.\n";
                        $serverStatusBlock = '';
                    }
                    $text_remnawave = "
آمار پنل شما 👇 :

🖥 وضعیت اتصال پنل رمن‌ویو: ✅ پنل متصل است
{$versionLine}{$statsUnavailableNote}
👥 تعداد کل کاربران: $totalUsers

👤 تعداد کاربران فعال: $activeUsers
{$serverStatusBlock}
{$rx_sales_metrics}";
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_remnawave, $option_remnawave, 'HTML');
                } else {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل رمن‌ویو: ❌ اتصال ناموفق
" . ($testRes['message'] ?? '') . "

لطفاً آدرس پنل یا توکن API را بررسی کنید.", $option_remnawave, 'HTML');
                }
            } elseif ($marzban_list_get['type'] == "jovadpanel") {
                $jovadStatusCheck = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelTestConnection($marzban_list_get['url_panel'] ?? '', $marzban_list_get['api_key'] ?? '');
                $rx_sales_metrics = \Faoxima\Admin\Reporting\PanelSalesMetrics::rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                if (($jovadStatusCheck['status'] ?? false) !== true) {
                    $text_jovad = "🖥 وضعیت اتصال پنل JovadPanel: ❌ اتصال ناموفق\n" . (string) ($jovadStatusCheck['msg'] ?? '')
                        . "\n\nلطفاً آدرس پنل یا کلید API را بررسی کنید.";
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_jovad, $optionJovad, 'HTML');
                } else {
                    $jovadSummary = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelAccountSummary($marzban_list_get);
                    $text_jovad = $jovadSummary['status'] === true
                        ? (string) $jovadSummary['text']
                        : "🖥 وضعیت اتصال پنل JovadPanel: ⚠️ اتصال برقرار شد اما دریافت اطلاعات حساب ناموفق بود.\n" . (string) ($jovadSummary['msg'] ?? '');
                    $text_jovad .= "\n\n{$rx_sales_metrics}\n\n⭕️ برای مدیریت پنل یکی از گزینه های زیر را انتخاب کنید";
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_jovad, $optionJovad, 'HTML');
                }
            } elseif ($marzban_list_get['type'] == "rebecca") {
                $rebeccaApiKey = trim((string) ($marzban_list_get['api_key'] ?? ''));
                if ($rebeccaApiKey === '') {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل Rebecca: ❌ کلید API تنظیم نشده است", $optionRebecca, 'HTML');
                } else {
                    $rebeccaTestRes = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaTestConnection($marzban_list_get['url_panel'] ?? null, $rebeccaApiKey);
                    if ($rebeccaTestRes['status'] !== false) {
                        $rebeccaStats = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaSystemStats($marzban_list_get['name_panel']);
                        $rebeccaStatsData = ($rebeccaStats['status'] !== false && is_array($rebeccaStats['data'])) ? $rebeccaStats['data'] : [];
                        $rebeccaVersion = $rebeccaStatsData['version'] ?? 'نامشخص';
                        $rebeccaTotalUsers = $rebeccaStatsData['total_user'] ?? 'نامشخص';
                        $rebeccaOnlineUsers = $rebeccaStatsData['online_users'] ?? 'نامشخص';
                        $rebeccaActiveUsers = $rebeccaStatsData['users_active'] ?? 'نامشخص';
                        $rebeccaExpiredUsers = $rebeccaStatsData['users_expired'] ?? 'نامشخص';
                        $rebeccaLimitedUsers = $rebeccaStatsData['users_limited'] ?? 'نامشخص';
                        $rebeccaDisabledUsers = $rebeccaStatsData['users_disabled'] ?? 'نامشخص';
                        $rebeccaCpuUsage = isset($rebeccaStatsData['cpu_usage']) ? round($rebeccaStatsData['cpu_usage'], 1) : 'نامشخص';
                        $rebeccaMemUsed = isset($rebeccaStatsData['memory']['current']) ? \Faoxima\Core\Support\Formatting::formatBytes($rebeccaStatsData['memory']['current']) : 'نامشخص';
                        $rebeccaMemTotal = isset($rebeccaStatsData['memory']['total']) ? \Faoxima\Core\Support\Formatting::formatBytes($rebeccaStatsData['memory']['total']) : 'نامشخص';
                        $rebeccaDiskUsed = isset($rebeccaStatsData['disk']['current']) ? \Faoxima\Core\Support\Formatting::formatBytes($rebeccaStatsData['disk']['current']) : 'نامشخص';
                        $rebeccaDiskTotal = isset($rebeccaStatsData['disk']['total']) ? \Faoxima\Core\Support\Formatting::formatBytes($rebeccaStatsData['disk']['total']) : 'نامشخص';
                        $rebeccaUptimeDays = isset($rebeccaStatsData['uptime_seconds']) ? floor($rebeccaStatsData['uptime_seconds'] / 86400) : 'نامشخص';
                        $rebeccaXrayStatus = !empty($rebeccaStatsData['xray_running']) ? '✅ فعال' : '❌ غیرفعال';
                        $rebeccaBandwidth = (isset($rebeccaStatsData['incoming_bandwidth']) && isset($rebeccaStatsData['outgoing_bandwidth']))
                            ? \Faoxima\Core\Support\Formatting::formatBytes($rebeccaStatsData['incoming_bandwidth'] + $rebeccaStatsData['outgoing_bandwidth'])
                            : 'نامشخص';
                        $rx_sales_metrics = \Faoxima\Admin\Reporting\PanelSalesMetrics::rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                        $rebeccaServiceIdDisplay = trim((string) ($marzban_list_get['rebecca_service_id'] ?? ''));
                        if ($rebeccaServiceIdDisplay === '' || $rebeccaServiceIdDisplay === 'auto') {
                            $rebeccaServiceLine = 'خودکار';
                        } else {
                            $rebeccaServiceLine = $rebeccaServiceIdDisplay;
                            if (method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaGetServices') && method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaServiceLabel')) {
                                $rebeccaServicesForLabel = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaGetServices($marzban_list_get['name_panel']);
                                if (($rebeccaServicesForLabel['status'] ?? false) === true && is_array($rebeccaServicesForLabel['services'] ?? null)) {
                                    foreach ($rebeccaServicesForLabel['services'] as $rebeccaServiceRow) {
                                        $rebeccaRowId = (string) ($rebeccaServiceRow['id'] ?? ($rebeccaServiceRow['service_id'] ?? ''));
                                        if ($rebeccaRowId === $rebeccaServiceIdDisplay) {
                                            $rebeccaServiceLine = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaServiceLabel($rebeccaServiceRow);
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        $text_rebecca = "
پنل متصل است ✅

📡 نسخه پنل Rebecca: {$rebeccaVersion}

‏──────────────
📊 «وضعیت سرور»
‏──────────────

🖥 پردازنده: {$rebeccaCpuUsage}%

🧠 حافظه: {$rebeccaMemUsed} از {$rebeccaMemTotal}

💾 فضای دیسک: {$rebeccaDiskUsed} از {$rebeccaDiskTotal}

⏱ مدت روشن‌بودن: {$rebeccaUptimeDays} روز

⚙️ وضعیت Xray: {$rebeccaXrayStatus}

🌐 ترافیک کل مصرف شده ( آپلود / دانلود ): {$rebeccaBandwidth}

‏──────────────
👥 «وضعیت کاربران پنل»
‏──────────────

🟢 آنلاین: {$rebeccaOnlineUsers}

👤 کل کاربران: {$rebeccaTotalUsers}

✅ فعال: {$rebeccaActiveUsers}

⏳ منقضی‌شده: {$rebeccaExpiredUsers}

📈 حجم‌تمام‌شده: {$rebeccaLimitedUsers}

⛔ غیرفعال: {$rebeccaDisabledUsers}

⚙️ سرویس پیش‌فرض: {$rebeccaServiceLine}

{$rx_sales_metrics}";
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_rebecca, $optionRebecca, 'HTML');
                    } else {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل Rebecca: ❌ اتصال ناموفق
" . ($rebeccaTestRes['msg'] ?? '') . "

لطفاً آدرس پنل یا کلید API را بررسی کنید.", $optionRebecca, 'HTML');
                    }
                }
            } elseif ($marzban_list_get['type'] == "pasarguard") {
                $pasarguardAuthModeStatus = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardAuthMode($marzban_list_get);
                if ($pasarguardAuthModeStatus === 'password') {
                    $pasarguardUsernameStatus = trim((string) ($marzban_list_get['username_panel'] ?? ''));
                    $pasarguardPasswordStatus = trim((string) ($marzban_list_get['password_panel'] ?? ''));
                    $pasarguardCredsMissing = $pasarguardUsernameStatus === '' || $pasarguardPasswordStatus === '';
                    $pasarguardTestRes = $pasarguardCredsMissing
                        ? ['status' => false, 'msg' => 'نام کاربری یا رمز عبور تنظیم نشده است']
                        : \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardTestConnectionPanel($marzban_list_get);
                } else {
                    $pasarguardApiKey = trim((string) ($marzban_list_get['api_key'] ?? ''));
                    $pasarguardCredsMissing = $pasarguardApiKey === '';
                    $pasarguardTestRes = $pasarguardCredsMissing
                        ? ['status' => false, 'msg' => 'کلید API تنظیم نشده است']
                        : \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardTestConnection($marzban_list_get['url_panel'] ?? null, $pasarguardApiKey);
                }
                if ($pasarguardCredsMissing) {
                    $missingLabel = $pasarguardAuthModeStatus === 'password' ? 'نام کاربری یا رمز عبور تنظیم نشده است' : 'کلید API تنظیم نشده است';
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل PasarGuard: ❌ {$missingLabel}", $optionPasarGuard, 'HTML');
                } else {
                    if ($pasarguardTestRes['status'] !== false) {
                        $pasarguardStats = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardGetSystemStats($marzban_list_get['name_panel']);
                        $pasarguardStatsData = !empty($pasarguardStats['body']) ? json_decode($pasarguardStats['body'], true) : [];
                        if (!is_array($pasarguardStatsData)) {
                            $pasarguardStatsData = [];
                        }
                        $pasarguardVersion = $pasarguardStatsData['version'] ?? 'نامشخص';
                        $pasarguardTotalUsers = $pasarguardStatsData['total_user'] ?? 'نامشخص';
                        $pasarguardOnlineUsers = $pasarguardStatsData['online_users'] ?? 'نامشخص';
                        $pasarguardActiveUsers = $pasarguardStatsData['active_users'] ?? 'نامشخص';
                        $pasarguardExpiredUsers = $pasarguardStatsData['expired_users'] ?? 'نامشخص';
                        $pasarguardLimitedUsers = $pasarguardStatsData['limited_users'] ?? 'نامشخص';
                        $pasarguardDisabledUsers = $pasarguardStatsData['disabled_users'] ?? 'نامشخص';
                        $pasarguardCpuUsage = isset($pasarguardStatsData['cpu_usage']) ? round($pasarguardStatsData['cpu_usage'], 1) : 'نامشخص';
                        $pasarguardMemUsed = isset($pasarguardStatsData['mem_used']) ? \Faoxima\Core\Support\Formatting::formatBytes($pasarguardStatsData['mem_used']) : 'نامشخص';
                        $pasarguardMemTotal = isset($pasarguardStatsData['mem_total']) ? \Faoxima\Core\Support\Formatting::formatBytes($pasarguardStatsData['mem_total']) : 'نامشخص';
                        $pasarguardDiskUsed = isset($pasarguardStatsData['disk_used']) ? \Faoxima\Core\Support\Formatting::formatBytes($pasarguardStatsData['disk_used']) : 'نامشخص';
                        $pasarguardDiskTotal = isset($pasarguardStatsData['disk_total']) ? \Faoxima\Core\Support\Formatting::formatBytes($pasarguardStatsData['disk_total']) : 'نامشخص';
                        $pasarguardUptime = isset($pasarguardStatsData['uptime_seconds']) ? \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardFormatUptime($pasarguardStatsData['uptime_seconds']) : 'نامشخص';
                        $pasarguardBandwidth = (isset($pasarguardStatsData['incoming_bandwidth']) && isset($pasarguardStatsData['outgoing_bandwidth']))
                            ? \Faoxima\Core\Support\Formatting::formatBytes($pasarguardStatsData['incoming_bandwidth'] + $pasarguardStatsData['outgoing_bandwidth'])
                            : 'نامشخص';
                        $rx_sales_metrics = \Faoxima\Admin\Reporting\PanelSalesMetrics::rx_panel_sales_metrics_block($marzban_list_get['name_panel']);
                        $text_pasarguard = "
پنل متصل است ✅

📡 نسخه پنل PasarGuard: {$pasarguardVersion}

‏──────────────
📊 «وضعیت سرور»
‏──────────────

🖥 پردازنده: {$pasarguardCpuUsage}%

🧠 حافظه: {$pasarguardMemUsed} از {$pasarguardMemTotal}

💾 فضای دیسک: {$pasarguardDiskUsed} از {$pasarguardDiskTotal}

⏱ مدت روشن‌بودن: {$pasarguardUptime}

🌐 ترافیک کل مصرف شده ( آپلود / دانلود ): {$pasarguardBandwidth}

‏──────────────
👥 «وضعیت کاربران پنل»
‏──────────────

🟢 آنلاین: {$pasarguardOnlineUsers}

👤 کل کاربران: {$pasarguardTotalUsers}

✅ فعال: {$pasarguardActiveUsers}

⏳ منقضی‌شده: {$pasarguardExpiredUsers}

📈 محدودشده: {$pasarguardLimitedUsers}

⛔ غیرفعال: {$pasarguardDisabledUsers}

{$rx_sales_metrics}";
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text_pasarguard, $optionPasarGuard, 'HTML');
                    } else {
                        $pasarguardFailHint = $pasarguardAuthModeStatus === 'password'
                            ? 'لطفاً آدرس پنل، نام کاربری یا رمز عبور را بررسی کنید.'
                            : 'لطفاً آدرس پنل یا کلید API را بررسی کنید.';
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🖥 وضعیت اتصال پنل PasarGuard: ❌ اتصال ناموفق
" . ($pasarguardTestRes['msg'] ?? '') . "

{$pasarguardFailHint}", $optionPasarGuard, 'HTML');
                    }
                }
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "یک گزینه را انتخاب نمایید", $optionMarzban, 'HTML');
            }
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $text, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            break;
        }

        return true;
    }
}
