<?php

namespace Faoxima\Telegram\Handlers;

use PDO;

/**
 * Handler stage. Inherited by value: $datatextbot, $from_id, $keyboard, $pdo, $setting, $telegramRoute, $textbotlang, $user, $username, $usernamebot.
 * Inherited or exported by reference: $affiliates, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class Referrals
{
    public static function handle(
        &$affiliates,
        $datatextbot,
        $from_id,
        $keyboard,
        $pdo,
        $setting,
        &$stmt,
        $telegramRoute,
        $textbotlang,
        $user,
        $username,
        $usernamebot
    ) {
        switch ($telegramRoute) {
        case 'referral':
            if (!\Faoxima\Telegram\Support\Transport::check_active_btn($setting['keyboardmain'], "text_affiliates")) {
                sendmessage($from_id, $datatextbot['dyn_errors_button_disabled'] ?? "❌ این دکمه غیرفعال می باشد", null, 'HTML');
                return;
            }
            if ($setting['affiliatesstatus'] == "offaffiliates") {
                sendmessage($from_id, $textbotlang['users']['affiliates']['offaffiliates'], null, 'HTML');
                return;
            }
            $affiliates = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $rxAffiliateLink = "https://t.me/$usernamebot?start=$from_id";
            $rxAffiliateBannerTpl = !empty($affiliates['banner_text']) ? $affiliates['banner_text'] : ($affiliates['description'] ?? '');
            $rxAffiliateBannerText = \Faoxima\Telegram\Support\Texts::faoxima_render_text($rxAffiliateBannerTpl, [
                'link' => $rxAffiliateLink,
                'name' => $user['first_name'] ?? $username,
                'code' => $user['codeInvitation'] ?? $from_id,
            ]);
            $textaffiliates = "{$rxAffiliateBannerText}\n\n🔗 {$rxAffiliateLink}";
            if (strlen($affiliates['id_media']) >= 5 && $affiliates['id_media'] !== 'none') {
                telegram('sendphoto', [
                    'chat_id' => $from_id,
                    'photo' => $affiliates['id_media'],
                    'caption' => $textaffiliates,
                    'parse_mode' => "HTML",
                ]);
            }
            $affiliatescommission = \Faoxima\Core\Database\Db::select("affiliates", "*", null, null, "select");
            $sqlPanel = "SELECT COUNT(*) AS orders, COALESCE(SUM(price_product), 0) AS total_price
                 FROM invoice
                 WHERE Status IN ('active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold')
                 AND refral = :refral
                 AND name_product != 'سرویس تست'";
            $stmt = $pdo->prepare($sqlPanel);
            $stmt->execute([':refral' => $from_id]);
            $inforefral = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['orders' => 0, 'total_price' => 0];
            $orders_count = (int)($inforefral['orders'] ?? 0);
            $total_purchase = (float)($inforefral['total_price'] ?? 0);
            $keyboard_share = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "🎁 هدیه عضویت", 'callback_data' => "get_gift_start", '_rxcolor' => 'affiliate:gift', '_rxsk' => 'affiliatesbtn', '_rxkeep' => true],
                        ['text' => "🔗 اشتراک لینک", 'url' => "https://t.me/share/url?url=https://t.me/$usernamebot?start=$from_id", '_rxcolor' => 'affiliate:share', '_rxsk' => 'affiliatesbtn', '_rxkeep' => true],
                    ],
                ]
            ]);
            $Percent_porsant = $setting['affiliatespercentage'];
            $sum_order = number_format($total_purchase, 0);
            $rxCommissionEarnedStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM wallet_ledger WHERE id_user = :id_user AND category = 'affiliate_commission' AND direction = 'credit'");
            $rxCommissionEarnedStmt->execute([':id_user' => $from_id]);
            $rxCommissionEarned = number_format((float) $rxCommissionEarnedStmt->fetchColumn());
            $rxPendingGiftStmt = $pdo->prepare("SELECT COUNT(*) FROM reagent_report WHERE reagent = :reagent AND get_gift = 0");
            $rxPendingGiftStmt->execute([':reagent' => $from_id]);
            $rxPendingGiftCount = (int) $rxPendingGiftStmt->fetchColumn();
            $textaffiliates = \Faoxima\Telegram\Support\FeatureFunctionsHelpers::rxBotReferralSummary(
                $affiliatescommission,
                $Percent_porsant,
                $user['affiliatescount'],
                $orders_count,
                $sum_order,
                $rxCommissionEarned,
                $rxPendingGiftCount,
                (string) ($textbotlang['users']['affiliates']['affiliateinfo'] ?? '')
            );

            sendmessage($from_id, $textaffiliates, $keyboard_share, 'HTML');
            break;
        case 'referral_gift':
            $rxGiftResult = \Faoxima\Services\Referrals\Referrals::rxReferralGiftPayout($from_id);
            $rxGiftStatus = (string) ($rxGiftResult['status'] ?? 'failed');
            if ($rxGiftStatus === 'paid') {
                \Faoxima\Services\Referrals\Referrals::rxReferralGiftNotify($rxGiftResult);
            } elseif ($rxGiftStatus === 'disabled' || $rxGiftStatus === 'no_amount') {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_section_disabled'] ?? "📛 این بخش درحال حاضر غیرفعال می باشد", $keyboard, 'HTML');
            } elseif ($rxGiftStatus === 'not_referred') {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_not_affiliate_of_anyone'] ?? "📛 شما زیرمجموعه هیچ کاربری نیستید.", $keyboard, 'HTML');
            } elseif ($rxGiftStatus === 'already') {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_gift_already_received'] ?? "<b>⛔ شما قبلاً هدیه عضویت را دریافت کرده‌اید.</b>\nاین هدیه فقط <b>یک‌بار</b> قابل فعال‌سازی است.", $keyboard, 'HTML');
            } elseif ($rxGiftStatus === 'phone_required') {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_phone_required'] ?? "📞 برای دریافت هدیه عضویت، ابتدا باید شماره موبایل خود را تایید کنید.", $keyboard, 'HTML');
            } elseif ($rxGiftStatus === 'too_new') {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_account_too_new'] ?? "⏳ حساب شما هنوز به اندازه کافی قدیمی نیست، کمی بعد دوباره تلاش کنید.", $keyboard, 'HTML');
            } elseif ($rxGiftStatus === 'cap_daily') {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_cap_reached'] ?? "📛 سقف روزانه هدیه معرفی برای این معرف پر شده است.", $keyboard, 'HTML');
            } elseif ($rxGiftStatus === 'cap_monthly') {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_cap_reached'] ?? "📛 سقف ماهانه هدیه معرفی برای این معرف پر شده است.", $keyboard, 'HTML');
            } else {
                sendmessage($from_id, $datatextbot['dyn_affiliates_extra_gift_failed'] ?? "⚠️ پرداخت هدیه عضویت در حال حاضر انجام نشد؛ لطفاً چند لحظه دیگر دوباره تلاش کنید.", $keyboard, 'HTML');
            }
            break;
        }

        return true;
    }
}
