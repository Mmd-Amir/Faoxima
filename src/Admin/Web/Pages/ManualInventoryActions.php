<?php

namespace Faoxima\Admin\Web\Pages;

/**
 * Handler stage. Inherited by value: $adminWebPage, $panelNameOf, $pdo, $productByCode, $tableMissing, $totalStorageUsedBytes.
 * Inherited or exported by reference: $action, $allowedStatuses, $bad, $flash, $items, $maxStorageBytes, $maxStorageGb, $msBulkClear, $ok, $payload, $statusFilter.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class ManualInventoryActions
{
    public static function handle(
        &$action,
        $adminWebPage,
        &$allowedStatuses,
        &$bad,
        &$flash,
        &$items,
        &$maxStorageBytes,
        &$maxStorageGb,
        &$msBulkClear,
        &$ok,
        $panelNameOf,
        &$payload,
        $pdo,
        $productByCode,
        &$statusFilter,
        $tableMissing,
        $totalStorageUsedBytes
    ) {
        if (($adminWebPage ?? null) !== 'manual_service.php') {
            return;
        }

        if (!$tableMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
                $flash['err'] = 'حجم فایل آپلود شده بیشتر از حد مجاز سرور (post_max_size / upload_max_filesize) است.';
            }

            $action = $_POST['_action'] ?? '';
            if ($action !== '') {
                \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();
            }

            if ($action === 'update_storage_limit') {
                $newGb = (float)($_POST['max_storage_gb'] ?? 5);
                if ($newGb <= 0) $newGb = 1.0;
                if ($newGb > 1000) $newGb = 1000.0;

                if (\Faoxima\Admin\Web\Pages\ManualServiceRequestHelpers::faoxima_set_ms_setting($pdo, 'max_storage_gb', (string)$newGb)) {
                    $flash['ok'] = "حداکثر حجم ذخیره‌سازی با موفقیت به {$newGb} گیگابایت تغییر یافت و پیکربندی دیتابیس بروزرسانی شد.";
                    $maxStorageGb = $newGb;
                    $maxStorageBytes = (int)round($maxStorageGb * 1073741824);
                    try {
                        $packetBytes = max(67108864, min(1073741824, $maxStorageBytes));
                        @$pdo->exec("SET SESSION max_allowed_packet = {$packetBytes}");
                        @$pdo->exec("SET GLOBAL max_allowed_packet = {$packetBytes}");
                    } catch (\Throwable $e) {
                    }
                } else {
                    $flash['err'] = 'خطا در ذخیره‌سازی تنظیمات حداکثر حجم دیتابیس.';
                }
            }

            if ($action === 'ms_add_config') {
                $codePanel    = trim((string)($_POST['codepanel']   ?? ''));
                $codeProduct  = trim((string)($_POST['codeproduct'] ?? ''));
                $nameRecord   = trim((string)($_POST['namerecord']  ?? ''));
                $configSource = (string)($_POST['config_source']   ?? 'text');
                $pasteText    = (string)($_POST['contentrecord']    ?? '');
                $itemsJson    = (string)($_POST['items_json']        ?? '');
                $customExt    = \Faoxima\Admin\Web\Pages\ManualServiceRequestHelpers::faoxima_ms_normalize_ext((string)($_POST['custom_ext'] ?? ''));
                $sendAsFile   = (($_POST['send_as_file'] ?? '') === '1');
                $entryMode    = (string)($_POST['entry_mode'] ?? 'single');
                $entryMode    = in_array($entryMode, ['bulk', 'multi'], true) ? $entryMode : 'single';
                $payload      = ($itemsJson !== '') ? $itemsJson : $pasteText;

                $items = \Faoxima\Admin\Web\Pages\ManualServiceRequestHelpers::fx_ms_prepare_items($_POST, $_FILES, $configSource, $entryMode, $payload, $customExt, $sendAsFile, $flash);

                if ($flash['err'] === '' && $codePanel === '') {
                    $flash['err'] = 'پنل فروش دستی را انتخاب کنید.';
                }
                if ($flash['err'] === '' && !isset($panelNameOf[$codePanel])) {
                    $flash['err'] = 'پنل انتخابی معتبر نیست.';
                }
                if ($flash['err'] === '' && $codeProduct === '') {
                    $flash['err'] = 'محصول را انتخاب کنید.';
                }
                if ($flash['err'] === '' && $codeProduct !== 'usertest' && !isset($productByCode[$codeProduct])) {
                    $flash['err'] = 'محصول انتخابی معتبر نیست.';
                }
                if ($flash['err'] === '' && $nameRecord === '') {
                    $flash['err'] = 'یک نام برای کانفیگ وارد کنید.';
                }
                if ($flash['err'] === '') {
                    $items = array_values(array_filter($items, static function ($it) {
                        return (string)($it['content'] ?? '') !== '';
                    }));
                    if (empty($items)) {
                        $flash['err'] = 'محتوای فایل کانفیگ خالی است. لطفاً یک فایل معتبر (غیر خالی) آپلود کنید.';
                    }
                }
                if ($flash['err'] === '') {
                    $incomingBytes = 0;
                    foreach ($items as $itm) {
                        $incomingBytes += strlen((string)($itm['content'] ?? ''));
                    }
                    if (($totalStorageUsedBytes + $incomingBytes) > $maxStorageBytes) {
                        $usedGbStr = round($totalStorageUsedBytes / 1073741824, 2);
                        $flash['err'] = "حجم فایل‌های انتخابی از سقف مجاز کل ذخیره‌سازی دیتابیس ({$maxStorageGb} گیگابایت) فراتر می‌رود. (میزان استفاده فعلی: {$usedGbStr} GB)";
                    }
                }
                if ($flash['err'] === '') {
                    [$ok, $bad, $isMultiItem] = \Faoxima\Admin\Web\Pages\ManualServiceRequestHelpers::fx_ms_store_items($pdo, $items, $entryMode, $codePanel, $codeProduct, $nameRecord);

                    if ($ok > 0) {
                        $flash['ok'] = ($isMultiItem
                            ? ($ok . ' مورد به‌صورت یک بستهٔ چندموردی ثبت شد.')
                            : ($ok . ' کانفیگ با موفقیت ثبت شد.'))
                            . ($bad > 0 ? ' (' . $bad . ' مورد نامعتبر)' : '');
                    } else {
                        $flash['err'] = 'هیچ کانفیگ معتبری برای ثبت یافت نشد.';
                    }
                }
            } elseif (in_array($action, ['ms_delete_config_hard', 'ms_delete_group_hard', 'ms_purge_deleted', 'ms_bulk_delete'], true)) {
                [$flash, $clearSelection] = \Faoxima\Admin\Web\Pages\ManualServiceRequestHelpers::fx_ms_delete_action($pdo, $action, $_POST, $flash);
                if ($clearSelection !== null) {
                    $msBulkClear = $clearSelection;
                }
            }
        }

        $statusFilter = isset($_GET['cstatus']) ? (string)$_GET['cstatus'] : 'all';
        $allowedStatuses = ['active', 'selled', 'disabled', 'delete'];

        return true;
    }
}
