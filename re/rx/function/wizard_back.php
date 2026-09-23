<?php
/**
 * Wizard step-back navigation (dynamic step stack).
 *
 * Multi-step admin "wizards" (Add Product, Add Panel, Add Channel, …) advance the
 * user through a chain of `user.step` values. Pressing "▶️ بازگشت به منوی قبل"
 * (the `backmenu` button) used to discard the whole form and drop the admin at a
 * top-level menu. To fix that generically we keep a breadcrumb **stack of step
 * names** in the dedicated `user.step_stack` column:
 *
 *   - `step()` (re/rx/function/bootstrap.php) pushes the previous step whenever the
 *     admin moves forward *within* a wizard, and clears the stack when entering,
 *     switching, or leaving a wizard. It is the single chokepoint for every step
 *     transition, so no forward-flow handler needs editing.
 *   - `stepBack()` pops one entry and sets `user.step` to it.
 *   - The `backmenu` router calls `stepBack()` + `rxRenderWizardStep()` to re-show
 *     exactly the previous step's prompt.
 *
 * Because the stack records the *actual* path taken, conditional branches are
 * handled automatically — no per-step reverse maps. Already-entered fields survive
 * because they live in `user.Processing_value`, which is never cleared on back.
 *
 * Safety: every stack operation is isolated to the `step_stack` column and wrapped
 * so it can never break the core `step` update or a forward flow. A flow is inert
 * until its steps appear in rxWizardStepFlow(), enabling a staged rollout.
 */

if (!function_exists('rxWizardStepFlow')) {
    /**
     * Single source of truth: maps a step name to its wizard flow id, or null if
     * the step is not part of a managed wizard. Adding a flow's steps here turns
     * step-back tracking on for that flow.
     */
    function rxWizardStepFlow($step)
    {
        static $map = null;
        if ($map === null) {
            $map = [
                'get_limit'            => 'product',
                'get_agent'            => 'product',
                'get_location'         => 'product',
                'getcategory'          => 'product',
                'get_time'             => 'product',
                'get_price'            => 'product',
                'gettimereset'         => 'product',
                'getnote'              => 'product',
                'endstep'              => 'product',

                'add_name_panel'       => 'panel',
                'add_link_panel'       => 'panel',
                'add_username_panel'   => 'panel',
                'add_password_panel'   => 'panel',
                'add_remna_token_setup' => 'panel',
                'add_guard_api_key'    => 'panel',
                'getlimitedpanel'      => 'panel',

                'addchannel'           => 'channel',
                'getremark'            => 'channel',
                'getlinkjoin'          => 'channel',

                'GetmaineExtra'        => 'warehouse',
                'gettypeextramain'     => 'warehouse',
                'GetmaxeExtra'         => 'warehouse',
                'gettypeextramax'      => 'warehouse',
                'Getmaintime'          => 'warehouse',
                'gettypeextramaintime' => 'warehouse',
                'Getmaxtime'           => 'warehouse',
                'gettypeextramaxtime'  => 'warehouse',

                'getpanelgift'         => 'gift',
                'getvaluegift'         => 'gift',
                'gettextgift'          => 'gift',
                'getaddpricepeoductloc' => 'bulk_price',
                'getagentaddpriceproduct' => 'bulk_price',
                'getaddpricepeoduct'   => 'bulk_price',
                'getlowpricepeoductloc' => 'bulk_price_dec',
                'getkampricepeoductloc' => 'bulk_price_dec',
                'getkampricepeoduct'   => 'bulk_price_dec',
                'add_Balance_all'      => 'bulk_balance',
                'getmeesagestatus'     => 'bulk_balance',
                'getnameconfigm'       => 'manual_config',
                'getnameproduct'       => 'manual_config',
                'getconfigtext'        => 'manual_config',
                'getnameedit'          => 'manual_config_edit',
                'getcontentedit'       => 'manual_config_edit',
            ];
        }
        $step = (string) $step;
        return isset($map[$step]) ? $map[$step] : null;
    }
}

if (!function_exists('rxStepStackRead')) {
    /**
     * Decode the per-user step stack (JSON array). Returns [] when missing/empty.
     * select() swallows a missing-column error internally and returns null, so no
     * exception escapes here.
     */
    function rxStepStackRead($from_id)
    {
        $row = select("user", "step_stack", "id", $from_id, "select", ['cache' => false]);
        $raw = (is_array($row) && isset($row['step_stack'])) ? $row['step_stack'] : '';
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }
}

if (!function_exists('stepBack')) {
    /**
     * Pop one step off the stack and set user.step to it (directly, so it does NOT
     * re-trigger step()'s push logic). Returns the previous step name, or null when
     * the stack is empty (i.e. we are at the first step of the wizard).
     */
    function stepBack($from_id)
    {
        global $pdo;
        $stack = rxStepStackRead($from_id);
        if (empty($stack)) {
            return null;
        }
        $prev = (string) array_pop($stack);
        $rxNavBackFrom = null;
        if (class_exists('logNavigation')) {
            $rxNavBackRow = select("user", "step", "id", $from_id, "select", ['cache' => false]);
            if (is_array($rxNavBackRow) && isset($rxNavBackRow['step'])) {
                $rxNavBackFrom = $rxNavBackRow['step'];
            } elseif (is_scalar($rxNavBackRow)) {
                $rxNavBackFrom = $rxNavBackRow;
            }
        }
        try {
            update("user", "step_stack", json_encode(array_values($stack), JSON_UNESCAPED_UNICODE), "id", $from_id);
        } catch (\Throwable $e) {
        }
        if ($pdo instanceof PDO) {
            $stmt = $pdo->prepare('UPDATE user SET step = ? WHERE id = ?');
            $stmt->execute([$prev, $from_id]);
        }
        if (class_exists('logNavigation')) {
            logNavigation::transition($from_id, $rxNavBackFrom, $prev);
        }
        if (function_exists('clearSelectCache')) {
            clearSelectCache('user');
        }
        return $prev;
    }
}

if (!function_exists('rxWizardProcessingType')) {
    /** Saved panel "type" from the in-progress Add Panel wizard (for branch prompts). */
    function rxWizardProcessingType($from_id)
    {
        $row = select("user", "Processing_value", "id", $from_id, "select", ['cache' => false]);
        $raw = (is_array($row) && isset($row['Processing_value'])) ? $row['Processing_value'] : '';
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        $decoded = json_decode($raw, true);
        return (is_array($decoded) && isset($decoded['type'])) ? (string) $decoded['type'] : '';
    }
}

if (!function_exists('rxRenderWizardStep')) {
    /**
     * Re-emit the prompt + keyboard for a given wizard step (mirrors exactly what
     * the forward handler shows when the admin is AT that step). Returns true if
     * the step is known/rendered, false otherwise (caller then falls through to the
     * legacy exit behavior).
     */
    function rxRenderWizardStep($step, $from_id)
    {
        global $user, $backadmin, $textbotlang, $json_list_marzban_panel, $keyboardtimereset;
        $step = (string) $step;
        switch ($step) {
            case 'get_limit':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['AddProductStepOne'], $backadmin, 'HTML');
                return true;
            case 'get_agent':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['setagentproduct'], rx_agentGroupKeyboard(false), 'HTML');
                return true;
            case 'get_location':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Service_location'], $json_list_marzban_panel, 'HTML');
                return true;
            case 'getcategory':
                nm_adminInstantReply($from_id, "📌 نام دسته بندی خود را ارسال نمایید.", KeyboardCategoryadmin(), 'HTML');
                return true;
            case 'get_time':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetLimit'], $backadmin, 'HTML');
                return true;
            case 'get_price':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GettIime'], $backadmin, 'HTML');
                return true;
            case 'gettimereset':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['GetPrice'], $backadmin, 'HTML');
                return true;
            case 'getnote':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['gettimereset'], $keyboardtimereset, 'HTML');
                return true;

            case 'add_name_panel':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelname'], $backadmin, 'HTML');
                return true;
            case 'add_link_panel':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['addpanelurl'], $backadmin, 'HTML');
                return true;
            case 'add_username_panel':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['usernameset'], $backadmin, 'HTML');
                return true;
            case 'add_password_panel':
                $ptype = rxWizardProcessingType($from_id);
                if ($ptype === 'WGDashboard') {
                    nm_adminInstantReply($from_id, "📌 توکن را ارسال نمایید", $backadmin, 'HTML');
                } else {
                    nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getpassword'], $backadmin, 'HTML');
                }
                return true;
            case 'add_remna_token_setup':
                nm_adminInstantReply($from_id, "📌 توکن ثابت API رمن‌ویو را ارسال نمایید.\n(از مسیر API Tokens در داشبورد ادمین رمن‌ویو بسازید)", $backadmin, 'HTML');
                return true;
            case 'getlimitedpanel':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['managepanel']['getlimitedpanel'], $backadmin, 'HTML');
                return true;

            case 'addchannel':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['channel']['changechannel'], $backadmin, 'HTML');
                return true;
            case 'getremark':
                nm_adminInstantReply($from_id, "📌 یک نام برای دکمه عضویت چنل انتخاب نمایید.", $backadmin, 'HTML');
                return true;
            case 'getlinkjoin':
                nm_adminInstantReply($from_id, "📌 لینک عضویت را ارسال کنید", $backadmin, 'HTML');
                return true;

            case 'GetmaineExtra':
                nm_adminInstantReply($from_id, "📌 حداقل حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
                return true;
            case 'GetmaxeExtra':
                nm_adminInstantReply($from_id, "📌 حداکثر حجم که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
                return true;
            case 'Getmaintime':
                nm_adminInstantReply($from_id, "📌 حداقل زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
                return true;
            case 'Getmaxtime':
                nm_adminInstantReply($from_id, "📌 حداکثر زمانی دلخواهی  که کاربر میتواند تهیه کند  برای این پنل را ارسال نمایید.", $backadmin, 'HTML');
                return true;

            case 'getpanelgift':
                nm_adminInstantReply($from_id, "📌 برای سرویس های کدام پنل میخواهید حجم یا زمان هدیه دهید؟", $GLOBALS['json_list_marzban_panel'] ?? null, "html");
                return true;
            case 'getvaluegift':
                $userdata = json_decode($user['Processing_value'] ?? '{}', true);
                $typegift = $userdata['typegift'] ?? 'volume';
                if ($typegift === 'volume') {
                    nm_adminInstantReply($from_id, "📌 چند گیگ حجم می خواهید به سرویس های کاربر اضافه شود", $backadmin, "html");
                } else {
                    nm_adminInstantReply($from_id, "📌 چند روز می خواهید به سرویس های کاربران اضافه شود", $backadmin, "html");
                }
                return true;
            case 'gettextgift':
                nm_adminInstantReply($from_id, "📌 متنی که می خواهید برای کاربر ارسال شود را ارسال کنید", $backadmin, "html");
                return true;
            case 'getaddpricepeoductloc':
                nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید افزایش قیمت دهید؟\nدر صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $GLOBALS['json_list_marzban_panel'] ?? null, 'HTML');
                return true;
            case 'getagentaddpriceproduct':
                nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود؟\nیکی از گزینه‌های زیر را انتخاب یا ارسال کنید:\n👤 کاربر عادی (f)\n🤝 نماینده عادی (n)\n💎 نماینده پیشرفته (n2)", rx_agentGroupKeyboard(false), 'HTML');
                return true;
            case 'getaddpricepeoduct':
                $userdata = json_decode($user['Processing_value'] ?? '{}', true);
                $type = $userdata['type_price'] ?? 'static';
                if ($type === 'static') {
                    nm_adminInstantReply($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
                } else {
                    nm_adminInstantReply($from_id, "📌 درصدی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
                }
                return true;
            case 'getlowpricepeoductloc':
                nm_adminInstantReply($from_id, "📌 محصولات کدام پنل میخواهید کاهش قیمت دهید؟\nدر صورتی که  موقع تعریف محصول /all زدید  اگر میخواید این دسته تغییر قیمت داشته باشد حتما باید /all ارسال شود", $GLOBALS['json_list_marzban_panel'] ?? null, 'HTML');
                return true;
            case 'getkampricepeoductloc':
                nm_adminInstantReply($from_id, "📌 قیمت برای کدام گروه کاربری اعمال شود؟\nیکی از گزینه‌های زیر را انتخاب یا ارسال کنید:\n👤 کاربر عادی (f)\n🤝 نماینده عادی (n)\n💎 نماینده پیشرفته (n2)", rx_agentGroupKeyboard(false), 'HTML');
                return true;
            case 'getkampricepeoduct':
                nm_adminInstantReply($from_id, "📌 مبلغی که میخواهید اعمال شود را ارسال نمایید", $backadmin, 'HTML');
                return true;
            case 'add_Balance_all':
                nm_adminInstantReply($from_id, $textbotlang['Admin']['Balance']['addallbalance'], $backadmin, 'HTML');
                return true;
            case 'getmeesagestatus':
                nm_adminInstantReply($from_id, "📌 برای کاربران پیام ارسال شارژ ارسال شود یا خیر؟\nبله : 1\nخیر : 0", $backadmin, 'HTML');
                return true;
            case 'getnameconfigm':
                nm_adminInstantReply($from_id, "📌 برای اضافه کردن کانفیگ ابتدا یک نام ارسال نمایید.", $backadmin, 'HTML');
                return true;
            case 'getnameproduct':
                $userdata = json_decode($user['Processing_value'] ?? '{}', true);
                $panelName = $userdata['namepanel'] ?? '';
                $product = [];
                $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM product WHERE FIND_IN_SET(:text, Location) > 0 or Location = '/all' ");
                $stmt->bindParam(':text', $panelName, PDO::PARAM_STR);
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $product[] = [$row['name_product']];
                }
                $list_product = [
                    'keyboard' => [],
                    'resize_keyboard' => true,
                ];
                $list_product['keyboard'][] = [
                    ['text' => "🏠 بازگشت به منوی مدیریت"],
                ];
                foreach ($product as $button) {
                    $list_product['keyboard'][] = [
                        ['text' => $button[0]]
                    ];
                }
                $json_list_product_list_admin = json_encode($list_product);
                nm_adminInstantReply($from_id, "📌 نام محصول خود را ارسال نمایید در صورتی که میخواهید  برای اکانت تست تنظیم کنید متن تست را ارسال کنید.", $json_list_product_list_admin, 'HTML');
                return true;
            case 'getconfigtext':
                nm_adminInstantReply($from_id, "📌 کانفیگ یا متن دیگر خود را ارسال نمایید", $backadmin, 'HTML');
                return true;
            case 'getnameedit':
                $panel = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
                $listconfig = [];
                $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM manualsell WHERE codepanel = '{$panel['code_panel']}'");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $listconfig[] = [$row['namerecord']];
                }
                $list_configmanual = [
                    'keyboard' => [],
                    'resize_keyboard' => true,
                ];
                $list_configmanual['keyboard'][] = [
                    ['text' => "🏠 بازگشت به منوی مدیریت"],
                ];
                foreach ($listconfig as $button) {
                    $list_configmanual['keyboard'][] = [
                        ['text' => $button[0]]
                    ];
                }
                $json_list_manualconfig_list = json_encode($list_configmanual);
                nm_adminInstantReply($from_id, "📌 نام کانفیگی که میخواهید ویرایش نمایید را ارسال کنید ", $json_list_manualconfig_list, 'HTML');
                return true;
            case 'getcontentedit':
                nm_adminInstantReply($from_id, "محتوا جدید کانفیگ را ارسال کنید", $backadmin, 'HTML');
                return true;
        }
        return false;
    }
}

if (!function_exists('rxNmAppendBackmenu')) {
    /**
     * Append a "▶️ بازگشت به منوی قبل" (backmenu) button to an already-built keyboard
     * JSON, supporting BOTH reply keyboards and inline keyboards, without touching the
     * shared keyboard builders (so other flows that reuse those builders are unaffected).
     * The backmenu router (bootstrap_2.php) handles both the button text and the
     * 'backmenu' callback, so the appended button works in either keyboard mode.
     */
    function rxNmAppendBackmenu($keyboardJson)
    {
        global $textbotlang;
        $backMenu = isset($textbotlang['Admin']['backmenu']) ? $textbotlang['Admin']['backmenu'] : '▶️ بازگشت به منوی قبل';
        $decoded = is_array($keyboardJson) ? $keyboardJson : (is_string($keyboardJson) && $keyboardJson !== '' ? json_decode($keyboardJson, true) : null);
        if (!is_array($decoded)) {
            return $keyboardJson;
        }
        if (isset($decoded['keyboard']) && is_array($decoded['keyboard'])) {
            $decoded['keyboard'][] = [['text' => $backMenu]];
        } elseif (isset($decoded['inline_keyboard']) && is_array($decoded['inline_keyboard'])) {
            $decoded['inline_keyboard'][] = [['text' => $backMenu, 'callback_data' => 'backmenu']];
        } else {
            return $keyboardJson;
        }
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('rxWizardExitFirstStep')) {
    /**
     * Flow-specific "exit one level up" when the admin presses back on the FIRST step
     * of a wizard (empty stack). Returns true if it handled the exit, false to let the
     * caller fall through to the legacy backmenu behavior.
     *
     * Currently only the warehouse flow needs this: its first step (a number prompt)
     * was opened from the panel's own menu, so back returns there via outtypepanel()
     * — mirroring what the forward "save" path does (settings.php). Processing_value is
     * reset to the scalar panel name so the panel menu context is valid again.
     */
    function rxWizardExitFirstStep($currentStep, $from_id)
    {
        global $user, $textbotlang;
        $currentStep = (string) $currentStep;

        if ($currentStep === 'getpanelgift' || $currentStep === 'add_Balance_all') {
            $backMsg = isset($textbotlang['Admin']['Back-menu']) ? $textbotlang['Admin']['Back-menu'] : 'به منوی قبل بازگشتید!';
            $usersHubKb = $GLOBALS['adminUsersMenu'] ?? null;
            step('home', $from_id);
            nm_adminInstantReply($from_id, $backMsg, $usersHubKb, 'HTML');
            return true;
        }
        if ($currentStep === 'getvaluegift') {
            $rxGiftTypeKb = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🔋 حجم", 'callback_data' => 'typegift_volume'],
                        ['text' => "⏳ زمان", 'callback_data' => 'typegift_day'],
                    ],
                ],
            ]);
            step('home', $from_id);
            nm_adminInstantReply($from_id, "📌 یکی از هدیه های زیر را انتخاب نمایید.", $rxGiftTypeKb, "html");
            return true;
        }
        if ($currentStep === 'getaddpricepeoductloc' || $currentStep === 'getlowpricepeoductloc') {
            $backMsg = isset($textbotlang['Admin']['Back-menu']) ? $textbotlang['Admin']['Back-menu'] : 'به منوی قبل بازگشتید!';
            $sendShopKb = $GLOBALS['keyboard_shop_manage'] ?? ($GLOBALS['shopkeyboard'] ?? null);
            step('home', $from_id);
            nm_adminInstantReply($from_id, $backMsg, $sendShopKb, 'HTML');
            return true;
        }
        if ($currentStep === 'getnameconfigm' || $currentStep === 'getnameedit') {
            $panelName = function_exists('nmResolvePanelNameForUser') ? nmResolvePanelNameForUser($user) : (string)($user['Processing_value'] ?? '');
            if ($panelName !== '' && $panelName !== '0') {
                $typepanel = select("marzban_panel", "*", "name_panel", $panelName, "select");
                if (is_array($typepanel) && !empty($typepanel)) {
                    step('PanelMenu', $from_id);
                    $backMsg = isset($textbotlang['Admin']['Back-menu']) ? $textbotlang['Admin']['Back-menu'] : 'به منوی قبل بازگشتید!';
                    outtypepanel($typepanel['type'], $backMsg);
                    return true;
                }
            }
            $panelHubKb = $GLOBALS['adminPanelsMenu'] ?? null;
            step('home', $from_id);
            nm_adminInstantReply($from_id, 'به منوی قبل بازگشتید!', $panelHubKb, 'HTML');
            return true;
        }

        $warehouseFirst = ['GetmaineExtra', 'GetmaxeExtra', 'Getmaintime', 'Getmaxtime'];
        if (!in_array($currentStep, $warehouseFirst, true)) {
            return false;
        }
        $row = select("user", "Processing_value", "id", $from_id, "select", ['cache' => false]);
        $raw = (is_array($row) && isset($row['Processing_value'])) ? $row['Processing_value'] : '';
        $name = '';
        if (is_string($raw) && $raw !== '') {
            if ($raw[0] === '{' || $raw[0] === '[') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['namepanel'])) {
                    $name = (string) $decoded['namepanel'];
                }
            } else {
                $name = $raw;
            }
        }
        if ($name === '' || $name === '0') {
            return false;
        }
        $panel = select("marzban_panel", "*", "name_panel", $name, "select");
        if (!is_array($panel) || empty($panel) || !function_exists('outtypepanel')) {
            return false;
        }
        update("user", "Processing_value", $name, "id", $from_id);
        step('home', $from_id);
        $backMsg = isset($textbotlang['Admin']['Back-menu']) ? $textbotlang['Admin']['Back-menu'] : 'به منوی قبل بازگشتید!';
        outtypepanel($panel['type'], $backMsg);
        return true;
    }
}
