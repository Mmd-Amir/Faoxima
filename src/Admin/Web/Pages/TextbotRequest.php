<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $action, $addedKey, $categories, $cur, $d, $delKey, $edgeRejectedKeys, $flash, $g, $items, $key, $orderedGrouped, $payload, $query, $r, $richRejectedKeys, $richStates, $richTemplateErrors, $row, $rows, $savedNum, $tonpayTemplateFlash, $value, $values, $visibleRows.
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
        &$value,
        &$values,
        &$visibleRows
    ) {
        $textDefaults = ['text_custom_price_summary' => \Faoxima\Orders\Pricing\CustomDuration::fx_custom_price_text_default()];
        $textDefaults += array_intersect_key(\Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateDefaults(), array_flip(['dyn_atlaspay_bot_receipt_button', 'dyn_atlaspay_bot_receipt_instructions']));

        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
        $query->execute();
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!isset($_SESSION["user"]) || !$adminRow) {
            header('Location: login.php');
            exit;
        }

        \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();












        $flash = ['ok' => '', 'err' => ''];


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['_action'] ?? '';

            if ($action === 'save') {
                $rawPayload = $_POST['_text_payload'] ?? null;
                $payload = is_string($rawPayload) ? json_decode($rawPayload, true) : null;
                $validPayload = is_array($payload) && ($payload['version'] ?? null) === 1
                    && isset($payload['t'], $payload['rich']) && is_array($payload['t']) && is_array($payload['rich']);
                if ($validPayload) {
                    $validPayload = count($payload['t']) === count($payload['rich']);
                    foreach ($payload['t'] as $key => $value) {
                        if (!array_key_exists($key, $payload['rich'])) {
                            $validPayload = false;
                            break;
                        }
                    }
                }
                if ($validPayload) {
                    foreach ([$payload['t'], $payload['rich']] as $values) {
                        foreach ($values as $value) {
                            if (!is_string($value)) {
                                $validPayload = false;
                                break 2;
                            }
                        }
                    }
                }
                if (!$validPayload) {
                    $_SESSION['textbot_save_error'] = 'داده‌های فرم کامل یا معتبر نیستند؛ تغییری ذخیره نشد. صفحه را تازه کنید و دوباره ذخیره کنید.';
                    header('Location: textbot.php');
                    exit;
                }
                $newValues = $payload['t'];


                $cur = [];
                $r = $pdo->query("SELECT id_text, text FROM textbot");
                if ($r) {
                    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $cur[(string)$row['id_text']] = (string)$row['text'];
                    }
                }

                $cur += $textDefaults;
                $updated = 0;
                $edgeRejected = [];
                $tonpayTemplateErrors = [];
                $richPrepared = [];
                $richDropped = [];
                $richRejected = [];
                $richBlocked = [];
                $richErrors = [];
                $richPost = $payload['rich'];
                foreach ($richPost as $rKey => $rRaw) {
                    $rKey = (string)$rKey;
                    if (!isset($cur[$rKey])) continue;
                    $rText = array_key_exists($rKey, $newValues) ? (string)$newValues[$rKey] : (string)$cur[$rKey];
                    $rCompanion = \Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_rich_prefix() . $rKey;
                    $rIssue = null;
                    $rState = \Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_rich_state_for_save($rRaw, $rText, $rIssue);
                    if ($rState === null) {
                        if ($rIssue !== null) {
                            $richRejected[] = $rKey;
                            $richBlocked[$rKey] = true;
                            $richErrors[$rKey] = $rIssue;
                            continue;
                        }
                        if (isset($cur[$rCompanion])) {
                            $richDropped[$rCompanion] = true;
                        }
                        continue;
                    }
                    $newValues[$rKey] = (string)$rState['html'];
                    $richPrepared[$rKey] = $rState;
                }
                foreach ($newValues as $key => $val) {
                    $key = (string)$key;
                    $val = (string)$val;
                    if (isset($richBlocked[$key])) continue;
                    $rCompanion = \Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_rich_prefix() . $key;
                    $hasRichChange = (isset($richPrepared[$key]) && ($cur[$rCompanion] ?? '') !== \Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_rich_state_encode($richPrepared[$key])) || isset($richDropped[$rCompanion]);
                    if (isset($cur[$key]) && $cur[$key] === $val && !$hasRichChange) {
                        continue;
                    }
                    $tonpayTemplateIssue = \Faoxima\Payments\Gateways\Tonpay\Templates::tonpayTemplateValidate($key, $val);
                    if ($tonpayTemplateIssue !== '') {
                        $tonpayTemplateErrors[$key] = $tonpayTemplateIssue;
                        continue;
                    }
                    if (\Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_edge_violation($key, $val) !== '') {
                        $edgeRejected[] = $key;
                        continue;
                    }
                    try {
                        if ($hasRichChange) {
                            $pdo->beginTransaction();
                        }
                        $upd = $pdo->prepare("INSERT INTO textbot (id_text, text) VALUES (:k, :v) ON DUPLICATE KEY UPDATE text = :v2");
                        $upd->execute([':k' => $key, ':v' => $val, ':v2' => $val]);
                        if (isset($richPrepared[$key])) {
                            $rJson = \Faoxima\Admin\Web\Support\TextbotEdge::fx_textbot_rich_state_encode($richPrepared[$key]);
                            $upd->execute([':k' => $rCompanion, ':v' => $rJson, ':v2' => $rJson]);
                        } elseif (isset($richDropped[$rCompanion])) {
                            $pdo->prepare("DELETE FROM textbot WHERE id_text = :k")->execute([':k' => $rCompanion]);
                        }
                        if ($hasRichChange) {
                            $pdo->commit();
                        }
                        $updated++;
                    } catch (\Throwable $e) {
                        if ($hasRichChange && $pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $richErrors[$key] = 'ذخیرهٔ متن انجام نشد؛ نسخهٔ قبلی حفظ شد.';
                        $richRejected[] = $key;
                        $flash['err'] = $e->getMessage();
                        error_log('[panel/textbot] update failed for ' . $key . ': ' . $e->getMessage());
                    }
                }
                if ($updated > 0) {
                    \Faoxima\Core\Database\SelectCache::faoxima_bust_bot_selectcache('textbot');
                    if (method_exists(\Faoxima\Core\Database\SelectCache::class, 'clearSelectCache')) {
                        \Faoxima\Core\Database\SelectCache::clearSelectCache('textbot');
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
        $tabOrder = ['sys', 'srv', 'help', 'fin', 'custom_pricing', 'ref', 'err_general', 'err_purchase', 'tickets', 'wallet_ext', 'tonpay', 'ref_ext', 'misc', 'cron', 'jsontext_users', 'jsontext_admin', 'other'];
        $orderedGrouped = [];
        foreach ($tabOrder as $g) {
            if (isset($grouped[$g])) { $orderedGrouped[$g] = $grouped[$g]; unset($grouped[$g]); }
        }
        foreach ($grouped as $g => $items) $orderedGrouped[$g] = $items;





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
