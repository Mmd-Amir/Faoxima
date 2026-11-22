<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $action, $addedKey, $categories, $cur, $d, $delKey, $edgeRejectedKeys, $flash, $g, $items, $key, $orderedGrouped, $payload, $query, $r, $richRejectedKeys, $richStates, $richTemplateErrors, $row, $rows, $savedNum, $tempStatus, $tonpayTemplateFlash, $value, $values, $visibleRows.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class TextbotRequest
{
    public static function handle(
        &$action,
        &$addedKey,
        &$categories,
        &$cur,
        &$d,
        &$delKey,
        &$edgeRejectedKeys,
        &$flash,
        &$g,
        &$items,
        &$key,
        &$orderedGrouped,
        &$payload,
        $pdo,
        &$query,
        &$r,
        &$richRejectedKeys,
        &$richStates,
        &$richTemplateErrors,
        &$row,
        &$rows,
        &$savedNum,
        &$tonpayTemplateFlash,
        &$tempStatus,
        &$value,
        &$values,
        &$visibleRows
    ) {
        $textDefaults = ['text_custom_price_summary' => \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text_default()];
        $textDefaults += array_intersect_key(\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateDefaults(), array_flip(['dyn_atlaspay_bot_receipt_button', 'dyn_atlaspay_bot_receipt_instructions']));
        $textDefaults += \Faoxima\Telegram\Support\MessageTemplates::defaults();

        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
        $query->execute();
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!isset($_SESSION["user"]) || !$adminRow) {
            header('Location: login.php');
            exit;
        }

        \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();

        $adminName = (string) ($adminRow['username'] ?? $_SESSION['user']);
        \Faoxima\Core\Database\TextbotTemporary::ensureSchema($pdo);
        \Faoxima\Core\Database\TextbotTemporary::enforceExpiry($pdo, true);
        if (\Faoxima\Admin\Web\Pages\TextbotTemporaryRequest::isTemporaryRequest()) {
            \Faoxima\Admin\Web\Pages\TextbotTemporaryRequest::handle($pdo, $adminName, $textDefaults);
        }












        $flash = ['ok' => '', 'err' => ''];


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['_action'] ?? '';

            if (in_array($action, ['add', 'delete'], true) && \Faoxima\Core\Database\TextbotTemporary::activeSession($pdo) !== null) {
                $flash['err'] = 'تا پایان یا ثبت نشست ذخیره موقت، افزودن و حذف کلید امکان‌پذیر نیست.';
                $action = '';
            }

            if ($action === 'save') {
                $payload = \Faoxima\Admin\Web\Support\TextbotSavePlanner::parsePayload($_POST['_text_payload'] ?? null);
                if ($payload === null) {
                    $_SESSION['textbot_save_error'] = 'داده‌های فرم کامل یا معتبر نیستند؛ تغییری ذخیره نشد. صفحه را تازه کنید و دوباره ذخیره کنید.';
                    header('Location: textbot.php');
                    exit;
                }
                $activeSession = \Faoxima\Core\Database\TextbotTemporary::activeSession($pdo);
                if ($activeSession !== null && (string) $activeSession['admin'] !== $adminName) {
                    $_SESSION['textbot_save_error'] = 'یک نشست ذخیره موقت متعلق به مدیر دیگری فعال است؛ تا پایان آن امکان ذخیره وجود ندارد.';
                    header('Location: textbot.php');
                    exit;
                }
                $cur = \Faoxima\Admin\Web\Support\TextbotSavePlanner::currentRows($pdo, $textDefaults);
                $plan = \Faoxima\Admin\Web\Support\TextbotSavePlanner::plan($payload, $cur, $pdo);
                $edgeRejected = $plan['edgeRejected'];
                $tonpayTemplateErrors = $plan['tonpayErrors'];
                $richRejected = $plan['richRejected'];
                $richErrors = $plan['richErrors'];
                foreach ($plan['conflicts'] as $conflictKey) {
                    $richRejected[] = $conflictKey;
                    $richErrors[$conflictKey] = 'این متن پس از بارگذاری صفحه توسط مدیر دیگری تغییر کرده است؛ صفحه را تازه کنید.';
                }
                $updated = 0;
                if ($activeSession !== null) {
                    $sessionRev = isset($_POST['_temp_rev']) && ctype_digit((string) $_POST['_temp_rev']) ? (int) $_POST['_temp_rev'] : -1;
                    $sessionRef = isset($_POST['_temp_session']) && ctype_digit((string) $_POST['_temp_session']) ? (int) $_POST['_temp_session'] : 0;
                    $committed = \Faoxima\Core\Database\TextbotTemporary::commit($pdo, $adminName, $sessionRef, $sessionRev, \Faoxima\Admin\Web\Support\TextbotSavePlanner::rowsOf($plan['units']));
                    if (empty($committed['ok'])) {
                        $_SESSION['textbot_save_error'] = (string) ($committed['message'] ?? 'ثبت دائمی انجام نشد.');
                        header('Location: textbot.php');
                        exit;
                    }
                    $updated = count($plan['units']);
                } else {
                    $applied = \Faoxima\Admin\Web\Support\TextbotSavePlanner::applyDirect($pdo, $plan['units']);
                    $updated = $applied['updated'];
                    foreach ($applied['errors'] as $failedKey => $failedMessage) {
                        $richErrors[$failedKey] = $failedMessage;
                        $richRejected[] = $failedKey;
                    }
                    if ($applied['detail'] !== '') {
                        $_SESSION['textbot_save_error'] = $applied['detail'];
                    }
                    if ($updated > 0) {
                        \Faoxima\Core\Database\TextbotTemporary::bustCaches();
                    }
                }
                $redirect = 'textbot.php?saved=' . $updated;
                if (!empty($tonpayTemplateErrors)) {
                    $_SESSION['tonpay_template_errors'] = $tonpayTemplateErrors;
                }
                if (!empty($edgeRejected)) {
                    $redirect .= '&edge_rejected=' . urlencode(implode(',', $edgeRejected));
                }
                if (!empty($richRejected)) {
                    $_SESSION['rich_template_errors'] = $richErrors;
                    $redirect .= '&rich_rejected=' . urlencode(implode(',', $richRejected));
                }
                header('Location: ' . $redirect);
                exit;
            }
            elseif ($action === 'add') {
                $key = trim((string)($_POST['new_key'] ?? ''));
                $val = (string)($_POST['new_val'] ?? '');


                $errors = [];
                if ($key === '') {
                    $errors[] = 'کلید نمی‌تواند خالی باشد.';
                } elseif (!preg_match('/^[A-Za-z0-9_ـ]{2,100}$/u', $key)) {
                    $errors[] = 'کلید فقط می‌تواند شامل حروف، عدد و آندرلاین باشد (۲ تا ۱۰۰ کاراکتر).';
                } elseif (mb_strlen($val) > 4000) {
                    $errors[] = 'متن بیش از حد طولانی است (حداکثر ۴۰۰۰ کاراکتر).';
                } elseif (\Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_edge_violation($key, $val) !== '') {
                    $errors[] = \Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_edge_error_message();
                } elseif (\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateValidate($key, $val) !== '') {
                    $errors[] = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateValidate($key, $val);
                }

                if (!empty($errors)) {
                    $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
                } else {
                    try {
                        $ins = $pdo->prepare("INSERT INTO textbot (id_text, text) VALUES (:k, :v)
                                      ON DUPLICATE KEY UPDATE text = VALUES(text)");
                        $ins->execute([':k' => $key, ':v' => $val]);
                        \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('textbot');
                        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                            \Faoxima\Core\Database\SelectCache::clearSelectCache('textbot');
                        }
                        header('Location: textbot.php?added=' . urlencode($key));
                        exit;
                    } catch (\Throwable $e) {
                        $flash['err'] = 'افزودن ناموفق: ' . $e->getMessage();
                    }
                }
            }
            elseif ($action === 'delete') {
                $key = (string)($_POST['key'] ?? '');
                if ($key !== '') {
                    try {
                        $d = $pdo->prepare("DELETE FROM textbot WHERE id_text = :k OR id_text = :rk");
                        $d->execute([':k' => $key, ':rk' => \Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_rich_prefix() . $key]);
                        \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('textbot');
                        if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                            \Faoxima\Core\Database\SelectCache::clearSelectCache('textbot');
                        }
                        header('Location: textbot.php?deleted=' . urlencode($key));
                        exit;
                    } catch (\Throwable $e) {
                        $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
                    }
                }
            }
        }


        $rows = [];
        try {
            $r = $pdo->query("SELECT id_text, text FROM textbot ORDER BY id_text ASC");
            if ($r) $rows = $r->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $flash['err'] = 'بارگذاری متن‌ها ناموفق: ' . $e->getMessage();
        }


        $presentIds = array_column($rows, 'id_text');
        foreach (\Faoxima\Core\Database\TextbotTemporary::deletedSnapshots($pdo) as $deletedId => $deletedText) {
            if (!in_array((string) $deletedId, $presentIds, true)) {
                $rows[] = ['id_text' => (string) $deletedId, 'text' => $deletedText];
            }
        }

        foreach ($textDefaults as $defaultKey => $defaultText) {
            if (!in_array($defaultKey, array_column($rows, 'id_text'), true)) {
                $rows[] = ['id_text' => $defaultKey, 'text' => $defaultText];
            }
        }
        $richStates = [];
        $visibleRows = [];
        $richPrefix = \Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_rich_prefix();
        foreach ($rows as $row) {
            $rowId = (string)$row['id_text'];
            if (strpos($rowId, $richPrefix) === 0) {
                $richStates[substr($rowId, strlen($richPrefix))] = (string)$row['text'];
                continue;
            }
            $visibleRows[] = $row;
        }

        $grouped = [];
        foreach ($visibleRows as $row) {
            $g = \Faoxima\Admin\Web\Pages\TextbotRequestHelpers::faoxima_text_group((string)$row['id_text']);
            if (!isset($grouped[$g])) $grouped[$g] = [];
            $grouped[$g][] = $row;
        }

        $categories = \Faoxima\Admin\Web\Pages\TextbotRequestHelpers::faoxima_text_categories();
        $tabOrder = ['sys', 'srv', 'help', 'fin', 'custom_pricing', 'ref', 'err_general', 'err_purchase', 'tickets', 'wallet_ext', 'tonpay', 'msg_pay_card', 'msg_pay_crypto', 'msg_pay_forapp', 'msg_pay_gateways', 'msg_pay_refund', 'msg_service_buy', 'msg_service_renew', 'msg_service_manage', 'ref_ext', 'misc', 'cron', 'jsontext_users', 'jsontext_admin', 'other'];
        $orderedGrouped = [];
        foreach ($tabOrder as $g) {
            if (isset($grouped[$g])) { $orderedGrouped[$g] = $grouped[$g]; unset($grouped[$g]); }
        }
        foreach ($grouped as $g => $items) $orderedGrouped[$g] = $items;





        $tempStatus = \Faoxima\Core\Database\TextbotTemporary::status($pdo, $adminName);
        $savedNum = isset($_GET['saved']) ? (int)$_GET['saved'] : -1;
        $addedKey = isset($_GET['added']) ? (string)$_GET['added'] : '';
        $delKey   = isset($_GET['deleted']) ? (string)$_GET['deleted'] : '';
        $tonpayTemplateFlash = isset($_SESSION['tonpay_template_errors']) && is_array($_SESSION['tonpay_template_errors']) ? $_SESSION['tonpay_template_errors'] : [];
        unset($_SESSION['tonpay_template_errors']);
        $edgeRejectedKeys = isset($_GET['edge_rejected']) ? array_values(array_filter(explode(',', (string)$_GET['edge_rejected']), 'strlen')) : [];
        $richRejectedKeys = isset($_GET['rich_rejected']) ? array_values(array_filter(explode(',', (string)$_GET['rich_rejected']), 'strlen')) : [];
        $richTemplateErrors = isset($_SESSION['rich_template_errors']) && is_array($_SESSION['rich_template_errors']) ? $_SESSION['rich_template_errors'] : [];
        unset($_SESSION['rich_template_errors']);
        if (isset($_SESSION['textbot_save_error'])) {
            $flash['err'] = (string) $_SESSION['textbot_save_error'];
            unset($_SESSION['textbot_save_error']);
        }

        return true;
    }
}
