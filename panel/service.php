<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jdf.php';

$query = $pdo->prepare("SELECT * FROM admin WHERE username=:username");
$query->bindParam("username", $_SESSION["user"], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION["user"]) || !$result) {
    header('Location: login.php');
    return;
}

$query = $pdo->prepare("SELECT * FROM service_other ORDER BY id DESC");
$query->execute();
$listservices = $query->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>خدمات انجام شده | ربات فاکسیما</title>
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
                        <?php echo icon('package', 'svg-icon'); ?>
                        لیست خدمات انجام شده
                    </div>
                    <div class="page-head__sub">گزارش تمدید، انتقال، تغییر لوکیشن و سایر عملیات</div>
                </div>
            </div>

            <div class="card">
                <div class="table-wrap">
                    <table id="servicesTable" class="display app-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>آیدی کاربر</th>
                                <th>نام کانفیگ</th>
                                <th>تاریخ سفارش</th>
                                <th>قیمت (تومان)</th>
                                <th>نوع خدمت</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($listservices as $list):
                            $time = $list['time'];
                            if (is_numeric($time)) $time = jdate('Y/m/d | H:i', $time);
                            $typeText = 'نامشخص'; $badgeClass = 'badge-gray';
                            switch ($list['type']) {
                                case 'extend_user':           $typeText='تمدید سرویس';       $badgeClass='badge-success'; break;
                                case 'extend_user_by_admin':  $typeText='تمدید توسط ادمین';   $badgeClass='badge-info';    break;
                                case 'extra_user':            $typeText='حجم اضافه';         $badgeClass='badge-purple';  break;
                                case 'extra_time_user':       $typeText='زمان اضافه';        $badgeClass='badge-purple';  break;
                                case 'transfertouser':        $typeText='انتقال سرویس';      $badgeClass='badge-warning'; break;
                                case 'extends_not_user':      $typeText='تمدید (خارج از لیست)'; $badgeClass='badge-gray'; break;
                                case 'change_location':       $typeText='تغییر لوکیشن';      $badgeClass='badge-cyan';    break;
                                default:                      $typeText=htmlspecialchars($list['type'], ENT_QUOTES, 'UTF-8');
                            }
                            $price = ($list['price'] == 0) ? 'رایگان' : number_format($list['price']);
                        ?>
                            <tr>
                                <td data-label="شناسه"><?php echo $list['id']; ?></td>
                                <td data-label="آیدی کاربر">
                                    <a href="user.php?id=<?php echo $list['id_user']; ?>" class="text-link">
                                        <?php echo $list['id_user']; ?>
                                    </a>
                                </td>
                                <td data-label="نام کانفیگ" style="direction:ltr; text-align:right;"><?php echo htmlspecialchars($list['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="تاریخ سفارش" style="direction:ltr; text-align:right;"><?php echo $time; ?></td>
                                <td data-label="قیمت (تومان)"><?php echo $price; ?></td>
                                <td data-label="نوع خدمت"><span class="badge <?php echo $badgeClass; ?>"><?php echo $typeText; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</section>
<script src="js/datatable.js" defer>

</script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    FaoximaDT.init("#servicesTable");
  });
</script>
</body>
</html>


