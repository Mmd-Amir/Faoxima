<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Marzban.php';
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/PasarGuard.php';
require_once __DIR__ . '/x-ui_single.php';
require_once __DIR__ . '/WGDashboard.php';
require_once __DIR__ . '/remnawave.php';
require_once __DIR__ . '/rebecca.php';
require_once __DIR__ . '/jovadpanel.php';
require_once __DIR__ . '/panel_capability.php';

if (!function_exists('rx_panel_http_status_fa')) {
    function rx_panel_http_status_fa($code)
    {
        $code = (int) $code;
        $map = [
            0   => 'اتصال به پنل برقرار نشد؛ پنل خاموش است، آدرس اشتباه است یا فایروال/SSL جلوی اتصال را گرفته',
            400 => 'درخواست نامعتبر؛ پنل داده‌های ارسالی ربات را نپذیرفت',
            401 => 'احراز هویت ناموفق؛ API Key یا نام کاربری/رمز پنل در ربات اشتباه یا منقضی است',
            403 => 'دسترسی غیرمجاز؛ ادمین پنل غیرفعال است، مجوز ساخت کاربر ندارد یا به سقف حجم/تعداد کاربر رسیده',
            404 => 'پیدا نشد؛ آدرس پنل، مسیر API یا گروه/اینباند انتخاب‌شده روی پنل وجود ندارد',
            405 => 'متد مجاز نیست؛ احتمالاً نوع یا نسخهٔ پنل در ربات اشتباه انتخاب شده',
            408 => 'پنل در زمان مقرر پاسخ نداد (Timeout)',
            409 => 'نام کاربری تکراری است؛ این یوزرنیم از قبل روی پنل وجود دارد',
            413 => 'حجم درخواست بیش از حد مجاز پنل است',
            422 => 'دادهٔ نامعتبر؛ فرمت نام کاربری، گروه/اینباند، حجم یا تاریخ انقضا مورد قبول پنل نیست',
            429 => 'تعداد درخواست‌ها زیاد است؛ پنل موقتاً درخواست‌ها را محدود کرده',
            500 => 'خطای داخلی پنل؛ لاگ پنل را بررسی کنید',
            502 => 'Bad Gateway؛ وب‌سرور/پروکسی جلوی پنل به خود پنل دسترسی ندارد',
            503 => 'سرویس پنل در دسترس نیست؛ پنل در حال ری‌استارت یا از کار افتاده است',
            504 => 'Gateway Timeout؛ پنل دیر پاسخ داد',
        ];
        if (isset($map[$code])) {
            return $map[$code];
        }
        if ($code >= 520 && $code <= 530) {
            return 'خطای Cloudflare؛ سرور پنل پشت Cloudflare پاسخ نداد یا در دسترس نیست';
        }
        if ($code >= 500) {
            return 'خطای سمت سرور پنل';
        }
        if ($code >= 400) {
            return 'پنل درخواست را رد کرد';
        }
        if ($code >= 300) {
            return 'ریدایرکت؛ آدرس پنل اشتباه است (http/https یا مسیر را بررسی کنید)';
        }
        if ($code >= 200) {
            return 'پنل عملیات را با موفقیت انجام داد اما پاسخ آن با انتظار ربات جور نبود؛ نوع پنل انتخاب‌شده در ربات را بررسی کنید';
        }
        return '';
    }
}

if (!function_exists('rx_panel_error_hint_fa')) {
    function rx_panel_error_hint_fa($text)
    {
        $text = (string) $text;
        $patterns = [
            '/already\s+exist|duplicate|\bexists\b|تکراری/iu'              => 'نام کاربری تکراری است؛ این یوزرنیم از قبل روی پنل وجود دارد',
            '/timed?\s*out|timeout/i'                                     => 'پنل در زمان مقرر پاسخ نداد (Timeout)',
            '/could not resolve host|name or service not known/i'         => 'دامنهٔ پنل پیدا نشد (خطای DNS)',
            '/connection refused|failed to connect|couldn\'t connect/i'   => 'اتصال به پنل رد شد؛ پنل خاموش است یا پورت بسته است',
            '/ssl|certificate/i'                                          => 'خطای SSL؛ گواهی پنل نامعتبر یا منقضی است',
            '/unauthori[sz]ed|invalid username or password|login failed|not authenticated/i' => 'احراز هویت ناموفق؛ اطلاعات ورود یا API Key پنل را بررسی کنید',
            '/^panel not found$/i'                                        => 'پنل در دیتابیس ربات پیدا نشد',
            '/^user not found$/i'                                         => 'کاربر روی پنل پیدا نشد',
            '/manualsale stock not found/i'                               => 'موجودی فروش دستی این محصول تمام شده است',
            '/panel configuration not found/i'                            => 'تنظیمات پنل در ربات پیدا نشد',
        ];
        foreach ($patterns as $pattern => $hint) {
            if (preg_match($pattern, $text)) {
                return $hint;
            }
        }
        return '';
    }
}

if (!function_exists('rx_panel_error_detail_text')) {
    function rx_panel_error_detail_text($detail)
    {
        if ($detail === null || $detail === '' || $detail === []) {
            return '';
        }
        if (is_array($detail)) {
            $parts = [];
            foreach ($detail as $key => $item) {
                if (is_array($item) && isset($item['msg'])) {
                    $loc = isset($item['loc']) && is_array($item['loc']) ? implode('.', array_map('strval', $item['loc'])) : '';
                    $parts[] = ($loc !== '' ? $loc . ': ' : '') . (string) $item['msg'];
                } elseif (is_scalar($item)) {
                    $parts[] = (is_string($key) ? $key . ': ' : '') . (string) $item;
                } else {
                    $parts[] = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            $text = implode(' | ', $parts);
        } else {
            $text = (string) $detail;
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 300) {
            $text = mb_substr($text, 0, 300, 'UTF-8') . '…';
        }
        return $text;
    }
}

if (!function_exists('rx_panel_error_text')) {
    function rx_panel_error_text($msg, $detail = null, $html = true)
    {
        if (is_array($msg) || is_object($msg)) {
            $raw = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $raw = trim((string) $msg);
        }
        $hint = '';
        if ($raw === '' || $raw === 'null') {
            $raw = 'نامشخص';
        } elseif (preg_match('/^\d{1,3}$/', $raw)) {
            $hint = rx_panel_http_status_fa((int) $raw);
        } else {
            $hint = rx_panel_error_hint_fa($raw);
        }
        $detailText = rx_panel_error_detail_text($detail);
        if ($hint === '' && $detailText !== '') {
            $hint = rx_panel_error_hint_fa($detailText);
        }
        $text = $hint !== '' ? $raw . ' — ' . $hint : $raw;
        if ($detailText !== '' && $detailText !== $raw) {
            $text .= "\nپاسخ پنل: " . $detailText;
        }
        return $html ? htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $text;
    }
}

if (!function_exists('rx_panel_username_variant')) {
    function rx_panel_username_variant($base)
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        $suffix = '';
        for ($i = 0; $i < 4; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $base = trim((string) $base);
        if (strlen($base) > 27) {
            $base = substr($base, 0, 27);
        }
        $base = rtrim($base, '_-.');
        if ($base === '') {
            $base = 'u';
        }
        return $base . '_' . $suffix;
    }
}

if (!function_exists('rx_adopt_created_username')) {
    function rx_adopt_created_username($output, $requestedUsername, $idInvoice = null)
    {
        $requestedUsername = (string) $requestedUsername;
        if (!is_array($output) || empty($output['username'])) {
            return $requestedUsername;
        }
        $created = (string) $output['username'];
        if ($created === $requestedUsername || empty($output['renamed_from'])) {
            return $created;
        }
        if ($idInvoice !== null && $idInvoice !== '' && function_exists('update')) {
            try {
                update('invoice', 'username', $created, 'id_invoice', (string) $idInvoice);
                if (function_exists('clearSelectCache')) {
                    clearSelectCache('invoice');
                }
            } catch (Throwable $e) {
                error_log('[rx_adopt_created_username] invoice update failed: ' . $e->getMessage());
            }
        }
        return $created;
    }
}

if (!function_exists('rx_notify_username_renamed')) {
    function rx_notify_username_renamed($chatId, $output, $panel = null)
    {
        if (!is_array($output) || empty($output['renamed_from']) || empty($output['username']) || !function_exists('sendmessage')) {
            return;
        }
        $old = (string) $output['renamed_from'];
        $new = (string) $output['username'];
        if (is_array($panel) && function_exists('guardDisplayUsername')) {
            $old = guardDisplayUsername($old, $panel);
            $new = guardDisplayUsername($new, $panel);
        }
        $template = "⚠️ نام کاربری «<code>{old}</code>» از قبل روی پنل وجود داشت؛ سرویس شما با نام کاربری «<code>{new}</code>» ساخته شد.";
        if (function_exists('faoxima_textbot_get')) {
            $template = faoxima_textbot_get('dyn_username_renamed_notice', $template);
        }
        $text = strtr($template, [
            '{old}' => htmlspecialchars($old, ENT_QUOTES, 'UTF-8'),
            '{new}' => htmlspecialchars($new, ENT_QUOTES, 'UTF-8'),
        ]);
        try {
            sendmessage($chatId, $text, null, 'HTML');
        } catch (Throwable $e) {
            error_log('[rx_notify_username_renamed] ' . $e->getMessage());
        }
    }
}

class ManagePanel
{
    public $pdo, $domainhosts, $name_panel;


    private static $panelRowCache = [];


    private function loadPanel(string $key, string $column = 'name_panel'): ?array
    {
        $cacheKey = $column . '|' . $key;
        if (array_key_exists($cacheKey, self::$panelRowCache)) {
            return self::$panelRowCache[$cacheKey];
        }
        $row = select('marzban_panel', '*', $column, $key, 'select');
        $value = is_array($row) ? $row : null;
        self::$panelRowCache[$cacheKey] = $value;
        return $value;
    }

    public function isPasarGuardDialect(?array $panel): bool
    {
        if (!is_array($panel) || empty($panel['type'])) {
            return false;
        }
        return ($panel['type'] === 'pasarguard')
            || ($panel['type'] === 'marzban' && (string)($panel['version_panel'] ?? '0') === '1');
    }
    public function RequiresLiveConnectionCheck(?array $panel): bool
    {
        if (!is_array($panel) || ($panel['type'] ?? '') === 'Manualsale') {
            return false;
        }
        if (($panel['national_net_status'] ?? '') === 'on_national_net') {
            return false;
        }
        return in_array((string) ($panel['type'] ?? ''), ['marzban', 'pasarguard', 'x-ui_single', 'guard', 'remnawave', 'rebecca'], true);
    }
    public function HasLiveConnectionHistory(?array $panel, array $user): bool
    {
        if (!$this->RequiresLiveConnectionCheck($panel)) {
            return true;
        }
        if (($user['is_online'] ?? null) === true || (float) ($user['used_traffic'] ?? 0) > 0) {
            return true;
        }
        $onlineAt = $user['online_at'] ?? null;
        if (is_numeric($onlineAt)) {
            return (float) $onlineAt > 0;
        }
        $onlineAt = strtolower(trim((string) $onlineAt));
        if ($onlineAt === '' || in_array($onlineAt, ['offline', 'null', 'never', 'none', '0', '-'], true)) {
            return false;
        }
        if ($onlineAt === 'online') {
            return true;
        }
        $onlineTs = strtotime($onlineAt);
        return $onlineTs !== false && $onlineTs > 0;
    }
    private function isDuplicateUsernameFailure($output): bool
    {
        if (!is_array($output) || !empty($output['username'])) {
            return false;
        }
        if ((int) ($output['http_code'] ?? 0) === 409) {
            return true;
        }
        $msg = $output['msg'] ?? '';
        if (is_scalar($msg) && preg_match('/^\s*409\s*$/', (string) $msg)) {
            return true;
        }
        $text = is_scalar($msg) ? (string) $msg : (string) json_encode($msg, JSON_UNESCAPED_UNICODE);
        if (isset($output['detail'])) {
            $text .= ' ' . (is_scalar($output['detail']) ? (string) $output['detail'] : (string) json_encode($output['detail'], JSON_UNESCAPED_UNICODE));
        }
        return (bool) preg_match('/already\s+exist|duplicate|\bexists\b|تکراری/iu', $text);
    }

    private function supportsUsernameRename($name_panel): bool
    {
        $panel = $this->loadPanel((string) $name_panel, 'name_panel');
        return is_array($panel) && in_array((string) ($panel['type'] ?? ''), ['marzban', 'pasarguard', 'x-ui_single', 'guard', 'remnawave', 'rebecca'], true);
    }

    private function panelResponseDetail($response)
    {
        if (!is_array($response) || !isset($response['body']) || !is_string($response['body']) || $response['body'] === '') {
            return null;
        }
        $decoded = json_decode($response['body'], true);
        if (is_array($decoded)) {
            return $decoded['detail'] ?? ($decoded['message'] ?? ($decoded['msg'] ?? null));
        }
        return function_exists('mb_substr') ? mb_substr(strip_tags($response['body']), 0, 300, 'UTF-8') : substr(strip_tags($response['body']), 0, 300);
    }

    function createUser($name_panel, $code_product, $usernameC, array $Data_Config, $allowRename = false)
    {
        $requested = (string) $usernameC;
        $Output = $this->createUserOnce($name_panel, $code_product, $requested, $Data_Config, $requested);
        if (!$allowRename || !$this->isDuplicateUsernameFailure($Output) || !$this->supportsUsernameRename($name_panel)) {
            return $Output;
        }
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $candidate = rx_panel_username_variant($requested);
            $Output = $this->createUserOnce($name_panel, $code_product, $candidate, $Data_Config, $requested);
            if (!$this->isDuplicateUsernameFailure($Output)) {
                break;
            }
        }
        if (is_array($Output) && !empty($Output['username'])) {
            $Output['renamed_from'] = $requested;
        }
        return $Output;
    }

    private function createUserOnce($name_panel, $code_product, $usernameC, array $Data_Config, $invoiceUsername)
    {
        $Output = [];
        global $pdo, $domainhosts;
        if (strlen($usernameC) < 3) {
            return array(
                "status" => "Unsuccessful",
                "msg" => "Username must be at least 3 characters long."
            );
        }


        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if ($Get_Data_Panel == false) {
            $Output['status'] = 'Unsuccessful';
            $Output['msg'] = 'Panel Not Found';
            return $Output;
        }
        if ($Get_Data_Panel['subvip'] == "onsubvip") {
            $inoice = select("invoice", "*", "username", $invoiceUsername, "select");
        } else {
            $inoice = false;
        }
        if (!in_array($code_product, ["usertest", "🛍 حجم دلخواه", "customvolume"])) {

            $stmt = $pdo->prepare("SELECT * FROM product WHERE (FIND_IN_SET(:name_panel, Location) > 0 OR Location = '/all')  AND code_product = :code_product");
            $stmt->bindParam(':name_panel', $name_panel);
            $stmt->bindParam(':code_product', $code_product);
            $stmt->execute();
            $Get_Data_Product = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            if ($code_product == "usertest") {
                $Get_Data_Product['name_product'] = "usertest";
            } else {
                $Get_Data_Product['name_product'] = false;
            }
            $Get_Data_Product['data_limit_reset'] = "no_reset";
        }
        $expire = $Data_Config['expire'];
        $data_limit = $Data_Config['data_limit'];
        $note = "{$Data_Config['from_id']} | {$Data_Config['username']} | {$Data_Config['type']}";
        if ($Get_Data_Panel['type'] == "marzban") {

            $ConnectToPanel = adduser($Get_Data_Panel['name_panel'], $data_limit, $usernameC, $expire, $note, $Get_Data_Product['data_limit_reset'], $Get_Data_Product['name_product']);
            if (!empty($ConnectToPanel['status']) && (int)$ConnectToPanel['status'] !== 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['status'],
                    'detail' => $this->panelResponseDetail($ConnectToPanel)
                );
            }
            if (!empty($ConnectToPanel['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['error']
                );
            }
            $data_Output = json_decode($ConnectToPanel['body'], true);
            if (!empty($data_Output['detail']) && $data_Output['detail']) {
                $Output['status'] = 'Unsuccessful';
                if ($data_Output['detail']) {
                    $Output['msg'] = $data_Output['detail'];
                } else {
                    $Output['msg'] = '';
                }
            } else {
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $data_Output['subscription_url'])) {
                    $data_Output['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($data_Output['subscription_url'], "/");
                }
                if ((string)($Get_Data_Panel['version_panel'] ?? '0') === '1') {
                    $out_put_link = outputlunk($data_Output['subscription_url']);
                    if (isBase64($out_put_link)) {
                        $data_Output['links'] = base64_decode(outputlunk($data_Output['subscription_url']));
                    }
                    $data_Output['links'] = explode("\n", $data_Output['links']);
                }
                if ($inoice != false) {
                    $data_Output['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                $Output['status'] = 'successful';
                $Output['username'] = $data_Output['username'];
                $Output['subscription_url'] = $data_Output['subscription_url'];
                $Output['configs'] = $data_Output['links'];
            }
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $ConnectToPanel = pasarguardAddUser($Get_Data_Panel['name_panel'], $data_limit, $usernameC, $expire, $note, $Get_Data_Product['data_limit_reset'], $Get_Data_Product['name_product']);
            if (!empty($ConnectToPanel['status']) && (int)$ConnectToPanel['status'] !== 201) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['status'],
                    'detail' => $this->panelResponseDetail($ConnectToPanel)
                );
            }
            if (!empty($ConnectToPanel['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $ConnectToPanel['error']
                );
            }
            $data_Output = json_decode($ConnectToPanel['body'], true);
            if (!empty($data_Output['detail']) && $data_Output['detail']) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['detail'];
            } else {
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $data_Output['subscription_url'])) {
                    $data_Output['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($data_Output['subscription_url'], "/");
                }
                if ($inoice != false) {
                    $data_Output['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                $Output['status'] = 'successful';
                $Output['username'] = $data_Output['username'];
                $Output['subscription_url'] = $data_Output['subscription_url'];
                $Output['configs'] = $data_Output['links'] ?? array();
            }
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            $subId = bin2hex(random_bytes(8));
            if (isset($Get_Data_Product['inbounds']) and $Get_Data_Product['inbounds'] != null) {
                $inbounds = $Get_Data_Product['inbounds'];
            } else {
                $inbounds = $Get_Data_Panel['inboundid'];
            }
            $productIpLimit = isset($Data_Config['ip_limit'])
                ? intval($Data_Config['ip_limit'])
                : (isset($Get_Data_Product['ip_limit']) ? intval($Get_Data_Product['ip_limit']) : 0);
            $productHwidLimit = isset($Data_Config['hwid_limit'])
                ? intval($Data_Config['hwid_limit'])
                : (isset($Get_Data_Product['hwid_limit']) ? intval($Get_Data_Product['hwid_limit']) : 0);
            $data_Output = addClient($Get_Data_Panel['name_panel'], $usernameC, $expire, $data_limit, generateUUID(), "", $subId, $inbounds, $Get_Data_Product['name_product'], $note, $productIpLimit, $productHwidLimit);
            if (!empty($data_Output['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $data_Output['error']
                );
            } elseif (!empty($data_Output['status']) && $data_Output['status'] != 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $data_Output['status']
                );
            } else {
                $data_Output = json_decode($data_Output['body'], true);
                if (!$data_Output['success']) {
                    $Output['status'] = 'Unsuccessful';
                    $Output['msg'] = $data_Output['msg'];
                } else {
                    if (!(isset($Get_Data_Product['inbounds']) and $Get_Data_Product['inbounds'] != null)) {
                        xsync_record_creation($Get_Data_Panel['name_panel'], $usernameC, xui_parse_inbound_ids($inbounds));
                    }
                    $subscriptionUrl = rtrim($Get_Data_Panel['linksubx'], '/') . "/{$subId}";
                    $singleConfig = get_single_link_after_create(
                        $Get_Data_Panel['url_panel'],
                        $inbounds,
                        $subscriptionUrl,
                        $usernameC,
                        $Get_Data_Panel['name_panel'],
                        $Get_Data_Panel['code_panel'] ?? null
                    );
                    $links_user = [];
                    if ($singleConfig && $singleConfig['type'] === 'link') {
                        $links_user[] = $singleConfig['value'];
                    }
                    $subscriptionLinks = get_subscription_links_with_retry($subscriptionUrl);
                    if (is_array($subscriptionLinks)) {
                        foreach ($subscriptionLinks as $linkItem) {
                            if (!in_array($linkItem, $links_user, true)) {
                                $links_user[] = $linkItem;
                            }
                        }
                    }
                    if (empty($links_user)) {
                        $links_user[] = 'در دسترس نیست';
                    }
                    $Output['status'] = 'successful';
                    $Output['username'] = $usernameC;
                    $Output['subscription_url'] = $subscriptionUrl;
                    $Output['configs'] = $links_user;
                    $Output['ip_limit'] = $productIpLimit;
                    $Output['hwid_limit'] = $productHwidLimit;
                    if ($inoice != false) {
                        $Output['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                    }
                }
            }
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $serviceIdsSource = $Get_Data_Panel['guard_service_ids'] ?? null;
            if (!empty($Get_Data_Product['inbounds'])) {
                $decodedServices = json_decode($Get_Data_Product['inbounds'], true);
                if (is_array($decodedServices)) {
                    $serviceIdsSource = $decodedServices;
                }
            }
            $serviceResult = guardResolveServiceIds($Get_Data_Panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $serviceResult['msg']
                );
            }
            $guardIsTestAccount = ($Get_Data_Product['name_product'] ?? '') === 'usertest';
            $guardOnHoldEnabled = $guardIsTestAccount
                ? (($Get_Data_Panel['on_hold_test'] ?? '0') !== '0')
                : (($Get_Data_Panel['conecton'] ?? '') === 'onconecton');
            $guardSubscriptionEntry = array(
                "username" => $usernameC,
                "limit_usage" => $data_limit,
                "service_ids" => $serviceResult['service_ids'],
                "note" => $note
            );
            if ($guardOnHoldEnabled && $expire != 0) {
                $guardOnHoldSeconds = max(60, $expire - time());
                $guardSubscriptionEntry['limit_expire'] = -$guardOnHoldSeconds;
            } else {
                $guardSubscriptionEntry['limit_expire'] = guardNormalizeExpire($expire);
            }
            $payload = array($guardSubscriptionEntry);
            $createResponse = guardCreateSubscription($Get_Data_Panel['name_panel'], $payload);
            if ($createResponse['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $createResponse['msg'],
                    'http_code' => $createResponse['http_code'] ?? null
                );
            }
            $subscriptionUrl = '';
            $configs = array();
            $createdData = $createResponse['data'];
            if (is_array($createdData) && isset($createdData[0])) {
                $first = $createdData[0];
                if (isset($first['subscription_url'])) {
                    $subscriptionUrl = $first['subscription_url'];
                } elseif (isset($first['subscription_link'])) {
                    $subscriptionUrl = $first['subscription_link'];
                } elseif (isset($first['subscription'])) {
                    $subscriptionUrl = $first['subscription'];
                }
            } elseif (is_array($createdData)) {
                if (isset($createdData['subscription_url'])) {
                    $subscriptionUrl = $createdData['subscription_url'];
                } elseif (isset($createdData['subscription_link'])) {
                    $subscriptionUrl = $createdData['subscription_link'];
                } elseif (isset($createdData['subscription'])) {
                    $subscriptionUrl = $createdData['subscription'];
                }
            }
            if (!empty($subscriptionUrl)) {
                $configs[] = $subscriptionUrl;
            }
            $userData = $this->DataUser($Get_Data_Panel['name_panel'], $usernameC);
            if (!empty($userData) && (!isset($userData['status']) || $userData['status'] != "Unsuccessful")) {
                if (!empty($userData['subscription_url'])) {
                    $subscriptionUrl = $userData['subscription_url'];
                }
                if (!empty($userData['links'])) {
                    $configs = $userData['links'];
                }
            }
            if ($inoice != false) {
                $subscriptionUrl = "https://$domainhosts/sub/" . $inoice['id_invoice'];
            }
            $Output['status'] = 'successful';
            $Output['username'] = $usernameC;
            $Output['subscription_url'] = $subscriptionUrl;
            $Output['configs'] = $configs;
        } elseif ($Get_Data_Panel['type'] == "Manualsale") {
            $configman = null;
            $manualClaimedRows = array();
            $pickStock = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = ? AND status = 'active' AND codeproduct = ? ORDER BY RAND() LIMIT 1");
            for ($claimAttempt = 0; $claimAttempt < 5 && $configman === null; $claimAttempt++) {
                $pickStock->execute(array((string) $Get_Data_Panel['code_panel'], (string) $code_product));
                $candidate = $pickStock->fetch(PDO::FETCH_ASSOC);
                $pickStock->closeCursor();
                if (!is_array($candidate) || empty($candidate['id'])) {
                    break;
                }
                if (!empty($candidate['group_id'])) {
                    $claimGroup = $pdo->prepare("UPDATE manualsell SET status = 'selled', username = ? WHERE group_id = ? AND status = 'active'");
                    $claimGroup->execute(array($usernameC, $candidate['group_id']));
                    if ($claimGroup->rowCount() < 1) {
                        continue;
                    }
                    $groupFetch = $pdo->prepare("SELECT * FROM manualsell WHERE group_id = ? AND username = ? AND status = 'selled' ORDER BY id ASC");
                    $groupFetch->execute(array($candidate['group_id'], $usernameC));
                    $groupRows = $groupFetch->fetchAll(PDO::FETCH_ASSOC);
                    if (empty($groupRows)) {
                        continue;
                    }
                    $manualClaimedRows = $groupRows;
                    $configman = $groupRows[0];
                    foreach ($groupRows as $groupRow) {
                        if ((string) $groupRow['id'] === (string) $candidate['id']) {
                            $configman = $groupRow;
                            break;
                        }
                    }
                } else {
                    $claimOne = $pdo->prepare("UPDATE manualsell SET status = 'selled', username = ? WHERE id = ? AND status = 'active'");
                    $claimOne->execute(array($usernameC, $candidate['id']));
                    if ($claimOne->rowCount() !== 1) {
                        continue;
                    }
                    $candidate['status'] = 'selled';
                    $candidate['username'] = $usernameC;
                    $configman = $candidate;
                    $manualClaimedRows = array($candidate);
                }
            }
            if (function_exists('clearSelectCache')) {
                clearSelectCache('manualsell');
            }
            if (!is_array($configman) || empty($configman['id'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'Manualsale stock not found'
                );
            }
            $Output['status'] = 'successful';
            $Output['username'] = $usernameC;
            $Output['subscription_url'] = $configman['contentrecord'];
            $Output['configs'] = "";
            $Output['file_ext'] = $configman['file_ext'];
            $Output['sub_link'] = $configman['sub_link'] ?? '';
            $manualItemsOut = array();
            $manualUnifiedSubOut = '';
            foreach ($manualClaimedRows as $mRow) {
                $mContent = (string)($mRow['contentrecord'] ?? '');
                $mSub = trim((string)($mRow['sub_link'] ?? ''));
                if ($manualUnifiedSubOut === '' && $mSub !== '') {
                    $manualUnifiedSubOut = $mSub;
                }
                if ($mContent === '') {
                    continue;
                }
                $manualItemsOut[] = array(
                    'content'  => $mContent,
                    'file_ext' => (string)($mRow['file_ext'] ?? ''),
                    'sub_link' => $mSub,
                    'name'     => (string)($mRow['namerecord'] ?? ''),
                );
            }
            $Output['manual_items'] = $manualItemsOut;
            $Output['manual_sub_link'] = $manualUnifiedSubOut;
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $data_limit = round($data_limit / (1024 * 1024 * 1024), 2);
            $data_Output = addpear($Get_Data_Panel['name_panel'], $usernameC);
            if (isset($data_Output['status']) && $data_Output['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => isset($data_Output['msg']) ? $data_Output['msg'] : ''
                );
            }
            if (!empty($data_Output['status']) && $data_Output['status'] != 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $data_Output['status']
                );
            }
            if (!empty($data_Output['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $data_Output['error']
                );
            }
            $data_Output = $data_Output['body'];
            $response = json_decode($data_Output['response'], true);
            if ($data_limit != 0) {
                $jobResponse = setjob($Get_Data_Panel['name_panel'], "total_data", $data_limit, $data_Output['public_key']);
                if (isset($jobResponse['status']) && $jobResponse['status'] === false) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => isset($jobResponse['msg']) ? $jobResponse['msg'] : ''
                    );
                }
            }
            if ($expire != 0) {
                $jobResponse = setjob($Get_Data_Panel['name_panel'], "date", date('Y-m-d H:i:s', $expire), $data_Output['public_key']);
                if (isset($jobResponse['status']) && $jobResponse['status'] === false) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => isset($jobResponse['msg']) ? $jobResponse['msg'] : ''
                    );
                }
            }
            update("invoice", "user_info", json_encode($data_Output), "username", $usernameC);
            if (!$response['status']) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['msg'];
            } else {
                $download_config = downloadconfig($Get_Data_Panel['name_panel'], $data_Output['public_key']);
                if (isset($download_config['status']) && $download_config['status'] === false) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => isset($download_config['msg']) ? $download_config['msg'] : ''
                    );
                }
                if (!empty($download_config['status']) && $download_config['status'] != 200) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $download_config['status']
                    );
                }
                if (!empty($download_config['error'])) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $download_config['error']
                    );
                }
                $download_config = json_decode($download_config['body'], true)['data'];
                $Output['status'] = 'successful';
                $Output['username'] = $usernameC;
                $Output['subscription_url'] = strval($download_config['file']);
                $Output['configs'] = [];
            }
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            $squad = (string) ($Get_Data_Panel['remna_squad'] ?? '');
            $invoiceId = (string) ($Data_Config['id_order'] ?? ($Data_Config['invoice'] ?? ''));
            $remnaHwidLimit = isset($Data_Config['hwid_limit'])
                ? intval($Data_Config['hwid_limit'])
                : (isset($Get_Data_Product['hwid_limit']) ? intval($Get_Data_Product['hwid_limit']) : 0);
            $data_Output = remnawave_adduser($Get_Data_Panel['name_panel'], $data_limit, $usernameC, $expire, $squad, $invoiceId, $remnaHwidLimit);
            if (($data_Output['status'] ?? '') !== 'successful') {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $data_Output['msg'] ?? '';
            } else {
                $Output['status'] = 'successful';
                $Output['username'] = $usernameC;
                $Output['subscription_url'] = $data_Output['subscription_url'];
                $Output['configs'] = (!empty($data_Output['configs']) && is_array($data_Output['configs'])) ? $data_Output['configs'] : [$data_Output['subscription_url']];
            }
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $rebeccaServiceValue = $Get_Data_Panel['rebecca_service_id'] ?? null;
            if (!empty($Get_Data_Product['inbounds'])) {
                $rebeccaServiceValue = $Get_Data_Product['inbounds'];
            }
            $rebeccaIpLimit = isset($Data_Config['ip_limit'])
                ? intval($Data_Config['ip_limit'])
                : (isset($Get_Data_Product['ip_limit']) ? intval($Get_Data_Product['ip_limit']) : 0);
            $rebeccaOnHoldDuration = null;
            $rebeccaIsTestAccount = ($Get_Data_Product['name_product'] ?? '') === 'usertest';
            $rebeccaOnHoldEnabled = $rebeccaIsTestAccount
                ? (($Get_Data_Panel['on_hold_test'] ?? '0') !== '0')
                : (($Get_Data_Panel['conecton'] ?? '') === 'onconecton');
            if ($rebeccaOnHoldEnabled && $expire != 0) {
                $rebeccaOnHoldDuration = $expire - time();
            }
            $createResponse = rebeccaCreateUser($Get_Data_Panel['name_panel'], $usernameC, $data_limit, $expire, $rebeccaServiceValue, $note, $rebeccaIpLimit, $rebeccaOnHoldDuration);
            if ($createResponse['status'] === false) {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $createResponse['msg'];
                $Output['http_code'] = $createResponse['http_code'] ?? null;
            } else {
                $createdData = $createResponse['data'];
                $subscriptionUrl = is_array($createdData) ? ($createdData['subscription_url'] ?? '') : '';
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $subscriptionUrl)) {
                    $subscriptionUrl = rtrim((string) ($Get_Data_Panel['url_panel'] ?? ''), '/') . "/" . ltrim($subscriptionUrl, "/");
                }
                $configs = function_exists('rebeccaBuildDisplayLinks') && is_array($createdData)
                    ? rebeccaBuildDisplayLinks($Get_Data_Panel['name_panel'], $createdData, $usernameC)
                    : [];
                if (empty($configs) && !empty($subscriptionUrl)) {
                    $configs[] = $subscriptionUrl;
                }
                if ($inoice != false) {
                    $subscriptionUrl = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                $Output['status'] = 'successful';
                $Output['username'] = $usernameC;
                $Output['subscription_url'] = $subscriptionUrl;
                $Output['configs'] = $configs;
            }
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            $jovadOutput = jovadpanelCreateUser(
                $Get_Data_Panel,
                $code_product,
                $usernameC,
                $Data_Config,
                is_array($Get_Data_Product) ? $Get_Data_Product : array(),
                $note
            );
            if (($jovadOutput['status'] ?? '') === 'successful' && !empty($jovadOutput['username'])) {
                $Output['status'] = 'successful';
                $Output['username'] = $jovadOutput['username'];
                $Output['subscription_url'] = $jovadOutput['subscription_url'];
                $Output['configs'] = $jovadOutput['configs'];
            } else {
                $Output['status'] = 'Unsuccessful';
                $Output['msg'] = $jovadOutput['msg'] ?? 'JovadPanel create failed';
                if (!empty($jovadOutput['code'])) {
                    $Output['code'] = $jovadOutput['code'];
                }
            }
        } else {
            $Output['status'] = 'Unsuccessful';
            $Output['msg'] = 'Panel Not Found';
        }
        if (function_exists('normalizeServiceConfigs')) {
            $rxNormalizeSubUrl = (($Get_Data_Panel['type'] ?? '') === 'jovadpanel') ? null : ($Output['subscription_url'] ?? null);
            if (isset($Output['status']) && $Output['status'] === 'successful') {
                $Output['configs'] = normalizeServiceConfigs($Output['configs'] ?? null, $rxNormalizeSubUrl);
            } else {
                $Output['configs'] = normalizeServiceConfigs($Output['configs'] ?? null);
            }
        } else {
            if (!isset($Output['configs'])) {
                $Output['configs'] = [];
            } elseif (!is_array($Output['configs'])) {
                $value = trim((string) $Output['configs']);
                $Output['configs'] = $value === '' ? [] : [$value];
            }
        }
        return $Output;
    }
    function DataUser($name_panel, $username, $client_type = 'links')
    {
        $Output = array();
        global $pdo, $domainhosts;
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if (!$Get_Data_Panel || !is_array($Get_Data_Panel)) {
            error_log("[REMNAWAVE-DATAUSER-ENTER] Username passed: " . ($username ?? 'NULL') . " | Panel Name: " . ($name_panel ?? 'NULL') . " | RESULT: panel not found by loadPanel");
            return array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        if (isset($Get_Data_Panel['subvip']) && $Get_Data_Panel['subvip'] == "onsubvip") {
            $inoice = select("invoice", "*", "username", $username, "select");
        } else {
            $inoice = false;
        }
        if ($Get_Data_Panel['type'] == "marzban") {
            $UsernameData = getuser($username, $Get_Data_Panel['name_panel']);
            if (!empty($UsernameData['error'])) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            } elseif (!empty($UsernameData['status']) && $UsernameData['status'] == 500) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['status']
                );
            } else {
                $UsernameData = json_decode($UsernameData['body'], true);
                if (!empty($UsernameData['detail'])) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $UsernameData['detail']
                    );
                }
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $UsernameData['subscription_url'])) {
                    $UsernameData['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($UsernameData['subscription_url'], "/");
                }
                if ((string)($Get_Data_Panel['version_panel'] ?? '0') === '1') {
                    $UsernameData['expire'] = empty($UsernameData['expire']) ? 0 : strtotime((string) $UsernameData['expire']);
                    $UsernameData['links'] = base64_decode(outputlunk($UsernameData['subscription_url']));
                    $UsernameData['links'] = explode("\n", $UsernameData['links']);
                    $sublist_update = get_list_update($name_panel, $username);
                    if (!empty($sublist_update['error'])) {
                        return array(
                            'status' => 'Unsuccessful',
                            'msg' => $sublist_update['error']
                        );
                    } elseif (!empty($sublist_update['status']) && $sublist_update['status'] == 500) {
                        return array(
                            'status' => 'Unsuccessful',
                            'msg' => $sublist_update['status']
                        );
                    }
                    $sublist_update_body = json_decode($sublist_update['body'], true);
                    if (!empty($sublist_update_body['updates']) && is_array($sublist_update_body['updates'])) {
                        $first_update = $sublist_update_body['updates'][0];
                        $UsernameData['sub_updated_at'] = isset($first_update['created_at']) ? $first_update['created_at'] : null;
                        $UsernameData['sub_last_user_agent'] = isset($first_update['user_agent']) ? $first_update['user_agent'] : null;
                    } else {
                        $UsernameData['sub_updated_at'] = isset($UsernameData['sub_updated_at']) ? $UsernameData['sub_updated_at'] : null;
                        $UsernameData['sub_last_user_agent'] = isset($UsernameData['sub_last_user_agent']) ? $UsernameData['sub_last_user_agent'] : null;
                    }
                } else {
                    $UsernameData['expire'] = $UsernameData['expire'];
                }
                if ($inoice != false) {
                    $UsernameData['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                if ((string)($Get_Data_Panel['version_panel'] ?? '0') === '1') {
                    $UsernameData['proxies'] = isset($UsernameData['proxy_settings']) ? $UsernameData['proxy_settings'] : null;
                }
                $Output = array(
                    'status' => $UsernameData['status'],
                    'username' => $UsernameData['username'],
                    'data_limit' => $UsernameData['data_limit'],
                    'expire' => $UsernameData['expire'],
                    'online_at' => $UsernameData['online_at'],
                    'used_traffic' => $UsernameData['used_traffic'],
                    'links' => $UsernameData['links'],
                    'subscription_url' => $UsernameData['subscription_url'],
                    'sub_updated_at' => $UsernameData['sub_updated_at'],
                    'sub_last_user_agent' => $UsernameData['sub_last_user_agent'],
                    'uuid' => $UsernameData['proxies'],
                    'data_limit_reset' => $UsernameData['data_limit_reset_strategy'],
                    'group_ids' => $UsernameData['group_ids'] ?? null,
                    'inbounds' => $UsernameData['inbounds'] ?? null,
                    'on_hold_expire_duration' => $UsernameData['on_hold_expire_duration'] ?? null
                );
            }
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $pasarguardUserRaw = pasarguardGetUser($username, $Get_Data_Panel['name_panel']);
            $UsernameData = $pasarguardUserRaw;
            if (!empty($UsernameData['error'])) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            } elseif (!empty($UsernameData['status']) && $UsernameData['status'] == 500) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['status']
                );
            } else {
                $UsernameData = json_decode($UsernameData['body'], true);
                if (!empty($UsernameData['detail'])) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $UsernameData['detail']
                    );
                }
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $UsernameData['subscription_url'])) {
                    $UsernameData['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($UsernameData['subscription_url'], "/");
                }
                if ($inoice != false) {
                    $UsernameData['subscription_url'] = "https://$domainhosts/sub/" . $inoice['id_invoice'];
                }
                $pasarguardExpireTs = 0;
                if (!empty($UsernameData['expire'])) {
                    $pasarguardExpireTs = is_numeric($UsernameData['expire']) ? (int) $UsernameData['expire'] : (int) strtotime((string) $UsernameData['expire']);
                }
                $pasarguardLinks = [];
                $pasarguardClientType = 'links';
                $pasarguardLinksResponse = pasarguardGetSubscriptionLinks($username, $Get_Data_Panel['name_panel'], $client_type, $pasarguardUserRaw);
                if (empty($pasarguardLinksResponse['error']) && !empty($pasarguardLinksResponse['links'])) {
                    $pasarguardLinks = $pasarguardLinksResponse['links'];
                    $pasarguardClientType = (string) ($pasarguardLinksResponse['client_type'] ?? 'links');
                }
                $pasarguardLinksDiagnostic = array(
                    'panel_type' => 'pasarguard',
                    'outcome' => !empty($pasarguardLinksResponse['error'])
                        ? (string) ($pasarguardLinksResponse['outcome'] ?? 'failed')
                        : (empty($pasarguardLinks) ? 'empty' : 'ok'),
                    'stage' => $pasarguardLinksResponse['stage'] ?? null,
                    'reason' => $pasarguardLinksResponse['reason'] ?? null,
                    'http_status' => $pasarguardLinksResponse['http_status'] ?? null,
                    'error' => !empty($pasarguardLinksResponse['error']) ? (string) $pasarguardLinksResponse['error'] : null,
                );
                $Output = array(
                    'status' => $UsernameData['status'],
                    'username' => $UsernameData['username'],
                    'data_limit' => $UsernameData['data_limit'],
                    'expire' => $pasarguardExpireTs,
                    'online_at' => $UsernameData['online_at'],
                    'used_traffic' => $UsernameData['used_traffic'],
                    'links' => $pasarguardLinks,
                    'subscription_url' => $UsernameData['subscription_url'],
                    'sub_updated_at' => $UsernameData['sub_updated_at'] ?? null,
                    'sub_last_user_agent' => $UsernameData['sub_last_user_agent'] ?? null,
                    'uuid' => $UsernameData['proxies'] ?? null,
                    'data_limit_reset' => $UsernameData['data_limit_reset_strategy'],
                    'hwid_limit' => $UsernameData['hwid_limit'] ?? null,
                    'group_ids' => $UsernameData['group_ids'] ?? null,
                    'client_type' => $pasarguardClientType,
                    'on_hold_expire_duration' => $UsernameData['on_hold_expire_duration'] ?? null,
                    'links_diagnostic' => $pasarguardLinksDiagnostic
                );
            }
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $subscription = guardGetSubscription($Get_Data_Panel['name_panel'], $username);
            if ($subscription['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $subscription['msg']
                );
            }
            $subscriptionData = $subscription['data'];
            if (isset($subscriptionData['data']) && is_array($subscriptionData['data'])) {
                $subscriptionData = $subscriptionData['data'];
            }
            if (isset($subscriptionData['subscription']) && is_array($subscriptionData['subscription'])) {
                $subscriptionData = $subscriptionData['subscription'];
            }
            if (is_array($subscriptionData) && isset($subscriptionData[0])) {
                $subscriptionData = $subscriptionData[0];
            }
            if (!is_array($subscriptionData)) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'User not found'
                );
            }
            $guardEnabled = array_key_exists('enabled', $subscriptionData)
                ? filter_var($subscriptionData['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null;
            $guardRawExpire = isset($subscriptionData['limit_expire']) ? intval($subscriptionData['limit_expire']) : 0;
            $guardOnHold = $guardRawExpire < 0
                || (!empty($subscriptionData['on_hold_timeout_days']) && empty($subscriptionData['on_hold_since']));
            if (!empty($subscriptionData['expired']) || !empty($subscriptionData['expired_at'])) {
                $statusGuard = "expired";
            } elseif (!empty($subscriptionData['limited']) || !empty($subscriptionData['limited_at'])) {
                $statusGuard = "limited";
            } elseif ($guardEnabled === false) {
                $statusGuard = "disabled";
            } elseif ($guardOnHold) {
                $statusGuard = "on_hold";
            } elseif (isset($subscriptionData['status']) && is_string($subscriptionData['status']) && $subscriptionData['status'] !== '') {
                $statusGuard = $subscriptionData['status'];
            } else {
                $statusGuard = "active";
            }
            $limitExpire = $guardRawExpire > 0 ? $guardRawExpire : 0;
            $dataLimit = isset($subscriptionData['limit_usage']) ? intval($subscriptionData['limit_usage']) : 0;
            $subscriptionUrl = $subscriptionData['subscription_url'] ?? ($subscriptionData['subscription_link'] ?? ($subscriptionData['subscription'] ?? ''));
            $serviceIds = isset($subscriptionData['service_ids']) && is_array($subscriptionData['service_ids'])
                ? $subscriptionData['service_ids']
                : (isset($subscriptionData['services']) && is_array($subscriptionData['services']) ? $subscriptionData['services'] : array());
            $usage = 0;
            if (isset($subscriptionData['total_usage'])) {
                $usage = intval($subscriptionData['total_usage']);
            } elseif (isset($subscriptionData['usage'])) {
                $usage = intval($subscriptionData['usage']);
            } elseif (isset($subscriptionData['used_traffic'])) {
                $usage = intval($subscriptionData['used_traffic']);
            }
            $usageResponse = guardGetSubscriptionUsages($Get_Data_Panel['name_panel'], $username);
            if ($usageResponse && $usageResponse['status'] !== false && isset($usageResponse['data'])) {
                $usageData = $usageResponse['data'];
                if (isset($usageData['total_usage'])) {
                    $usage = intval($usageData['total_usage']);
                } elseif (isset($usageData['hourly_usage']) && is_array($usageData['hourly_usage'])) {
                    $usage = 0;
                    foreach ($usageData['hourly_usage'] as $hourlyPoint) {
                        $usage += intval($hourlyPoint['usage'] ?? 0);
                    }
                } else {
                    $usageList = array();
                    if (isset($usageData['usages']) && is_array($usageData['usages'])) {
                        $usageList = $usageData['usages'];
                    } elseif (is_array($usageData) && isset($usageData[0])) {
                        $usageList = $usageData;
                    }
                    foreach ($usageList as $usageItem) {
                        $usage += intval($usageItem['total_usage'] ?? $usageItem['usage'] ?? (($usageItem['download'] ?? 0) + ($usageItem['upload'] ?? 0)));
                    }
                }
            }
            $onlineAt = $subscriptionData['online_at'] ?? ($subscriptionData['last_online_at'] ?? null);
            $isOnline = null;
            if (array_key_exists('is_online', $subscriptionData)) {
                $isOnline = $subscriptionData['is_online'];
            } elseif (array_key_exists('online', $subscriptionData)) {
                $isOnline = $subscriptionData['online'];
            }
            if ($isOnline !== null) {
                $isOnline = filter_var($isOnline, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
            $links = array();
            $linksResponse = guardGetSubscriptionLinks($Get_Data_Panel['name_panel'], $username);
            if ($linksResponse && $linksResponse['status'] !== false && isset($linksResponse['data']['links']) && is_array($linksResponse['data']['links'])) {
                foreach ($linksResponse['data']['links'] as $linkItem) {
                    if (is_array($linkItem) && !empty($linkItem['link'])) {
                        $links[] = $linkItem['link'];
                    } elseif (is_string($linkItem) && $linkItem !== '') {
                        $links[] = $linkItem;
                    }
                }
            }
            if (empty($links) && !empty($subscriptionUrl)) {
                $links[] = $subscriptionUrl;
            }
            if ($inoice != false) {
                $subscriptionUrl = "https://$domainhosts/sub/" . $inoice['id_invoice'];
            }
            $Output = array(
                'status' => $statusGuard,
                'username' => $subscriptionData['username'] ?? $username,
                'data_limit' => $dataLimit,
                'expire' => $limitExpire,
                'online_at' => $onlineAt,
                'is_online' => $isOnline,
                'used_traffic' => $usage,
                'links' => $links,
                'subscription_url' => $subscriptionUrl,
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
                'uuid' => null,
                'data_limit_reset' => null,
                'service_ids' => $serviceIds
            );
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            $user_data = get_clinets($username, $Get_Data_Panel['name_panel']);
            if (!empty($user_data['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $user_data['error']
                );
            } elseif (!empty($user_data['status']) && $user_data['status'] != 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => json_encode($user_data)
                );
            }
            $user_data = json_decode($user_data['body'], true);

            if (!is_array($user_data)) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'object invalid'
                );
            }
            if (empty($user_data['obj'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => "User not found"
                );
            }
            $user_data = $user_data['obj'];
            $expire = $user_data['expiryTime'] / 1000;
            if ($user_data['enable']) {
                $user_data['enable'] = "active";
            } else {
                $user_data['enable'] = "disabled";
            }
            if ((intval($user_data['total'])) != 0) {
                if ((intval($user_data['total']) - ($user_data['up'] + $user_data['down'])) <= 0)
                    $user_data['enable'] = "limited";
            }
            if (intval($user_data['expiryTime']) != 0) {
                if ($expire - time() <= 0)
                    $user_data['enable'] = "expired";
            }
            $xuiOnHoldDuration = null;
            if ($user_data['expiryTime'] < -10000) {
                $user_data['enable'] = "on_hold";
                $xuiOnHoldDuration = (int) round(abs((float) $user_data['expiryTime']) / 1000);
                $expire = 0;
            }
            $subscriptionUrl = rtrim($Get_Data_Panel['linksubx'], '/') . "/{$user_data['subId']}";
            $linksub = $subscriptionUrl;
            $links_user_raw = outputlunk($subscriptionUrl);
            if (!is_string($links_user_raw)) {
                $links_user_raw = '';
            }
            if (isBase64($links_user_raw)) {
                $links_user_raw = base64_decode($links_user_raw);
            }
            $links_user = preg_split('/\R/', trim($links_user_raw));
            if (!is_array($links_user)) {
                $links_user = [];
            }
            $links_user = array_values(array_filter(array_map('trim', $links_user), function ($ln) {
                return $ln !== '';
            }));
            $singleLink = $links_user[0] ?? null;
            $singleConfigFile = null;
            if (!$singleLink || !preg_match('/^(vless|vmess|trojan):\/\//i', $singleLink)) {
                if (is_file(xuisingle_cookie_path())) {
                    @unlink(xuisingle_cookie_path());
                }
                login($Get_Data_Panel['code_panel']);
                $singleConfig = get_single_link_smart(
                    $Get_Data_Panel['url_panel'],
                    $Get_Data_Panel['inboundid'],
                    $subscriptionUrl,
                    $username,
                    $Get_Data_Panel['name_panel'],
                    $Get_Data_Panel['code_panel'] ?? null
                );
                if (is_file(xuisingle_cookie_path())) {
                    @unlink(xuisingle_cookie_path());
                }
                if (!$singleConfig) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => 'Unable to build single link'
                    );
                }
                if ($singleConfig['type'] === 'file') {
                    $singleConfigFile = $singleConfig;
                    $xuiFileScheme = ($singleConfig['protocol'] ?? '') === 'amneziawg' ? 'xuiawgfile' : 'xuifile';
                    $singleLink = $xuiFileScheme . '://' . rawurlencode($singleConfig['filename']);
                    array_unshift($links_user, $singleLink);
                } else {
                    $singleLink = $singleConfig['value'];
                    array_unshift($links_user, $singleLink);
                }
            }
            if ($inoice != false)
                $linksub = "https://$domainhosts/sub/" . $inoice['id_invoice'];
            $user_data['lastOnline'] = $user_data['lastOnline'] == 0 ? "offline" : date('Y-m-d H:i:s', $user_data['lastOnline'] / 1000);
            $Output = array(
                'status' => $user_data['enable'],
                'username' => $user_data['email'],
                'data_limit' => $user_data['total'],
                'expire' => $expire,
                'online_at' => $user_data['lastOnline'],
                'used_traffic' => $user_data['up'] + $user_data['down'],
                'links' => $links_user,
                'subscription_url' => $linksub,
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
                'single_config_file' => $singleConfigFile,
                'on_hold_expire_duration' => $xuiOnHoldDuration,
            );

        } elseif ($Get_Data_Panel['type'] == "Manualsale") {
            $stmt = $pdo->prepare("SELECT * FROM manualsell WHERE username = :username ORDER BY id ASC");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $manualRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $configman = (is_array($manualRows) && !empty($manualRows)) ? $manualRows[0] : false;
            $manualItems = array();
            $manualUnifiedSub = '';
            if (is_array($manualRows)) {
                foreach ($manualRows as $mr) {
                    $manualItems[] = array(
                        'record_id' => (int) ($mr['id'] ?? 0),
                        'content' => (string) ($mr['contentrecord'] ?? ''),
                        'file_ext' => (string) ($mr['file_ext'] ?? ''),
                        'sub_link' => (string) ($mr['sub_link'] ?? ''),
                        'name' => (string) ($mr['namerecord'] ?? ''),
                    );
                    if ($manualUnifiedSub === '' && trim((string) ($mr['sub_link'] ?? '')) !== '') {
                        $manualUnifiedSub = trim((string) $mr['sub_link']);
                    }
                }
            }
            $service = select("invoice", "*", "username", $username, "select");
            if (!is_array($service)) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'User not found'
                );
            }
            $volumeValue = (float) ($service['Volume'] ?? 0);
            $volumeUnit = function_exists('rxInvoiceVolumeUnit') ? rxInvoiceVolumeUnit($service) : ((($service['name_product'] ?? '') === 'سرویس تست') ? 'MB' : 'GB');
            $data_limit = $volumeValue == 0 ? null : (function_exists('rxVolumeToBytes') ? rxVolumeToBytes($volumeValue, $volumeUnit) : $volumeValue * ($volumeUnit === 'MB' ? pow(1024, 2) : pow(1024, 3)));
            $manualSellTs = is_numeric($service['time_sell'] ?? null) ? (int) $service['time_sell'] : strtotime((string) ($service['time_sell'] ?? ''));
            $manualServiceTime = (int) ($service['Service_time'] ?? 0);
            $manualIsTest = (($service['name_product'] ?? '') === 'سرویس تست');
            $manualServiceSeconds = $manualIsTest ? ($manualServiceTime * 3600) : ($manualServiceTime * 86400);
            $manualExpire = ($manualSellTs !== false && $manualServiceTime > 0) ? ($manualSellTs + $manualServiceSeconds) : null;
            $Output = array(
                'status' => $service['Status'],
                'username' => $service['username'],
                'data_limit' => $data_limit,
                'expire' => $manualExpire,
                'online_at' => null,
                'used_traffic' => 0,
                'links' => [],
                'subscription_url' => is_array($configman) ? ($configman['contentrecord'] ?? '') : '',
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
                'file_ext' => is_array($configman) ? ($configman['file_ext'] ?? '') : '',
                'sub_link' => is_array($configman) ? ($configman['sub_link'] ?? '') : '',
                'manual_items' => $manualItems,
                'manual_sub_link' => $manualUnifiedSub,
                'uuid' => null
            );
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $UsernameData = get_userwg($username, $Get_Data_Panel['name_panel']);
            if (isset($UsernameData['status']) && $UsernameData['status'] === false && !isset($UsernameData['id'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => isset($UsernameData['msg']) ? $UsernameData['msg'] : ''
                );
            }
            $invoiceinfo = select("invoice", "*", "username", $username, "select");
            $infoconfig = isset($invoiceinfo['user_info']) ? json_decode($invoiceinfo['user_info'], true) : json_encode(array());
            if (!isset($UsernameData['id'])) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => isset($UsernameData['msg']) ? $UsernameData['msg'] : ''
                );
            } else {
                $jobtime = [];
                $jobvolume = [];
                foreach ($UsernameData['jobs'] as $job) {
                    if ($job['Field'] == "total_data") {
                        $jobvolume = $job;
                    } elseif ($job['Field'] == "date") {
                        $jobtime = $job;
                    }
                }
                if (isset($jobtime['Value'])) {
                    $expire = strtotime($jobtime['Value']);
                } else {
                    $expire = 0;
                }
                $status = "active";
                if (!$UsernameData['configuration']['Status'])
                    $status = "disabled";
                if ($expire != 0 and $expire - time() < 0) {
                    $status = "expired";
                }
                $data_useage = ($UsernameData['total_data'] * pow(1024, 3)) + ($UsernameData['cumu_data'] * pow(1024, 3));
                if (isset($jobvolume['Value']) && ($jobvolume['Value'] * pow(1024, 3)) < $data_useage) {
                    $status = "limited";
                }
                $download_config = downloadconfig($Get_Data_Panel['name_panel'], $UsernameData['id']);
                if (isset($download_config['status']) && $download_config['status'] === false) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => isset($download_config['msg']) ? $download_config['msg'] : ''
                    );
                }
                if (!empty($download_config['status']) && $download_config['status'] != 200) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $download_config['status']
                    );
                }
                if (!empty($download_config['error'])) {
                    return array(
                        'status' => 'Unsuccessful',
                        'msg' => $download_config['error']
                    );
                }
                $download_config = json_decode($download_config['body'], true)['data'];
                $Output = array(
                    'status' => $status,
                    'username' => $UsernameData['name'],
                    'data_limit' => $jobvolume['Value'] * pow(1024, 3),
                    'expire' => $expire,
                    'online_at' => null,
                    'used_traffic' => $data_useage,
                    'links' => [],
                    'subscription_url' => strval($download_config['file']),
                    'sub_updated_at' => null,
                    'sub_last_user_agent' => null,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            try {
                $localUser = remnawave_find_user($username);
                if (!$localUser) {
                    $Output = array('status' => 'Unsuccessful', 'msg' => 'remnawave user not found in db');
                } else {
                    if (empty($localUser['panel_user_id'])) {
                        error_log("[REMNAWAVE-DATAUSER-LOCAL-NOTFOUND] panel_user_id empty for username: " . $username . " (row id=" . ($localUser['id'] ?? '?') . ")");
                        $Output = array('status' => 'Unsuccessful', 'msg' => 'remnawave user id missing');
                    } else {
                        $mgr = new RemnawaveManager($Get_Data_Panel);
                        $apiRes = $mgr->getUserById($localUser['panel_user_id'], $pdo);
                        if (empty($apiRes['ok']) || !is_array($apiRes['data'])) {
                            error_log("[REMNAWAVE-DATAUSER-API-FAIL] getUserById failed for id " . $localUser['panel_user_id']);
                            $Output = array('status' => 'Unsuccessful', 'msg' => 'remnawave api fetch failed');
                        } else {
                            $d = $apiRes['data'];
                            $expireTs = !empty($d['expireAt']) ? strtotime((string) $d['expireAt']) : 0;
                            $trafficLimit = (int) ($d['trafficLimitBytes'] ?? 0);
                            $rawStatus = strtoupper((string) ($d['status'] ?? 'ACTIVE'));
                            $statusMap = array('ACTIVE' => 'active', 'DISABLED' => 'disabled', 'LIMITED' => 'limited', 'EXPIRED' => 'expired');
                            $accountStatus = $statusMap[$rawStatus] ?? 'active';
                            $subUrl = (string) ($d['subscriptionUrl'] ?? ($localUser['subscription_url'] ?? ''));
                            $rwFreshShort = (string) ($d['shortUuid'] ?? '');
                            if ($rwFreshShort !== '' && $rwFreshShort !== (string) ($localUser['short_uuid'] ?? '')) {
                                try {
                                    $rwSync = $pdo->prepare("UPDATE remnawave_users SET short_uuid = :su, subscription_url = :sub WHERE id = :id");
                                    $rwSync->execute([':su' => $rwFreshShort, ':sub' => $subUrl, ':id' => $localUser['id']]);
                                    $localUser['short_uuid'] = $rwFreshShort;
                                } catch (\Throwable $e) {
                                    error_log("[REMNAWAVE-DATAUSER-SYNC] " . $e->getMessage());
                                }
                            }
                            $rawConfigs = [];
                            if (function_exists('remnawave_get_configs')) {
                                $cfgRes = remnawave_get_configs($Get_Data_Panel['name_panel'], $username);
                                if (($cfgRes['status'] ?? '') === 'successful' && !empty($cfgRes['links']) && is_array($cfgRes['links'])) {
                                    $rawConfigs = array_values(array_filter($cfgRes['links'], function ($c) {
                                        return is_string($c) && trim($c) !== '';
                                    }));
                                }
                            }
                            $rwSublinkOn = (($Get_Data_Panel['sublink'] ?? '') === 'onsublink');
                            $linksOut = !empty($rawConfigs) ? $rawConfigs : ($rwSublinkOn ? [$subUrl] : []);
                            $rwTraffic = is_array($d['userTraffic'] ?? null) ? $d['userTraffic'] : [];
                            $rwOnlineAt = $rwTraffic['onlineAt'] ?? ($d['onlineAt'] ?? null);
                            $rwOnlineTs = (!empty($rwOnlineAt) && !is_numeric($rwOnlineAt)) ? strtotime((string) $rwOnlineAt) : (int) $rwOnlineAt;
                            $rwIsOnline = ($rwOnlineTs > 0) && ((time() - $rwOnlineTs) <= 300);
                            $rwNodeUuid = (string) ($rwTraffic['lastConnectedNodeUuid'] ?? ($d['lastConnectedNodeUuid'] ?? ''));
                            $rwNodeName = ($rwNodeUuid !== '' && function_exists('remnawave_node_name')) ? remnawave_node_name($Get_Data_Panel['name_panel'], $rwNodeUuid) : '';
                            $Output = array(
                                'status' => $accountStatus,
                                'username' => $username,
                                'data_limit' => $trafficLimit,
                                'used_traffic' => (int) ($rwTraffic['usedTrafficBytes'] ?? ($d['usedTrafficBytes'] ?? 0)),
                                'expire' => $expireTs ?: 0,
                                'data_limit_reset' => 'no_reset',
                                'account_status' => $accountStatus,
                                'online_at' => $rwOnlineAt,
                                'is_online' => $rwIsOnline,
                                'node_name' => $rwNodeName,
                                'links' => $linksOut,
                                'subscription_url' => $subUrl,
                                'sub_updated_at' => null,
                                'sub_last_user_agent' => null,
                                'configs' => $linksOut,
                                'hwid_limit' => (int) ($d['hwidDeviceLimit'] ?? 0),
                                'uuid' => $localUser['panel_user_id'],
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("[REMNAWAVE-DATAUSER-CRASH] Exception Message: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
                $Output = array('status' => 'Unsuccessful', 'msg' => 'remnawave exception');
            }
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $userResponse = rebeccaGetUser($Get_Data_Panel['name_panel'], $username);
            if ($userResponse['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $userResponse['msg']
                );
            }
            $userData = $userResponse['data'];
            if (!is_array($userData)) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => 'User not found'
                );
            }
            $rebeccaStatus = strtolower((string) ($userData['status'] ?? 'active'));
            if (!in_array($rebeccaStatus, ["active", "disabled", "expired", "limited", "on_hold"], true)) {
                $rebeccaStatus = "active";
            }
            $subscriptionUrl = $userData['subscription_url'] ?? '';
            if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $subscriptionUrl)) {
                $subscriptionUrl = rtrim((string) ($Get_Data_Panel['url_panel'] ?? ''), '/') . "/" . ltrim($subscriptionUrl, "/");
            }
            if (function_exists('rebeccaBuildDisplayLinks')) {
                $links = rebeccaBuildDisplayLinks($Get_Data_Panel['name_panel'], $userData, $username);
                if (empty($links) && !empty($subscriptionUrl)) {
                    $links = [$subscriptionUrl];
                }
            } else {
                $links = !empty($subscriptionUrl) ? [$subscriptionUrl] : [];
            }
            if ($inoice != false) {
                $subscriptionUrl = "https://$domainhosts/sub/" . $inoice['id_invoice'];
            }
            $Output = array(
                'status' => $rebeccaStatus,
                'username' => $userData['username'] ?? $username,
                'data_limit' => intval($userData['data_limit'] ?? 0),
                'expire' => intval($userData['expire'] ?? 0),
                'online_at' => $userData['online_at'] ?? null,
                'used_traffic' => intval($userData['used_traffic'] ?? 0),
                'links' => $links,
                'links_diagnostic' => array('panel_type' => 'rebecca', 'outcome' => empty($links) ? 'empty' : 'ok'),
                'subscription_url' => $subscriptionUrl,
                'sub_updated_at' => null,
                'sub_last_user_agent' => null,
                'uuid' => null,
                'data_limit_reset' => $userData['data_limit_reset_strategy'] ?? null,
                'ip_limit' => intval($userData['ip_limit'] ?? 0),
                'on_hold_expire_duration' => $userData['on_hold_expire_duration'] ?? null,
            );
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            $Output = jovadpanelDataUser($Get_Data_Panel, $username);
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function Revoke_sub($name_panel, $username)
    {
        $Output = array();
        $ManagePanel = new ManagePanel();
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if ($Get_Data_Panel['type'] == "marzban") {
            $revoke_sub = revoke_sub($username, $name_panel);
            if (!empty($revoke_sub['error'])) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['error']
                );
            } elseif (isset($revoke_sub['detail']) && $revoke_sub['detail']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['detail']
                );
            } else {
                $config = new ManagePanel();
                $Data_User = $config->DataUser($name_panel, $username);
                if (!preg_match('/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:\d+)?((\/[^\s\/]+)+)?$/', $Data_User['subscription_url'])) {
                    $Data_User['subscription_url'] = $Get_Data_Panel['url_panel'] . "/" . ltrim($Data_User['subscription_url'], "/");
                }
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'],
                    'subscription_url' => $Data_User['subscription_url']
                );
            }
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $revoke_sub = pasarguardRevokeSub($username, $name_panel);
            if (isset($revoke_sub['detail']) && $revoke_sub['detail']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['detail']
                );
            } else {
                $config = new ManagePanel();
                $Data_User = $config->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? null
                );
            }
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $revoke_sub = guardRevokeSubscriptions($name_panel, array($username));
            if ($revoke_sub['status'] === false) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke_sub['msg']
                );
            } else {
                $Data_User = $ManagePanel->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? null
                );
            }
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            $subId = bin2hex(random_bytes(8));
            $config = array(
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "id" => generateUUID(),
                                "enable" => true,
                                "subId" => $subId,
                            )
                        ),
                    )
                )
            );
            $updateinbound = $ManagePanel->Modifyuser($username, $Get_Data_Panel['name_panel'], $config);
            if (!$updateinbound['status']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => 'Unsuccessful'
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'configs' => [outputlunk($Get_Data_Panel['linksubx'] . "/{$subId}")],
                    'subscription_url' => $Get_Data_Panel['linksubx'] . "/{$subId}",
                );
            }
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            $revoke = remnawave_revoke($name_panel, $username);
            if (($revoke['status'] ?? '') !== 'successful') {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke['msg'] ?? 'revoke failed'
                );
            } else {
                $Data_User = $ManagePanel->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? ($revoke['subscription_url'] ?? '')
                );
            }
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $revoke = rebeccaRevokeSub($name_panel, $username);
            if ($revoke['status'] === false) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $revoke['msg']
                );
            } else {
                $Data_User = $ManagePanel->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? null
                );
            }
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            $rotate = jovadpanelRotate($Get_Data_Panel, $username);
            if (($rotate['status'] ?? '') !== 'successful') {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $rotate['msg'] ?? 'revoke failed'
                );
            } else {
                $Data_User = $ManagePanel->DataUser($name_panel, $username);
                $Output = array(
                    'status' => 'successful',
                    'configs' => $Data_User['links'] ?? array(),
                    'subscription_url' => $Data_User['subscription_url'] ?? ($rotate['subscription_url'] ?? null)
                );
            }
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function RemoveUser($name_panel, $username)
    {
        $Output = array();
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if ($Get_Data_Panel['type'] == "marzban") {
            $UsernameData = removeuser($Get_Data_Panel['name_panel'], $username);
            if (!empty($UsernameData['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            }
            $httpStatus = isset($UsernameData['status']) ? (int)$UsernameData['status'] : 0;
            if (in_array($httpStatus, [200, 201, 202, 204, 404], true)) {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            } else {
                $bodyDecoded = (!empty($UsernameData['body']) && is_string($UsernameData['body'])) ? json_decode($UsernameData['body'], true) : null;
                $msg = is_array($bodyDecoded) ? ($bodyDecoded['detail'] ?? ($bodyDecoded['message'] ?? $httpStatus)) : ($httpStatus ?: 'remove failed');
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $msg
                );
            }
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $UsernameData = pasarguardRemoveUser($Get_Data_Panel['name_panel'], $username);
            if (!empty($UsernameData['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            }
            $httpStatus = isset($UsernameData['status']) ? (int)$UsernameData['status'] : 0;
            if (in_array($httpStatus, [200, 201, 202, 204, 404], true)) {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            } else {
                $bodyDecoded = (!empty($UsernameData['body']) && is_string($UsernameData['body'])) ? json_decode($UsernameData['body'], true) : null;
                $msg = is_array($bodyDecoded) ? ($bodyDecoded['detail'] ?? ($bodyDecoded['message'] ?? $httpStatus)) : ($httpStatus ?: 'remove failed');
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $msg
                );
            }
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            $UsernameData = removeClient($Get_Data_Panel['name_panel'], $username);
            if (!empty($UsernameData['status']) && $UsernameData['status'] != 200) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['status']
                );
            } elseif (!empty($UsernameData['error'])) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['error']
                );
            }
            $UsernameData = json_decode($UsernameData['body'], true);
            if (!$UsernameData['success']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['msg']
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "Manualsale") {
            update("manualsell", "status", "delete", "username", $username);
            $Output = array(
                'status' => 'successful',
                'username' => $username,
            );
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $UsernameData = remove_userwg($Get_Data_Panel['name_panel'], $username);
            if (!$UsernameData['status']) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['msg']
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $UsernameData = guardDeleteSubscriptions($Get_Data_Panel['name_panel'], array($username));
            if ($UsernameData['status'] === false) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $UsernameData['msg']
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            $res = remnawave_remove_user($Get_Data_Panel['name_panel'], $username);
            if (($res['status'] ?? '') !== 'successful') {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $res['msg'] ?? 'remove failed'
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $removeResponse = rebeccaRemoveUser($Get_Data_Panel['name_panel'], $username);
            if ($removeResponse['status'] === false) {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $removeResponse['msg']
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            $removeResponse = jovadpanelRemoveUser($Get_Data_Panel, $username);
            if (($removeResponse['status'] ?? '') !== 'successful') {
                $Output = array(
                    'status' => 'Unsuccessful',
                    'msg' => $removeResponse['msg'] ?? 'remove failed'
                );
            } else {
                $Output = array(
                    'status' => 'successful',
                    'username' => $username,
                );
            }
        } else {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => 'Panel Not Found'
            );
        }
        return $Output;
    }
    function Modifyuser($username, $name_panel, $config = array())
    {

        $Output = array();
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if (!$Get_Data_Panel || !is_array($Get_Data_Panel) || empty($Get_Data_Panel['type'])) {
            return array(
                'status' => false,
                'msg' => 'Panel Not Found'
            );
        }
        if ($Get_Data_Panel['type'] == "marzban") {
            if ((string)($Get_Data_Panel['version_panel'] ?? '0') === '1') {
                $result = getuser($username, $name_panel);
                if (!empty($result['body'])) {
                    $result = json_decode($result['body'], true);
                    if (is_array($result) && isset($result['proxy_settings'])) {
                        $config['proxy_settings'] = $result['proxy_settings'];
                    }
                }
            }
            $modify = Modifyuser($name_panel, $username, $config);
            if (!empty($modify['error'])) {
                return array(
                    'status' => false,
                    'msg' => $modify['error']
                );
            } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $modify['status']
                );
            }
            $modifycheck = json_decode($modify['body'], true);
            if (!empty($modifycheck['detail'])) {
                return array(
                    'status' => false,
                    'msg' => $modifycheck['detail']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            $modify = pasarguardModifyUser($name_panel, $username, $config);
            if (!empty($modify['error'])) {
                return array(
                    'status' => false,
                    'msg' => $modify['error']
                );
            } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $modify['status']
                );
            }
            $modifycheck = json_decode($modify['body'], true);
            if (!empty($modifycheck['detail'])) {
                return array(
                    'status' => false,
                    'msg' => $modifycheck['detail']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            if (xui_panel_uses_token($Get_Data_Panel)) {
                $fullClient = xui_get_full_client($Get_Data_Panel, $username);
                if (empty($fullClient['status'])) {
                    return array(
                        'status' => false,
                        'msg' => $fullClient['msg'] ?? 'User not found'
                    );
                }
                $baseClient = $fullClient['client'];
                $configs = array(
                    'settings' => json_encode(array('clients' => array($baseClient))),
                );
                $configs['settings'] = json_encode(array_replace_recursive(json_decode($configs['settings'], true), json_decode($config['settings'], true)));
                $modify = updateClient($Get_Data_Panel['name_panel'], $baseClient['uuid'] ?? ($baseClient['id'] ?? ''), $configs);
                if (!empty($modify['error'])) {
                    return array(
                        'status' => false,
                        'msg' => $modify['error']
                    );
                } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                    return array(
                        'status' => false,
                        'msg' => 'error code : ' . $modify['status']
                    );
                }
                $modify = json_decode($modify['body'], true);
                if (!$modify['success']) {
                    return array(
                        'status' => false,
                        'msg' => 'error :' . $modify['msg']
                    );
                }
                return array(
                    'status' => true,
                    'data' => $modify
                );
            }
            $clients = get_clinets($username, $name_panel);
            if (!empty($clients['error'])) {
                return array(
                    'status' => false,
                    'msg' => $clients['error']
                );
            } elseif (!empty($clients['status']) && $clients['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => json_encode($clients)
                );
            }
            $clients = json_decode($clients['body'], true);
            if (!is_array($clients)) {
                return array(
                    'status' => false,
                    'msg' => 'object invalid'
                );
            }
            if (empty($clients['obj'])) {
                return array(
                    'status' => false,
                    'msg' => "User not found"
                );
            }
            $clients = $clients['obj'];
            $configs = array(
                'id' => intval($clients['inboundId']),
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "id" => $clients['uuid'],
                                "flow" => "",
                                "email" => $clients['email'],
                                "totalGB" => $clients['total'],
                                "expiryTime" => $clients['expiryTime'],
                                "enable" => true,
                                "subId" => $clients['subId'],
                            )
                        ),
                        'decryption' => 'none',
                        'fallbacks' => array(),
                    )
                ),
            );
            $configs['settings'] = json_encode(array_replace_recursive(json_decode($configs['settings'], true), json_decode($config['settings'], true)));
            $modify = updateClient($Get_Data_Panel['name_panel'], $clients['uuid'], $configs);
            if (!empty($modify['error'])) {
                return array(
                    'status' => false,
                    'msg' => $modify['error']
                );
            } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $modify['status']
                );
            }
            $modify = json_decode($modify['body'], true);
            if (!$modify['success']) {
                return array(
                    'status' => false,
                    'msg' => 'error :' . $modify['msg']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "WGDashboard") {
            $data_user = get_userwg($username, $name_panel);
            if (isset($data_user['status']) && $data_user['status'] === false && !isset($data_user['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($data_user['msg']) ? $data_user['msg'] : ''
                );
            }
            $configs = array(
                "DNS" => $data_user['DNS'],
                "allowed_ip" => $data_user['allowed_ip'],
                "endpoint_allowed_ip" => "0.0.0.0/0",
                "jobs" => $data_user['jobs'],
                "id" => $data_user['id'],
                "keepalive" => $data_user['keepalive'],
                "mtu" => $data_user['mtu'],
                "name" => $data_user['name'],
                "preshared_key" => $data_user['preshared_key'],
                "private_key" => $data_user['private_key']
            );
            $configs = array_merge($configs, $config);
            $modify = updatepear($Get_Data_Panel['name_panel'], $configs);
            if (isset($modify['status']) && $modify['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($modify['msg']) ? $modify['msg'] : ''
                );
            }
            if (!empty($modify['error'])) {
                return array(
                    'status' => false,
                    'msg' => $modify['error']
                );
            } elseif (!empty($modify['status']) && $modify['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $modify['status']
                );
            }
            $modify = json_decode($modify['body'], true);
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "guard") {
            if (isset($config['limit_expire'])) {
                $config['limit_expire'] = guardNormalizeExpire($config['limit_expire']);
            }
            $modify = guardUpdateSubscription($Get_Data_Panel['name_panel'], $username, $config);
            if ($modify['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $modify['msg']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $modify = rebeccaUpdateUser($Get_Data_Panel['name_panel'], $username, $config);
            if ($modify['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $modify['msg']
                );
            }
            return array(
                'status' => true,
                'data' => $modify
            );
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            return jovadpanelModifyUser($Get_Data_Panel, $username, is_array($config) ? $config : array());
        }
    }
    function Change_status($username, $name_panel)
    {
        $ManagePanel = new ManagePanel();
        $DataUserOut = $ManagePanel->DataUser($name_panel, $username);
        $Get_Data_Panel = $this->loadPanel($name_panel, "name_panel");
        if ($DataUserOut['status'] == "Unsuccessful") {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => $DataUserOut['detail']
            );
            return;
        }
        if (!in_array($DataUserOut['status'], ["active", "disabled", "expired", "limited", "on_hold"])) {
            $Output = array(
                'status' => 'Unsuccessful',
                'msg' => "status invalid"
            );
            return;
        }
        if ($DataUserOut['status'] === 'active' && !$this->HasLiveConnectionHistory($Get_Data_Panel, $DataUserOut)) {
            return array(
                'status' => 'Unsuccessful',
                'code' => 'not_connected',
                'msg' => 'ابتدا به کانفیگ متصل شوید و سپس مجدداً تلاش کنید.'
            );
        }
        if ($Get_Data_Panel['type'] == "marzban") {
            if ($DataUserOut['status'] == "active") {
                $status = "disabled";
            } else {
                $status = "active";
            }
            $configs = array("status" => $status);
            $ManagePanel->Modifyuser($username, $name_panel, $configs);
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "pasarguard") {
            if ($DataUserOut['status'] == "active") {
                $status = "disabled";
            } else {
                $status = "active";
            }
            $configs = array("status" => $status);
            $ManagePanel->Modifyuser($username, $name_panel, $configs);
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "x-ui_single") {
            if ($DataUserOut['status'] == "active") {
                $status = false;
            } else {
                $status = true;
            }
            $configs = array(
                'settings' => json_encode(array(
                    'clients' => array(
                        array(
                            "enable" => $status,
                        )
                    ),
                )),
            );
            $ManagePanel->Modifyuser($username, $name_panel, $configs);
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "guard") {
            $action = $DataUserOut['status'] == "active" ? "disable" : "enable";
            $toggle = guardToggleSubscriptions($Get_Data_Panel['name_panel'], array($username), $action);
            if ($toggle['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $toggle['msg'] ?? 'Toggle failed'
                );
            }
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "remnawave") {
            $local = remnawave_find_user($username);
            $isActive = is_array($local) ? ((string) ($local['status'] ?? 'active') === 'active') : true;
            $res = remnawave_change_status($Get_Data_Panel['name_panel'], $username, !$isActive);
            if (($res['status'] ?? '') !== 'successful') {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $res['msg'] ?? 'Toggle failed'
                );
            }
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "rebecca") {
            $wantEnable = $DataUserOut['status'] != "active";
            $toggle = rebeccaChangeStatus($Get_Data_Panel['name_panel'], $username, $wantEnable);
            if ($toggle['status'] === false) {
                return array(
                    'status' => 'Unsuccessful',
                    'msg' => $toggle['msg'] ?? 'Toggle failed'
                );
            }
            $Output = array(
                'status' => 'successful',
                'msg' => null
            );
        } elseif ($Get_Data_Panel['type'] == "jovadpanel") {
            return jovadpanelSetStatus($Get_Data_Panel, $username, $DataUserOut);
        }

        return $Output;
    }
    function ResetUserDataUsage($username, $name_panel)
    {
        $panel = $this->loadPanel($name_panel, "name_panel");
        if ($panel == false) {
            return array(
                'status' => false,
                'msg' => 'data not found'
            );
        }
        if ($panel['type'] == "marzban") {
            $reset = ResetUserDataUsage($username, $panel['name_panel']);
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode($reset['body'], true);
            if (!empty($reset['detail'])) {
                return array(
                    'status' => false,
                    'msg' => $reset['detail']
                );
            }
            return array(
                'status' => true,
                'msg' => 'successful'
            );
        } elseif ($panel['type'] == "pasarguard") {
            $reset = pasarguardResetUserDataUsage($username, $panel['name_panel']);
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode($reset['body'], true);
            if (!empty($reset['detail'])) {
                return array(
                    'status' => false,
                    'msg' => $reset['detail']
                );
            }
            return array(
                'status' => true,
                'msg' => 'successful'
            );
        } elseif ($panel['type'] == 'x-ui_single') {
            $reset = ResetUserDataUsagex_uisin($username, $panel['name_panel']);
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode($reset['body'], true);
            if (!$reset['success']) {
                return array(
                    'status' => false,
                    'msg' => 'error :' . $reset['msg']
                );
            }
            return array(
                'status' => true,
                'data' => $reset
            );
        } elseif ($panel['type'] == "WGDashboard") {
            $allowResponse = allowAccessPeers($panel['name_panel'], $username);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = get_userwg($username, $panel['name_panel']);
            if (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($datauser['msg']) ? $datauser['msg'] : ''
                );
            }
            $reset = ResetUserDataUsagewg($datauser['id'], $panel['name_panel']);
            if (isset($reset['status']) && $reset['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($reset['msg']) ? $reset['msg'] : ''
                );
            }
            if (!empty($reset['status']) && $reset['status'] != 200) {
                return array(
                    'status' => false,
                    'msg' => 'error code : ' . $reset['status']
                );
            } elseif (!empty($reset['error'])) {
                return array(
                    'status' => false,
                    'msg' => 'error  : ' . $reset['error']
                );
            }
            $reset = json_decode($reset['body'], true);
            return array(
                'status' => true,
                'data' => $reset
            );
        } elseif ($panel['type'] == "guard") {
            $reset = guardResetSubscriptions($panel['name_panel'], array($username));
            if ($reset['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $reset['msg']
                );
            }
            return array(
                'status' => true
            );
        } elseif ($panel['type'] == "remnawave") {
            global $pdo;
            $local = remnawave_find_user($username);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $res = $mgr->resetTraffic($local['panel_user_id'], $pdo);
            if (empty($res['ok'])) {
                return array('status' => false, 'msg' => 'remnawave reset traffic failed');
            }
            return array('status' => true);
        } elseif ($panel['type'] == "rebecca") {
            $reset = rebeccaResetUsage($panel['name_panel'], $username);
            if ($reset['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $reset['msg']
                );
            }
            return array(
                'status' => true
            );
        } elseif ($panel['type'] == "jovadpanel") {
            $reset = jovadpanelResetUsage($panel, $username);
            if (($reset['status'] ?? false) !== true) {
                return array(
                    'status' => false,
                    'msg' => $reset['msg'] ?? 'reset failed'
                );
            }
            return array(
                'status' => true,
                'data' => $reset['data'] ?? null
            );
        }
    }
    function extend($Method_extend, $new_limit, $time_day, $username, $code_product, $name_panel, $rxTarget = null, $rxOptions = array())
    {
        $rxOptions = is_array($rxOptions) ? $rxOptions : array();
        $panel = $this->loadPanel($name_panel, "code_panel");
        $code_product = rxRenewalNormalizeProductCode($code_product);
        $product = $code_product === 'custom_volume' ? array() : select("product", "*", "code_product", $code_product, "select");
        if (!is_array($product)) {
            $product = array();
        }
        $rxLogBase = array(
            'username' => (string) $username,
            'method' => (string) $Method_extend,
            'volume_gb' => $new_limit,
            'days' => $time_day,
            'ref' => (string) ($rxOptions['ref'] ?? ''),
            'operation_id' => (string) ($rxOptions['op_id'] ?? ''),
        );
        if ($panel == false) {
            rx_renewal_log('extend', 'panel_not_found', $rxLogBase + array('panel_name' => (string) $name_panel, 'mutation' => 'none'));
            return array(
                'status' => false,
                'code' => 'panel_not_found',
                'no_mutation' => true,
                'msg' => 'data not found'
            );
        }
        $rxLogBase['panel'] = $panel;
        $invoice = $this->rxRenewalInvoiceRow($username, $panel);
        $rxLogBase['invoice_id'] = is_array($invoice) ? (string) ($invoice['id_invoice'] ?? '') : '';
        $rxLogBase['invoice_status'] = is_array($invoice) ? ($invoice['Status'] ?? null) : null;
        $data_user = $this->DataUser($panel['name_panel'], $username);
        if (!is_array($data_user) || ($data_user['status'] ?? '') == "Unsuccessful") {
            $rxReadMsg = is_array($data_user) ? ($data_user['msg'] ?? '') : 'invalid panel response';
            $rxReadCode = rx_pf_user_missing_message(is_scalar($rxReadMsg) ? (string) $rxReadMsg : '') ? 'user_missing' : 'panel_read_failed';
            rx_renewal_log('extend', $rxReadCode, $rxLogBase + array('snapshot_error' => is_scalar($rxReadMsg) ? (string) $rxReadMsg : 'non-scalar panel message', 'mutation' => 'none'));
            return array(
                'status' => false,
                'code' => rx_pf_user_missing_message(is_scalar($rxReadMsg) ? (string) $rxReadMsg : '') ? 'user_missing' : 'panel_read_failed',
                'no_mutation' => true,
                'msg' => $rxReadMsg
            );
        }
        $rxMode = rxRenewalMode($Method_extend);
        if (($rxOptions['source'] ?? '') === 'payment' && rxReservationStoredSelection($rxOptions['reservation'] ?? null) !== null) {
            $rxMode = rxRenewalMode('رزرو اشتراک');
        }
        if ($rxMode === null) {
            rx_renewal_log('extend', 'unsupported_renewal_method', $rxLogBase + array('mutation' => 'none'));
            return array(
                'status' => false,
                'code' => 'unsupported_renewal_method',
                'no_mutation' => true,
                'msg' => 'unsupported renewal method: ' . (string) $Method_extend
            );
        }
        $rxSnapshot = rx_pf_snapshot_from_datauser($data_user, (string) $panel['type']);
        $rxPolicy = (string) ($rxMode['policy'] ?? '');
        $rxResetUsage = !empty($rxMode['reset_usage']);
        if (!empty($rxMode['queued']) && !is_array($rxTarget)) {
            $rxRegistered = $this->rxRenewalRegisterReservation($panel, $invoice, $product, $code_product, $new_limit, $time_day, $username, $rxSnapshot, $rxOptions);
            if ($rxRegistered !== null) {
                if (empty($rxRegistered['status'])) {
                    rx_renewal_log('extend_reservation', (string) ($rxRegistered['code'] ?? 'reservation_refused'), $rxLogBase + (($rxRegistered['code'] ?? '') === 'admin_blocked' ? array('admin_blocked' => true) : array()) + array('snapshot' => $rxSnapshot, 'error' => $rxRegistered['msg'] ?? null, 'detail' => $rxRegistered['detail'] ?? null, 'mutation' => 'none'));
                }
                return $rxRegistered;
            }
            $rxSelection = (array) ($rxOptions['reservation'] ?? array());
            $rxPolicy = (string) ($rxSelection['policy'] ?? 'reset_all');
            $rxResetUsage = !empty($rxSelection['reset_usage']) && rxRenewalPolicyAllowsUsageReset($rxPolicy);
        }
        if (($rxOptions['source'] ?? '') === 'admin' && empty($rxMode['queued']) && isset($rxOptions['policy']) && isset(rxRenewalPolicies()[(string) $rxOptions['policy']])) {
            $rxPolicy = (string) $rxOptions['policy'];
            $rxResetUsage = false;
        }
        if ($panel['type'] == "Manualsale") {
            global $pdo;
            $statement = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :code_panel AND status = 'active' AND codeproduct = :code_product ORDER BY RAND() LIMIT 1");
            $statement->execute(array(
                ':code_panel' => $panel['code_panel'],
                ':code_product' => $code_product,
            ));
            $newConfig = $statement->fetch(PDO::FETCH_ASSOC);
            if ($newConfig == false) {
                $statement2 = $pdo->prepare("SELECT * FROM manualsell WHERE codepanel = :code_panel AND status = 'active' ORDER BY RAND() LIMIT 1");
                $statement2->execute(array(':code_panel' => $panel['code_panel']));
                $newConfig = $statement2->fetch(PDO::FETCH_ASSOC);
            }
            if ($newConfig == false) {
                rx_renewal_log('extend', 'manual_stock_empty', $rxLogBase + array('mutation' => 'none'));
                return array(
                    'status' => false,
                    'code' => 'manual_stock_empty',
                    'no_mutation' => true,
                    'msg' => 'no manual config available in stock for renewal'
                );
            }

            if (!empty($newConfig['group_id'])) {
                $release = $pdo->prepare("UPDATE manualsell SET status = 'delete', username = NULL WHERE username = :username AND (group_id IS NULL OR group_id <> :group_id)");
                $release->execute(array(
                    ':username' => $username,
                    ':group_id' => $newConfig['group_id'],
                ));
                $claimGroup = $pdo->prepare("UPDATE manualsell SET status = 'selled', username = :username WHERE group_id = :group_id AND status = 'active'");
                $claimGroup->execute(array(
                    ':username' => $username,
                    ':group_id' => $newConfig['group_id'],
                ));
            } else {
                $release = $pdo->prepare("UPDATE manualsell SET status = 'delete', username = NULL WHERE username = :username AND id <> :new_id");
                $release->execute(array(
                    ':username' => $username,
                    ':new_id' => $newConfig['id'],
                ));

                update("manualsell", "status", "selled", "id", $newConfig['id']);
                update("manualsell", "username", $username, "id", $newConfig['id']);
            }

            $notifctions = json_encode(array(
                'volume' => false,
                'time' => false,
            ));
            if (is_array($invoice)) {
                update("invoice", "notifctions", $notifctions, 'id_invoice', $invoice['id_invoice']);
            }
            update("invoice", 'Status', "active", "username", $username);
            return array(
                'status' => true,
                'subscription_url' => $newConfig['contentrecord'],
                'configs' => "",
                'file_ext' => $newConfig['file_ext']
            );
        }
        if (is_array($rxTarget) && !empty($rxTarget['rx_renewal_plan']) && !empty($rxTarget['ok'])) {
            $rxPlan = $rxTarget;
        } else {
            if (empty($rxSnapshot['ok']) || empty($rxSnapshot['exists'])) {
                rx_renewal_log('extend', 'panel_state_unavailable', $rxLogBase + array('snapshot' => $rxSnapshot, 'snapshot_error' => $rxSnapshot['error'] ?? null, 'mutation' => 'none'));
                return array(
                    'status' => false,
                    'code' => 'panel_state_unavailable',
                    'no_mutation' => true,
                    'msg' => 'panel state unavailable: ' . (string) ($rxSnapshot['error'] ?? '')
                );
            }
            $rxPlan = rxRenewalPlanForSnapshot($rxSnapshot, $panel, (string) $username, $rxPolicy, $rxResetUsage, $new_limit, $time_day, (string) $rxMode['method'], null, array(
                'stage' => 'extend_plan',
                'invoice_id' => $rxLogBase['invoice_id'],
                'ref' => $rxLogBase['ref'],
                'operation_id' => $rxLogBase['operation_id'],
            ));
            if (empty($rxPlan['ok'])) {
                return array(
                    'status' => false,
                    'code' => (string) ($rxPlan['reason'] ?? 'renewal_refused'),
                    'no_mutation' => true,
                    'plan' => $rxPlan,
                    'msg' => 'renewal refused: ' . (string) ($rxPlan['reason'] ?? '')
                );
            }
        }
        return $this->applyRenewalPlan($panel, $data_user, $invoice, $product, $rxPlan, array_merge($rxOptions, array(
            'username' => (string) $username,
            'method' => (string) $Method_extend,
            'product_name' => (string) ($product['name_product'] ?? ''),
        )));
    }

    private function rxRenewalRegisterReservation(array $panel, $invoice, array $product, $code_product, $new_limit, $time_day, $username, array $snapshot, array $rxOptions)
    {
        $paymentRef = (string) (($rxOptions['reservation']['payment_ref'] ?? '') ?: ($rxOptions['ref'] ?? ''));
        $existing = $paymentRef !== '' ? rxReservationByPaymentRef($paymentRef) : null;
        if (is_array($existing)) {
            return $this->rxRenewalReservationResult($existing);
        }
        $isManual = $panel['type'] == "Manualsale";
        $stored = (string) ($rxOptions['source'] ?? '') === 'payment' ? rxReservationStoredSelection($rxOptions['reservation'] ?? null) : null;
        $selection = $stored !== null ? $stored : rxReservationPanelSelection($panel);
        if ($isManual) {
            $selection['policy'] = 'reset_all';
            $selection['reset_usage'] = false;
        }
        $normalized = rxReservationNormalizeSelection($selection, $isManual ? null : $snapshot, time(), $stored !== null);
        if (empty($normalized['ok'])) {
            return array(
                'status' => false,
                'code' => (string) $normalized['reason'],
                'no_mutation' => true,
                'detail' => (string) ($normalized['detail'] ?? ''),
                'msg' => 'reservation refused: ' . (string) $normalized['reason']
            );
        }
        if ($normalized['trigger'] === 'immediate') {
            return null;
        }
        $context = rxRenewalContext($panel, (string) $username);
        if ($context['admin_blocked'] || ($snapshot['status'] ?? '') === 'disabled') {
            return array('status' => false, 'code' => 'admin_blocked', 'no_mutation' => true, 'msg' => 'service is blocked by an administrator');
        }
        if (!is_numeric($new_limit) || !is_numeric($time_day) || (float) $new_limit < 0 || (float) $time_day < 0) {
            return array('status' => false, 'code' => 'invalid_purchase', 'no_mutation' => true, 'msg' => 'invalid purchased quantities');
        }
        $invoice = is_array($invoice) ? $invoice : array();
        $registered = rxReservationRegister(array(
            'service_key' => rx_rn_service_key($invoice['id_invoice'] ?? '', $panel['code_panel'], $username),
            'id_user' => (string) ($invoice['id_user'] ?? ($rxOptions['id_user'] ?? '')),
            'username' => (string) $username,
            'name_panel' => (string) $panel['name_panel'],
            'code_panel' => (string) $panel['code_panel'],
            'code_product' => (string) $code_product,
            'new_limit_gb' => (string) $new_limit,
            'time_day' => (string) $time_day,
            'price' => (string) ($rxOptions['price'] ?? ($product['price_product'] ?? '0')),
            'id_invoice' => $invoice['id_invoice'] ?? null,
            'bottype' => $invoice['bottype'] ?? null,
            'trigger' => $normalized['trigger'],
            'conditions' => $normalized['conditions'],
            'trigger_at' => $normalized['trigger_at'],
            'policy' => $normalized['policy'],
            'reset_usage' => $normalized['reset_usage'],
            'payment_ref' => $paymentRef,
            'fulfillment' => array(
                'product_name' => (string) ($product['name_product'] ?? ($invoice['name_product'] ?? '')),
                'inbounds' => $product['inbounds'] ?? null,
                'panel_type' => (string) $panel['type'],
                'source' => (string) ($rxOptions['source'] ?? ''),
            ),
        ));
        if (empty($registered['ok'])) {
            return array(
                'status' => false,
                'code' => (string) ($registered['code'] ?? 'queued_renewal_insert_failed'),
                'no_mutation' => true,
                'msg' => (string) ($registered['msg'] ?? ($registered['code'] ?? 'reservation failed'))
            );
        }
        $row = is_array($registered['row'] ?? null) ? $registered['row'] : rxReservationFetch((int) $registered['id']);
        return $this->rxRenewalReservationResult(is_array($row) ? $row : array(
            'id' => (int) $registered['id'],
            'trigger_type' => $normalized['trigger'],
            'conditions' => implode(',', $normalized['conditions']),
            'trigger_at' => $normalized['trigger_at'],
            'policy' => $normalized['policy'],
            'reset_usage' => $normalized['reset_usage'] ? 1 : 0,
        ));
    }

    private function rxRenewalReservationResult(array $row)
    {
        $conditions = rxReservationRowConditions($row);
        $triggerAt = isset($row['trigger_at']) && $row['trigger_at'] !== null ? (int) $row['trigger_at'] : null;
        $policy = (string) ($row['policy'] ?? 'reset_all');
        return array(
            'status' => true,
            'queued' => true,
            'reservation_id' => (int) $row['id'],
            'reservation' => array(
                'trigger' => (string) ($row['trigger_type'] ?? rxReservationTriggerForConditions($conditions)),
                'conditions' => $conditions,
                'trigger_at' => $triggerAt,
                'policy' => $policy,
                'reset_usage' => !empty($row['reset_usage']),
                'trigger_label' => rxReservationActivationText($conditions, $triggerAt),
                'policy_label' => isset(rxRenewalPolicies()[$policy]) ? rxRenewalPolicyLabel($policy, !empty($row['reset_usage'])) : $policy,
                'confirmation' => rxReservationConfirmationText($conditions, $triggerAt),
            ),
        );
    }

    private function rxRenewalInvoiceRow($username, $panel)
    {
        global $pdo;
        if ($pdo instanceof PDO && is_array($panel)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND Service_location = :location ORDER BY time_sell DESC LIMIT 1");
                $stmt->execute(array(':username' => (string) $username, ':location' => (string) $panel['name_panel']));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return $row;
                }
            } catch (Throwable $e) {
            }
        }
        $row = select("invoice", "*", "username", $username, "select");
        return is_array($row) ? $row : null;
    }

    public function rxRenewalLocalSuccess($panel, $username, $idInvoice = '')
    {
        $invoice = ((string) $idInvoice !== '') ? select("invoice", "*", "id_invoice", $idInvoice, "select") : null;
        if (!is_array($invoice)) {
            $invoice = $this->rxRenewalInvoiceRow($username, $panel);
        }
        if (!is_array($invoice)) {
            return;
        }
        update("invoice", "notifctions", json_encode(array('volume' => false, 'time' => false)), 'id_invoice', $invoice['id_invoice']);
        if (($panel['type'] ?? '') != "WGDashboard") {
            update("invoice", 'user_info', null, "id_invoice", $invoice['id_invoice']);
        }
        update("invoice", 'uuid', null, "id_invoice", $invoice['id_invoice']);
        if (($invoice['Status'] ?? '') !== 'disablebyadmin') {
            update("invoice", 'Status', "active", "id_invoice", $invoice['id_invoice']);
        }
    }

    public function applyStoredRenewalPlan($name_panel, $username, array $plan, array $opts = array())
    {
        $panel = $this->loadPanel((string) $name_panel, 'name_panel');
        if (!is_array($panel)) {
            return array('status' => false, 'code' => 'panel_not_found', 'no_mutation' => true, 'msg' => 'Panel Not Found');
        }
        $data_user = $this->DataUser($panel['name_panel'], $username);
        if (!is_array($data_user) || ($data_user['status'] ?? '') == "Unsuccessful") {
            return array('status' => false, 'code' => 'panel_read_failed', 'no_mutation' => true, 'msg' => is_array($data_user) ? ($data_user['msg'] ?? '') : '');
        }
        $invoice = !empty($opts['id_invoice']) ? select("invoice", "*", "id_invoice", $opts['id_invoice'], "select") : null;
        if (!is_array($invoice)) {
            $invoice = $this->rxRenewalInvoiceRow($username, $panel);
        }
        $code = rxRenewalNormalizeProductCode($opts['code_product'] ?? '');
        $product = ($code !== '' && $code !== 'custom_volume') ? select("product", "*", "code_product", $code, "select") : array();
        if (!is_array($product)) {
            $product = array();
        }
        $fulfillment = is_array($opts['fulfillment'] ?? null) ? $opts['fulfillment'] : array();
        if (empty($product['inbounds']) && !empty($fulfillment['inbounds'])) {
            $product['inbounds'] = $fulfillment['inbounds'];
        }
        return $this->applyRenewalPlan($panel, $data_user, $invoice, $product, $plan, array_merge($opts, array('username' => (string) $username)));
    }

    public function applyRenewalPlan(array $panel, array $data_user, $invoice, $product, array $plan, array $opts = array())
    {
        $username = (string) ($opts['username'] ?? ($data_user['username'] ?? ''));
        $invoice = is_array($invoice) ? $invoice : array();
        $product = is_array($product) ? $product : array();
        $idInvoice = (string) ($invoice['id_invoice'] ?? ($opts['id_invoice'] ?? ''));
        $serviceKey = (string) ($opts['service_key'] ?? rx_rn_service_key($idInvoice, $panel['code_panel'] ?? '', $username));
        $opId = (string) ($opts['op_id'] ?? rx_pf_new_token());
        if (($panel['type'] ?? '') === 'jovadpanel' && in_array((string) ($opts['source'] ?? ''), ['payment', 'reservation'], true) && trim((string) ($opts['ref'] ?? '')) !== '') {
            $opId = hash('sha256', 'jovad|' . $opts['source'] . '|' . $opts['ref'] . '|' . $serviceKey);
        }
        $lockOwner = isset($opts['lock_owner']) ? (string) $opts['lock_owner'] : '';
        $ownLock = $lockOwner === '';
        $source = (string) ($opts['source'] ?? 'wallet');
        $diag = array(
            'operation_id' => $opId,
            'service_key' => $serviceKey,
            'username' => $username,
            'panel' => (string) $panel['name_panel'],
            'panel_type' => (string) $panel['type'],
            'method' => (string) ($opts['method'] ?? ''),
            'policy' => (string) ($plan['policy'] ?? ''),
            'source' => $source,
            'ref' => (string) ($opts['ref'] ?? ''),
        );
        $before = array(
            'data_limit' => rx_pf_int($plan['before_data_limit'] ?? 0),
            'expire' => rx_pf_int($plan['before_expire'] ?? 0),
            'used_traffic' => rx_pf_int($plan['before_used_traffic'] ?? 0),
            'status' => (string) ($plan['before_status'] ?? ''),
        );
        $target = array(
            'data_limit' => rx_pf_int($plan['data_limit'] ?? 0),
            'expire' => rx_pf_int($plan['expire'] ?? 0),
            'used_traffic' => rx_pf_int($plan['target_used_traffic'] ?? 0),
            'reset_usage' => !empty($plan['reset_usage']),
            'pending_duration' => $plan['target_pending_duration'] ?? null,
        );
        $rxLogFail = function ($stage, $reason, $mutation, $error = null) use ($panel, $username, $idInvoice, $invoice, $plan, $diag, $before) {
            rx_renewal_log($stage, $reason, array(
                'panel' => $panel,
                'username' => $username,
                'invoice_id' => $idInvoice,
                'invoice_status' => $invoice['Status'] ?? null,
                'method' => $diag['method'],
                'policy' => $diag['policy'],
                'plan' => $plan,
                'before' => $before,
                'operation_id' => $diag['operation_id'],
                'ref' => $diag['ref'],
                'mutation' => $mutation,
                'error' => $error,
            ));
        };
        if ($ownLock && !rx_rn_lock_acquire($serviceKey, $opId)) {
            $rxLogFail('apply_lock', 'renewal_in_progress', 'none');
            return array('status' => false, 'code' => 'renewal_in_progress', 'no_mutation' => true, 'operation_id' => $opId, 'msg' => 'another renewal operation is running for this service');
        }
        try {
            if (!rx_rn_op_start(array(
                'op_id' => $opId,
                'source' => $source,
                'ref' => $opts['ref'] ?? null,
                'service_key' => $serviceKey,
                'username' => $username,
                'name_panel' => (string) $panel['name_panel'],
                'id_invoice' => $idInvoice,
                'method' => $opts['method'] ?? null,
                'policy' => $plan['policy'] ?? null,
                'before' => $before,
                'target' => (trim((string) ($opts['product_name'] ?? '')) !== '' && !isset($plan['rx_product_name'])) ? array_merge($plan, array('rx_product_name' => trim((string) $opts['product_name']))) : $plan,
                'finalize' => is_array($opts['finalize'] ?? null) ? $opts['finalize'] : null,
            ))) {
                $rxLogFail('apply_persist', 'operation_persist_failed', 'none');
                return array('status' => false, 'code' => 'operation_persist_failed', 'no_mutation' => true, 'operation_id' => $opId, 'msg' => 'renewal operation could not be persisted');
            }
            $resetDone = false;
            if (!empty($plan['reset_usage']) && ($panel['type'] ?? '') !== 'jovadpanel' && rx_pf_int($data_user['used_traffic'] ?? 0) > 0) {
                rx_rn_op_update($opId, array('stage' => 'reset_sent'));
                $reset = $this->ResetUserDataUsage($username, $panel['name_panel']);
                if (!is_array($reset) || empty($reset['status'])) {
                    $resetMsg = is_array($reset) ? (is_scalar($reset['msg'] ?? null) ? (string) $reset['msg'] : json_encode($reset['msg'] ?? '')) : '';
                    $probe = rx_pf_panel_snapshot($this, (string) $panel['name_panel'], $username, (string) $panel['type']);
                    if (empty($probe['ok']) || empty($probe['exists'])) {
                        return $this->rxRenewalUncertain($opId, $plan, $before, null, 'reset_result_unknown: ' . $resetMsg, $diag);
                    }
                    $probeUsed = rx_pf_int($probe['used_traffic']);
                    if ($probeUsed > RX_RN_USAGE_SLACK && $probeUsed + RX_RN_USAGE_SLACK > rx_pf_int($data_user['used_traffic'] ?? 0)) {
                        rx_rn_op_update($opId, array('state' => 'failed', 'stage' => 'reset_failed', 'last_error' => $resetMsg));
                        $rxLogFail('apply_reset', 'reset_failed', 'attempted_not_applied', $resetMsg);
                        return array('status' => false, 'code' => 'reset_failed', 'no_mutation' => true, 'operation_id' => $opId, 'msg' => 'error reset : ' . $resetMsg);
                    }
                }
                $resetDone = true;
                rx_rn_op_update($opId, array('stage' => 'reset_done'));
            }
            rx_rn_op_update($opId, array('stage' => 'modify_sent'));
            $write = $this->rxRenewalPanelWrite($panel, $data_user, $invoice, $product, $plan, $username, $opId);
            if (empty($write['status'])) {
                $writeMsg = is_scalar($write['msg'] ?? null) ? (string) $write['msg'] : json_encode($write['msg'] ?? '');
                if (($panel['type'] ?? '') === 'jovadpanel' && !empty($write['uncertain'])) {
                    return $this->rxRenewalUncertain($opId, $plan, $before, null, $writeMsg, $diag);
                }
                $probe = rx_pf_panel_snapshot($this, (string) $panel['name_panel'], $username, (string) $panel['type']);
                $class = rxRenewalClassify($probe, $plan);
                $untouched = !empty($probe['ok']) && !empty($probe['exists'])
                    && rx_pf_int($probe['data_limit']) === $before['data_limit']
                    && rx_pf_int($probe['expire']) === $before['expire']
                    && (bool) ($probe['pending'] ?? false) === (bool) ($plan['before_pending'] ?? false);
                if ($class === 'before' && $untouched && !$resetDone) {
                    rx_rn_op_update($opId, array('state' => 'failed', 'stage' => 'modify_failed', 'last_error' => $writeMsg));
                    $rxLogFail('apply_write', 'panel_write_failed', 'attempted_not_applied', $writeMsg);
                    return array('status' => false, 'code' => 'panel_write_failed', 'no_mutation' => true, 'operation_id' => $opId, 'msg' => $writeMsg);
                }
                if ($class === 'before') {
                    $write = $this->rxRenewalPanelWrite($panel, $data_user, $invoice, $product, $plan, $username, $opId);
                    if (empty($write['status'])) {
                        return $this->rxRenewalUncertain($opId, $plan, $before, rx_pf_limits($probe), 'partial_write: ' . $writeMsg, $diag);
                    }
                } elseif ($class !== 'target') {
                    return $this->rxRenewalUncertain($opId, $plan, $before, !empty($probe['ok']) ? rx_pf_limits($probe) : null, 'write_result_' . $class . ': ' . $writeMsg, $diag);
                }
            }
            $verify = $this->rxRenewalVerifyLoop($panel, $username, $plan);
            if (!empty($verify['ok'])) {
                rx_rn_op_update($opId, array('state' => 'verified', 'stage' => 'done', 'result_state' => $verify['after'], 'last_error' => null));
                $this->rxRenewalLocalSuccess($panel, $username, $idInvoice);
                $adminFinalize = ($source === 'admin' && function_exists('rxArFinalizeOperation')) ? rxArFinalizeOperation($opId) : null;
                return array(
                    'status' => true,
                    'data' => $write['data'] ?? null,
                    'operation_id' => $opId,
                    'admin_finalize' => $adminFinalize,
                    'rx_renewal' => array(
                        'plan' => $plan,
                        'before' => $before,
                        'target' => $target,
                        'after' => $verify['after'],
                        'verified' => true,
                        'operation_id' => $opId,
                    ),
                );
            }
            return $this->rxRenewalUncertain($opId, $plan, $before, $verify['after'], 'verification_failed: ' . (string) $verify['reason'], $diag);
        } catch (Throwable $e) {
            return $this->rxRenewalUncertain($opId, $plan, $before, null, 'exception: ' . $e->getMessage(), $diag);
        } finally {
            if ($ownLock) {
                rx_rn_lock_release($serviceKey, $opId);
            }
        }
    }

    private function rxRenewalVerifyLoop(array $panel, $username, array $plan)
    {
        $delays = defined('RX_RN_VERIFY_DELAYS') ? array_map('intval', explode(',', (string) RX_RN_VERIFY_DELAYS)) : array(0, 1, 2);
        $result = array('ok' => false, 'reason' => 'unreadable', 'after' => null);
        foreach ($delays as $delay) {
            if ($delay > 0) {
                sleep($delay);
            }
            $snapshot = rx_pf_panel_snapshot($this, (string) $panel['name_panel'], (string) $username, (string) $panel['type']);
            if (empty($snapshot['ok']) || empty($snapshot['exists'])) {
                $result = array('ok' => false, 'reason' => 'unreadable: ' . (string) ($snapshot['error'] ?? 'missing'), 'after' => null);
                continue;
            }
            $verify = rxRenewalVerify($snapshot, $plan);
            $after = rx_pf_limits($snapshot);
            $after['status'] = (string) ($snapshot['status'] ?? '');
            $after['pending_duration'] = $snapshot['pending_duration'] ?? null;
            $result = array('ok' => !empty($verify['ok']), 'reason' => (string) $verify['reason'], 'after' => $after, 'checks' => $verify['checks']);
            if (!empty($verify['ok'])) {
                break;
            }
        }
        return $result;
    }

    private function rxRenewalUncertain($opId, array $plan, array $before, $after, $reason, array $diag)
    {
        rx_rn_op_update($opId, array('state' => 'uncertain', 'last_error' => $reason, 'result_state' => $after, 'next_attempt_at' => time() + 120));
        rx_renewal_log('apply_uncertain', trim((string) $reason) !== '' ? (string) $reason : 'pending_verification', array(
            'panel_name' => (string) ($diag['panel'] ?? ''),
            'panel_type' => (string) ($diag['panel_type'] ?? ''),
            'username' => (string) ($diag['username'] ?? ''),
            'method' => $diag['method'] ?? '',
            'policy' => $diag['policy'] ?? '',
            'plan' => $plan,
            'before' => $before,
            'after' => is_array($after) ? $after : null,
            'operation_id' => $opId,
            'ref' => $diag['ref'] ?? '',
            'mutation' => 'uncertain',
            'error' => $reason,
        ));
        if (!in_array((string) ($diag['source'] ?? ''), array('payment', 'reservation'), true)) {
            rx_pf_alert_admin('renewal:' . $opId, 'نتیجه تمدید هنوز تأیید نشده و به‌صورت خودکار بررسی می‌شود (پنل: ' . (string) ($diag['panel'] ?? '') . ')', array('username' => (string) ($diag['username'] ?? '')));
        }
        return array(
            'status' => false,
            'pending_verification' => true,
            'code' => 'pending_verification',
            'operation_id' => $opId,
            'msg' => $reason,
            'rx_renewal' => array(
                'plan' => $plan,
                'before' => $before,
                'target' => array('data_limit' => $plan['data_limit'] ?? null, 'expire' => $plan['expire'] ?? null),
                'after' => $after,
                'verified' => false,
                'operation_id' => $opId,
            ),
        );
    }

    private function rxRenewalPanelWrite(array $panel, array $data_user, array $invoice, array $product, array $plan, $username, $opId = '')
    {
        global $pdo;
        $data_limit_new = (int) $plan['data_limit'];
        $time_new = (int) $plan['expire'];
        $pending = !empty($plan['target_pending']);
        $pendingDuration = (int) ($plan['target_pending_duration'] ?? 0);
        $enable = !empty($plan['enable']);
        $inbounds = is_string($panel['inbounds'] ?? null) ? json_decode($panel['inbounds']) : "{}";
        if (isset($product['inbounds']) && $product['inbounds'] != null) {
            $inbounds = is_string($product['inbounds']) ? json_decode($product['inbounds']) : $product['inbounds'];
        }
        if ($panel['type'] == "remnawave") {
            $local = remnawave_find_user($username);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $params = array(
                'id' => (int) $local['panel_user_id'],
                'expireAt' => remnawave_iso_from_timestamp($time_new),
                'trafficLimitBytes' => $data_limit_new,
            );
            if ($enable) {
                $params['status'] = 'ACTIVE';
            }
            $res = $mgr->updateUser($params, null, $pdo);
            return array('status' => !empty($res['ok']), 'msg' => empty($res['ok']) ? 'remnawave extend failed' : '', 'data' => $res['data'] ?? null);
        }
        if ($panel['type'] == "WGDashboard") {
            $allowResponse = allowAccessPeers($panel['name_panel'], $username);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array('status' => false, 'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : '');
            }
            $datauser = get_userwg($username, $panel['name_panel']);
            if (!is_array($datauser) || (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id']))) {
                return array('status' => false, 'msg' => is_array($datauser) && isset($datauser['msg']) ? $datauser['msg'] : '');
            }
            $jobs = is_array($datauser['jobs'] ?? null) ? $datauser['jobs'] : array();
            $fields = array();
            if (($plan['time_mode'] ?? '') !== 'keep') {
                $fields['date'] = $time_new > 0 ? date("Y-m-d H:i:s", $time_new) : null;
            }
            if (($plan['traffic_mode'] ?? '') !== 'keep') {
                $fields['total_data'] = $data_limit_new > 0 ? $data_limit_new / pow(1024, 3) : null;
            }
            foreach ($fields as $field => $value) {
                foreach ($jobs as $job) {
                    if (($job['Field'] ?? '') !== $field) {
                        continue;
                    }
                    $deleteJob = deletejob($panel['name_panel'], array("Job" => $job));
                    if (isset($deleteJob['status']) && $deleteJob['status'] === false) {
                        return array('status' => false, 'msg' => isset($deleteJob['msg']) ? $deleteJob['msg'] : '');
                    }
                }
                if ($value !== null) {
                    $setJob = setjob($panel['name_panel'], $field, $value, $datauser['id']);
                    if (isset($setJob['status']) && $setJob['status'] === false) {
                        return array('status' => false, 'msg' => isset($setJob['msg']) ? $setJob['msg'] : '');
                    }
                }
            }
            return array('status' => true);
        }
        if ($this->isPasarGuardDialect($panel)) {
            $configuredGroups = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingGroups = (!empty($data_user['group_ids']) && is_array($data_user['group_ids'])) ? $data_user['group_ids'] : [];
            $mergedGroups = array_values(array_unique(array_merge($existingGroups, is_array($configuredGroups) ? $configuredGroups : [])));
            $targetGroups = !empty($mergedGroups) ? $mergedGroups : (!empty($configuredGroups) ? $configuredGroups : null);
            $data = array(
                'data_limit' => $data_limit_new,
                'expire' => ($pending || $time_new == 0) ? 0 : date('c', $time_new),
            );
            if ($targetGroups !== null) {
                $data['group_ids'] = $targetGroups;
            }
            if (!empty($invoice['uuid'])) {
                $data['proxy_settings'] = json_decode($invoice['uuid']);
            }
        } elseif ($panel['type'] == "marzban") {
            $configuredInbounds = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingInbounds = (!empty($data_user['inbounds']) && is_array($data_user['inbounds'])) ? $data_user['inbounds'] : [];
            $mergedInbounds = is_array($configuredInbounds) ? $configuredInbounds : [];
            foreach ($existingInbounds as $protocol => $tags) {
                if (is_array($tags)) {
                    $currentTags = isset($mergedInbounds[$protocol]) && is_array($mergedInbounds[$protocol]) ? $mergedInbounds[$protocol] : [];
                    $mergedInbounds[$protocol] = array_values(array_unique(array_merge($currentTags, $tags)));
                }
            }
            $data = array(
                'data_limit' => $data_limit_new,
                'expire' => $pending ? 0 : $time_new,
                'inbounds' => !empty($mergedInbounds) ? $mergedInbounds : $inbounds,
            );
            if (!empty($invoice['uuid'])) {
                $data['proxies'] = json_decode($invoice['uuid'], true);
            }
        } elseif ($panel['type'] == "x-ui_single") {
            $data = array(
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "totalGB" => $data_limit_new,
                                "expiryTime" => $pending ? -1 * $pendingDuration * 1000 : $time_new * 1000,
                                "enable" => true,
                            )
                        ),
                        'decryption' => 'none',
                        'fallbacks' => array(),
                    )
                ),
            );
        } elseif ($panel['type'] == "guard") {
            $serviceIdsSource = isset($data_user['service_ids']) ? $data_user['service_ids'] : ($panel['guard_service_ids'] ?? null);
            $serviceResult = guardResolveServiceIds($panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array('status' => false, 'msg' => $serviceResult['msg']);
            }
            $data = array(
                "limit_usage" => $data_limit_new,
                "limit_expire" => guardNormalizeExpire($time_new),
                "service_ids" => $serviceResult['service_ids']
            );
        } elseif ($panel['type'] == "rebecca") {
            $data = array(
                'data_limit' => $data_limit_new,
                'expire' => $pending ? 0 : $time_new,
            );
        } elseif ($panel['type'] == "jovadpanel") {
            return jovadpanelApplyRenewalPlan($panel, $data_user, $plan, (string) $username, (string) $opId);
        } else {
            return array('status' => false, 'msg' => 'unsupported panel type for renewal');
        }
        if (in_array($panel['type'], array('marzban', 'pasarguard', 'rebecca'), true)) {
            if ($pending) {
                $data['status'] = 'on_hold';
                $data['on_hold_expire_duration'] = $pendingDuration;
            } elseif ($enable) {
                $data['status'] = 'active';
            }
        }
        $extend = $this->Modifyuser($username, $panel['name_panel'], $data);
        if (!is_array($extend) || empty($extend['status'])) {
            return array('status' => false, 'msg' => is_array($extend) ? ($extend['msg'] ?? '') : 'invalid panel response');
        }
        return array('status' => true, 'data' => $extend['data'] ?? null);
    }
    function extra_volume($username_account, $code_panel, $limit_volume_new, $rxTarget = null)
    {
        $panel = $this->loadPanel($code_panel, "code_panel");
        $invoice = select("invoice", "*", "username", $username_account, "select");
        if ($panel == false) {
            return array(
                'status' => false,
                'msg' => 'data not found'
            );
        }
        $notif_value = json_decode($invoice['notifctions'], true);
        $notifctions = json_encode(array(
            'volume' => false,
            'time' => $notif_value['time'],
        ));
        update("invoice", "notifctions", $notifctions, 'id_invoice', $invoice['id_invoice']);
        $user_info = $this->DataUser($panel['name_panel'], $username_account);
        if ($user_info['status'] == "Unsuccessful") {
            return array(
                'status' => false,
                'msg' => $user_info['msg']
            );
        }
        $old_limit_volume = $user_info['data_limit'];
        $new_limit = $limit_volume_new == 0 ? 0 : ($limit_volume_new * pow(1024, 3)) + $old_limit_volume;
        if (is_array($rxTarget) && array_key_exists('data_limit', $rxTarget)) {
            $new_limit = (int) $rxTarget['data_limit'];
        }
        $inbound_id = isset($panel['inboundid']) ? $panel['inboundid'] : 1;
        $inbounds = is_string($panel['inbounds']) ? json_decode($panel['inbounds']) : "{}";
        if ($panel['type'] != "WGDashboard") {
            update("invoice", 'user_info', null, "username", $username_account);
        }
        update("invoice", 'uuid', null, "username", $username_account);
        update("invoice", 'Status', "active", "username", $username_account);
        if ($panel['type'] == "remnawave") {
            global $pdo;
            $local = remnawave_find_user($username_account);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $res = $mgr->addVolume($local['panel_user_id'], (int) $new_limit, $pdo);
            return array('status' => !empty($res['ok']));
        }
        if ($this->isPasarGuardDialect($panel)) {
            $configuredGroups = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingGroups = (!empty($user_info['group_ids']) && is_array($user_info['group_ids'])) ? $user_info['group_ids'] : [];
            $mergedGroups = array_values(array_unique(array_merge($existingGroups, is_array($configuredGroups) ? $configuredGroups : [])));
            $targetGroups = !empty($mergedGroups) ? $mergedGroups : (!empty($configuredGroups) ? $configuredGroups : null);

            $data = array(
                'data_limit' => $new_limit,
            );
            if ($targetGroups !== null) {
                $data['group_ids'] = $targetGroups;
            }
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxy_settings'] = json_decode($invoice['uuid']);
            }
        } elseif ($panel['type'] == "marzban") {
            $configuredInbounds = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingInbounds = (!empty($user_info['inbounds']) && is_array($user_info['inbounds'])) ? $user_info['inbounds'] : [];
            $mergedInbounds = is_array($configuredInbounds) ? $configuredInbounds : [];
            if (is_array($existingInbounds)) {
                foreach ($existingInbounds as $protocol => $tags) {
                    if (is_array($tags)) {
                        $currentTags = isset($mergedInbounds[$protocol]) && is_array($mergedInbounds[$protocol]) ? $mergedInbounds[$protocol] : [];
                        $mergedInbounds[$protocol] = array_values(array_unique(array_merge($currentTags, $tags)));
                    }
                }
            }
            $targetInbounds = !empty($mergedInbounds) ? $mergedInbounds : $inbounds;
            $data = array(
                'data_limit' => $new_limit,
                'inbounds' => $targetInbounds,
            );
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxies'] = json_decode($invoice['uuid'], true);
            }
        } elseif ($panel['type'] == "x-ui_single") {
            $data = array(
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "totalGB" => $new_limit,
                            )
                        ),
                    )
                ),
            );
        } elseif ($panel['type'] == "WGDashboard") {
            $allowResponse = allowAccessPeers($panel['name_panel'], $username_account);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = get_userwg($username_account, $panel['name_panel']);
            if (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($datauser['msg']) ? $datauser['msg'] : ''
                );
            }
            $count = 0;
            foreach ($datauser['jobs'] as $jobsvolume) {
                if ($jobsvolume['Field'] == "total_data") {
                    break;
                }
                $count += 1;
            }
            if (isset($datauser['jobs'][$count])) {
                $datam = array(
                    "Job" => $datauser['jobs'][$count],
                );
                $deleteJob = deletejob($panel['name_panel'], $datam);
                if (isset($deleteJob['status']) && $deleteJob['status'] === false) {
                    return array(
                        'status' => false,
                        'msg' => isset($deleteJob['msg']) ? $deleteJob['msg'] : ''
                    );
                }
            } else {
                $resetResult = $this->ResetUserDataUsage($username_account, $panel['name_panel']);
                if (isset($resetResult['status']) && $resetResult['status'] === false) {
                    return array(
                        'status' => false,
                        'msg' => isset($resetResult['msg']) ? $resetResult['msg'] : ''
                    );
                }
            }
            $log = setjob($panel['name_panel'], "total_data", $new_limit / pow(1024, 3), $datauser['id']);
            if (isset($log['status']) && $log['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($log['msg']) ? $log['msg'] : ''
                );
            }
            return array(
                'status' => true,
                'data' => $log
            );
        } elseif ($panel['type'] == "guard") {
            $serviceIdsSource = isset($user_info['service_ids']) ? $user_info['service_ids'] : ($panel['guard_service_ids'] ?? null);
            $serviceResult = guardResolveServiceIds($panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $serviceResult['msg']
                );
            }
            $data = array(
                "limit_usage" => $new_limit,
                "service_ids" => $serviceResult['service_ids']
            );
        } elseif ($panel['type'] == "rebecca") {
            $data = array(
                'data_limit' => $new_limit,
            );
        } elseif ($panel['type'] == "jovadpanel") {
            $extra_volume = jovadpanelAddVolume($panel, (string) $username_account, (int) $new_limit);
            return array(
                'status' => !empty($extra_volume['status']),
                'data' => $extra_volume['data'] ?? null,
                'msg' => $extra_volume['msg'] ?? null
            );
        }
        $extra_volume = $this->Modifyuser($username_account, $panel['name_panel'], $data);
        if ($extra_volume['status'] == false) {
            return array(
                'status' => false,
                'msg' => $extra_volume['msg']
            );
        }
        return $extra_volume;
    }
    function extra_time($username_account, $code_panel, $limit_time_new, $rxTarget = null)
    {
        $panel = $this->loadPanel($code_panel, "code_panel");
        $invoice = select("invoice", "*", "username", $username_account, "select");
        if ($panel == false) {
            return array(
                'status' => false,
                'msg' => 'data not found'
            );
        }
        $notif_value = json_decode($invoice['notifctions'], true);
        $notifctions = json_encode(array(
            'volume' => $notif_value['volume'],
            'time' => false,
        ));
        update("invoice", "notifctions", $notifctions, 'id_invoice', $invoice['id_invoice']);
        $user_info = $this->DataUser($panel['name_panel'], $username_account);
        if ($user_info['status'] == "Unsuccessful") {
            return array(
                'status' => false,
                'msg' => $user_info['msg']
            );
        }
        $old_limit_time = $user_info['expire'];
        $old_limit_time = time() - $old_limit_time > 0 ? time() : $old_limit_time;
        $new_limit = $limit_time_new == 0 ? 0 : $limit_time_new * 86400 + $old_limit_time;
        if (is_array($rxTarget) && array_key_exists('expire', $rxTarget)) {
            $new_limit = (int) $rxTarget['expire'];
        }
        $inbound_id = isset($panel['inboundid']) ? $panel['inboundid'] : 1;
        $inbounds = is_string($panel['inbounds']) ? json_decode($panel['inbounds']) : "{}";
        if ($panel['type'] != "WGDashboard") {
            update("invoice", 'user_info', null, "username", $username_account);
        }
        update("invoice", 'uuid', null, "username", $username_account);
        update("invoice", 'Status', "active", "username", $username_account);
        if ($panel['type'] == "remnawave") {
            global $pdo;
            $local = remnawave_find_user($username_account);
            if ($local === null || empty($local['panel_user_id'])) {
                return array('status' => false, 'msg' => 'remnawave user not found');
            }
            $mgr = remnawave_manager_for($panel['name_panel']);
            if ($mgr === null) {
                return array('status' => false, 'msg' => 'Panel Not Found');
            }
            $res = $mgr->extendUser($local['panel_user_id'], remnawave_iso_from_timestamp($new_limit), $pdo);
            return array('status' => !empty($res['ok']));
        }
        if ($this->isPasarGuardDialect($panel)) {
            $configuredGroups = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingGroups = (!empty($user_info['group_ids']) && is_array($user_info['group_ids'])) ? $user_info['group_ids'] : [];
            $mergedGroups = array_values(array_unique(array_merge($existingGroups, is_array($configuredGroups) ? $configuredGroups : [])));
            $targetGroups = !empty($mergedGroups) ? $mergedGroups : (!empty($configuredGroups) ? $configuredGroups : null);

            $data = array(
                'expire' => ($new_limit == 0 ? 0 : date('c', $new_limit)),
            );
            if ($targetGroups !== null) {
                $data['group_ids'] = $targetGroups;
            }
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxy_settings'] = json_decode($invoice['uuid']);
            }
        } elseif ($panel['type'] == "marzban") {
            $configuredInbounds = is_array($inbounds) ? $inbounds : (is_string($inbounds) ? json_decode($inbounds, true) : []);
            $existingInbounds = (!empty($user_info['inbounds']) && is_array($user_info['inbounds'])) ? $user_info['inbounds'] : [];
            $mergedInbounds = is_array($configuredInbounds) ? $configuredInbounds : [];
            if (is_array($existingInbounds)) {
                foreach ($existingInbounds as $protocol => $tags) {
                    if (is_array($tags)) {
                        $currentTags = isset($mergedInbounds[$protocol]) && is_array($mergedInbounds[$protocol]) ? $mergedInbounds[$protocol] : [];
                        $mergedInbounds[$protocol] = array_values(array_unique(array_merge($currentTags, $tags)));
                    }
                }
            }
            $targetInbounds = !empty($mergedInbounds) ? $mergedInbounds : $inbounds;
            $data = array(
                'expire' => $new_limit,
                'inbounds' => $targetInbounds,
            );
            if ($invoice != false && $invoice['uuid'] != null) {
                $data['proxies'] = json_decode($invoice['uuid'], true);
            }
        } elseif ($panel['type'] == "x-ui_single") {
            $new_limit = $new_limit * 1000;
            $data = array(
                'settings' => json_encode(
                    array(
                        'clients' => array(
                            array(
                                "expiryTime" => $new_limit,
                            )
                        ),
                    )
                ),
            );
        } elseif ($panel['type'] == "WGDashboard") {
            $allowResponse = allowAccessPeers($panel['name_panel'], $username_account);
            if (isset($allowResponse['status']) && $allowResponse['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($allowResponse['msg']) ? $allowResponse['msg'] : ''
                );
            }
            $datauser = get_userwg($username_account, $panel['name_panel']);
            if (isset($datauser['status']) && $datauser['status'] === false && !isset($datauser['id'])) {
                return array(
                    'status' => false,
                    'msg' => isset($datauser['msg']) ? $datauser['msg'] : ''
                );
            }
            $count = 0;
            foreach ($datauser['jobs'] as $jobsvolume) {
                if ($jobsvolume['Field'] == "date") {
                    break;
                }
                $count += 1;
            }
            if (isset($datauser['jobs'][$count])) {
                $datam = array(
                    "Job" => $datauser['jobs'][$count],
                );
                $deleteJob = deletejob($panel['name_panel'], $datam);
                if (isset($deleteJob['status']) && $deleteJob['status'] === false) {
                    return array(
                        'status' => false,
                        'msg' => isset($deleteJob['msg']) ? $deleteJob['msg'] : ''
                    );
                }
            }
            $log = setjob($panel['name_panel'], "date", date('Y-m-d H:i:s', $new_limit), $datauser['id']);
            if (isset($log['status']) && $log['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => isset($log['msg']) ? $log['msg'] : ''
                );
            }
            return array(
                'status' => true,
                'data' => $log
            );
        } elseif ($panel['type'] == "guard") {
            $serviceIdsSource = isset($user_info['service_ids']) ? $user_info['service_ids'] : ($panel['guard_service_ids'] ?? null);
            $serviceResult = guardResolveServiceIds($panel['name_panel'], $serviceIdsSource);
            if ($serviceResult['status'] === false) {
                return array(
                    'status' => false,
                    'msg' => $serviceResult['msg']
                );
            }
            $data = array(
                "limit_expire" => guardNormalizeExpire($new_limit),
                "service_ids" => $serviceResult['service_ids']
            );
        } elseif ($panel['type'] == "rebecca") {
            $data = array(
                'expire' => $new_limit,
            );
        } elseif ($panel['type'] == "jovadpanel") {
            $extra_time = jovadpanelAddTime($panel, (string) $username_account, (int) $new_limit);
            return array(
                'status' => !empty($extra_time['status']),
                'data' => $extra_time['data'] ?? null,
                'msg' => $extra_time['msg'] ?? null
            );
        }
        $extra_time = $this->Modifyuser($username_account, $panel['name_panel'], $data);
        if ($extra_time['status'] == false) {
            return array(
                'status' => false,
                'msg' => $extra_time['msg']
            );
        }
        return $extra_time;
    }
}


