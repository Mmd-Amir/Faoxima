<?php

namespace Faoxima\Admin\Keyboard;


/**
 * Handler stage. Inherited by value: $datatextbot, $setting, $textbotlang.
 * Inherited or exported by reference: $adminrule, $affiliates, $affiliatesAntiFraud, $helpedit, $keyboardexportdata, $supportoption.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class SupportRoleAndReferrals
{
    public static function handle(
        &$adminrule,
        &$affiliates,
        &$affiliatesAntiFraud,
        $datatextbot,
        &$helpedit,
        &$keyboardexportdata,
        $setting,
        &$supportoption,
        $textbotlang
    ) {
        if($setting['statussupportpv'] == "onpvsupport"){
            $supportoption = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                        ['text' => "🎟 پیام پشتیبانی", 'url' => "https://t.me/{$setting['id_support']}"    ],
                    ],[
                        ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
                    ],

                ]
            ]);
        }else{
        $supportoption = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $datatextbot['text_fq'], 'callback_data' => "fqQuestions"] ,
                        ['text' => "🎟 پیام پشتیبانی", 'callback_data' => "support"],
                    ],[
                        ['text' => "🔙 بازگشت به منوی اصلی" ,'callback_data' => "backuser"]
                    ],

                ]
            ]);
        }
        $adminrule = json_encode([
            'keyboard' => [
                [['text' => "administrator"],['text' => "Seller"],['text' => "support"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);
        $rxAffiliatesSettingsRow = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
        $rxAffiliatesMenuMode = (is_array($rxAffiliatesSettingsRow) && ($rxAffiliatesSettingsRow['menu_keyboard_mode'] ?? '') === 'inline_menu') ? 'inline_menu' : 'reply_menu';

        $affiliatesReplyRows = [
            [['text' => "🖼 مشاهده بنر فعال"]],
            [['text' => "🏞 تنظیم عکس بنر"], ['text' => "✏️ ویرایش متن بنر"]],
            [['text' => "🗑 حذف بنر"], ['text' => "♻️ بازگردانی بنر پیش‌فرض"]],
            [['text' => "🧮 تنظیم درصد زیرمجموعه"], ['text' => "🌟 مبلغ هدیه استارت"]],
            [['text' => "🎁 هدیه استارت"], ['text' => "🎁 پورسانت بعد از خرید"]],
            [['text' => "🎉 پورسانت فقط برای خرید اول"]],
            [['text' => "🛡 حفاظت از سواستفاده"]],
            [['text' => "📊 گزارشات زیرمجموعه"]],
            [['text' => $textbotlang['Admin']['backmenu']], ['text' => $textbotlang['Admin']['backadmin']]]
        ];

        $affiliatesInlineRows = [
            [['text' => "🖼 مشاهده بنر فعال", 'callback_data' => "affiliates_view_banner"]],
            [['text' => "🏞 تنظیم عکس بنر", 'callback_data' => "affiliates_edit_banner_image"], ['text' => "✏️ ویرایش متن بنر", 'callback_data' => "affiliates_edit_banner_text"]],
            [['text' => "🗑 حذف بنر", 'callback_data' => "affiliates_delete_banner"], ['text' => "♻️ بازگردانی بنر پیش‌فرض", 'callback_data' => "affiliates_reset_banner"]],
            [['text' => "🧮 تنظیم درصد زیرمجموعه", 'callback_data' => "affiliates_set_percentage"], ['text' => "🌟 مبلغ هدیه استارت", 'callback_data' => "affiliates_set_start_amount"]],
            [['text' => "🎁 هدیه استارت", 'callback_data' => "affiliates_toggle_start_gift"], ['text' => "🎁 پورسانت بعد از خرید", 'callback_data' => "affiliates_toggle_commission"]],
            [['text' => "🎉 پورسانت فقط برای خرید اول", 'callback_data' => "affiliates_toggle_first_buy_only"]],
            [['text' => "🛡 حفاظت از سواستفاده", 'callback_data' => "affiliates_antifraud_menu"]],
            [['text' => "📊 گزارشات زیرمجموعه", 'callback_data' => "affiliates_reports"]],
            [['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => "backmenu"]]
        ];

        $affiliates = $rxAffiliatesMenuMode === 'inline_menu'
            ? json_encode(['inline_keyboard' => $affiliatesInlineRows])
            : json_encode(['keyboard' => $affiliatesReplyRows, 'resize_keyboard' => true]);

        $affiliatesAntiFraud = json_encode([
            'keyboard' => [
                [['text' => "☎️ الزام تایید شماره"]],
                [['text' => "⏳ حداقل سن اکانت"]],
                [['text' => "📅 سقف روزانه معرفی"], ['text' => "🗓 سقف ماهانه معرفی"]],
                [['text' => $textbotlang['Admin']['backmenu']], ['text' => $textbotlang['Admin']['backadmin']]]
            ],
            'resize_keyboard' => true
        ]);
        $keyboardexportdata =  json_encode([
            'keyboard' => [
                [['text' => "خروجی کاربران"],['text' => "خروجی سفارشات"]],
                [['text' => "خروجی گرفتن پرداخت ها"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);
        $helpedit =  json_encode([
            'keyboard' => [
                [['text' =>"ویرایش نام آموزش"],['text' =>"ویرایش توضیحات"]],
                [['text' => "ویرایش رسانه"],['text' => "ویرایش دسته بندی"]],
                [['text' => "ویرایش لینک برنامه"]],
                [['text' => $textbotlang['Admin']['backadmin']],['text' => $textbotlang['Admin']['backmenu']]]
            ],
            'resize_keyboard' => true
        ]);

        return 1;
    }
}
