<?php

namespace Faoxima\Admin\Telegram\Handlers;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $adminAction, $adminExecution, $backadmin, $callback_query_id, $change_product, $cmDel, $connect, $dataget, $from_id, $json_list_marzban_panel, $list_marzban_panel_edit_product, $message_id, $name_product, $paymentreports, $pdo, $setting, $shopkeyboard, $text, $textbotlang, $update, $user, $username.
 * Inherited or exported by reference: $Response, $id_product, $info_product, $json_list_product_list_admin, $location, $panel, $product, $row, $stmt.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ManualInventoryAndProducts
{
    public static function handle(
        &$Response,
        $adminAction,
        $adminExecution,
        $backadmin,
        $callback_query_id,
        $change_product,
        $cmDel,
        $connect,
        $dataget,
        $from_id,
        &$id_product,
        &$info_product,
        $json_list_marzban_panel,
        &$json_list_product_list_admin,
        $list_marzban_panel_edit_product,
        &$location,
        $message_id,
        $name_product,
        &$panel,
        $paymentreports,
        $pdo,
        &$product,
        &$row,
        $setting,
        $shopkeyboard,
        &$stmt,
        $text,
        $textbotlang,
        $update,
        $user,
        $username
    ) {
        if (!isset($adminExecution) || !$adminExecution instanceof \Faoxima\Admin\Security\TelegramAccess) {
            return;
        }

        switch ($adminAction) {
        case 1:
            $cmdOrderId = $cmDel[1];
            $cmdRow = \Faoxima\Core\Database\Db::select("Payment_report", "*", "id_order", $cmdOrderId, "select");
            if (!$cmdRow) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'تراکنش یافت نشد یا قبلاً حذف شده',
                    'show_alert' => true,
                    'cache_time' => 5,
                ]);
                return;
            }
            if (($cmdRow['payment_Status'] ?? '') === 'paid') {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'این تراکنش قبلاً تایید شده — قابل حذف نیست.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                return;
            }
            $cmdUserId = (string) ($cmdRow['id_user'] ?? '');
            try {
                $cmdDel = $pdo->prepare("DELETE FROM Payment_report WHERE id_order = :o");
                $cmdDel->execute([':o' => $cmdOrderId]);
            } catch (Throwable $e) {
                telegram('answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                    'text' => 'خطا در حذف. لاگ را بررسی کنید.',
                    'show_alert' => true,
                    'cache_time' => 0,
                ]);
                error_log('[cmdelete] failed: ' . $e->getMessage());
                return;
            }
            if ($cmdUserId !== '' && function_exists('sendmessage')) {
                @sendmessage(
                    $cmdUserId,
                    "🗑️ <b>درخواست بررسی دستی شما لغو و حذف شد</b>\n\n"
                    . "🛒 کد فاکتور: <code>" . htmlspecialchars($cmdOrderId) . "</code>\n\n"
                    . "اگر سوال دارید، با پشتیبانی در ارتباط باشید.",
                    null,
                    'HTML'
                );
            }
            telegram('answerCallbackQuery', [
                'callback_query_id' => $callback_query_id,
                'text' => '✅ حذف شد',
                'cache_time' => 0,
            ]);
            if (!empty($message_id) && function_exists('Editmessagetext')) {
                $cmdOriginal = (string) ($update['callback_query']['message']['text'] ?? '');
                $cmdDoneNote = "\n\n━━━━━━━━━━━━\n🗑️ <b>لغو و حذف شده</b>\n"
                    . "👨‍💼 توسط ادمین: <code>" . htmlspecialchars((string) $from_id) . "</code>\n"
                    . "⏰ " . date('Y/m/d H:i:s');
                @Editmessagetext($from_id, $message_id, $cmdOriginal . $cmdDoneNote, null);
            }
            if (strlen($setting['Channel_Report'] ?? '') > 0 && (string) $setting['Channel_Report'] !== (string) $from_id) {
                @telegram('sendmessage', [
                    'chat_id' => $setting['Channel_Report'],
                    'message_thread_id' => $paymentreports,
                    'text' => "🗑️ <b>بررسی دستی پرداخت کریپتو لغو و حذف شد</b>\n\n"
                        . "🛒 کد پیگیری: <code>" . htmlspecialchars($cmdOrderId) . "</code>\n"
                        . "👤 کاربر: <code>" . htmlspecialchars($cmdUserId) . "</code>\n"
                        . "👨‍💼 ادمین: <code>{$from_id}</code> (@{$username})",
                    'parse_mode' => 'HTML',
                ]);
            }
            break;
        case 2:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $json_list_marzban_panel, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('selectloc', $from_id);
            break;
        case 3:
            $panelResolved = method_exists(\Faoxima\Telegram\Support\AdminInput::class, 'rx_resolvePanelFromInput') ? \Faoxima\Telegram\Support\AdminInput::rx_resolvePanelFromInput($text, $pdo) : null;
            $locValue = is_array($panelResolved) && !empty($panelResolved['name_panel']) ? $panelResolved['name_panel'] : $text;
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $locValue, "id", $from_id);
            \Faoxima\Telegram\Navigation\Steps::step('remove-product', $from_id);
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['selectRemoveProduct'], $json_list_product_list_admin, 'HTML');
            break;
        case 4:
            if (!in_array($text, $name_product)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['users']['sell']['error-product'], null, 'HTML');
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM product WHERE name_product =:name_product AND (FIND_IN_SET(:Location, Location) > 0 or Location= '/all')");
            $stmt->bindParam(':name_product', $text, PDO::PARAM_STR);
            $stmt->bindParam(':Location', $user['Processing_value'], PDO::PARAM_STR);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['RemoveedProduct'], $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 5:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['Rmove_location'], $list_marzban_panel_edit_product, 'HTML');
            break;
        case 6:
            $location = $dataget[1];
            $location = $location == "all" ? "/all" : $location;
            \Faoxima\Core\Database\Db::update("user", "Processing_value_one", $location, "id", $from_id);
            $Response = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "کاربر عادی", 'callback_data' => 'typeagenteditproduct_f'],
                    ],
                    [
                        ['text' => "نماینده پیشرفته", 'callback_data' => 'typeagenteditproduct_n2'],
                        ['text' => "نماینده عادی", 'callback_data' => 'typeagenteditproduct_n'],
                    ],
                    [
                        ['text' => "بازگشت", 'callback_data' => "admin"]
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, "📌 نوع کاربری را انتخاب کنید", $Response);
            break;
        case 7:
            $typeagent = $dataget[1];
            \Faoxima\Core\Database\Db::update("user", "Processing_value_tow", $typeagent, "id", $from_id);
            $product = [];
            $escapedText = mysqli_real_escape_string($connect, $user['Processing_value_one']);
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $_loc = $panel['name_panel'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE (FIND_IN_SET(?, Location) > 0 OR Location = '/all') AND agent = ?");
            $_stmt->bind_param("ss", $_loc, $typeagent);
            $_stmt->execute();
            $getdataproduct = $_stmt->get_result();
            $_stmt->close();
            $list_product = [
                'inline_keyboard' => [],
            ];
            if (isset($getdataproduct)) {
                while ($row = mysqli_fetch_assoc($getdataproduct)) {
                    $list_product['inline_keyboard'][] = [
                        ['text' => $row['name_product'], 'callback_data' => "productedit_" . $row['id']]
                    ];
                }
                $list_product['inline_keyboard'][] = [
                    ['text' => "🏠 بازگشت به منوی قبل", 'callback_data' => "locationedit_" . $user['Processing_value_one']],
                ];

                $json_list_product_list_admin = json_encode($list_product);
            }
            Editmessagetext($from_id, $message_id, $textbotlang['Admin']['Product']['selectEditProduct'], $json_list_product_list_admin);
            break;
        case 8:
            $id_product = $dataget[1];
            deletemessage($from_id, $message_id);
            \Faoxima\Core\Database\Db::update("user", "Processing_value", $id_product, "id", $from_id);
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $_loc2 = $panel['name_panel']; $_pv2 = $user['Processing_value_tow'];
            $_stmt = $connect->prepare("SELECT * FROM product WHERE id = ? AND agent = ? AND (FIND_IN_SET(?, Location) > 0 OR Location = '/all') LIMIT 1");
            $_stmt->bind_param("sss", $id_product, $_pv2, $_loc2);
            $_stmt->execute();
            $info_product = $_stmt->get_result()->fetch_assoc();
            $_stmt->close();
            $count_invoice = \Faoxima\Core\Database\Db::select("invoice", "*", "name_product", $info_product['name_product'], "count");
            $infoproduct = "
📌 اطلاعات محصول در حال ویرایش:
نام محصول :  {$info_product['name_product']}
قیمت محصول : " . \Faoxima\Core\Support\Formatting::rxFormatToman($info_product['price_product']) . "
حجم محصول : {$info_product['Volume_constraint']}
موقعیت محصول : {$info_product['Location']}
زمان محصول : {$info_product['Service_time']}
نوع کاربری محصول : {$info_product['agent']}
ریست دوره ای حجم محصول : {$info_product['data_limit_reset']}
یادداشت محصول : {$info_product['note']}
دسته بندی محصول : {$info_product['category']}
تعداد محصول فروخته شده : $count_invoice عدد
    ";
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $infoproduct, $change_product, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('product_editor', $from_id);
            break;
        case 9:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "قیمت جدید را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('change_price', $from_id);
            break;
        case 10:
            if (!isset($update['message']) && empty($text)) { return; }
            if (!ctype_digit($text)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, $textbotlang['Admin']['Product']['InvalidPrice'], $backadmin, 'HTML');
                return;
            }
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("UPDATE product SET price_product = :price_product WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':price_product', $text);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ قیمت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 11:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "یادداشت جدید را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('change_note', $from_id);
            break;
        case 12:
            if (!isset($update['message']) && empty($text)) { return; }
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("UPDATE product SET note = :notes WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':notes', $text);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ یادداشت محصول بروزرسانی شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 13:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "نام دسته بندی جدید را انتخاب کنید", \Faoxima\Telegram\Keyboard\AdminCategoryFunctions::KeyboardCategoryadmin(), 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('change_categroy', $from_id);
            break;
        case 14:
            if (!isset($update['message']) && empty($text)) { return; }
            $category = \Faoxima\Core\Database\Db::select("category", "*", "remark", $text, "count");
            if ($category == 0) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ دسته بندی انتخاب شده وجود ندارد از بخش پلن ها > اضافه کردن دسته بندی ُ دسته بندی خود را اضافه کنید سپس محصول را اضافه نمایید.", \Faoxima\Telegram\Keyboard\AdminCategoryFunctions::KeyboardCategoryadmin(), 'HTML');
                return;
            }
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("UPDATE product SET category = :categroy WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':categroy', $text);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅ دسته بندی محصول بروزرسانی شد", $shopkeyboard, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        case 15:
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "نام جدید را ارسال کنید", $backadmin, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('change_name', $from_id);
            break;
        case 16:
            if (!isset($update['message']) && empty($text)) { return; }
            if (strlen($text) > 150) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ نام محصول باید کمتر از 150 کاراکتر باشد", $backadmin, 'HTML');
                return;
            }
            if (in_array($text, $name_product)) {
                \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "❌ محصول با نام $text وجود دارد", $backadmin, 'HTML');
                return;
            }
            $panel = \Faoxima\Core\Database\Db::select("marzban_panel", "*", "code_panel", $user['Processing_value_one'], "select");
            $stmt = $pdo->prepare("UPDATE product SET name_product = :name_products WHERE id = :name_product AND (FIND_IN_SET(:Location, Location) > 0 OR Location = '/all') AND agent = :agent");
            $stmt->bindParam(':name_products', $text);
            $stmt->bindParam(':name_product', $user['Processing_value']);
            $stmt->bindParam(':Location', $panel['name_panel']);
            $stmt->bindParam(':agent', $user['Processing_value_tow']);
            $stmt->execute();
            \Faoxima\Telegram\Support\Replies::nm_adminInstantReply($from_id, "✅نام محصول بروزرسانی شد", $change_product, 'HTML');
            \Faoxima\Telegram\Navigation\Steps::step('home', $from_id);
            break;
        }

        return true;
    }
}
