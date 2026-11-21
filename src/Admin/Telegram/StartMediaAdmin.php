<?php

namespace Faoxima\Admin\Telegram;

use PDO;

final class StartMediaAdmin
{
    public static function rxStartMediaWaitingStep($section = 'start') {
    return $section === 'start' ? 'start_media_waiting' : 'start_media_waiting:' . $section;
}

    public static function rxStartMediaStepSection($step) {
    $step = (string) $step;
    if ($step === 'start_media_waiting') {
        return 'start';
    }
    if (strpos($step, 'start_media_waiting:') === 0) {
        $section = substr($step, strlen('start_media_waiting:'));
        return \Faoxima\Telegram\Media\SectionMedia::rx_section_media_is_valid_section($section) ? $section : null;
    }
    return null;
}

    public static function rxStartMediaCallbacks() {
    return [
        'stmedia_menu', 'stmedia_banner_off', 'stmedia_settings', 'stmedia_toggle', 'stmedia_set',
        'stmedia_cancel', 'stmedia_type', 'stmedia_preview', 'stmedia_delete', 'stmedia_delete_yes',
        'stmedia_sections', 'stmedia_pick',
    ];
}

    public static function rxStartMediaParseCallback($callbackName) {
    $parts = explode(':', (string) $callbackName, 2);
    $base = $parts[0];
    $section = $parts[1] ?? 'start';
    if (!in_array($base, self::rxStartMediaCallbacks(), true) || !\Faoxima\Telegram\Media\SectionMedia::rx_section_media_is_valid_section($section)) {
        return null;
    }
    return [$base, $section];
}

    public static function rxStartMediaCb($base, $section) {
    return $section === 'start' ? $base : $base . ':' . $section;
}

    public static function rxStartMediaBannerKey($section) {
    $map = ['start' => 'start', 'buy' => 'buy'];
    return $map[$section] ?? null;
}

    public static function rxStartMediaBannerNoun($section) {
    $map = ['start' => 'بنر استارت', 'buy' => 'بنر خرید اشتراک'];
    return $map[$section] ?? '';
}

    public static function rxStartMediaSectionLabel($section) {
    $sections = \Faoxima\Telegram\Media\SectionMedia::rx_section_media_sections();
    return (string) ($sections[$section] ?? $section);
}

    public static function rxStartMediaSectionName($section) {
    return trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', self::rxStartMediaSectionLabel($section)));
}

    public static function rxStartMediaColumns() {
    return [
        'start_media_status' => "VARCHAR(20) NULL DEFAULT '0'",
        'start_media_type' => "VARCHAR(32) NULL DEFAULT ''",
        'start_media_file_id' => "VARCHAR(512) NULL DEFAULT ''",
        'start_media_text' => "VARCHAR(255) NULL DEFAULT ''",
        'start_media_entities' => "TEXT NULL",
    ];
}

    public static function rxStartMediaEnsureSchema() {
    global $pdo;
    static $schemaReady = null;
    if ($schemaReady !== null) {
        return $schemaReady;
    }
    if (!($pdo instanceof PDO)) {
        return $schemaReady = false;
    }
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'setting'");
        $stmt->execute();
        $existingColumns = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if (empty($existingColumns)) {
            return $schemaReady = false;
        }
        foreach (self::rxStartMediaColumns() as $columnName => $columnDefinition) {
            if (!in_array(strtolower($columnName), $existingColumns, true)) {
                $pdo->exec("ALTER TABLE setting ADD `{$columnName}` {$columnDefinition}");
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS section_media (
        section_id VARCHAR(32) NOT NULL PRIMARY KEY,
        media_status VARCHAR(20) NULL DEFAULT '0',
        media_type VARCHAR(32) NULL DEFAULT '',
        media_file_id VARCHAR(512) NULL DEFAULT '',
        media_text VARCHAR(255) NULL DEFAULT '',
        media_entities TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return $schemaReady = true;
    } catch (\Throwable $schemaError) {
        \Faoxima\Telegram\Media\SectionMedia::rx_start_media_log('schema ensure failed: ' . $schemaError->getMessage());
        return $schemaReady = false;
    }
}

    public static function rxStartMediaReloadSetting() {
    global $setting;
    $freshSetting = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select", ['cache' => false]);
    if (is_array($freshSetting)) {
        $setting = $freshSetting;
    }
    \Faoxima\Telegram\Media\SectionMedia::rx_section_media_table_rows(true);
    return is_array($setting) ? $setting : [];
}

    public static function rxSectionMediaBuildWrite($section, array $fields): array {
    if ($section === 'start') {
        $assignments = [];
        foreach (array_keys($fields) as $name) {
            $assignments[] = "start_media_{$name} = ?";
        }
        return ["UPDATE setting SET " . implode(', ', $assignments), array_values($fields)];
    }

    $columns = ['section_id'];
    $placeholders = ['?'];
    $updates = [];
    $params = [$section];
    foreach ($fields as $name => $value) {
        $columns[] = "media_{$name}";
        $placeholders[] = '?';
        $updates[] = "media_{$name} = VALUES(media_{$name})";
        $params[] = $value;
    }
    return ["INSERT INTO section_media (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ") ON DUPLICATE KEY UPDATE " . implode(', ', $updates), $params];
}

    public static function rxSectionMediaApply($section, array $fields, $clearBanner = false) {
    global $pdo;
    if (!($pdo instanceof PDO) || !\Faoxima\Telegram\Media\SectionMedia::rx_section_media_is_valid_section($section)) {
        return false;
    }
    $allowed = ['status', 'type', 'file_id', 'text', 'entities'];
    $fields = array_intersect_key($fields, array_flip($allowed));
    if (empty($fields)) {
        return false;
    }
    $bannerKey = $clearBanner ? self::rxStartMediaBannerKey($section) : null;
    try {
        $pdo->beginTransaction();
        [$sql, $params] = self::rxSectionMediaBuildWrite($section, $fields);
        $pdo->prepare($sql)->execute($params);
        if ($bannerKey !== null) {
            $pdo->exec("UPDATE setting SET banner_{$bannerKey}_status = '0'");
        }
        $pdo->commit();
        return true;
    } catch (\Throwable $writeError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        \Faoxima\Telegram\Media\SectionMedia::rx_start_media_log('write failed: ' . $writeError->getMessage());
        return false;
    }
}

    public static function rxStartMediaWrite($sql, array $params = []) {
    global $pdo;
    if (!($pdo instanceof PDO)) {
        return false;
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (\Throwable $writeError) {
        \Faoxima\Telegram\Media\SectionMedia::rx_start_media_log('write failed: ' . $writeError->getMessage());
        return false;
    }
}

    public static function rxStartMediaTypeLabel($type) {
    $labels = [
        'sticker' => "🖼 استیکر معمولی",
        'animation' => "🎞 گیف / انیمیشن",
        'emoji' => "😀 ایموجی معمولی",
        'custom_emoji' => "🌟 ایموجی پرمیوم",
    ];
    return $labels[(string) $type] ?? "— تنظیم نشده";
}

    public static function rxStartMediaAnswer($toast = null, $showAlert = false) {
    global $callback_query_id;
    if (empty($callback_query_id)) {
        return;
    }
    $payload = ['callback_query_id' => $callback_query_id, 'cache_time' => 0];
    if (is_string($toast) && $toast !== '') {
        $payload['text'] = $toast;
        $payload['show_alert'] = (bool) $showAlert;
    }
    try {
        telegram('answerCallbackQuery', $payload);
    } catch (\Throwable $answerError) {
    }
}

    public static function rxStartMediaShow($from_id, $text, array $inlineRows, $editInPlace) {
    global $message_id;
    $keyboard = json_encode(['inline_keyboard' => $inlineRows], JSON_UNESCAPED_UNICODE);
    if ($editInPlace && !empty($message_id)) {
        $editResult = Editmessagetext($from_id, $message_id, $text, $keyboard, 'HTML');
        if (is_array($editResult) && (!empty($editResult['ok']) || stripos((string)($editResult['description'] ?? ''), 'message is not modified') !== false)) {
            return $editResult;
        }
    }
    return sendmessage($from_id, $text, $keyboard, 'HTML');
}

    public static function rxStartMediaStatusText($isOn) {
    global $textbotlang;
    return $isOn
        ? (string)($textbotlang['Admin']['Status']['statuson'] ?? '✅ روشن')
        : (string)($textbotlang['Admin']['Status']['statusoff'] ?? '❌ خاموش');
}

    public static function rxStartMediaRenderSections($from_id, $editInPlace = true) {
    $settingRow = self::rxStartMediaReloadSetting();
    $text = "🎯 <b>انتخاب بخش</b>\n\n"
        . "بخشی را که می‌خواهید رسانه‌ی خودکارش را تنظیم کنید انتخاب کنید.\n"
        . "هر بخش تنظیمات و وضعیت مستقل دارد.\n\n"
        . "✅ = ارسال خودکار روشن | ▫️ = خاموش";
    $rows = [];
    $pair = [];
    foreach (\Faoxima\Telegram\Media\SectionMedia::rx_section_media_sections() as $sectionId => $sectionLabel) {
        $mark = \Faoxima\Telegram\Media\SectionMedia::rx_section_media_is_enabled($sectionId, $settingRow) ? "✅ " : "";
        $button = ['text' => $mark . $sectionLabel, 'callback_data' => self::rxStartMediaCb('stmedia_pick', $sectionId)];
        if ($sectionId === 'start') {
            $rows[] = [$button];
            continue;
        }
        $pair[] = $button;
        if (count($pair) === 2) {
            $rows[] = $pair;
            $pair = [];
        }
    }
    if (!empty($pair)) {
        $rows[] = $pair;
    }
    $rows[] = [['text' => "🔙 بازگشت", 'callback_data' => 'stmedia_settings']];
    return self::rxStartMediaShow($from_id, $text, $rows, $editInPlace);
}

    public static function rxStartMediaRenderMenu($from_id, $editInPlace = true, $section = 'start') {
    $settingRow = self::rxStartMediaReloadSetting();
    $bannerKey = self::rxStartMediaBannerKey($section);
    $bannerNoun = self::rxStartMediaBannerNoun($section);
    $bannerOn = $bannerKey !== null && (string)($settingRow["banner_{$bannerKey}_status"] ?? '0') === '1';
    $mediaOn = \Faoxima\Telegram\Media\SectionMedia::rx_section_media_is_enabled($section, $settingRow);
    $storedConfig = \Faoxima\Telegram\Media\SectionMedia::rx_section_media_stored_config($section, $settingRow);
    $title = $section === 'start' ? "ارسال استیکر در استارت" : "ارسال استیکر در " . self::rxStartMediaSectionName($section);
    $text = "🎭 <b>{$title}</b>\n\n"
        . "📌 <b>وضعیت فعلی:</b>\n"
        . "• {$bannerNoun}: " . self::rxStartMediaStatusText($bannerOn) . "\n"
        . "• ارسال خودکار رسانه: " . self::rxStartMediaStatusText($mediaOn) . "\n"
        . "• رسانه تنظیم‌شده: " . self::rxStartMediaTypeLabel($storedConfig['type'] ?? '') . "\n\n"
        . "💡 {$bannerNoun} و رسانه خودکار هم‌زمان فعال نمی‌شوند؛ با روشن کردن ارسال رسانه، {$bannerNoun} به‌صورت خودکار خاموش می‌شود.";
    $bannerButton = $bannerOn
        ? "🖼 غیرفعال‌سازی {$bannerNoun} (اکنون روشن)"
        : "🖼 {$bannerNoun} غیرفعال است";
    $rows = [
        [['text' => $bannerButton, 'callback_data' => self::rxStartMediaCb('stmedia_banner_off', $section)]],
        [['text' => "⚙️ تنظیمات ارسال استیکر", 'callback_data' => self::rxStartMediaCb('stmedia_settings', $section)]],
        [['text' => "🔙 بازگشت", 'callback_data' => $section === 'start' ? 'featcat_bot' : 'stmedia_sections']],
    ];
    return self::rxStartMediaShow($from_id, $text, $rows, $editInPlace);
}

    public static function rxStartMediaRenderSettings($from_id, $editInPlace = true, $headline = '', $section = 'start') {
    $settingRow = self::rxStartMediaReloadSetting();
    $mediaOn = \Faoxima\Telegram\Media\SectionMedia::rx_section_media_is_enabled($section, $settingRow);
    $storedConfig = \Faoxima\Telegram\Media\SectionMedia::rx_section_media_stored_config($section, $settingRow);
    $typeLabel = self::rxStartMediaTypeLabel($storedConfig['type'] ?? '');
    $text = ($headline !== '' ? $headline . "\n\n" : '')
        . "⚙️ <b>تنظیمات ارسال استیکر</b>\n\n";
    if ($section === 'start') {
        $text .= "• ارسال خودکار در /start: " . self::rxStartMediaStatusText($mediaOn) . "\n";
    } else {
        $text .= "• بخش: " . self::rxStartMediaSectionLabel($section) . "\n"
            . "• ارسال خودکار: " . self::rxStartMediaStatusText($mediaOn) . "\n";
    }
    $text .= "• نوع رسانه فعلی: " . $typeLabel . "\n";
    if ($storedConfig !== null && in_array($storedConfig['type'], ['emoji', 'custom_emoji'], true)) {
        $text .= "• ایموجی پایه: " . htmlspecialchars($storedConfig['text'], ENT_QUOTES, 'UTF-8') . "\n";
    }
    $text .= "\n📥 انواع قابل قبول: استیکر معمولی، ایموجی پرمیوم، ایموجی معمولی، گیف (انیمیشن تلگرام).\n";
    if ($section === 'start') {
        $text .= "رسانه قبل از پیام خوش‌آمد /start به‌صورت یک پیام جداگانه ارسال می‌شود.";
    } elseif ($section === 'wallet') {
        $text .= "رسانه هنگام ورود به کیف پول از منوی اصلی، قبل از پاسخ بخش به‌صورت پیام جداگانه ارسال می‌شود؛ ورود به شارژ، رسانه را دوباره ارسال نمی‌کند.";
    } else {
        $text .= "رسانه هنگام ورود به این بخش از منوی اصلی، قبل از پاسخ بخش به‌صورت پیام جداگانه ارسال می‌شود.";
    }
    $backTarget = $section === 'start' ? 'stmedia_menu' : (self::rxStartMediaBannerKey($section) !== null ? self::rxStartMediaCb('stmedia_menu', $section) : 'stmedia_sections');
    $rows = [
        [['text' => ($mediaOn ? "✅ ارسال خودکار: روشن" : "❌ ارسال خودکار: خاموش"), 'callback_data' => self::rxStartMediaCb('stmedia_toggle', $section)]],
        [['text' => ($storedConfig === null ? "📤 تنظیم رسانه" : "📤 جایگزینی رسانه"), 'callback_data' => self::rxStartMediaCb('stmedia_set', $section)]],
        [['text' => "🏷 نوع: " . $typeLabel, 'callback_data' => self::rxStartMediaCb('stmedia_type', $section)]],
        [['text' => "👁 پیش‌نمایش", 'callback_data' => self::rxStartMediaCb('stmedia_preview', $section)],
         ['text' => "🗑 حذف رسانه", 'callback_data' => self::rxStartMediaCb('stmedia_delete', $section)]],
        [['text' => "🎯 انتخاب بخش", 'callback_data' => 'stmedia_sections']],
        [['text' => "🔙 بازگشت", 'callback_data' => $backTarget]],
    ];
    return self::rxStartMediaShow($from_id, $text, $rows, $editInPlace);
}

    public static function rxStartMediaSetStep(&$user, $from_id, $stepName) {
    \Faoxima\Telegram\Navigation\Steps::step($stepName, $from_id);
    if (is_array($user)) {
        $user['step'] = $stepName;
    }
}

    public static function rxStartMediaHandleCallback($from_id, $callbackName, &$user) {
    $parsed = self::rxStartMediaParseCallback($callbackName);
    if ($parsed === null) {
        self::rxStartMediaAnswer("⚠️ درخواست نامعتبر است.", true);
        return;
    }
    list($action, $section) = $parsed;
    $bannerKey = self::rxStartMediaBannerKey($section);
    if (!self::rxStartMediaEnsureSchema()) {
        self::rxStartMediaAnswer("⚠️ ساختار پایگاه داده برای این بخش آماده نیست. لطفاً به‌روزرسانی جداول (table.php) را اجرا کنید.", true);
        return;
    }
    if (self::rxStartMediaStepSection($user['step'] ?? '') !== null && $action !== 'stmedia_set') {
        self::rxStartMediaSetStep($user, $from_id, 'home');
    }
    $settingRow = self::rxStartMediaReloadSetting();

    if ($action === 'stmedia_menu') {
        self::rxStartMediaAnswer();
        self::rxStartMediaRenderMenu($from_id, true, $section);
        return;
    }
    if ($action === 'stmedia_sections') {
        self::rxStartMediaAnswer();
        self::rxStartMediaRenderSections($from_id);
        return;
    }
    if ($action === 'stmedia_pick') {
        self::rxStartMediaAnswer();
        if ($bannerKey !== null) {
            self::rxStartMediaRenderMenu($from_id, true, $section);
        } else {
            self::rxStartMediaRenderSettings($from_id, true, '', $section);
        }
        return;
    }
    if ($action === 'stmedia_settings') {
        self::rxStartMediaAnswer();
        self::rxStartMediaRenderSettings($from_id, true, '', $section);
        return;
    }
    if ($action === 'stmedia_banner_off') {
        if ($bannerKey === null) {
            self::rxStartMediaAnswer("⚠️ درخواست نامعتبر است.", true);
            return;
        }
        $bannerNoun = self::rxStartMediaBannerNoun($section);
        if ((string)($settingRow["banner_{$bannerKey}_status"] ?? '0') !== '1') {
            self::rxStartMediaAnswer("ℹ️ {$bannerNoun} از قبل غیرفعال است.", true);
            self::rxStartMediaRenderMenu($from_id, true, $section);
            return;
        }
        if (!self::rxStartMediaWrite("UPDATE setting SET banner_{$bannerKey}_status = '0'")) {
            self::rxStartMediaAnswer("❌ غیرفعال‌سازی بنر ناموفق بود. لطفاً دوباره تلاش کنید.", true);
            return;
        }
        self::rxStartMediaAnswer("✅ {$bannerNoun} غیرفعال شد.");
        self::rxStartMediaRenderMenu($from_id, true, $section);
        return;
    }
    if ($action === 'stmedia_toggle') {
        if (\Faoxima\Telegram\Media\SectionMedia::rx_section_media_is_enabled($section, $settingRow)) {
            if (!self::rxSectionMediaApply($section, ['status' => '0'])) {
                self::rxStartMediaAnswer("❌ ذخیره تغییرات ناموفق بود.", true);
                return;
            }
            self::rxStartMediaAnswer("❌ ارسال خودکار رسانه خاموش شد.");
            self::rxStartMediaRenderSettings($from_id, true, '', $section);
            return;
        }
        if (\Faoxima\Telegram\Media\SectionMedia::rx_section_media_stored_config($section, $settingRow) === null) {
            self::rxStartMediaAnswer("⚠️ ابتدا یک رسانه معتبر تنظیم کنید.", true);
            return;
        }
        $bannerWasOn = $bannerKey !== null && (string)($settingRow["banner_{$bannerKey}_status"] ?? '0') === '1';
        if (!self::rxSectionMediaApply($section, ['status' => '1'], true)) {
            self::rxStartMediaAnswer("❌ فعال‌سازی ناموفق بود؛ هیچ تغییری اعمال نشد.", true);
            return;
        }
        $bannerNoun = self::rxStartMediaBannerNoun($section);
        self::rxStartMediaAnswer($bannerWasOn
            ? "✅ ارسال خودکار رسانه روشن شد و {$bannerNoun} به‌صورت خودکار غیرفعال شد."
            : "✅ ارسال خودکار رسانه روشن شد.", $bannerWasOn);
        self::rxStartMediaRenderSettings($from_id, true, '', $section);
        return;
    }
    if ($action === 'stmedia_set') {
        self::rxStartMediaSetStep($user, $from_id, self::rxStartMediaWaitingStep($section));
        self::rxStartMediaAnswer();
        self::rxStartMediaShow($from_id,
            "📤 <b>تنظیم رسانه " . ($section === 'start' ? "استارت" : self::rxStartMediaSectionName($section)) . "</b>\n\n"
            . "یکی از موارد زیر را ارسال کنید:\n"
            . "• استیکر معمولی\n"
            . "• ایموجی پرمیوم\n"
            . "• ایموجی معمولی (فقط ایموجی، بدون متن)\n"
            . "• گیف / انیمیشن تلگرام\n\n"
            . "ℹ️ تا زمانی که رسانه معتبری دریافت نشود، رسانه فعلی تغییر نمی‌کند.",
            [[['text' => "🔙 لغو", 'callback_data' => self::rxStartMediaCb('stmedia_cancel', $section)]]],
            true
        );
        return;
    }
    if ($action === 'stmedia_cancel') {
        self::rxStartMediaAnswer("لغو شد.");
        self::rxStartMediaRenderSettings($from_id, true, '', $section);
        return;
    }
    if ($action === 'stmedia_type') {
        $storedConfig = \Faoxima\Telegram\Media\SectionMedia::rx_section_media_stored_config($section, $settingRow);
        self::rxStartMediaAnswer("نوع رسانه فعلی: " . self::rxStartMediaTypeLabel($storedConfig['type'] ?? ''), true);
        return;
    }
    if ($action === 'stmedia_preview') {
        $storedConfig = \Faoxima\Telegram\Media\SectionMedia::rx_section_media_stored_config($section, $settingRow);
        if ($storedConfig === null) {
            self::rxStartMediaAnswer("⚠️ هیچ رسانه معتبری تنظیم نشده است.", true);
            return;
        }
        self::rxStartMediaAnswer();
        global $message_id;
        if (!empty($message_id)) {
            deletemessage($from_id, $message_id);
        }
        $previewResult = \Faoxima\Telegram\Media\SectionMedia::rx_start_media_dispatch($from_id, $storedConfig);
        $headline = '';
        if (!is_array($previewResult) || empty($previewResult['ok'])) {
            \Faoxima\Telegram\Media\SectionMedia::rx_start_media_log($section . ' preview ' . $storedConfig['type'] . ' failed: ' . (is_array($previewResult) ? (string)($previewResult['description'] ?? 'unknown error') : 'no response'));
            $headline = "❌ ارسال پیش‌نمایش ناموفق بود؛ ممکن است رسانه ذخیره‌شده دیگر معتبر نباشد. لطفاً رسانه را دوباره تنظیم کنید.";
        } else {
            $headline = "👆 پیش‌نمایش رسانه " . ($section === 'start' ? "استارت" : self::rxStartMediaSectionName($section));
        }
        self::rxStartMediaRenderSettings($from_id, false, $headline, $section);
        return;
    }
    if ($action === 'stmedia_delete') {
        $row = \Faoxima\Telegram\Media\SectionMedia::rx_section_media_row($section, $settingRow);
        if (\Faoxima\Telegram\Media\SectionMedia::rx_section_media_stored_config($section, $settingRow) === null && trim((string)($row['type'] ?? '')) === '') {
            self::rxStartMediaAnswer("ℹ️ رسانه‌ای برای حذف وجود ندارد.", true);
            return;
        }
        self::rxStartMediaAnswer();
        self::rxStartMediaShow($from_id,
            "🗑 <b>حذف رسانه " . ($section === 'start' ? "استارت" : self::rxStartMediaSectionName($section)) . "</b>\n\nآیا مطمئن هستید؟ با حذف رسانه، ارسال خودکار نیز خاموش می‌شود.",
            [[['text' => "✅ بله، حذف شود", 'callback_data' => self::rxStartMediaCb('stmedia_delete_yes', $section)],
              ['text' => "❌ خیر", 'callback_data' => self::rxStartMediaCb('stmedia_settings', $section)]]],
            true
        );
        return;
    }
    if ($action === 'stmedia_delete_yes') {
        if (!self::rxSectionMediaApply($section, ['status' => '0', 'type' => '', 'file_id' => '', 'text' => '', 'entities' => null])) {
            self::rxStartMediaAnswer("❌ حذف رسانه ناموفق بود.", true);
            return;
        }
        self::rxStartMediaAnswer("🗑 رسانه حذف شد.");
        self::rxStartMediaRenderSettings($from_id, true, '', $section);
        return;
    }
    self::rxStartMediaAnswer("⚠️ درخواست نامعتبر است.", true);
}

    public static function rxStartMediaHandleInput($from_id, array $message, &$user, $section = 'start') {
    $cancelKind = null;
    if (isset($message['text']) && is_string($message['text']) && method_exists(\Faoxima\Admin\Telegram\PremiumEmoji::class, 'rxPremiumEmojiCancelKind')) {
        $cancelKind = \Faoxima\Admin\Telegram\PremiumEmoji::rxPremiumEmojiCancelKind($message['text'], '');
    }
    if ($cancelKind === 'admin') {
        self::rxStartMediaSetStep($user, $from_id, 'home');
        return false;
    }
    if ($cancelKind === 'panel') {
        self::rxStartMediaSetStep($user, $from_id, 'home');
        self::rxStartMediaRenderSettings($from_id, false, "لغو شد.", $section);
        return true;
    }
    $cancelRow = [[['text' => "🔙 لغو", 'callback_data' => self::rxStartMediaCb('stmedia_cancel', $section)]]];
    $parsedConfig = \Faoxima\Telegram\Media\SectionMedia::rx_start_media_parse_message($message);
    if ($parsedConfig === null) {
        self::rxStartMediaShow($from_id,
            "❌ <b>ورودی نامعتبر است.</b>\n\n"
            . "فقط یکی از موارد زیر پذیرفته می‌شود:\n"
            . "• استیکر معمولی\n"
            . "• ایموجی پرمیوم\n"
            . "• ایموجی معمولی (حداکثر ۵ ایموجی، بدون متن یا فاصله)\n"
            . "• گیف / انیمیشن تلگرام\n\n"
            . "رسانه فعلی تغییری نکرد. دوباره ارسال کنید یا لغو را بزنید.",
            $cancelRow,
            false
        );
        return true;
    }
    if (!self::rxStartMediaEnsureSchema()) {
        self::rxStartMediaShow($from_id, "⚠️ ساختار پایگاه داده برای این بخش آماده نیست. لطفاً به‌روزرسانی جداول (table.php) را اجرا کنید.", $cancelRow, false);
        return true;
    }
    $saved = self::rxSectionMediaApply($section, [
        'type' => $parsedConfig['type'],
        'file_id' => $parsedConfig['file_id'],
        'text' => $parsedConfig['text'],
        'entities' => empty($parsedConfig['entities']) ? null : json_encode($parsedConfig['entities'], JSON_UNESCAPED_UNICODE),
    ]);
    if (!$saved) {
        self::rxStartMediaShow($from_id, "❌ ذخیره رسانه ناموفق بود. رسانه قبلی حفظ شد؛ دوباره تلاش کنید یا لغو را بزنید.", $cancelRow, false);
        return true;
    }
    self::rxStartMediaSetStep($user, $from_id, 'home');
    self::rxStartMediaRenderSettings($from_id, false, "✅ رسانه با موفقیت ذخیره شد: " . self::rxStartMediaTypeLabel($parsedConfig['type']), $section);
    return true;
}

    public static function rxStartMediaHandleAdminUpdate($from_id, $datain, $update, &$user, $isAdministrator) {
    $callbackName = is_string($datain) ? trim($datain) : '';
    $waitingSection = is_array($user) ? self::rxStartMediaStepSection($user['step'] ?? '') : null;
    $isWaiting = $waitingSection !== null;
    if ($callbackName !== '' && strpos($callbackName, 'stmedia_') === 0) {
        if (!$isAdministrator) {
            self::rxStartMediaAnswer("⛔️ فقط ادمین اصلی به این بخش دسترسی دارد.", true);
            return true;
        }
        if (self::rxStartMediaParseCallback($callbackName) === null) {
            self::rxStartMediaAnswer("⚠️ درخواست نامعتبر است.", true);
            return true;
        }
        try {
            self::rxStartMediaHandleCallback($from_id, $callbackName, $user);
        } catch (\Throwable $callbackError) {
            \Faoxima\Telegram\Media\SectionMedia::rx_start_media_log('admin callback ' . $callbackName . ' failed: ' . $callbackError->getMessage());
            self::rxStartMediaAnswer("⚠️ خطای داخلی رخ داد.", true);
        }
        return true;
    }
    if (!$isWaiting) {
        return false;
    }
    if (!$isAdministrator || $callbackName !== '') {
        self::rxStartMediaSetStep($user, $from_id, 'home');
        return false;
    }
    $message = (is_array($update) && isset($update['message']) && is_array($update['message'])) ? $update['message'] : null;
    if ($message === null) {
        return false;
    }
    try {
        return self::rxStartMediaHandleInput($from_id, $message, $user, $waitingSection);
    } catch (\Throwable $inputError) {
        \Faoxima\Telegram\Media\SectionMedia::rx_start_media_log('admin input failed: ' . $inputError->getMessage());
        self::rxStartMediaShow($from_id, "⚠️ خطای داخلی رخ داد؛ رسانه قبلی حفظ شد.", [[['text' => "🔙 لغو", 'callback_data' => self::rxStartMediaCb('stmedia_cancel', $waitingSection)]]], false);
        return true;
    }
}
}
