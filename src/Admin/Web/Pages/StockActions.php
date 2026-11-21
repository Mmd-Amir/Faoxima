<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;
use Throwable;

/**
 * Handler stage. Inherited by value: $adminWebPage, $bad, $badSub, $duplicate, $invoiceTableMissing, $n, $nationalPanels, $panelNameOf, $pdo, $productByCode, $tableMissing.
 * Inherited or exported by reference: $action, $cfgFilter, $daysActive, $daysFilter, $daysMode, $flash, $name, $ok, $payload, $remainingOf, $scanTruncated, $selectedShelf, $sh, $shelfCounts, $stmt, $viewShelfId.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class StockActions
{
    public static function handle(
        &$action,
        $adminWebPage,
        $bad,
        $badSub,
        &$cfgFilter,
        &$daysActive,
        &$daysFilter,
        &$daysMode,
        $duplicate,
        &$flash,
        $invoiceTableMissing,
        $n,
        &$name,
        $nationalPanels,
        &$ok,
        $panelNameOf,
        &$payload,
        $pdo,
        $productByCode,
        &$remainingOf,
        &$scanTruncated,
        &$selectedShelf,
        &$sh,
        &$shelfCounts,
        &$stmt,
        $tableMissing,
        &$viewShelfId
    ) {
        if (($adminWebPage ?? null) !== 'stock.php') {
            return;
        }

        if (!$tableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
            \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();
            $action = $_POST['_action'] ?? '';

            if ($action === 'add_shelf') {
                [$name, $selectedPanel, $codeProduct, $productName, $volumeGb, $serviceDays, $price, $errors] = \Faoxima\Admin\Web\Pages\StockRequestHelpers::fx_stock_shelf_input($_POST, $productByCode, $nationalPanels, $panelNameOf);

                $sourcePanel = $selectedPanel;
                $stockPanel  = $selectedPanel;

                if (!empty($errors)) {
                    $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
                } else {
                    try {
                        $dup = $pdo->prepare("SELECT 1 FROM nm_stock_shelves WHERE name = :n AND source_codepanel = :sp LIMIT 1");
                        $dup->execute([':n' => $name, ':sp' => $sourcePanel]);
                        if ($dup->fetchColumn()) {
                            $flash['err'] = 'انباری با همین نام و پنل مبدأ از قبل وجود دارد.';
                        }
                    } catch (\Throwable $e) {
                    }

                    if (!$flash['err']) {
                        try {
                            $stmt = $pdo->prepare(
                                "INSERT INTO nm_stock_shelves
                         (name, source_codepanel, stock_codepanel, codeproduct, product_name,
                          volume_gb, service_days, price, status, created_at)
                         VALUES (:n, :sp, :tp, :cp, :pn, :v, :d, :p, 'active', :t)"
                            );
                            $stmt->execute([
                                ':n'  => $name,
                                ':sp' => $sourcePanel,
                                ':tp' => $stockPanel,
                                ':cp' => $codeProduct,
                                ':pn' => $productName,
                                ':v'  => $volumeGb,
                                ':d'  => $serviceDays,
                                ':p' => $price,
                                ':t'  => time(),
                            ]);
                            $flash['ok'] = 'انبار جدید ثبت شد.';
                        } catch (\Throwable $e) {
                            $flash['err'] = 'افزودن ناموفق: ' . $e->getMessage();
                        }
                    }
                }
            } elseif ($action === 'delete_shelf') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        \Faoxima\Admin\Web\Support\BulkDelete::fx_admin_delete_configs($pdo, 'nm_config_stock', 'shelf_id = ?', [$id], $id);
                        $flash['ok'] = 'انبار و کانفیگ‌های آن حذف شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'حذف انجام نشد. جزئیات در لاگ سرور ثبت شد.';
                    }
                }
            } elseif ($action === 'add_configs') {
                $shelfId    = (int)($_POST['shelf_id'] ?? 0);
                $configsRaw = (string)($_POST['configs']   ?? '');
                $itemsJson  = (string)($_POST['items_json'] ?? '');
                $entryMode  = (string)($_POST['entry_mode'] ?? 'single');
                $entryMode  = ($entryMode === 'bulk') ? 'bulk' : 'single';
                $payload    = ($itemsJson !== '') ? $itemsJson : $configsRaw;

                $shelf = null;
                try {
                    $sh = $pdo->prepare("SELECT * FROM nm_stock_shelves WHERE id = :id");
                    $sh->execute([':id' => $shelfId]);
                    $shelf = $sh->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (\Throwable $e) {
                }

                if (!$shelf) {
                    $flash['err'] = 'انبار انتخابی پیدا نشد.';
                } elseif (trim($payload) === '') {
                    $flash['err'] = 'هیچ کانفیگی وارد نشده است.';
                } else {
                    $entries = \Faoxima\Admin\Web\Support\ItemParser::faoxima_pair_configs_and_links($payload);

                    if (empty($entries)) {
                        $flash['err'] = 'هیچ کانفیگی وارد نشده است.';
                    } else {
                        $tooManyInSingleMode = false;
                        if ($entryMode === 'single' && count($entries) > 1) {
                            $tooManyInSingleMode = true;
                            $flash['err'] = 'در حالت «تکی» فقط یک کانفیگ (یا یک کانفیگ همراه با لینکِ اشتراکش) مجاز است. شما ' . count($entries) . ' آیتم وارد کرده‌اید. برای ثبت چند کانفیگ با هم، از حالت «دسته‌ای» استفاده کنید.';
                        }

                        if (!$tooManyInSingleMode) {
                            try {
                                [$ok, $duplicate, $bad, $badSub] = \Faoxima\Admin\Web\Pages\StockRequestHelpers::fx_stock_import_configs($pdo, $shelfId, $entries);
                            } catch (Throwable $e) {
                                header('Location: stock.php?shelf=' . $shelfId . '&import_error=1');
                                exit;
                            }

                            $parts = ["ثبت‌شده: {$ok}"];
                            if ($duplicate > 0) $parts[] = "تکراری: {$duplicate}";
                            if ($bad       > 0) $parts[] = "نامعتبر: {$bad}";
                            if ($badSub    > 0) $parts[] = "لینک اشتراک نامعتبر (کانفیگ ثبت شد): {$badSub}";
                            $msg = 'نتیجه ورود کانفیگ‌ها — ' . implode(' · ', $parts);
                            if ($ok === 0 && $duplicate > 0) {
                                $flash['err'] = $msg . ' (این کانفیگ‌ها از قبل در انبار موجودند)';
                            } else {
                                $flash['ok'] = $msg;
                            }
                            header('Location: stock.php?shelf=' . $shelfId);
                            exit;
                        }
                    }
                }
            } elseif ($action === 'delete_config_hard') {

                $configId = (int)($_POST['config_id'] ?? 0);
                $shelfId  = (int)($_POST['shelf_id']  ?? 0);
                $configDeleted = false;
                $deleteFailed = false;
                if ($configId > 0) {
                    try {
                        $configDeleted = \Faoxima\Admin\Web\Support\BulkDelete::fx_admin_delete_configs($pdo, 'nm_config_stock', 'id = ?', [$configId]) > 0;
                        $flash['ok'] = $configDeleted ? 'کانفیگ برای همیشه از دیتابیس حذف شد.' : 'کانفیگ پیدا نشد.';
                    } catch (\Throwable $e) {
                        $deleteFailed = true;
                        $flash['err'] = 'حذف انجام نشد. جزئیات در لاگ سرور ثبت شد.';
                    }
                }
                $redir = 'stock.php?shelf=' . $shelfId;
                if (!empty($_POST['cstatus'])) $redir .= '&cstatus=' . urlencode((string)$_POST['cstatus']);
                if (!empty($_POST['cpage']))   $redir .= '&cpage=' . (int)$_POST['cpage'];
                $redir .= \Faoxima\Admin\Web\Pages\StockRequestHelpers::faoxima_stock_days_qs();
                if ($configDeleted) $redir .= '&bulkclear=1';
                if ($deleteFailed) $redir .= '&delete_error=1';
                if ($shelfId > 0) {
                    header('Location: ' . $redir);
                    exit;
                }
            } elseif ($action === 'stock_bulk_delete') {

                $shelfId = (int)($_POST['shelf_id'] ?? 0);
                $ids = \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_positive_ids((array)($_POST['ids'] ?? []));
                $ids = array_slice($ids, 0, 500);
                $deleteFailed = false;

                if (!$ids) {
                    $flash['err'] = 'موردی برای حذف انتخاب نشده بود.';
                } elseif ($shelfId <= 0) {
                    $flash['err'] = 'انبار مشخص نشده است.';
                } else {
                    try {
                        $ph = implode(',', array_fill(0, count($ids), '?'));
                        $n = \Faoxima\Admin\Web\Support\BulkDelete::fx_admin_delete_configs($pdo, 'nm_config_stock', "shelf_id = ? AND id IN ($ph)", array_merge([$shelfId], $ids));
                        $flash['ok'] = $n > 0
                            ? $n . ' کانفیگ برای همیشه از دیتابیس حذف شد.'
                            : 'هیچ کانفیگی حذف نشد.';
                    } catch (\Throwable $e) {
                        $deleteFailed = true;
                        $flash['err'] = 'حذف انجام نشد. جزئیات در لاگ سرور ثبت شد.';
                    }
                }

                $redir = 'stock.php?shelf=' . $shelfId;
                if (!empty($_POST['cstatus'])) $redir .= '&cstatus=' . urlencode((string)$_POST['cstatus']);
                if (!empty($_POST['cpage']))   $redir .= '&cpage=' . (int)$_POST['cpage'];
                $redir .= \Faoxima\Admin\Web\Pages\StockRequestHelpers::faoxima_stock_days_qs();
                if (!empty($n)) $redir .= '&bulkclear=1';
                if ($deleteFailed) $redir .= '&delete_error=1';
                if ($shelfId > 0) {
                    header('Location: ' . $redir);
                    exit;
                }
            } elseif ($action === 'delete_filtered_configs') {
                $shelfId = (int)($_POST['shelf_id'] ?? 0);
                $cfgFilter = (string)($_POST['cstatus'] ?? 'all');
                $allowed = ['active', 'reserved', 'delivered', 'disabled'];
                $filteredDeleted = 0;
                if ($shelfId > 0) {
                    try {
                        if ($cfgFilter !== 'all' && in_array($cfgFilter, $allowed, true)) {
                            $filteredDeleted = \Faoxima\Admin\Web\Support\BulkDelete::fx_admin_delete_configs($pdo, 'nm_config_stock', 'shelf_id = ? AND status = ?', [$shelfId, $cfgFilter]);
                        } else {
                            $filteredDeleted = \Faoxima\Admin\Web\Support\BulkDelete::fx_admin_delete_configs($pdo, 'nm_config_stock', 'shelf_id = ?', [$shelfId]);
                        }
                        $flash['ok'] = $filteredDeleted . ' کانفیگ برای همیشه از دیتابیس حذف شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'حذف انجام نشد. جزئیات در لاگ سرور ثبت شد.';
                    }
                    $redir = 'stock.php?shelf=' . $shelfId . '&cstatus=' . urlencode($cfgFilter);
                    if ($filteredDeleted > 0) $redir .= '&bulkclear=1';
                    if ($flash['err'] !== '') $redir .= '&delete_error=1';
                    header('Location: ' . $redir);
                    exit;
                }
            } elseif ($action === 'delete_active_configs') {

                $shelfId = (int)($_POST['shelf_id'] ?? 0);
                $activeDeleted = 0;
                if ($shelfId > 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM nm_config_stock WHERE shelf_id = :id AND status = 'active'");
                        $stmt->execute([':id' => $shelfId]);
                        $activeDeleted = $stmt->rowCount();
                        $flash['ok'] = $activeDeleted . ' کانفیگ فعال برای همیشه از دیتابیس حذف شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
                    }
                    $redir = 'stock.php?shelf=' . $shelfId . \Faoxima\Admin\Web\Pages\StockRequestHelpers::faoxima_stock_days_qs();
                    if ($activeDeleted > 0) $redir .= '&bulkclear=1';
                    header('Location: ' . $redir);
                    exit;
                }
            }
        }


        $viewShelfId   = isset($_GET['shelf']) ? (int)$_GET['shelf'] : 0;
        $selectedShelf = null;
        $shelfCounts   = [];
        $remainingOf   = [];
        $scanTruncated = false;

        $daysRaw    = isset($_GET['days']) ? trim((string)$_GET['days']) : '';
        $daysFilter = ($daysRaw !== '' && ctype_digit($daysRaw)) ? (int)$daysRaw : null;
        $daysMode   = \Faoxima\Admin\Web\Support\Remaining::faoxima_remaining_dmode($_GET['dmode'] ?? '');
        $daysActive = ($daysFilter !== null && !$invoiceTableMissing);

        return true;
    }
}
