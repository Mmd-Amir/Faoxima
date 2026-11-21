<?php

namespace Faoxima\Admin\Telegram\Handlers;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $affiliates, $affiliatesAntiFraud, $backadmin, $datatextbot, $from_id, $message_id, $pdo, $text, $textbotlang, $update.
 * Inherited or exported by reference: none.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class AffiliatePayoutAndReports
{
    public static function handle(
        $adminAction,
        $adminExecution,
        $affiliates,
        $affiliatesAntiFraud,
        $backadmin,
        $datatextbot,
        $from_id,
        $message_id,
        $pdo,
        $text,
        $textbotlang,
        $update
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbancommission = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardcommission = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['commission'], $keyboardcommission, 'HTML');
            break;
        case 2:
            \Faoxima\Core\Database\Db::update("affiliates", "status_commission", "offcommission");
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbancommission = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardcommission = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatusOff'], $keyboardcommission);
            break;
        case 3:
            \Faoxima\Core\Database\Db::update("affiliates", "status_commission", "oncommission");
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbancommission = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardcommission = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['commissionStatuson'], $keyboardcommission);
            break;
        case 4:
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbanDiscountaffiliates = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardDiscountaffiliates = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['Discountaffiliates'], $keyboardDiscountaffiliates, 'HTML');
            break;
        case 5:
            \Faoxima\Core\Database\Db::update("affiliates", "Discount", "offDiscountaffiliates");
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbanDiscountaffiliates = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardDiscountaffiliates = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatusOff'], $keyboardDiscountaffiliates);
            break;
        case 6:
            \Faoxima\Core\Database\Db::update("affiliates", "Discount", "onDiscountaffiliates");
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbanDiscountaffiliates = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardDiscountaffiliates = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Status']['DiscountaffiliatesStatuson'], $keyboardDiscountaffiliates);
            break;
        case 7:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getdiscont', $from_id);
            break;
        case 8:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text) || (int) $text <= 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_amount'] ?? "❌ مبلغ نامعتبر است. لطفاً یک عدد مثبت وارد کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['changedpriceDiscount'], $affiliates, 'HTML');
            \Faoxima\Core\Database\Db::update("affiliates", "price_Discount", $text);
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['setpercentage'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('setpercentage', $from_id);
            break;
        case 10:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['affiliates']['priceDiscount'], $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('getdiscont', $from_id);
            break;
        case 11:
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbanDiscountaffiliates = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardDiscountaffiliates = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbanDiscountaffiliates['Discount'], 'callback_data' => $marzbanDiscountaffiliates['Discount']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['Discountaffiliates'], $keyboardDiscountaffiliates, 'HTML');
            break;
        case 12:
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $marzbancommission = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $keyboardcommission = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $marzbancommission['status_commission'], 'callback_data' => $marzbancommission['status_commission']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Status']['commission'], $keyboardcommission, 'HTML');
            break;
        case 13:
            \Faoxima\Telegram\Navigation\Steps::step('featnav_affiliates', $from_id);
            $rxOneBuy = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $rxOneBuyLabel = ($rxOneBuy['porsant_one_buy'] ?? '') === 'on_buy_porsant' ? "✅ فقط خرید اول" : "❌ همه خریدها";
            $keyboardOneBuy = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $rxOneBuyLabel, 'callback_data' => $rxOneBuy['porsant_one_buy']],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "می‌توانید تعیین کنید که پورسانت به کاربر فقط برای اولین خرید زیرمجموعه‌اش داده شود یا برای همه خریدهای او.", $keyboardOneBuy, 'HTML');
            break;
        case 14:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_antifraud_title'] ?? "🛡 تنظیمات ضد سواستفاده از سیستم زیرمجموعه‌گیری را انتخاب کنید", $affiliatesAntiFraud, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('affiliates_antifraud_menu', $from_id);
            break;
        case 15:
            \Faoxima\Telegram\Navigation\Steps::step('affiliates_antifraud_menu', $from_id);
            $rxAntiFraud = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $rxPhoneReqLabel = ($rxAntiFraud['require_phone_verified'] ?? '') === 'on_require_phone' ? "✅ فعال" : "❌ غیرفعال";
            $keyboardPhoneReq = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $rxPhoneReqLabel, 'callback_data' => "affiliates_toggle_phone_required"],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_phone_required_title'] ?? "📞 در صورت فعال بودن، کاربران باید شماره موبایل خود را قبل از دریافت هدیه عضویت تایید کنند.", $keyboardPhoneReq, 'HTML');
            break;
        case 16:
            $rxAntiFraud = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $rxNewPhoneReq = ($rxAntiFraud['require_phone_verified'] ?? '') === 'on_require_phone' ? 'off_require_phone' : 'on_require_phone';
            \Faoxima\Core\Database\Db::update("affiliates", "require_phone_verified", $rxNewPhoneReq);
            \Faoxima\Telegram\Navigation\Steps::step('affiliates_antifraud_menu', $from_id);
            $rxPhoneReqLabel = $rxNewPhoneReq === 'on_require_phone' ? "✅ فعال" : "❌ غیرفعال";
            $keyboardPhoneReq = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $rxPhoneReqLabel, 'callback_data' => "affiliates_toggle_phone_required"],
                    ],
                    [
                        ['text' => $textbotlang['Admin']['backmenu'], 'callback_data' => 'backmenu'],
                    ],
                ]
            ]);
            Editmessagetext($from_id, $message_id, $datatextbot['dyn_affiliates_extra_phone_required_title'] ?? "📞 در صورت فعال بودن، کاربران باید شماره موبایل خود را قبل از دریافت هدیه عضویت تایید کنند.", $keyboardPhoneReq);
            break;
        case 17:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_min_age_prompt'] ?? "⏳ حداقل سن اکانت (به ساعت) برای واجد شرایط بودن دریافت هدیه عضویت را وارد کنید. برای غیرفعال کردن این محدودیت عدد 0 را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('setminaccountage', $from_id);
            break;
        case 18:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text) || (int) $text < 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_amount'] ?? "❌ مقدار نامعتبر است. لطفاً یک عدد وارد کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("affiliates", "min_account_age_hours", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_min_age_saved'] ?? "✅ حداقل سن اکانت ذخیره شد.", $affiliatesAntiFraud, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('affiliates_antifraud_menu', $from_id);
            break;
        case 19:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_daily_cap_prompt'] ?? "📅 حداکثر تعداد هدیه عضویت قابل پرداخت به هر معرف در روز را وارد کنید. برای غیرفعال کردن سقف عدد 0 را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('setdailycap', $from_id);
            break;
        case 20:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text) || (int) $text < 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_amount'] ?? "❌ مقدار نامعتبر است. لطفاً یک عدد وارد کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("affiliates", "daily_referral_cap", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_daily_cap_saved'] ?? "✅ سقف روزانه ذخیره شد.", $affiliatesAntiFraud, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('affiliates_antifraud_menu', $from_id);
            break;
        case 21:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_monthly_cap_prompt'] ?? "🗓 حداکثر تعداد هدیه عضویت قابل پرداخت به هر معرف در ماه را وارد کنید. برای غیرفعال کردن سقف عدد 0 را ارسال کنید.", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('setmonthlycap', $from_id);
            break;
        case 22:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text) || (int) $text < 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_invalid_amount'] ?? "❌ مقدار نامعتبر است. لطفاً یک عدد وارد کنید.", $backadmin, 'HTML');
                return;
            }
            \Faoxima\Core\Database\Db::update("affiliates", "monthly_referral_cap", $text);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $datatextbot['dyn_affiliates_extra_monthly_cap_saved'] ?? "✅ سقف ماهانه ذخیره شد.", $affiliatesAntiFraud, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('affiliates_antifraud_menu', $from_id);
            break;
        case 23:
            [$rxTotalCommission, $rxTotalStartGiftClaims, $rxTotalReferredUsers, $rxTopReferrers] = \Faoxima\Admin\Telegram\Support\FinanceFunctionsHelpers::rxAdminAffiliateReportData($pdo);
            $rxTopReferrersText = "";
            $rxRank = 1;
            foreach ($rxTopReferrers as $rxRow) {
                $rxTopReferrersText .= "{$rxRank}. @{$rxRow['uname']} — " . number_format((float) $rxRow['total_commission']) . " تومان ({$rxRow['payouts']} پرداخت)\n";
                $rxRank++;
            }
            if ($rxTopReferrersText === '') {
                $rxTopReferrersText = "— هنوز پورسانتی پرداخت نشده است —";
            }
            $rxReportsText = "<b>📊 گزارشات زیرمجموعه‌گیری</b>

💰 مجموع پورسانت پرداخت شده: {$rxTotalCommission} تومان
🎁 تعداد هدیه‌های عضویت پرداخت شده: {$rxTotalStartGiftClaims}
👥 تعداد کل کاربران جذب شده از طریق زیرمجموعه: {$rxTotalReferredUsers}

<b>🏆 برترین معرف‌ها:</b>
{$rxTopReferrersText}";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $rxReportsText, $affiliates, 'HTML');
            break;
        }

        return true;
    }
}
