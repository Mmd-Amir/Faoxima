<?php
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

$panelQuery = $pdo->prepare("SELECT name_panel FROM marzban_panel ORDER BY id ASC");
$panelQuery->execute();
$listpanel = $panelQuery->fetchAll(PDO::FETCH_ASSOC);

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

        $category = htmlspecialchars($_POST['category'], ENT_QUOTES, 'UTF-8');
        if ($product['category'] != $category) {
            update("product", "category", $category, "id", $id_product);
        }

        $locationPost = htmlspecialchars($_POST['Location'] ?? '', ENT_QUOTES, 'UTF-8');
        if ($product['Location'] != $locationPost) {
            update("product", "Location", $locationPost, "id", $id_product);
        }

        $note = htmlspecialchars($_POST['note'], ENT_QUOTES, 'UTF-8');
        if ($product['note'] != $note) {
            update("product", "note", $note, "id", $id_product);
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
    <link rel="stylesheet" href="css/theme.css">
<script src="js/theme.js" defer>

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
                            <label class="form-label">قیمت (تومان)</label>
                            <input type="number" name="price_product" class="form-control" value="<?php echo htmlspecialchars($product['price_product'], ENT_QUOTES, 'UTF-8'); ?>" required>
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
                        <label class="form-label">پنل (لوکیشن)</label>
                        <select name="Location" class="form-control" required>
                            <option value="/all" <?php if ($product['Location'] == '/all') echo 'selected'; ?>>تمامی پنل‌ها</option>
                            <?php
                            $currentLoc = (string)($product['Location'] ?? '');
                            $foundCurrent = ($currentLoc === '/all');
                            foreach ($listpanel as $panel):
                                $pname = (string)($panel['name_panel'] ?? '');
                                if ($pname === $currentLoc) $foundCurrent = true;
                            ?>
                                <option value="<?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($pname === $currentLoc) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (!$foundCurrent && $currentLoc !== ''): ?>
                                <option value="<?php echo htmlspecialchars($currentLoc, ENT_QUOTES, 'UTF-8'); ?>" selected>
                                    <?php echo htmlspecialchars($currentLoc, ENT_QUOTES, 'UTF-8'); ?> (حذف‌شده)
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">دسته‌بندی</label>
                        <input type="text" name="category" class="form-control" value="<?php echo htmlspecialchars($product['category'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">یادداشت</label>
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

</body>
</html>


