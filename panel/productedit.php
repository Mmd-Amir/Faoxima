<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_httponly', '1');
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/../function.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

$statusmessage = false;
$infomesssage  = "";
$id_product    = htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8');
$product       = select("product", "*", "id", $id_product, "select");

$panelQuery = $pdo->prepare("SELECT name_panel, type FROM marzban_panel ORDER BY id ASC");
$panelQuery->execute();
$listpanel = $panelQuery->fetchAll(PDO::FETCH_ASSOC);

$panelTypeMap = [];
foreach ($listpanel as $panelRow) {
    $panelTypeMap[$panelRow['name_panel']] = $panelRow['type'];
}

$catQuery = $pdo->prepare("SELECT remark FROM category ORDER BY id ASC");
$catQuery->execute();
$listcategory = $catQuery->fetchAll(PDO::FETCH_COLUMN);

if ($product == false) {
    $statusmessage = true;
    $infomesssage  = "محصول مورد نظر یافت نشد!";
} else {
    if (isset($_GET['action']) && $_GET['action'] == "save") {
        $name_product = htmlspecialchars($_POST['name_product'], ENT_QUOTES, 'UTF-8');
        $prodcutcheck = select("product", "*", "name_product", $name_product, "count");
        if ($product['name_product'] != $name_product && $prodcutcheck != 0) {
            $statusmessage = true;
            $infomesssage  = "نام محصول تکراری است.";
        } else {
            if ($product['name_product'] != $name_product) {
                update("product", "name_product", $name_product, "id", $id_product);
            }
        }

        $price_product = htmlspecialchars($_POST['price_product'], ENT_QUOTES, 'UTF-8');
        if (!is_numeric($price_product)) {
            $statusmessage = true; $infomesssage = "مبلغ محصول باید عدد باشد";
        } elseif ($product['price_product'] != $price_product) {
            update("product", "price_product", $price_product, "id", $id_product);
        }

        $Volume_constraint = htmlspecialchars($_POST['Volume_constraint'], ENT_QUOTES, 'UTF-8');
        if (!is_numeric($Volume_constraint)) {
            $statusmessage = true; $infomesssage = "حجم محصول باید عدد باشد";
        } elseif ($product['Volume_constraint'] != $Volume_constraint) {
            update("product", "Volume_constraint", $Volume_constraint, "id", $id_product);
        }

        $Service_time = htmlspecialchars($_POST['Service_time'], ENT_QUOTES, 'UTF-8');
        if (!is_numeric($Service_time)) {
            $statusmessage = true; $infomesssage = "زمان محصول باید عدد باشد";
        } elseif ($product['Service_time'] != $Service_time) {
            update("product", "Service_time", $Service_time, "id", $id_product);
        }

        $agent = htmlspecialchars($_POST['agent'], ENT_QUOTES, 'UTF-8');
        if (!in_array($agent, ['f', 'n', 'n2'])) {
            $statusmessage = true; $infomesssage = "گروه کاربری نامعتبر است";
        } elseif ($product['agent'] != $agent) {
            update("product", "agent", $agent, "id", $id_product);
        }

        $categoryArr = explode(',', (string)($_POST['category_csv'] ?? ''));
        $newCategory = trim((string)($_POST['category_new'] ?? ''));
        if ($newCategory !== '') $categoryArr[] = $newCategory;
        $categoryArr = array_values(array_unique(array_filter(array_map(function ($v) {
            return htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8');
        }, $categoryArr), function ($v) { return $v !== ''; })));
        $category = implode(',', $categoryArr);
        foreach ($categoryArr as $catValue) {
            $catCheck = $pdo->prepare("SELECT COUNT(*) FROM category WHERE remark = :r");
            $catCheck->execute([':r' => $catValue]);
            if ((int)$catCheck->fetchColumn() === 0) {
                $pdo->prepare("INSERT IGNORE INTO category (remark) VALUES (:r)")->execute([':r' => $catValue]);
            }
        }
        if ($product['category'] != $category) {
            update("product", "category", $category, "id", $id_product);
        }

        $locationArr = explode(',', (string)($_POST['Location_csv'] ?? ''));
        $locationArr = array_values(array_unique(array_filter(array_map(function ($v) {
            return htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8');
        }, $locationArr), function ($v) { return $v !== ''; })));
        if (in_array('/all', $locationArr, true)) $locationArr = ['/all'];
        $locationPost = implode(',', $locationArr);
        if ($product['Location'] != $locationPost) {
            update("product", "Location", $locationPost, "id", $id_product);
        }

        $note = htmlspecialchars($_POST['note'], ENT_QUOTES, 'UTF-8');
        if ($product['note'] != $note) {
            update("product", "note", $note, "id", $id_product);
        }

        $ipLimitRaw = trim((string)($_POST['ip_limit'] ?? '0'));
        if ($ipLimitRaw === '' || !ctype_digit($ipLimitRaw) || (int)$ipLimitRaw > 50) {
            $statusmessage = true; $infomesssage = "محدودیت IP باید عددی بین ۰ تا ۵۰ باشد";
        } else {
            $ipLimit = (string)(int)$ipLimitRaw;
            if ((string)($product['ip_limit'] ?? '0') != $ipLimit) {
                update("product", "ip_limit", $ipLimit, "id", $id_product);
            }
        }

        $hwidLimitRaw = trim((string)($_POST['hwid_limit'] ?? '0'));
        if ($hwidLimitRaw === '' || !ctype_digit($hwidLimitRaw) || (int)$hwidLimitRaw > 50) {
            $statusmessage = true; $infomesssage = "محدودیت HWID باید عددی بین ۰ تا ۵۰ باشد";
        } else {
            $hwidLimit = (string)(int)$hwidLimitRaw;
            if ((string)($product['hwid_limit'] ?? '0') != $hwidLimit) {
                update("product", "hwid_limit", $hwidLimit, "id", $id_product);
            }
        }

        $symbolicLimitUsersRaw = trim((string)($_POST['symbolic_limit_users'] ?? '0'));
        if ($symbolicLimitUsersRaw === '' || !ctype_digit($symbolicLimitUsersRaw) || (int)$symbolicLimitUsersRaw > 50) {
            $statusmessage = true; $infomesssage = "تعداد کاربر Limiet باید عددی بین ۰ تا ۵۰ باشد";
        } else {
            $symbolicLimitUsers = (string)(int)$symbolicLimitUsersRaw;
            $symbolicLimitEnabled = (!empty($_POST['symbolic_limit_enabled']) && (int)$symbolicLimitUsers > 0) ? '1' : '0';
            if ((string)($product['symbolic_limit_users'] ?? '0') != $symbolicLimitUsers) {
                update("product", "symbolic_limit_users", $symbolicLimitUsers, "id", $id_product);
            }
            if ((string)($product['symbolic_limit_enabled'] ?? '0') != $symbolicLimitEnabled) {
                update("product", "symbolic_limit_enabled", $symbolicLimitEnabled, "id", $id_product);
            }
        }

        if (!$statusmessage) {
            header('Location: product.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>ویرایش محصول | ربات فاکسیما</title>
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
                        <?php echo icon('pen-to-square', 'svg-icon svg-lg'); ?>
                        ویرایش محصول
                    </div>
                    <div class="page-head__sub">
                        <a href="product.php" class="text-link">
                            <?php echo icon('arrow-right', 'svg-icon'); ?> بازگشت به لیست محصولات
                        </a>
                    </div>
                </div>
            </div>

            <div class="card" style="max-width:820px; margin: 0 auto;">

                <?php if ($statusmessage): ?>
                    <div class="alert alert-error">
                        <?php echo icon('circle-exclamation', 'svg-icon'); ?>
                        <span><?php echo $infomesssage; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($product): ?>
                <form action="productedit.php?action=save&id=<?php echo $id_product; ?>" method="POST">

                    <div class="form-group">
                        <label class="form-label">نام محصول</label>
                        <input type="text" name="name_product" class="form-control" value="<?php echo htmlspecialchars($product['name_product'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">قیمت (T)</label>
                            <input type="text" name="price_product" class="form-control" data-money value="<?php echo htmlspecialchars($product['price_product'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">حجم (GB)</label>
                            <input type="number" name="Volume_constraint" class="form-control" value="<?php echo htmlspecialchars($product['Volume_constraint'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">زمان (روز)</label>
                            <input type="number" name="Service_time" class="form-control" value="<?php echo htmlspecialchars($product['Service_time'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">نوع کاربر</label>
                            <select name="agent" class="form-control">
                                <option value="f"  <?php if ($product['agent']=='f')  echo 'selected'; ?>>کاربر عادی</option>
                                <option value="n"  <?php if ($product['agent']=='n')  echo 'selected'; ?>>نماینده معمولی</option>
                                <option value="n2" <?php if ($product['agent']=='n2') echo 'selected'; ?>>نماینده پیشرفته</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">پنل (لوکیشن) <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                        <?php
                        $currentLocArr = array_values(array_filter(array_map('trim', explode(',', (string)($product['Location'] ?? '')))));
                        $isAllSelected = in_array('/all', $currentLocArr, true);
                        $matchedPanels = [];
                        ?>
                        <div class="chip-row" id="edit_product_panel_picker" data-panel-picker>
                            <button type="button" class="chip<?php if ($isAllSelected) echo ' is-active'; ?>" data-panel-chip data-value="/all"><span>تمامی پنل‌ها</span></button>
                            <?php foreach ($listpanel as $panel):
                                $pname = (string)($panel['name_panel'] ?? '');
                                $isSel = !$isAllSelected && in_array($pname, $currentLocArr, true);
                                if ($isSel) $matchedPanels[] = $pname;
                            ?>
                                <button type="button" class="chip<?php if ($isSel) echo ' is-active'; ?>" data-panel-chip data-value="<?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span><?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?></span>
                                </button>
                            <?php endforeach; ?>
                            <?php
                            $missingPanels = $isAllSelected ? [] : array_diff($currentLocArr, $matchedPanels);
                            foreach ($missingPanels as $missingLoc):
                                if ($missingLoc === '') continue;
                            ?>
                                <button type="button" class="chip is-active" data-panel-chip data-value="<?php echo htmlspecialchars($missingLoc, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span><?php echo htmlspecialchars($missingLoc, ENT_QUOTES, 'UTF-8'); ?> (حذف‌شده)</span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="Location_csv" id="edit_product_panel_hidden" data-panel-hidden value="<?php echo htmlspecialchars((string)($product['Location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">محدودیت IP</label>
                        <input type="number" name="ip_limit" id="edit_product_ip_limit" class="form-control" min="0" max="50" step="1" value="<?php echo htmlspecialchars((string)($product['ip_limit'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                        <small style="color:var(--text-muted)" id="edit_product_ip_limit_hint">مقدار ۰ به معنی بدون محدودیت است. تغییر این مقدار فقط روی خریدهای بعدی اعمال می‌شود و سرویس‌های فعال فعلی را تغییر نمی‌دهد.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">محدودیت HWID (تعداد دستگاه)</label>
                        <input type="number" name="hwid_limit" id="edit_product_hwid_limit" class="form-control" min="0" max="50" step="1" value="<?php echo htmlspecialchars((string)($product['hwid_limit'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                        <small style="color:var(--text-muted)" id="edit_product_hwid_limit_hint">تعداد دستگاه‌های مجاز برای اتصال از طریق سابسکریپشن (مخصوص پنل‌های PasarGuard، Remnawave و ثنایی (3x-ui)). مقدار ۰ به معنی بدون محدودیت است.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" name="symbolic_limit_enabled" id="edit_product_symbolic_limit_enabled" value="1" <?php if (($product['symbolic_limit_enabled'] ?? '0') == '1') echo 'checked'; ?> onchange="document.getElementById('edit_product_symbolic_limit_users').disabled = !this.checked;">
                            Limiet (محدودیت نمایشی)
                        </label>
                        <input type="number" name="symbolic_limit_users" id="edit_product_symbolic_limit_users" class="form-control" min="1" max="50" step="1" value="<?php echo htmlspecialchars((string)($product['symbolic_limit_users'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>" <?php if (($product['symbolic_limit_enabled'] ?? '0') != '1') echo 'disabled'; ?>>
                        <small style="color:var(--text-muted)">این گزینه کاملاً نمایشی و مستقل از محدودیت واقعی IP (fail2ban) است و برای هر نوع پنل قابل استفاده است. در صورت فعال بودن، فقط تعداد کاربر تعیین‌شده به کاربر نمایش داده می‌شود و هیچ اطلاعات IP/دستگاه متصل نشان داده نمی‌شود.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">دسته‌بندی <small style="color:var(--text-muted)">(می‌توانید چند مورد انتخاب کنید)</small></label>
                        <?php
                        $currentCatArr = array_values(array_filter(array_map('trim', explode(',', (string)($product['category'] ?? '')))));
                        $matchedCats = [];
                        ?>
                        <div class="chip-row" id="edit_product_category_picker" data-category-picker>
                            <?php foreach ($listcategory as $catName):
                                $isSel = in_array($catName, $currentCatArr, true);
                                if ($isSel) $matchedCats[] = $catName;
                            ?>
                                <button type="button" class="chip<?php if ($isSel) echo ' is-active'; ?>" data-category-chip data-value="<?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?></span></button>
                            <?php endforeach; ?>
                            <?php
                            $missingCats = array_diff($currentCatArr, $matchedCats);
                            foreach ($missingCats as $missingCat):
                                if ($missingCat === '') continue;
                            ?>
                                <button type="button" class="chip is-active" data-category-chip data-value="<?php echo htmlspecialchars($missingCat, ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($missingCat, ENT_QUOTES, 'UTF-8'); ?></span></button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="category_csv" id="edit_product_category_hidden" data-category-hidden value="<?php echo htmlspecialchars((string)($product['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="text" name="category_new" class="form-control" style="margin-top:8px;" placeholder="دسته‌بندی جدید (اختیاری)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">یادداشت (اختیاری)</label>
                        <textarea name="note" class="form-control" rows="3"><?php echo htmlspecialchars($product['note'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo icon('check', 'svg-icon'); ?> ذخیره تغییرات
                    </button>

                </form>
                <?php endif; ?>
            </div>

        </div>
    </section>
</section>

<script>
  var faoximaPanelTypeMap = <?php echo json_encode($panelTypeMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
  function faoximaChipPickerSync(picker, hiddenAttr, chipAttr) {
    var hidden = picker.parentElement.querySelector('[' + hiddenAttr + ']');
    if (!hidden) return;
    var values = Array.from(picker.querySelectorAll('.chip.is-active')).map(function (c) { return c.getAttribute('data-value'); });
    hidden.value = values.join(',');
  }
  function faoximaCategoryPickerSync(picker) {
    faoximaChipPickerSync(picker, 'data-category-hidden', 'data-category-chip');
  }
  function faoximaPanelPickerSync(picker, clickedChip) {
    var allChip = picker.querySelector('.chip[data-panel-chip][data-value="/all"]');
    if (allChip) {
      if (clickedChip === allChip) {
        if (allChip.classList.contains('is-active')) {
          picker.querySelectorAll('.chip[data-panel-chip]').forEach(function (c) { if (c !== allChip) c.classList.remove('is-active'); });
        }
      } else if (clickedChip && clickedChip.classList.contains('is-active')) {
        allChip.classList.remove('is-active');
      }
      var anyActive = Array.from(picker.querySelectorAll('.chip[data-panel-chip]')).some(function (c) { return c.classList.contains('is-active'); });
      if (!anyActive) allChip.classList.add('is-active');
    }
    faoximaChipPickerSync(picker, 'data-panel-hidden', 'data-panel-chip');
    faoximaUpdateIpLimitHint('edit');
    faoximaUpdateHwidLimitHint('edit');
  }
  function faoximaBindChipPicker(pickerRootSelector, chipSelector, syncFn) {
    document.querySelectorAll(pickerRootSelector).forEach(function (picker) {
      picker.addEventListener('click', function (ev) {
        var chip = ev.target.closest(chipSelector);
        if (!chip || !picker.contains(chip)) return;
        chip.classList.toggle('is-active');
        syncFn(picker, chip);
      });
    });
  }
  faoximaBindChipPicker('[data-category-picker]', '.chip[data-category-chip]', faoximaCategoryPickerSync);
  faoximaBindChipPicker('[data-panel-picker]', '.chip[data-panel-chip]', faoximaPanelPickerSync);

  function faoximaUpdateIpLimitHint(prefix) {
    var hidden = document.getElementById(prefix + '_product_panel_hidden');
    var hint = document.getElementById(prefix + '_product_ip_limit_hint');
    if (!hidden || !hint) return;
    var values = hidden.value.split(',').filter(function (v) { return v !== ''; });
    var value = values[0] || '/all';
    if (value === '/all') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این مقدار فقط هنگام فروش از یک پنل ثنایی (3x-ui) با حالت توکنی یا Rebecca اعمال می‌شود.';
      return;
    }
    var type = faoximaPanelTypeMap[value];
    if (type === 'x-ui_single') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع ثنایی است؛ در حالت توکنی این مقدار اعمال می‌شود.';
      fetch('product.php?ajax=fail2ban_check&panel=' + encodeURIComponent(value), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok && !j.usable) {
            hint.textContent += ' ⚠️ محدودیت IP روی این پنل ذخیره می‌شود، اما Fail2ban فعال یا قابل استفاده نیست؛ ممکن است محدودیت عملاً اجرا نشود.';
          }
        })
        .catch(function () {});
    } else if (type === 'rebecca') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع Rebecca است؛ این مقدار مستقیماً به پنل ارسال می‌شود.';
    } else {
      hint.textContent = 'این پنل از نوع ثنایی (3x-ui) یا Rebecca نیست — مقدار محدودیت IP برای آن اعمال نخواهد شد.';
    }
  }
  function faoximaUpdateHwidLimitHint(prefix) {
    var hidden = document.getElementById(prefix + '_product_panel_hidden');
    var hint = document.getElementById(prefix + '_product_hwid_limit_hint');
    if (!hidden || !hint) return;
    var values = hidden.value.split(',').filter(function (v) { return v !== ''; });
    var value = values[0] || '/all';
    if (value === '/all') {
      hint.textContent = 'تعداد دستگاه‌های مجاز برای اتصال از طریق سابسکریپشن (مخصوص پنل‌های PasarGuard، Remnawave و ثنایی (3x-ui)). مقدار ۰ به معنی بدون محدودیت است.';
      return;
    }
    var type = faoximaPanelTypeMap[value];
    if (type === 'pasarguard') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع PasarGuard است؛ این مقدار هنگام ساخت کاربر به پنل ارسال می‌شود.';
    } else if (type === 'remnawave') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع Remnawave است؛ این مقدار هنگام ساخت کاربر به پنل ارسال می‌شود.';
    } else if (type === 'x-ui_single') {
      hint.textContent = 'مقدار ۰ به معنی بدون محدودیت است. این پنل از نوع ثنایی (3x-ui) است؛ در حالت توکنی این مقدار هنگام ساخت کاربر به پنل ارسال می‌شود.';
    } else {
      hint.textContent = 'این پنل از نوع PasarGuard، Remnawave یا ثنایی (3x-ui) نیست — مقدار محدودیت HWID برای آن اعمال نخواهد شد.';
    }
  }
  document.addEventListener('DOMContentLoaded', function () {
    faoximaUpdateIpLimitHint('edit');
    faoximaUpdateHwidLimitHint('edit');
  });
</script>
</body>
</html>


