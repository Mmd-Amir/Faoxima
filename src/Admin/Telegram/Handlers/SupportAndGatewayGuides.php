<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $CartManage, $adminAction, $adminExecution, $backadmin, $caption, $from_id, $nowpayment_setting_keyboard, $photo, $photoid, $supportcenter, $text, $textbotlang, $tonpay, $video, $videoid.
 * Inherited or exported by reference: $data.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class SupportAndGatewayGuides
{
    public static function handle(
        $CartManage,
        $adminAction,
        $adminExecution,
        $backadmin,
        $caption,
        &$data,
        $from_id,
        $nowpayment_setting_keyboard,
        $photo,
        $photoid,
        $supportcenter,
        $text,
        $textbotlang,
        $tonpay,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['SettingPayment']['CartDirect'], $supportcenter, 'HTML');
            \Faoxima\Core\Database\Db::update("setting", "id_support", $text, null, null);
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 2:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("gethelpcart", $from_id);
            break;
        case 3:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "2", "NamePay", "helpcart");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpcart");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
            break;
        case 4:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("gethelpnowpayment", $from_id);
            break;
        case 5:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "2", "NamePay", "helpnowpayment");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpnowpayment");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $nowpayment_setting_keyboard, 'HTML');
            break;
        case 6:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("gethelpperfect", $from_id);
            break;
        case 7:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpperfectmony");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpperfectmony");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("gethelpplisio", $from_id);
            break;
        case 9:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpplisio");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpplisio");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("helpiranpay2", $from_id);
            break;
        case 11:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpiranpay2");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpiranpay2");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
            break;
        case 12:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("helptonpay", $from_id);
            break;
        case 13:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helptonpay");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helptonpay");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helptonpay");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helptonpay");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $tonpay, 'HTML');
            break;
        case 14:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("helpblupal", $from_id);
            break;
        }

        return true;
    }
}
