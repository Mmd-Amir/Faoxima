<?php

namespace Faoxima\Admin\Web\Pages;

use PDO;

/**
 * Handler stage. Inherited by value: $pdo.
 * Inherited or exported by reference: $_csrf, $action, $df1, $df2, $discDateKeep, $discFilterActive, $discFilterCriteria, $discQ, $discountAgentOptions, $discountSectionOptions, $flash, $giftDateKeep, $giftFilterActive, $giftFilterCriteria, $giftQ, $list, $pg1, $query, $section, $stmt, $value.
 * Returns true to continue the request pipeline, anything else stops it.
 */
final class DiscountsRequest
{
    public static function handle(
        &$_csrf,
        &$action,
        &$df1,
        &$df2,
        &$discDateKeep,
        &$discFilterActive,
        &$discFilterCriteria,
        &$discQ,
        &$discountAgentOptions,
        &$discountSectionOptions,
        &$flash,
        &$giftDateKeep,
        &$giftFilterActive,
        &$giftFilterCriteria,
        &$giftQ,
        &$list,
        $pdo,
        &$pg1,
        &$query,
        &$section,
        &$stmt,
        &$value
    ) {
        $query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
        $query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
        $query->execute();
        $adminRow = $query->fetch(PDO::FETCH_ASSOC);
        if (!isset($_SESSION["user"]) || !$adminRow) {
            header('Location: login.php');
            exit;
        }

        \Faoxima\Admin\Web\Support\Csrf::fx_csrf_guard();
        $_csrf = \Faoxima\Admin\Web\Support\Csrf::fx_csrf_token();

        $flash = ['ok' => '', 'err' => ''];

        $discountSectionOptions = [
            'all'    => 'همه بخش‌ها',
            'buy'    => 'خرید سرویس',
            'extend' => 'تمدید سرویس',
            'volume' => 'حجم اضافه',
            'time'   => 'زمان اضافه',
            'charge' => 'شارژ کیف پول',
        ];
        $discountAgentOptions = [
            'allusers' => 'همه کاربران',
            'f'        => 'فقط کاربر عادی',
            'n'        => 'فقط نمایندگان',
            'n2'       => 'فقط نمایندگان پیشرفته',
        ];


        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['_action'] ?? '';

            if ($action === 'add') {
                $code    = trim((string)($_POST['codeDiscount'] ?? ''));
                $vtype   = (string)($_POST['type']            ?? 'percent');
                $value   = (string)($_POST['price']           ?? '0');
                $limit   = (int)($_POST['limitDiscount']      ?? 0);
                $first   = !empty($_POST['usefirst']) ? '1' : '0';
                $oneper  = !empty($_POST['useuser'])  ? '1' : '0';
                $target  = trim((string)($_POST['target_user'] ?? ''));
                $expDays = (int)($_POST['expire_days'] ?? 0);


                $errors = [];


                if ($code === '') {
                    $errors[] = 'کد تخفیف نمی‌تواند خالی باشد.';
                } elseif (!preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code)) {
                    $errors[] = 'کد تخفیف فقط شامل حروف انگلیسی، عدد، خط تیره و آندرلاین (۲ تا ۴۰ کاراکتر).';
                }


                if (!in_array($vtype, ['percent', 'amount', 'free'], true)) {
                    $errors[] = 'نوع تخفیف نامعتبر است.';
                }

                $sectionList = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($_POST['section_csv'] ?? 'all'))))));
                if (empty($sectionList)) {
                    $errors[] = 'حداقل یک بخش کد باید انتخاب شود.';
                } elseif (array_diff($sectionList, array_keys($discountSectionOptions)) !== []) {
                    $errors[] = 'بخش کد نامعتبر است.';
                }
                $section = implode(',', $sectionList);


                $numericValue = (float)$value;
                if ($vtype === 'percent') {
                    if ($numericValue <= 0 || $numericValue > 100) {
                        $errors[] = 'درصد تخفیف باید بین ۱ تا ۱۰۰ باشد.';
                    }
                } elseif ($vtype === 'amount') {
                    if ($numericValue <= 0) {
                        $errors[] = 'مبلغ تخفیف باید بزرگ‌تر از صفر باشد.';
                    } elseif ($numericValue > 100000000) {
                        $errors[] = 'مبلغ تخفیف بیش از حد بزرگ است.';
                    }
                } else {
                    $value = '0';
                }


                if ($limit < 0) {
                    $errors[] = 'سقف کل استفاده نمی‌تواند منفی باشد.';
                }


                $agentList = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($_POST['agent_csv'] ?? 'allusers'))))));
                if (empty($agentList)) {
                    $errors[] = 'گروه هدف نامعتبر است.';
                } elseif (array_diff($agentList, array_keys($discountAgentOptions)) !== []) {
                    $errors[] = 'گروه هدف نامعتبر است.';
                }
                $agent = implode(',', $agentList);

                if ($target !== '' && !ctype_digit($target)) {
                    $errors[] = 'آیدی عددی کاربر هدف نامعتبر است.';
                }

                $targetingMode = (string)($_POST['targeting_mode'] ?? 'none');
                if (!in_array($targetingMode, ['none', 'category', 'panel'], true)) {
                    $errors[] = 'نحوه هدف‌گیری نامعتبر است.';
                }
                $codeCategorySave = 'all';
                $codePanelTargeted = '/all';
                if ($targetingMode === 'category') {
                    $categoryList = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['code_category_csv'] ?? '')))));
                    if (empty($categoryList)) {
                        $errors[] = 'حداقل یک دسته‌بندی باید انتخاب شود.';
                    } else {
                        $codeCategorySave = implode(',', $categoryList);
                    }
                } elseif ($targetingMode === 'panel') {
                    $panelList = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['code_panel_csv'] ?? '')))));
                    if (empty($panelList)) {
                        $errors[] = 'حداقل یک پنل باید انتخاب شود.';
                    } else {
                        $codePanelTargeted = implode(',', $panelList);
                    }
                }


                if (empty($errors)) {
                    try {
                        $dup = $pdo->prepare("SELECT 1 FROM DiscountSell WHERE codeDiscount = :c LIMIT 1");
                        $dup->execute([':c' => $code]);
                        if ($dup->fetchColumn()) {
                            $errors[] = 'این کد تخفیف از قبل ثبت شده است.';
                        }
                    } catch (\Throwable $e) {  }
                }

                if (!empty($errors)) {
                    $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
                } else {
                    try {
                        $expiry = $expDays > 0 ? (string)(time() + $expDays * 86400) : '0';
                        $stmt = $pdo->prepare(
                            "INSERT INTO DiscountSell
                     (codeDiscount, price, limitDiscount, agent, usefirst, useuser, code_product, code_panel, time, type, usedDiscount, section, value_type, target_user, status, targeting_mode, code_category)
                     VALUES (:c, :p, :l, :a, :f, :u, 'all', :cpanel, :t, :sec, '0', :sec2, :vt, :tg, 'active', :tm, :ccat)"
                        );
                        $stmt->execute([
                            ':c'   => $code,
                            ':p'   => $value,
                            ':l'   => (string)$limit,
                            ':a'   => $agent,
                            ':f'   => $first,
                            ':u'   => $oneper,
                            ':t'   => $expiry,
                            ':sec' => $section,
                            ':sec2'=> $section,
                            ':vt'  => $vtype,
                            ':tg'  => ($target === '' ? null : $target),
                            ':cpanel' => $codePanelTargeted,
                            ':tm'  => $targetingMode,
                            ':ccat'=> $codeCategorySave,
                        ]);
                        $flash['ok'] = 'کد تخفیف افزوده شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'خطا: ' . $e->getMessage();
                    }
                }
            }
            elseif ($action === 'edit') {
                $id      = (int)($_POST['id'] ?? 0);
                $code    = trim((string)($_POST['codeDiscount'] ?? ''));
                $vtype   = (string)($_POST['type']            ?? 'percent');
                $value   = (string)($_POST['price']           ?? '0');
                $limit   = (int)($_POST['limitDiscount']      ?? 0);
                $first   = !empty($_POST['usefirst']) ? '1' : '0';
                $oneper  = !empty($_POST['useuser'])  ? '1' : '0';
                $target  = trim((string)($_POST['target_user'] ?? ''));
                $expDays = (int)($_POST['expire_days'] ?? 0);

                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'شناسه کد نامعتبر است.';
                }
                if ($code === '') {
                    $errors[] = 'کد تخفیف نمی‌تواند خالی باشد.';
                } elseif (!preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code)) {
                    $errors[] = 'کد تخفیف فقط شامل حروف انگلیسی، عدد، خط تیره و آندرلاین (۲ تا ۴۰ کاراکتر).';
                }
                if (!in_array($vtype, ['percent', 'amount', 'free'], true)) {
                    $errors[] = 'نوع تخفیف نامعتبر است.';
                }
                $sectionList = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($_POST['section_csv'] ?? 'all'))))));
                if (empty($sectionList)) {
                    $errors[] = 'حداقل یک بخش کد باید انتخاب شود.';
                } elseif (array_diff($sectionList, array_keys($discountSectionOptions)) !== []) {
                    $errors[] = 'بخش کد نامعتبر است.';
                }
                $section = implode(',', $sectionList);
                $numericValue = (float)$value;
                if ($vtype === 'percent') {
                    if ($numericValue <= 0 || $numericValue > 100) {
                        $errors[] = 'درصد تخفیف باید بین ۱ تا ۱۰۰ باشد.';
                    }
                } elseif ($vtype === 'amount') {
                    if ($numericValue <= 0) {
                        $errors[] = 'مبلغ تخفیف باید بزرگ‌تر از صفر باشد.';
                    } elseif ($numericValue > 100000000) {
                        $errors[] = 'مبلغ تخفیف بیش از حد بزرگ است.';
                    }
                } else {
                    $value = '0';
                }
                if ($limit < 0) {
                    $errors[] = 'سقف کل استفاده نمی‌تواند منفی باشد.';
                }
                $agentList = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($_POST['agent_csv'] ?? 'allusers'))))));
                if (empty($agentList)) {
                    $errors[] = 'گروه هدف نامعتبر است.';
                } elseif (array_diff($agentList, array_keys($discountAgentOptions)) !== []) {
                    $errors[] = 'گروه هدف نامعتبر است.';
                }
                $agent = implode(',', $agentList);
                if ($target !== '' && !ctype_digit($target)) {
                    $errors[] = 'آیدی عددی کاربر هدف نامعتبر است.';
                }
                $targetingMode = (string)($_POST['targeting_mode'] ?? 'none');
                if (!in_array($targetingMode, ['none', 'category', 'panel'], true)) {
                    $errors[] = 'نحوه هدف‌گیری نامعتبر است.';
                }
                $codeCategorySave = 'all';
                $codePanelTargeted = '/all';
                if ($targetingMode === 'category') {
                    $categoryList = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['code_category_csv'] ?? '')))));
                    if (empty($categoryList)) {
                        $errors[] = 'حداقل یک دسته‌بندی باید انتخاب شود.';
                    } else {
                        $codeCategorySave = implode(',', $categoryList);
                    }
                } elseif ($targetingMode === 'panel') {
                    $panelList = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['code_panel_csv'] ?? '')))));
                    if (empty($panelList)) {
                        $errors[] = 'حداقل یک پنل باید انتخاب شود.';
                    } else {
                        $codePanelTargeted = implode(',', $panelList);
                    }
                }
                if (empty($errors)) {
                    try {
                        $dup = $pdo->prepare("SELECT 1 FROM DiscountSell WHERE codeDiscount = :c AND id <> :id LIMIT 1");
                        $dup->execute([':c' => $code, ':id' => $id]);
                        if ($dup->fetchColumn()) {
                            $errors[] = 'این کد تخفیف از قبل ثبت شده است.';
                        }
                    } catch (\Throwable $e) {  }
                }
                if (!empty($errors)) {
                    $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
                } else {
                    try {
                        $expiry = $expDays > 0 ? (string)(time() + $expDays * 86400) : '0';
                        $stmt = $pdo->prepare(
                            "UPDATE DiscountSell
                        SET codeDiscount = :c, price = :p, limitDiscount = :l, agent = :a,
                            usefirst = :f, useuser = :u, time = :t, section = :sec,
                            value_type = :vt, type = :sec2, target_user = :tg,
                            code_panel = :cpanel, code_product = 'all', targeting_mode = :tm, code_category = :ccat
                      WHERE id = :id"
                        );
                        $stmt->execute([
                            ':c'   => $code,
                            ':p'   => $value,
                            ':l'   => (string)$limit,
                            ':a'   => $agent,
                            ':f'   => $first,
                            ':u'   => $oneper,
                            ':t'   => $expiry,
                            ':sec' => $section,
                            ':sec2'=> $section,
                            ':vt'  => $vtype,
                            ':tg'  => ($target === '' ? null : $target),
                            ':cpanel' => $codePanelTargeted,
                            ':tm'  => $targetingMode,
                            ':ccat'=> $codeCategorySave,
                            ':id'  => $id,
                        ]);
                        $flash['ok'] = 'کد تخفیف ویرایش شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'خطا: ' . $e->getMessage();
                    }
                }
            }
            elseif ($action === 'gift_add') {
                $code    = trim((string)($_POST['code'] ?? ''));
                $price   = (int)($_POST['gift_price'] ?? 0);
                $limit   = (int)($_POST['gift_limit'] ?? 0);
                $target  = trim((string)($_POST['gift_target'] ?? ''));
                $expDays = (int)($_POST['gift_expire_days'] ?? 0);

                $errors = [];
                if ($code === '' || !preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code)) {
                    $errors[] = 'کد هدیه نامعتبر است (۲ تا ۴۰ کاراکتر، حروف/عدد/-/_).';
                }
                if ($price <= 0) {
                    $errors[] = 'مبلغ کد هدیه باید بزرگ‌تر از صفر باشد.';
                }
                if ($limit < 0) {
                    $errors[] = 'سقف استفاده نمی‌تواند منفی باشد.';
                }
                if ($target !== '' && !ctype_digit($target)) {
                    $errors[] = 'آیدی عددی کاربر هدف نامعتبر است.';
                }
                if (empty($errors)) {
                    try {
                        $dup = $pdo->prepare("SELECT 1 FROM Discount WHERE code = :c LIMIT 1");
                        $dup->execute([':c' => $code]);
                        if ($dup->fetchColumn()) {
                            $errors[] = 'این کد هدیه از قبل ثبت شده است.';
                        }
                    } catch (\Throwable $e) {  }
                }
                if (!empty($errors)) {
                    $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
                } else {
                    try {
                        $expiry = $expDays > 0 ? (string)(time() + $expDays * 86400) : null;
                        $stmt = $pdo->prepare(
                            "INSERT INTO Discount (code, price, limituse, limitused, target_user, expire_at, status)
                     VALUES (:c, :p, :l, '0', :tg, :ex, 'active')"
                        );
                        $stmt->execute([
                            ':c'  => $code,
                            ':p'  => (string)$price,
                            ':l'  => (string)$limit,
                            ':tg' => ($target === '' ? null : $target),
                            ':ex' => $expiry,
                        ]);
                        $flash['ok'] = 'کد هدیه افزوده شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'خطا: ' . $e->getMessage();
                    }
                }
            }
            elseif ($action === 'gift_edit') {
                $id      = (int)($_POST['id'] ?? 0);
                $code    = trim((string)($_POST['code'] ?? ''));
                $price   = (int)($_POST['gift_price'] ?? 0);
                $limit   = (int)($_POST['gift_limit'] ?? 0);
                $target  = trim((string)($_POST['gift_target'] ?? ''));
                $expDays = (int)($_POST['gift_expire_days'] ?? 0);

                $errors = [];
                if ($id <= 0) {
                    $errors[] = 'شناسه کد نامعتبر است.';
                }
                if ($code === '' || !preg_match('/^[A-Za-z0-9_\-]{2,40}$/', $code)) {
                    $errors[] = 'کد هدیه نامعتبر است (۲ تا ۴۰ کاراکتر، حروف/عدد/-/_).';
                }
                if ($price <= 0) {
                    $errors[] = 'مبلغ کد هدیه باید بزرگ‌تر از صفر باشد.';
                }
                if ($limit < 0) {
                    $errors[] = 'سقف استفاده نمی‌تواند منفی باشد.';
                }
                if ($target !== '' && !ctype_digit($target)) {
                    $errors[] = 'آیدی عددی کاربر هدف نامعتبر است.';
                }
                if (empty($errors)) {
                    try {
                        $dup = $pdo->prepare("SELECT 1 FROM Discount WHERE code = :c AND id <> :id LIMIT 1");
                        $dup->execute([':c' => $code, ':id' => $id]);
                        if ($dup->fetchColumn()) {
                            $errors[] = 'این کد هدیه از قبل ثبت شده است.';
                        }
                    } catch (\Throwable $e) {  }
                }
                if (!empty($errors)) {
                    $flash['err'] = '• ' . implode("<br>• ", array_map(fn($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $errors));
                } else {
                    try {
                        $expiry = $expDays > 0 ? (string)(time() + $expDays * 86400) : null;
                        $stmt = $pdo->prepare(
                            "UPDATE Discount SET code = :c, price = :p, limituse = :l, target_user = :tg, expire_at = :ex WHERE id = :id"
                        );
                        $stmt->execute([
                            ':c'  => $code,
                            ':p'  => (string)$price,
                            ':l'  => (string)$limit,
                            ':tg' => ($target === '' ? null : $target),
                            ':ex' => $expiry,
                            ':id' => $id,
                        ]);
                        $flash['ok'] = 'کد هدیه ویرایش شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'خطا: ' . $e->getMessage();
                    }
                }
            }
            elseif ($action === 'gift_delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $pdo->prepare("DELETE FROM Discount WHERE id = :id")->execute([':id' => $id]);
                        $flash['ok'] = 'کد هدیه حذف شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
                    }
                }
            }
            elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $pdo->prepare("DELETE FROM DiscountSell WHERE id = :id")->execute([':id' => $id]);
                        $flash['ok'] = 'کد تخفیف حذف شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'حذف ناموفق: ' . $e->getMessage();
                    }
                }
            }
            elseif ($action === 'reset_usage') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $pdo->prepare("UPDATE DiscountSell SET usedDiscount = '0' WHERE id = :id")->execute([':id' => $id]);
                        $flash['ok'] = 'شمارنده استفاده صفر شد.';
                    } catch (\Throwable $e) {
                        $flash['err'] = 'بازنشانی ناموفق: ' . $e->getMessage();
                    }
                }
            }
            elseif ($action === 'bulk_delete') {
                $requestedIds = $_POST['ids'] ?? [];
                $deletedCount = \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_ids($pdo, 'DiscountSell', 'id', $requestedIds);
                \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_redirect('discounts.php', count($requestedIds), $deletedCount, '1');
            }
            elseif ($action === 'gift_bulk_delete') {
                $requestedIds = $_POST['ids'] ?? [];
                $deletedCount = \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_ids($pdo, 'Discount', 'id', $requestedIds);
                \Faoxima\Admin\Web\Support\BulkDelete::fx_bulk_delete_redirect('discounts.php', count($requestedIds), $deletedCount, '2');
            }
        }


        $df1 = \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_resolve('1');
        $df2 = \Faoxima\Admin\Web\Support\DateFilter::fx_date_filter_resolve('2');
        $discQ = \Faoxima\Admin\Web\Support\SearchFilter::fx_search_current('q1');
        $giftQ = \Faoxima\Admin\Web\Support\SearchFilter::fx_search_current('q2');
        $discDateKeep = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_date_params('1', $df1['active']);
        $giftDateKeep = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_date_params('2', $df2['active']);
        $discFilterActive = $discQ !== '' || $df1['active'];
        $giftFilterActive = $giftQ !== '' || $df2['active'];
        $discFilterCriteria = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_criteria('', $df1, $discQ);
        $giftFilterCriteria = \Faoxima\Admin\Web\Support\BulkDelete::fx_filter_delete_criteria('', $df2, $giftQ);

        $list = [];
        $pg1 = ['page' => 1, 'perPage' => 5, 'offset' => 0, 'total' => 0, 'pages' => 1];

        return true;
    }
}
