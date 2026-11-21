<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $CartManage, $abangateway, $adminAction, $adminExecution, $atlaspay, $backadmin, $blupal, $caption, $cubepay, $from_id, $photo, $photoid, $text, $variza, $video, $videoid.
 * Inherited or exported by reference: $data.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class GatewayGuidesAndPricing
{
    public static function handle(
        $CartManage,
        $abangateway,
        $adminAction,
        $adminExecution,
        $atlaspay,
        $backadmin,
        $blupal,
        $caption,
        $cubepay,
        &$data,
        $from_id,
        $photo,
        $photoid,
        $text,
        $variza,
        $video,
        $videoid
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpblupal");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpblupal");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpblupal");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpblupal");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $blupal, 'HTML');
            break;
        case 2:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("helpvariza", $from_id);
            break;
        case 3:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpvariza");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpvariza");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpvariza");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpvariza");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $variza, 'HTML');
            break;
        case 4:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("helpabangateway", $from_id);
            break;
        case 5:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "2", "NamePay", "helpabangateway");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpabangateway");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpabangateway");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpabangateway");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $abangateway, 'HTML');
            break;
        case 6:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("helpatlaspay", $from_id);
            break;
        case 7:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpatlaspay");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpatlaspay");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpatlaspay");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpatlaspay");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $atlaspay, 'HTML');
            break;
        case 8:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("helpcubepay", $from_id);
            break;
        case 9:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpcubepay");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpcubepay");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpcubepay");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpcubepay");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $cubepay, 'HTML');
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "📌آموزش خود را ارسال نمایید .
۱ - در صورتی که میخواید اموزشی نشان داده نشود عدد 2 را ارسال کنید
۲ - شما می توانید آموزش بصورت فیلم ُ  متن ُ تصویر ارسال نمایید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step("helpzarinpal", $from_id);
            break;
        case 11:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpcart");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpzarinpal");
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
            \Faoxima\Telegram\Navigation\Steps::step("helpofflinearze", $from_id);
            break;
        case 13:
            if ($text) {
                if (intval($text) == 2) {
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", "0", "NamePay", "helpofflinearze");
                } else {
                    $data = json_encode(array(
                        'type' => "text",
                        'text' => $text
                    ));
                    \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
                }
            } elseif ($photo) {
                $data = json_encode(array(
                    'type' => "photo",
                    'text' => $caption,
                    'photoid' => $photoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
            } elseif ($video) {
                $data = json_encode(array(
                    'type' => "video",
                    'text' => $caption,
                    'videoid' => $videoid
                ));
                \Faoxima\Core\Database\Db::update("PaySetting", "ValuePay", $data, "NamePay", "helpofflinearze");
            } else {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محتوای ارسال نامعتبر است.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ آموزش با موفقیت ذخیره گردید.", $CartManage, 'HTML');
            break;
        }

        return true;
    }
}
