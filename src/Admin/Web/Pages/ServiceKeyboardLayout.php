<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $EXPLICIT_STYLES, $MENUS, $adminWebPage, $kmRaw, $kmRows, $pdo, $rxAutoStyleFor, $rxFamilyStyle, $useBuiltinDefaults, $userStylesRaw.
 * Inherited or exported by reference: $SCREENS, $buttons, $catalogSamples, $colorCatalogGroups, $keyboardMain, $label, $mkBtn, $mkCfgHead, $mkIndividual, $name, $page, $payValue, $role, $row, $serviceSampleActions, $textbotMap, $token.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ServiceKeyboardLayout
{
    public static function handle(
        $EXPLICIT_STYLES,
        $MENUS,
        &$SCREENS,
        $adminWebPage,
        &$buttons,
        &$catalogSamples,
        &$colorCatalogGroups,
        &$keyboardMain,
        $kmRaw,
        $kmRows,
        &$label,
        &$mkBtn,
        &$mkCfgHead,
        &$mkIndividual,
        &$name,
        &$page,
        &$payValue,
        $pdo,
        &$role,
        &$row,
        $rxAutoStyleFor,
        $rxFamilyStyle,
        &$serviceSampleActions,
        &$textbotMap,
        &$token,
        $useBuiltinDefaults,
        $userStylesRaw
    ) {
        if (($adminWebPage ?? null) !== 'service_keyboard.php') {
            return;
        }

        if ($kmRaw !== '') {
            $kmDecoded = json_decode($kmRaw, true);
            if (is_array($kmDecoded) && isset($kmDecoded['keyboard']) && is_array($kmDecoded['keyboard'])) {
                $kmRows = $kmDecoded['keyboard'];
            }
        }
        foreach ($kmRows as $kmRowEach) {
            if (!is_array($kmRowEach)) { continue; }
            foreach ($kmRowEach as $kmBtnEach) {
                if (!is_array($kmBtnEach)) { continue; }
                $token = (string) ($kmBtnEach['text'] ?? '');
                $style = (string) ($kmBtnEach['style'] ?? '');
                if ($token !== '' && in_array($style, $EXPLICIT_STYLES, true)) {
                    $keyboardMain[$token] = $style;
                }
            }
        }

        $rxMainExact = function (string $token) use ($keyboardMain, $EXPLICIT_STYLES): ?string {
            $style = $keyboardMain[$token] ?? null;
            return (is_string($style) && in_array($style, $EXPLICIT_STYLES, true)) ? $style : null;
        };

        $textbotMap = [];
        try {
            $textbotRows = \Faoxima\Core\Database\Db::select("textbot", "*", null, null, "fetchAll");
            if (is_array($textbotRows)) {
                foreach ($textbotRows as $row) {
                    if (isset($row['id_text']) && isset($row['text']) && $row['text'] !== '') {
                        $textbotMap[(string) $row['id_text']] = (string) $row['text'];
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[panel/service_keyboard] textbot load failed: ' . $e->getMessage());
        }

        $paySettings = [];
        try {
            $paySettingRows = \Faoxima\Core\Database\Db::select("PaySetting", "*", null, null, "fetchAll");
            if (is_array($paySettingRows)) {
                foreach ($paySettingRows as $paySettingRow) {
                    if (isset($paySettingRow['NamePay'])) {
                        $paySettings[(string) $paySettingRow['NamePay']] = (string) ($paySettingRow['ValuePay'] ?? '');
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[panel/service_keyboard] PaySetting load failed: ' . $e->getMessage());
        }
        $payValue = function (string $name) use ($paySettings): string {
            return $paySettings[$name] ?? '';
        };

        $accountRegistry = method_exists(\Faoxima\Telegram\Presentation\AccountLayout::class, 'rx_account_button_registry') ? \Faoxima\Telegram\Presentation\AccountLayout::rx_account_button_registry() : [];
        $accountLayoutRaw = '';
        try {
            $accountLayoutRow = \Faoxima\Core\Database\Db::select("setting", "account_keyboard_layout", null, null, "select", ['cache' => false]);
            $accountLayoutRaw = is_array($accountLayoutRow) ? trim((string) ($accountLayoutRow['account_keyboard_layout'] ?? '')) : '';
        } catch (Throwable $e) {
            $accountLayoutRaw = '';
        }
        if ($accountLayoutRaw === '') {
            $accountLayout = method_exists(\Faoxima\Telegram\Presentation\AccountLayout::class, 'rx_account_default_layout') ? \Faoxima\Telegram\Presentation\AccountLayout::rx_account_default_layout() : [];
        } else {
            $accountLayout = method_exists(\Faoxima\Telegram\Presentation\AccountLayout::class, 'rx_account_layout_normalize') ? \Faoxima\Telegram\Presentation\AccountLayout::rx_account_layout_normalize($accountLayoutRaw) : [];
        }
        $accountLabelFor = function (string $id) use ($accountRegistry, $textbotMap): string {
            $def = $accountRegistry[$id] ?? null;
            if (!is_array($def)) { return $id; }
            $textbotKey = $def['textbot'] ?? null;
            if (is_string($textbotKey) && isset($textbotMap[$textbotKey]) && trim($textbotMap[$textbotKey]) !== '') {
                return $textbotMap[$textbotKey];
            }
            return (string) ($def['label'] ?? $id);
        };

        $mkBtn = function (string $menu, string $key, array $opt = []) use ($MENUS, $rxAutoStyleFor, $rxFamilyStyle): array {
            $label = $opt['label'] ?? (string) ($MENUS[$menu]['buttons'][$key] ?? $key);
            $auto = $rxAutoStyleFor($label);
            $fallbackKey = isset($opt['fallback_key']) && is_string($opt['fallback_key']) ? $opt['fallback_key'] : null;
            $style = $rxFamilyStyle($menu, $key, $label, $fallbackKey);
            return array_merge([
                'kind' => 'family',
                'menu' => $menu,
                'key' => $key,
                'label' => $label,
                'auto' => $auto,
                'style' => $style,
                'fallback_key' => $fallbackKey,
                'scopes' => ['button'],
            ], $opt);
        };

        $mkMain = function (string $token, string $familyKey, string $label) use ($rxAutoStyleFor, $rxFamilyStyle, $rxMainExact): array {
            $auto = $rxAutoStyleFor($label);
            $exact = $rxMainExact($token);
            return [
                'kind' => 'main',
                'menu' => 'home_menu',
                'key' => $familyKey,
                'token' => $token,
                'label' => $label,
                'auto' => $auto,
                'style' => $exact !== null ? $exact : $rxFamilyStyle('home_menu', $familyKey, $label),
                'scopes' => ['exact'],
                'family_label' => 'رنگ پیش‌فرض نقش این دکمه',
            ];
        };

        $mkIndividual = function (array $button, string $label, string $fallbackMenu, string $fallbackKey) use ($userStylesRaw, $EXPLICIT_STYLES, $rxAutoStyleFor, $rxFamilyStyle, $useBuiltinDefaults): array {
            $key = rx_button_color_key($button);
            $saved = $userStylesRaw['button_items'][$key] ?? null;
            $fallbackSecondary = $fallbackMenu === 'user_dynamic_lists' && in_array($fallbackKey, ['rcc_view', 'rcc_resubmit'], true) ? 'crypto_manual_actions' : null;
            return [
                'kind' => 'individual',
                'menu' => 'button_items',
                'key' => $key,
                'label' => $label,
                'auto' => $rxAutoStyleFor($label),
                'style' => in_array($saved, $EXPLICIT_STYLES, true) ? $saved : ($useBuiltinDefaults ? $rxFamilyStyle($fallbackMenu, $fallbackKey, $label, $fallbackSecondary) : 'none'),
                'fallback_menu' => $fallbackMenu,
                'fallback_key' => $fallbackKey,
                'fallback_secondary' => $fallbackSecondary,
                'scopes' => ['button'],
            ];
        };
        $mkCfgHead = function (string $key, string $label) use ($mkIndividual): array {
            return $mkIndividual(['_rxcolor' => 'config_header:' . $key], $label, 'config_list', $key) + ['header' => true];
        };

        $colorCatalogGroups = [
            'products' => 'محصولات', 'categories' => 'دسته‌بندی‌ها', 'durations' => 'مدت سرویس',
            'volumes' => 'حجم سرویس', 'panels' => 'پنل و لوکیشن', 'channels' => 'کانال‌های الزامی',
            'help' => 'آموزش‌ها', 'help_categories' => 'دسته‌بندی آموزش',
            'departments' => 'دپارتمان پشتیبانی', 'tickets' => 'تیکت‌ها', 'crypto' => 'تراکنش‌های کریپتو',
            'affiliate' => 'دعوت دوستان و هدیه',
        ];
        $mkCatalogSample = function (string $group, string $role, string $label, string $fallbackKey, string $fallbackMenu = 'user_dynamic_lists') use ($mkIndividual): array {
            return $mkIndividual(['_rxcolor' => 'catalog_template:' . $group . ':' . $role], $label, $fallbackMenu, $fallbackKey) + ['sample' => true];
        };
        $catalogSamples = [
            'affiliate' => [
                $mkIndividual(['_rxcolor' => 'affiliate:gift'], '🎁 هدیه عضویت', 'home_menu', 'affiliatesbtn') + ['sample' => true],
                $mkIndividual(['_rxcolor' => 'affiliate:share'], '🔗 اشتراک لینک دعوت', 'home_menu', 'affiliatesbtn') + ['sample' => true],
            ],
            'categories' => [$mkCatalogSample('categories', 'category', '📁 نمونه دسته‌بندی — پیش‌فرض دسته‌بندی‌های جدید', 'category_buy')],
            'durations' => [
                $mkCatalogSample('durations', 'time', '⏳ نمونه مدت سرویس', 'time_buy'),
                $mkCatalogSample('durations', 'category_time', '⏳ نمونه مدت در دسته‌بندی', 'time_buy'),
                $mkCatalogSample('durations', 'custom_time', '✍️ نمونه مدت دلخواه', 'time_buy'),
            ],
            'volumes' => [$mkCatalogSample('volumes', 'volume', '📦 نمونه حجم سرویس', 'volume_buy')],
            'panels' => [$mkCatalogSample('panels', 'panel', '🌍 نمونه پنل و لوکیشن', 'paneluser_list')],
            'channels' => [$mkCatalogSample('channels', 'channel', '📢 نمونه کانال الزامی', 'channel_join')],
            'help' => [$mkCatalogSample('help', 'guide', '📚 نمونه آموزش', 'helpsection')],
            'help_categories' => [$mkCatalogSample('help_categories', 'category', '📂 نمونه دسته‌بندی آموزش', 'helpsection')],
            'departments' => [$mkCatalogSample('departments', 'department', '💬 نمونه دپارتمان پشتیبانی', 'ticket_list')],
            'tickets' => [
                $mkCatalogSample('tickets', 'open', '🎫 باز کردن تیکت', 'ticket_list'),
                $mkCatalogSample('tickets', 'message', '✍️ ارسال پیام تیکت', 'ticket_list'),
                $mkCatalogSample('tickets', 'close', '🔒 بستن تیکت', 'ticket_list'),
                $mkCatalogSample('tickets', 'media', '📎 مشاهده رسانه‌های تیکت', 'ticket_list'),
            ],
            'crypto' => [
                $mkCatalogSample('crypto', 'copy_wallet', '🔗 کپی آدرس کیف پول', 'copy_wallet', 'invoice_copy_buttons'),
                $mkCatalogSample('crypto', 'copy_amount', '🪙 کپی مبلغ', 'copy_amount', 'invoice_copy_buttons'),
                $mkCatalogSample('crypto', 'copy_memo', '🏷 کپی ممو', 'copy_memo', 'invoice_copy_buttons'),
                $mkCatalogSample('crypto', 'paid_submit', '✅ ارسال هش پرداخت', 'paid_submit', 'invoice_copy_buttons'),
                $mkCatalogSample('crypto', 'invoice_back', '🔙 لغو فاکتور', 'invoice_back', 'invoice_copy_buttons'),
                $mkCatalogSample('crypto', 'rcc_view', '👁 مشاهده وضعیت', 'rcc_view'),
                $mkCatalogSample('crypto', 'rcc_resubmit', '📨 ارسال مجدد به ادمین', 'rcc_resubmit'),
            ],
        ];
        $serviceSampleActions = [
            'service_manage' => ['🔧 مدیریت سرویس / بازگشت به اطلاعات', 'user_dynamic_lists', 'service_actions'],
            'copy_subscription' => ['📋 کپی لینک اشتراک', 'user_dynamic_lists', 'copy_text_btn'],
            'service_open' => ['📂 مشاهده سرویس', 'user_dynamic_lists', 'user_services_list'],
            'infocard_qr' => ['📷 دریافت QR Code', 'user_dynamic_lists', 'service_actions'],
            'confirm_status' => ['✅ تایید تغییر وضعیت', 'user_dynamic_lists', 'user_confirms'],
            'confirm_link' => ['✅ تایید تغییر لینک', 'user_dynamic_lists', 'user_confirms'],
            'stock_extend' => ['🔄 تمدید سرویس دستی', 'user_dynamic_lists', 'nmstock_actions'],
            'stock_refund' => ['💎 بازگشت وجه سرویس دستی', 'user_dynamic_lists', 'nmstock_actions'],
            'stock_config' => ['📥 دریافت کانفیگ دستی', 'user_dynamic_lists', 'nmstock_actions'],
            'stock_subscription' => ['🔗 لینک اشتراک دستی', 'user_dynamic_lists', 'nmstock_actions'],
        ];
        if (filter_input(INPUT_GET, 'action') === 'button_catalog' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            header('Content-Type: application/json; charset=utf-8');
            if (($_GET['group'] ?? 'products') !== 'products') {
                http_response_code(400);
                echo json_encode(['ok' => false]);
                exit;
            }
            $page = max(0, min(100000, (int) ($_GET['page'] ?? 0)));
            $pageSize = 1;
            $offset = $page * $pageSize;
            try {
                $statement = $pdo->query("SELECT id, name_product, Service_time, price_product FROM product ORDER BY (position = 0) ASC, position ASC, id ASC LIMIT 2 OFFSET $offset");
                $records = $statement->fetchAll(PDO::FETCH_ASSOC);
                $more = count($records) > $pageSize;
                $buttons = [];
                foreach (array_slice($records, 0, $pageSize) as $record) {
                    $id = (string) $record['id'];
                    $name = (string) $record['name_product'];
                    foreach (['name' => '📦 نام: ' . $name, 'duration' => '⏳ ' . $record['Service_time'] . ' روز · ' . $name, 'price' => '💵 ' . number_format((float) $record['price_product']) . ' تومان · ' . $name] as $role => $label) {
                        $buttons[] = $mkIndividual(['_rxcolor' => 'product:' . $id . ':' . $role], $label, 'user_dynamic_lists', 'product_buy');
                    }
                    $buttons[] = $mkIndividual(['_rxcolor' => 'stock_product:' . $id], '🔄 تمدید دستی · ' . $name, 'user_dynamic_lists', 'nmstock_actions');
                }
                echo json_encode(['ok' => true, 'buttons' => $buttons, 'more' => $more, 'page' => $page], JSON_UNESCAPED_UNICODE);
            } catch (Throwable $e) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'catalog_unavailable']);
            }
            exit;
        }
        $SCREENS = [];

        return true;
    }
}
