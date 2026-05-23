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

$query = $pdo->prepare("SELECT * FROM product ORDER BY id ASC");
$query->execute();
$listinvoice = $query->fetchAll();

$query = $pdo->prepare("SELECT * FROM marzban_panel");
$query->execute();
$listpanel = $query->fetchAll();


$nameProduct = $_POST['nameproduct'] ?? null;
if (!empty($nameProduct)) {
    $randomString = bin2hex(random_bytes(2));
    $userdata['data_limit_reset'] = "no_reset";

    $product_count = select("product", "*", "name_product", $nameProduct, "count");
    if ($product_count != 0) {
        echo "<script>
alert('محصول از قبل وجود دارد'); window.location.href='product.php';
</script>";
        return;
    }

    $hidepanel       = "{}";
    $priceProduct    = $_POST['price_product']    ?? '';
    $volumeProduct   = $_POST['volume_product']   ?? '';
    $serviceTime     = $_POST['time_product']     ?? '';
    $location        = $_POST['namepanel']        ?? '';
    $agentProduct    = $_POST['agent_product']    ?? '';
    $category        = $_POST['cetegory_product'] ?? '';
    $note            = $_POST['note_product']     ?? '';
    $dataLimitReset  = $userdata['data_limit_reset'];

    $stmt = $pdo->prepare("INSERT IGNORE INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status) VALUES (:name_product,:code_product,:price_product,:Volume_constraint,:Service_time,:Location,:agent,:data_limit_reset,:note,:category,:hide_panel,'0')");
    $stmt->bindParam(':name_product',     $nameProduct, PDO::PARAM_STR);
    $stmt->bindParam(':code_product',     $randomString);
    $stmt->bindParam(':price_product',    $priceProduct, PDO::PARAM_STR);
    $stmt->bindParam(':Volume_constraint',$volumeProduct, PDO::PARAM_STR);
    $stmt->bindParam(':Service_time',     $serviceTime, PDO::PARAM_STR);
    $stmt->bindParam(':Location',         $location, PDO::PARAM_STR);
    $stmt->bindParam(':agent',            $agentProduct, PDO::PARAM_STR);
    $stmt->bindParam(':data_limit_reset', $dataLimitReset);
    $stmt->bindParam(':category',         $category, PDO::PARAM_STR);
    $stmt->bindParam(':note',             $note, PDO::PARAM_STR);
    $stmt->bindParam(':hide_panel',       $hidepanel);
    $stmt->execute();

    header("Location: product.php");
    exit;
}


if (isset($_GET['oneproduct'], $_GET['toweproduct']) && $_GET['oneproduct'] !== '' && $_GET['toweproduct'] !== '') {
    update("product", "id", 10000, "id", $_GET['oneproduct']);
    update("product", "id", intval($_GET['oneproduct']),  "id", intval($_GET['toweproduct']));
    update("product", "id", intval($_GET['toweproduct']), "id", 10000);
    header("Location: product.php");
    exit;
}


if (isset($_GET['removeid']) && $_GET['removeid'] !== '') {
    $stmt = $pdo->prepare("DELETE FROM product WHERE id = :id");
    $stmt->bindParam(':id', $_GET['removeid']);
    $stmt->execute();
    header("Location: product.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>مدیریت محصولات | ربات فاکسیما</title>
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
                        <?php echo icon('grid', 'svg-icon svg-lg'); ?>
                        لیست محصولات
                    </div>
                    <div class="page-head__sub">مدیریت محصولات و پنل‌های مرزبان</div>
                </div>
                <div class="chip-row">
                    <button onclick="openModal('modal-add-product')" class="btn btn-primary btn-sm">
                        <?php echo icon('plus', 'svg-icon'); ?> افزودن محصول
                    </button>
                    <button onclick="openModal('modal-move-product')" class="btn btn-soft-purple btn-sm">
                        <?php echo icon('arrow-right-arrow-left', 'svg-icon'); ?> جابجایی ردیف
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table id="productsTable" class="display app-table" style="width:100%" data-mdt-filter="5,6,7">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>نام محصول</th>
                                <th>قیمت</th>
                                <th>حجم (GB)</th>
                                <th>زمان (روز)</th>
                                <th>لوکیشن</th>
                                <th>گروه کاربری</th>
                                <th>دسته‌بندی</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listinvoice as $list):
                            $category = ($list['category'] == null) ? 'ندارد' : htmlspecialchars($list['category'], ENT_QUOTES, 'UTF-8');
                            $agent_type = 'عادی'; $agent_badge = 'badge-gray';
                            if ($list['agent'] == 'n')  { $agent_type = 'نماینده';      $agent_badge = 'badge-purple';  }
                            if ($list['agent'] == 'n2') { $agent_type = 'نماینده ویژه';  $agent_badge = 'badge-warning'; }
                        ?>
                            <tr>
                                <td data-label="شناسه"><?php echo $list['id']; ?></td>
                                <td data-label="نام محصول"><?php echo htmlspecialchars($list['name_product'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="قیمت"><span class="badge badge-success"><?php echo number_format($list['price_product']); ?></span></td>
                                <td data-label="حجم (GB)"><span class="badge badge-info"><?php echo (int)$list['Volume_constraint']; ?></span></td>
                                <td data-label="زمان (روز)"><span class="badge badge-warning"><?php echo (int)$list['Service_time']; ?></span></td>
                                <td data-label="لوکیشن"><span class="badge badge-cyan"><?php echo htmlspecialchars($list['Location'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td data-label="گروه کاربری"><span class="badge <?php echo $agent_badge; ?>"><?php echo $agent_type; ?></span></td>
                                <td data-label="دسته‌بندی"><?php echo $category; ?></td>
                                <td data-label="عملیات" class="cell-actions">
                                    <div style="display:inline-flex; gap:6px;">
                                        <a href="productedit.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-soft-info" title="ویرایش">
                                            <?php echo icon('pen', 'svg-icon'); ?>
                                        </a>
                                        <a href="product.php?removeid=<?php echo $list['id']; ?>" class="btn btn-sm btn-soft-danger" title="حذف"
                                           onclick="return confirm('آیا از حذف این محصول مطمئن هستید؟')">
                                            <?php echo icon('trash', 'svg-icon'); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</section>


<div id="modal-add-product" class="modal-overlay">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-head">
            <span class="modal-head__title">افزودن محصول جدید</span>
            <button class="modal-close" onclick="closeModal('modal-add-product')">&times;</button>
        </div>
        <form action="product.php" method="POST">
            <div class="form-group">
                <label class="form-label">نام محصول</label>
                <input type="text" name="nameproduct" class="form-control" placeholder="نام محصول را وارد کنید" required>
            </div>

            <div class="form-group">
                <label class="form-label">پنل (موقعیت)</label>
                <select name="namepanel" class="form-control" required>
                    <option value="/all">تمامی پنل‌ها</option>
                    <?php foreach ($listpanel as $panel): ?>
                        <option value="<?php echo htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($panel['name_panel'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">قیمت (تومان)</label>
                    <input type="number" name="price_product" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">حجم (GB)</label>
                    <input type="number" name="volume_product" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">زمان (روز)</label>
                    <input type="number" name="time_product" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع کاربر</label>
                    <select name="agent_product" class="form-control" required>
                        <option value="f">کاربر عادی</option>
                        <option value="n">نماینده</option>
                        <option value="n2">نماینده پیشرفته</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">دسته‌بندی</label>
                <input type="text" name="cetegory_product" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">توضیحات</label>
                <input type="text" name="note_product" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">افزودن محصول</button>
        </form>
    </div>
</div>


<div id="modal-move-product" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <span class="modal-head__title">جابجایی ردیف محصولات</span>
            <button class="modal-close" onclick="closeModal('modal-move-product')">&times;</button>
        </div>
        <form action="product.php" method="GET">
            <div class="form-group">
                <label class="form-label">شناسه محصول اول</label>
                <input type="number" name="oneproduct" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">شناسه محصول دوم</label>
                <input type="number" name="toweproduct" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-soft-purple btn-block">جابجایی</button>
        </form>
    </div>
</div>
<script src="js/datatable.js" defer>

</script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    FaoximaDT.init("#productsTable");
  });
</script>
</body>
</html>


