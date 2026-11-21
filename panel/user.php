<?php

require_once __DIR__ . '/../src/bootstrap.php';
$adminWebPage = 'user.php';

// legacy runtime bootstrap (global scope): session settings, config.php, ad_boot.php and library loads
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
if (!@include_once __DIR__ . '/lib/ad_boot.php') { http_response_code(503); header('Retry-After: 3600'); exit; }
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';

if (\Faoxima\Admin\Web\Pages\UserRequest::handle(
        _csrf: $_csrf,
        act: $act,
        adminnumber: $adminnumber ?? null,
        agent_display: $agent_display,
        number_display: $number_display,
        otherservice: $otherservice,
        paymentreports: $paymentreports,
        pdo: $pdo ?? null,
        query: $query,
        section: $section,
        setting: $setting,
        st: $st,
        status: $status,
        status_class: $status_class,
        status_label: $status_label,
        user: $user,
        userCodes: $userCodes,
        userGiftCodes: $userGiftCodes,
        value: $value
    ) !== true) {
    unset($adminWebPage);
    return;
}

unset($adminWebPage);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت کاربر <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="css/theme.css?v=identity104">
<script src="js/money-input.js?v=fx1" defer></script>
<script src="js/theme.js?v=dark62" defer>

</script>
</head>
<body class="fx-user-detail">

<section id="container">
    <?php include("header.php"); ?>

    <section id="main-content">
        <div class="wrapper fx-page-user-detail">

            <div class="page-head">
                <div>
                    <div class="page-head__title">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('user', 'svg-icon svg-lg'); ?>
                        پروفایل کاربر
                    </div>
                    <div class="page-head__sub">
                        <a href="users.php" class="text-link">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('arrow-right', 'svg-icon'); ?> بازگشت به لیست
                        </a>
                    </div>
                </div>
            </div>

            <div class="profile-card">
                <div class="avatar-circle">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('user', 'svg-icon'); ?>
                </div>
                <div class="profile-identity">
                    <p>شناسه عددی: <bdi><?php echo htmlspecialchars((string)$user['id'], ENT_QUOTES, 'UTF-8'); ?></bdi></p>
                </div>
                <a href="https://t.me/<?php echo urlencode($user['username']); ?>" target="_blank" rel="noopener noreferrer" class="tg-btn" aria-label="مشاهدهٔ کاربر در تلگرام">
                    <?php echo \Faoxima\Admin\Web\Support\Icons::icon('paper-plane', 'svg-icon'); ?>
                    <bdi><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></bdi>
                </a>
                <span class="badge profile-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
            </div>

            <div class="info-grid">
                <div class="card">
                    <div class="card__head">
                        <div class="card__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('circle-info', 'svg-icon svg-md'); ?><span>مشخصات حساب</span></div>
                    </div>
                    <div class="info-row">
                        <span class="info-label">وضعیت حساب</span>
                        <span class="info-value"><span class="badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">موجودی کیف پول</span>
                        <span class="info-value text-accent"><?php echo number_format($user['Balance']); ?> <small class="currency-unit">تومان</small></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">شماره موبایل</span>
                        <span class="info-value"><?php echo $number_display; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">نوع کاربری</span>
                        <span class="info-value"><?php echo $agent_display; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">درصد تخفیف اختصاصی</span>
                        <span class="info-value text-accent"><?php echo (int)($user['pricediscount'] ?? 0); ?>٪</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card__head">
                        <div class="card__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('chart-line', 'svg-icon svg-md'); ?><span>آمار فعالیت</span></div>
                    </div>
                    <div class="info-row">
                        <span class="info-label">تعداد زیرمجموعه</span>
                        <span class="info-value"><?php echo (int)$user['affiliatescount']; ?> نفر</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">معرف (بالاسری)</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['affiliates'] ?: '---', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">محدودیت تست</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['limit_usertest'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>

            <div class="card user-management">
                <div class="card__head">
                    <div class="card__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('sliders', 'svg-icon svg-md'); ?><span>عملیات مدیریت</span></div>
                </div>
                <div class="actions-grid">
                    <?php if ($user['User_Status'] == 'block'): ?>
                        <form method="POST" action="user.php?id=<?php echo urlencode((string)$user['id']); ?>" style="margin:0;">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="_action" value="user_status">
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-soft-success btn-block">
                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('check', 'svg-icon'); ?> رفع مسدودی
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="user.php?id=<?php echo urlencode((string)$user['id']); ?>" style="margin:0;" onsubmit="return confirm('کاربر مسدود شود؟')">
                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="_action" value="user_status">
                            <input type="hidden" name="status" value="block">
                            <button type="submit" class="btn btn-soft-danger btn-block">
                                <?php echo \Faoxima\Admin\Web\Support\Icons::icon('ban', 'svg-icon'); ?> مسدود کردن
                            </button>
                        </form>
                    <?php endif; ?>

                    <button onclick="openModal('modal-add-balance')" class="btn btn-soft-info btn-block">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon'); ?> افزایش موجودی
                    </button>

                    <button onclick="openModal('modal-low-balance')" class="btn btn-soft-warning btn-block">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('minus', 'svg-icon'); ?> کسر موجودی
                    </button>

                    <button onclick="openModal('modal-change-agent')" class="btn btn-soft-purple btn-block">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('user-tag', 'svg-icon'); ?> تغییر نوع کاربر
                    </button>

                    <button onclick="openModal('modal-user-discount-percent')" class="btn btn-soft-purple btn-block">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('gift', 'svg-icon'); ?> درصد تخفیف اختصاصی
                    </button>

                    <form method="POST" action="user.php?id=<?php echo urlencode((string)$user['id']); ?>" style="margin:0;" onsubmit="return confirm('آیا مطمئن هستید؟')">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="_action" value="user_agent">
                        <input type="hidden" name="agent" value="f">
                        <button type="submit" class="btn btn-soft-danger btn-block">
                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('user-xmark', 'svg-icon'); ?> حذف نمایندگی
                        </button>
                    </form>

                    <button onclick="openModal('modal-send-msg')" class="btn btn-outline btn-block">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('paper-plane', 'svg-icon'); ?> ارسال پیام
                    </button>
                </div>
            </div>

            <div class="card user-private-codes">
                <div class="card__head">
                    <div class="card__title"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('ticket', 'svg-icon svg-md'); ?><span>کدهای اختصاصی این کاربر</span></div>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
                    <button onclick="openModal('modal-user-discount')" class="btn btn-soft-info btn-sm">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon'); ?> کد تخفیف اختصاصی
                    </button>
                    <button onclick="openModal('modal-user-gift')" class="btn btn-soft-success btn-sm">
                        <?php echo \Faoxima\Admin\Web\Support\Icons::icon('plus', 'svg-icon'); ?> کد هدیه اختصاصی
                    </button>
                </div>
                <div class="table-wrap">
                    <table id="userCodesTable" class="app-table">
                        <thead><tr><th>کد</th><th>نوع</th><th>مقدار</th><th>بخش/کاربرد</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php
                        $__sectionFa = ['all'=>'همه','buy'=>'خرید','extend'=>'تمدید','volume'=>'حجم','time'=>'زمان','charge'=>'شارژ'];
                        $__vtFa = ['percent'=>'درصدی','amount'=>'مبلغی','free'=>'رایگان'];
                        if (empty($userCodes) && empty($userGiftCodes)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:22px 0; color:var(--text-muted);">کد اختصاصی‌ای برای این کاربر ثبت نشده است.</td></tr>
                        <?php else:
                            foreach ($userCodes as $c):
                                $vt = $c['value_type'] ?? 'percent';
                                if (!isset($__vtFa[$vt])) $vt = 'percent';
                                $sec = $c['section'] ?? 'all';
                                if (!isset($__sectionFa[$sec])) $sec = 'all';
                                $val = $vt === 'free' ? 'رایگان' : ($vt === 'amount' ? number_format((int)$c['price']) . ' <small class="currency-unit">تومان</small>' : ((string)$c['price'] . '٪'));
                        ?>
                            <tr>
                                <td data-label="کد"><code style="direction:ltr"><?php echo htmlspecialchars($c['codeDiscount'], ENT_QUOTES); ?></code></td>
                                <td data-label="نوع"><span class="badge badge-info">تخفیف · <?php echo $__vtFa[$vt]; ?></span></td>
                                <td data-label="مقدار"><?php echo $val; ?></td>
                                <td data-label="بخش/کاربرد"><?php echo $__sectionFa[$sec]; ?></td>
                                <td data-label="عملیات">
                                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                                        <button type="button" class="btn btn-sm btn-soft-info" title="ویرایش"
                                            data-id="<?php echo (int)$c['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($c['codeDiscount'], ENT_QUOTES); ?>"
                                            data-vt="<?php echo htmlspecialchars($vt, ENT_QUOTES); ?>"
                                            data-section="<?php echo htmlspecialchars($sec, ENT_QUOTES); ?>"
                                            data-price="<?php echo htmlspecialchars((string)$c['price'], ENT_QUOTES); ?>"
                                            data-limit="<?php echo (int)($c['limitDiscount'] ?? 0); ?>"
                                            onclick="editUserDiscount(this)">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen-to-square', 'svg-icon'); ?>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('این کد حذف شود؟');">
                                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="_action" value="user_code_delete">
                                            <input type="hidden" name="kind" value="discount">
                                            <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach;
                            foreach ($userGiftCodes as $g): ?>
                            <tr>
                                <td data-label="کد"><code style="direction:ltr"><?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?></code></td>
                                <td data-label="نوع"><span class="badge badge-success">هدیه</span></td>
                                <td data-label="مقدار"><?php echo number_format((int)($g['price'] ?? 0)); ?> <small class="currency-unit">تومان</small></td>
                                <td data-label="بخش/کاربرد">شارژ کیف پول</td>
                                <td data-label="عملیات">
                                    <div style="display:flex; gap:6px; justify-content:flex-end;">
                                        <button type="button" class="btn btn-sm btn-soft-info" title="ویرایش"
                                            data-id="<?php echo (int)$g['id']; ?>"
                                            data-code="<?php echo htmlspecialchars((string)$g['code'], ENT_QUOTES); ?>"
                                            data-price="<?php echo (int)($g['price'] ?? 0); ?>"
                                            data-limit="<?php echo (int)($g['limituse'] ?? 0); ?>"
                                            onclick="editUserGift(this)">
                                            <?php echo \Faoxima\Admin\Web\Support\Icons::icon('pen-to-square', 'svg-icon'); ?>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('این کد حذف شود؟');">
                                            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="_action" value="user_code_delete">
                                            <input type="hidden" name="kind" value="gift">
                                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-soft-danger"><?php echo \Faoxima\Admin\Web\Support\Icons::icon('trash', 'svg-icon'); ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</section>


<div id="modal-user-discount" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">کد تخفیف اختصاصی برای کاربر <?php echo htmlspecialchars((string)($user['id'] ?? ''), ENT_QUOTES); ?></span>
            <button class="modal-close" onclick="closeModal('modal-user-discount')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_discount_add">
            <div class="form-group">
                <label class="form-label">کد تخفیف</label>
                <input type="text" name="codeDiscount" class="form-control" style="direction:ltr;" required>
            </div>
            <div class="form-group">
                <label class="form-label">نوع تخفیف</label>
                <select name="value_type" id="add_d_vt" class="form-control" onchange="faoximaToggleDiscountMoney('add_d_price', this.value)">
                    <option value="percent">درصدی</option>
                    <option value="amount">مبلغی (تومان)</option>
                    <option value="free">رایگان</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">بخش کد</label>
                <select name="section" class="form-control">
                    <option value="all">همه بخش‌ها</option>
                    <option value="buy">خرید</option>
                    <option value="extend">تمدید</option>
                    <option value="volume">حجم اضافه</option>
                    <option value="time">زمان اضافه</option>
                    <option value="charge">شارژ کیف پول</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">مقدار</label>
                <input type="text" name="price" id="add_d_price" class="form-control" value="0" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">سقف کل استفاده <small>(۰ = نامحدود)</small></label>
                <input type="number" name="limitDiscount" class="form-control" value="0" min="0">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-user-discount')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">ثبت کد</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-user-gift" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">کد هدیه اختصاصی برای کاربر <?php echo htmlspecialchars((string)($user['id'] ?? ''), ENT_QUOTES); ?></span>
            <button class="modal-close" onclick="closeModal('modal-user-gift')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_gift_add">
            <div class="form-group">
                <label class="form-label">کد هدیه</label>
                <input type="text" name="code" class="form-control" style="direction:ltr;" required>
            </div>
            <div class="form-group">
                <label class="form-label">مبلغ (تومان)</label>
                <input type="text" name="gift_price" class="form-control" data-money min="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">سقف کل استفاده <small>(۰ = نامحدود)</small></label>
                <input type="number" name="gift_limit" class="form-control" value="0" min="0">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-user-gift')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">ثبت کد</button>
            </div>
        </form>
    </div>
</div>


<div id="modal-add-balance" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">افزایش موجودی کاربر</span>
            <button class="modal-close" onclick="closeModal('modal-add-balance')">&times;</button>
        </div>
        <form action="user.php?id=<?php echo urlencode((string)$user['id']); ?>" method="POST">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_balance_add">
            <div class="form-group">
                <label class="form-label">مبلغ (تومان)</label>
                <input type="text" name="priceadd" class="form-control" data-money placeholder="مثلا 50000" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">افزایش موجودی</button>
        </form>
    </div>
</div>

<div id="modal-low-balance" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">کسر موجودی کاربر</span>
            <button class="modal-close" onclick="closeModal('modal-low-balance')">&times;</button>
        </div>
        <form action="user.php?id=<?php echo urlencode((string)$user['id']); ?>" method="POST">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_balance_lower">
            <div class="form-group">
                <label class="form-label">مبلغ کسر (تومان)</label>
                <input type="text" name="pricelow" class="form-control" data-money placeholder="مثلا 10000" required>
            </div>
            <button type="submit" class="btn btn-soft-warning btn-block">کسر موجودی</button>
        </form>
    </div>
</div>

<div id="modal-change-agent" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">تغییر سطح کاربری</span>
            <button class="modal-close" onclick="closeModal('modal-change-agent')">&times;</button>
        </div>
        <form action="user.php?id=<?php echo urlencode((string)$user['id']); ?>" method="POST">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_agent">
            <div class="form-group">
                <label class="form-label">انتخاب سطح</label>
                <select name="agent" class="form-control">
                    <option value="f"  <?php if ($user['agent']=='f')  echo 'selected'; ?>>کاربر عادی</option>
                    <option value="n"  <?php if ($user['agent']=='n')  echo 'selected'; ?>>نماینده معمولی</option>
                    <option value="n2" <?php if ($user['agent']=='n2') echo 'selected'; ?>>نماینده پیشرفته</option>
                </select>
            </div>
            <button type="submit" class="btn btn-soft-purple btn-block">تغییر سطح</button>
        </form>
    </div>
</div>

<div id="modal-user-discount-percent" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">درصد تخفیف اختصاصی کاربر</span>
            <button class="modal-close" onclick="closeModal('modal-user-discount-percent')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_set_discount">
            <div class="form-group">
                <label class="form-label">درصد تخفیف (۰ تا ۱۰۰)</label>
                <input type="number" name="pricediscount" min="0" max="100" value="<?php echo (int)($user['pricediscount'] ?? 0); ?>" class="form-control" style="direction:ltr" required>
                <small style="opacity:.75">روی تمام خریدها و تمدیدهای کاربر اعمال می‌شود (به‌جز شارژ کیف پول). با مقدار بزرگ‌تر از صفر، کاربر نمی‌تواند از کد تخفیف استفاده کند.</small>
            </div>
            <button type="submit" class="btn btn-primary btn-block">ذخیره</button>
        </form>
    </div>
</div>

<div id="modal-send-msg" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">ارسال پیام خصوصی</span>
            <button class="modal-close" onclick="closeModal('modal-send-msg')">&times;</button>
        </div>
        <form action="user.php?id=<?php echo urlencode((string)$user['id']); ?>" method="POST">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_message">
            <div class="form-group">
                <label class="form-label">متن پیام</label>
                <textarea name="textmessage" class="form-control" rows="4" placeholder="پیام خود را بنویسید..." required></textarea>
            </div>
            <button type="submit" class="btn btn-outline btn-block">ارسال پیام</button>
        </form>
    </div>
</div>

<div id="modal-user-discount-edit" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش کد تخفیف اختصاصی</span>
            <button class="modal-close" onclick="closeModal('modal-user-discount-edit')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_discount_edit">
            <input type="hidden" name="id" id="eu_d_id">
            <div class="form-group">
                <label class="form-label">کد تخفیف</label>
                <input type="text" name="codeDiscount" id="eu_d_code" class="form-control" style="direction:ltr;" required>
            </div>
            <div class="form-group">
                <label class="form-label">نوع تخفیف</label>
                <select name="value_type" id="eu_d_vt" class="form-control" onchange="faoximaToggleDiscountMoney('eu_d_price', this.value)">
                    <option value="percent">درصدی</option>
                    <option value="amount">مبلغی (تومان)</option>
                    <option value="free">رایگان</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">بخش کد</label>
                <select name="section" id="eu_d_section" class="form-control">
                    <option value="all">همه بخش‌ها</option>
                    <option value="buy">خرید</option>
                    <option value="extend">تمدید</option>
                    <option value="volume">حجم اضافه</option>
                    <option value="time">زمان اضافه</option>
                    <option value="charge">شارژ کیف پول</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">مقدار</label>
                <input type="text" name="price" id="eu_d_price" class="form-control" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">سقف کل استفاده <small>(۰ = نامحدود)</small></label>
                <input type="number" name="limitDiscount" id="eu_d_limit" class="form-control" min="0">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-user-discount-edit')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-user-gift-edit" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">ویرایش کد هدیه اختصاصی</span>
            <button class="modal-close" onclick="closeModal('modal-user-gift-edit')">&times;</button>
        </div>
        <form method="POST" action="user.php?id=<?php echo urlencode((string)($user['id'] ?? '')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_action" value="user_gift_edit">
            <input type="hidden" name="id" id="eu_g_id">
            <div class="form-group">
                <label class="form-label">کد هدیه</label>
                <input type="text" name="code" id="eu_g_code" class="form-control" style="direction:ltr;" required>
            </div>
            <div class="form-group">
                <label class="form-label">مبلغ (تومان)</label>
                <input type="text" name="gift_price" id="eu_g_price" class="form-control" data-money min="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">سقف کل استفاده <small>(۰ = نامحدود)</small></label>
                <input type="number" name="gift_limit" id="eu_g_limit" class="form-control" min="0">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('modal-user-gift-edit')">انصراف</button>
                <button type="submit" class="btn btn-primary btn-sm">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>
<script>
function faoximaToggleDiscountMoney(inputId, type) {
    if (window.FaoximaMoneyInput) {
        window.FaoximaMoneyInput.setMode(document.getElementById(inputId), type === 'amount');
    }
}
function editUserDiscount(btn) {
    document.getElementById('eu_d_id').value      = btn.getAttribute('data-id');
    document.getElementById('eu_d_code').value    = btn.getAttribute('data-code');
    document.getElementById('eu_d_vt').value      = btn.getAttribute('data-vt');
    document.getElementById('eu_d_section').value = btn.getAttribute('data-section');
    document.getElementById('eu_d_price').value   = btn.getAttribute('data-price');
    document.getElementById('eu_d_limit').value   = btn.getAttribute('data-limit');
    faoximaToggleDiscountMoney('eu_d_price', btn.getAttribute('data-vt'));
    openModal('modal-user-discount-edit');
}
function editUserGift(btn) {
    document.getElementById('eu_g_id').value    = btn.getAttribute('data-id');
    document.getElementById('eu_g_code').value  = btn.getAttribute('data-code');
    document.getElementById('eu_g_price').value = window.FaoximaMoneyInput ? window.FaoximaMoneyInput.format(btn.getAttribute('data-price')) : btn.getAttribute('data-price');
    document.getElementById('eu_g_limit').value = btn.getAttribute('data-limit');
    openModal('modal-user-gift-edit');
}
</script>
</body>
</html>


