<?php

namespace Faoxima\Services\Panels;

use ManagePanel;
use PDO;
use Throwable;

final class PanelSetup
{
    public static function rxEnsureManagePanel()
    {
        global $ManagePanel;

        if (isset($ManagePanel) && is_object($ManagePanel)) {
            return $ManagePanel;
        }

        if (!class_exists('ManagePanel', false)) {
            $rxPanelsFile = REFACTORED_LEGACY_ROOT . '/panels.php';
            if (!is_file($rxPanelsFile)) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('MANAGE_PANEL_UNAVAILABLE', 'panels.php not found', ['path' => $rxPanelsFile]);
                }
                return null;
            }
            $rxPrevErrorLog = ini_get('error_log');
            try {
                require_once $rxPanelsFile;
            } catch (Throwable $e) {
                if (function_exists('rx_log_event')) {
                    rx_log_event('MANAGE_PANEL_UNAVAILABLE', 'Loading panels.php threw', [
                        'class' => get_class($e),
                        'err' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }
            if ($rxPrevErrorLog !== false) {
                ini_set('error_log', $rxPrevErrorLog);
            }
        }

        if (!class_exists('ManagePanel', false)) {
            return null;
        }

        try {
            $ManagePanel = new ManagePanel();
        } catch (Throwable $e) {
            if (function_exists('rx_log_event')) {
                rx_log_event('MANAGE_PANEL_UNAVAILABLE', 'ManagePanel construction threw', [
                    'class' => get_class($e),
                    'err' => $e->getMessage(),
                ]);
            }
            return null;
        }

        return $ManagePanel;
    }

    public static function rx_panel_token_lock_acquire($scope, $identity, $timeout = 10)
    {
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
        if (!($pdo instanceof PDO)) {
            return null;
        }
        $lockKey = 'fx_ptok_' . md5((string) $scope . '|' . (string) $identity);
        try {
            $stmt = $pdo->prepare("SELECT GET_LOCK(?, ?)");
            $stmt->execute([$lockKey, max(1, (int) $timeout)]);
            if ((int) $stmt->fetchColumn() === 1) {
                return $lockKey;
            }
        } catch (Throwable $e) {
            error_log('rx_panel_token_lock_acquire: ' . $e->getMessage());
        }
        return null;
    }

    public static function rx_panel_token_lock_release($lockKey)
    {
        if ($lockKey === null) {
            return;
        }
        $pdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
        if (!($pdo instanceof PDO)) {
            return;
        }
        try {
            $stmt = $pdo->prepare("SELECT RELEASE_LOCK(?)");
            $stmt->execute([$lockKey]);
        } catch (Throwable $e) {
            error_log('rx_panel_token_lock_release: ' . $e->getMessage());
        }
    }

    public static function rx_panel_token_jwt_exp($token)
    {
        $parts = explode('.', (string) $token);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/') . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4), true);
        $decoded = $payload === false ? null : json_decode($payload, true);
        if (!is_array($decoded) || !isset($decoded['exp']) || !is_numeric($decoded['exp'])) {
            return null;
        }
        return (int) $decoded['exp'];
    }

    public static function rxAddPanelTypeKeyboard($type = '')
    {
        global $keyboardpanelcategory, $keyboardtypepanelpublic, $keyboardtypepanelsubscription;
        $type = (string) $type;
        if ($type === 'guard' || $type === 'jovadpanel') {
            return $keyboardtypepanelsubscription;
        }
        if (in_array($type, ['marzban', 'rebecca', 'pasarguard', 'remnawave', 'x-ui_single'], true)) {
            return $keyboardtypepanelpublic;
        }
        return $keyboardpanelcategory;
    }

    public static function rxAddPanelCategory($type = '')
    {
        if (in_array((string) $type, ['guard', 'jovadpanel', 'subscription'], true)) return 'subscription';
        if (in_array((string) $type, ['marzban', 'rebecca', 'pasarguard', 'pasargard', 'remnawave', 'x-ui_single', 'public'], true)) return 'public';
        return 'root';
    }

    public static function rxPanelUrlPrompt($type = '', $editing = false)
    {
        global $textbotlang;
        $manage = (isset($textbotlang['Admin']['managepanel']) && is_array($textbotlang['Admin']['managepanel']))
            ? $textbotlang['Admin']['managepanel']
            : [];
        $baseKey = $editing ? 'geturlnew' : 'addpanelurl';
        $key = ((string) $type === 'x-ui_single') ? $baseKey . '_xui' : $baseKey;
        if (!empty($manage[$key])) {
            return (string) $manage[$key];
        }
        return (string) ($manage[$baseKey] ?? '');
    }

    public static function rxPanelUrlPathViolation($url, $type = '')
    {
        $type = strtolower(trim((string) $type));
        $path = parse_url(trim((string) $url), PHP_URL_PATH);
        if (!is_string($path) || trim($path, '/') === '') {
            return '';
        }
        if ($type !== 'x-ui_single') {
            foreach (explode('/', trim($path, '/')) as $segment) {
                if (strcasecmp($segment, 'dashboard') === 0) {
                    return 'dashboard';
                }
            }
        }
        if ($type === 'remnawave') {
            return 'path';
        }
        return '';
    }

    public static function rxPanelUrlPathWarning($url, $type = '')
    {
        global $textbotlang;
        $violation = self::rxPanelUrlPathViolation($url, $type);
        if ($violation === 'dashboard') {
            $warning = (string) ($textbotlang['Admin']['managepanel']['Invalid-dashboard'] ?? '');
            if ($warning !== '') {
                return $warning;
            }
            return '⚠️ آدرس پنل را همراه با dashboard وارد کرده‌اید. بخش /dashboard و ادامه آن را از آدرس حذف کنید و آدرس صحیح را دوباره ارسال کنید.
نمونه صحیح: https://example.com:8000';
        }
        if ($violation === 'path') {
            $warning = (string) ($textbotlang['Admin']['managepanel']['Invalid-remnawave-url'] ?? '');
            if ($warning !== '') {
                return $warning;
            }
            return '⚠️ آدرس پنل Remnawave باید فقط شامل دامنه و پورت باشد و نباید مسیر داشته باشد. آدرس صحیح را دوباره ارسال کنید.
نمونه صحیح: https://example.com:8000';
        }
        return '';
    }

    public static function rxAddPanelIsStep($step)
    {
        return in_array((string) $step, [
            'add_name_panel', 'add_link_panel', 'add_username_panel', 'add_password_panel',
            'add_remna_token_setup', 'getlimitedpanel', 'add_guard_version', 'add_guard_api_key',
            'add_rebecca_api_key', 'add_jovad_api_key', 'add_pasarguard_auth_method',
            'add_pasarguard_api_key', 'add_pasarguard_username', 'add_pasarguard_password',
            'add_xui_api_mode', 'add_xui_api_token',
        ], true);
    }

    public static function rxAddPanelResetDraft($chatId)
    {
        global $user;
        \Faoxima\Telegram\Navigation\Steps::step('home', $chatId);
        \Faoxima\Core\Database\Db::update('user', 'Processing_value', '0', 'id', $chatId);
        try {
            \Faoxima\Core\Database\Db::update('user', 'step_stack', '[]', 'id', $chatId);
        } catch (Throwable $e) {
        }
        if (is_array($user)) {
            $user['step'] = 'home';
            $user['Processing_value'] = '0';
            $user['step_stack'] = '[]';
        }
        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) \Faoxima\Core\Database\SelectCache::clearSelectCache('user');
    }

    public static function rxAddPanelBackKeyboard($type = '')
    {
        global $textbotlang;
        return json_encode(['inline_keyboard' => [
            [['text' => '🔙 بازگشت به انتخاب نوع پنل', 'callback_data' => 'panelcat#' . self::rxAddPanelCategory($type)]],
            [['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'admin']],
        ]], JSON_UNESCAPED_UNICODE);
    }

    public static function rxAddPanelCategoryKeyboard($category = 'root')
    {
        global $keyboardpanelcategory, $keyboardtypepanelpublic, $keyboardtypepanelsubscription;
        if ((string) $category === 'public') return $keyboardtypepanelpublic;
        if ((string) $category === 'subscription') return $keyboardtypepanelsubscription;
        return $keyboardpanelcategory;
    }

    public static function rxAddPanelCategoryMessage($category = 'root')
    {
        if ((string) $category === 'public') {
            return "🌐 پنل‌های عمومی (رایگان)\n\nاین پنل‌ها به‌صورت رایگان از طریق مخازن عمومی مانند GitHub در دسترس هستند. برای استفاده، باید پنل موردنظر را روی سرور خود نصب و راه‌اندازی کنید.\n\nلطفاً نوع پنل خود را انتخاب کنید.";
        }
        if ((string) $category === 'subscription') {
            return "💳 پنل‌های اشتراکی (پولی)\n\nبرای استفاده از این پنل‌ها، لازم است ابتدا حساب و اشتراک یا اعتبار موردنیاز را از ارائه‌دهندهٔ مربوط تهیه کنید.\n\nپس از فعال‌سازی حساب، می‌توانید با اطلاعات اتصال و کلید API، پنل خود را به ربات متصل کنید.\n\nلطفاً نوع پنل خود را انتخاب کنید.";
        }
        return 'لطفاً دسته‌بندی پنل موردنظر را انتخاب کنید.';
    }

    public static function rxSwitchTypeKeyboard($category = 'root')
    {
        $source = (string) self::rxAddPanelCategoryKeyboard($category);
        $decoded = json_decode($source, true);
        if (!is_array($decoded) || !isset($decoded['inline_keyboard']) || !is_array($decoded['inline_keyboard'])) {
            return $source;
        }
        foreach ($decoded['inline_keyboard'] as $rowIndex => $row) {
            if (!is_array($row)) continue;
            foreach ($row as $buttonIndex => $button) {
                if (!is_array($button)) continue;
                $callbackData = (string) ($button['callback_data'] ?? '');
                if (strpos($callbackData, 'panelcat#') === 0) {
                    $decoded['inline_keyboard'][$rowIndex][$buttonIndex]['callback_data'] = 'switchpanelcat#' . substr($callbackData, strlen('panelcat#'));
                } elseif (strpos($callbackData, 'typepanel#') === 0) {
                    $decoded['inline_keyboard'][$rowIndex][$buttonIndex]['callback_data'] = 'switchtype#' . substr($callbackData, strlen('typepanel#'));
                } elseif ($callbackData === 'admin') {
                    $decoded['inline_keyboard'][$rowIndex][$buttonIndex]['callback_data'] = 'switchpanelcat#menu';
                    $decoded['inline_keyboard'][$rowIndex][$buttonIndex]['text'] = '🔙 بازگشت به منوی پنل';
                }
            }
        }
        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    public static function rxAddPanelShowCategory($chatId, $type = '', $notice = '')
    {
        $category = self::rxAddPanelCategory($type);
        self::rxAddPanelResetDraft($chatId);
        if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($chatId, 'panels');
        $keyboard = self::rxAddPanelCategoryKeyboard($category);
        $message = self::rxAddPanelCategoryMessage($category);
        if ($notice !== '') $message = htmlspecialchars((string) $notice, ENT_QUOTES, 'UTF-8') . "\n\n" . $message;
        return \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($chatId, $message, $keyboard, 'HTML');
    }

    public static function outtypepanel($typepanel, $message)
    {
        global $from_id, $optionMarzban, $optionGuard, $optionX_ui_single, $optionwg, $optioneylanpanel, $option_remnawave, $optionManualsale, $optionRebecca, $optionPasarGuard, $optionJovad;
        if ($typepanel == "marzban") {
            sendmessage($from_id, $message, $optionMarzban, 'HTML');
        } elseif ($typepanel == "pasarguard") {
            sendmessage($from_id, $message, $optionPasarGuard, 'HTML');
        } elseif ($typepanel == "guard") {
            sendmessage($from_id, $message, $optionGuard, 'HTML');
        } elseif ($typepanel == "x-ui_single") {
            sendmessage($from_id, $message, $optionX_ui_single, 'HTML');
        } elseif ($typepanel == "WGDashboard") {
            sendmessage($from_id, $message, $optionwg, 'HTML');
        } elseif ($typepanel == "eylanpanel") {
            sendmessage($from_id, $message, $optioneylanpanel, 'HTML');
        } elseif ($typepanel == "remnawave") {
            sendmessage($from_id, $message, $option_remnawave, 'HTML');
        } elseif ($typepanel == "rebecca") {
            sendmessage($from_id, $message, $optionRebecca, 'HTML');
        } elseif ($typepanel == "jovadpanel") {
            sendmessage($from_id, $message, $optionJovad, 'HTML');
        } elseif ($typepanel == "Manualsale") {
            sendmessage($from_id, $message, $optionManualsale, 'HTML');
        }
        if (method_exists(\Faoxima\Telegram\Navigation\Steps::class, 'step')) {
            \Faoxima\Telegram\Navigation\Steps::step('PanelMenu', $from_id);
        }
        if (method_exists(\Faoxima\Telegram\Navigation\NavParents::class, 'rxNavSetState')) {
            \Faoxima\Telegram\Navigation\NavParents::rxNavSetState($from_id, 'PanelMenu');
        }
    }
}
