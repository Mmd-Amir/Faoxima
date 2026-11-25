<?php

namespace Faoxima\Services\Delivery;

use CURLFile;
use DateTime;
use DateTimeZone;
use Exception;
use ManagePanel;
use PDO;
use RuntimeException;
use Throwable;

final class ServiceDelivery
{
    public static function rxManualsaleExtIsFile($fileExt)
    {
        $ext = strtolower(ltrim(trim((string) $fileExt), '.'));
        return $ext !== '' && $ext !== 'sub' && $ext !== 'text';
    }

    public static function rxShouldShowConnectionLink($panel_info, $fileExt = null)
    {
        $type = is_array($panel_info) ? ($panel_info['type'] ?? '') : (string) $panel_info;
        if ($type === 'Manualsale') {
            return !self::rxManualsaleExtIsFile($fileExt);
        }
        $sublink = is_array($panel_info) ? ($panel_info['sublink'] ?? '') : '';
        return $sublink == "onsublink";
    }

    public static function rxResolveConnectionLink($panel_info, $subscriptionLink, $fileExt = null)
    {
        $type = is_array($panel_info) ? ($panel_info['type'] ?? '') : (string) $panel_info;
        if ($type !== 'Manualsale') {
            return (string) $subscriptionLink;
        }
        if (self::rxManualsaleExtIsFile($fileExt)) {
            return '';
        }
        return (string) $subscriptionLink;
    }

    public static function rxPurchaseDeliveryPanel(array $panel, $subscriptionLink): array
    {
        if (($panel['type'] ?? '') !== 'Manualsale' && ($panel['auto_file'] ?? '') === 'onautofile'
            && self::rxValidSubscriptionUrl($subscriptionLink)) {
            $panel['sublink'] = 'onsublink';
        }
        return $panel;
    }

    public static function rxValidSubscriptionUrl($link): bool
    {
        return is_string($link) && filter_var($link, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($link, PHP_URL_SCHEME)), ['https', 'http'], true);
    }

    public static function rxSavedSubscriptionUrl(array $invoice, array $panel): string
    {
        if (!in_array($invoice['Status'] ?? '', ['active', 'sendedwarn', 'send_on_hold'], true)
            || ($panel['type'] ?? '') === 'Manualsale'
            || ($panel['national_net_status'] ?? '') === 'on_national_net') {
            return '';
        }
        $saved = json_decode((string) ($invoice['user_info'] ?? ''), true);
        $link = $saved['subscription_url'] ?? '';
        if (!self::rxValidSubscriptionUrl($link) || ($saved['username'] ?? null) !== ($invoice['username'] ?? null)) {
            return '';
        }
        return self::rxShouldShowConnectionLink(self::rxPurchaseDeliveryPanel($panel, $link)) ? $link : '';
    }

    public static function rxServiceListStatusSuffix(array $invoice): string
    {
        return in_array(strtolower((string) ($invoice['Status'] ?? '')), ['disabled', 'disablebyadmin'], true) ? ' | غیرفعال' : '';
    }

    public static function rxServiceListBaseLabel(array $invoice): string
    {
        $product = trim((string) ($invoice['name_product'] ?? ''));
        return $product !== '' ? $product : (string) ($invoice['username'] ?? '');
    }

    public static function rxServiceListLabelKey(array $invoice, bool $withNote): string
    {
        return self::rxServiceListBaseLabel($invoice) . ($withNote ? "\x1F" . trim((string) ($invoice['note'] ?? '')) : '');
    }

    public static function rxServiceListDuplicateKeys(array $rows, bool $withNote): array
    {
        $counts = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = self::rxServiceListLabelKey($row, $withNote);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        $duplicates = [];
        foreach ($counts as $key => $count) {
            if ($count > 1) {
                $duplicates[$key] = true;
            }
        }
        return $duplicates;
    }

    public static function rxServiceListUserDuplicateKeys($userId, array $statuses, bool $withNote): array
    {
        global $pdo;
        if (!($pdo instanceof PDO) || $statuses === []) {
            return [];
        }
        try {
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $stmt = $pdo->prepare("SELECT name_product, username, note FROM invoice WHERE id_user = ? AND Status IN ($placeholders)");
            $stmt->execute(array_merge([(string) $userId], array_values($statuses)));
            return self::rxServiceListDuplicateKeys($stmt->fetchAll(PDO::FETCH_ASSOC), $withNote);
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function rxServiceListLabel(array $invoice, array $duplicateKeys = [], bool $withNote = false): string
    {
        $label = self::rxServiceListBaseLabel($invoice);
        $username = (string) ($invoice['username'] ?? '');
        if ($username !== '' && $label !== $username && isset($duplicateKeys[self::rxServiceListLabelKey($invoice, $withNote)])) {
            $short = mb_strlen($username) > 12 ? '…' . mb_substr($username, -10) : $username;
            $label .= ' (' . $short . ')';
        }
        return $label;
    }

    public static function formatOnlineAtLabel($onlineAt, $isOnline = null)
    {
        if ($isOnline === true && (empty($onlineAt) || $onlineAt === null)) {
            return "Online (بدون زمان)";
        }

        if ($onlineAt === null || $onlineAt === '') {
            return "—";
        }

        if (is_string($onlineAt)) {
            $onlineAt = trim($onlineAt);
            if ($onlineAt === '') {
                return "—";
            }
            $lowered = strtolower($onlineAt);
            if ($lowered === 'online') {
                return 'آنلاین';
            }
            if ($lowered === 'offline') {
                return 'آفلاین';
            }
        }

        try {
            if (is_numeric($onlineAt)) {
                $dateTime = new DateTime('@' . intval($onlineAt));
                $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
            } else {
                $dateTime = new DateTime((string) $onlineAt, new DateTimeZone('UTC'));
                $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
            }
            return jdate('Y/m/d H:i:s', $dateTime->getTimestamp());
        } catch (Exception $e) {
            return (string) $onlineAt;
        }
    }

    public static function rx_build_smart_random_username($telegramUsername, $telegramId)
    {
        $hasUsername = is_string($telegramUsername) && $telegramUsername !== '' && !in_array(strtolower($telegramUsername), ['not_username', 'none'], true);
        if ($hasUsername) {
            $suffix = str_pad((string) rand(0, 999), 3, '0', STR_PAD_LEFT);
            $base = substr(str_replace('_', '-', strtolower($telegramUsername)), 0, 28);
            return $base . '-' . $suffix;
        }
        $letters = '';
        for ($i = 0; $i < 3; $i++) {
            $letters .= chr(rand(97, 122));
        }
        return 'u' . $telegramId . '-' . $letters;
    }

    public static function generateUsername($from_id, $Metode, $username, $randomString, $text, $namecustome, $usernamecustom)
    {
        $username = str_replace('_', '-', (string) $username);
        $text = str_replace('_', '-', (string) $text);
        $namecustome = str_replace('_', '-', (string) $namecustome);
        $usernamecustom = str_replace('_', '-', (string) $usernamecustom);
        $setting = \Faoxima\Core\Database\Db::select("setting", "*", null, null, "select");
        $user = \Faoxima\Core\Database\Db::select("user", "*", "id", $from_id, "select");
        if ($user == false) {
            $user = array();
            $user = array(
                'number_username' => '',
            );
        }
        $telegramUsernameMissing = trim($username) === '' || in_array(strtolower($username), ['not_username', 'not-username', 'none'], true);
        $telegramUsernameBase = $telegramUsernameMissing ? $namecustome : $username;
        if ($telegramUsernameBase === '' || strtolower($telegramUsernameBase) === 'none') {
            $telegramUsernameBase = 'u' . $from_id;
        }
        $panelCustomBase = trim($namecustome) !== '' && strtolower($namecustome) !== 'none' ? $namecustome : 'u' . $from_id;
        $requestedBase = trim($text) !== '' ? $text : $telegramUsernameBase;
        if ($Metode == "آیدی عددی + حروف و عدد رندوم") {
            return $from_id . "-" . $randomString;
        } elseif ($Metode == "نام کاربری + حروف و عدد رندوم") {
            return $telegramUsernameBase . "-" . $randomString;
        } elseif ($Metode == "نام کاربری + عدد به ترتیب") {
            return $telegramUsernameBase . "-" . $user['number_username'];
        } elseif ($Metode == "نام کاربری دلخواه")
            return $requestedBase;
        elseif ($Metode == "نام کاربری دلخواه + عدد رندوم") {
            $random_number = rand(1000000, 9999999);
            return $requestedBase . "-" . $random_number;
        } elseif ($Metode == "متن دلخواه کاربر + رندوم") {
            return $requestedBase;
        } elseif ($Metode == "متن دلخواه + عدد رندوم") {
            return $panelCustomBase . "-" . $randomString;
        } elseif ($Metode == "متن دلخواه + عدد ترتیبی") {
            return $panelCustomBase . "-" . $setting['numbercount'];
        } elseif ($Metode == "آیدی عددی+عدد ترتیبی") {
            return $from_id . "-" . $user['number_username'];
        } elseif ($Metode == "آیدی عددی") {
            return (string) $from_id;
        } elseif ($Metode == "متن دلخواه نماینده + عدد ترتیبی") {
            if (trim($usernamecustom) === '' || strtolower($usernamecustom) === "none") {
                return $panelCustomBase . "-" . $setting['numbercount'];
            }
            return $usernamecustom . "-" . $user['number_username'];
        }
        return $from_id . "-" . $randomString;
    }

    public static function outputlunk($text)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $text);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $timeoutInfo = method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_panel_timeout_info') ? \Faoxima\Services\Panels\PanelHttp::faoxima_panel_timeout_info() : ['timeout' => 20, 'connect_timeout' => 8];
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeoutInfo['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeoutInfo['connect_timeout']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            return null;
        } else {
            return $response;
        }

        curl_close($ch);
    }

    public static function outputlunksub($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$url/info");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $timeoutInfo = method_exists(\Faoxima\Services\Panels\PanelHttp::class, 'faoxima_panel_timeout_info') ? \Faoxima\Services\Panels\PanelHttp::faoxima_panel_timeout_info() : ['timeout' => 20, 'connect_timeout' => 8];
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeoutInfo['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeoutInfo['connect_timeout']);
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


        $headers = array();
        $headers[] = 'Accept: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        return $result;
        curl_close($ch);
    }

    public static function normalizeServiceConfigs($configs, $subscriptionUrl = null)
    {
        $normalizeEntries = static function ($entries): array {
            if (is_string($entries)) {
                $entries = preg_split("/\r\n|\n|\r/", $entries);
            }
            if (!is_array($entries)) {
                return [];
            }
            $normalized = [];
            foreach ($entries as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $item = trim($item);
                if ($item === '') {
                    continue;
                }
                $normalized[] = $item;
            }
            return $normalized;
        };

        $normalized = $normalizeEntries($configs);
        $subscriptionUrl = is_string($subscriptionUrl) ? trim($subscriptionUrl) : '';
        if (empty($normalized) && $subscriptionUrl !== '') {
            if (preg_match('/^https?:/i', $subscriptionUrl)) {
                $fetched = self::outputlunk($subscriptionUrl);
                if (is_string($fetched) && $fetched !== '') {
                    if (\Faoxima\Core\Support\Formatting::isBase64($fetched)) {
                        $fetched = base64_decode($fetched);
                    }
                    $normalized = $normalizeEntries($fetched);
                }
            } else {
                $normalized[] = $subscriptionUrl;
            }
        }

        return array_values($normalized);
    }

    public static function rxStripDeliveryContentFromCaption($caption)
    {
        $caption = (string) $caption;
        $markers = [
            "🔗 لینک اتصال:",
            "🔗 لینک اتصال :",
            "لینک اتصال:",
            "لینک اتصال :",
            "🔐 کانفیگ اشتراک :",
            "🔐 کانفیگ اشتراک:",
            "لینک اشتراک:",
            "لینک اشتراک :",
        ];
        foreach ($markers as $marker) {
            $pattern = '/(^|\R)[^\S\r\n]*' . preg_quote($marker, '/') . '[^\S\r\n]*(?:\r?\n)*'
                . '(?:[^\S\r\n]*(?:<code>.*?<\/code>|[A-Za-z][A-Za-z0-9+.\-]*:\/\/\S+)[^\S\r\n]*(?:\r?\n|$))*/us';
            $caption = preg_replace($pattern, '$1', $caption, 1);
        }
        $caption = preg_replace('/(^|\R)[^\S\r\n]*<code>[^<]*:\/\/[^<]*<\/code>/us', '$1', $caption);
        $caption = preg_replace('/(^|\R)[^\S\r\n]*[A-Za-z][A-Za-z0-9+.\-]*:\/\/\S+[^\S\r\n]*($|\R)/u', '$1', $caption);
        $caption = preg_replace('/[^\S\r\n]+$/mu', '', $caption);
        $caption = preg_replace("/\n{3,}/u", "\n\n", $caption);
        return trim($caption);
    }

    public static function rxReceiptDeliveryRoute($topicKey = 'receiptreport')
    {
        global $pdo, $setting;
        $row = null;
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->query("SELECT receipt_topic_reporting, Channel_Report FROM setting ORDER BY CASE WHEN TRIM(COALESCE(Channel_Report, '')) NOT IN ('', '0') THEN 0 ELSE 1 END LIMIT 1");
                $fresh = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                if (is_array($fresh)) {
                    $row = $fresh;
                }
            } catch (Throwable $e) {
                $row = null;
            }
        }
        if (!is_array($row)) {
            $row = is_array($setting ?? null) ? $setting : [];
        }
        $enabled = !array_key_exists('receipt_topic_reporting', $row)
            || !in_array(strtolower(trim((string)$row['receipt_topic_reporting'])), ['0', 'off', 'false', 'no'], true);
        $chatId = $enabled ? trim((string)($row['Channel_Report'] ?? '')) : '';
        if ($chatId === '0') {
            $chatId = '';
        }
        $threadId = null;
        if ($enabled && $chatId !== '' && $pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare('SELECT idreport FROM topicid WHERE report = ? LIMIT 1');
                $stmt->execute([(string)$topicKey]);
                $candidate = (int)$stmt->fetchColumn();
                if ($candidate > 0) {
                    $threadId = $candidate;
                }
            } catch (Throwable $e) {
                $threadId = null;
            }
        }
        return ['topic_enabled' => $enabled, 'chat_id' => $chatId, 'thread_id' => $threadId];
    }

    public static function rxSubscriptionLinkButtonEnabled($panel_info)
    {
        if (($panel_info['type'] ?? '') === 'Manualsale') {
            return true;
        }
        if (method_exists(\Faoxima\Services\Fulfillment\StockPresentation::class, 'nmPanelNationalEnabled') && \Faoxima\Services\Fulfillment\Inventory::nmPanelNationalEnabled($panel_info)) {
            return true;
        }
        return \Faoxima\Telegram\Support\Replies::rxSettingFlag('subscription_link_button', true);
    }

    public static function rxAppendSubscriptionLinkLine($caption, $sub_link, $panel_info)
    {
        $caption = trim((string)$caption);
        $subLink = trim((string)$sub_link);
        if ($subLink === '' || self::rxSubscriptionLinkButtonEnabled($panel_info)) {
            return $caption;
        }
        $lineTemplate = \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_subscription_link_line', '🔗 لینک اتصال: {link}');
        $line = trim(\Faoxima\Telegram\Support\Texts::faoxima_render_text($lineTemplate, [
            'link' => htmlspecialchars($subLink, ENT_QUOTES, 'UTF-8'),
        ]));
        if ($line === '') {
            return $caption;
        }
        return $caption === '' ? $line : $caption . "\n\n" . $line;
    }

    public static function rxManualsalePublicName($recordName, $ext)
    {
        $ext = strtolower(ltrim(trim((string) $ext), '.'));
        if ($ext === '') {
            $ext = 'bin';
        }
        $stem = trim((string) $recordName);
        $suffix = '.' . $ext;
        if ($stem !== '' && strtolower(substr($stem, -strlen($suffix))) === $suffix) {
            $stem = substr($stem, 0, -strlen($suffix));
        }
        return normalizeConfigFilename($stem . $suffix, '', $ext === 'conf');
    }

    public static function rxManualsaleTempPath($publicName)
    {
        $tempExtension = strtolower((string) pathinfo((string) $publicName, PATHINFO_EXTENSION));
        $tempExtension = preg_match('/^[a-z0-9]{1,10}$/', $tempExtension) ? '.' . $tempExtension : '.tmp';
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rxmanual-' . bin2hex(random_bytes(8)) . $tempExtension;
    }

    public static function rxManualsaleFileItems($items)
    {
        $fileItems = [];
        if (!is_array($items)) {
            return $fileItems;
        }
        foreach ($items as $it) {
            if (is_array($it) && !empty($it['is_file'])) {
                $fileItems[] = $it;
            }
        }
        return $fileItems;
    }

    public static function rxManualsaleSendFileItems($user_id, $username_service, $fileItems, $caption = '', $reply_markup = null, $totalForNaming = 0, $startIndex = 0, ?array &$deliveryResults = null)
    {
        $unique = [];
        foreach ((array) $fileItems as $item) {
            if (is_array($item)) {
                $key = hash('sha256', serialize($item));
                $unique[$key] = $item;
            }
        }
        $attachments = [];
        $keyCounts = method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingManualKeyCounts') ? \Faoxima\Services\Delivery\FileNaming::rxFileNamingManualKeyCounts($unique) : [];
        foreach ($unique as $item) {
            $ext = trim((string) ($item['ext'] ?? '')) !== '' ? $item['ext'] : 'bin';
            $name = self::rxManualsalePublicName($item['name'] ?? '', $ext);
            $customCaption = '';
            if (method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingResolveManualItem')) {
                $key = \Faoxima\Services\Delivery\FileNaming::rxFileNamingManualItemKey($item);
                $resolved = \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolveManualItem($item, $name, '', $key !== '' && ($keyCounts[$key] ?? 0) > 1);
                $name = $resolved['filename'];
                $customCaption = $resolved['caption'];
            }
            $content = (string) ($item['content'] ?? '');
            $key = hash('sha256', serialize([$name, $customCaption, $content]));
            $attachments[$key] = ['name' => $name, 'caption' => $customCaption, 'content' => $content];
        }
        $fileCount = count($attachments);
        $index = 0;
        $sentAny = false;
        $files = [];
        $text = [];
        $keyboardState = 'skipped';
        foreach ($attachments as $item) {
            $index++;
            $name = $item['name'];
            $itemCaption = $index === 1 && trim((string) $caption) !== ''
                ? (string) $caption . ($item['caption'] !== '' ? "\n\n" . $item['caption'] : '')
                : $item['caption'];
            $path = self::rxManualsaleTempPath($name);
            $keyboard = $index === $fileCount ? $reply_markup : null;
            $required = trim($itemCaption) !== '';
            $result = null;
            $mediaAttempted = false;
            try {
                if (file_put_contents($path, (string) ($item['content'] ?? '')) !== false && is_readable($path)) {
                    $document = new CURLFile($path, 'application/octet-stream', $name);
                    $mediaAttempted = true;
                    $result = rxSendMediaWithCaption('sendDocument', [
                        'chat_id' => $user_id,
                        'document' => $document,
                        'caption' => $itemCaption,
                        'parse_mode' => 'HTML',
                        'reply_markup' => $keyboard,
                    ], null, $detail, true);
                } else {
                    $fallback = $required ? rxSendRequiredMediaText(['chat_id' => $user_id, 'reply_markup' => $keyboard], $itemCaption) : ['state' => 'skipped'];
                    $detail = [
                        'media_state' => 'failed',
                        'text_state' => $fallback['state'],
                        'text_chunks_confirmed' => $fallback['chunks_confirmed'] ?? 0,
                        'caption_source' => $required ? 'separate_text' : 'none',
                    ];
                }
            } catch (Throwable $e) {
                $detail = ['media_state' => $mediaAttempted ? 'unknown' : 'failed', 'text_state' => $required ? 'unknown' : 'skipped'];
            } finally {
                @unlink($path);
            }
            $state = $detail['media_state'];
            $captionState = $detail['text_state'];
            if ($keyboard !== null) {
                $keyboardState = $state;
                if ($state === 'failed' && in_array($captionState, ['confirmed', 'unknown'], true)) {
                    $keyboardState = $captionState;
                }
            }
            $itemText = $required ? [['required' => true, 'state' => $captionState]] : [];
            if ($captionState !== 'confirmed' && ($detail['text_chunks_confirmed'] ?? 0) > 0) {
                $itemText[] = ['required' => true, 'state' => 'confirmed'];
            }
            $sentAny = $sentAny || $state === 'confirmed';
            $files[] = [
                'required' => true,
                'state' => $state,
                'caption_state' => $captionState,
                'caption_required' => $required,
                'caption_valid' => $detail['caption_valid'] ?? null,
                'caption_complete' => !$required || $captionState === 'confirmed',
                'caption_source' => $detail['caption_source'] ?? 'none',
                'delivery_status' => \Faoxima\Services\Fulfillment\DeliveryState::deliveryAggregate(array_merge([['required' => true, 'state' => $state]], $itemText)),
            ];
            array_push($text, ...$itemText);
        }
        $filesStatus = $files ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryAggregate($files) : 'not_attempted';
        $deliveryResults = [
            'files' => $files,
            'text' => $text,
            'files_delivery_status' => $filesStatus,
            'files_state' => ['completed' => 'confirmed', 'unknown' => 'unknown', 'not_attempted' => 'not_attempted'][$filesStatus] ?? 'failed',
            'text_delivery_status' => $text ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryAggregate($text) : 'skipped',
            'keyboard_state' => $keyboardState,
            'delivery_status' => $files ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryAggregate(array_merge($files, $text)) : 'failed',
        ];
        return $sentAny;
    }

    public static function rxDeliveryAvailability($panel_info, $config, $sub_link)
    {
        if (($panel_info['type'] ?? '') == "Manualsale") {
            $manual = is_array($config) && isset($config['__manual__']) ? $config['__manual__'] : null;
            if (!is_array($manual)) {
                $manual = [
                    'sub' => is_string($sub_link) ? trim($sub_link) : '',
                    'configs' => is_array($config) ? $config : [],
                ];
            }
            $hasSub = isset($manual['sub']) && trim((string) $manual['sub']) !== '';
            $validConfigs = isset($manual['configs']) && is_array($manual['configs']) ? array_values(array_filter($manual['configs'], function ($item) {
                return is_string($item) && trim($item) !== '';
            })) : [];
            return ['sub' => $hasSub, 'config' => !empty($validConfigs)];
        }
        $hasSub = (($panel_info['sublink'] ?? '') == "onsublink") && is_string($sub_link) && trim($sub_link) !== '';
        $validConfigs = is_array($config) ? array_values(array_filter($config, function ($item) {
            return is_string($item) && trim($item) !== '';
        })) : [];
        $hasConfig = (($panel_info['config'] ?? '') == "onconfig") && !empty($validConfigs);
        return ['sub' => $hasSub, 'config' => $hasConfig];
    }

    public static function rxBuildDeliveryOffer($panel_info, $invoice_id, $hasConfig, $hasSub, $reply_markup)
    {
        global $textbotlang;
        $mergedKb = json_decode((string) $reply_markup, true);
        if (!is_array($mergedKb) || !isset($mergedKb['inline_keyboard']) || !is_array($mergedKb['inline_keyboard'])) {
            $mergedKb = ['inline_keyboard' => []];
        }
        $offerRows = [];
        if ($hasConfig) {
            $offerRows[] = [['text' => "🔐 دریافت کانفیگ", 'callback_data' => "config_{$invoice_id}"]];
        }
        if ($hasSub && self::rxSubscriptionLinkButtonEnabled($panel_info)) {
            $offerRows[] = [['text' => "🔗 دریافت لینک اشتراک", 'callback_data' => "subscriptionurl_{$invoice_id}"]];
        }
        if (empty($offerRows)) {
            return null;
        }
        array_splice($mergedKb['inline_keyboard'], 0, 0, $offerRows);
        return json_encode($mergedKb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function rxAutoFilePanelRow($panel_info)
    {
        if (is_array($panel_info)) {
            if (array_key_exists('auto_file', $panel_info)) {
                return $panel_info;
            }
            $panel_name = trim((string) ($panel_info['name_panel'] ?? ''));
            $panel_code = trim((string) ($panel_info['code_panel'] ?? ''));
            if (\Faoxima\Core\Database\Db::runtimeReady()) {
                $fresh = $panel_name !== '' ? \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', $panel_name, 'select') : false;
                if (!is_array($fresh) && $panel_code !== '') {
                    $fresh = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'code_panel', $panel_code, 'select');
                }
                if (is_array($fresh)) {
                    return $fresh;
                }
            }
            return $panel_info;
        }
        if (method_exists(\Faoxima\Services\Panels\PanelFeatures::class, 'panel_feature_resolve_row')) {
            $row = \Faoxima\Services\Panels\PanelFeatures::panel_feature_resolve_row($panel_info);
            return is_array($row) ? $row : null;
        }
        if (\Faoxima\Core\Database\Db::runtimeReady()) {
            $row = \Faoxima\Core\Database\Db::select('marzban_panel', '*', 'name_panel', (string) $panel_info, 'select');
            return is_array($row) ? $row : null;
        }
        return null;
    }

    public static function rxPanelAutoFileEnabled($panel_info)
    {
        $row = self::rxAutoFilePanelRow($panel_info);
        return is_array($row) && (string) ($row['auto_file'] ?? 'offautofile') === 'onautofile';
    }

    public static function rxAutoFileKey($prefix, $value)
    {
        return hash('sha256', trim((string) $prefix) . '|' . (string) $value);
    }

    public static function rxAutoFileTempPath($public_name)
    {
        $ext = strtolower((string) pathinfo((string) $public_name, PATHINFO_EXTENSION));
        $ext = preg_match('/^[a-z0-9]{1,10}$/', $ext) ? '.' . $ext : '.tmp';
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rxauto-' . bin2hex(random_bytes(8)) . $ext;
    }

    public static function rxAutoFileResolveNaming($panel, $source, $key, $name, $caption, $ambiguous = false)
    {
        if (method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingResolve')) {
            return \Faoxima\Services\Delivery\FileNaming::rxFileNamingResolve($panel, $source, $key, $name, $caption, $ambiguous);
        }
        return ['filename' => (string) $name, 'caption' => (string) $caption];
    }

    public static function rxAutoFileCaptionProgress(array $row, string $caption): array
    {
        $detail = (string) ($row['detail'] ?? '');
        if (preg_match('/^caption_v1 (skipped|not_attempted|confirmed|failed|unknown) (\d+) ([a-f0-9]{64})$/D', $detail, $match)) {
            return hash_equals($match[3], hash('sha256', $caption))
                ? [$match[1], (int) $match[2]]
                : ['unknown', 0];
        }
        if (($row['status'] ?? '') === 'sent') {
            return [trim($caption) === '' && strpos($detail, 'caption=') === false ? 'skipped' : 'unknown', 0];
        }
        if (strpos($detail, 'caption=') !== false) {
            return ['unknown', 0];
        }
        return [trim($caption) === '' ? 'skipped' : 'not_attempted', 0];
    }

    public static function rxAutoFileCaptionMark($invoiceId, $fileKey, string $documentStatus, string $caption, string $state, int $cursor, ?PDO $pdo = null): bool
    {
        if (!($pdo instanceof PDO)) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }
        return \Faoxima\Services\Fulfillment\DeliveryState::autoFileDeliveryMark($pdo, $invoiceId, $fileKey, $documentStatus, 'caption_v1 ' . $state . ' ' . $cursor . ' ' . hash('sha256', $caption));
    }

    public static function rxAutoFileFinish($userId, $invoiceId, $fileKey, string $documentStatus, string $caption, string $captionState, int $cursor, ?bool $captionValid, string $source, $result, ?array &$detail, ?PDO $pdo = null): string
    {
        if (!($pdo instanceof PDO)) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }
        $textResult = null;
        if (in_array($documentStatus, ['sent', 'failed'], true) && trim($caption) !== ''
            && in_array($captionState, ['not_attempted', 'failed'], true)) {
            $checkpoint = static function (string $state, int $next) use ($invoiceId, $fileKey, $documentStatus, $caption, $pdo): bool {
                return self::rxAutoFileCaptionMark($invoiceId, $fileKey, $documentStatus, $caption, $state, $next, $pdo);
            };
            $text = rxSendRequiredMediaText(['chat_id' => $userId], $caption, null, $cursor, $checkpoint);
            $captionState = $text['state'];
            $cursor = $text['chunks_confirmed'];
            $textResult = $text['result'];
            $source = 'separate_text';
        }
        $documentState = $documentStatus === 'sent' ? 'confirmed' : $documentStatus;
        $captionRequired = trim($caption) !== '' || $captionState === 'unknown';
        $components = [
            'document' => ['required' => true, 'state' => $documentState],
            'caption' => ['required' => $captionRequired, 'state' => $captionState],
        ];
        if ($captionState !== 'confirmed' && $cursor > 0) {
            $components['caption_chunks'] = ['required' => true, 'state' => 'confirmed'];
        }
        $detail = \Faoxima\Services\Fulfillment\DeliveryState::deliveryComponentContext($components);
        $status = $detail['delivery_status'];
        $detail += [
            'state' => $status === 'completed' ? 'sent' : ($documentStatus === 'retry' ? 'retry' : ($status === 'unknown' ? 'unknown' : 'failed')),
            'caption_required' => $captionRequired,
            'caption_valid' => $captionValid,
            'caption_complete' => !$captionRequired || $captionState === 'confirmed',
            'caption_source' => $source,
            'caption_delivery_state' => $captionState,
            'caption_fallback_reason' => $source === 'separate_text' ? 'separate_caption_text' : null,
        ];
        if ($documentStatus === 'retry' && $status !== 'completed') {
            $detail['delivery_status'] = 'retry';
        }
        return $detail['state'];
    }

    public static function rxAutoFileLogFailure($invoiceId, $fileKey, string $stage, string $category): void
    {
        error_log('rxAutoFileDelivery invoice=' . substr((string) $invoiceId, 0, 191) . ' file=' . substr((string) $fileKey, 0, 64) . ' stage=' . $stage . ' category=' . $category);
    }

    public static function rxAutoFileSendDocument($user_id, $invoice_id, $file_key, $path, $public_name, $caption, ?array &$deliveryDetail = null, array $row = [], ?PDO $pdo = null)
    {
        if (!($pdo instanceof PDO)) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }
        $caption = (string) $caption;
        [$captionState, $cursor] = self::rxAutoFileCaptionProgress($row, $caption);
        $documentStatus = (string) ($row['status'] ?? '');
        if (in_array($documentStatus, ['sent', 'unknown', 'failed'], true)) {
            return self::rxAutoFileFinish($user_id, $invoice_id, $file_key, $documentStatus, $caption, $captionState, $cursor, null, 'stored_state', null, $deliveryDetail, $pdo);
        }
        if (!is_file($path) || !is_readable($path)) {
            self::rxAutoFileCaptionMark($invoice_id, $file_key, 'retry', $caption, $captionState, $cursor, $pdo);
            self::rxAutoFileLogFailure($invoice_id, $file_key, 'prepare_document', 'retry');
            return self::rxAutoFileFinish($user_id, $invoice_id, $file_key, 'retry', $caption, $captionState, $cursor, null, 'none', null, $deliveryDetail, $pdo);
        }
        $safeCaption = self::rxPrepareSafeMediaCaption($caption);
        $attach = $safeCaption !== null && $captionState === 'not_attempted' && $cursor === 0;
        $payload = [
            'chat_id' => $user_id,
            'document' => new CURLFile($path, 'application/octet-stream', $public_name),
            'caption' => $attach ? $safeCaption : '',
            'parse_mode' => 'HTML',
            '_rx_no_network_retry' => true,
        ];
        $result = null;
        $documentStatus = 'unknown';
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $pendingCaption = $attach ? 'unknown' : $captionState;
            if (!self::rxAutoFileCaptionMark($invoice_id, $file_key, 'unknown', $caption, $pendingCaption, $cursor, $pdo)) {
                $captionState = $pendingCaption;
                self::rxAutoFileLogFailure($invoice_id, $file_key, 'persist_state', 'unknown');
                break;
            }
            try {
                $result = method_exists(\Faoxima\Telegram\Rich\Dispatcher::class, 'rx_richUi_plainCall')
                    ? \Faoxima\Telegram\Rich\Dispatcher::rx_richUi_plainCall('sendDocument', $payload, null)
                    : telegram('sendDocument', $payload);
                $documentState = \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($result);
            } catch (Throwable $e) {
                $documentState = 'unknown';
                $result = null;
            }
            $failureKind = $documentState === 'confirmed' ? 'none' : \Faoxima\Services\Fulfillment\DeliveryState::deliveryFailureKind($result);
            $documentStatus = $documentState === 'confirmed' ? 'sent' : ($failureKind === 'retry' ? 'retry' : ($failureKind === 'permanent' ? 'failed' : 'unknown'));
            if ($documentStatus !== 'sent') {
                self::rxAutoFileLogFailure($invoice_id, $file_key, 'send_document', $documentStatus);
            }
            if ($attach && $documentState !== 'failed' && $failureKind !== 'retry') {
                $captionState = $documentState;
            }
            if (!self::rxAutoFileCaptionMark($invoice_id, $file_key, $documentStatus, $caption, $captionState, $cursor, $pdo)) {
                $deliveryDetail = \Faoxima\Services\Fulfillment\DeliveryState::deliveryComponentContext([
                    'document' => ['required' => true, 'state' => $documentState],
                    'caption' => ['required' => trim($caption) !== '', 'state' => $captionState],
                    'persistence' => ['required' => true, 'state' => 'unknown'],
                ]);
                self::rxAutoFileLogFailure($invoice_id, $file_key, 'persist_state', 'unknown');
                return 'unknown';
            }
            $retryAfter = $documentStatus === 'retry' && is_array($result) ? (int) ($result['parameters']['retry_after'] ?? 0) : 0;
            if ($retryAfter > 0) {
                throw new AutoFileRateLimit($retryAfter);
            }
            if (!$attach || !rxMediaCaptionRejected($result)) {
                break;
            }
            $attach = false;
            $payload['caption'] = '';
            $documentStatus = 'unknown';
        }
        return self::rxAutoFileFinish($user_id, $invoice_id, $file_key, $documentStatus, $caption, $captionState, $cursor, $safeCaption !== null, $attach ? 'document_caption' : 'none', $result, $deliveryDetail, $pdo);
    }

    public static function rxAutoFileSendContent($user_id, $invoice_id, $file_key, $content, $public_name, $caption, ?array &$deliveryDetail = null, array $row = [], ?PDO $pdo = null)
    {
        if (!($pdo instanceof PDO)) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }
        if (in_array($row['status'] ?? '', ['sent', 'unknown', 'failed'], true)) {
            return self::rxAutoFileSendDocument($user_id, $invoice_id, $file_key, '', $public_name, $caption, $deliveryDetail, $row, $pdo);
        }
        $temp = self::rxAutoFileTempPath($public_name);
        try {
            $path = file_put_contents($temp, (string) $content) === false ? '' : $temp;
            return self::rxAutoFileSendDocument($user_id, $invoice_id, $file_key, $path, $public_name, $caption, $deliveryDetail, $row, $pdo);
        } finally {
            @unlink($temp);
        }
    }

    public static function rxAutoFileRecord(array &$summary, string $status, array $detail = []): void
    {
        if (!isset($summary[$status])) {
            $summary[$status] = 0;
        }
        $summary[$status]++;
        $summary['total']++;
        $summary['file_states'][] = [
            'document_state' => $detail['document_state'] ?? ($status === 'sent' ? 'confirmed' : $status),
            'caption_state' => $detail['caption_state'] ?? 'not_attempted',
            'caption_required' => $detail['caption_required'] ?? false,
            'caption_chunks_state' => $detail['caption_chunks_state'] ?? 'not_attempted',
            'delivery_status' => $detail['delivery_status'] ?? ($status === 'sent' ? 'completed' : $status),
        ];
        $summary['ok'] = $summary['failed'] === 0 && $summary['unknown'] === 0 && ($summary['retry'] ?? 0) === 0;
    }

    public static function rxAutoFileIncludeUnresolved(array $summary, array $state, array $handled): array
    {
        foreach (array_diff_key($state, $handled) as $row) {
            $status = $row['status'] ?? '';
            $captionPending = preg_match('/^caption_v1 (?:not_attempted|failed|unknown) |(?:^| )caption=(?:failed|unknown) source=/', (string) ($row['detail'] ?? '')) === 1;
            if (!in_array($status, ['unknown', 'retry', 'failed'], true) && !$captionPending) {
                continue;
            }
            self::rxAutoFileRecord($summary, $status === 'retry' ? 'retry' : ($status === 'failed' ? 'failed' : 'unknown'), [
                'document_state' => $status === 'sent' ? 'confirmed' : $status,
                'caption_state' => $captionPending ? 'unknown' : 'not_attempted',
                'caption_required' => $captionPending,
                'delivery_status' => $status === 'retry' ? 'retry' : ($status === 'failed' ? 'failed' : 'unknown'),
            ]);
        }
        return $summary;
    }

    public static function rxAutoDeliverPurchaseFiles($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id, ?PDO $pdo = null)
    {
        $empty = ['ok' => true, 'sent' => 0, 'retry' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0];
        $panel = self::rxAutoFilePanelRow($panel_info);
        if (!self::rxPanelAutoFileEnabled($panel) || ($panel['type'] ?? '') === 'Manualsale') return $empty;
        if (trim((string) $invoice_id) === '' || trim((string) $user_id) === '') return $empty;
        if (!($pdo instanceof PDO)) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }
        if (!($pdo instanceof PDO)) throw new RuntimeException('Automatic file delivery database is unavailable');
        $lock = 'rxauto:' . substr(hash('sha256', (string) ($GLOBALS['dbname'] ?? '') . '|' . (string) $invoice_id), 0, 56);
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$lock]);
        $acquired = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ((string) $acquired !== '1') {
            $empty['ok'] = false;
            return $empty;
        }
        try {
            return self::rxAutoDeliverPurchaseFilesUnlocked($panel, $config, $sub_link, $username_service, $user_id, $invoice_id, $pdo);
        } finally {
            try {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$lock]);
                $release->closeCursor();
            } catch (Throwable $e) {
                error_log('Automatic file delivery lock release failed: ' . $e->getMessage());
            }
        }
    }

    public static function rxAutoDeliverPurchaseFilesUnlocked($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id, ?PDO $pdo = null)
    {
        global $ManagePanel;
        $result = ['ok' => true, 'sent' => 0, 'retry' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0];
        $panel = self::rxAutoFilePanelRow($panel_info);
        if (!self::rxPanelAutoFileEnabled($panel)) {
            return $result;
        }
        $user_id = trim((string) $user_id);
        $invoice_id = trim((string) $invoice_id);
        $username_service = trim((string) $username_service);
        if (!is_array($panel) || $user_id === '' || $invoice_id === '' || $username_service === '') {
            return $result;
        }
        if ((string) ($panel['type'] ?? '') === 'Manualsale') {
            return $result;
        }
        if (!($pdo instanceof PDO)) {
            $pdo = $GLOBALS['pdo'] ?? null;
        }
        if (!($pdo instanceof PDO)) {
            $result['ok'] = false;
            $result['unknown'] = 1;
            return $result;
        }
        $autoManager = is_object($ManagePanel) ? $ManagePanel : (class_exists('ManagePanel') ? new ManagePanel() : null);
        $state = \Faoxima\Services\Fulfillment\DeliveryState::autoFileDeliveryState($pdo, $invoice_id);
        if ((string) ($panel['type'] ?? '') === 'jovadpanel') {
            if (!method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadpanelAutoDeliverFiles')) {
                return self::rxAutoFileIncludeUnresolved($result, $state, []);
            }
            $fileStates = [];
            $handled = [];
            $deliver = static function ($fileKey, $path, $name, $caption) use ($state, $invoice_id, $user_id, &$fileStates, &$handled, $pdo) {
                $row = $state[$fileKey] ?? [];
                if ($path === null && !in_array($row['status'] ?? '', ['sent', 'unknown', 'failed'], true)) {
                    return null;
                }
                $status = self::rxAutoFileSendDocument($user_id, $invoice_id, $fileKey, $path ?? '', $name, $caption, $detail, $row, $pdo);
                $handled[$fileKey] = true;
                $fileStates[] = $detail;
                return $status;
            };
            $result = \Faoxima\Services\Panels\Drivers\JovadPanel::jovadpanelAutoDeliverFiles($user_id, $panel, $username_service, ['deliver_file' => $deliver]);
            $result['file_states'] = $fileStates;
            return self::rxAutoFileIncludeUnresolved($result, $state, $handled);
        }
        if ((string) ($panel['type'] ?? '') === 'WGDashboard') {
            $content = (string) $sub_link;
            if (trim($content) === '' && is_object($autoManager)) {
                $wgUser = $autoManager->DataUser($panel['name_panel'], $username_service);
                if (is_array($wgUser)) {
                    $content = (string) ($wgUser['subscription_url'] ?? '');
                }
            }
            if (trim($content) === '') {
                return self::rxAutoFileIncludeUnresolved($result, $state, []);
            }
            $defaultName = normalizeConfigFilename("{$panel['inboundid']}-{$username_service}.conf", $panel['namecustom'] ?? '', true);
            $key = method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingKeySubscription') ? \Faoxima\Services\Delivery\FileNaming::rxFileNamingKeySubscription($panel['inboundid'] ?? '') : '';
            $resolved = self::rxAutoFileResolveNaming($panel, 'wgdashboard', $key, $defaultName, '');
            $fileKey = self::rxAutoFileKey('wgdashboard', $content);
            $status = self::rxAutoFileSendContent($user_id, $invoice_id, $fileKey, $content, $resolved['filename'], $resolved['caption'], $detail, $state[$fileKey] ?? [], $pdo);
            self::rxAutoFileRecord($result, $status, $detail);
            return self::rxAutoFileIncludeUnresolved($result, $state, [$fileKey => true]);
        }
        $links = [];
        if (is_array($config)) {
            foreach ($config as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $links[] = (string) $item;
                }
            }
        } elseif (is_string($config) && trim($config) !== '') {
            $links[] = $config;
        }
        if ((string) ($panel['type'] ?? '') === 'x-ui_single' && trim((string) $sub_link) !== '') {
            $subContent = (string) $sub_link;
            if (preg_match('/\[Interface\]/i', $subContent) && preg_match('/\[Peer\]/i', $subContent)) {
                array_unshift($links, $subContent);
            }
        }
        $lazyDataUser = null;
        if (($panel['type'] ?? '') === 'x-ui_single' && is_object($autoManager)) {
            $hasFileSource = false;
            foreach ($links as $candidateLink) {
                if (preg_match('~^(?:xuifile|xuiawgfile|wireguard|wg|vpn|awg|amneziawg)://~i', trim($candidateLink))
                    || (preg_match('/^\h*\[Interface\]\h*\r?$/mi', $candidateLink) && preg_match('/^\h*\[Peer\]\h*\r?$/mi', $candidateLink))) {
                    $hasFileSource = true;
                    break;
                }
            }
            if (!$hasFileSource) {
                $lazyDataUser = $autoManager->DataUser($panel['name_panel'], $username_service);
                $singleFile = is_array($lazyDataUser) ? ($lazyDataUser['single_config_file'] ?? null) : null;
                if (is_array($singleFile) && trim((string) ($singleFile['value'] ?? '')) !== '') {
                    $scheme = ($singleFile['protocol'] ?? '') === 'amneziawg' ? 'xuiawgfile' : 'xuifile';
                    array_unshift($links, $scheme . '://' . rawurlencode((string) ($singleFile['filename'] ?? 'wireguard.conf')));
                }
            }
        }
        if (empty($links)) {
            return self::rxAutoFileIncludeUnresolved($result, $state, []);
        }
        $namingLinks = [];
        foreach ($links as $namingLink) {
            $namingLinks[] = ($panel['type'] ?? '') === 'x-ui_single'
                && preg_match('/^\h*\[Interface\]\h*\r?$/mi', $namingLink)
                && preg_match('/^\h*\[Peer\]\h*\r?$/mi', $namingLink)
                ? 'vpn://' . base64_encode($namingLink) : $namingLink;
        }
        $keyCounts = method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingLinkKeyCounts') ? \Faoxima\Services\Delivery\FileNaming::rxFileNamingLinkKeyCounts($namingLinks, $panel) : [];
        $deliveredKeys = [];
        foreach ($links as $index => $link) {
            $rawLink = (string) $link;
            $link = trim((string) $link);
            if ($link === '' || (method_exists(\Faoxima\Services\Panels\Drivers\JovadPanel::class, 'jovadIsProtocolMarker') && \Faoxima\Services\Panels\Drivers\JovadPanel::jovadIsProtocolMarker($link))) {
                continue;
            }
            $fileKey = '';
            $content = '';
            $publicName = '';
            $caption = '';
            if (($panel['type'] ?? '') === 'x-ui_single' && preg_match('/^\h*\[Interface\]\h*\r?$/mi', $rawLink) && preg_match('/^\h*\[Peer\]\h*\r?$/mi', $rawLink)) {
                $content = $rawLink;
                $protocol = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_protocol($content) ?? 'wireguard';
                if ($lazyDataUser === null && is_object($autoManager)) {
                    $lazyDataUser = $autoManager->DataUser($panel['name_panel'], $username_service);
                }
                $singleFile = is_array($lazyDataUser) ? ($lazyDataUser['single_config_file'] ?? null) : null;
                if (is_array($singleFile) && (string) ($singleFile['value'] ?? '') === $content) {
                    $defaultName = normalizeConfigFilename($singleFile['filename'] ?? 'wireguard.conf', '', true);
                    $namingKey = \Faoxima\Services\Delivery\FileNaming::rxFileNamingScopedKey(\Faoxima\Services\Delivery\FileNaming::rxFileNamingKeyFile($singleFile['filename'] ?? ''), $protocol);
                    $resolved = self::rxAutoFileResolveNaming($panel, 'xui_file', $namingKey, $defaultName, '');
                } else {
                    $encodedLink = 'vpn://' . base64_encode($content);
                    $descriptor = \Faoxima\Services\Delivery\FileNaming::rxFileNamingDescriptorFromLink($encodedLink);
                    $namingKey = is_array($descriptor) ? $descriptor['key'] : '';
                    $defaultName = normalizeConfigFilename(\Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_filename($encodedLink, $protocol, (int) $index), '', true);
                    $resolved = self::rxAutoFileResolveNaming($panel, 'xui_wg', $namingKey, $defaultName, '', $namingKey !== '' && (($keyCounts[$namingKey] ?? 0) > 1));
                }
                $publicName = $resolved['filename'];
                $caption = $resolved['caption'];
                $fileKey = self::rxAutoFileKey('config_content', $protocol . '|' . $content);
            } elseif (preg_match('~^(xuifile|xuiawgfile)://~i', $link, $fileMatch)) {
                if ($lazyDataUser === null && is_object($autoManager)) {
                    $lazyDataUser = $autoManager->DataUser($panel['name_panel'], $username_service);
                }
                $singleFile = is_array($lazyDataUser) ? ($lazyDataUser['single_config_file'] ?? null) : null;
                if (!is_array($singleFile) || trim((string) ($singleFile['value'] ?? '')) === '') {
                    continue;
                }
                $content = (string) $singleFile['value'];
                $protocol = (string) ($singleFile['protocol'] ?? (strtolower($fileMatch[1]) === 'xuiawgfile' ? 'amneziawg' : 'wireguard'));
                $defaultName = normalizeConfigFilename($singleFile['filename'] ?? 'config.conf', '', true);
                $namingKey = method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingScopedKey') && method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingKeyFile')
                    ? \Faoxima\Services\Delivery\FileNaming::rxFileNamingScopedKey(\Faoxima\Services\Delivery\FileNaming::rxFileNamingKeyFile($singleFile['filename'] ?? ''), $protocol)
                    : '';
                $resolved = self::rxAutoFileResolveNaming($panel, 'xui_file', $namingKey, $defaultName, '');
                $publicName = $resolved['filename'];
                $caption = $resolved['caption'];
                $fileKey = self::rxAutoFileKey('config_content', $protocol . '|' . $content);
            } elseif (method_exists(\Faoxima\Services\Panels\Drivers\XuiSingle::class, 'xui_wg_conf_from_link')) {
                $wg = \Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_from_link($link);
                if (is_array($wg) && isset($wg['conf']) && (string) $wg['conf'] !== '') {
                    $content = (string) $wg['conf'];
                    $protocol = (string) ($wg['protocol'] ?? 'wireguard');
                    $defaultName = normalizeConfigFilename(\Faoxima\Services\Panels\Drivers\XuiSingle::xui_wg_conf_filename($link, $protocol, (int) $index), '', true);
                    $descriptor = method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingDescriptorFromLink') ? \Faoxima\Services\Delivery\FileNaming::rxFileNamingDescriptorFromLink($link) : null;
                    $namingKey = is_array($descriptor) ? (string) ($descriptor['key'] ?? '') : '';
                    $ambiguous = $namingKey !== '' && (($keyCounts[$namingKey] ?? 0) > 1);
                    $resolved = self::rxAutoFileResolveNaming($panel, 'xui_wg', $namingKey, $defaultName, '', $ambiguous);
                    $publicName = $resolved['filename'];
                    $caption = $resolved['caption'];
                    $fileKey = self::rxAutoFileKey('config_content', $protocol . '|' . $content);
                }
            }
            if ($fileKey === '' && method_exists(\Faoxima\Services\Panels\Drivers\Rebecca::class, 'rebeccaConfigDownloadInfo')) {
                $rebeccaInfo = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaConfigDownloadInfo($link);
                if (is_array($rebeccaInfo)) {
                    $descriptor = method_exists(\Faoxima\Services\Delivery\FileNaming::class, 'rxFileNamingDescriptorFromLink') ? \Faoxima\Services\Delivery\FileNaming::rxFileNamingDescriptorFromLink($link) : null;
                    $namingKey = is_array($descriptor) ? (string) ($descriptor['key'] ?? '') : '';
                    $ambiguous = $namingKey !== '' && (($keyCounts[$namingKey] ?? 0) > 1);
                    $defaultName = normalizeConfigFilename($rebeccaInfo['filename'], '', ($rebeccaInfo['kind'] ?? '') === 'wireguard');
                    $resolved = self::rxAutoFileResolveNaming($panel, 'rebecca', $namingKey, $defaultName, '', $ambiguous);
                    $fileKey = self::rxAutoFileKey('rebecca', (string) ($rebeccaInfo['kind'] ?? '') . '|' . (string) ($rebeccaInfo['url'] ?? ''));
                    if (isset($deliveredKeys[$fileKey])) {
                        $result['skipped']++;
                        continue;
                    }
                    $deliveredKeys[$fileKey] = true;
                    $row = $state[$fileKey] ?? [];
                    if (in_array($row['status'] ?? '', ['sent', 'unknown', 'failed'], true)) {
                        $status = self::rxAutoFileSendDocument($user_id, $invoice_id, $fileKey, '', $resolved['filename'], $resolved['caption'], $detail, $row, $pdo);
                        self::rxAutoFileRecord($result, $status, $detail);
                        continue;
                    }
                    $temp = self::rxAutoFileTempPath($resolved['filename']);
                    try {
                        $downloaded = \Faoxima\Services\Panels\Drivers\Rebecca::rebeccaDownloadConfigFile($rebeccaInfo['url'], $temp);
                        $status = self::rxAutoFileSendDocument($user_id, $invoice_id, $fileKey, $downloaded ? $temp : '', $resolved['filename'], $resolved['caption'], $detail, $row, $pdo);
                        self::rxAutoFileRecord($result, $status, $detail);
                    } finally {
                        @unlink($temp);
                    }
                    continue;
                }
            }
            if ($fileKey === '') {
                continue;
            }
            if ($content !== '') {
                $legacyKeys = [self::rxAutoFileKey('xui_wg', $content)];
                $previousSingleFile = is_array($lazyDataUser) ? ($lazyDataUser['single_config_file'] ?? null) : null;
                if (is_array($previousSingleFile) && (string) ($previousSingleFile['value'] ?? '') === $content) {
                    $legacyKeys[] = self::rxAutoFileKey('xui_file', $protocol . '|' . (string) ($previousSingleFile['filename'] ?? '') . '|' . $content);
                }
                foreach ($legacyKeys as $legacyKey) {
                    if (!in_array($state[$fileKey]['status'] ?? '', ['sent', 'unknown'], true)
                        && in_array($state[$legacyKey]['status'] ?? '', ['sent', 'unknown'], true)) {
                        $fileKey = $legacyKey;
                    }
                }
            }
            if (isset($deliveredKeys[$fileKey])) {
                $result['skipped']++;
                continue;
            }
            $deliveredKeys[$fileKey] = true;
            $status = self::rxAutoFileSendContent($user_id, $invoice_id, $fileKey, $content, $publicName, $caption, $detail, $state[$fileKey] ?? [], $pdo);
            self::rxAutoFileRecord($result, $status, $detail);
        }
        return self::rxAutoFileIncludeUnresolved($result, $state, $deliveredKeys);
    }

    public static function rxAutoDeliverPurchaseFilesSafe($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id, ?PDO $pdo = null)
    {
        try {
            $summary = self::rxAutoDeliverPurchaseFiles($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id, $pdo);
        } catch (Throwable $e) {
            error_log('rxAutoDeliverPurchaseFiles failed: ' . get_class($e));
            $summary = ['ok' => false, 'sent' => 0, 'retry' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0];
        }
        $confirmed = (int) $summary['sent'];
        foreach ($summary['file_states'] ?? [] as $file) {
            if (($file['delivery_status'] ?? '') === 'partial') {
                $confirmed++;
            }
        }
        $hasRetry = (int) ($summary['retry'] ?? 0) > 0;
        $summary['delivery_status'] = $summary['ok'] ? 'completed'
            : (($summary['unknown'] ?? 0) > 0 || $hasRetry || ($summary['failed'] ?? 0) === 0 ? 'unknown' : ($confirmed > 0 ? 'partial' : 'failed'));
        $rxNotifyUser = trim((string) $user_id);
        if ($rxNotifyUser !== '' && in_array((string) $summary['delivery_status'], ['failed', 'partial', 'unknown'], true)) {
            try {
                $rxNoticeKey = in_array((string) $summary['delivery_status'], ['failed', 'partial'], true)
                    ? 'service_files_incomplete_contact_support'
                    : 'service_files_pending_retry';
                sendmessage($rxNotifyUser, \Faoxima\Telegram\Support\MessageTemplates::get($rxNoticeKey), null, 'HTML');
            } catch (Throwable $e) {
            }
        }
        return $summary;
    }

    public static function rxPrepareSafeMediaCaption(string $html): ?string
    {
        $caption = trim($html);
        if ($caption === '') {
            return '';
        }
        if (preg_match('~<table\b~i', $caption) === 1) {
            if (!method_exists(\Faoxima\Telegram\Rich\Markup::class, 'rx_richUi_replaceTablesWithText')) {
                return null;
            }
            $caption = (string) \Faoxima\Telegram\Rich\Markup::rx_richUi_replaceTablesWithText($caption);
            if (preg_match('~</?table\b~i', $caption) === 1) {
                return null;
            }
        }
        if (method_exists(\Faoxima\Telegram\Rich\Markup::class, 'rx_richUi_stripRtlMarker')) {
            $caption = \Faoxima\Telegram\Rich\Markup::rx_richUi_stripRtlMarker($caption);
        }
        $caption = preg_replace('~<br\s*/?>~i', "\n", $caption);
        $caption = preg_replace('~</p\s*>~i', "\n", $caption);
        $caption = preg_replace('~<p\b[^>]*>~i', '', $caption);
        $caption = trim((string) $caption);
        if (!mb_check_encoding($caption, 'UTF-8')) {
            return null;
        }
        $allowed = ['b', 'strong', 'i', 'em', 'u', 'ins', 's', 'strike', 'del', 'code', 'pre', 'mark', 'sub', 'sup', 'tg-spoiler', 'tg-emoji', 'a', 'tg-time', 'br', 'p', 'blockquote'];
        if (preg_match_all('~<\s*/?\s*([a-z][a-z0-9-]*)\b~i', $caption, $tags)) {
            foreach ($tags[1] as $tag) {
                if (!in_array(strtolower($tag), $allowed, true)) {
                    return null;
                }
            }
        }
        if (preg_match_all('~<tg-emoji\b([^>]*)>~i', $caption, $emojiTags)) {
            foreach ($emojiTags[1] as $attributes) {
                if (!preg_match('~emoji-id\s*=\s*"\d+"~i', $attributes)) {
                    return null;
                }
            }
        }
        $visible = html_entity_decode(strip_tags($caption), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        if (trim($visible) === '' || strlen(mb_convert_encoding($visible, 'UTF-16LE', 'UTF-8')) > 2048) {
            return null;
        }
        return $caption;
    }

    public static function rxServiceDeliveryStatus(array $components): string
    {
        $deliveryStatus = \Faoxima\Services\Fulfillment\DeliveryState::deliveryAggregate(array_values($components));
        $summary = \Faoxima\Services\Fulfillment\DeliveryState::deliveryComponentContext($components);
        if ($deliveryStatus === 'completed' && empty($summary['required_components']) && empty($summary['confirmed_components'])) {
            $deliveryStatus = 'failed';
        }
        return $deliveryStatus;
    }

    public static function sendMessageService($panel_info, $config, $sub_link, $username_service, $reply_markup, $caption, $invoice_id, $user_id = null, $image = 'images.jpg', $auto_files = false, ?array &$deliveryResult = null)
    {
        global $setting, $from_id;
        $config = self::normalizeServiceConfigs($config);
        if (!\Faoxima\Telegram\Support\Transport::check_active_btn($setting['keyboardmain'], "text_help"))
            $reply_markup = null;
        $user_id = $user_id == null ? $from_id : $user_id;

        $rxDeliveryComponents = [
            'text' => ['required' => false, 'state' => 'not_attempted'],
            'qr' => ['required' => false, 'state' => 'not_attempted'],
            'card' => ['required' => false, 'state' => 'not_attempted'],
            'files' => ['required' => false, 'state' => 'not_attempted'],
        ];
        $rxSetDelivery = static function (string $name, string $state, bool $required = true) use (&$rxDeliveryComponents): void {
            if (!isset($rxDeliveryComponents[$name]) || !is_array($rxDeliveryComponents[$name])) {
                $rxDeliveryComponents[$name] = ['required' => $required, 'state' => $state];
                return;
            }
            $rxDeliveryComponents[$name]['required'] = $required;
            $rxDeliveryComponents[$name]['state'] = $state;
        };
        $rxSendServiceText = static function ($keyboard) use ($user_id, &$cleanCaption, $rxSetDelivery): array {
            $result = sendmessage($user_id, $cleanCaption, $keyboard, 'HTML');
            $state = \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($result);
            $chunks = (int) ($result['_rx_confirmed_text_chunks'] ?? 0);
            if ($chunks === 0 && (rxMediaCaptionRejected($result) || !empty($result['_rx_validation_failed']) || ($result['error_code'] ?? null) === 'RICH_VALIDATION_FAILED')) {
                $fallback = rxSendRequiredMediaText(['chat_id' => $user_id, 'reply_markup' => $keyboard], $cleanCaption);
                $result = $fallback['result'];
                $state = $fallback['state'];
                $chunks = $fallback['chunks_confirmed'];
            }
            if ($state !== 'confirmed' && $chunks > 0) {
                $rxSetDelivery('text_chunks', 'confirmed');
            }
            return [$result, $state, $chunks];
        };

        $rxAutoFilesSummary = null;
        $rxDeliveryDone = static function () use (&$rxDeliveryComponents, &$deliveryResult) {
            $deliveryStatus = self::rxServiceDeliveryStatus($rxDeliveryComponents);
            $deliveryResult = ['ok' => $deliveryStatus === 'completed', 'status' => $deliveryStatus];
        };
        $rxAutoFilesAttempt = static function () use ($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id, &$rxSetDelivery, &$rxAutoFilesSummary): void {
            $rxAutoFilesSummary = ['ok' => false, 'sent' => 0, 'retry' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0, 'total' => 0, 'delivery_status' => 'unknown', 'state' => 'unknown', 'counts' => []];
            if (!method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'rxAutoDeliverPurchaseFilesSafe')) {
                $rxAutoFilesSummary['delivery_status'] = 'skipped';
                $rxAutoFilesSummary['state'] = 'skipped';
                $rxSetDelivery('files', 'skipped', false);
                return;
            }
            $rxDeliveryPdo = $GLOBALS['pdo'] ?? null;
            $rxAutoFilesResult = self::rxAutoDeliverPurchaseFilesSafe($panel_info, $config, $sub_link, $username_service, $user_id, $invoice_id, $rxDeliveryPdo instanceof PDO ? $rxDeliveryPdo : null);
            $rxAutoFilesSummary = is_array($rxAutoFilesResult) ? $rxAutoFilesResult : [];
            $rxSent = (int) ($rxAutoFilesSummary['sent'] ?? 0);
            $rxRetry = (int) ($rxAutoFilesSummary['retry'] ?? 0);
            $rxFailed = (int) ($rxAutoFilesSummary['failed'] ?? 0);
            $rxUnknown = (int) ($rxAutoFilesSummary['unknown'] ?? 0);
            $rxSkipped = (int) ($rxAutoFilesSummary['skipped'] ?? 0);
            $rxTotal = (int) ($rxAutoFilesSummary['total'] ?? 0);
            $rxAutoFilesSummary['counts'] = [
                'sent' => $rxSent,
                'retry' => $rxRetry,
                'failed' => $rxFailed,
                'unknown' => $rxUnknown,
                'skipped' => $rxSkipped,
                'total' => $rxTotal,
            ];
            $rxFileStates = $rxAutoFilesSummary['file_states'] ?? [];
            foreach ($rxFileStates as $index => $file) {
                $rxSetDelivery('auto_document_' . $index, $file['document_state']);
                if (!empty($file['caption_required'])) {
                    $rxSetDelivery('auto_caption_' . $index, $file['caption_state']);
                }
                if (($file['caption_chunks_state'] ?? '') === 'confirmed') {
                    $rxSetDelivery('auto_caption_chunks_' . $index, 'confirmed');
                }
                if (in_array(($file['delivery_status'] ?? ''), ['unknown', 'retry'], true)) {
                    $rxSetDelivery('auto_confirmation_' . $index, 'unknown');
                }
            }
            $unreported = $rxTotal > count($rxFileStates) || (empty($rxAutoFilesSummary['ok']) && $rxFailed + $rxUnknown + $rxRetry === 0);
            if ($unreported) {
                $rxSetDelivery('files', $rxUnknown + $rxRetry > 0 || $rxFailed === 0 ? 'unknown' : 'failed');
            }
        };

        if (($panel_info['type'] ?? '') == "Manualsale") {
            $manual = \Faoxima\Services\Fulfillment\DeliveryState::manualsaleDelivery($username_service);
            if ($manual['content'] === "" && is_string($sub_link) && trim($sub_link) !== '') {
                $manual['configs'] = [$sub_link];
            }
            $items = isset($manual['items']) && is_array($manual['items']) ? $manual['items'] : [];
            $fileItems = [];
            $textItems = [];
            foreach ($items as $it) {
                if (!empty($it['is_file'])) {
                    $fileItems[] = $it;
                } elseif (trim((string) ($it['content'] ?? '')) !== '') {
                    $textItems[] = $it;
                }
            }
            $cleanCaption = self::rxStripDeliveryContentFromCaption($caption);
            $hasSubOffer = self::rxDeliveryAvailability($panel_info, ['__manual__' => $manual], $manual['sub'])['sub'];
            $hasConfigOffer = !empty($textItems);

            if (!empty($fileItems)) {
                $fileKeyboard = self::rxBuildDeliveryOffer($panel_info, $invoice_id, $hasConfigOffer, $hasSubOffer, $reply_markup) ?? $reply_markup;
                self::rxManualsaleSendFileItems($user_id, $username_service, $fileItems, $cleanCaption, $fileKeyboard, 0, 0, $fileDelivery);
                foreach ($fileDelivery['files'] as $index => $item) {
                    $rxSetDelivery('file_' . $index, $item['state']);
                }
                foreach ($fileDelivery['text'] as $index => $item) {
                    $rxSetDelivery('file_caption_' . $index, $item['state']);
                }
                if ($fileDelivery['keyboard_state'] === 'failed') {
                    $keyboardResult = sendmessage($user_id, \Faoxima\Telegram\Support\MessageTemplates::get('service_files_incomplete_contact_support'), $fileKeyboard, 'HTML');
                    $rxSetDelivery('file_keyboard_notice', \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($keyboardResult), false);
                }
                $rxDeliveryDone();
                return;
            }

            $availability = self::rxDeliveryAvailability($panel_info, ['__manual__' => $manual], $manual['sub']);
            $offerKeyboard = self::rxBuildDeliveryOffer($panel_info, $invoice_id, $availability['config'], $availability['sub'], $reply_markup);
            if ($offerKeyboard === null) {
                $rxManualPlainResult = sendmessage($user_id, $cleanCaption, $reply_markup, 'HTML');
                $rxSetDelivery('text', \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxManualPlainResult), true);
                $rxDeliveryDone();
                return;
            }
            $rxManualOfferResult = sendmessage($user_id, $cleanCaption, $offerKeyboard, 'HTML');
            $rxSetDelivery('text', \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxManualOfferResult), true);
            $rxDeliveryDone();
            return;
        }

        $availability = self::rxDeliveryAvailability($panel_info, $config, $sub_link);
        $cleanCaption = self::rxStripDeliveryContentFromCaption($caption);
        $cleanCaption = self::rxAppendSubscriptionLinkLine($cleanCaption, $availability['sub'] ? $sub_link : '', $panel_info);
        $offerKeyboard = self::rxBuildDeliveryOffer($panel_info, $invoice_id, $availability['config'], $availability['sub'], $reply_markup);
        $finalKeyboard = $offerKeyboard === null ? $reply_markup : $offerKeyboard;
        $rxServiceCaptionHasTable = is_string($cleanCaption) && preg_match('~<table\b~i', $cleanCaption) === 1;
        $rxSafeMediaCaption = method_exists(\Faoxima\Services\Delivery\ServiceDelivery::class, 'rxPrepareSafeMediaCaption') ? self::rxPrepareSafeMediaCaption($cleanCaption) : $cleanCaption;
        $rxServiceCaptionNeedsSplit = $rxSafeMediaCaption === null || $rxServiceCaptionHasTable;
        $rxTextRequired = trim((string) $cleanCaption) !== '';
        $rxServiceCaptionDelivered = false;
        $rxQrKeyboardDelivered = false;
        if (!$rxTextRequired) {
            $rxSetDelivery('text', 'skipped', false);
        }

        if (method_exists(\Faoxima\Telegram\Support\InfoCards::class, 'nm_renderInfoCardForInvoice')) {
            $cardPath = \Faoxima\Telegram\Support\InfoCards::nm_renderInfoCardForInvoice($panel_info, $username_service, $invoice_id, $user_id);
            if ($cardPath !== null) {
                $kb = json_decode((string) $finalKeyboard, true);
                if (!is_array($kb) || !isset($kb['inline_keyboard']) || !is_array($kb['inline_keyboard'])) {
                    $kb = ['inline_keyboard' => []];
                }
                if (!method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') || !\Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled()) {
                    array_unshift($kb['inline_keyboard'], [['text' => \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_purchase_qr_code_btn', '📷 دریافت QR Code'), 'callback_data' => 'infocard_qr_' . $invoice_id]]);
                }
                $cardKeyboard = json_encode($kb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $rxCardPhotoResult = null;
                $rxCardTextResult = null;
                $rxCardTextState = 'not_attempted';
                $rxCardTextAttempted = false;
                try {
                    if ($rxServiceCaptionNeedsSplit) {
                        $rxCardPhotoResult = telegram('sendphoto', [
                            'chat_id' => $user_id,
                            'photo'   => new CURLFile($cardPath),
                        ]);
                        $rxCardPhotoState = \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxCardPhotoResult);
                        $rxSetDelivery('card', $rxCardPhotoState, true);
                        @unlink($cardPath);
                        $rxCardTextAttempted = true;
                        [$rxCardTextResult, $rxCardTextState, $rxCardTextChunks] = $rxSendServiceText($cardKeyboard);
                        $rxSetDelivery('text', $rxCardTextState, true);
                        $rxServiceCaptionDelivered = $rxCardTextState === 'confirmed';
                        $rxCardQrState = 'not_attempted';
                        if ($rxCardTextState === 'failed' && $rxCardTextChunks === 0 && method_exists(\Faoxima\Telegram\Support\InfoCards::class, 'nm_sendServiceQrFallback') && is_string($sub_link) && trim($sub_link) !== '') {
                            $rxCardQrDetail = null;
                            $rxCardQrFallback = \Faoxima\Telegram\Support\InfoCards::nm_sendServiceQrFallback($user_id, $sub_link, $image, '', $cardKeyboard, $rxCardQrDetail);
                            $rxCardQrState = is_array($rxCardQrDetail) ? (string) ($rxCardQrDetail['state'] ?? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxCardQrFallback)) : \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxCardQrFallback);
                            $rxSetDelivery('qr', $rxCardQrState, $rxCardQrState !== 'skipped');
                        }
                        if ($auto_files) {
                            $rxAutoFilesAttempt();
                        }
                        $rxDeliveryDone();
                        return;
                    }
                    $rxCardResult = rxSendMediaWithCaption('sendphoto', [
                        'chat_id'      => $user_id,
                        'photo'        => new CURLFile($cardPath),
                        'caption'      => $cleanCaption,
                        'parse_mode'   => 'HTML',
                        'reply_markup' => $cardKeyboard,
                    ], null, $rxCardDelivery);
                    $rxCardState = $rxCardDelivery['media_state'];
                    $rxSetDelivery('card', $rxCardState, true);
                    if ($rxTextRequired) {
                        $rxSetDelivery('text', $rxCardDelivery['text_state'], true);
                        if ($rxCardDelivery['text_state'] !== 'confirmed' && $rxCardDelivery['text_chunks_confirmed'] > 0) {
                            $rxSetDelivery('text_chunks', 'confirmed');
                        }
                    } else {
                        $rxSetDelivery('text', 'skipped', false);
                    }
                    @unlink($cardPath);
                    if ($rxCardState === 'confirmed' || ($rxCardDelivery['caption_source'] === 'separate_text'
                        && ($rxCardDelivery['text_state'] !== 'failed' || $rxCardDelivery['text_chunks_confirmed'] > 0))) {
                        $rxServiceCaptionDelivered = $rxCardDelivery['caption_complete'];
                        if ($auto_files) {
                            $rxAutoFilesAttempt();
                        }
                        $rxDeliveryDone();
                        return;
                    }
                    if ($rxCardState === 'unknown') {
                        if ($auto_files) {
                            $rxAutoFilesAttempt();
                        }
                        $rxDeliveryDone();
                        return;
                    }
                } catch (Throwable $e) {
                    $rxCatchCardState = isset($rxCardResult) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxCardResult) : (isset($rxCardPhotoResult) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxCardPhotoResult) : 'failed');
                    $rxCatchTextState = isset($rxCardTextResult) ? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxCardTextResult) : 'not_attempted';
                    if ($rxCatchTextState === 'not_attempted') {
                        $rxCatchTextState = $rxCatchCardState === 'failed' ? 'failed' : 'unknown';
                    }
                    $rxSetDelivery('card', $rxCatchCardState, true);
                    $rxSetDelivery('text', $rxCatchTextState, true);
                    @unlink($cardPath);
                    if ($rxCardTextAttempted && $rxCardTextResult === null) {
                        if ($auto_files) {
                            $rxAutoFilesAttempt();
                        }
                        $rxDeliveryDone();
                        return;
                    }
                    error_log('sendMessageService info card send failed: ' . get_class($e));
                }
            } else {
                $rxSetDelivery('card', 'skipped', false);
            }
        } else {
            $rxSetDelivery('card', 'skipped', false);
        }
        if (method_exists(\Faoxima\Telegram\Support\InfoCards::class, 'nm_sendServiceQrFallback') && is_string($sub_link) && trim($sub_link) !== '') {
                if ($rxServiceCaptionNeedsSplit) {
                    $rxQrDetail = null;
                    $rxQrDelivered = \Faoxima\Telegram\Support\InfoCards::nm_sendServiceQrFallback($user_id, $sub_link, $image, '', null, $rxQrDetail);
                    $rxQrState = is_array($rxQrDetail) ? (string) ($rxQrDetail['state'] ?? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxQrDelivered)) : \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxQrDelivered);
                    $rxSetDelivery('qr', $rxQrState, $rxQrState !== 'skipped');
                    try {
                        [$rxQrTextResult, $rxQrTextState, $rxQrTextChunks] = $rxSendServiceText($finalKeyboard);
                    } catch (Throwable $e) {
                        $rxQrTextResult = null;
                        $rxQrTextState = 'unknown';
                        $rxQrTextChunks = 0;
                        error_log('sendMessageService QR text send failed: ' . get_class($e));
                    }
                    $rxSetDelivery('text', $rxQrTextState, true);
                    $rxServiceCaptionDelivered = $rxQrTextState === 'confirmed';
                    $rxQrKeyboardMessageId = (int) ($rxQrDetail['message_id'] ?? 0);
                    if ($rxQrTextState === 'failed' && $rxQrTextChunks === 0 && $rxQrState === 'confirmed' && $rxQrKeyboardMessageId > 0 && !rxIsEmptyInlineKeyboard($finalKeyboard)) {
                        telegram('editMessageReplyMarkup', [
                            'chat_id' => $user_id,
                            'message_id' => $rxQrKeyboardMessageId,
                            'reply_markup' => $finalKeyboard,
                        ]);
                    }
                    if ($auto_files) {
                        $rxAutoFilesAttempt();
                    }
                    $rxDeliveryDone();
                    return;
                }
                $rxQrDetail = null;
                $rxQrCaption = $cleanCaption;
                $rxQrDelivered = \Faoxima\Telegram\Support\InfoCards::nm_sendServiceQrFallback($user_id, $sub_link, $image, $rxQrCaption, $finalKeyboard, $rxQrDetail);
                $rxQrState = is_array($rxQrDetail) ? (string) ($rxQrDetail['state'] ?? \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxQrDelivered)) : \Faoxima\Services\Fulfillment\DeliveryState::deliveryResultState($rxQrDelivered);
                $rxQrCaptionComplete = $rxTextRequired && ($rxQrDetail['caption_delivery_state'] ?? null) === 'confirmed';
                $rxQrCaptionSeparate = ($rxQrDetail['caption_source'] ?? '') === 'separate_text';
                if ($rxQrState === 'confirmed') {
                    $rxQrKeyboardDelivered = true;
                }
                if ($rxQrState === 'skipped') {
                    $rxSetDelivery('qr', 'skipped', false);
                    if ($rxTextRequired) {
                        $rxSetDelivery('text', 'not_attempted', true);
                    }
                } else {
                    $rxSetDelivery('qr', $rxQrState, true);
                    if (!$rxTextRequired) {
                        $rxSetDelivery('text', 'skipped', false);
                    } elseif ($rxQrCaptionComplete) {
                        $rxSetDelivery('text', 'confirmed', true);
                        $rxServiceCaptionDelivered = true;
                    } elseif ($rxQrCaptionSeparate) {
                        $rxSetDelivery('text', $rxQrDetail['caption_delivery_state'], true);
                        if ($rxQrDetail['text_chunks_confirmed'] > 0) {
                            $rxSetDelivery('text_chunks', 'confirmed');
                        }
                    } elseif ($rxQrState === 'unknown') {
                        $rxSetDelivery('text', 'unknown', true);
                    } else {
                        $rxSetDelivery('text', 'not_attempted', true);
                    }
                }
                if ($rxQrState === 'confirmed' || ($rxQrCaptionSeparate
                    && ($rxQrDetail['caption_delivery_state'] !== 'failed' || $rxQrDetail['text_chunks_confirmed'] > 0))) {
                    if (!$rxTextRequired) {
                        $rxSetDelivery('text', 'skipped', false);
                    }
                    $rxServiceCaptionDelivered = !$rxTextRequired || $rxQrCaptionComplete;
                    if ($auto_files) {
                        $rxAutoFilesAttempt();
                    }
                    $rxDeliveryDone();
                    return;
                }
                if ($rxQrState === 'unknown') {
                    if ($auto_files) {
                        $rxAutoFilesAttempt();
                    }
                    $rxDeliveryDone();
                    return;
                }
            } else {
                $rxSetDelivery('qr', 'skipped', false);
            }

        if ($rxTextRequired && !$rxServiceCaptionDelivered) {
            try {
                [$rxServiceTextResult, $rxServiceTextState] = $rxSendServiceText($rxQrKeyboardDelivered ? null : $finalKeyboard);
            } catch (Throwable $e) {
                $rxServiceTextResult = null;
                $rxServiceTextState = 'unknown';
                error_log('sendMessageService text send failed: ' . get_class($e));
            }
            $rxSetDelivery('text', $rxServiceTextState, true);
            $rxServiceCaptionDelivered = $rxServiceTextState === 'confirmed';
        }
        if ($auto_files) {
            $rxAutoFilesAttempt();
        }
        $rxDeliveryDone();
    }
}
