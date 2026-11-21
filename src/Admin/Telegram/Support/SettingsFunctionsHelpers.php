<?php

namespace Faoxima\Admin\Telegram\Support;

use PDO;

final class SettingsFunctionsHelpers
{
    public static function rxAdminManualConfigNames(PDO $pdo, $panel): array
    {
        $listconfig = [];
        $stmt = $pdo->prepare("SELECT namerecord FROM manualsell WHERE codepanel = ?");
        $stmt->execute([(string) $panel['code_panel']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $listconfig[] = [$row['namerecord']];
        }
        return $listconfig;
    }

    public static function rxAdminManualConfigKeyboard(array $listconfig)
    {
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
        return json_encode($list_configmanual);
    }

    public static function rxAdminManualConfigInsert(PDO $pdo, $panel, $userdata, $text): void
    {
        $status = "active";
        $stmt = $pdo->prepare("INSERT IGNORE INTO manualsell (codepanel,namerecord,contentrecord,status,codeproduct) VALUES (:codepanel,:namerecord,:contentrecord,:status,:codeproduct)");
        $stmt->bindParam(':codepanel', $panel['code_panel']);
        $stmt->bindParam(':namerecord', $userdata['namerecord']);
        $stmt->bindParam(':contentrecord', $text);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':codeproduct', $userdata['codeproduct']);
        $stmt->execute();
    }

    public static function rxAdminGatewayLabelRoute($text, $step): ?array
    {
        $routes = [
            ['labels' => ["🗂 نام درگاه کارت به کارت", "🏷️ نام نمایشی درگاه کارت به کارت"], 'step' => 'getnamecarttocart', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه کارت به کارت را ارسال کنید.", 'key' => 'carttocart', 'keyboard' => 'CartManage'],
            ['labels' => ["🗂 نام درگاه nowpayment", "🏷️ نام نمایشی درگاه nowpayment"], 'step' => 'getnamenowpayment', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه nowpayment را ارسال کنید.", 'key' => 'textsnowpayment', 'keyboard' => 'nowpayment_setting_keyboard'],
            ['labels' => ["🗂 نام درگاه ریالی بدون احراز"], 'step' => 'getnamecarttopaynotverify', 'prompt' => " 📌 نام درگاه را ارسال نمايید", 'key' => 'textpaymentnotverify', 'keyboard' => 'CartManage'],
            ['labels' => ["🗂 نام درگاه   plisio", "🏷️ نام نمایشی درگاه plisio"], 'step' => 'gettextnowpayment', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه plisio را ارسال کنید.", 'key' => 'textnowpayment', 'keyboard' => 'NowPaymentsManage'],
            ['labels' => ["🗂 نام درگاه رمز ارز آفلاین", "🏷️ نام نمایشی درگاه رمز ارز آفلاین"], 'step' => 'gettextnowpaymentTRON', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه رمز ارز آفلاین را ارسال کنید.", 'key' => 'textnowpaymenttron', 'keyboard' => 'tronnowpayments'],
            ['labels' => ["🗂 نام درگاه استار", "🏷️ نام نمایشی درگاه استار"], 'step' => 'gettextstartelegram', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه استار را ارسال کنید.", 'key' => 'text_star_telegram', 'keyboard' => 'Startelegram'],
            ['labels' => ["🏷️ نام نمایشی درگاه ترونادو"], 'step' => 'gettextiranpay3', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه ترونادو را ارسال کنید.", 'key' => 'iranpay3', 'keyboard' => 'trnado'],
            ['labels' => ["🏷️ نام نمایشی درگاه تون‌پی"], 'step' => 'gettexttonpay', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه تون‌پی را ارسال کنید.", 'key' => 'tonpay', 'keyboard' => 'tonpay'],
            ['labels' => ["🏷️ نام نمایشی درگاه کیوب‌پی"], 'step' => 'gettextcubepay', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه کیوب‌پی را ارسال کنید.", 'key' => 'cubepay', 'keyboard' => 'cubepay'],
            ['labels' => ["🏷️ نام نمایشی درگاه واریزا"], 'step' => 'gettextvariza', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه واریزا را ارسال کنید.", 'key' => 'variza', 'keyboard' => 'variza'],
            ['labels' => ["🏷️ نام نمایشی درگاه آبان گیت وی"], 'step' => 'gettextabangateway', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه آبان گیت وی را ارسال کنید.", 'key' => 'abangateway', 'keyboard' => 'abangateway'],
            ['labels' => ["🏷️ نام نمایشی درگاه بلوپال"], 'step' => 'gettextblupal', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه بلوپال را ارسال کنید.", 'key' => 'blupal', 'keyboard' => 'blupal'],
            ['labels' => ["🏷️ نام نمایشی درگاه اطلس‌پی"], 'step' => 'gettextatlaspay', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه اطلس‌پی را ارسال کنید.", 'key' => 'atlaspay', 'keyboard' => 'atlaspay'],
            ['labels' => ["🗂 نام درگاه ریالی سوم"], 'step' => 'gettextiranpay1', 'prompt' => " 📌 نام درگاه را ارسال نمايید", 'key' => 'iranpay1', 'keyboard' => 'iranpaykeyboard'],
            ['labels' => ["🗂 درگاه زرین پال", "🏷️ نام نمایشی درگاه زرین پال"], 'step' => 'gettextzarinpal', 'prompt' => "🏷️ نام نمایشی دلخواه برای درگاه زرین پال را ارسال کنید.", 'key' => 'zarinpal', 'keyboard' => 'keyboardzarinpal'],
        ];
        return self::rxAdminMatchSettingsRoute($routes, $text, $step);
    }

    public static function rxAdminGatewayLimitRoute($text, $step): ?array
    {
        $routes = [
            ['labels' => ["⬇️ کف plisio"], 'step' => 'getmainplisio', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'NowPaymentsManage', 'key' => 'minbalanceplisio'],
            ['labels' => ["⬆️ سقف plisio"], 'step' => 'getmaxplisio', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'NowPaymentsManage', 'key' => 'maxbalanceplisio'],
            ['labels' => ["⬇️ کف رمزارز آفلاین"], 'step' => 'getmaindigitaltron', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'tronnowpayments', 'key' => 'minbalancedigitaltron'],
            ['labels' => ["⬆️ سقف رمزارز آفلاین"], 'step' => 'getmaxdigitaltron', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'tronnowpayments', 'key' => 'maxbalancedigitaltron'],
            ['labels' => ["⬇️ کف ترونادو"], 'step' => 'getmainiranpay2', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'trnado', 'key' => 'minbalanceiranpay2'],
            ['labels' => ["⬆️ سقف ترونادو"], 'step' => 'getmaaxiranpay2', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'trnado', 'key' => 'maxbalanceiranpay2'],
            ['labels' => ["⬇️ کف تون‌پی"], 'step' => 'getmaintonpay', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'tonpay', 'key' => 'minbalancetonpay'],
            ['labels' => ["⬆️ سقف تون‌پی"], 'step' => 'getmaxtonpay', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'tonpay', 'key' => 'maxbalancetonpay'],
            ['labels' => ["⬇️ کف بلوپال"], 'step' => 'getmainblupal', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'blupal', 'key' => 'minbalanceblupal'],
            ['labels' => ["⬆️ سقف بلوپال"], 'step' => 'getmaxblupal', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'blupal', 'key' => 'maxbalanceblupal'],
            ['labels' => ["⬇️ کف واریزا"], 'step' => 'getmainvariza', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'variza', 'key' => 'minbalancevariza'],
            ['labels' => ["⬆️ سقف واریزا"], 'step' => 'getmaxvariza', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'variza', 'key' => 'maxbalancevariza'],
            ['labels' => ["⬇️ کف آبان گیت وی"], 'step' => 'getmainabangateway', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'abangateway', 'key' => 'minbalanceabangateway'],
            ['labels' => ["⬆️ سقف آبان گیت وی"], 'step' => 'getmaxabangateway', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'abangateway', 'key' => 'maxbalanceabangateway'],
            ['labels' => ["⬇️ کف اطلس‌پی"], 'step' => 'getmainatlaspay', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'atlaspay', 'key' => 'minbalanceatlaspay'],
            ['labels' => ["⬆️ سقف اطلس‌پی"], 'step' => 'getmaxatlaspay', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'atlaspay', 'key' => 'maxbalanceatlaspay'],
            ['labels' => ["⬇️ کف کیوب‌پی"], 'step' => 'getmaincubepay', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'cubepay', 'key' => 'minbalancecubepay'],
            ['labels' => ["⬆️ سقف کیوب‌پی"], 'step' => 'getmaxcubepay', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'cubepay', 'key' => 'maxbalancecubepay'],
            ['labels' => ["⬇️ کف زرین پال"], 'step' => 'getmainaqzarinpal', 'prompt' => "📌 حداقل مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداقل مبلغ واریزی تنظیم گردید.", 'keyboard' => 'keyboardzarinpal', 'key' => 'minbalancezarinpal'],
            ['labels' => ["⬆️ سقف زرین پال"], 'step' => 'getmaaxzarinpal', 'prompt' => "📌 حداکثر مبلغ واریزی را ارسال نمایید", 'saved' => "✅ حداکثر مبلغ واریزی تنظیم گردید.", 'keyboard' => 'keyboardzarinpal', 'key' => 'maxbalancezarinpal'],
        ];
        return self::rxAdminMatchSettingsRoute($routes, $text, $step);
    }

    public static function rxAdminMatchSettingsRoute(array $routes, $text, $step): ?array
    {
        foreach ($routes as $route) {
            foreach ($route['labels'] as $label) {
                if ($text == $label) {
                    return ['is_prompt' => true] + $route;
                }
            }
            if ($step == $route['step']) {
                return ['is_prompt' => false] + $route;
            }
        }
        return null;
    }

    public static function rxAdminSaveGatewayLabel($adminId, $text, string $key, $keyboard): void
    {
        \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($adminId, "✅  متن با موفقیت تنظیم گردید.", $keyboard, 'HTML');
        \Faoxima\Core\Database\Db::update("textbot", "text", $text, "id_text", $key);
        \Faoxima\Telegram\Navigation\Steps::step("home", $adminId);
    }

    public static function rx_iranpay_label($datatextbot, $key, $fallback)
    {
        $name = (is_array($datatextbot) && isset($datatextbot[$key])) ? trim((string)$datatextbot[$key]) : '';
        if ($name !== '') {
            return "📌 " . $name;
        }
        return $fallback;
    }
}
