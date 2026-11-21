<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use stdClass;

/**
 * Handler stage. Inherited by value: $APIKEY, $adminAction, $adminExecution, $backadmin, $dataget, $from_id, $keyboardadmin, $message_id, $optionMarzban, $optionPasarGuard, $pdo, $photo, $photoid, $setting_panel, $text, $textbotlang, $textsetprotocol, $update, $user.
 * Inherited or exported by reference: $key, $panel, $response, $stmt, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AgentSettingsAndServerMaintenance
{
    public static function handle(
        $APIKEY,
        $adminAction,
        $adminExecution,
        $backadmin,
        $dataget,
        $from_id,
        &$key,
        $keyboardadmin,
        $message_id,
        $optionMarzban,
        $optionPasarGuard,
        &$panel,
        $pdo,
        $photo,
        $photoid,
        &$response,
        $setting_panel,
        &$stmt,
        $text,
        $textbotlang,
        $textsetprotocol,
        $update,
        $user,
        &$value
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 قیمت درخواست  عضویت  برای نمایندگی را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getpricereqagent", $from_id);
            break;
        case 2:
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['agent']['invalidvlue'], $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ تغییرات با موفقیت ذخیره گردید", $setting_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            \Faoxima\Core\Database\Db::update("setting", "agentreqprice", $text, null, null);
            break;
        case 3:
            $paymentverify = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
            $keyboardverify = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $paymentverify, 'callback_data' => $paymentverify],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌 با فعال کردن این قابلیت  در زمان هایی که آنلاین نیستید ربات بصورت خودکار تمامی تراکنش های کارت به کارت را تایید می کند سپس بعد از آنلاین شدن شما رسید ها را بررسی میکنید سپس اگر رسید فیک  ارسال شده تراکنش را کنسل میکنید", $keyboardverify, 'HTML');
            break;
        case 4:
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "offauto", "NamePay", "autoconfirmcart");
            $paymentverify = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
            $keyboardverify = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $paymentverify, 'callback_data' => $paymentverify],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "خاموش شد", $keyboardverify);
            break;
        case 5:
            \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "onauto", "NamePay", "autoconfirmcart");
            $paymentverify = \Faoxima\Core\Database\Db::select("PaySetting", "ValuePay", "NamePay", "autoconfirmcart", "select")['ValuePay'];
            $keyboardverify = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $paymentverify, 'callback_data' => $paymentverify],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backadmin'], 'callback_data' => 'cart_back'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, "روشن شد", $keyboardverify);
            break;
        case 6:
            $iduser = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $iduser, "id", $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "آیدی عددی کاربری که میخواهید تمامی اطلاعات به آن کاربر منتقل شود را ارسال نمایید
    توجه داشتید باشید در کاربر مقصد در صورت داشتن موجودی حذف خواهد شد", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getidfortransfers", $from_id);
            break;
        case 7:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!\Faoxima\Core\Database\Db::userExists($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['not-user'], $backadmin, 'HTML');
                return;
            }
            if ($text == $user['Processing_value']) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ شما نمی توانید اطلاعات به کاربر فعلی منتقل کنید", $keyboardadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "اطلاعات با موفقیت به حساب کاربری جدید منتقل گردید", $keyboardadmin, 'HTML');
            $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id_user");
            $stmt->bindParam(':id_user', $text, PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Core\Database\Db::update("user", "id", $text, "id", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("Payment_report", "id_user", $text, "id_user", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("invoice", "id_user", $text, "id_user", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("support_message", "iduser", $text, "iduser", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("service_other", "id_user", $text, "id_user", $user['Processing_value']);
            \Faoxima\Core\Database\Db::update("Giftcodeconsumed", "id_user", $text, "id_user", $user['Processing_value']);
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        case 8:
            $__qrDisabledNow = method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') && \Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled();
            $__qrToggleLabel = $__qrDisabledNow ? "✅ فعال کردن کیو آر کد در سراسر سیستم" : "🚫 غیرفعال کردن کیو آر کد در سراسر سیستم";
            $__qrStatusText  = $__qrDisabledNow ? "❌ کیو آر کد در حال حاضر <b>غیرفعال</b> است." : "✅ کیو آر کد در حال حاضر <b>فعال</b> است.";
            $__qrSettingsKb = json_encode([
                'inline_keyboard' => [
                    [['text' => $__qrToggleLabel, 'callback_data' => 'set_qr_toggle']],
                    [['text' => "🖼 تغییر پس‌زمینه کیو آر کد", 'callback_data' => 'set_qrbg']],
                    [['text' => "🔙 بازگشت", 'callback_data' => 'admin_settings']],
                ],
            ], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📷 <b>تنظیمات کیو آر کد</b>\n\n{$__qrStatusText}", $__qrSettingsKb, 'HTML');
            break;
        case 9:
            $__qrDisabledNow = method_exists(\Faoxima\Telegram\Support\InfoCardRenderer::class, 'isQrDisabled') && \Faoxima\Telegram\Support\InfoCardRenderer::isQrDisabled();
            $__newVal = $__qrDisabledNow ? '0' : '1';
            $__existingQrRow = \Faoxima\Core\Database\Db::select("shopSetting", "*", "Namevalue", "qr_disabled", "select");
            if (is_array($__existingQrRow)) {
                \Faoxima\Core\Database\Db::update("shopSetting", "value", $__newVal, "Namevalue", "qr_disabled");
            } else {
                $__qrPdo = \Faoxima\Core\Database\Db::runtimeReady() ? \Faoxima\Core\Database\Db::getDatabaseConnection() : ($GLOBALS['pdo'] ?? null);
                if ($__qrPdo instanceof PDO) {
                    $__qrPdo->prepare("INSERT INTO shopSetting (Namevalue, value) VALUES ('qr_disabled', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute([$__newVal]);
                }
            }
            $__qrNowDisabled = ($__newVal === '1');
            $__qrToggleLabel2 = $__qrNowDisabled ? "✅ فعال کردن کیو آر کد در سراسر سیستم" : "🚫 غیرفعال کردن کیو آر کد در سراسر سیستم";
            $__qrStatusText2  = $__qrNowDisabled ? "❌ کیو آر کد در حال حاضر <b>غیرفعال</b> است." : "✅ کیو آر کد در حال حاضر <b>فعال</b> است.";
            $__qrSettingsKb2 = json_encode([
                'inline_keyboard' => [
                    [['text' => $__qrToggleLabel2, 'callback_data' => 'set_qr_toggle']],
                    [['text' => "🖼 تغییر پس‌زمینه کیو آر کد", 'callback_data' => 'set_qrbg']],
                    [['text' => "🔙 بازگشت", 'callback_data' => 'admin_settings']],
                ],
            ], JSON_UNESCAPED_UNICODE);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📷 <b>تنظیمات کیو آر کد</b>\n\n{$__qrStatusText2}\n\n" . ($__qrNowDisabled ? "🚫 کیو آر کد با موفقیت <b>غیرفعال</b> شد." : "✅ کیو آر کد با موفقیت <b>فعال</b> شد."), $__qrSettingsKb2, 'HTML');
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "تصویر خود را برای پس زمینه ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("getimagebackgroundqr", $from_id);
            break;
        case 11:
            if (!$photo) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "تصویر نامعتبر است", $backadmin, 'HTML');
                return;
            }
            $response = getFileddire($photoid);
            if ($response['ok']) {
                $filePath = $response['result']['file_path'];
                $fileUrl = "https://api.telegram.org/file/bot$APIKEY/$filePath";
                $fileContent = @file_get_contents($fileUrl);

                $projectRoot = REFACTORED_LEGACY_ROOT;
                $customBgPath = $projectRoot . '/custom.jpg';
                $saved = false;
                if (is_string($fileContent) && $fileContent !== '' && @getimagesizefromstring($fileContent) !== false) {
                    $tmpBgPath = $customBgPath . '.' . getmypid() . '.' . substr(md5($fileContent), 0, 8) . '.tmp';
                    $written = @file_put_contents($tmpBgPath, $fileContent, LOCK_EX);
                    clearstatcache(true, $tmpBgPath);
                    if ($written === strlen($fileContent) && @filesize($tmpBgPath) === $written && @getimagesize($tmpBgPath) !== false && @rename($tmpBgPath, $customBgPath)) {
                        clearstatcache(true, $customBgPath);
                        $saved = is_file($customBgPath) && is_readable($customBgPath) && @filesize($customBgPath) === $written && @getimagesize($customBgPath) !== false;
                    }
                    if (is_file($tmpBgPath)) {
                        @unlink($tmpBgPath);
                    }
                }
                if ($saved) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "🖼 پس زمینه با موفقیت تنظیم گردید (همه‌جا اعمال شد: ربات، مینی‌اپ، کیف‌پول‌های ارز)", $setting_panel, 'HTML');
                } else {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ ذخیره‌سازی فایل ناموفق بود — دسترسی نوشتن روی پوشه‌ی روت پروژه را بررسی کنید.", $setting_panel, 'HTML');
                }
                \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            }
            break;
        case 12:
            if ($text == "🎛 تنظیم نام گروه") {
                $textsetprotocol = "📌 نام گروهی که بصورت پیشفرض می خواهید از آن ساخته شود را ارسال نمایید.";
            } elseif ($text == "⚙️ تنظیم نود") {
                $textsetprotocol = "📌 برای تنظیم نود یک کاربر در پنل خود ساخته و  نودهایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کاربر را ارسال نمایید";
            } else {
                $textsetprotocol = "📌 برای تنظیم اینباند  و پروتکل باید یک کانفیگ در پنل خود ساخته و  پروتکل و اینباند هایی که میخواهید فعال باشند. را داخل پنل فعال کرده و نام کاربری کانفیگ را ارسال نمایید";
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textsetprotocol, $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("setinboundandprotocol", $from_id);
            break;
        case 13:
            if (!isset($update['message']) && empty($text)) { return; }
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
            if ($panel['type'] == "marzban") {
                if ((string)($panel['version_panel'] ?? '0') === '1') {
                    $DataUserOut = \Faoxima\Services\Panels\Drivers\Marzban::getuser($text, $user['Processing_value']);
                    if (!empty($DataUserOut['error'])) {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $DataUserOut['error'], null, 'HTML');
                        return;
                    }
                    if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                        return;
                    }
                    $DataUserOut = json_decode($DataUserOut['body'], true);
                    if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxy_settings'])) {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                        return;
                    }
                    foreach ($DataUserOut['proxy_settings'] as $key => &$value) {
                        if ($key == "shadowsocks") {
                            unset($DataUserOut['proxy_settings'][$key]['password']);
                        } elseif ($key == "trojan") {
                            unset($DataUserOut['proxy_settings'][$key]['password']);
                        } else {
                            unset($DataUserOut['proxy_settings'][$key]['id']);
                        }
                        if (count($DataUserOut['proxy_settings'][$key]) == 0) {
                            $DataUserOut['proxy_settings'][$key] = new stdClass();
                        }
                    }
                    \Faoxima\Core\Database\Db::update("marzban_panel", "inbounds", json_encode($DataUserOut['group_ids']), "name_panel", $user['Processing_value']);
                    \Faoxima\Core\Database\Db::update("marzban_panel", "proxies", json_encode($DataUserOut['proxy_settings'], true), "name_panel", $user['Processing_value']);
                } else {
                    $DataUserOut = \Faoxima\Services\Panels\Drivers\Marzban::getuser($text, $user['Processing_value']);
                    if (!empty($DataUserOut['error'])) {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $DataUserOut['error'], null, 'HTML');
                        return;
                    }
                    if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                        return;
                    }
                    $DataUserOut = json_decode($DataUserOut['body'], true);
                    if ((isset($DataUserOut['msg']) && $DataUserOut['msg'] == "User not found") or !isset($DataUserOut['proxies'])) {
                        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                        return;
                    }
                    foreach ($DataUserOut['proxies'] as $key => &$value) {
                        if ($key == "shadowsocks") {
                            unset($DataUserOut['proxies'][$key]['password']);
                        } elseif ($key == "trojan") {
                            unset($DataUserOut['proxies'][$key]['password']);
                        } else {
                            unset($DataUserOut['proxies'][$key]['id']);
                        }
                        if (count($DataUserOut['proxies'][$key]) == 0) {
                            $DataUserOut['proxies'][$key] = new stdClass();
                        }
                    }
                    \Faoxima\Core\Database\Db::update("marzban_panel", "inbounds", json_encode($DataUserOut['inbounds']), "name_panel", $user['Processing_value']);
                    \Faoxima\Core\Database\Db::update("marzban_panel", "proxies", json_encode($DataUserOut['proxies'], true), "name_panel", $user['Processing_value']);
                }
            } elseif ($panel['type'] == "pasarguard") {
                $DataUserOut = \Faoxima\Services\Panels\Drivers\PasarGuard::pasarguardGetUser($text, $user['Processing_value']);
                if (!empty($DataUserOut['error'])) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $DataUserOut['error'], null, 'HTML');
                    return;
                }
                if (!empty($DataUserOut['status']) && $DataUserOut['status'] != 200) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌  خطایی رخ داده است کد خطا :  {$DataUserOut['status']}", null, 'HTML');
                    return;
                }
                $DataUserOut = json_decode($DataUserOut['body'], true);
                if ((isset($DataUserOut['detail']) && !empty($DataUserOut['detail'])) or !isset($DataUserOut['proxy_settings'])) {
                    \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['stateus']['UserNotFound'], null, 'html');
                    return;
                }
                foreach ($DataUserOut['proxy_settings'] as $key => &$value) {
                    if ($key == "shadowsocks") {
                        unset($DataUserOut['proxy_settings'][$key]['password']);
                    } elseif ($key == "trojan") {
                        unset($DataUserOut['proxy_settings'][$key]['password']);
                    } elseif ($key == "wireguard") {
                        unset($DataUserOut['proxy_settings'][$key]['private_key']);
                        unset($DataUserOut['proxy_settings'][$key]['public_key']);
                        unset($DataUserOut['proxy_settings'][$key]['peer_ips']);
                    } elseif ($key == "hysteria") {
                        unset($DataUserOut['proxy_settings'][$key]['auth']);
                    } else {
                        unset($DataUserOut['proxy_settings'][$key]['id']);
                    }
                    if (count($DataUserOut['proxy_settings'][$key]) == 0) {
                        $DataUserOut['proxy_settings'][$key] = new stdClass();
                    }
                }
                \Faoxima\Core\Database\Db::update("marzban_panel", "inbounds", json_encode($DataUserOut['group_ids'] ?? []), "name_panel", $user['Processing_value']);
                \Faoxima\Core\Database\Db::update("marzban_panel", "proxies", json_encode($DataUserOut['proxy_settings'], true), "name_panel", $user['Processing_value']);
            }
            $rxInboundSetupKeyboard = $panel['type'] == "pasarguard" ? $optionPasarGuard : $optionMarzban;
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ اینباند و پروتکل های شما با موفقیت تنظیم گردیدند.", $rxInboundSetupKeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("home", $from_id);
            break;
        }

        return true;
    }
}
