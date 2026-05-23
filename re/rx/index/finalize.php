<?php

if (isset($update['pre_checkout_query'])) {
    $userid = $update['pre_checkout_query']['from']['id'];
    $id_order = $update['pre_checkout_query']['invoice_payload'];
    $Payment_report = select("Payment_report", "*", "id_order", $id_order, "select");
    if ($Payment_report == false) {
        return;
    } else {
        telegram('answerPreCheckoutQuery', [
            'pre_checkout_query_id' => $update['pre_checkout_query']['id'],
            'ok' => true,
        ]);
    }
    if ($Payment_report['payment_Status'] == "paid") {
        return;
    }
    update("Payment_report", "dec_not_confirmed", json_encode($update['pre_checkout_query']), "id_order", $Payment_report['id_order']);
    DirectPayment($Payment_report['id_order']);
    $pricecashback = select("PaySetting", "ValuePay", "NamePay", "chashbackstar", "select")['ValuePay'];
    $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
    if ($pricecashback != "0") {
        $result = ($Payment_report['price'] * $pricecashback) / 100;
        $Balance_confrim = intval($Balance_id['Balance']) + $result;
        update("user", "Balance", $Balance_confrim, "id", $Balance_id['id']);
        $text_report = sprintf($textbotlang['users']['Discount']['gift-deposit'], $result);
        sendmessage($Balance_id['id'], $text_report, null, 'HTML');
    }
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $paymentreports,
            'text' => sprintf($textbotlang['Admin']['reportgroup']['new-payment-star'], $Balance_id['username'], $Balance_id['id'], $Payment_report['price'], $update['pre_checkout_query']['total_amount']),
            'parse_mode' => "HTML"
        ]);
    }
    update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
} elseif (preg_match('/extends_(\w+)_(.*)/', $datain, $dataget)) {
    $username = $dataget[1];
    $invoiceIdForExtend = $dataget[2] ?? '';
    $nameloc = false;
    if ($invoiceIdForExtend !== '') {
        $nameloc = select("invoice", "*", "id_invoice", $invoiceIdForExtend, "select");
        if (is_array($nameloc) && (string)($nameloc['id_user'] ?? '') !== (string)$from_id) {
            if (function_exists('rx_log_event')) {
                rx_log_event('INVOICE_OWNERSHIP_DENIED', 'Handler extends on non-owned invoice', [
                    'from_id' => $from_id, 'invoice' => $invoiceIdForExtend, 'handler' => 'extends',
                ]);
            }
            $nameloc = false;
        }
    }
    if (!is_array($nameloc)) {
        $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
        $stmtInvoice->execute([':username' => $username, ':id_user' => $from_id]);
        $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }
    if (function_exists('nmStockPanelForInvoice') && is_array($nameloc)) {
        $marzban_list_get = nmStockPanelForInvoice($nameloc);
    } else {
        $marzban_list_get = false;
    }
    if (!is_array($marzban_list_get)) {
        $panelRef = $user['Processing_value_four'] ?? '';
        $marzban_list_get = select("marzban_panel", "*", "name_panel", $panelRef, "select");
        if (!is_array($marzban_list_get)) {
            $marzban_list_get = select("marzban_panel", "*", "code_panel", $panelRef, "select");
        }
    }
    if (!is_array($marzban_list_get)) {
        sendmessage($from_id, "❌ خطایی رخ داده است مراحل را از اول طی کنید", null, 'html');
        return;
    }
    $location = $marzban_list_get['name_panel'];
    update("user", "Processing_value", $location, "id", $from_id);
    update("user", "Processing_value_four", $marzban_list_get['code_panel'], "id", $from_id);
    if (is_array($nameloc) && !empty($nameloc['id_invoice'])) {
        update("user", "Processing_value_one", $nameloc['id_invoice'], "id", $from_id);
    }

    if (is_array($nameloc) && function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get) && function_exists('nmStockExtendProductKeyboard')) {
        $stockKeyboard = nmStockExtendProductKeyboard($nameloc, $user, $marzban_list_get);
        if (!$stockKeyboard) {
            Editmessagetext($from_id, $message_id, "❌ برای این سرویس موجودی انبار قابل تمدید وجود ندارد.", json_encode(['inline_keyboard' => [[['text' => $textbotlang['users']['stateus']['backlist'] ?? '🏠 بازگشت به لیست سرویس ها', 'callback_data' => 'backorder']]]], JSON_UNESCAPED_UNICODE), 'HTML');
            return;
        }
        Editmessagetext($from_id, $message_id, "📦 وضعیت نت ملی فعال است؛ محصول انباری تمدید را انتخاب کنید:", $stockKeyboard, 'HTML');
        return;
    }

    $query = "SELECT * FROM product WHERE (Location = :location OR Location = '/all') AND (agent = :agent OR agent = 'all' OR agent = '' OR agent IS NULL)";
    $queryParams = [
        ':location' => $location,
        ':agent' => $user['agent']
    ];
    $customVolumeData = json_decode($marzban_list_get['customvolume'] ?? '{}', true);
    if (!is_array($customVolumeData)) $customVolumeData = [];
    $statuscustomvolume = $customVolumeData[$user['agent']] ?? '0';
    if ($marzban_list_get['MethodUsername'] == $textbotlang['users']['customusername'] || $marzban_list_get['MethodUsername'] == "نام کاربری دلخواه + عدد رندوم") {
        $datakeyboard = "prodcutservicesom_";
    } else {
        $datakeyboard = "prodcutserviceom_";
    }
    $statuscustom = ($statuscustomvolume == "1" && $marzban_list_get['type'] != "Manualsale");
    Editmessagetext($from_id, $message_id, $textbotlang['users']['extend']['selectservice'], KeyboardProduct($marzban_list_get['name_panel'], $query, $user['pricediscount'], "serviceextendselects-", false, "backuser", $username, "customsellvolume", $user['agent'], $queryParams));
} elseif (preg_match('/^serviceextendselects-(.*)-(.*)/', $datain, $dataget)) {
    deletemessage($from_id, $message_id);
    $codeproduct = $dataget[1];
    $username = $dataget[2];
    if (function_exists('rxResolveProductForPanel')) {
        $prodcut = rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :processing_value OR Location = '/all') AND code_product = :code_product AND agent = :agent");
        $stmt->execute([
            ':processing_value' => $user['Processing_value'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent']
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($prodcut == false) {
        error_log('Service extend preview failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $textbotlang['users']['erroroccurred'], $keyboard, 'html');
        return;
    }
    $keyboardextend = json_encode([
        'inline_keyboard' => [
            [
                ['text' => $textbotlang['users']['extend']['confirm'], 'callback_data' => "confirmserivces-" . $codeproduct . "-" . $username],
            ]
        ]
    ]);
    sendmessage($from_id, sprintf($textbotlang['users']['extend']['renewalinvoice'], $username, $prodcut['name_product'], $prodcut['price_product'], $prodcut['Service_time'], $prodcut['Volume_constraint'], $prodcut['note'], $user['Balance']), $keyboardextend, 'html');
} elseif (preg_match('/^confirmserivces-(.*)-(.*)/', $datain, $dataget)) {
    $codeproduct = $dataget[1];
    $usernamePanelExtends = $dataget[2];
    deletemessage($from_id, $message_id);
    if (function_exists('rxResolveProductForPanel')) {
        $prodcut = rxResolveProductForPanel($codeproduct, $user['Processing_value'], $user['agent']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE (Location = :processing_value OR Location = '/all') AND code_product = :code_product AND agent = :agent");
        $stmt->execute([
            ':processing_value' => $user['Processing_value'],
            ':code_product' => $codeproduct,
            ':agent' => $user['agent']
        ]);
        $prodcut = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($prodcut == false) {
        error_log('Service extend confirmation failed: product not found for code=' . ($codeproduct ?? '') . ', panel=' . ($user['Processing_value'] ?? '') . ', agent=' . ($user['agent'] ?? ''));
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $user['Processing_value'], "select");
    if ($marzban_list_get == false) {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $nameloc = false;
    if (!empty($user['Processing_value_one'])) {
        $nameloc = select("invoice", "*", "id_invoice", $user['Processing_value_one'], "select");
    }
    if (!is_array($nameloc)) {
        $stmtInvoice = $pdo->prepare("SELECT * FROM invoice WHERE username = :username AND id_user = :id_user ORDER BY time_sell DESC LIMIT 1");
        $stmtInvoice->execute([':username' => $usernamePanelExtends, ':id_user' => $from_id]);
        $nameloc = $stmtInvoice->fetch(PDO::FETCH_ASSOC);
    }
    if ($user['Balance'] < $prodcut['price_product'] && $user['agent'] != "n2") {
        $marzbandirectpay = select('shopSetting', "*", "Namevalue", "statusdirectpabuy", "select")['value'];
        if ($marzbandirectpay == "offdirectbuy") {
            $minbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "minbalance", "select")['ValuePay'], true)[$user['agent']]);
            $maxbalance = number_format(json_decode(select("PaySetting", "*", "NamePay", "maxbalance", "select")['ValuePay'], true)[$user['agent']]);
            $bakinfos = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => $textbotlang['users']['stateus']['backinfo'], 'callback_data' => "account"],
                    ]
                ]
            ]);
            Editmessagetext($from_id, $message_id, sprintf($textbotlang['users']['Balance']['insufficientbalance'], $minbalance, $maxbalance), $bakinfos, 'HTML');
            step('getprice', $from_id);
            return;
        } else {
            if (intval($user['pricediscount']) != 0) {
                $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
                $prodcut['price_product'] = $prodcut['price_product'] - $result;
                sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
            }
            $Balance_prim = $prodcut['price_product'] - $user['Balance'];
            update("user", "Processing_value", $Balance_prim, "id", $from_id);
            sendmessage($from_id, $textbotlang['users']['sell']['None-credit'], $step_payment, 'HTML');
            step('get_step_payment', $from_id);
            return;
        }
    }
    if (intval($user['maxbuyagent']) != 0 and $user['agent'] == "n2") {
        if (($user['Balance'] - $prodcut['price_product']) < intval("-" . $user['maxbuyagent'])) {
            sendmessage($from_id, $textbotlang['users']['Balance']['maxpurchasereached'], null, 'HTML');
            return;
        }
    }
    if (intval($user['pricediscount']) != 0) {
        $result = ($prodcut['price_product'] * $user['pricediscount']) / 100;
        $prodcut['price_product'] = $prodcut['price_product'] - $result;
        sendmessage($from_id, sprintf($textbotlang['users']['Discount']['discountapplied'], $user['pricediscount']), null, 'HTML');
    }
    if (function_exists('nmPanelNationalEnabled') && nmPanelNationalEnabled($marzban_list_get)) {
        if (!is_array($nameloc)) {
            sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
            return;
        }
        $stockNew = function_exists('nmStockReserveForProduct') ? nmStockReserveForProduct($marzban_list_get, $prodcut, $from_id, $nameloc['id_invoice'], 'normal_extend_national_stock') : false;
        if (!$stockNew) {
            sendmessage($from_id, "❌ موجودی انبار برای این محصول تمام شده است. مبلغی کسر نشد.", $keyboard, 'HTML');
            return;
        }
        $__pp = (float)$prodcut['price_product'];
        $__allowNeg = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
        $__charge = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $__pp, $__allowNeg) : ['ok' => false, 'reason' => 'helper-missing'];
        if (empty($__charge['ok'])) {
            sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
            return;
        }
        $Balance_Low_user = $__charge['new_balance'];
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
        update("invoice", "user_info", $stockNew['content'], "id_invoice", $nameloc['id_invoice']);
        try { update("invoice", "source_panel_code", $marzban_list_get['code_panel'], "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
        $invoiceNew = array_merge($nameloc, [
            'name_product' => $prodcut['name_product'],
            'price_product' => $prodcut['price_product'],
            'Volume' => $prodcut['Volume_constraint'],
            'Service_time' => $prodcut['Service_time'],
            'time_sell' => time(),
            'user_info' => $stockNew['content'],
            'source_panel_code' => $marzban_list_get['code_panel'],
        ]);
        nmStockDeliverConfig($stockNew, $invoiceNew, '✅ تمدید سرویس از انبار شبکه‌ملی با موفقیت انجام شد');
        sendmessage($from_id, "✅ تمدید انباری انجام شد و موجودی انبار یک عدد کم شد.", $keyboard, 'HTML');
        return;
    }

    $DataUserOut = $ManagePanel->DataUser($marzban_list_get['name_panel'], $usernamePanelExtends);
    if ($DataUserOut['status'] == "Unsuccessful") {
        sendmessage($from_id, $textbotlang['users']['extend']['renewalerror'], $keyboard, 'HTML');
        return;
    }
    $__pp2 = (float)$prodcut['price_product'];
    $__allowNeg2 = ($user['agent'] === 'n2') ? (int)($user['maxbuyagent'] ?? 0) : 0;
    $__charge2 = function_exists('balance_atomic_charge') ? balance_atomic_charge($from_id, $__pp2, $__allowNeg2) : ['ok' => false, 'reason' => 'helper-missing'];
    if (empty($__charge2['ok'])) {
        sendmessage($from_id, "❌ موجودی کافی نیست (تلاش هم‌زمان شناسایی شد). یک بار دیگر تلاش کنید.", $keyboard, 'HTML');
        return;
    }
    $Balance_Low_user = $__charge2['new_balance'];
    $extend = $ManagePanel->extend($marzban_list_get['Methodextend'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $usernamePanelExtends, $prodcut['code_product'], $marzban_list_get['code_panel']);
    if (empty($extend['status']) && function_exists('balance_atomic_credit')) {
        balance_atomic_credit($from_id, $__pp2);
        $Balance_Low_user = $user['Balance'];
    }
    if ($extend['status'] == false) {
        $fallbackInvoice = is_array($nameloc) ? $nameloc : ['id_invoice' => '', 'id_user' => $from_id, 'username' => $usernamePanelExtends, 'Service_location' => $marzban_list_get['name_panel'], 'name_product' => $prodcut['name_product'], 'Volume' => $prodcut['Volume_constraint'], 'Service_time' => $prodcut['Service_time']];
        if (nmStockCompleteExtendFallback($from_id, $user, array_merge($fallbackInvoice, ['username' => $usernamePanelExtends]), $prodcut, 0, 'paid_extend_panel_fallback')) {
            return;
        }
        $extend['msg'] = json_encode($extend['msg']);
        $textreports = "خطای تمدید سرویس
        نام پنل : {$marzban_list_get['name_panel']}
        نام کاربری سرویس : $usernamePanelExtends
        دلیل خطا : {$extend['msg']}";
        sendmessage($from_id, "❌خطایی در تمدید سرویس رخ داده با پشتیبانی در ارتباط باشید", null, 'HTML');
        if (strlen($setting['Channel_Report'] ?? '') > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $errorreport,
                'text' => $textreports,
                'parse_mode' => "HTML"
            ]);
        }
        return;
    }
    if (is_array($nameloc)) {
        update("invoice", "name_product", $prodcut['name_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "price_product", $prodcut['price_product'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Volume", $prodcut['Volume_constraint'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Service_time", $prodcut['Service_time'], "id_invoice", $nameloc['id_invoice']);
        update("invoice", "Status", "active", "id_invoice", $nameloc['id_invoice']);
        update("invoice", "time_sell", time(), "id_invoice", $nameloc['id_invoice']);
        try { update("invoice", "user_info", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
        try { update("invoice", "source_panel_code", "", "id_invoice", $nameloc['id_invoice']); } catch (Throwable $e) {}
    }
    $stmt = $pdo->prepare("INSERT IGNORE INTO service_other (id_user, username, value, type, time, price,output) VALUES (:id_user, :username, :value, :type, :time, :price,:output)");
    $value = json_encode(array(
        "volumebuy" => $prodcut['Volume_constraint'],
        "Service_time" => $prodcut['Service_time'],
        "oldvolume" => $DataUserOut['data_limit'],
        "oldtime" => $DataUserOut['expire'],
        'code_product' => $prodcut['code_product'],
    ));
    $dateacc = date('Y/m/d H:i:s');
    $type = "extends_not_user";
    $stmt->execute([
        ':id_user' => $from_id,
        ':username' => $usernamePanelExtends,
        ':value' => $value,
        ':type' => $type,
        ':time' => $dateacc,
        ':price' => $prodcut['price_product'],
        ':output' => json_encode($extend)
    ]);
    $prodcut['price_product'] = number_format($prodcut['price_product']);
    $balanceformatsell = number_format(select("user", "Balance", "id", $from_id, "select")['Balance'], 0);
    $textextend = "✅ تمدید برای سرویس شما با موفقیت صورت گرفت

▫️نام سرویس : $usernamePanelExtends
▫️نام محصول : {$prodcut['name_product']}
▫️مبلغ تمدید {$prodcut['price_product']} تومان
";
    sendmessage($from_id, $textextend, $keyboard, 'HTML');
    $timejalali = jdate('Y/m/d H:i:s');
    $text_report = sprintf($textbotlang['Admin']['reportgroup']['renewaldetails'], $from_id, $username, $usernamePanelExtends, $first_name, $marzban_list_get['name_panel'], $prodcut['name_product'], $prodcut['Volume_constraint'], $prodcut['Service_time'], $prodcut['price_product'], $balanceformatsell, $timejalali);
    if (strlen($setting['Channel_Report'] ?? '') > 0) {
        telegram('sendmessage', [
            'chat_id' => $setting['Channel_Report'],
            'message_thread_id' => $otherservice,
            'text' => $text_report,
            'parse_mode' => "HTML"
        ]);
    }
}
if (in_array($from_id, $admin_ids))
    require_once 'admin.php';

$pdo = null;
$connect->close();