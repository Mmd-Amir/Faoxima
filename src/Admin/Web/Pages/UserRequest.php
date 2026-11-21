<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $adminnumber, $pdo.
 * Inherited or exported by reference: $_csrf, $act, $agent_display, $number_display, $otherservice, $paymentreports, $query, $section, $setting, $st, $status, $status_class, $status_label, $user, $userCodes, $userGiftCodes, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class UserRequest
{
    public static function handle(
        &$_csrf,
        &$act,
        $adminnumber,
        &$agent_display,
        &$number_display,
        &$otherservice,
        &$paymentreports,
        $pdo,
        &$query,
        &$section,
        &$setting,
        &$st,
        &$status,
        &$status_class,
        &$status_label,
        &$user,
        &$userCodes,
        &$userGiftCodes,
        &$value
    ) {
        \Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo);
        \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();

        $query = $pdo->prepare("SELECT * FROM user WHERE id=:id");
        $query->bindParam("id", $_GET["id"], PDO::PARAM_STR);
        $query->execute();
        $user = $query->fetch(PDO::FETCH_ASSOC);

        $setting        = \Faoxima\Core\Database\Db::select("setting", "*", null, null);
        $otherservice   = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "otherservice", "select")['idreport'];
        $paymentreports = \Faoxima\Core\Database\Db::select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];


        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
            $targetUid = (string)($_GET['id'] ?? '');
            $act = (string)$_POST['_action'];
            if (in_array($act, ['user_status', 'user_balance_add', 'user_balance_lower', 'user_agent'], true)) {
                \Faoxima\Admin\Web\Support\Csrf::fx_admin_require_access($pdo, ['administrator'], $adminnumber ?? null);
            }
            if ($act === 'user_status' && $targetUid !== '') {
                $status = (string)($_POST['status'] ?? '');
                if (in_array($status, ['block', 'active'], true)) {
                    if ($status === 'block') {
                        $textblok = "کاربر با آیدی عددی {$_GET['id']} در ربات مسدود گردید \n\n<blockquote>ادمین مسدود کننده : پنل تحت وب</blockquote>\n<blockquote>نام کاربری : {$_SESSION['user']}</blockquote>";
                        if (strlen($setting['Channel_Report']) > 0) {
                            telegram('sendmessage', [
                                'chat_id'           => $setting['Channel_Report'],
                                'message_thread_id' => $otherservice,
                                'text'              => $textblok,
                                'parse_mode'        => "HTML"
                            ]);
                        }
                    } else {
                        sendmessage($_GET['id'], \Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_user_unblocked_notice', "✳️ حساب کاربری شما از مسدودی خارج شد ✳️\nاکنون میتوانید از ربات استفاده کنید "), null, 'HTML');
                    }
                    \Faoxima\Admin\Users\UserStatus::change($targetUid, $status, 'update');
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_balance_add' && $targetUid !== '') {
                $amount = (int)($_POST['priceadd'] ?? 0);
                $priceadd = number_format($amount, 0);
                if ($amount > 0 && \Faoxima\Wallet\Balance\Balances::credit($pdo, $targetUid, $amount)) {
                    if (is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                        \Faoxima\Wallet\Ledger\Ledger::record($pdo, $targetUid, 'credit', $amount, 'admin_credit', 'افزایش موجودی از پنل تحت وب');
                    }
                    $textadd  = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_user_balance_added_tpl', '💎 کاربر عزیز مبلغ {amount} T به موجودی کیف پول تان اضافه گردید.'), ['amount' => $priceadd]);
                    sendmessage($targetUid, $textadd, null, 'HTML');
                    if (strlen($setting['Channel_Report']) > 0) {
                        $textaddbalance = "📌 یک ادمین موجودی کاربر را از پنل تحت وب افزایش داده است :\n\n<blockquote>🪪 ادمین : {$_SESSION['user']}</blockquote>\n<blockquote>👤 کاربر : {$targetUid}</blockquote>\n<blockquote>مبلغ : $priceadd</blockquote>";
                        telegram('sendmessage', [
                            'chat_id'           => $setting['Channel_Report'],
                            'message_thread_id' => $paymentreports,
                            'text'              => $textaddbalance,
                            'parse_mode'        => "HTML"
                        ]);
                    }
                } else {
                    error_log('[panel_add_balance] credit failed for user ' . $targetUid . ' amount ' . $amount);
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_balance_lower' && $targetUid !== '') {
                $amount = (int)($_POST['pricelow'] ?? 0);
                $pricelow = number_format($amount, 0);
                $__adminDebitOk = $amount > 0 ? \Faoxima\Wallet\Balance\Balances::charge($pdo, $targetUid, $amount, 0) : ['ok' => false];
                $__adminDebitOk = !empty($__adminDebitOk['ok']);
                if ($__adminDebitOk && is_callable([\Faoxima\Wallet\Ledger\Ledger::class, 'record'])) {
                    \Faoxima\Wallet\Ledger\Ledger::record($pdo, $targetUid, 'debit', $amount, 'admin_debit', 'کاهش موجودی از پنل تحت وب');
                }
                if ($__adminDebitOk && strlen($setting['Channel_Report']) > 0) {
                    $textlowbalance = "📌 یک ادمین موجودی کاربر را از پنل تحت وب کسر کرده است :\n\n<blockquote>🪪 ادمین : {$_SESSION['user']}</blockquote>\n<blockquote>👤 کاربر : {$targetUid}</blockquote>\n<blockquote>مبلغ کسر شده : $pricelow</blockquote>";
                    telegram('sendmessage', [
                        'chat_id'           => $setting['Channel_Report'],
                        'message_thread_id' => $paymentreports,
                        'text'              => $textlowbalance,
                        'parse_mode'        => "HTML"
                    ]);
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_agent' && $targetUid !== '') {
                $agent = (string)($_POST['agent'] ?? '');
                if (in_array($agent, ['f', 'n', 'n2'], true)) {
                    \Faoxima\Core\Database\Db::update("user", "agent", $agent, "id", $targetUid);
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_message' && $targetUid !== '') {
                $message = trim((string)($_POST['textmessage'] ?? ''));
                if ($message !== '') {
                    $messagetext = \Faoxima\Telegram\Support\Texts::faoxima_render_text(\Faoxima\Telegram\Support\Texts::faoxima_textbot_get('dyn_panel_user_admin_message_tpl', "📥 یک پیام از مدیریت برای شما ارسال شد.\n\nمتن پیام : {message}"), ['message' => $message]);
                    sendmessage($targetUid, $messagetext, null, 'HTML');
                    if (strlen($setting['Channel_Report']) > 0) {
                        $textmsg = "📌 پیام مدیریت ارسال شد\n\n<blockquote>🪪 ادمین : {$_SESSION['user']}</blockquote>\n<blockquote>👤 گیرنده : {$targetUid}</blockquote>\n<blockquote>متن : {$message}</blockquote>";
                        telegram('sendmessage', [
                            'chat_id'           => $setting['Channel_Report'],
                            'message_thread_id' => $otherservice,
                            'text'              => $textmsg,
                            'parse_mode'        => "HTML"
                        ]);
                    }
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_discount_add' && $targetUid !== '') {
                $code    = trim((string)($_POST['codeDiscount'] ?? ''));
                $vtype   = (string)($_POST['value_type'] ?? 'percent');
                $section = (string)($_POST['section'] ?? 'all');
                $value   = (string)($_POST['price'] ?? '0');
                $limit   = (int)($_POST['limitDiscount'] ?? 0);
                if (preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code)
                    && in_array($vtype, ['percent', 'amount', 'free'], true)
                    && in_array($section, ['all', 'buy', 'extend', 'volume', 'time', 'charge'], true)) {
                    if ($vtype === 'free') $value = '0';
                    try {
                        $dup = $pdo->prepare("SELECT 1 FROM DiscountSell WHERE codeDiscount = :c LIMIT 1");
                        $dup->execute([':c' => $code]);
                        if (!$dup->fetchColumn()) {
                            $st = $pdo->prepare(
                                "INSERT INTO DiscountSell
                         (codeDiscount, price, limitDiscount, agent, usefirst, useuser, code_product, code_panel, time, type, usedDiscount, section, value_type, target_user, status)
                         VALUES (:c, :p, :l, 'allusers', '0', '0', 'all', '/all', '0', :sec, '0', :sec2, :vt, :tg, 'active')"
                            );
                            $st->execute([':c' => $code, ':p' => $value, ':l' => (string)$limit, ':sec' => $section, ':sec2' => $section, ':vt' => $vtype, ':tg' => $targetUid]);
                        }
                    } catch (\Throwable $e) {  }
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_discount_edit' && $targetUid !== '') {
                $id      = (int)($_POST['id'] ?? 0);
                $code    = trim((string)($_POST['codeDiscount'] ?? ''));
                $vtype   = (string)($_POST['value_type'] ?? 'percent');
                $section = (string)($_POST['section'] ?? 'all');
                $value   = (string)($_POST['price'] ?? '0');
                $limit   = (int)($_POST['limitDiscount'] ?? 0);
                if ($id > 0
                    && preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code)
                    && in_array($vtype, ['percent', 'amount', 'free'], true)
                    && in_array($section, ['all', 'buy', 'extend', 'volume', 'time', 'charge'], true)) {
                    if ($vtype === 'free') $value = '0';
                    try {
                        $dup = $pdo->prepare("SELECT 1 FROM DiscountSell WHERE codeDiscount = :c AND id <> :id LIMIT 1");
                        $dup->execute([':c' => $code, ':id' => $id]);
                        if (!$dup->fetchColumn()) {
                            $st = $pdo->prepare(
                                "UPDATE DiscountSell
                            SET codeDiscount = :c, price = :p, limitDiscount = :l,
                                section = :sec, type = :sec2, value_type = :vt
                          WHERE id = :id AND target_user = :tg"
                            );
                            $st->execute([':c' => $code, ':p' => $value, ':l' => (string)$limit, ':sec' => $section, ':sec2' => $section, ':vt' => $vtype, ':id' => $id, ':tg' => $targetUid]);
                        }
                    } catch (\Throwable $e) {  }
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_gift_add' && $targetUid !== '') {
                $code  = trim((string)($_POST['code'] ?? ''));
                $price = (int)($_POST['gift_price'] ?? 0);
                $limit = (int)($_POST['gift_limit'] ?? 0);
                if (preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code) && $price > 0) {
                    try {
                        $dup = $pdo->prepare("SELECT 1 FROM Discount WHERE code = :c LIMIT 1");
                        $dup->execute([':c' => $code]);
                        if (!$dup->fetchColumn()) {
                            $st = $pdo->prepare(
                                "INSERT INTO Discount (code, price, limituse, limitused, target_user, status)
                         VALUES (:c, :p, :l, '0', :tg, 'active')"
                            );
                            $st->execute([':c' => $code, ':p' => (string)$price, ':l' => (string)$limit, ':tg' => $targetUid]);
                        }
                    } catch (\Throwable $e) {  }
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_gift_edit' && $targetUid !== '') {
                $id    = (int)($_POST['id'] ?? 0);
                $code  = trim((string)($_POST['code'] ?? ''));
                $price = (int)($_POST['gift_price'] ?? 0);
                $limit = (int)($_POST['gift_limit'] ?? 0);
                if ($id > 0 && preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code) && $price > 0) {
                    try {
                        $dup = $pdo->prepare("SELECT 1 FROM Discount WHERE code = :c AND id <> :id LIMIT 1");
                        $dup->execute([':c' => $code, ':id' => $id]);
                        if (!$dup->fetchColumn()) {
                            $st = $pdo->prepare(
                                "UPDATE Discount SET code = :c, price = :p, limituse = :l WHERE id = :id AND target_user = :tg"
                            );
                            $st->execute([':c' => $code, ':p' => (string)$price, ':l' => (string)$limit, ':id' => $id, ':tg' => $targetUid]);
                        }
                    } catch (\Throwable $e) {  }
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_code_delete' && $targetUid !== '') {
                $id   = (int)($_POST['id'] ?? 0);
                $kind = (string)($_POST['kind'] ?? '');
                if ($id > 0) {
                    try {
                        if ($kind === 'gift') {
                            $pdo->prepare("DELETE FROM Discount WHERE id = :id AND target_user = :u")->execute([':id' => $id, ':u' => $targetUid]);
                        } else {
                            $pdo->prepare("DELETE FROM DiscountSell WHERE id = :id AND target_user = :u")->execute([':id' => $id, ':u' => $targetUid]);
                        }
                    } catch (\Throwable $e) {  }
                }
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
            if ($act === 'user_set_discount' && $targetUid !== '') {
                $pd = (int)($_POST['pricediscount'] ?? 0);
                if ($pd < 0)   $pd = 0;
                if ($pd > 100) $pd = 100;
                \Faoxima\Core\Database\Db::update("user", "pricediscount", $pd, "id", $targetUid);
                header("Location: user.php?id=" . urlencode($targetUid));
                exit;
            }
        }

        $userCodes = [];
        $userGiftCodes = [];
        try {
            $st = $pdo->prepare("SELECT * FROM DiscountSell WHERE target_user = :u ORDER BY id DESC");
            $st->execute([':u' => (string)($_GET['id'] ?? '')]);
            $userCodes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {  }
        try {
            $st = $pdo->prepare("SELECT * FROM Discount WHERE target_user = :u ORDER BY id DESC");
            $st->execute([':u' => (string)($_GET['id'] ?? '')]);
            $userGiftCodes = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {  }


        $status_label   = ($user['User_Status'] == 'block') ? 'مسدود' : 'فعال';
        $status_class   = ($user['User_Status'] == 'block') ? 'badge-danger' : 'badge-success';
        $number_display = ($user['number'] == "none") ? 'ثبت نشده' : htmlspecialchars($user['number'], ENT_QUOTES, 'UTF-8');

        $_csrf = \Faoxima\Admin\Web\Support\Csrf::fx_csrf_token();

        $agent_types = [
            'f'  => 'کاربر عادی',
            'n'  => 'نماینده معمولی',
            'n2' => 'نماینده پیشرفته'
        ];
        $agent_display = isset($agent_types[$user['agent']]) ? $agent_types[$user['agent']] : 'نامشخص';

        return true;
    }
}
