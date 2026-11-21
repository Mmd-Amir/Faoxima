<?php

namespace Faoxima\Telegram\Presentation;

use PDO;
use Throwable;

final class Sections
{
    public static function rx_ui_section_catalog(): array
    {
        return [
            'main_menu' => 'منوی اصلی کاربر',
            'purchase' => 'خرید اشتراک',
            'extend' => 'تمدید سرویس',
            'services' => 'سرویس‌های من',
            'wallet' => 'کیف پول و شارژ',
            'test' => 'اکانت تست',
            'tariff' => 'تعرفه‌ها',
            'referral' => 'زیرمجموعه‌گیری',
            'wheel' => 'گردونه شانس',
            'help' => 'آموزش',
            'support' => 'پشتیبانی و تیکت',
            'agent' => 'امکانات نمایندگی',
        ];
    }

    public static function rx_ui_parse_selection($raw): array
    {
        $catalog = self::rx_ui_section_catalog();
        $all = array_keys($catalog);
        if ($raw === null || trim((string) $raw) === '') {
            return ['state' => 'missing', 'ids' => $all];
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || $decoded !== array_values($decoded)) {
            return ['state' => 'malformed', 'ids' => $all];
        }
        $ids = [];
        $unknown = false;
        foreach ($decoded as $value) {
            if (is_string($value) && isset($catalog[$value])) {
                $ids[$value] = true;
            } else {
                $unknown = true;
            }
        }
        return ['state' => $unknown ? 'unknown' : 'ok', 'ids' => array_keys($ids)];
    }

    public static function rx_ui_selection(): array
    {
        return self::rx_ui_parse_selection($GLOBALS['setting']['user_rich_sections'] ?? null);
    }

    public static function rx_richSectionActive($section): bool
    {
        if (!function_exists('rx_richUserButtonsOn') || !rx_richUserButtonsOn()) {
            return false;
        }
        return in_array((string) $section, self::rx_ui_selection()['ids'], true);
    }

    public static function rx_ui_route_tables(): array
    {
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        return \Faoxima\Telegram\Routing\SectionRoutes::tables();
    }

    public static function rx_ui_route_callback($callback): ?string
    {
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        return \Faoxima\Telegram\Routing\SectionRoutes::callback($callback, [\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_route_tables']);
    }

    public static function rx_ui_route($datain, $text): ?string
    {
        require_once dirname(__DIR__, 3) . '/src/bootstrap.php';
        $backLabel = (string) ($GLOBALS['textbotlang']['users']['backbtn'] ?? '');
        $datatextbot = $GLOBALS['datatextbot'] ?? [];
        return \Faoxima\Telegram\Routing\SectionRoutes::resolve($datain, $text, $backLabel, $datatextbot, [\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_route_callback'], [\Faoxima\Telegram\Presentation\Sections::class, 'rx_ui_route_tables']);
    }

    public static function rx_ui_resolve_request(): void
    {
        if (!function_exists('rx_richUserButtonsOn') || !rx_richUserButtonsOn()) {
            return;
        }
        $catalog = self::rx_ui_section_catalog();
        $user = $GLOBALS['user'] ?? [];
        $persisted = is_array($user) ? (string) ($user['ui_section'] ?? '') : '';
        $route = self::rx_ui_route($GLOBALS['datain'] ?? '', $GLOBALS['text'] ?? '');
        if ($route !== null) {
            $GLOBALS['rx_ui_section'] = $route;
            if ($route !== $persisted && isset($GLOBALS['from_id'])) {
                try {
                    self::rx_ui_ensure_user_column();
                    \Faoxima\Core\Database\Db::update('user', 'ui_section', $route, 'id', $GLOBALS['from_id']);
                    if (is_array($GLOBALS['user'] ?? null)) {
                        $GLOBALS['user']['ui_section'] = $route;
                    }
                } catch (Throwable $e) {
                    error_log('[ui_sections] persist failed: ' . $e->getMessage());
                }
            }
            return;
        }
        $GLOBALS['rx_ui_section'] = isset($catalog[$persisted]) ? $persisted : 'main_menu';
    }

    public static function rx_ui_ensure_user_column(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        global $pdo;
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $stmt->execute(['user', 'ui_section']);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE `user` ADD `ui_section` VARCHAR(24) NULL');
            }
        } catch (Throwable $e) {
            error_log('[ui_sections] user column: ' . $e->getMessage());
        }
    }

    public static function rx_ui_marker_section($markup): ?string
    {
        if (is_string($markup) && $markup !== '' && strpos($markup, '"_rxsec"') !== false) {
            $markup = json_decode($markup, true);
        }
        if (is_array($markup) && isset($markup['_rxsec']) && is_string($markup['_rxsec']) && isset(self::rx_ui_section_catalog()[$markup['_rxsec']])) {
            return $markup['_rxsec'];
        }
        return null;
    }

    public static function rx_ui_mark_keyboard($keyboard, string $section)
    {
        if (!is_string($keyboard) || $keyboard === '' || !isset(self::rx_ui_section_catalog()[$section])) {
            return $keyboard;
        }
        $decoded = json_decode($keyboard, true);
        if (!is_array($decoded)) {
            return $keyboard;
        }
        $decoded['_rxsec'] = $section;
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function rx_ui_classify_markup($markup): ?string
    {
        if (is_string($markup) && $markup !== '') {
            $markup = json_decode($markup, true);
        }
        if (!is_array($markup) || empty($markup['inline_keyboard']) || !is_array($markup['inline_keyboard'])) {
            return null;
        }
        foreach ($markup['inline_keyboard'] as $row) {
            foreach ((array) $row as $btn) {
                if (is_array($btn) && isset($btn['callback_data'])) {
                    $section = self::rx_ui_route_callback($btn['callback_data']);
                    if ($section !== null) {
                        return $section;
                    }
                }
            }
        }
        return null;
    }

    public static function rx_ui_section_for_send($chatId, $markup = null, $explicit = null): string
    {
        $catalog = self::rx_ui_section_catalog();
        if (is_string($explicit) && isset($catalog[$explicit])) {
            return $explicit;
        }
        $marker = self::rx_ui_marker_section($markup);
        if ($marker !== null) {
            return $marker;
        }
        $chatId = trim((string) $chatId);
        $actor = isset($GLOBALS['from_id']) ? trim((string) $GLOBALS['from_id']) : '';
        if ($actor !== '' && $chatId === $actor && !empty($GLOBALS['update'])) {
            $ctx = $GLOBALS['rx_ui_section'] ?? null;
            return is_string($ctx) && isset($catalog[$ctx]) ? $ctx : 'main_menu';
        }
        $classified = self::rx_ui_classify_markup($markup);
        if ($classified !== null) {
            return $classified;
        }
        if ($chatId !== '' && ctype_digit($chatId) && \Faoxima\Core\Database\Db::runtimeReady()) {
            try {
                $stored = \Faoxima\Core\Database\Db::select('user', 'ui_section', 'id', $chatId, 'select');
                if (is_string($stored) && isset($catalog[$stored])) {
                    return $stored;
                }
            } catch (Throwable $e) {
            }
        }
        return 'main_menu';
    }

    public static function rx_richActiveForSend($chatId, $markup = null, $explicit = null): bool
    {
        if (!function_exists('rx_richUserButtonsOn') || !rx_richUserButtonsOn() || !method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_chatIsUserUi') || !\Faoxima\Telegram\Rich\Dispatcher::rx_richUi_chatIsUserUi($chatId)) {
            return false;
        }
        return self::rx_richSectionActive(self::rx_ui_section_for_send($chatId, $markup, $explicit));
    }

    public static function rx_ui_track_reply_keyboard($datas, $result): void
    {
        try {
            if (!is_array($datas) || !is_array($result) || empty($result['ok']) || !function_exists('rx_richUserButtonsOn') || !rx_richUserButtonsOn()) {
                return;
            }
            $markup = $datas['reply_markup'] ?? null;
            if (is_string($markup) && $markup !== '') {
                $markup = json_decode($markup, true);
            }
            if (!is_array($markup) || empty($markup['keyboard']) || !isset($datas['chat_id']) || !\Faoxima\Telegram\Rich\Dispatcher::rx_richUi_chatIsUserUi($datas['chat_id'])) {
                return;
            }
            $chat = (string) $datas['chat_id'];
            $flag = \Faoxima\Core\Database\Db::select('user', 'reply_kb_cleared', 'id', $chat, 'select', ['cache' => false]);
            if (is_scalar($flag) && (string) $flag === '1') {
                \Faoxima\Core\Database\Db::update('user', 'reply_kb_cleared', '0', 'id', $chat);
            }
        } catch (Throwable $e) {
        }
    }

    public static function rx_ui_after_send($method, $datas, $result): void
    {
        self::rx_ui_track_reply_keyboard($datas, $result);
        if (method_exists(\Faoxima\Telegram\Presentation\Decor::class, 'rx_decor_after_send')) {
            \Faoxima\Telegram\Presentation\Decor::rx_decor_after_send($method, $datas, $result);
        }
    }

    public static function rx_ui_sections_mutate(string $action, string $id = '', string $embedded = ''): array
    {
        global $pdo;
        $catalog = self::rx_ui_section_catalog();
        if (!($pdo instanceof PDO)) {
            return ['ok' => false, 'changed' => false, 'stale' => false];
        }
        if ($action === 'toggle' && !isset($catalog[$id])) {
            return ['ok' => false, 'changed' => false, 'stale' => false];
        }
        try {
            $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $check->execute(['setting', 'user_rich_sections']);
            if ((int) $check->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE `setting` ADD `user_rich_sections` TEXT NULL');
            }
            $pdo->beginTransaction();
            $raw = $pdo->query('SELECT user_rich_sections FROM setting LIMIT 1 FOR UPDATE')->fetchColumn();
            $current = self::rx_ui_parse_selection($raw === false ? null : $raw)['ids'];
            $next = $current;
            $stale = false;
            if ($action === 'all') {
                $next = array_keys($catalog);
            } elseif ($action === 'none') {
                $next = [];
            } else {
                $isOn = in_array($id, $current, true);
                if ($embedded !== ($isOn ? '1' : '0')) {
                    $stale = true;
                } else {
                    $next = $isOn ? array_values(array_diff($current, [$id])) : array_merge($current, [$id]);
                }
            }
            $ordered = array_values(array_filter(array_keys($catalog), static function ($key) use ($next) {
                return in_array($key, $next, true);
            }));
            $changed = !$stale && ($raw === null || $raw === false || trim((string) $raw) === '' || $ordered !== $current || json_encode($ordered) !== (string) $raw);
            if ($changed) {
                $pdo->prepare('UPDATE setting SET user_rich_sections = ?')->execute([json_encode($ordered)]);
            }
            $pdo->commit();
            return ['ok' => true, 'changed' => $changed, 'stale' => $stale];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ui_sections] mutate failed: ' . $e->getMessage());
            return ['ok' => false, 'changed' => false, 'stale' => false];
        }
    }

    public static function rxRenderRichSections($from_id): void
    {
        global $setting;
        $catalog = self::rx_ui_section_catalog();
        $selection = self::rx_ui_selection();
        $rows = [];
        foreach ($catalog as $id => $label) {
            $on = in_array($id, $selection['ids'], true);
            $rows[] = [['text' => ($on ? '✅ ' : '❌ ') . $label, 'callback_data' => 'richsec_t_' . $id . '_' . ($on ? '1' : '0')]];
        }
        $rows[] = [
            ['text' => 'انتخاب همه', 'callback_data' => 'richsec_all'],
            ['text' => 'لغو انتخاب همه', 'callback_data' => 'richsec_none'],
        ];
        $rows[] = [['text' => 'بازگشت', 'callback_data' => 'glass_mode_settings']];
        $masterOn = rx_richUserButtonsOn();
        $text = "⚙️ <b>تنظیمات داخلی متن</b>\n\n"
            . "وضعیت دکمه داخل متن پیام: " . ($masterOn ? "✅ روشن" : "❌ خاموش") . "\n\n"
            . "با انتخاب هر بخش، دکمه‌های آن بخش و تمام مراحل وابسته به آن به‌صورت «دکمه داخل متن پیام» نمایش داده می‌شوند. بخش‌های انتخاب‌نشده با حالت عادی (حالت شیشه‌ای بخش کاربر یا Reply Keyboard) نمایش داده می‌شوند.\n"
            . ($masterOn ? "" : "\n💡 تا زمانی که «دکمه داخل متن پیام» خاموش است، انتخاب‌ها ذخیره می‌شوند ولی اعمال نمی‌شوند.\n");
        if (in_array($selection['state'], ['malformed', 'unknown'], true)) {
            $text .= "\n⚠️ تنظیمات ذخیره‌شده نامعتبر است و موقتاً همه بخش‌ها (یا فقط بخش‌های معتبر) در نظر گرفته می‌شوند. با تغییر انتخاب، تنظیمات اصلاح می‌شود.\n";
        }
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $text, json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE), 'HTML');
    }

    public static function rx_ui_sections_admin_action(string $datain, $from_id, bool $authorized, $callbackQueryId = null): bool
    {
        if (!preg_match('/^richsec_(menu|all|none|t_([a-z_]+)_([01]))$/', $datain, $m)) {
            return false;
        }
        if (!$authorized) {
            if (!empty($callbackQueryId)) {
                @telegram('answerCallbackQuery', ['callback_query_id' => $callbackQueryId, 'text' => 'شما دسترسی تغییر این تنظیم را ندارید.', 'show_alert' => true]);
            }
            return true;
        }
        $outcome = null;
        if ($m[1] === 'all') {
            $outcome = self::rx_ui_sections_mutate('all');
        } elseif ($m[1] === 'none') {
            $outcome = self::rx_ui_sections_mutate('none');
        } elseif (isset($m[2]) && $m[2] !== '') {
            $outcome = self::rx_ui_sections_mutate('toggle', $m[2], $m[3]);
        }
        if ($outcome !== null) {
            if (method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_refreshSettings')) {
                \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_refreshSettings();
            }
            if (!empty($callbackQueryId) && !empty($outcome['stale'])) {
                @telegram('answerCallbackQuery', ['callback_query_id' => $callbackQueryId, 'text' => 'وضعیت تغییر کرده بود؛ صفحه به‌روز شد.']);
            }
        }
        self::rxRenderRichSections($from_id);
        return true;
    }
}
