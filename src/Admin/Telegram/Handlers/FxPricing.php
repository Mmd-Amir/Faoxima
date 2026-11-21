<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $datain, $from_id, $text, $textbotlang, $user.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class FxPricing
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $datain,
        $from_id,
        $text,
        $textbotlang,
        $user
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $rxFxPanelName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            $rxFxPanel = $rxFxPanelName !== '' ? \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $rxFxPanelName, "select", ['cache' => false]) : false;
            if (!is_array($rxFxPanel) || empty($rxFxPanel['code_panel'])) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
            $rxFxMenu = \Faoxima\Orders\Pricing\ExchangePricing::fxAdminMenuRender($rxFxPanel);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxFxMenu['text'], $rxFxMenu['keyboard'], 'HTML');
            break;
        case 2:
            if (!preg_match('/^fxp_(menu|back|on|onok|off|rebase|base|markup|round|stale|jump|tp|tcv|tct|tev|tet|tp0|tp1|tcv0|tcv1|tct0|tct1|tev0|tev1|tet0|tet1|manual|unmanual|test)_([A-Za-z0-9_-]{1,100})$/', $datain, $rxFxCb)) {
                if (!empty($callback_query_id)) {
                    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "❌ درخواست نامعتبر است.", 'show_alert' => false]);
                }
                return;
            }
            $rxFxPanel = \Faoxima\Orders\Pricing\ExchangePricing::fxAdminLoadPanel($rxFxCb[2]);
            $rxFxCurrentName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            if ($rxFxPanel === null || (string)$rxFxPanel['name_panel'] !== (string)$rxFxCurrentName) {
                if (!empty($callback_query_id)) {
                    telegram('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "⚠️ این منو منقضی شده است؛ دوباره از مدیریت پنل وارد شوید.", 'show_alert' => true]);
                }
                return;
            }
            $rxFxAction = $rxFxCb[1];
            $rxFxCode = (string)$rxFxPanel['code_panel'];
            $rxFxConfig = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelConfig($rxFxPanel);
            $rxFxEffective = \Faoxima\Orders\Pricing\ExchangeRates::fxRateGetEffective($rxFxConfig['pair'], $rxFxConfig);
            $rxFxRender = static function (string $prefix, string $code) use ($from_id, $backadmin, $textbotlang) {
                $panel = \Faoxima\Orders\Pricing\ExchangePricing::fxAdminLoadPanel($code);
                if ($panel === null) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
                    return;
                }
                $menu = \Faoxima\Orders\Pricing\ExchangePricing::fxAdminMenuRender($panel);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $prefix . $menu['text'], $menu['keyboard'], 'HTML');
            };
            if ($rxFxAction === 'back') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                \Faoxima\Services\Panels\PanelSetup::outtypepanel($rxFxPanel['type'], $textbotlang['Admin']['Back-menu']);
                return;
            }
            if ($rxFxAction === 'menu') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                $rxFxRender('', $rxFxCode);
                return;
            }
            if ($rxFxAction === 'on') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                $rxFxEnable = \Faoxima\Orders\Pricing\ExchangePricing::fxAdminEnableRender($rxFxPanel);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxFxEnable['text'], $rxFxEnable['keyboard'], 'HTML');
                return;
            }
            if ($rxFxAction === 'onok' || $rxFxAction === 'rebase') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                if (!$rxFxEffective['ok'] || $rxFxEffective['rate'] === null) {
                    $rxFxRender("❌ نرخ معتبر USDT/IRT در دسترس نیست؛ تغییری ذخیره نشد.\n\n", $rxFxCode);
                    return;
                }
                $rxFxConfig['base_rate'] = $rxFxEffective['rate'];
                if ($rxFxAction === 'onok') {
                    $rxFxConfig['enabled'] = '1';
                    $rxFxConfig['enabled_at'] = time();
                }
                $rxFxSaved = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelSaveConfig($rxFxPanel, $rxFxConfig);
                $rxFxPrefix = $rxFxSaved
                    ? ($rxFxAction === 'onok'
                        ? "✅ قیمت‌گذاری دلاری فعال شد. نرخ پایه: " . \Faoxima\Orders\Pricing\ExchangePricing::fxFormatToman($rxFxEffective['rate']) . "\n\n"
                        : "✅ نرخ فعلی (" . \Faoxima\Orders\Pricing\ExchangePricing::fxFormatToman($rxFxEffective['rate']) . ") به‌عنوان نرخ پایه ذخیره شد.\n\n")
                    : "❌ ذخیره تنظیمات انجام نشد.\n\n";
                $rxFxRender($rxFxPrefix, $rxFxCode);
                return;
            }
            if ($rxFxAction === 'off') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                $rxFxConfig['enabled'] = '0';
                $rxFxSaved = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelSaveConfig($rxFxPanel, $rxFxConfig);
                $rxFxRender($rxFxSaved ? "✅ قیمت‌گذاری دلاری غیرفعال شد؛ قیمت‌های پایه تومانی اعمال می‌شوند.\n\n" : "❌ ذخیره تنظیمات انجام نشد.\n\n", $rxFxCode);
                return;
            }
            $rxFxToggleMap = [
                'tp'  => 'apply_products',
                'tcv' => 'apply_custom_volume',
                'tct' => 'apply_custom_time',
                'tev' => 'apply_extra_volume',
                'tet' => 'apply_extra_time',
            ];
            if (isset($rxFxToggleMap[$rxFxAction])) {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                $rxFxRender("🔄 این منو بروزرسانی شد؛ لطفاً دوباره گزینه را انتخاب کنید.\n\n", $rxFxCode);
                return;
            }
            $rxFxToggleBase = substr($rxFxAction, 0, -1);
            $rxFxTargetValue = substr($rxFxAction, -1);
            if (isset($rxFxToggleMap[$rxFxToggleBase]) && ($rxFxTargetValue === '0' || $rxFxTargetValue === '1')) {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                $rxFxKey = $rxFxToggleMap[$rxFxToggleBase];
                $rxFxConfig[$rxFxKey] = $rxFxTargetValue;
                $rxFxSaved = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelSaveConfig($rxFxPanel, $rxFxConfig);
                if ($rxFxSaved) {
                    $rxFxReloaded = \Faoxima\Orders\Pricing\ExchangePricing::fxAdminLoadPanel($rxFxCode);
                    $rxFxSaved = $rxFxReloaded !== null && \Faoxima\Orders\Pricing\ExchangePricing::fxPanelConfig($rxFxReloaded)[$rxFxKey] === $rxFxTargetValue;
                }
                $rxFxRender($rxFxSaved ? "✅ ذخیره شد.\n\n" : "❌ ذخیره تنظیمات انجام نشد.\n\n", $rxFxCode);
                return;
            }
            if ($rxFxAction === 'unmanual') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                $rxFxConfig['manual_rate'] = null;
                $rxFxSaved = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelSaveConfig($rxFxPanel, $rxFxConfig);
                $rxFxRender($rxFxSaved ? "✅ نرخ اضطراری دستی حذف شد؛ قیمت‌ها دوباره از نرخ ذخیره‌شده API محاسبه می‌شوند.\n\n" : "❌ ذخیره تنظیمات انجام نشد.\n\n", $rxFxCode);
                return;
            }
            $rxFxPrompts = [
                'base'   => "✍️ نرخ پایه USDT/IRT (تومان) را ارسال کنید.\n⚠️ قیمت‌های ثبت‌شده محصولات این پنل معادل همین نرخ در نظر گرفته می‌شوند.",
                'markup' => "📈 درصد افزایش (Markup) را ارسال کنید.\nمثال: 5 یا 2.5 — برای حذف، 0 ارسال کنید. (بین -90 تا 1000)",
                'round'  => "🔢 گام گرد کردن قیمت نهایی (تومان) را ارسال کنید.\nمثال: 1000 — قیمت نهایی به مضرب بعدی این عدد گرد می‌شود.",
                'stale'  => "⏱ آستانه قدیمی‌شدن نرخ (دقیقه) را ارسال کنید. (بین 5 تا 10080)",
                'jump'   => "📊 حداکثر درصد جهش مجاز نرخ را ارسال کنید. (بین 1 تا 100)",
                'manual' => "🚨 نرخ اضطراری دستی USDT/IRT (تومان) را ارسال کنید.\nتا زمان حذف دستی، این نرخ به جای نرخ API استفاده می‌شود.",
                'test'   => "🧮 یک قیمت پایه نمونه (تومان) ارسال کنید تا قیمت نهایی محاسبه شود.",
            ];
            if (isset($rxFxPrompts[$rxFxAction])) {
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "fxp|" . $rxFxAction . "|" . $rxFxCode, "id", $from_id);
                \Faoxima\Telegram\Navigation\Steps::step('fxp_input', $from_id);
                $rxFxPromptKb = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'fxp_menu_' . $rxFxCode]]]], JSON_UNESCAPED_UNICODE);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxFxPrompts[$rxFxAction], $rxFxPromptKb, 'HTML');
                return;
            }
            break;
        case 3:
            $rxFxState = explode('|', (string)($user['Processing_value_tow'] ?? ''));
            $rxFxPanel = (count($rxFxState) === 3 && $rxFxState[0] === 'fxp') ? \Faoxima\Orders\Pricing\ExchangePricing::fxAdminLoadPanel($rxFxState[2]) : null;
            $rxFxCurrentName = is_callable([\Faoxima\Orders\Catalog\Products::class, 'nmResolvePanelNameForUser']) ? \Faoxima\Orders\Catalog\Products::nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            if ($rxFxPanel === null || (string)$rxFxPanel['name_panel'] !== (string)$rxFxCurrentName) {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "⚠️ این مرحله منقضی شده است؛ دوباره از مدیریت پنل وارد شوید.", $backadmin, 'HTML');
                return;
            }
            $rxFxAction = $rxFxState[1];
            $rxFxCode = (string)$rxFxPanel['code_panel'];
            $rxFxConfig = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelConfig($rxFxPanel);
            $rxFxPromptKb = json_encode(['inline_keyboard' => [[['text' => "🔙 بازگشت", 'callback_data' => 'fxp_menu_' . $rxFxCode]]]], JSON_UNESCAPED_UNICODE);
            $rxFxValue = \Faoxima\Orders\Pricing\ExchangePricing::fxParseAdminNumber((string)$text, $rxFxAction === 'markup');
            $rxFxInputStatus = \Faoxima\Admin\Telegram\Support\PricingInputHelpers::rxAdminPricingInputStatus($rxFxAction, $rxFxValue);
            if ($rxFxInputStatus === 'unknown_action') {
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                return;
            }
            if ($rxFxInputStatus === 'invalid_value') {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ مقدار نامعتبر است. یک عدد معتبر در بازه مجاز ارسال کنید.", $rxFxPromptKb, 'HTML');
                return;
            }
            if ($rxFxAction === 'test') {
                $rxFxEffective = \Faoxima\Orders\Pricing\ExchangeRates::fxRateGetEffective($rxFxConfig['pair'], $rxFxConfig);
                if (!$rxFxEffective['ok']) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نرخ معتبر USDT/IRT در دسترس نیست؛ محاسبه ممکن نیست.", $rxFxPromptKb, 'HTML');
                    return;
                }
                $rxFxTestConfig = $rxFxConfig;
                $rxFxTestConfig['enabled'] = '1';
                $rxFxTestConfig['apply_products'] = '1';
                if ($rxFxTestConfig['base_rate'] === null) {
                    $rxFxTestConfig['base_rate'] = $rxFxEffective['rate'];
                }
                $rxFxTestPanel = $rxFxPanel;
                $rxFxTestPanel['fx_pricing_config'] = json_encode(\Faoxima\Orders\Pricing\ExchangePricing::fxSanitizeConfig($rxFxTestConfig));
                $rxFxTestPrice = \Faoxima\Orders\Pricing\ExchangePricing::fxAdjustBaseToman($rxFxValue, $rxFxTestPanel, 'product');
                $rxFxMultiplier = ($rxFxEffective['rate'] / $rxFxTestConfig['base_rate']) * (1 + $rxFxTestConfig['markup_percent'] / 100);
                $rxFxTestText = "🧮 <b>نتیجه تست محاسبه</b>\n\n"
                    . "💰 قیمت پایه: " . \Faoxima\Orders\Pricing\ExchangePricing::fxFormatToman($rxFxValue) . "\n"
                    . "💱 نرخ فعلی: " . \Faoxima\Orders\Pricing\ExchangePricing::fxFormatToman($rxFxEffective['rate']) . ($rxFxEffective['manual'] ? ' (دستی)' : '') . "\n"
                    . "🏁 نرخ پایه: " . \Faoxima\Orders\Pricing\ExchangePricing::fxFormatToman($rxFxTestConfig['base_rate']) . "\n"
                    . "📈 درصد افزایش: " . $rxFxTestConfig['markup_percent'] . "%\n"
                    . "✖️ ضریب: " . number_format($rxFxMultiplier, 4) . "\n"
                    . "🔢 گام گرد کردن: " . number_format($rxFxTestConfig['round_step']) . "\n\n"
                    . "✅ قیمت نهایی: <b>" . \Faoxima\Orders\Pricing\ExchangePricing::fxFormatToman($rxFxTestPrice) . "</b>"
                    . ($rxFxConfig['enabled'] === '1' ? '' : "\n\nℹ️ قیمت‌گذاری دلاری این پنل فعلاً غیرفعال است؛ این فقط یک محاسبه نمونه است.");
                \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
                \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxFxTestText, $rxFxPromptKb, 'HTML');
                return;
            }
            $rxFxFieldMap = [
                'base'   => 'base_rate',
                'markup' => 'markup_percent',
                'round'  => 'round_step',
                'stale'  => 'stale_after_minutes',
                'jump'   => 'max_jump_percent',
                'manual' => 'manual_rate',
            ];
            $rxFxConfig[$rxFxFieldMap[$rxFxAction]] = $rxFxValue;
            $rxFxSaved = \Faoxima\Orders\Pricing\ExchangePricing::fxPanelSaveConfig($rxFxPanel, $rxFxConfig);
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", "", "id", $from_id);
            $rxFxPanel = \Faoxima\Orders\Pricing\ExchangePricing::fxAdminLoadPanel($rxFxCode);
            if ($rxFxPanel === null) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['nullpanel'], $backadmin, 'HTML');
                return;
            }
            $rxFxMenu = \Faoxima\Orders\Pricing\ExchangePricing::fxAdminMenuRender($rxFxPanel);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, ($rxFxSaved ? "✅ ذخیره شد.\n\n" : "❌ ذخیره تنظیمات انجام نشد.\n\n") . $rxFxMenu['text'], $rxFxMenu['keyboard'], 'HTML');
            break;
        }

        return true;
    }
}
