<?php


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}


if (!defined('FAOXIMA_SKIP_BOTAPI_ROUTER')) {
    define('FAOXIMA_SKIP_BOTAPI_ROUTER', true);
}
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/pagination.php';
require_once __DIR__ . '/lib/bulk_delete.php';
require_once __DIR__ . '/lib/date_filter.php';
require_once __DIR__ . '/lib/search_filter.php';
require_once __DIR__ . '/lib/compact_badges.php';
require_once __DIR__ . '/lib/csrf.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindValue(":username", $_SESSION["user"] ?? '', PDO::PARAM_STR);
$query->execute();
$adminRow = $query->fetch(PDO::FETCH_ASSOC);
if (!isset($_SESSION["user"]) || !$adminRow) {
    header('Location: login.php');
    exit;
}

fx_csrf_guard();
$_csrf = fx_csrf_token();

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
        $deletedCount = fx_bulk_delete_ids($pdo, 'DiscountSell', 'id', $requestedIds);
        fx_bulk_delete_redirect('discounts.php', count($requestedIds), $deletedCount, '1');
    }
    elseif ($action === 'gift_bulk_delete') {
        $requestedIds = $_POST['ids'] ?? [];
        $deletedCount = fx_bulk_delete_ids($pdo, 'Discount', 'id', $requestedIds);
        fx_bulk_delete_redirect('discounts.php', count($requestedIds), $deletedCount, '2');
    }
}


$df1 = fx_date_filter_resolve('1');
$df2 = fx_date_filter_resolve('2');
$discQ = fx_search_current('q1');
$giftQ = fx_search_current('q2');

$list = [];
$pg1 = ['page' => 1, 'perPage' => 5, 'offset' => 0, 'total' => 0, 'pages' => 1];
try {
    $where1 = '1=1';
    $params1 = [];
    if ($df1['active']) {
        $where1 .= ' AND `time` BETWEEN :dfrom1 AND :dto1';
        $params1[':dfrom1'] = $df1['from'];
        $params1[':dto1'] = $df1['to'];
    }
    if ($discQ !== '') {
        $where1 .= ' AND (codeDiscount LIKE :dq1 OR target_user LIKE :dq2)';
        $params1[':dq1'] = '%' . $discQ . '%';
        $params1[':dq2'] = '%' . $discQ . '%';
    }
    $pg1 = fx_paginate($pdo, "SELECT COUNT(*) FROM DiscountSell WHERE $where1", $params1, 5, 'p1');
    $r = $pdo->prepare("SELECT * FROM DiscountSell WHERE $where1 ORDER BY id DESC LIMIT :perPage OFFSET :offset");
    foreach ($params1 as $k => $v) $r->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $r->bindValue(':perPage', $pg1['perPage'], PDO::PARAM_INT);
    $r->bindValue(':offset', $pg1['offset'], PDO::PARAM_INT);
    $r->execute();
    $list = $r->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $flash['err'] = 'بارگذاری ناموفق: ' . $e->getMessage();
}

$giftList = [];
$pg2 = ['page' => 1, 'perPage' => 5, 'offset' => 0, 'total' => 0, 'pages' => 1];
try {
    $where2 = '1=1';
    $params2 = [];
    if ($df2['active']) {
        $where2 .= ' AND expire_at BETWEEN :dfrom2 AND :dto2';
        $params2[':dfrom2'] = $df2['from'];
        $params2[':dto2'] = $df2['to'];
    }
    if ($giftQ !== '') {
        $where2 .= ' AND (code LIKE :gq1 OR target_user LIKE :gq2)';
        $params2[':gq1'] = '%' . $giftQ . '%';
        $params2[':gq2'] = '%' . $giftQ . '%';
    }
    $pg2 = fx_paginate($pdo, "SELECT COUNT(*) FROM Discount WHERE $where2", $params2, 5, 'p2');
    $rg = $pdo->prepare("SELECT * FROM Discount WHERE $where2 ORDER BY id DESC LIMIT :perPage OFFSET :offset");
    foreach ($params2 as $k => $v) $rg->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $rg->bindValue(':perPage', $pg2['perPage'], PDO::PARAM_INT);
    $rg->bindValue(':offset', $pg2['offset'], PDO::PARAM_INT);
    $rg->execute();
    $giftList = $rg->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {  }

$listcategory = [];
try {
    $catQuery = $pdo->prepare("SELECT remark FROM category ORDER BY id ASC");
    $catQuery->execute();
    $listcategory = $catQuery->fetchAll(PDO::FETCH_COLUMN);
} catch (\Throwable $e) {  }

$listpanel = [];
$panelNameByCode = [];
try {
    $panQuery = $pdo->prepare("SELECT * FROM marzban_panel");
    $panQuery->execute();
    $listpanel = $panQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($listpanel as $p) {
        $panelNameByCode[(string)($p['code_panel'] ?? '')] = (string)($p['name_panel'] ?? '');
    }
} catch (\Throwable $e) {  }

function faoxima_d_label_targeting($mode, $category, $panelCode, $panelNameByCode) {
    if ($mode === 'category') {
        return ['🎯 دسته: ' . htmlspecialchars((string)$category, ENT_QUOTES), 'badge-purple'];
    }
    if ($mode === 'panel') {
        $codes = array_values(array_filter(array_map('trim', explode(',', (string)$panelCode))));
        $names = array_map(function ($c) use ($panelNameByCode) { return $panelNameByCode[$c] ?? $c; }, $codes);
        return ['🎯 پنل: ' . htmlspecialchars(implode('، ', $names), ENT_QUOTES), 'badge-info'];
    }
    return ['بدون محدودیت هدف', 'badge-gray'];
}

function faoxima_d_label_type($t) {
    switch ($t) {
        case 'percent': return ['درصدی', 'badge-success'];
        case 'amount':  return ['مبلغی', 'badge-info'];
        case 'free':    return ['رایگان', 'badge-purple'];
        default:        return [htmlspecialchars((string)$t, ENT_QUOTES), 'badge-gray'];
    }
}
function faoxima_d_label_agent($a) {
    $map = ['f' => 'کاربر عادی', 'n' => 'نماینده', 'n2' => 'نماینده+', 'all' => 'همه', 'allusers' => 'همه'];
    $values = array_values(array_filter(array_map('trim', explode(',', (string)$a))));
    if (empty($values) || in_array('allusers', $values, true) || in_array('all', $values, true)) {
        return ['همه', 'badge-success'];
    }
    $names = array_map(function ($v) use ($map) { return $map[$v] ?? $v; }, $values);
    return [htmlspecialchars(implode('، ', $names), ENT_QUOTES), count($values) > 1 ? 'badge-purple' : 'badge-info'];
}
function faoxima_d_label_section($s) {
    $map = ['buy' => 'خرید', 'extend' => 'تمدید', 'volume' => 'حجم', 'time' => 'زمان', 'charge' => 'شارژ', 'all' => 'همه بخش‌ها'];
    $values = array_values(array_filter(array_map('trim', explode(',', (string)$s))));
    if (empty($values) || in_array('all', $values, true)) {
        return ['همه بخش‌ها', 'badge-gray'];
    }
    $names = array_map(function ($v) use ($map) { return $map[$v] ?? $v; }, $values);
    return [htmlspecialchars(implode('، ', $names), ENT_QUOTES), count($values) > 1 ? 'badge-purple' : 'badge-warning'];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>کدهای تخفیف | پنل فاکسیما</title>
    <link rel="stylesheet" href="css/theme.css?v=flat47">
    <script src="js/money-input.js?v=fx1" defer></script>
    <script src="js/theme.js?v=flat5" defer>

</script>
</head>
<body>

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <svg class="svg-icon svg-lg" viewBox="0 0 511.998 511.998" aria-hidden="true"><g fill="currentColor" stroke="none"><path d="M179.34,262.92c-9.217,0-16.716,7.499-16.716,16.716s7.499,16.716,16.716,16.716c9.217,0,16.716-7.499,16.716-16.716C196.056,270.419,188.559,262.92,179.34,262.92z"/><path d="M379.934,262.92c-9.217,0-16.716,7.499-16.716,16.716s7.499,16.716,16.716,16.716c9.217,0,16.716-7.499,16.716-16.716C396.65,270.419,389.152,262.92,379.934,262.92z"/><path d="M474.505,354.619l22.789-22.805c19.596-19.573,19.616-51.325,0-70.919L291.456,55.058l-47.275,47.28c-6.529,6.529-17.107,6.53-23.638,0c-6.529-6.524-6.529-17.108,0-23.638l47.275-47.28l-16.716-16.717c-19.548-19.558-51.268-19.638-70.924,0.007l-22.811,22.794l-11.514-8.2C113.358,6.17,67.665,8.998,38.341,38.342C9.382,67.306,5.584,112.524,29.309,145.864l8.184,11.514l-22.789,22.805c-19.596,19.573-19.616,51.325,0,70.919l16.716,16.716l47.275-47.275c6.529-6.529,17.108-6.529,23.638,0c6.529,6.524,6.529,17.113,0,23.638l-47.275,47.275l205.839,205.839c19.548,19.559,51.268,19.639,70.924-0.006l22.811-22.8l11.525,8.228c32.14,22.971,77.86,20.592,107.501-9.06c28.959-28.965,32.758-74.183,9.032-107.523L474.505,354.619z M125.981,173.262l47.275-47.281c6.529-6.529,17.108-6.529,23.638,0c6.529,6.524,6.529,17.108,0,23.638l-47.275,47.281c-6.529,6.529-17.107,6.53-23.638,0C119.452,190.376,119.452,179.791,125.981,173.262z M179.34,329.785c-27.653,0-50.148-22.495-50.148-50.148s22.495-50.148,50.148-50.148s50.148,22.495,50.148,50.148S206.994,329.785,179.34,329.785z M296.353,396.649c0,9.234-7.488,16.716-16.716,16.716c-9.228,0-16.716-7.482-16.716-16.716V162.624c0-9.234,7.488-16.716,16.716-16.716c9.228,0,16.716,7.482,16.716,16.716V396.649z M379.934,329.785c-27.653,0-50.148-22.495-50.148-50.148s22.495-50.148,50.148-50.148s50.148,22.495,50.148,50.148S407.588,329.785,379.934,329.785z"/></g></svg>
                        کدهای تخفیف
                    </div>
                    <div class="page-head__sub">مدیریت کدهای تخفیف و هدیه</div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn btn-soft-success" onclick="openModal('modal-add-gift')">
                        <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                        <span>افزودن کد هدیه</span>
                    </button>
                    <button class="btn btn-primary" onclick="openModal('modal-add-discount')">
                        <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                        <span>افزودن کد تخفیف</span>
                    </button>
                </div>
            </div>

            <?php if ($flash['ok']): ?>
                <div class="alert" style="background:var(--color-success-soft); border:1px solid var(--color-success); color:var(--color-success); padding:12px 16px; border-radius:10px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                    <?php echo icon('circle-check', 'svg-icon'); ?>
                    <span><?php echo htmlspecialchars($flash['ok'], ENT_QUOTES); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flash['err']): ?>
                <div class="alert" style="background:var(--color-danger-soft); border:1px solid var(--color-danger); color:var(--color-danger); padding:12px 16px; border-radius:10px; margin-bottom:18px;">
                    <?php echo htmlspecialchars($flash['err'], ENT_QUOTES); ?>
                </div>
            <?php endif; ?>

            <?php echo fx_bulk_delete_flash_html('1', 'کدهای تخفیف'); ?>
            <?php echo fx_bulk_delete_flash_html('2', 'کدهای هدیه'); ?>

            <?php echo fx_search_ui('discounts.php', $discQ, ['p2' => $pg2['page'] > 1 ? $pg2['page'] : null], 'جستجو در کد تخفیف یا کاربر هدف…', 'q1'); ?>

            <?php echo fx_date_filter_ui('discounts.php', '1', ['p2' => $pg2['page'] > 1 ? $pg2['page'] : null, 'q1' => $discQ !== '' ? $discQ : null], 'انقضا'); ?>

            <div class="card">
                <div id="bulk-scope-1">
                <div class="table-wrap">
                    <table id="discountsTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all-1" onclick="faoximaToggleAll(this, 'bulk-scope-1')"></th>
                                <th>کد</th>
                                <th>نوع</th>
                                <th>مقدار</th>
                                <th>سقف کل</th>
                                <th>تعداد استفاده</th>
                                <th>گروه هدف</th>
                                <th>محدودیت</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($list)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:30px 0; color:var(--text-muted);">
                                هنوز کدی ثبت نشده است.
                            </td></tr>
                        <?php else: foreach ($list as $d):
                            $kind = $d['value_type'] ?? '';
                            if (!in_array($kind, ['percent','amount','free'], true)) {
                                $kind = in_array(($d['type'] ?? ''), ['percent','amount','free'], true) ? $d['type'] : 'percent';
                            }
                            [$typeLabel, $typeBadge] = faoxima_d_label_type($kind);
                            $sectionVal = $d['section'] ?? '';
                            if ($sectionVal === '') $sectionVal = in_array(($d['type'] ?? ''), ['buy','extend'], true) ? $d['type'] : 'all';
                            [$secLabel, $secBadge] = faoxima_d_label_section($sectionVal);
                            [$agentLabel, $agentBadge] = faoxima_d_label_agent($d['agent'] ?? '');
                            $valDisplay = htmlspecialchars((string)$d['price'], ENT_QUOTES);
                            if ($kind === 'percent') $valDisplay .= '%';
                            elseif ($kind === 'amount') $valDisplay = number_format((int)$d['price']) . ' <small>T</small>';
                            elseif ($kind === 'free') $valDisplay = 'رایگان';
                            $limit = (int)$d['limitDiscount'];
                            $used  = (int)$d['usedDiscount'];
                            $remaining = $limit > 0 ? max(0, $limit - $used) : -1;
                            $targetUser = trim((string)($d['target_user'] ?? ''));
                            $editAgent = in_array(($d['agent'] ?? ''), ['f','n','n2'], true) ? $d['agent'] : 'allusers';
                            $editExpTs = (int)($d['time'] ?? 0);
                            $editExpDays = ($editExpTs > time()) ? (int)ceil(($editExpTs - time()) / 86400) : 0;
                            $targetingMode = in_array(($d['targeting_mode'] ?? ''), ['category','panel'], true) ? $d['targeting_mode'] : 'none';
                            $codeCategoryVal = (string)($d['code_category'] ?? 'all');
                            [$targetingLabel, $targetingBadge] = faoxima_d_label_targeting($targetingMode, $codeCategoryVal, $d['code_panel'] ?? '', $panelNameByCode);
                        ?>
                            <tr data-detail-row data-detail-title="کد <?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?>">
                                <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo (int)$d['id']; ?>"></label></td>
                                <td data-label="کد" data-summary="1"><code style="direction:ltr; background:var(--accent-soft); color:var(--accent); padding:4px 8px; border-radius:6px; font-weight:700;"><?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?></code></td>
                                <td data-label="نوع" data-summary="1">
                                    <?php
                                        $discountTypeBadgesHtml = [
                                            '<span class="badge ' . $typeBadge . '">' . $typeLabel . '</span>',
                                            '<span class="badge ' . $secBadge . '">' . $secLabel . '</span>',
                                        ];
                                        if ($targetUser !== '') {
                                            $discountTypeBadgesHtml[] = '<span class="badge badge-warning">کاربر ' . htmlspecialchars($targetUser, ENT_QUOTES) . '</span>';
                                        }
                                        if ($targetingMode !== 'none') {
                                            $discountTypeBadgesHtml[] = '<span class="badge ' . $targetingBadge . '">' . $targetingLabel . '</span>';
                                        }
                                        echo faoxima_render_compact_badges($discountTypeBadgesHtml);
                                    ?>
                                </td>
                                <td data-label="مقدار" data-summary="1"><?php echo $valDisplay; ?></td>
                                <td data-label="سقف کل"><?php echo $limit > 0 ? $limit : '<span class="text-muted">نامحدود</span>'; ?></td>
                                <td data-label="تعداد استفاده">
                                    <b style="color:<?php echo ($remaining === 0) ? 'var(--color-danger)' : 'var(--color-success)'; ?>;"><?php echo $used; ?></b>
                                    <?php if ($limit > 0): ?>/ <?php echo $limit; ?><?php endif; ?>
                                </td>
                                <td data-label="گروه هدف"><span class="badge <?php echo $agentBadge; ?>"><?php echo $agentLabel; ?></span></td>
                                <td data-label="محدودیت">
                                    <?php if ($d['usefirst'] == '1'): ?>
                                        <span class="badge badge-warning">فقط خرید اول</span>
                                    <?php endif; ?>
                                    <?php if ($d['useuser'] == '1'): ?>
                                        <span class="badge badge-info">یک بار/کاربر</span>
                                    <?php endif; ?>
                                    <?php if ($d['usefirst'] != '1' && $d['useuser'] != '1'): ?>
                                        <span class="text-muted">نامحدود</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="عملیات" class="cell-actions">
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                        <button type="button" class="btn btn-sm btn-soft-info" title="ویرایش"
                                            data-id="<?php echo (int)$d['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?>"
                                            data-vt="<?php echo htmlspecialchars($kind, ENT_QUOTES); ?>"
                                            data-section="<?php echo htmlspecialchars($sectionVal, ENT_QUOTES); ?>"
                                            data-price="<?php echo htmlspecialchars((string)$d['price'], ENT_QUOTES); ?>"
                                            data-limit="<?php echo (int)$d['limitDiscount']; ?>"
                                            data-agent="<?php echo htmlspecialchars($editAgent, ENT_QUOTES); ?>"
                                            data-first="<?php echo $d['usefirst'] == '1' ? '1' : '0'; ?>"
                                            data-useuser="<?php echo $d['useuser'] == '1' ? '1' : '0'; ?>"
                                            data-target="<?php echo htmlspecialchars($targetUser, ENT_QUOTES); ?>"
                                            data-exp="<?php echo (int)$editExpDays; ?>"
                                            data-targeting-mode="<?php echo htmlspecialchars($targetingMode, ENT_QUOTES); ?>"
                                            data-category="<?php echo htmlspecialchars($codeCategoryVal === 'all' ? '' : $codeCategoryVal, ENT_QUOTES); ?>"
                                            data-panel="<?php echo htmlspecialchars((string)($d['code_panel'] ?? ''), ENT_QUOTES); ?>"
                                            onclick="editDiscount(this)">
                                            <?php echo icon('pen-to-square', 'svg-icon'); ?>
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('شمارنده استفاده این کد صفر شود؟');">
                                            <?php echo fx_csrf_field(); ?>
                                            <input type="hidden" name="_action" value="reset_usage">
                                            <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-warning" title="بازنشانی شمارنده استفاده">
                                                <?php echo icon('rotate-left', 'svg-icon'); ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('کد <?php echo htmlspecialchars($d['codeDiscount'], ENT_QUOTES); ?> حذف شود؟');">
                                            <?php echo fx_csrf_field(); ?>
                                            <input type="hidden" name="_action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                                <?php echo icon('trash', 'svg-icon'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <?php
                    $d1Keep = ['p2' => $pg2['page'] > 1 ? $pg2['page'] : null, 'q1' => $discQ !== '' ? $discQ : null, 'q2' => $giftQ !== '' ? $giftQ : null];
                    foreach (['dpreset1', 'ddays1', 'dfrom1', 'dto1', 'dpreset2', 'ddays2', 'dfrom2', 'dto2'] as $dk) {
                        if (isset($_GET[$dk]) && $_GET[$dk] !== '') $d1Keep[$dk] = $_GET[$dk];
                    }
                    echo fx_pager_html($pg1['page'], $pg1['pages'], $pg1['total'], count($list), 'discounts.php', $d1Keep, 'p1');
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="button" class="btn btn-soft-danger btn-sm js-bulk-delete-btn js-bulk-delete-btn-1" style="display:none;" onclick="faoximaBulkDelete('bulk-scope-1', 'bulk_delete', '_action')">
                        <?php echo icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                    </button>
                </div>
                </div>
            </div>

            <?php echo fx_search_ui('discounts.php', $giftQ, ['p1' => $pg1['page'] > 1 ? $pg1['page'] : null], 'جستجو در کد هدیه یا کاربر هدف…', 'q2'); ?>

            <?php echo fx_date_filter_ui('discounts.php', '2', ['p1' => $pg1['page'] > 1 ? $pg1['page'] : null, 'q2' => $giftQ !== '' ? $giftQ : null], 'انقضا'); ?>

            <div class="card" style="margin-top:18px">
                <div style="padding:14px 16px; font-weight:700; display:flex; align-items:center; gap:8px;">
                    <?php echo icon('gift', 'svg-icon'); ?> کدهای هدیه (شارژ کیف پول)
                </div>
                <div id="bulk-scope-2">
                <div class="table-wrap">
                    <table id="giftsTable" class="display app-table app-table--summary" style="width:100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="check-all-2" onclick="faoximaToggleAll(this, 'bulk-scope-2')"></th>
                                <th>کد</th>
                                <th>مبلغ</th>
                                <th>سقف کل</th>
                                <th>تعداد استفاده</th>
                                <th>اختصاصی</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($giftList)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px 0; color:var(--text-muted);">هنوز کد هدیه‌ای ثبت نشده است.</td></tr>
                        <?php else: foreach ($giftList as $g):
                            $glimit = (int)($g['limituse'] ?? 0);
                            $gused  = (int)($g['limitused'] ?? 0);
                            $gtarget = trim((string)($g['target_user'] ?? ''));
                            $gEditExpTs = (int)($g['expire_at'] ?? 0);
                            $gEditExpDays = ($gEditExpTs > time()) ? (int)ceil(($gEditExpTs - time()) / 86400) : 0;
                        ?>
                            <tr data-detail-row data-detail-title="کد هدیه <?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?>">
                                <td><label class="fx-check-row"><input type="checkbox" name="ids[]" value="<?php echo (int)$g['id']; ?>"></label></td>
                                <td data-label="کد" data-summary="1"><code style="direction:ltr; background:var(--accent-soft); color:var(--accent); padding:4px 8px; border-radius:6px; font-weight:700;"><?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?></code></td>
                                <td data-label="مبلغ" data-summary="1"><?php echo number_format((int)($g['price'] ?? 0)); ?> <small>T</small></td>
                                <td data-label="سقف کل"><?php echo $glimit > 0 ? $glimit : '<span class="text-muted">نامحدود</span>'; ?></td>
                                <td data-label="تعداد استفاده"><?php echo $gused; ?><?php if ($glimit > 0): ?> / <?php echo $glimit; ?><?php endif; ?></td>
                                <td data-label="اختصاصی" data-summary="1"><?php echo $gtarget !== '' ? '<span class="badge badge-warning">کاربر ' . htmlspecialchars($gtarget, ENT_QUOTES) . '</span>' : '<span class="text-muted">عمومی</span>'; ?></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                        <button type="button" class="btn btn-sm btn-soft-info" title="ویرایش"
                                            data-id="<?php echo (int)$g['id']; ?>"
                                            data-code="<?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?>"
                                            data-price="<?php echo (int)($g['price'] ?? 0); ?>"
                                            data-limit="<?php echo (int)($g['limituse'] ?? 0); ?>"
                                            data-target="<?php echo htmlspecialchars($gtarget, ENT_QUOTES); ?>"
                                            data-exp="<?php echo (int)$gEditExpDays; ?>"
                                            onclick="editGift(this)">
                                            <?php echo icon('pen-to-square', 'svg-icon'); ?>
                                        </button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('کد هدیه حذف شود؟');">
                                            <?php echo fx_csrf_field(); ?>
                                            <input type="hidden" name="_action" value="gift_delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo icon('trash', 'svg-icon'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <?php
                    $d2Keep = ['p1' => $pg1['page'] > 1 ? $pg1['page'] : null, 'q1' => $discQ !== '' ? $discQ : null, 'q2' => $giftQ !== '' ? $giftQ : null];
                    foreach (['dpreset1', 'ddays1', 'dfrom1', 'dto1', 'dpreset2', 'ddays2', 'dfrom2', 'dto2'] as $dk) {
                        if (isset($_GET[$dk]) && $_GET[$dk] !== '') $d2Keep[$dk] = $_GET[$dk];
                    }
                    echo fx_pager_html($pg2['page'], $pg2['pages'], $pg2['total'], count($giftList), 'discounts.php', $d2Keep, 'p2');
                    ?>
                </div>
                <div style="padding:12px 0;">
                    <button type="button" class="btn btn-soft-danger btn-sm js-bulk-delete-btn js-bulk-delete-btn-2" style="display:none;" onclick="faoximaBulkDelete('bulk-scope-2', 'gift_bulk_delete', '_action')">
                        <?php echo icon('trash', 'svg-icon'); ?> حذف انتخاب‌شده‌ها
                    </button>
                </div>
                </div>
            </div>


<div id="modal-add-discount" class="modal-overlay">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن کد تخفیف جدید</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-discount')">&times;</button>
        </div>
        <form method="POST" action="discounts.php">
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="_action" value="add">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">کد تخفیف</label>
                    <input type="text" name="codeDiscount" class="form-control" style="direction:ltr; font-family:'JetBrains Mono', monospace;" placeholder="FAOXIMA20" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع تخفیف</label>
                    <select name="type" class="form-control" required onchange="updateValueHint(this.value)">
                        <option value="percent">درصدی (٪)</option>
                        <option value="amount">مبلغی (T)</option>
                        <option value="free">رایگان (۱۰۰٪)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" id="valueLabel">مقدار تخفیف (٪)</label>
                    <input type="text" name="price" id="add_price" class="form-control" placeholder="20" required min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                    <input type="number" name="limitDiscount" class="form-control" placeholder="100" value="0" min="0">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">انقضا (روز) <small style="color:var(--text-muted)">(۰ = بدون انقضا)</small></label>
                <input type="number" name="expire_days" class="form-control" placeholder="0" value="0" min="0">
            </div>

            <div class="form-group">
                <label class="form-label">بخش کد <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" data-section-picker>
                    <?php foreach ($discountSectionOptions as $secVal => $secLabel): ?>
                    <button type="button" class="chip" data-section-chip data-value="<?php echo htmlspecialchars($secVal, ENT_QUOTES); ?>">
                        <span><?php echo htmlspecialchars($secLabel, ENT_QUOTES); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="section_csv" data-section-hidden value="all">
            </div>

            <div class="form-group">
                <label class="form-label">نحوه هدف‌گیری</label>
                <select name="targeting_mode" id="targeting_mode" class="form-control" onchange="faoximaToggleTargeting(this.value)">
                    <option value="none">بدون محدودیت (همه محصولات/پنل‌ها)</option>
                    <option value="category">بر اساس دسته‌بندی</option>
                    <option value="panel">بر اساس پنل مشخص</option>
                </select>
            </div>
            <div class="form-group" id="targeting_category_wrap" style="display:none;">
                <label class="form-label">دسته‌بندی‌ها <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" data-category-picker>
                    <?php foreach ($listcategory as $catName): ?>
                        <button type="button" class="chip" data-category-chip data-value="<?php echo htmlspecialchars((string)$catName, ENT_QUOTES); ?>">
                            <span><?php echo htmlspecialchars((string)$catName, ENT_QUOTES); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="code_category_csv" data-category-hidden value="">
            </div>
            <div class="form-group" id="targeting_panel_wrap" style="display:none;">
                <label class="form-label">پنل‌ها <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" data-panel-picker>
                    <?php foreach ($listpanel as $p): ?>
                        <button type="button" class="chip" data-panel-chip data-value="<?php echo htmlspecialchars((string)($p['code_panel'] ?? ''), ENT_QUOTES); ?>">
                            <span><?php echo htmlspecialchars((string)($p['name_panel'] ?? ''), ENT_QUOTES); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="code_panel_csv" data-panel-hidden value="">
            </div>

            <div class="form-group">
                <label class="form-label">گروه هدف <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" data-agent-picker>
                    <?php foreach ($discountAgentOptions as $agVal => $agLabel): ?>
                    <button type="button" class="chip" data-agent-chip data-value="<?php echo htmlspecialchars($agVal, ENT_QUOTES); ?>">
                        <span><?php echo htmlspecialchars($agLabel, ENT_QUOTES); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="agent_csv" data-agent-hidden value="allusers">
            </div>

            <div class="form-group">
                <label class="form-label">کد اختصاصی کاربر <small style="color:var(--text-muted)">(آیدی عددی، اختیاری)</small></label>
                <input type="text" name="target_user" class="form-control" style="direction:ltr" placeholder="123456789">
            </div>

            <div class="form-group" style="display:flex; flex-direction:column; gap:8px;">
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="usefirst" value="1">
                    فقط برای خرید اول کاربر قابل استفاده باشد
                </label>
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="useuser" value="1">
                    هر کاربر فقط یک بار بتواند استفاده کند
                </label>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-discount')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <?php echo icon('plus', 'svg-icon svg-sm'); ?>
                    افزودن کد
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modal-add-gift" class="modal-overlay">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن کد هدیه</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-gift')">&times;</button>
        </div>
        <form method="POST" action="discounts.php">
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="_action" value="gift_add">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">کد هدیه</label>
                    <input type="text" name="code" class="form-control" style="direction:ltr;" placeholder="GIFT50" required>
                </div>
                <div class="form-group">
                    <label class="form-label">مبلغ (T)</label>
                    <input type="text" name="gift_price" class="form-control" data-money placeholder="50000" required min="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                    <input type="number" name="gift_limit" class="form-control" value="0" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">انقضا (روز) <small style="color:var(--text-muted)">(۰ = بدون انقضا)</small></label>
                    <input type="number" name="gift_expire_days" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">کد اختصاصی کاربر <small style="color:var(--text-muted)">(آیدی عددی، اختیاری)</small></label>
                <input type="text" name="gift_target" class="form-control" style="direction:ltr" placeholder="123456789">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-add-gift')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm"><?php echo icon('plus', 'svg-icon svg-sm'); ?> افزودن</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-discount" class="modal-overlay">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش کد تخفیف</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-discount')">&times;</button>
        </div>
        <form method="POST" action="discounts.php">
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="_action" value="edit">
            <input type="hidden" name="id" id="ed_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">کد تخفیف</label>
                    <input type="text" name="codeDiscount" id="ed_code" class="form-control" style="direction:ltr; font-family:'JetBrains Mono', monospace;" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع تخفیف</label>
                    <select name="type" id="ed_type" class="form-control" required onchange="updateEditValueHint(this.value)">
                        <option value="percent">درصدی (٪)</option>
                        <option value="amount">مبلغی (T)</option>
                        <option value="free">رایگان (۱۰۰٪)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" id="ed_valueLabel">مقدار تخفیف (٪)</label>
                    <input type="text" name="price" id="ed_price" class="form-control" required min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                    <input type="number" name="limitDiscount" id="ed_limit" class="form-control" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">انقضا (روز) <small style="color:var(--text-muted)">(۰ = بدون انقضا)</small></label>
                <input type="number" name="expire_days" id="ed_exp" class="form-control" min="0">
            </div>

            <div class="form-group">
                <label class="form-label">بخش کد <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="ed_section_picker" data-section-picker>
                    <?php foreach ($discountSectionOptions as $secVal => $secLabel): ?>
                    <button type="button" class="chip" data-section-chip data-value="<?php echo htmlspecialchars($secVal, ENT_QUOTES); ?>">
                        <span><?php echo htmlspecialchars($secLabel, ENT_QUOTES); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="section_csv" id="ed_section_csv" data-section-hidden value="all">
            </div>

            <div class="form-group">
                <label class="form-label">نحوه هدف‌گیری</label>
                <select name="targeting_mode" id="ed_targeting_mode" class="form-control" onchange="faoximaToggleEditTargeting(this.value)">
                    <option value="none">بدون محدودیت (همه محصولات/پنل‌ها)</option>
                    <option value="category">بر اساس دسته‌بندی</option>
                    <option value="panel">بر اساس پنل مشخص</option>
                </select>
            </div>
            <div class="form-group" id="ed_targeting_category_wrap" style="display:none;">
                <label class="form-label">دسته‌بندی‌ها <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="ed_category_picker" data-category-picker>
                    <?php foreach ($listcategory as $catName): ?>
                        <button type="button" class="chip" data-category-chip data-value="<?php echo htmlspecialchars((string)$catName, ENT_QUOTES); ?>">
                            <span><?php echo htmlspecialchars((string)$catName, ENT_QUOTES); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="code_category_csv" id="ed_code_category" data-category-hidden value="">
            </div>
            <div class="form-group" id="ed_targeting_panel_wrap" style="display:none;">
                <label class="form-label">پنل‌ها <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="ed_panel_picker" data-panel-picker>
                    <?php foreach ($listpanel as $p): ?>
                        <button type="button" class="chip" data-panel-chip data-value="<?php echo htmlspecialchars((string)($p['code_panel'] ?? ''), ENT_QUOTES); ?>">
                            <span><?php echo htmlspecialchars((string)($p['name_panel'] ?? ''), ENT_QUOTES); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="code_panel_csv" id="ed_code_panel_csv" data-panel-hidden value="">
            </div>
            <div class="form-group">
                <label class="form-label">گروه هدف <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                <div class="chip-row" id="ed_agent_picker" data-agent-picker>
                    <?php foreach ($discountAgentOptions as $agVal => $agLabel): ?>
                    <button type="button" class="chip" data-agent-chip data-value="<?php echo htmlspecialchars($agVal, ENT_QUOTES); ?>">
                        <span><?php echo htmlspecialchars($agLabel, ENT_QUOTES); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="agent_csv" id="ed_agent_csv" data-agent-hidden value="allusers">
            </div>

            <div class="form-group">
                <label class="form-label">کد اختصاصی کاربر <small style="color:var(--text-muted)">(آیدی عددی، اختیاری)</small></label>
                <input type="text" name="target_user" id="ed_target" class="form-control" style="direction:ltr">
            </div>
            <div class="form-group" style="display:flex; flex-direction:column; gap:8px;">
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="usefirst" id="ed_first" value="1">
                    فقط برای خرید اول کاربر قابل استفاده باشد
                </label>
                <label style="display:flex; align-items:center; gap:10px; font-size:13px; cursor:pointer;">
                    <input type="checkbox" name="useuser" id="ed_useuser" value="1">
                    هر کاربر فقط یک بار بتواند استفاده کند
                </label>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-edit-discount')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm"><?php echo icon('pen-to-square', 'svg-icon svg-sm'); ?> ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-edit-gift" class="modal-overlay">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش کد هدیه</span>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-gift')">&times;</button>
        </div>
        <form method="POST" action="discounts.php">
            <?php echo fx_csrf_field(); ?>
            <input type="hidden" name="_action" value="gift_edit">
            <input type="hidden" name="id" id="ed_g_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">کد هدیه</label>
                    <input type="text" name="code" id="ed_g_code" class="form-control" style="direction:ltr;" required>
                </div>
                <div class="form-group">
                    <label class="form-label">مبلغ (T)</label>
                    <input type="text" name="gift_price" id="ed_g_price" class="form-control" data-money required min="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">سقف کل استفاده <small style="color:var(--text-muted)">(۰ = نامحدود)</small></label>
                    <input type="number" name="gift_limit" id="ed_g_limit" class="form-control" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">انقضا (روز) <small style="color:var(--text-muted)">(۰ = بدون انقضا)</small></label>
                    <input type="number" name="gift_expire_days" id="ed_g_exp" class="form-control" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">کد اختصاصی کاربر <small style="color:var(--text-muted)">(آیدی عددی، اختیاری)</small></label>
                <input type="text" name="gift_target" id="ed_g_target" class="form-control" style="direction:ltr">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-edit-gift')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm"><?php echo icon('pen-to-square', 'svg-icon svg-sm'); ?> ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>

<script src="js/bulk-select.js?v=fx2"></script>
<script src="js/compact-badges.js?v=fx1"></script>
<script>
function faoximaToggleAll(master, scopeId) {
    var scope = scopeId ? document.getElementById(scopeId) : document;
    scope.querySelectorAll('input[name="ids[]"]:not(:disabled)').forEach(function(cb) {
        if (cb.checked === master.checked) return;
        cb.checked = master.checked;
        cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
}
var fxBulkHandle1 = FxBulkSelect.init({
    scope: '1',
    checkboxSelector: 'input[name="ids[]"]',
    scopeRoot: document.getElementById('bulk-scope-1'),
    formEl: null,
    deleteButtonSelector: '.js-bulk-delete-btn-1',
    clearOnQueryFlags: ['bulk1']
});
var fxBulkHandle2 = FxBulkSelect.init({
    scope: '2',
    checkboxSelector: 'input[name="ids[]"]',
    scopeRoot: document.getElementById('bulk-scope-2'),
    formEl: null,
    deleteButtonSelector: '.js-bulk-delete-btn-2',
    clearOnQueryFlags: ['bulk2']
});
function faoximaBulkDelete(scopeId, actionValue, actionField) {
    var scope = document.getElementById(scopeId);
    var handle = scopeId === 'bulk-scope-1' ? fxBulkHandle1 : fxBulkHandle2;
    var idSet = {};
    Array.prototype.forEach.call(scope.querySelectorAll('input[name="ids[]"]:checked'), function (cb) {
        idSet[cb.value] = true;
    });
    FxBulkSelect.getPersistedIds(handle.key).forEach(function (v) {
        idSet[v] = true;
    });
    var ids = Object.keys(idSet);
    if (ids.length === 0) return;
    if (!confirm('آیا از حذف موارد انتخاب‌شده مطمئن هستید؟')) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'discounts.php';
    var actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = actionField;
    actionInput.value = actionValue;
    form.appendChild(actionInput);
    ids.forEach(function(val) {
        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'ids[]';
        idInput.value = val;
        form.appendChild(idInput);
    });
    document.body.appendChild(form);
    form.submit();
}
function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    if (typeof window.closeDetailSheet === 'function') window.closeDetailSheet();
    m.classList.add('active');
    if (id === 'modal-add-discount') {
        m.querySelector('form')?.reset();
        var picker = document.querySelector('#targeting_category_wrap [data-category-picker]');
        if (picker) faoximaCategoryPickerSetSelected(picker, []);
        faoximaToggleTargeting('none');
        updateValueHint('percent');
        faoximaSectionPickerSetSelected(m.querySelector('[data-section-picker]'), ['all']);
        faoximaAgentPickerSetSelected(m.querySelector('[data-agent-picker]'), ['allusers']);
    }
}
function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('active'); }

function updateValueHint(type) {
    var lbl = document.getElementById('valueLabel');
    if (type === 'percent') lbl.textContent = 'مقدار تخفیف (٪)';
    else if (type === 'amount') lbl.textContent = 'مقدار تخفیف (T)';
    else lbl.textContent = 'مقدار (استفاده‌نمی‌شود)';
    if (window.FaoximaMoneyInput) {
        window.FaoximaMoneyInput.setMode(document.getElementById('add_price'), type === 'amount');
    }
}

function updateEditValueHint(type) {
    var lbl = document.getElementById('ed_valueLabel');
    if (!lbl) return;
    if (type === 'percent') lbl.textContent = 'مقدار تخفیف (٪)';
    else if (type === 'amount') lbl.textContent = 'مقدار تخفیف (T)';
    else lbl.textContent = 'مقدار (استفاده‌نمی‌شود)';
    if (window.FaoximaMoneyInput) {
        window.FaoximaMoneyInput.setMode(document.getElementById('ed_price'), type === 'amount');
    }
}

function faoximaToggleTargeting(mode) {
    document.getElementById('targeting_category_wrap').style.display = (mode === 'category') ? '' : 'none';
    document.getElementById('targeting_panel_wrap').style.display = (mode === 'panel') ? '' : 'none';
}
function faoximaToggleEditTargeting(mode) {
    document.getElementById('ed_targeting_category_wrap').style.display = (mode === 'category') ? '' : 'none';
    document.getElementById('ed_targeting_panel_wrap').style.display = (mode === 'panel') ? '' : 'none';
}

function faoximaChipPickerSync(picker, hiddenAttr, chipAttr) {
    var hidden = picker.parentElement.querySelector('[' + hiddenAttr + ']');
    if (!hidden) return;
    var values = Array.from(picker.querySelectorAll('.chip.is-active')).map(function (c) { return c.getAttribute('data-value'); });
    hidden.value = values.join(',');
}
function faoximaChipPickerSetSelected(picker, values, chipAttr, hiddenAttr) {
    var set = values.filter(function (v) { return v !== ''; });
    picker.querySelectorAll('.chip[' + chipAttr + ']').forEach(function (chip) {
        chip.classList.toggle('is-active', set.indexOf(chip.getAttribute('data-value')) !== -1);
    });
    faoximaChipPickerSync(picker, hiddenAttr, chipAttr);
}
function faoximaCategoryPickerSync(picker) {
    faoximaChipPickerSync(picker, 'data-category-hidden', 'data-category-chip');
}
function faoximaCategoryPickerSetSelected(picker, values) {
    faoximaChipPickerSetSelected(picker, values, 'data-category-chip', 'data-category-hidden');
}
function faoximaPanelPickerSync(picker) {
    faoximaChipPickerSync(picker, 'data-panel-hidden', 'data-panel-chip');
}
function faoximaPanelPickerSetSelected(picker, values) {
    faoximaChipPickerSetSelected(picker, values, 'data-panel-chip', 'data-panel-hidden');
}
function faoximaSectionPickerSync(picker) {
    faoximaChipPickerSync(picker, 'data-section-hidden', 'data-section-chip');
}
function faoximaSectionPickerSetSelected(picker, values) {
    faoximaChipPickerSetSelected(picker, values, 'data-section-chip', 'data-section-hidden');
}
function faoximaAgentPickerSync(picker) {
    faoximaChipPickerSync(picker, 'data-agent-hidden', 'data-agent-chip');
}
function faoximaAgentPickerSetSelected(picker, values) {
    faoximaChipPickerSetSelected(picker, values, 'data-agent-chip', 'data-agent-hidden');
}
function faoximaBindChipPicker(pickerRootSelector, chipSelector, syncFn) {
    document.querySelectorAll(pickerRootSelector).forEach(function (picker) {
        picker.addEventListener('click', function (ev) {
            var chip = ev.target.closest(chipSelector);
            if (!chip || !picker.contains(chip)) return;
            chip.classList.toggle('is-active');
            syncFn(picker);
        });
    });
}
faoximaBindChipPicker('[data-category-picker]', '.chip[data-category-chip]', faoximaCategoryPickerSync);
faoximaBindChipPicker('[data-panel-picker]', '.chip[data-panel-chip]', faoximaPanelPickerSync);
faoximaBindChipPicker('[data-section-picker]', '.chip[data-section-chip]', faoximaSectionPickerSync);
faoximaBindChipPicker('[data-agent-picker]', '.chip[data-agent-chip]', faoximaAgentPickerSync);

function editDiscount(btn) {
    document.getElementById('ed_id').value      = btn.getAttribute('data-id');
    document.getElementById('ed_code').value    = btn.getAttribute('data-code');
    document.getElementById('ed_type').value    = btn.getAttribute('data-vt');
    document.getElementById('ed_price').value   = btn.getAttribute('data-price');
    document.getElementById('ed_limit').value   = btn.getAttribute('data-limit');
    document.getElementById('ed_exp').value      = btn.getAttribute('data-exp');
    document.getElementById('ed_target').value  = btn.getAttribute('data-target');
    document.getElementById('ed_first').checked   = btn.getAttribute('data-first') === '1';
    document.getElementById('ed_useuser').checked = btn.getAttribute('data-useuser') === '1';
    updateEditValueHint(btn.getAttribute('data-vt'));

    var targetingMode = btn.getAttribute('data-targeting-mode') || 'none';
    document.getElementById('ed_targeting_mode').value = targetingMode;
    var categoryStr = btn.getAttribute('data-category') || '';
    var categoryArr = categoryStr.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    faoximaCategoryPickerSetSelected(document.getElementById('ed_category_picker'), categoryArr);
    var panelStr = btn.getAttribute('data-panel') || '';
    var panelArr = panelStr.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    faoximaPanelPickerSetSelected(document.getElementById('ed_panel_picker'), panelArr);
    faoximaToggleEditTargeting(targetingMode);

    var sectionStr = btn.getAttribute('data-section') || 'all';
    var sectionArr = sectionStr.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    faoximaSectionPickerSetSelected(document.getElementById('ed_section_picker'), sectionArr);

    var agentStr = btn.getAttribute('data-agent') || 'allusers';
    var agentArr = agentStr.split(',').map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
    faoximaAgentPickerSetSelected(document.getElementById('ed_agent_picker'), agentArr);

    openModal('modal-edit-discount');
}
function editGift(btn) {
    document.getElementById('ed_g_id').value     = btn.getAttribute('data-id');
    document.getElementById('ed_g_code').value   = btn.getAttribute('data-code');
    document.getElementById('ed_g_price').value  = window.FaoximaMoneyInput ? window.FaoximaMoneyInput.format(btn.getAttribute('data-price')) : btn.getAttribute('data-price');
    document.getElementById('ed_g_limit').value  = btn.getAttribute('data-limit');
    document.getElementById('ed_g_target').value = btn.getAttribute('data-target');
    document.getElementById('ed_g_exp').value    = btn.getAttribute('data-exp');
    openModal('modal-edit-gift');
}
</script>
</body>
</html>


